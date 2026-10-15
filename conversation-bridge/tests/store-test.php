<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Offline contract checks. Database transaction behavior is covered by the WP smoke test. */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
$tlit_test_options = array('tlit_cb_db_version' => '3');
$tlit_test_timezone = 'Asia/Kolkata';
$tlit_test_schema = array();
$tlit_test_erase_filter = null;
$tlit_test_erased_actions = array();
$tlit_test_cron = array();
function add_action($hook, $callback, $priority = 10, $args = 1) {}
function delete_option($key) { global $tlit_test_options; unset($tlit_test_options[$key]); return true; }
function wp_next_scheduled($hook) { global $tlit_test_cron; return $tlit_test_cron[$hook] ?? false; }
function wp_schedule_single_event($time, $hook) { global $tlit_test_cron; $tlit_test_cron[$hook] = $time; return true; }
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
    public $goal_rows = array(17 => array('id' => 17, 'course' => 'digital_marketing', 'goal' => '', 'goal_analysis_json' => '', 'updated_at' => '2026-10-13 00:00:00'));
    public $goal_history = array();
    public $before_goal_lock = null;
    private $message_sequence = 0;
    private $snapshot = null;
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
        if ($sql === 'START TRANSACTION') { $this->snapshot = array($this->goal_rows, $this->goal_history, $this->message_sequence); }
        if ($sql === 'ROLLBACK' && $this->snapshot !== null) { list($this->goal_rows, $this->goal_history, $this->message_sequence) = $this->snapshot; $this->snapshot = null; }
        if ($sql === 'COMMIT') { $this->snapshot = null; }
        $prepared = end($this->prepared);
        $args = $prepared ? $prepared['args'] : array();
        if (strpos($sql, 'INSERT INTO wp_tlit_cb_conversations') === 0) {
            $row = $this->goal_rows[17] ?? array('id' => 17, 'goal' => '', 'goal_analysis_json' => '');
            $row['course'] = $args[1];
            if ($args[7] !== '' || strpos($sql, 'goal=VALUES(goal),') !== false) { $row['goal'] = $args[7]; }
            $this->goal_rows[17] = $row;
        }
        if (strpos($sql, 'INSERT INTO wp_tlit_cb_messages') === 0) {
            $this->goal_history[$args[0]][] = array('id' => ++$this->message_sequence, 'role' => $args[2], 'content' => $args[3], 'type' => $args[4]);
        }
        if (strpos($sql, 'UPDATE wp_tlit_cb_conversations SET goal=') === 0) {
            $this->goal_rows[$args[2]]['goal'] = $args[0];
            $this->goal_rows[$args[2]]['goal_analysis_json'] = $args[1];
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
    public function get_results($sql, $format) {
        if ($this->query($sql) === false) { return null; }
        if (preg_match('/SELECT id,role,content,type .*conversation_id=(\d+).*id>(\d+)/', $sql, $match)) {
            return array_slice(array_reverse(array_values(array_filter($this->goal_history[(int) $match[1]] ?? array(), function ($message) use ($match) { return $message['role'] === 'visitor' && $message['id'] > (int) $match[2]; }))), 0, 200);
        }
        return $this->rows;
    }
    public function get_row($sql, $format) {
        if ($this->query($sql) === false) { return null; }
        if (preg_match('/SELECT id,course,goal,goal_analysis_json .*id=(\d+)/', $sql, $match)) {
            if (strpos($sql, 'FOR UPDATE') !== false && is_callable($this->before_goal_lock)) { ($this->before_goal_lock)($this, (int) $match[1]); }
            return $this->goal_rows[(int) $match[1]] ?? null;
        }
        return array('id' => 17, 'total' => 4, 'with_phone' => 2, 'brochure' => 1, 'handoff' => 1);
    }
    public function get_col($sql, $column = 0) {
        $this->query($sql);
        if (preg_match('/SELECT id FROM wp_tlit_cb_conversations.*id>(\d+).*LIMIT (\d+)/', $sql, $match)) {
            $rows = array_filter($this->goal_rows, function ($row) use ($match) { return $row['id'] > (int) $match[1] && $row['course'] === 'digital_marketing' && $row['goal_analysis_json'] === ''; });
            ksort($rows);
            return array_slice(array_keys($rows), 0, (int) $match[2]);
        }
        if ($this->columns_missing) { return array(); }
        return array('id', 'session_hash', 'course', 'source_url', 'name', 'email', 'phone', 'language', 'goal', 'goal_analysis_json', 'tracking_json', 'stage', 'status', 'has_brochure', 'answer_count', 'created_at', 'updated_at', 'conversation_id', 'event_key', 'role', 'content', 'type');
    }
}
$wpdb = new TLIT_CB_Test_DB();
require dirname(__DIR__) . '/includes/store.php';
require dirname(__DIR__) . '/includes/goal-backfill.php';
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
$clear_goal = array_merge($event, array('event_id' => 'goal-retracted', 'goal' => '', 'goal_authoritative' => true));
check(TLIT_CB_Store::capture($clear_goal) === 17, 'Explicit learning goal retraction is accepted');
$goal_call = prepared_for('INSERT INTO wp_tlit_cb_conversations')[0];
check($goal_call['args'][7] === '' && strpos($goal_call['sql'], 'goal=VALUES(goal),') !== false, 'Confirmed empty goal clears the stored goal instead of reviving an old one');
reset_db();
$partial_event = array_merge($event, array('event_id' => 'brochure-after-state-expired', 'goal' => ''));
TLIT_CB_Store::capture($partial_event);
$goal_call = prepared_for('INSERT INTO wp_tlit_cb_conversations')[0];
check(strpos($goal_call['sql'], "goal=IF(VALUES(goal)<>'',VALUES(goal),goal),") !== false, 'Partial events without confirmed goal state preserve the stored learning goal');
reset_db();
$partial_event['goal_authoritative'] = 'true';
TLIT_CB_Store::capture($partial_event);
$goal_call = prepared_for('INSERT INTO wp_tlit_cb_conversations')[0];
check(strpos($goal_call['sql'], "goal=IF(VALUES(goal)<>'',VALUES(goal),goal),") !== false, 'Malformed clear marker cannot clear a saved goal');
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

