<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Offline integration contract test; no external network requests are made. */
define('ABSPATH', dirname(__DIR__) . '/');
define('ARRAY_A', 'ARRAY_A');
$checks = 0;
$options = array();
$hooks = array();
$allowed = true;
$valid_nonce = true;
$salt = 'offline-test-salt';
$http_calls = array();
$cleared = array();
$crm_calls = array();
$remote_response = array('response' => array('code' => 202), 'body' => 'ignored response');
$schedule_success = true;
class TestDie extends RuntimeException {}
class TestRedirect extends RuntimeException {}
class WP_Error {
    private $message;
    public function __construct($code = '', $message = '') { $this->message = $message; }
    public function get_error_message() { return $this->message; }
}
function add_action($name, $callback, $priority = 10, $args = 1) { $GLOBALS['hooks'][$name] = $callback; }
function add_filter($name, $callback, $priority = 10, $args = 1) { $GLOBALS['hooks'][$name] = $callback; }
function apply_filters($name, $value, ...$args) { return $value; }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function update_option($name, $value, $autoload = null) { $GLOBALS['options'][$name] = $value; return true; }
function wp_parse_args($value, $default) { return array_merge($default, $value); }
function wp_parse_url($value) { return parse_url($value); }
function wp_http_validate_url($value) {
    $host = parse_url($value, PHP_URL_HOST);
    return $host === '8.8.8.8' ? $value : false;
}
function wp_salt($scheme) { return $GLOBALS['salt']; }
function current_user_can($capability) { return $capability === 'manage_options' && $GLOBALS['allowed']; }
function wp_die($message, $title = '', $args = array()) { throw new TestDie($message, $args['response'] ?? 0); }
function check_admin_referer($action) { if (!$GLOBALS['valid_nonce']) { throw new TestDie('invalid nonce', 403); } }
function wp_unslash($value) { return $value; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function wp_json_encode($value) { return json_encode($value, JSON_UNESCAPED_UNICODE); }
function wp_safe_remote_post($url, $args) { $GLOBALS['http_calls'][] = array($url, $args); return $GLOBALS['remote_response']; }
function wp_remote_retrieve_response_code($value) { return $value['response']['code'] ?? 0; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function tlit_dm_crm_url() { return 'https://next.telecrm.in/api/b1/enterprise/offline-test/autoupdatelead'; }
function tlit_dm_crm($fields, $note) { $GLOBALS['crm_calls'][] = array($fields, $note); return true; }
function wp_clear_scheduled_hook($hook, $args) { $GLOBALS['cleared'][] = array($hook, $args); return 1; }
function admin_url($path = '') { return 'https://wordpress.example.test/wp-admin/' . $path; }
function wp_safe_redirect($url) { check(!$GLOBALS['wpdb']->transaction, 'redirect occurs after transaction closes'); throw new TestRedirect($url); }
function wp_generate_uuid4() { return '00000000-1111-4444-8888-123456789abc'; }
function wp_schedule_single_event($timestamp, $hook, $args, $return_error = false) { check(!$GLOBALS['wpdb']->transaction, 'Cron scheduling occurs after commit'); return $GLOBALS['schedule_success']; }
function check($condition, $message) { ++$GLOBALS['checks']; if (!$condition) { throw new RuntimeException('FAIL: ' . $message); } }
function denied($callback, $message) {
    try { $callback(); } catch (TestDie $error) { check(true, $message); return; }
    throw new RuntimeException('FAIL: ' . $message);
}
final class TLIT_CB_Store {
    public static $row;
    public static $messages = array();
    public static $error = false;
    public static function get($id) { return self::$row && (int) self::$row['id'] === (int) $id ? self::$row : null; }
    public static function messages($id) { return self::$messages; }
    public static function read_error() { return self::$error; }
}
final class IntegrationDB {
    public $prefix = 'wp_';
    public $last_error = '';
    public $job = null;
    public $updates = array();
    public $deleted = array();
    public $insert_id = 11;
    public $delete_fail = false;
    public $insert_fail = false;
    public $commit_fail = false;
    public $transaction = false;
    public $snapshot = null;
    public $trace = array();
    public function prepare($query, ...$args) {
        foreach ($args as $arg) { $query = preg_replace('/%[ds]/', is_numeric($arg) ? (string) $arg : "'" . str_replace("'", "''", $arg) . "'", $query, 1); }
        return $query;
    }
    public function query($sql) {
        $this->trace[] = $sql;
        if ($sql === 'START TRANSACTION') { $this->transaction = true; $this->snapshot = $this->job; return 1; }
        if ($sql === 'COMMIT') { if ($this->commit_fail) { return false; } $this->transaction = false; return 1; }
        if ($sql === 'ROLLBACK') { $this->transaction = false; $this->job = $this->snapshot; return 1; }
        if (strpos($sql, "state='sending'") !== false) {
            if (!$this->job || $this->job['state'] !== 'pending') { return 0; }
            $this->job['state'] = 'sending';
            return 1;
        }
        return 1;
    }
    public function get_row($query, $mode = null) { $this->trace[] = $query; return strpos($query, 'tlit_cb_conversations') !== false ? TLIT_CB_Store::$row : $this->job; }
    public function get_var($query) { return TLIT_CB_Store::$row['id'] ?? null; }
    public function get_col($query) { return array(11, 12); }
    public function update($table, $data, $where, ...$unused) {
        if (isset($where['state']) && ($this->job['state'] ?? '') !== $where['state']) { return 0; }
        $this->updates[] = $data;
        $this->job = array_merge($this->job ?? array(), $data);
        return 1;
    }
    public function delete($table, $where, ...$unused) { if ($this->delete_fail) { return false; } $this->deleted[] = array($table, $where); $this->job = null; return 1; }
    public function insert($table, $data, ...$unused) { $this->trace[] = 'INSERT snapshot'; if ($this->insert_fail) { return false; } $this->job = array_merge(array('id' => 11), $data); return 1; }
    public function suppress_errors($value = true) { return false; }
}
$wpdb = new IntegrationDB();
require dirname(__DIR__, 2) . '/digital-marketing-integration/lead-schema.php';
require dirname(__DIR__, 2) . '/digital-marketing-integration/chat-phone.php';
require dirname(__DIR__) . '/includes/integrations.php';
TLIT_CB_Integrations::register();
check(isset($hooks['admin_post_tlit_cb_connection_save'], $hooks['admin_post_tlit_cb_send']), 'only authenticated actions registered');
check(!isset($hooks['admin_post_nopriv_tlit_cb_send']), 'no public send action');
$_SERVER['REQUEST_METHOD'] = 'POST';
$allowed = false;
denied(array('TLIT_CB_Integrations', 'save'), 'non-admin cannot save');
denied(array('TLIT_CB_Integrations', 'enqueue'), 'non-admin cannot queue');
$allowed = true;
$_SERVER['REQUEST_METHOD'] = 'GET';
denied(array('TLIT_CB_Integrations', 'save'), 'GET cannot alter settings');
denied(array('TLIT_CB_Integrations', 'enqueue'), 'GET cannot queue a delivery');
$_SERVER['REQUEST_METHOD'] = 'POST';
$valid_nonce = false;
denied(array('TLIT_CB_Integrations', 'save'), 'nonce required for settings');
denied(array('TLIT_CB_Integrations', 'enqueue'), 'nonce required for queue');
$valid_nonce = true;
foreach (array('http://8.8.8.8/a', 'https://user:secret@8.8.8.8/a', 'https://8.8.8.8/a#fragment', 'https://localhost/a', 'https://127.0.0.1/a', 'https://192.168.0.1/a', 'https://[::1]/a', 'javascript:alert(1)', '') as $url) {
    check(!TLIT_CB_Integrations::valid_url($url), 'reject unsafe endpoint ' . $url);
}
check((bool) TLIT_CB_Integrations::valid_url('https://8.8.8.8/ingest'), 'allow validated public HTTPS endpoint');
$secret = 'integration-secret-not-to-be-in-history';
$sealed = TLIT_CB_Integrations::seal($secret);
check($sealed !== '' && strpos($sealed, $secret) === false, 'secret encrypted at rest');
check(TLIT_CB_Integrations::secret($sealed) === $secret, 'authenticated encryption roundtrip');
$salt = 'different-test-salt';
check(TLIT_CB_Integrations::secret($sealed) === '', 'wrong encryption key cannot decrypt');
$salt = 'offline-test-salt';
check(TLIT_CB_Integrations::secret('invalid!') === '', 'malformed encrypted token handled');
$tracking = array('utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'launch', 'utm_adgroup' => 'group', 'utm_term' => 'ai course', 'utm_content' => 'creative', 'gclid' => 'google-click', 'gbraid' => 'gbraid-id', 'wbraid' => 'wbraid-id', 'fbclid' => 'fb-click', 'fbp' => 'browser-id', 'fbc' => 'meta-click', 'ga_client_id' => 'ga-client', 'session_id' => 'analytics-session', 'landing_page' => 'https://wordpress.example.test/course/', 'referrer' => 'https://search.example.test/', 'fb_ad' => 'fb-ad', 'fb_campaign' => 'fb-campaign', 'fb_adset_name' => 'fb-adset', 'fb_adset_id' => 'fb-adset-id', 'fb_lead_id' => 'fb-lead-id', 'city' => 'Hyderabad', 'state' => 'Telangana');
$row = array('id' => 7, 'course' => 'digital_marketing', 'source_url' => 'https://wordpress.example.test/course/', 'name' => 'లక్ష్మి', 'phone' => '+919876543210', 'email' => 'offline@example.test', 'language' => 'Telugu', 'goal' => 'first_marketing_job', 'status' => 'new', 'stage' => 'question', 'has_brochure' => 1, 'created_at' => '2026-10-13 00:00:00', 'updated_at' => '2026-10-13 00:01:00', 'tracking_json' => wp_json_encode($tracking), 'session_hash' => 'SHOULD_NOT_EXPORT_HASH', 'api_secret' => 'SHOULD_NOT_EXPORT_SECRET', 'otp' => 'SHOULD_NOT_EXPORT_OTP');
TLIT_CB_Store::$row = $row;
TLIT_CB_Store::$messages = array(array('role' => 'visitor', 'content' => 'తెలుగులో వివరాలు కావాలి.', 'type' => 'question', 'created_at' => '2026-10-13 00:00:00', 'internal_debug' => 'DO_NOT_SEND'));
$payload = TLIT_CB_Integrations::payload($row);
check(!isset($payload['messages']), 'transcript excluded by default');
check($payload['tracking'] === $tracking, 'all sanitized tracking fields preserved');
check($payload['phone_verified'] === false, 'chat capture does not claim phone verification');
check(strpos(wp_json_encode($payload), 'SHOULD_NOT_EXPORT') === false, 'payload excludes raw row secret fields');
$options['tlit_cb_connections'] = array('webhook_enabled' => 1, 'webhook_url' => 'https://8.8.8.8/ingest', 'webhook_secret' => $sealed, 'telecrm_enabled' => 1, 'include_transcript' => 1);
$payload = TLIT_CB_Integrations::payload($row);
check(count($payload['messages']) === 1 && strpos(wp_json_encode($payload), 'DO_NOT_SEND') === false, 'transcript sharing is explicit and allowlisted');
$result = TLIT_CB_Integrations::send_webhook($payload, 'delivery-key');
check($result['success'] === true && $result['code'] === 202, 'HTTP success accepted');
$request = $http_calls[0][1];
check($request['headers']['Authorization'] === 'Bearer ' . $secret, 'credential appears only in authorization header');
check(strpos($request['body'], $secret) === false, 'credential absent from JSON body');
check($request['headers']['Idempotency-Key'] === 'delivery-key', 'idempotency header retained');
check($request['redirection'] === 0 && $request['sslverify'] === true && $request['limit_response_size'] <= 4096, 'HTTP request security and size bounds');
$http_before = count($http_calls);
$options['tlit_cb_connections']['webhook_secret'] = 'invalid!';
check(TLIT_CB_Integrations::send_webhook($payload, 'delivery-key')['success'] === false, 'corrupt configured secret fails closed');
check(count($http_calls) === $http_before, 'corrupt secret does not send without authorization');
$options['tlit_cb_connections']['webhook_secret'] = $sealed;
$remote_response = array('response' => array('code' => 403), 'body' => 'secret-response-that-must-not-be-saved');
check(TLIT_CB_Integrations::send_webhook($payload, 'delivery-key')['success'] === false, 'HTTP failure stays failure');
$result = TLIT_CB_Integrations::send_telecrm($payload, 'delivery-key');
check($result['success'] === true && count($crm_calls) === 1, 'existing TeleCRM field mapper called');
list($fields, $note) = $crm_calls[0];
foreach (array('name','phone','email','Course Name','Lead date','lead_date','leaddate','date','Your preferred time to call','City Name','State Name','Lead Source','Mode of Training','Exp Level','Remarks','gclid','gbraid','wbraid','fbclid','fbp','fbc','gaclientid','sessionid','landingpage','referrer','utmsource','utmmedium','utmcampaign','utmadgroup','utmterm','utmcontent','Facebook Campaign','Facebook Ad','Facebook Ad set Name','Facebook Ad set ID','Facebook Lead ID') as $field) {
    check(array_key_exists($field, $fields), 'TeleCRM contract field ' . $field);
}
check($fields['phone'] === '+919876543210' && $fields['Lead Source'] === 'Google Ads', 'phone normalized and attribution retained');
check($fields['sessionid'] === 'analytics-session' && $fields['gaclientid'] === 'ga-client', 'analytics aliases retained');
check($fields['Facebook Ad set ID'] === 'fb-adset-id' && $fields['Facebook Lead ID'] === 'fb-lead-id', 'Facebook fields retained');
check(strpos($note, 'Not verified') !== false && strpos($note, 'delivery-key') !== false && strpos($note, 'downloaded brochure') !== false, 'CRM note includes verification, reference, brochure');
foreach (array('Course Enrollment date', 'Course 2 Enrollment Date', 'Fee', 'Payment') as $field) { check(!isset($fields[$field]), 'counselor field not auto-filled: ' . $field); }
$wrong_course = $payload; $wrong_course['course'] = 'unrelated';
check(TLIT_CB_Integrations::send_telecrm($wrong_course, 'delivery-key')['success'] === false, 'TeleCRM mapper rejects other courses');
$international=$payload;$international['phone']='+447712345678';
check(TLIT_CB_Integrations::send_telecrm($international,'international-delivery')['success']===true, 'International Bridge contact can use the existing TeleCRM mapper');
check(end($crm_calls)[0]['phone']==='+447712345678', 'International CRM number keeps its actual country code');
$international['phone']='+99912345678';$before_invalid=count($crm_calls);
check(TLIT_CB_Integrations::send_telecrm($international,'invalid-international')['success']===false&&count($crm_calls)===$before_invalid, 'Unknown international code never reaches CRM');
$wpdb->job = array('id' => 11, 'conversation_id' => 7, 'connector' => 'webhook', 'state' => 'pending', 'payload' => wp_json_encode($payload), 'idempotency_key' => 'delivery-key');
TLIT_CB_Integrations::deliver(11);
check($wpdb->job['state'] === 'failed' && strpos(wp_json_encode($wpdb->updates), 'secret-response') === false, 'delivery history retains generic error only');
$http_before = count($http_calls);
TLIT_CB_Integrations::deliver(11);
check(count($http_calls) === $http_before, 'atomic pending claim prevents re-delivery');
$wpdb->job['state'] = 'pending';
TLIT_CB_Store::$row = null;
TLIT_CB_Integrations::deliver(11);
check($wpdb->job['state'] === 'failed', 'missing parent fails instead of remaining stuck in sending');
check(count($http_calls) === $http_before, 'missing parent prevents transmission');
TLIT_CB_Store::$row = $row;
$wpdb->job = null;
$schedule_success = false;
$_POST = array('conversation_id' => 7, 'connector' => 'webhook');
denied(array('TLIT_CB_Integrations', 'enqueue'), 'scheduling failure is visible');
check($wpdb->job['state'] === 'failed', 'unscheduled jobs remain retryable');
$schedule_success = true;
$wpdb->delete_fail = true;
check(is_wp_error(TLIT_CB_Integrations::erase_payload(true, 7)), 'payload deletion failure propagates to privacy transaction');
check(!$cleared, 'failed erasure does not clear scheduled jobs prematurely');
$wpdb->delete_fail = false;
check(TLIT_CB_Integrations::erase_payload(true, 7) === true, 'payload deletion succeeds inside privacy transaction');
check(!$cleared, 'cron cleanup waits for privacy commit');
TLIT_CB_Integrations::erase(7);
check($wpdb->deleted[0][1] === array('conversation_id' => 7), 'privacy erasure removes payload snapshots');
check($cleared === array(array('tlit_cb_deliver', array(11)), array('tlit_cb_deliver', array(12))), 'privacy erasure clears both queued jobs');

$wpdb->trace = array();
$queued = TLIT_CB_Integrations::queue(7, 'webhook');
check(!is_wp_error($queued) && $queued['status'] === 'queued' && $wpdb->job['state'] === 'pending', 'transactional queue creates pending snapshot');
check(!$wpdb->transaction && end($wpdb->trace) === 'COMMIT', 'queue commits before returning');
check($wpdb->trace[0] === 'START TRANSACTION' && strpos($wpdb->trace[1], 'tlit_cb_conversations') !== false && strpos($wpdb->trace[1], 'FOR UPDATE') !== false, 'parent locked first using eraser lock order');
check(strpos($wpdb->trace[2], 'tlit_cb_deliveries') !== false && strpos($wpdb->trace[2], 'FOR UPDATE') !== false && $wpdb->trace[3] === 'INSERT snapshot', 'delivery lock precedes snapshot insertion');
$original_job = $wpdb->job;
$queued = TLIT_CB_Integrations::queue(7, 'webhook');
check($queued['status'] === 'existing' && $wpdb->job === $original_job, 'duplicate queue preserves snapshot and idempotency key');
$wpdb->job['state'] = 'failed';
TLIT_CB_Store::$row['name'] = 'Changed after first delivery';
$queued = TLIT_CB_Integrations::queue(7, 'webhook');
check($queued['status'] === 'queued' && $wpdb->job['payload'] === $original_job['payload'] && $wpdb->job['idempotency_key'] === $original_job['idempotency_key'], 'retry keeps original immutable payload and key');
$wpdb->job = null;
TLIT_CB_Store::$row = null;
check(is_wp_error(TLIT_CB_Integrations::queue(7, 'webhook')) && $wpdb->job === null && !$wpdb->transaction, 'erased parent cannot create orphan PII snapshot');
TLIT_CB_Store::$row = $row;
check(is_wp_error(TLIT_CB_Integrations::queue(7, 'disabled')) && !$wpdb->transaction, 'disabled connection rolls back safely');
TLIT_CB_Store::$row['phone'] = ''; TLIT_CB_Store::$row['email'] = '';
check(is_wp_error(TLIT_CB_Integrations::queue(7, 'webhook')) && !$wpdb->transaction, 'missing contact rolls back safely');
TLIT_CB_Store::$row = $row;
TLIT_CB_Store::$error = true;
$failed = TLIT_CB_Integrations::queue(7, 'webhook');
check(is_wp_error($failed) && !$wpdb->transaction && $wpdb->job === null, 'transcript exception becomes generic queue error and rollback');
check($failed->get_error_message() === 'The conversation could not be queued. Reload and try again.', 'queue errors expose no database or contact details');
TLIT_CB_Store::$error = false;
$wpdb->insert_fail = true;
check(is_wp_error(TLIT_CB_Integrations::queue(7, 'webhook')) && !$wpdb->transaction && $wpdb->job === null, 'failed insertion rolls back without snapshot');
$wpdb->insert_fail = false;
$wpdb->commit_fail = true;
check(is_wp_error(TLIT_CB_Integrations::queue(7, 'webhook')) && !$wpdb->transaction && $wpdb->job === null, 'failed commit rolls back generated payload');
$wpdb->commit_fail = false;
echo "PASS: offline integration contracts ($checks checks; zero external requests).\n";
