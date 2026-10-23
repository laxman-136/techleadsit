<?php
// SCM-only registration relay. Never routes contact details through the CRM.
if (!defined('ABSPATH')) exit;

function tlit_scm_config() {
    return (array) get_option('tlit_scm_registration', array());
}
function tlit_scm_ready($c) {
    return preg_match('#^https://script\.google\.com/macros/s/[A-Za-z0-9_-]+/exec$#D', $c['url'] ?? '')
        && preg_match('/^[a-f0-9]{64}$/D', $c['secret'] ?? '')
        && !empty($c['sitekey']) && !empty($c['captcha_secret']);
}
function tlit_scm_reply($data, $status = 200) {
    return new WP_REST_Response($data, $status, array('Cache-Control' => 'no-store'));
}
function tlit_scm_failure($message, $status) {
    return tlit_scm_reply(array('success' => false, 'message' => $message), $status);
}
function tlit_scm_allowed($c) {
    return tlit_scm_ready($c) && (!empty($c['enabled']) || current_user_can('manage_options'));
}
add_action('rest_api_init', function () {
    register_rest_route('techleadsit-scm/v1', '/config', array('methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => function () {
        $c = tlit_scm_config();
        $allowed = tlit_scm_allowed($c);
        return tlit_scm_reply(array('enabled' => (bool) $allowed, 'sitekey' => $allowed ? $c['sitekey'] : '', 'testing' => $allowed && empty($c['enabled'])));
    }));
    register_rest_route('techleadsit-scm/v1', '/register', array('methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'tlit_scm_register'));
});

// Atomic counter protected by a DB unique-key lock. Only salted hashes are stored.
function tlit_scm_rate($identity, $limit, $seconds) {
    $key = 'scm_r_' . hash_hmac('sha256', $identity . ':' . floor(time() / $seconds), wp_salt('auth'));
    $lock = $key . '_lock';
    if (!add_option($lock, time(), '', false)) return false;
    try {
        $count = (int) get_transient($key);
        if ($count >= $limit) return false;
        set_transient($key, $count + 1, $seconds * 2);
        return true;
    } finally { delete_option($lock); }
}
function tlit_scm_fields($b) {
    $fields = array('name','email','countryCode','mobile','careerObjective','source','registrationId','website','captchaToken');
    foreach ($fields as $f) {
        if (!isset($b[$f]) || !is_string($b[$f])) return false;
        $b[$f] = trim($b[$f]);
    }
    $objectives = array('I want to get my first job in finance or ERP.','I want to increase my salary.','I want to switch to a new career.','I want to grow into a consultant or lead role.','I want a skill that keeps my career secure.');
    $b['email'] = strtolower($b['email']);
    $b['mobile'] = preg_replace('/[\s()-]/', '', $b['mobile']);
    if ($b['website'] !== '' || $b['name'] === '' || strlen($b['name']) > 300 || preg_match('/[\x00-\x1F\x7F]/', $b['name'])
        || strlen($b['email']) > 254 || !is_email($b['email']) || !preg_match('/^\+[1-9]\d{0,3}$/D', $b['countryCode'])
        || !preg_match('/^\d{6,14}$/D', $b['mobile']) || !in_array($b['careerObjective'], $objectives, true)
        || !in_array($b['source'], array('inline','popup'), true) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di', $b['registrationId'])
        || strlen($b['captchaToken']) < 1 || strlen($b['captchaToken']) > 2048) return false;
    $phone = $b['countryCode'] . ltrim($b['mobile'], '0');
    if (!preg_match('/^\+[1-9]\d{7,14}$/D', $phone) || ($b['countryCode'] === '+91' && !preg_match('/^[6-9]\d{9}$/D', $b['mobile']))) return false;
    return array('name' => $b['name'], 'email' => $b['email'], 'mobile' => $phone, 'careerObjective' => $b['careerObjective'], 'registrationId' => $b['registrationId']);
}
function tlit_scm_register($request) {
    $c = tlit_scm_config();
    if (!tlit_scm_allowed($c)) return tlit_scm_failure('Registration is temporarily paused. Please check back shortly.', 503);
    if ($request->get_header('origin') !== 'https://lp.techleadsit.com') return tlit_scm_failure('Request not allowed.', 403);
    if (strlen($request->get_body()) > 8192) return tlit_scm_failure('Request too large.', 413);
    if (!tlit_scm_rate('ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 600)) return tlit_scm_failure('Too many attempts. Please try again later.', 429);
    $b = $request->get_body_params();
    $fields = tlit_scm_fields($b);
    if (!$fields) return tlit_scm_failure('Please check the registration fields.', 400);
    if (!tlit_scm_rate('email:' . $fields['email'], 3, 3600)) return tlit_scm_failure('Too many attempts. Please try again later.', 429);
    $captcha = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array('timeout' => 10, 'redirection' => 0, 'body' => array('secret' => $c['captcha_secret'], 'response' => $b['captchaToken'])));
    $verified = is_wp_error($captcha) ? array() : json_decode(wp_remote_retrieve_body($captcha), true);
    if (empty($verified['success']) || ($verified['hostname'] ?? '') !== 'lp.techleadsit.com' || ($verified['action'] ?? '') !== 'scm_registration') return tlit_scm_failure('Please complete the security check again.', 403);
    $payload = wp_json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $timestamp = (string) time();
    $envelope = array('timestamp' => $timestamp, 'payload' => $payload, 'signature' => hash_hmac('sha256', $timestamp . '.' . $payload, $c['secret']));
    // No automatic retries: Apps Script confirms a durable request ID before returning success.
    $result = wp_remote_post($c['url'], array('timeout' => 25, 'redirection' => 0, 'headers' => array('Content-Type' => 'application/json'), 'body' => wp_json_encode($envelope)));
    // Apps Script ContentService uses a one-time googleusercontent response URL.
    if (!is_wp_error($result) && in_array(wp_remote_retrieve_response_code($result), array(302,303), true)) {
        $location = wp_remote_retrieve_header($result, 'location');
        if (is_string($location) && preg_match('#^https://script\.googleusercontent\.com/macros/echo\?#', $location)) {
            $result = wp_remote_get($location, array('timeout' => 10, 'redirection' => 0));
        }
    }
    $saved = is_wp_error($result) ? array() : json_decode(wp_remote_retrieve_body($result), true);
    if (is_wp_error($result) || wp_remote_retrieve_response_code($result) !== 200 || ($saved['success'] ?? false) !== true || ($saved['registrationId'] ?? '') !== $fields['registrationId']) {
        return tlit_scm_failure('We could not confirm your registration. Please retry or call +91 8125323232.', 502);
    }
    return tlit_scm_reply(array('success' => true));
}

add_action('admin_menu', function () {
    add_options_page('SCM Registration Security', 'SCM Registration Security', 'manage_options', 'tlit-scm-registration', 'tlit_scm_admin');
});
function tlit_scm_admin() {
    if (!current_user_can('manage_options')) return;
    $c = tlit_scm_config();
    if (empty($c['secret'])) { $c['secret'] = bin2hex(random_bytes(32)); update_option('tlit_scm_registration', $c, false); }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        check_admin_referer('tlit_scm_settings');
        $c['url'] = esc_url_raw(wp_unslash($_POST['script_url'] ?? ''));
        $c['sitekey'] = sanitize_text_field(wp_unslash($_POST['sitekey'] ?? ''));
        if (!empty($_POST['captcha_secret'])) $c['captcha_secret'] = sanitize_text_field(wp_unslash($_POST['captcha_secret']));
        $c['enabled'] = !empty($_POST['enabled']) && !empty($_POST['verified']) && tlit_scm_ready($c);
        update_option('tlit_scm_registration', $c, false);
        echo '<div class="notice notice-success"><p>Settings saved. Public registration is ' . (!empty($c['enabled']) ? 'enabled.' : 'paused.') . '</p></div>';
    }
    echo '<div class="wrap"><h1>SCM Registration Security</h1><p>Google Apps Script only. Public registration stays paused until private Sheet access and both forms have been verified.</p><form method="post">';
    wp_nonce_field('tlit_scm_settings');
    echo '<p><label>Apps Script deployment URL<br><input class="large-text" type="url" name="script_url" value="' . esc_attr($c['url'] ?? '') . '"></label></p>';
    echo '<p><label>Apps Script SCM_SHARED_SECRET (copy into Script Properties)<br><input class="large-text" type="password" readonly value="' . esc_attr($c['secret']) . '" autocomplete="off"></label></p>';
    echo '<p><label>Turnstile site key<br><input class="large-text" name="sitekey" value="' . esc_attr($c['sitekey'] ?? '') . '"></label></p>';
    echo '<p><label>Turnstile secret key (blank keeps existing)<br><input class="large-text" type="password" name="captcha_secret" autocomplete="new-password"></label></p>';
    echo '<p><label><input type="checkbox" name="verified" value="1"> I verified the Sheet is Restricted and both forms saved successfully in admin test mode.</label></p>';
    echo '<p><label><input type="checkbox" name="enabled" value="1" ' . checked(!empty($c['enabled']), true, false) . '> Enable public registration</label></p>';
    submit_button('Save registration settings');
    echo '</form><p>With the required keys saved, use <a href="' . esc_url(home_url('/scm-batch-80?scm_test=1')) . '">admin test mode</a> while public registration remains paused. Both forms still require a real security check.</p></div>';
}
