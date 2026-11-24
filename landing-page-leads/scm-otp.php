<?php
if (!defined('ABSPATH')) { exit; }

// This policy belongs only to /oracle-fusion-scm-course-it. Other form adapters
// keep their existing verification rules and transient namespaces.
trait TLIT_LP_SCM_OTP {
    static function scm_otp($request) {
        $p = self::body($request);
        $phone = self::phone($p['phone'] ?? '');
        $name = self::clean($p['name'] ?? '', 81);
        $id = $p['registrationId'] ?? '';
        $intent = $p['intent'] ?? '';
        $placement = $p['source'] ?? '';
        if (!$phone || strlen($name) < 2 || strlen($name) > 80 || !is_string($id) ||
            !preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/Di', $id) ||
            !in_array($intent, ['demo', 'brochure'], true) || !in_array($placement, ['inline', 'popup'], true) ||
            ($intent === 'brochure' && $placement !== 'popup')) {
            return self::error('Enter your name and a valid Indian (+91) mobile number.');
        }
        if (!function_exists('techleadsit_send_vispl_sms')) {
            return self::error('SMS verification is unavailable. Please contact the course advisor.', 503);
        }
        if (!self::rate('ip-'.($_SERVER['REMOTE_ADDR'] ?? ''), 10, 3600) || !self::rate('phone-'.$phone, 3, 600)) {
            return self::error('Too many code requests. Please try again later.', 429);
        }
        $lock = self::lock('sms'.$phone);
        if (!$lock) return self::error('A code request is in progress. Please wait.', 409);
        try {
            $cooldown = 'tlit_lp_cool_'.self::hash($phone);
            if (get_transient($cooldown)) return self::error('Wait 30 seconds before requesting another code.', 429);
            set_transient($cooldown, 1, 30);
            $code = (string) random_int(1000, 9999);
            $challenge = bin2hex(random_bytes(24));
            $key = 'tlit_lp_scm_otp_'.self::hash($challenge);
            $state = [
                'scope' => 'oracle-fusion-scm-course-it', 'phone' => $phone,
                'registration_id' => $id, 'intent' => $intent, 'placement' => $placement,
                'hash' => self::hash($challenge.'|'.$code), 'tries' => 0, 'expires' => time() + 300,
            ];
            if (!set_transient($key, $state, 300)) return self::error('We could not prepare verification. Please try again.', 503);
            $sent = techleadsit_send_vispl_sms($phone, $code, $name);
            if (empty($sent['success'])) {
                delete_transient($key);
                return self::error('We could not send the SMS code. Please try again or contact the course advisor.', 502);
            }
            $active = 'tlit_lp_scm_active_'.self::hash($phone);
            $old = get_transient($active);
            if (!set_transient($active, $challenge, 300)) {
                delete_transient($key);
                return self::error('We could not prepare verification. Please try again.', 503);
            }
            if (is_string($old) && $old !== '') delete_transient('tlit_lp_scm_otp_'.self::hash($old));
            return ['success' => true, 'challenge' => $challenge, 'expiresIn' => 300, 'resendAfter' => 30];
        } finally { delete_option($lock); }
    }

    static function scm_save_verified_registration(array $form, array $payload, $source) {
        $challenge = $form['challenge'] ?? '';
        $code = $form['otp'] ?? '';
        if (!is_string($challenge) || !preg_match('/^[a-f0-9]{48}$/D', $challenge) ||
            !is_string($code) || !preg_match('/^[0-9]{4}$/D', $code)) {
            return self::error('Enter the four-digit SMS code to verify your mobile number.');
        }
        $lock = self::lock('scm-verify'.$challenge);
        if (!$lock) return self::error('Verification is in progress. Please wait.', 409);
        try {
            $key = 'tlit_lp_scm_otp_'.self::hash($challenge);
            $state = get_transient($key);
            if (!is_array($state) || ($state['scope'] ?? '') !== 'oracle-fusion-scm-course-it' ||
                ($state['phone'] ?? '') !== self::phone($payload['phone']) ||
                ($state['registration_id'] ?? '') !== $payload['request_id'] ||
                ($state['intent'] ?? '') !== ($form['intent'] ?? '') ||
                ($state['placement'] ?? '') !== ($form['source'] ?? '') ||
                ($state['expires'] ?? 0) <= time() || ($state['tries'] ?? 5) >= 5) {
                return self::error('This code expired or no longer matches your request. Send a new code.');
            }
            if (!hash_equals($state['hash'], self::hash($challenge.'|'.$code))) {
                $state['tries']++;
                set_transient($key, $state, max(1, $state['expires'] - time()));
                return self::error('Incorrect code. Please check the SMS and try again.');
            }
            $payload['details']['phone_verification'] = 'OTP verified via four-digit SMS';
            // The challenge authorizes one registration ID only. Keep it until
            // expiry so a lost response/database failure can be retried; ingest
            // rejects changed payloads and returns the same receipt for exact retries.
            return self::ingest($payload, ['source' => $source, 'otp_verified' => true]);
        } finally { delete_option($lock); }
    }
}
