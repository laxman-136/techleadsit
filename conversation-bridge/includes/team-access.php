<?php
/** Scoped team access: a private, expiring link plus a different PIN for each lead. */
if (!defined('ABSPATH')) { exit; }

final class TLIT_CB_Team_Access {
    const OPTION = 'tlit_cb_team_access';
    const SESSION_SECONDS = 1800;
    const RATE_SECONDS = 900;
    const COOKIE = 'tlit_bridge_team';

    public static function register() {
        // Mark private requests before normal page/canonical routing or cache rendering.
        if (isset($_GET['tlit_team_bridge']) && !defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
        add_action('template_redirect', array(__CLASS__, 'route'), -20);
        add_action('admin_post_tlit_team_access', array(__CLASS__, 'admin_save'));
        add_action('tlit_cb_team_cleanup', array(__CLASS__, 'cleanup'));
        add_filter('tlit_cb_before_conversation_erase', array(__CLASS__, 'erase'), 35, 2);
    }

    public static function table() { global $wpdb; return $wpdb->prefix . 'tlit_cb_team_links'; }

    public static function install() {
        if (get_option('tlit_cb_team_schema') !== '1') {
            global $wpdb;
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta('CREATE TABLE ' . self::table() . " (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                identity_key varchar(40) NOT NULL,
                entity_type varchar(12) NOT NULL,
                entity_id bigint(20) unsigned NOT NULL,
                generation char(32) NOT NULL,
                nonce char(32) NOT NULL,
                token_hash char(64) NOT NULL,
                pin_hash varchar(255) NOT NULL,
                pin_sealed text NOT NULL,
                pin_index char(64) NOT NULL,
                created_at datetime NOT NULL,
                expires_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY identity_key (identity_key),
                UNIQUE KEY token_hash (token_hash),
                UNIQUE KEY pin_index (pin_index),
                KEY expires_at (expires_at),
                KEY entity (entity_type,entity_id)
            ) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';');
            $columns = $wpdb->get_col('SHOW COLUMNS FROM ' . self::table());
            if (is_array($columns) && !array_diff(array('pin_sealed', 'token_hash', 'generation', 'expires_at'), $columns)) {
                update_option('tlit_cb_team_schema', '1', false);
            }
        }
        if (!wp_next_scheduled('tlit_cb_team_cleanup')) { wp_schedule_event(time() + 3600, 'daily', 'tlit_cb_team_cleanup'); }
    }

    public static function options() {
        $value = get_option(self::OPTION, array());
        return wp_parse_args(is_array($value) ? $value : array(), array('enabled' => 0, 'days' => 7, 'generation' => ''));
    }

    public static function enabled() {
        $o = self::options();
        return !empty($o['enabled']) && preg_match('/^[a-f0-9]{32}$/D', $o['generation']) === 1 && TLIT_CB_Qualification::ready();
    }

    private static function error($message, $status = 400) { return new WP_Error('tlit_team_access', $message, array('status' => $status)); }
    private static function hash($value) { return hash_hmac('sha256', 'team-bridge|' . $value, wp_salt('auth')); }
    private static function scalar($value) { return is_string($value) ? $value : ''; }

