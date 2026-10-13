<?php
/**
 * CLI-only deterministic admin fixture; no database or real visitor information.
 * php tests/admin-preview.php inbox > inbox.html
 * php tests/admin-preview.php detail > detail.html
 * php tests/admin-preview.php empty > empty.html
 * php tests/admin-preview.php integrations > integrations.html
 * php tests/admin-preview.php detail-error > detail-error.html
 * php tests/admin-preview.php transcript-error > transcript-error.html
 * php tests/admin-preview.php --test
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('ABSPATH', __DIR__ . '/');
define('TLIT_CB_URL', '../');
define('TLIT_CB_VERSION', 'preview');
define('ARRAY_A', 'ARRAY_A');
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_html__($value, $domain = '') { return esc_html($value); }
function esc_url($value, $protocols = null) { return preg_match('/^\s*(javascript|data):/i', $value) ? '' : esc_attr($value); }
function sanitize_text_field($value) { return trim(preg_replace('/\s+/', ' ', strip_tags($value))); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function wp_unslash($value) { return $value; }
function current_user_can($capability) { return true; }
function admin_url($path) { return 'http://localhost/wp-admin/' . $path; }
function add_query_arg($args, $url) { return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args); }
function selected($actual, $expected, $echo = true) { $value = (string) $actual === (string) $expected ? ' selected="selected"' : ''; if ($echo) echo $value; return $value; }
function checked($actual, $expected, $echo = true) { $value = (string) $actual === (string) $expected ? ' checked="checked"' : ''; if ($echo) echo $value; return $value; }
function wp_nonce_field($action) { echo '<input type="hidden" name="_wpnonce" value="fixture-nonce">'; }
function number_format_i18n($value) { return number_format($value); }
function wp_timezone() { return new DateTimeZone('Asia/Kolkata'); }
function wp_date($format, $timestamp, $timezone) { return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format($format); }
function do_action($action, $row = null) { if ($action === 'tlit_cb_conversation_actions' && class_exists('TLIT_CB_Integrations')) TLIT_CB_Integrations::actions($row); }
function apply_filters($hook, $value) { return $value; }
function wp_parse_args($value, $defaults) { return array_merge($defaults, $value); }
function get_option($name, $default = false) {
    return $name === 'tlit_cb_connections' ? array('webhook_enabled' => 1, 'webhook_url' => 'https://example.com/conversations', 'webhook_secret' => 'fixture-token-never-displayed', 'telecrm_enabled' => 0, 'include_transcript' => 0) : $default;
}
function is_wp_error($result) { return false; }
function wp_die($message, $title = '', $args = array()) { throw new RuntimeException($message); }

class TLIT_CB_Store {
    public static $empty = false;
    public static $failure = '';
    private static $read_failed = false;
    public static function fixtures() {
        return array(
            array('id' => 1048, 'course' => 'AI Powered Digital Marketing', 'source_url' => 'https://example.com/ai-digital-marketing/', 'name' => 'Pranav Reddy', 'email' => 'pranav@example.com', 'phone' => '+91 90000 01048', 'language' => 'English', 'goal' => 'Build practical marketing skills and move into a growth role.', 'stage' => 'handoff', 'status' => 'new', 'has_brochure' => 1, 'answer_count' => 8, 'created_at' => '2026-10-13 05:14:00', 'updated_at' => '2026-10-13 05:21:00', 'tracking_json' => json_encode(array('utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'Digital Marketing | Career Growth', 'utm_content' => '<script>attributionUnsafe()</script>', 'gclid' => 'fixture-google-click-id', 'city' => 'Hyderabad', 'state' => 'Telangana', 'api_key' => 'fixture-credential-never-shown', 'session_hash' => 'fixture-private-session-never-shown'))),
            array('id' => 1047, 'course' => 'Oracle Fusion SCM', 'name' => 'Nandini Rao', 'email' => 'nandini@example.com', 'phone' => '+91 90000 01047', 'language' => 'Telugu', 'stage' => 'course_details', 'status' => 'reviewed', 'has_brochure' => 1, 'answer_count' => 5, 'created_at' => '2026-10-13 04:48:00'),
            array('id' => 1046, 'course' => 'AI Powered Digital Marketing', 'name' => 'Arjun Mehta', 'email' => 'arjun@example.com', 'phone' => '+91 90000 01046', 'language' => 'Hindi', 'stage' => 'career_goal', 'status' => 'contacted', 'has_brochure' => 0, 'answer_count' => 6, 'created_at' => '2026-10-13 04:22:00'),
            array('id' => 1045, 'course' => 'Oracle Fusion HCM', 'name' => 'Sneha Nair', 'email' => 'sneha@example.com', 'phone' => '', 'language' => 'English', 'stage' => 'started', 'status' => 'new', 'has_brochure' => 0, 'answer_count' => 2, 'created_at' => '2026-10-13 03:53:00'),
            array('id' => 1044, 'course' => 'Oracle Fusion SCM', 'name' => 'Rohit Verma', 'email' => 'rohit@example.com', 'phone' => '+91 90000 01044', 'language' => 'English', 'stage' => 'handoff', 'status' => 'new', 'has_brochure' => 1, 'answer_count' => 9, 'created_at' => '2026-10-12 13:46:00'),
            array('id' => 1043, 'course' => 'AI Powered Digital Marketing', 'name' => 'Keerthi Kumar', 'email' => 'keerthi@example.com', 'phone' => '+91 90000 01043', 'language' => 'Telugu', 'stage' => 'brochure', 'status' => 'reviewed', 'has_brochure' => 1, 'answer_count' => 4, 'created_at' => '2026-10-12 12:37:00'),
            array('id' => 1042, 'course' => 'Oracle Fusion HCM', 'name' => '', 'email' => '', 'phone' => '', 'language' => 'English', 'stage' => 'started', 'status' => 'new', 'has_brochure' => 0, 'answer_count' => 1, 'created_at' => '2026-10-12 11:15:00'),
            array('id' => 1041, 'course' => 'AI Powered Digital Marketing', 'name' => 'Vikram Shah', 'email' => 'vikram@example.com', 'phone' => '+91 90000 01041', 'language' => 'Hindi', 'stage' => 'completed', 'status' => 'archived', 'has_brochure' => 0, 'answer_count' => 7, 'created_at' => '2026-10-12 09:03:00'),
        );
    }
    public static function query($filters, $page = 1, $per_page = 25) { return array('rows' => self::$empty ? array() : self::fixtures(), 'total' => self::$empty ? 0 : 8, 'page' => $page, 'per_page' => $per_page); }
    public static function stats($filters) { return self::$empty ? array('total' => 0, 'with_phone' => 0, 'brochure' => 0, 'handoff' => 0) : array('total' => 8, 'with_phone' => 6, 'brochure' => 4, 'handoff' => 2); }
    public static function get($id) { self::$read_failed = self::$failure === 'detail-error'; if (self::$read_failed) return null; foreach (self::fixtures() as $row) if ($row['id'] === $id) return $row; return null; }
    public static function read_error() { return self::$read_failed; }
    public static function distinct_values($column) { return array_values(array_unique(array_column(self::fixtures(), $column))); }
    public static function messages($id) {
        self::$read_failed = self::$failure === 'transcript-error';
        if (self::$read_failed) return array();
        return array(
            array('role' => 'assistant', 'content' => 'Hi Pranav! I can help you explore the AI Powered Digital Marketing course. What would you like to achieve?', 'type' => 'text', 'created_at' => '2026-10-13 05:14:00'),
            array('role' => 'visitor', 'content' => 'I work in sales right now and want to move into digital marketing. Is this suitable for someone without experience?', 'type' => 'text', 'created_at' => '2026-10-13 05:15:00'),
            array('role' => 'assistant', 'content' => "Yes. The course starts with the fundamentals and moves into practical campaign work. You’ll learn SEO, paid advertising, analytics, and how to use AI tools in your workflow.\n\nWould you like the curriculum brochure?", 'type' => 'text', 'created_at' => '2026-10-13 05:15:00'),
            array('role' => 'visitor', 'content' => 'Yes, please share the brochure. I’d also like to understand the weekday batch timings.', 'type' => 'text', 'created_at' => '2026-10-13 05:17:00'),
            array('role' => 'system', 'content' => 'Visitor requested the course brochure.', 'type' => 'brochure_request', 'created_at' => '2026-10-13 05:17:00'),
            array('role' => 'assistant', 'content' => 'I’ve noted your interest in the weekday batch. Our course team can help you choose a batch that works with your schedule. Shall I connect you?', 'type' => 'text', 'created_at' => '2026-10-13 05:18:00'),
            array('role' => 'visitor', 'content' => 'That would help. Please call me in the afternoon.', 'type' => 'text', 'created_at' => '2026-10-13 05:20:00'),
            array('role' => 'system', 'content' => 'Visitor requested a conversation with the course team.', 'type' => 'handoff', 'created_at' => '2026-10-13 05:21:00'),
        );
    }
}
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/integrations.php';
$wpdb = new class {
    public $prefix = 'wp_';
    public function prepare($query, ...$args) { return $query; }
    public function get_results($query, $format) { return array(array('connector' => 'webhook', 'state' => 'sent', 'attempts' => 1, 'response_code' => 200, 'last_error' => '', 'updated_at' => '2026-10-13 05:25:00')); }
};

function fixture_render($mode) {
    $_GET = array('page' => TLIT_CB_Admin::SLUG);
    TLIT_CB_Store::$empty = $mode === 'empty';
    TLIT_CB_Store::$failure = $mode;
    if (in_array($mode, array('detail', 'detail-error', 'transcript-error'), true)) $_GET['conversation'] = '1048';
    if ($mode === 'empty') $_GET['search'] = 'No matching visitor';
    if ($mode === 'integrations') $_GET['view'] = 'integrations';
    ob_start();
    TLIT_CB_Admin::render();
    return ob_get_clean();
}

$mode = $argv[1] ?? 'inbox';
if ($mode === '--test') {
    $assertions = 0;
    $assert = function ($condition, $message) use (&$assertions) { if (!$condition) throw new RuntimeException($message); $assertions++; };
    $filters = TLIT_CB_Admin::filters(array('search' => array('bad'), 'course' => '<b>Oracle</b>', 'language' => 'English', 'status' => 'sent', 'date_from' => '2026-10-31', 'date_to' => '2026-10-01', 'has_phone' => 'yes', 'has_brochure' => '1', 'unexpected' => 'anything'));
    $assert(!isset($filters['search']) && !isset($filters['status']) && !isset($filters['unexpected']), 'Reject unsupported and nonscalar filter values.');
    $assert($filters['course'] === 'Oracle', 'Sanitize course text.');
    $assert($filters['date_from'] === '2026-10-01' && $filters['date_to'] === '2026-10-31', 'Normalize reversed inclusive date range.');
    $assert(!isset(TLIT_CB_Admin::filters(array('date_from' => '2026-02-30'))['date_from']), 'Reject invalid calendar date.');
    $assert($filters['has_phone'] === 'yes' && !isset($filters['has_brochure']), 'Limit qualification filters to yes or no.');
    $html = fixture_render('inbox');
    $assert(substr_count($html, 'data-tlit-select-row') === 8, 'Render every selectable fixture record.');
    $assert(strpos($html, 'name="scope" value="filtered"') !== false && strpos($html, 'name="scope" value="selected"') !== false, 'Provide both bulk export scopes.');
    $assert(substr_count($html, '<form ') === substr_count($html, '</form>'), 'Balance inbox forms.');
    $html = fixture_render('detail');
    $assert(substr_count($html, '<article class="tlit-cb-message ') === 8, 'Render complete transcript.');
    $assert(strpos($html, 'name="scope" value="single"') !== false, 'Provide single document export.');
    $assert(strpos($html, 'tlit_cb_status') !== false, 'Provide review status action.');
    $assert(strpos($html, 'Changing status does not send this conversation to a CRM.') !== false, 'Keep review status separate from dispatch.');
    $assert(substr_count($html, '<form ') === substr_count($html, '</form>'), 'Balance detail forms.');
    $assert(strpos($html, 'Queue delivery') !== false && strpos($html, 'Custom API / webhook') !== false, 'Render the real integration delivery panel.');
    $assert(strpos($html, 'Attribution details') !== false && strpos($html, 'UTM campaign') !== false && strpos($html, 'fixture-google-click-id') !== false, 'Show captured attribution fields.');
    $assert(strpos($html, '<script>attributionUnsafe()</script>') === false && strpos($html, '&lt;script&gt;attributionUnsafe()&lt;/script&gt;') !== false, 'Escape attribution values.');
    $assert(strpos($html, 'fixture-credential-never-shown') === false && strpos($html, 'fixture-private-session-never-shown') === false, 'Do not render hidden credential or session fields.');
    $html = fixture_render('detail-error');
    $assert(strpos($html, 'Conversation could not be loaded') !== false && strpos($html, 'Conversation not found') === false, 'Do not confuse a database read failure with a missing record.');
    $html = fixture_render('transcript-error');
    $assert(strpos($html, 'Transcript could not be loaded') !== false && strpos($html, 'No messages captured yet') === false, 'Do not confuse a transcript read failure with an empty transcript.');
    $assert(strpos($html, '0 messages') === false && strpos($html, 'Temporarily unavailable') !== false, 'Never show a zero message count on database read failure.');
    $html = fixture_render('integrations');
    $assert(strpos($html, 'name="webhook_url"') !== false && strpos($html, 'Save connections') !== false, 'Render the real integration settings.');
    $assert(strpos($html, 'fixture-token-never-displayed') === false, 'Never display the stored authorization token.');
    $assert(substr_count($html, '<form ') === substr_count($html, '</form>'), 'Balance integration forms.');
    $html = fixture_render('empty');
    $assert(strpos($html, 'No conversations match these filters') !== false, 'Explain filtered empty state.');
    $_GET = array('search' => '\"><script>alert(1)</script>');
    TLIT_CB_Store::$empty = false;
    ob_start(); TLIT_CB_Admin::render(); $html = ob_get_clean();
    $assert(strpos($html, '<script>alert(1)</script>') === false, 'Escape visitor search in generated markup.');
    fwrite(STDOUT, 'Admin render checks passed (' . $assertions . " assertions).\n");
    exit;
}
$content = fixture_render($mode);
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Conversation Bridge — ' . esc_html($mode) . ' preview</title><style>body{margin:0;background:#f0f0f1;font:13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#3c434a}a{color:#2271b1}.screen-reader-text{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}.button{background:#f6f7f7;border:1px solid #2271b1;color:#2271b1;cursor:pointer}.button:disabled{cursor:default}.dashicons{display:inline-block;font-style:normal;font-weight:normal;text-align:center;vertical-align:top}.dashicons:before{content:"◦"}.dashicons-format-chat:before{content:"▤"}.dashicons-search:before{content:"⌕"}.dashicons-download:before{content:"↓"}.dashicons-arrow-right-alt2:before{content:"→"}.dashicons-arrow-left-alt:before{content:"←"}.dashicons-filter:before{content:"≡"}.dashicons-lock:before{content:"•"}.dashicons-admin-links:before{content:"↗"}.dashicons-smartphone:before{content:"▯"}.dashicons-media-document:before{content:"▤"}.dashicons-groups:before{content:"♧"}.dashicons-info-outline:before{content:"ⓘ"}.notice{padding:8px 16px;background:white;border-left:4px solid #00a32a}.notice p{margin:0}.preview-shell{max-width:1560px;margin:0 auto;padding:16px 26px}.preview-top{height:32px;background:#1d2327;color:#c3c4c7;display:flex;align-items:center;justify-content:space-between;padding:0 20px;font-size:11px}.preview-top strong{font-weight:500;color:#f0f0f1}.tlit-cb{margin-right:4px!important}@media(max-width:600px){.preview-shell{padding:8px 12px}}</style><style>' . file_get_contents(dirname(__DIR__) . '/assets/admin.css') . '</style></head><body><div class="preview-top"><strong>W &nbsp; TechLeads IT</strong><span>Administrator</span></div><main class="preview-shell">' . $content . '</main><script>' . file_get_contents(dirname(__DIR__) . '/assets/admin.js') . '</script></body></html>';
