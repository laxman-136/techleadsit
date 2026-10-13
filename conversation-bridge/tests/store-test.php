<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Offline contract checks. Database transaction behavior is covered by the WP smoke test. */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
$tlit_test_options = array('tlit_cb_db_version' => '2');
$tlit_test_timezone = 'Asia/Kolkata';
$tlit_test_schema = array();
$tlit_test_erase_filter = null;
$tlit_test_erased_actions = array();
function add_filter($hook, $callback) {}
function apply_filters($hook, $value, $id) { global $tlit_test_erase_filter; return is_callable($tlit_test_erase_filter) ? $tlit_test_erase_filter($value, $id) : $value; }
function do_action($hook, $value) { global $tlit_test_erased_actions; $tlit_test_erased_actions[] = $value; }
function get_option($key, $default = false) { global $tlit_test_options; return isset($tlit_test_options[$key]) ? $tlit_test_options[$key] : $default; }
function update_option($key, $value, $autoload = null) { global $tlit_test_options; $tlit_test_options[$key] = $value; return true; }
function dbDelta($sql) { global $tlit_test_schema; $tlit_test_schema[] = $sql; }
function wp_salt($scheme) { return 'offline-testing-salt'; }
function wp_check_invalid_utf8($text, $strip = false) { return preg_match('//u', $text) ? $text : ''; }
function sanitize_text_field($text) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags($text))); }
function sanitize_textarea_field($text) { return trim(strip_tags($text)); }
function sanitize_key($text) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($text)); }
function sanitize_email($text) { return filter_var($text, FILTER_SANITIZE_EMAIL); }
function is_email($text) { return is_string($text) && filter_var($text, FILTER_VALIDATE_EMAIL); }
function esc_url_raw($url, $protocols = null) { return filter_var($url, FILTER_VALIDATE_URL) ? $url : ''; }
function wp_parse_url($url) { return parse_url($url); }
function wp_json_encode($value) { return json_encode($value); }
function wp_timezone() { global $tlit_test_timezone; return new DateTimeZone($tlit_test_timezone); }
class WP_Error {
    public $code;
    public $message;
    public function __construct($code, $message, $data = array()) { $this->code = $code; $this->message = $message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
class TLIT_CB_Test_DB {
    public $prefix = 'wp_';
    public $insert_id = 17;
    public $last_error = '';
    public $queries = array();
    public $prepared = array();
    public $seen = false;
    public $failure = '';
    public $suppressed = false;
    public $columns_missing = false;
    public $rows = array();
    public function suppress_errors($value) { $old = $this->suppressed; $this->suppressed = $value; return $old; }
    public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
    public function prepare($sql, ...$args) {
        if (count($args) === 1 && is_array($args[0])) { $args = $args[0]; }
        $this->prepared[] = array('sql' => $sql, 'args' => $args);
        return preg_replace_callback('/%[sd]/', function ($match) use (&$args) {
            $arg = array_shift($args);
            return $match[0] === '%d' ? (string) (int) $arg : "'" . addslashes($arg) . "'";
        }, $sql);
    }
    public function esc_like($text) { return addcslashes($text, '_%\\'); }
    public function query($sql) {
        $this->queries[] = $sql;
        $this->last_error = '';
        if ($this->failure !== '' && strpos($sql, $this->failure) !== false) {
            $this->last_error = 'Sensitive database detail must never leave this mock.';
            return false;
        }
        return 1;
    }
    public function get_var($sql) {
        if ($this->query($sql) === false) { return null; }
        if (strpos($sql, 'SELECT event_key') === 0) { return $this->seen ? 'existing-hash' : null; }
        if (strpos($sql, 'SELECT tracking_json') === 0) { return '{"gclid":"preserved-gclid","utm_source":"old-source"}'; }
        if (strpos($sql, 'SELECT MAX') === 0) { return '40'; }
        return '4';
    }
    public function get_results($sql, $format) { return $this->query($sql) === false ? null : $this->rows; }
    public function get_row($sql, $format) { return $this->query($sql) === false ? null : array('id' => 17, 'total' => 4, 'with_phone' => 2, 'brochure' => 1, 'handoff' => 1); }
    public function get_col($sql, $column = 0) {
        $this->query($sql);
        if ($this->columns_missing) { return array(); }
        return array('id', 'session_hash', 'course', 'source_url', 'name', 'email', 'phone', 'language', 'goal', 'tracking_json', 'stage', 'status', 'has_brochure', 'answer_count', 'created_at', 'updated_at', 'conversation_id', 'event_key', 'role', 'content', 'type');
    }
}
$wpdb = new TLIT_CB_Test_DB();
require dirname(__DIR__) . '/includes/store.php';
$checks = 0;
function check($condition, $message) {
    global $checks;
    if (!$condition) { fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL); exit(1); }
    $checks++;
}
function reset_db() { global $wpdb; $wpdb = new TLIT_CB_Test_DB(); return $wpdb; }
function prepared_for($needle) {
    global $wpdb;
    return array_values(array_filter($wpdb->prepared, function ($call) use ($needle) { return strpos($call['sql'], $needle) !== false; }));
}

// Schema migrations verify all three private tables and do not mark a failed upgrade complete.
$tlit_test_options = array();
check(TLIT_CB_Store::install() === true, 'Fresh schema installation succeeds');
check(count($tlit_test_schema) === 3 && strpos(implode('\n', $tlit_test_schema), 'ENGINE=InnoDB') !== false, 'Transactional tables and event ledger are created');
check(get_option('tlit_cb_db_version') === TLIT_CB_Store::DB_VERSION, 'Successful schema is versioned');
$tlit_test_options = array();
$wpdb->columns_missing = true;
check(is_wp_error(TLIT_CB_Store::install()) && get_option('tlit_cb_db_version') === false, 'Failed schema is retried on a future request');
$tlit_test_options = array('tlit_cb_db_version' => TLIT_CB_Store::DB_VERSION);

$session = str_repeat('ab', 24);
$event = array(
    'session' => $session, 'event_id' => 'question-1', 'course' => 'digital_marketing',
    'source_url' => 'https://user:pass@example.test/course/?email=person@example.test#secret',
    'contact' => array('name' => 'Asha', 'email' => 'asha@example.test', 'phone' => '+91 98765 43210'),
    'language' => 'Telugu', 'goal' => 'తెలుగు నేర్చుకోవాలి', 'stage' => 'question', 'answer_count' => 1,
    'tracking' => array('utm_source' => 'new-source', 'gclid' => '', 'referrer' => 'https://example.test/path?phone=1234567890', 'password' => 'do-not-store', 'fb_ad' => str_repeat('x', 600)),
    'messages' => array(
        array('role' => 'visitor', 'type' => 'message', 'content' => 'OTP: 654321, api_key=abcdefghijk and Bearer abc.def.ghi; session ' . $session),
        array('role' => 'visitor', 'type' => 'otp', 'content' => '123456'),
        array('role' => 'assistant', 'type' => 'answer', 'content' => 'తెలుగులో సమాధానం'),
    ),
);
reset_db();
check(TLIT_CB_Store::capture($event) === 17, 'Capture returns conversation ID');
$calls = prepared_for('INSERT INTO wp_tlit_cb_conversations');
$args = $calls[0]['args'];
check(strlen($args[0]) === 64 && $args[0] !== $session, 'Only HMAC session hash is stored');
check($args[2] === 'https://example.test/course/', 'Source query, fragment and credentials are removed');
check($args[5] === '+919876543210', 'Contact phone is normalized');
check($args[7] === $event['goal'], 'Unicode content is preserved');
$messages = prepared_for('INSERT INTO wp_tlit_cb_messages');
check(count($messages) === 2, 'OTP messages are omitted');
check(strpos($messages[0]['args'][3], '654321') === false && strpos($messages[0]['args'][3], 'abcdefghijk') === false && strpos($messages[0]['args'][3], 'abc.def.ghi') === false, 'OTP and common secrets are redacted');
check(strpos(implode('\n', $wpdb->queries), $session) === false, 'Raw conversation token never reaches SQL');
check($messages[1]['args'][3] === 'తెలుగులో సమాధానం', 'Transcript preserves multilingual content');
$tracking_calls = prepared_for('SET tracking_json');
$tracking = json_decode($tracking_calls[0]['args'][0], true);
check($tracking['gclid'] === 'preserved-gclid' && $tracking['utm_source'] === 'new-source', 'Tracking merges nonblank values');
check(!isset($tracking['password']) && $tracking['referrer'] === 'https://example.test/path' && strlen($tracking['fb_ad']) === 512, 'Tracking whitelist, URL privacy and size limits apply');
check(end($wpdb->queries) === 'COMMIT' && !$wpdb->suppressed, 'Successful transaction commits and restores error display setting');
$first_key = $messages[0]['args'][1];
reset_db();
TLIT_CB_Store::capture($event);
$again = prepared_for('INSERT INTO wp_tlit_cb_messages');
check($again[0]['args'][1] === $first_key, 'Retry message key is stable');
reset_db();
$wpdb->seen = true;
check(TLIT_CB_Store::capture($event) === 17 && end($wpdb->queries) === 'ROLLBACK', 'Delayed retry rolls back metadata merge');
check(count(prepared_for('INSERT INTO wp_tlit_cb_messages')) === 0, 'Already accepted event cannot insert messages again');
reset_db();
$wpdb->failure = 'INSERT INTO wp_tlit_cb_messages';
$error = TLIT_CB_Store::capture($event);
check(is_wp_error($error) && strpos($error->message, 'Sensitive') === false && end($wpdb->queries) === 'ROLLBACK', 'Database failure rolls back and returns only a generic error');
check(is_wp_error(TLIT_CB_Store::capture(array('session' => 'invalid'))), 'Malformed session is rejected');

reset_db();
$result = TLIT_CB_Store::query(array('date_from' => '2026-10-13', 'date_to' => '2026-10-13', 'search' => "O'Neil%_", 'has_phone' => 'yes', 'has_brochure' => 'no', 'max_id' => 40), -2, 500);
$sql = implode('\n', $wpdb->queries);
check(strpos($sql, '2026-10-12 18:30:00') !== false && strpos($sql, '2026-10-13 18:30:00') !== false, 'Inclusive site-date filter uses correct UTC boundaries');
check($result['per_page'] === 200 && $result['page'] === 1, 'Pagination is bounded');
check(strpos($sql, "phone<>''") !== false && strpos($sql, 'has_brochure=0') !== false && strpos($sql, 'id<=40') !== false, 'Contact, brochure and snapshot filters apply');
check(strpos($sql, 'updated_at DESC,id DESC') !== false, 'Normal queries have stable date and ID ordering');
check(strpos($sql, "O\\'Neil") !== false && strpos($sql, '\\%') !== false, 'Search value and LIKE metacharacters are escaped');
reset_db();
TLIT_CB_Store::query(array('export_order' => 'id_asc', 'max_id' => 40, 'after_id' => 17), 1, 100);
$sql = implode('\n', $wpdb->queries);
check(strpos($sql, 'ORDER BY id ASC') !== false && strpos($sql, 'id>17') !== false, 'Exports support immutable ID cursor ordering');
check(TLIT_CB_Store::snapshot_max_id() === 40, 'Export snapshot returns maximum matching ID');
reset_db();
$tlit_test_timezone = 'America/New_York';
TLIT_CB_Store::query(array('date_from' => '2026-03-08', 'date_to' => '2026-03-08'));
$sql = implode('\n', $wpdb->queries);
check(strpos($sql, '2026-03-08 05:00:00') !== false && strpos($sql, '2026-03-09 04:00:00') !== false, 'Date filters respect DST changes rather than adding 24 hours');
reset_db();
TLIT_CB_Store::query(array('date_from' => '2026-02-30', 'status' => 'injected', 'order' => 'DROP TABLE'));
check(strpos(implode('\n', $wpdb->queries), '1=0') !== false && strpos(implode('\n', $wpdb->queries), 'DROP TABLE') === false, 'Invalid filter values cannot broaden dates/status or inject ordering');
check(TLIT_CB_Store::update_status(17, 'injected') === false && TLIT_CB_Store::update_status(17, 'reviewed') === true, 'Only explicit review statuses are accepted');
reset_db();
$wpdb->failure = 'SELECT *';
check(TLIT_CB_Store::messages(17) === array() && TLIT_CB_Store::read_error(), 'Transcript read failure is distinguishable from an empty chat');
$wpdb->failure = '';
TLIT_CB_Store::messages(17);
check(!TLIT_CB_Store::read_error(), 'Read failure state resets for the next operation');
reset_db();
$wpdb->rows = array(array('id' => 17));
$tlit_test_erase_filter = function ($ready, $id) { return new WP_Error('cleanup_failed', 'Sensitive integration detail'); };
$erasure = TLIT_CB_Store::privacy_erase('privacy@example.test');
check(is_wp_error($erasure) && strpos($erasure->message, 'Sensitive') === false && end($wpdb->queries) === 'ROLLBACK', 'Integration cleanup error rolls back privacy erasure with generic failure');
check(count($tlit_test_erased_actions) === 0 && count(prepared_for('DELETE FROM')) === 0, 'Failed cleanup cannot announce erasure or delete store rows');
reset_db();
$wpdb->rows = array(array('id' => 17));
$tlit_test_erase_filter = function ($ready, $id) { throw new RuntimeException('Sensitive exception detail'); };
$erasure = TLIT_CB_Store::privacy_erase('privacy@example.test');
check(is_wp_error($erasure) && end($wpdb->queries) === 'ROLLBACK' && !$wpdb->suppressed, 'Thrown cleanup exception rolls back and restores database error setting');
reset_db();
$wpdb->rows = array(array('id' => 17));
$tlit_test_erase_filter = null;
$erasure = TLIT_CB_Store::privacy_erase('privacy@example.test');
check(!is_wp_error($erasure) && $erasure['items_removed'] && end($wpdb->queries) === 'COMMIT' && $tlit_test_erased_actions === array(17), 'Successful cleanup commits then emits post-erasure action');
echo 'PASS: ' . $checks . ' storage contract checks.' . PHP_EOL;