    public static function save_settings(array $input) {
        $old = self::options();
        $enabled = !empty($input['enabled']) ? 1 : 0;
        if ($enabled && (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt'))) {
            return self::error('OpenSSL is required to send private team access codes.');
        }
        if ($enabled && !TLIT_CB_Qualification::ready()) { return self::error('Update the Landing Page Leads module before enabling team reviews.'); }
        $days = isset($input['days']) && is_scalar($input['days']) ? (int) $input['days'] : 7;
        if (!in_array($days, array(1, 7, 30), true)) { return self::error('Choose a valid link lifetime.'); }
        $generation = $old['generation'];
        // Disabling or revoking immediately invalidates old links and signed-in sessions.
        if ($generation === '' || (bool) $enabled !== (bool) $old['enabled'] || !empty($input['revoke'])) {
            $generation = bin2hex(random_bytes(16));
        }
        update_option(self::OPTION, array('enabled' => $enabled, 'days' => $days, 'generation' => $generation), false);
        return true;
    }

    private static function seal($value) {
        $iv = random_bytes(12); $tag = '';
        $body = openssl_encrypt($value, 'aes-256-gcm', hash('sha256', wp_salt('auth') . 'team-bridge-pin', true), OPENSSL_RAW_DATA, $iv, $tag);
        return $body === false ? '' : base64_encode($iv . $tag . $body);
    }

    private static function unseal($value) {
        if (!function_exists('openssl_decrypt')) { return ''; }
        $raw = base64_decode($value, true);
        if (!is_string($raw) || strlen($raw) < 29) { return ''; }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', wp_salt('auth') . 'team-bridge-pin', true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return is_string($plain) ? $plain : '';
    }

    private static function token($row) { return self::hash('link|' . $row['identity_key'] . '|' . $row['generation'] . '|' . $row['nonce']); }
    public static function url($token) { return add_query_arg('tlit_team_bridge', $token, home_url('/')); }

    private static function lock($scope) {
        global $wpdb;
        $key = 'tlit_team_' . substr(self::hash(DB_NAME . '|' . $wpdb->prefix . '|' . $scope), 0, 48);
        return (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 1)', $key)) === '1' ? $key : false;
    }

    private static function unlock($key) { global $wpdb; $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key)); }

    public static function cleanup() {
        global $wpdb; $o = self::options();
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::table() . ' WHERE expires_at<=%s OR generation<>%s', gmdate('Y-m-d H:i:s'), $o['generation']));
    }

    /** Allocate once per lead. Retries retain the same URL/PIN until expiry. Never puts a PIN in a URL. */
    public static function issue($reference) {
        if (!self::enabled()) { return self::error('Team access is disabled.', 403); }
        if (!is_string($reference) || !preg_match('/^(LP|CB)-([1-9][0-9]{0,17})$/D', $reference, $parts)) { return self::error('Invalid lead reference.'); }
        $lock = self::lock('issue|' . $reference);
        if (!$lock) { return self::error('Team access could not be prepared. Please retry.', 503); }
        global $wpdb;
        try {
            self::cleanup(); $o = self::options();
            $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE identity_key=%s', $reference), ARRAY_A);
            if (!$row) {
                $row = array('identity_key' => $reference, 'entity_type' => $parts[1] === 'CB' ? 'chatbot' : 'form', 'entity_id' => (int) $parts[2], 'generation' => $o['generation'], 'nonce' => bin2hex(random_bytes(16)), 'created_at' => gmdate('Y-m-d H:i:s'), 'expires_at' => gmdate('Y-m-d H:i:s', time() + (int) $o['days'] * DAY_IN_SECONDS));
                if (!self::record($row)) { return self::error('This lead is unavailable.', 404); }
                $row['token_hash'] = self::hash(self::token($row));
                $inserted = false;
                for ($attempt = 0; $attempt < 100; ++$attempt) {
                    $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                    $row['pin_index'] = self::hash('pin|' . $pin);
                    if ($wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::table() . ' WHERE pin_index=%s', $row['pin_index']))) { continue; }
                    $row['pin_hash'] = wp_hash_password($pin); $row['pin_sealed'] = self::seal($pin);
                    if ($row['pin_sealed'] === '') { break; }
                    $previous = $wpdb->suppress_errors(true);
                    $inserted = $wpdb->insert(self::table(), $row) === 1;
                    $wpdb->suppress_errors($previous);
                    if ($inserted) { $row['id'] = (int) $wpdb->insert_id; break; }
                }
                if (!$inserted) { return self::error('A unique team PIN could not be allocated. The lead remains private.', 503); }
            }
            $pin = self::unseal($row['pin_sealed']);
            if (!preg_match('/^[0-9]{4}$/D', $pin)) { return self::error('Team access could not be prepared. The lead remains private.', 503); }
            return array('url' => self::url(self::token($row)), 'pin' => $pin, 'expires_at' => $row['expires_at']);
        } finally { self::unlock($lock); }
    }

    public static function for_notification(array $lead) {
        if (!self::enabled()) { return $lead; }
        $grant = self::issue($lead['reference'] ?? '');
        if (is_wp_error($grant)) { return $grant; }
        $lead['url'] = $grant['url']; $lead['team_pin'] = $grant['pin'];
        $lead['team_expires'] = TLIT_LP_Leads::display_date($grant['expires_at']);
        return $lead;
    }

    public static function find($token) {
        if (!self::enabled() || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) { return null; }
        global $wpdb; $o = self::options();
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE token_hash=%s AND generation=%s AND expires_at>%s', self::hash($token), $o['generation'], gmdate('Y-m-d H:i:s')), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    private static function fresh($grant) { return is_array($grant) ? self::find(self::token($grant)) : null; }

    /** The target always comes from the server-held grant, never a submitted lead/conversation ID. */
    public static function record($grant) {
        if ($grant['entity_type'] === 'chatbot') {
            $row = TLIT_CB_Store::get((int) $grant['entity_id']);
            return $row && ($row['course'] ?? '') === 'digital_marketing' ? $row : null;
        }
        if ($grant['entity_type'] !== 'form' || !class_exists('TLIT_LP_Leads')) { return null; }
        $row = TLIT_LP_Leads::get((int) $grant['entity_id']);
        return $row ? (array) $row : null;
    }

    private static function check_pin($grant, $pin, $ip) {
        $lock = self::lock('login-rate');
        if (!$lock) { return self::error('Please wait a moment and try again.', 429); }
        try {
            $bucket = (string) floor(time() / self::RATE_SECONDS);
            $keys = array('grant|' . $grant['id'] => 5, 'ip|' . $ip => 20);
            foreach ($keys as $scope => $maximum) {
                $key = 'tlit_bt_rate_' . self::hash($bucket . '|' . $scope);
                if ((int) get_transient($key) >= $maximum) { return self::error('Too many incorrect attempts. Wait 15 minutes before trying again.', 429); }
            }
            if (is_string($pin) && preg_match('/^[0-9]{4}$/D', $pin) && wp_check_password($pin, $grant['pin_hash'])) { return true; }
            foreach ($keys as $scope => $maximum) {
                $key = 'tlit_bt_rate_' . self::hash($bucket . '|' . $scope);
                if (!set_transient($key, (int) get_transient($key) + 1, self::RATE_SECONDS * 2)) { return self::error('Access could not be checked. Please retry.', 503); }
            }
            return self::error('The PIN is incorrect. Use the four digits in this lead’s Teams message.', 401);
        } finally { self::unlock($lock); }
    }

    public static function login($grant, $pin, $ip) {
        $grant = self::fresh($grant);
        if (!$grant || !self::record($grant)) { return self::error('This link is unavailable or has expired.', 404); }
        $checked = self::check_pin($grant, $pin, $ip);
        if (is_wp_error($checked)) { return $checked; }
        $token = bin2hex(random_bytes(32)); $expires = min(time() + self::SESSION_SECONDS, strtotime($grant['expires_at'] . ' UTC'));
        $session = array('grant_id' => (int) $grant['id'], 'nonce' => $grant['nonce'], 'generation' => $grant['generation'], 'csrf' => bin2hex(random_bytes(32)), 'expires' => $expires);
        if (!set_transient('tlit_bt_session_' . self::hash($token), $session, max(1, $expires - time()))) { return self::error('A private session could not be started. Please retry.', 503); }
        return array_merge($session, array('token' => $token));
    }

    public static function session($grant, $token) {
        $grant = self::fresh($grant);
        if (!$grant || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) { return null; }
        $session = get_transient('tlit_bt_session_' . self::hash($token));
        if (!is_array($session) || !is_string($session['nonce'] ?? null) || !is_string($session['generation'] ?? null) || !is_string($session['csrf'] ?? null)) { return null; }
        return (int) ($session['grant_id'] ?? 0) === (int) $grant['id'] && hash_equals($grant['nonce'], $session['nonce']) && hash_equals($grant['generation'], $session['generation']) && preg_match('/^[a-f0-9]{64}$/D', $session['csrf']) && (int) ($session['expires'] ?? 0) > time() ? $session : null;
    }

    public static function logout($token) { if (is_string($token)) { delete_transient('tlit_bt_session_' . self::hash($token)); } }

    public static function claim($grant, $token, array $input) {
        $session = self::session($grant, $token);
        if (!$session || !hash_equals($session['csrf'], self::scalar($input['csrf'] ?? ''))) { return self::error('Your session has expired. Enter this lead’s PIN again.', 403); }
        $grant = self::fresh($grant);
        if (!$grant || !self::record($grant) || !class_exists('TLIT_CB_Lead_Ops')) { return self::error('This lead is unavailable.', 404); }
        $member = self::scalar($input['member'] ?? ''); $members = TLIT_CB_Lead_Ops::members();
        if (!isset($members[$member])) { return self::error('Select your name before taking this lead.'); }
        return TLIT_CB_Lead_Ops::assign(TLIT_CB_Lead_Ops::reference($grant['entity_type'],$grant['entity_id']), $member, $members[$member] . ' (self-reported PIN user)', absint($input['version'] ?? 0), true);
    }

    public static function review($grant, $token, array $input) {
        $session = self::session($grant, $token);
        if (!$session || !hash_equals($session['csrf'], self::scalar($input['csrf'] ?? ''))) { return self::error('Your session has expired. Open the lead link and enter its PIN again.', 403); }
        $grant = self::fresh($grant);
        if (!$grant || !self::record($grant)) { return self::error('This lead is unavailable.', 404); }
        $status = self::scalar($input['lead_status'] ?? '');
        if (!isset(TLIT_CB_Qualification::statuses()[$status])) { return self::error('Choose a valid counsellor status.'); }
        $note = substr(sanitize_textarea_field(self::scalar($input['note'] ?? '')), 0, 4000);
        $reviewer = str_replace(array('[', ']'), '', substr(sanitize_text_field(self::scalar($input['reviewer'] ?? '')), 0, 80));
        $note = preg_replace('/^\[Team Bridge[^\]\r\n]*\]\r?\n/', '', $note);
        $note = '[Team Bridge' . ($reviewer !== '' ? ' — ' . $reviewer : '') . "]\n" . $note;
        return $grant['entity_type'] === 'chatbot'
            ? TLIT_CB_Qualification::qualify((int) $grant['entity_id'], $status, $note, 0)
            : TLIT_LP_Leads::save_status((int) $grant['entity_id'], $status, $note, 0);
    }

    private static function cookie_name($grant) { return self::COOKIE . '_' . (int) $grant['id']; }
    private static function cookie($name, $value, $expires) {
        setcookie($name, $value, array('expires' => $expires, 'path' => wp_parse_url(home_url('/'), PHP_URL_PATH) ?: '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Strict'));
    }

    private static function transport_ok() {
        return is_ssl() || in_array(wp_parse_url(home_url('/'), PHP_URL_HOST), array('localhost', '127.0.0.1', '::1'), true);
    }

    public static function route() {
        if (!isset($_GET['tlit_team_bridge'])) { return; }
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
        nocache_headers();
        header('Cache-Control: private, no-store, max-age=0');
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Referrer-Policy: no-referrer');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        require_once __DIR__ . '/team-view.php';
        $raw = isset($_GET['tlit_team_bridge']) && is_string($_GET['tlit_team_bridge']) ? wp_unslash($_GET['tlit_team_bridge']) : '';
        $grant = self::find($raw);
        if (!self::transport_ok() || !$grant) { status_header(404); TLIT_CB_Team_View::unavailable(); exit; }
        $cookie = self::cookie_name($grant);
        $session_token = self::scalar($_COOKIE[$cookie] ?? '');
        $session = self::session($grant, $session_token); $error = ''; $notice = '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, array('GET', 'POST'), true)) { status_header(405); header('Allow: GET, POST'); TLIT_CB_Team_View::unavailable(); exit; }
        if ($method === 'POST') {
            $p = wp_unslash($_POST); $operation = self::scalar($p['operation'] ?? '');
            if ($operation === 'login') {
                $challenge_name = 'tlit_bt_challenge_' . (int) $grant['id'];
                $challenge = self::scalar($_COOKIE[$challenge_name] ?? '');
                $valid = preg_match('/^[a-f0-9]{64}$/D', $challenge) && hash_equals(self::hash('challenge|' . $raw . '|' . $challenge), self::scalar($p['csrf'] ?? ''));
                if (!$valid) { $result = self::error('Reload this page and try again.', 403); }
                else { $result = self::login($grant, self::scalar($p['pin'] ?? ''), self::scalar($_SERVER['REMOTE_ADDR'] ?? 'unknown')); }
                if (is_wp_error($result)) { $error = $result->get_error_message(); status_header($result->get_error_data()['status'] ?? 400); }
                else {
                    self::logout($session_token); self::cookie($cookie, $result['token'], $result['expires']); self::cookie($challenge_name, '', time() - 3600);
                    wp_safe_redirect(self::url($raw), 303); exit;
                }
            } elseif ($operation === 'logout') {
                if ($session && hash_equals($session['csrf'], self::scalar($p['csrf'] ?? ''))) { self::logout($session_token); self::cookie($cookie, '', time() - 3600); wp_safe_redirect(self::url($raw), 303); exit; }
                $error = 'Your session has expired. Enter this lead’s PIN again.'; status_header(403);
            } elseif ($operation === 'claim') {
                $result = self::claim($grant, $session_token, $p);
                if (is_wp_error($result)) { $error = $result->get_error_message(); status_header($result->get_error_data()['status'] ?? 409); }
                else { wp_safe_redirect(add_query_arg('claimed', '1', self::url($raw)), 303); exit; }
            } elseif ($operation === 'review') {
                $result = self::review($grant, $session_token, $p);
                if (is_wp_error($result)) { $error = $result->get_error_message(); status_header($result->get_error_data()['status'] ?? 400); }
                else { wp_safe_redirect(add_query_arg('saved', '1', self::url($raw)), 303); exit; }
            } else { $error = 'This action is unavailable.'; status_header(400); }
        }
        if (!$session) {
            $challenge = bin2hex(random_bytes(32));
            self::cookie('tlit_bt_challenge_' . (int) $grant['id'], $challenge, time() + 900);
            TLIT_CB_Team_View::login(self::url($raw), self::hash('challenge|' . $raw . '|' . $challenge), $error);
        } else {
            $record = self::record($grant);
            if (!$record) { status_header(404); TLIT_CB_Team_View::unavailable(); exit; }
            if ($method === 'GET' && class_exists('TLIT_CB_Lead_Ops')) { TLIT_CB_Lead_Ops::viewed($grant, $session); }
            TLIT_CB_Team_View::detail($grant, $record, $session, self::url($raw), $error, isset($_GET['saved']) && $_GET['saved'] === '1', isset($_GET['claimed']) && $_GET['claimed'] === '1');
        }
        exit;
    }

    public static function erase($result, $id) {
        if ($result !== true) { return $result; }
        global $wpdb;
        return $wpdb->delete(self::table(), array('entity_type' => 'chatbot', 'entity_id' => (int) $id)) === false ? self::error('The private team link could not be revoked. Please retry.') : true;
    }

    public static function admin_save() {
        if (!current_user_can('manage_options')) { wp_die('Access denied.', '', array('response' => 403)); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('Use the team access settings.', '', array('response' => 405)); }
        check_admin_referer('tlit_team_access');
        $result = self::save_settings(wp_unslash($_POST));
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), '', array('response' => 400)); }
        wp_safe_redirect(admin_url('admin.php?page=tlit-chat-bridge&view=integrations&team_saved=1')); exit;
    }

    public static function settings_view() {
        if (!current_user_can('manage_options')) { return; }
        $o = self::options();
        echo '<section class="tlit-cb-side-panel tlit-teams-history"><h2>Team access from Teams</h2><p>Each new lead alert can include its own four-digit PIN and a private Bridge link. The link opens only that lead, including its learning goal, conversation and counsellor review.</p>';
        if (isset($_GET['team_saved'])) { echo '<p role="status"><strong>Team access settings saved.</strong></p>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="tlit_team_access">'; wp_nonce_field('tlit_team_access');
        echo '<label class="tlit-teams-check"><input type="checkbox" name="enabled" value="1"' . checked(!empty($o['enabled']), true, false) . '> Allow PIN access to individual leads from new Teams alerts</label><p><label for="tlit-team-days">Private link expires after </label><select id="tlit-team-days" name="days">';
        foreach (array(1 => '1 day', 7 => '7 days', 30 => '30 days') as $days => $label) { echo '<option value="' . $days . '"' . selected((int) $o['days'], $days, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></p><p>PIN sessions last 30 minutes. Five incorrect attempts per lead lock access for the rest of the 15-minute window. Codes are unique across active links and are included only in the Teams alert. Anyone with that link and PIN can review that lead. Individual staff identity is not verified by a shared alert.</p><p>Saving New or Junk stays in WordPress. Other counsellor statuses use your existing TeleCRM connection when saved. Settings, exports and other leads remain administrator-only.</p><label class="tlit-teams-check"><input type="checkbox" name="revoke" value="1"> Expire all existing team links and signed-in sessions</label><p><button class="button button-primary">Save team access</button></p></form></section>';
    }
}