// Every DM session receives a nonblank, evidence-aware summary without asking for a goal.
reset_db();
$goal_event = array('session' => $session, 'event_id' => 'intro', 'course' => 'digital_marketing', 'messages' => array(array('role' => 'visitor', 'type' => 'contact_name', 'content' => 'Asha')));
TLIT_CB_Store::capture($goal_event);
$goal_meta = json_decode($wpdb->goal_rows[17]['goal_analysis_json'], true);
check($wpdb->goal_rows[17]['goal'] !== '' && $goal_meta['status'] === 'insufficient', 'Intro-only sessions receive an honest nonblank summary');
$goal_event['event_id'] = 'fee';
$goal_event['messages'] = array(array('role' => 'visitor', 'type' => 'question', 'content' => 'What is the course fee?'));
TLIT_CB_Store::capture($goal_event);
$goal_meta = json_decode($wpdb->goal_rows[17]['goal_analysis_json'], true);
check($goal_meta['status'] === 'interest' && stripos($wpdb->goal_rows[17]['goal'], 'fee') !== false && stripos($wpdb->goal_rows[17]['goal'], 'job') === false, 'Fee interest is stored without inventing a job goal');
$goal_event['event_id'] = 'job';
$goal_event['messages'] = array(array('role' => 'visitor', 'type' => 'question', 'content' => 'Can I get a job after this course?'));
TLIT_CB_Store::capture($goal_event);
$goal_meta = json_decode($wpdb->goal_rows[17]['goal_analysis_json'], true);
check($goal_meta['status'] === 'inferred' && $goal_meta['source'] === 'transcript' && stripos($wpdb->goal_rows[17]['goal'], 'job') !== false, 'Transcript intent replaces an earlier broad interest summary');
check($goal_meta['evidence'] !== '' && $goal_meta['version'] === 1, 'Evidence and version are stored beside the canonical goal');
$goal_event['event_id'] = 'clear';
$goal_event['goal'] = null;
$goal_event['goal_authoritative'] = true;
$goal_event['messages'] = array(array('role' => 'visitor', 'type' => 'question', 'content' => 'Actually I am not looking for a job.'));
TLIT_CB_Store::capture($goal_event);
$goal_meta = json_decode($wpdb->goal_rows[17]['goal_analysis_json'], true);
check($goal_meta['status'] === 'insufficient' && $goal_meta['cleared_through_message_id'] === 4, 'An authoritative null clears intent and records the discarded transcript boundary');
unset($goal_event['goal_authoritative'], $goal_event['goal']);
$goal_event['event_id'] = 'after-expired-state';
$goal_event['messages'] = array(array('role' => 'visitor', 'type' => 'contact_phone', 'content' => '+919876543210'));
TLIT_CB_Store::capture($goal_event);
check(stripos($wpdb->goal_rows[17]['goal'], 'job') === false, 'A later partial event cannot revive the retracted goal from old messages');
$goal_event['event_id'] = 'new-model-goal';
$goal_event['goal'] = 'Likely interested in growing their bakery with Instagram.';
$goal_event['messages'] = array(array('role' => 'visitor', 'type' => 'question', 'content' => 'I run a bakery and want more local leads.'));
TLIT_CB_Store::capture($goal_event);
$goal_meta = json_decode($wpdb->goal_rows[17]['goal_analysis_json'], true);
check($goal_meta['source'] === 'model' && $goal_meta['status'] === 'inferred' && !isset($goal_meta['cleared_through_message_id']), 'New valid model intent replaces the cleared state');
$expected_goal = $wpdb->goal_rows[17]['goal'];
unset($goal_event['goal']);
$goal_event['event_id'] = 'partial-contact';
$goal_event['messages'] = array();
TLIT_CB_Store::capture($goal_event);
check($wpdb->goal_rows[17]['goal'] === $expected_goal, 'Missing expired-state intent preserves the existing real model goal');
$wpdb->failure = 'SET goal=';
$goal_event['event_id'] = 'failed-goal-write';
$goal_event['goal'] = 'A changed goal that must roll back';
$goal_event['messages'] = array(array('role' => 'visitor', 'type' => 'question', 'content' => 'I want to change careers.'));
$message_count = count($wpdb->goal_history[17]);
check(is_wp_error(TLIT_CB_Store::capture($goal_event)) && $wpdb->goal_rows[17]['goal'] === $expected_goal && count($wpdb->goal_history[17]) === $message_count, 'A goal write failure atomically rolls back metadata and transcript');

// Historical jobs update private reporting only, in bounded row-locked batches.
reset_db();
$historical = array('course' => 'digital_marketing', 'goal' => '', 'goal_analysis_json' => '', 'updated_at' => '2026-09-01 08:00:00');
$wpdb->goal_rows = array(1 => array_merge($historical, array('id' => 1)), 2 => array_merge($historical, array('id' => 2, 'goal' => 'Build practical SEO skills.')), 3 => array_merge($historical, array('id' => 3, 'course' => 'oracle_hcm')));
$wpdb->goal_history[1] = array(array('id' => 1, 'role' => 'visitor', 'type' => 'question', 'content' => 'Can I get a job after this course?'));
$actions_before = count($tlit_test_erased_actions);
$backfilled = TLIT_CB_Store::backfill_goals(0, 1);
check($backfilled === array('last_id' => 1, 'processed' => 1, 'has_more' => true), 'Historical analysis is bounded and advances an immutable ID cursor');
check(stripos($wpdb->goal_rows[1]['goal'], 'job') !== false && $wpdb->goal_rows[1]['updated_at'] === $historical['updated_at'], 'Historical intent is inferred without changing the last-activity timestamp');
$backfilled = TLIT_CB_Store::backfill_goals(1, 20);
check($backfilled['processed'] === 1 && $wpdb->goal_rows[2]['goal'] === 'Build practical SEO skills.' && $wpdb->goal_rows[3]['goal_analysis_json'] === '', 'Backfill preserves existing goals and leaves other courses untouched');
check(TLIT_CB_Store::backfill_goals(0, 20)['processed'] === 0 && count($tlit_test_erased_actions) === $actions_before, 'Repeated historical passes are idempotent and emit no integration actions');
check(strpos(implode('\n', $wpdb->queries), 'FOR UPDATE') !== false, 'Historical rows are locked before analysis to avoid racing fresh chat events');
$wpdb->goal_rows[4] = array_merge($historical, array('id' => 4));
$wpdb->before_goal_lock = function ($db, $id) { $db->goal_rows[$id]['goal'] = 'Newly captured model goal'; $db->goal_rows[$id]['goal_analysis_json'] = '{"version":1,"source":"model"}'; };
check(TLIT_CB_Store::backfill_goals(0, 20)['processed'] === 0 && $wpdb->goal_rows[4]['goal'] === 'Newly captured model goal', 'A fresh capture that wins the race is never overwritten by backfill');

reset_db();
$wpdb->goal_rows = array();
for ($id = 1; $id <= 21; $id++) { $wpdb->goal_rows[$id] = array_merge($historical, array('id' => $id)); }
$tlit_test_options = array('tlit_cb_db_version' => TLIT_CB_Store::DB_VERSION);
$tlit_test_cron = array();
TLIT_CB_Goal_Backfill::schedule();
check(isset($tlit_test_cron[TLIT_CB_Goal_Backfill::HOOK]) && count(prepared_for('SET goal=')) === 0, 'Scheduling performs no historical transcript work on the public request');
$tlit_test_cron = array();
TLIT_CB_Goal_Backfill::run();
check(get_option(TLIT_CB_Goal_Backfill::CURSOR_OPTION) === 20 && isset($tlit_test_cron[TLIT_CB_Goal_Backfill::HOOK]) && get_option(TLIT_CB_Goal_Backfill::VERSION_OPTION) === false, 'Cron processes only twenty sessions then schedules the next batch');
$tlit_test_cron = array();
TLIT_CB_Goal_Backfill::run();
check(get_option(TLIT_CB_Goal_Backfill::VERSION_OPTION) === 1 && get_option(TLIT_CB_Goal_Backfill::CURSOR_OPTION) === false && !$tlit_test_cron, 'Cron records completion after the remaining session');
$writes = count(prepared_for('SET goal='));
TLIT_CB_Goal_Backfill::run();
TLIT_CB_Goal_Backfill::schedule();
check(count(prepared_for('SET goal=')) === $writes && !$tlit_test_cron, 'Completed historical work is not repeated or rescheduled');
delete_option(TLIT_CB_Goal_Backfill::VERSION_OPTION);
$wpdb->failure = 'SELECT id FROM';
TLIT_CB_Goal_Backfill::run();
check(get_option(TLIT_CB_Goal_Backfill::ERROR_OPTION) !== false && isset($tlit_test_cron[TLIT_CB_Goal_Backfill::HOOK]) && get_option(TLIT_CB_Goal_Backfill::VERSION_OPTION) === false, 'Failed historical reads remain incomplete and schedule a bounded retry');

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
