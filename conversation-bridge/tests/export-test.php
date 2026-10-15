<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Offline export integration tests. Run: php conversation-bridge/tests/export-test.php */
define('ABSPATH', __DIR__ . '/wp-test-public-' . bin2hex(random_bytes(6)) . '/');
mkdir(ABSPATH, 0700);

// Workspace temp simulates private storage: the Windows tool sandbox prevents
// libzip's rename operation inside the OS temporary directory.
$test_dir = __DIR__ . '/tlit-cb-export-test-' . bin2hex(random_bytes(6));
mkdir($test_dir, 0700);
$temp_history = array();
$current_capability = true;
$nonce_valid = true;
$hooks = array();
function add_action($hook, $callback) { $GLOBALS['hooks'][$hook] = $callback; }
function get_temp_dir() { return $GLOBALS['test_dir']; }
function wp_tempnam($name, $directory) {
    // WordPress concatenates the directory and filename directly. Unlike
    // tempnam(), it requires the caller to supply the trailing separator.
    $path = $directory . 'tlit-test-' . bin2hex(random_bytes(8)) . '.tmp';
    $handle = fopen($path, 'x');
    if ($handle === false) { return false; }
    fclose($handle);
    $GLOBALS['temp_history'][] = $path;
    return $path;
}
function current_user_can($capability) { return $capability === 'manage_options' && $GLOBALS['current_capability']; }
function wp_unslash($value) { return $value; }
function wp_verify_nonce($value, $action) { return $GLOBALS['nonce_valid'] && $value === 'test-nonce' && $action === 'tlit_cb_export'; }
function wp_die($message, $title = '', $args = array()) { throw new TestWPDie($message, (int) ($args['response'] ?? 0)); }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function wp_date($format, $timestamp, $timezone = null) { return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone ?? new DateTimeZone('UTC'))->format($format); }
class TestWPDie extends RuntimeException {}

final class TLIT_CB_Store
{
    public static $rows = array();
    public static $transcripts = array();
    public static $query_calls = array();
    public static $query_error = false;
    public static $read_error = false;
    public static $get_error = false;
    public static $message_error = false;
    public static $inject_new = false;
    public static function get($id) { self::$read_error = self::$get_error; return self::$rows[$id] ?? null; }
    public static function messages($id) { self::$read_error = self::$message_error; return self::$transcripts[$id] ?? array(); }
    public static function read_error() { return self::$read_error; }
    public static function snapshot_max_id($filters = array()) {
        $ids = array_keys(self::$rows);
        return $ids ? max($ids) : 0;
    }
    public static function query($filters, $page, $per_page) {
        self::$query_calls[] = array('filters' => $filters, 'page' => $page, 'per_page' => $per_page);
        $rows = array_filter(self::$rows, static function ($row) use ($filters) {
            return $row['id'] > ($filters['after_id'] ?? 0) && $row['id'] <= ($filters['max_id'] ?? PHP_INT_MAX);
        });
        ksort($rows);
        $result = array('rows' => array_slice(array_values($rows), ($page - 1) * $per_page, $per_page), 'total' => count($rows));
        if (self::$query_error) { $result['error'] = 'simulated storage failure'; }
        if (self::$inject_new) {
            self::$rows[9999] = array_merge(reset(self::$rows), array('id' => 9999));
            self::$inject_new = false;
        }
        return $result;
    }
}

require dirname(__DIR__) . '/includes/export.php';
$checks = 0;
function check($condition, $message) {
    ++$GLOBALS['checks'];
    if (!$condition) { throw new RuntimeException('FAIL: ' . $message); }
}
function rejects($callback, $message, $class = InvalidArgumentException::class) {
    try { $callback(); } catch (Throwable $error) {
        check($error instanceof $class, $message . ' (wrong exception: ' . get_class($error) . ')');
        return $error;
    }
    throw new RuntimeException('FAIL: ' . $message . ' did not reject');
}
function no_temporary_files($message) {
    foreach ($GLOBALS['temp_history'] as $path) { check(!is_file($path), $message . ': ' . basename($path)); }
}
function open_zip($path) {
    $zip = new ZipArchive();
    check($zip->open($path) === true, 'valid ZIP container');
    return $zip;
}

try {
    TLIT_CB_Export::register();
    check(isset($hooks['admin_post_tlit_cb_export']), 'authenticated admin-post action registered');
    check(!isset($hooks['admin_post_nopriv_tlit_cb_export']), 'no public download endpoint');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = array('_wpnonce' => 'test-nonce', 'scope' => 'single', 'id' => '1');
    $current_capability = false;
    check(rejects(array('TLIT_CB_Export', 'download'), 'non-admin blocked', TestWPDie::class)->getCode() === 403, 'capability denial returns 403');
    $current_capability = true;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    check(rejects(array('TLIT_CB_Export', 'download'), 'GET blocked', TestWPDie::class)->getCode() === 405, 'GET returns 405');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $nonce_valid = false;
    check(rejects(array('TLIT_CB_Export', 'download'), 'nonce blocked', TestWPDie::class)->getCode() === 403, 'nonce denial returns 403');
    $nonce_valid = true;
    check(!$temp_history, 'authorization failures create no files');

    if (!class_exists('ZipArchive')) {
        rejects(static function () { TLIT_CB_Export::build_download(array('scope' => 'single', 'id' => 1)); }, 'missing ZIP has a controlled error');
        echo "PASS: missing PHP ZIP extension is handled cleanly ($checks checks).\n";
        return;
    }

    $lead = array('id' => 17, 'name' => 'సీత <&> "శ్రీ"', 'email' => '=HYPERLINK("https://example.invalid")', 'phone' => '+919876543210', 'language' => 'Telugu', 'goal' => "\t=2+2", 'status' => 'phone_captured', 'stage' => 'questions', 'course' => 'AI Digital Marketing', 'source_url' => 'https://user:password@example.test/course/?session_token=PRIVATE_TOKEN&otp=555444#secret', 'has_brochure' => 1, 'answer_count' => 3, 'created_at' => '2026-10-13 10:00:00', 'updated_at' => '2026-10-13 10:05:00', 'session_token' => 'RAW_SESSION_SECRET_MUST_NOT_APPEAR', 'api_key' => 'RAW_API_SECRET_MUST_NOT_APPEAR', 'otp' => '987654');
    $messages = array(
        array('role' => 'assistant', 'type' => 'introduction', 'content' => 'Hello! How can I help?', 'created_at' => '2026-10-13 10:00:00'),
        array('role' => 'visitor', 'type' => 'question', 'content' => "తెలుగులో చెప్పండి. मुझे हिन्दी में जानकारी चाहिए। 😀\nPrice is 2026 & <w:fake/>.\n\nOTP: 123456. api_key=not-a-real-secret", 'created_at' => '2026-10-13 10:01:00'),
        array('role' => 'system', 'type' => 'brochure', 'content' => 'Brochure requested.', 'created_at' => '2026-10-13 10:05:00'),
        array('role' => 'visitor', 'type' => 'otp', 'content' => '555555', 'created_at' => '2026-10-13 10:05:00'),
    );
    TLIT_CB_Store::$rows[17] = $lead;
    TLIT_CB_Store::$transcripts[17] = $messages;
    $docx = TLIT_CB_Export::create_docx((object) $lead, $messages);
    check(strpos($docx, realpath($test_dir) . DIRECTORY_SEPARATOR) === 0, 'DOCX stays in private temporary storage');
    $zip = open_zip($docx);
    $required = array('[Content_Types].xml', '_rels/.rels', 'docProps/core.xml', 'docProps/app.xml', 'word/document.xml', 'word/styles.xml', 'word/settings.xml', 'word/_rels/document.xml.rels', 'word/footer1.xml');
    foreach ($required as $part) {
        $xml = $zip->getFromName($part);
        check(is_string($xml), 'DOCX part ' . $part);
        $dom = new DOMDocument();
        check($dom->loadXML($xml, LIBXML_NONET), 'well-formed XML ' . $part);
    }
    $xml = $zip->getFromName('word/document.xml');
    foreach (array('తెలుగులో చెప్పండి', 'मुझे हिन्दी में जानकारी चाहिए', '😀', 'సీత', 'Price is 2026', 'Visitor', 'Lakshmi / Assistant', 'System', '13 Oct 2026, 15:30:00 IST') as $needle) {
        check(strpos($xml, $needle) !== false, 'transcript preserves ' . $needle);
    }
    check(strpos($xml, '&lt;w:fake/&gt;') !== false && strpos($xml, '<w:fake/>') === false, 'XML markup is escaped');
    foreach (array('123456', '555555', 'RAW_SESSION_SECRET', 'RAW_API_SECRET', 'PRIVATE_TOKEN', 'password@', 'not-a-real-secret') as $secret) {
        check(strpos($xml, $secret) === false, 'secret is absent: ' . $secret);
    }
    $dom = new DOMDocument();
    $dom->loadXML($xml, LIBXML_NONET);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    check($xpath->query('//w:t[contains(., "Price is")]')->length === 1, 'one escaped transcript text node');
    check($xpath->query('//w:sectPr/w:footerReference')->length === 1, 'page-number footer relationship');
    $zip->close();
    if (in_array('--fixture', $argv, true)) {
        check(copy($docx, __DIR__ . '/export-fixture.docx'), 'write optional validation fixture');
    }

    $single = TLIT_CB_Export::build_download(array('scope' => 'single', 'id' => 17));
    check($single['filename'] === 'conversation-17-20261013.docx', 'download filename has ID/date, no contact details');
    check(strpos($single['mime'], 'wordprocessingml') !== false, 'DOCX media type');
    TLIT_CB_Export::cleanup();
    no_temporary_files('single DOCX cleanup');

    $lead2 = array_merge($lead, array('id' => 18, 'name' => '@SUM(1+1)', 'email' => '-1+1', 'goal' => "\r\n+1+1"));
    TLIT_CB_Store::$rows[18] = $lead2;
    TLIT_CB_Store::$transcripts[18] = $messages;
    $archive = TLIT_CB_Export::build_download(array('scope' => 'selected', 'ids' => array('17', '18', '17')));
    $zip = open_zip($archive['path']);
    check($zip->numFiles === 3, 'bulk ZIP contains two DOCX files and one manifest, deduplicating IDs');
    check($zip->locateName('conversation-17-20261013.docx') !== false, 'safe first DOCX entry name');
    check($zip->locateName('conversation-18-20261013.docx') !== false, 'safe second DOCX entry name');
    $csv = $zip->getFromName('manifest.csv');
    check(substr($csv, 0, 3) === "\xEF\xBB\xBF", 'CSV UTF-8 BOM');
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, substr($csv, 3));
    rewind($stream);
    $header = fgetcsv($stream, 0, ',', '"', '');
    $first = array_combine($header, fgetcsv($stream, 0, ',', '"', ''));
    $second = array_combine($header, fgetcsv($stream, 0, ',', '"', ''));
    fclose($stream);
    check($first['Name'] === $lead['name'], 'CSV preserves Telugu');
    check($first['Email'][0] === "'" && $first['Phone'][0] === "'" && $first['Goal'][0] === "'", 'formula and +phone cells escaped');
    check($second['Name'][0] === "'" && $second['Email'][0] === "'" && $second['Goal'][0] === "'", 'all formula prefixes and hidden newline prefixes escaped');
    check($first['Source page'] === 'https://example.test/course/', 'manifest source omits URL auth, query, fragment');
    $zip->close();
    $live_temps = array_values(array_filter($temp_history, 'is_file'));
    check($live_temps === array($archive['path']), 'bulk intermediates removed before download');
    TLIT_CB_Export::cleanup();
    no_temporary_files('archive cleanup');

    foreach (array('../17', '17.1', 0, -1, array(17), "17\r\nX-Header:evil", '9999999999999999999999999') as $bad_id) {
        rejects(static function () use ($bad_id) { TLIT_CB_Export::build_download(array('scope' => 'single', 'id' => $bad_id)); }, 'invalid ID rejected');
    }
    rejects(static function () { TLIT_CB_Export::build_download(array('scope' => 'selected', 'ids' => range(1, 201))); }, 'selected limit');
    rejects(static function () { TLIT_CB_Export::build_download(array('scope' => 'selected', 'ids' => array())); }, 'empty selection');
    rejects(static function () { TLIT_CB_Export::build_download(array('scope' => 'single', 'id' => 404)); }, 'missing conversation');
    rejects(static function () { TLIT_CB_Export::build_download(array('scope' => 'filtered', 'filters' => array('search' => array('bad')))); }, 'nested filter rejected');
    rejects(static function () { TLIT_CB_Export::build_download(array('scope' => 'path', 'path' => '/etc/passwd')); }, 'no direct-path exports');
    TLIT_CB_Store::$get_error = true;
    rejects(static function () { TLIT_CB_Export::build_download(array('scope' => 'single', 'id' => 17)); }, 'lead read failure', RuntimeException::class);
    TLIT_CB_Store::$get_error = false;
    TLIT_CB_Store::$message_error = true;
    rejects(static function () { TLIT_CB_Export::build_download(array('scope' => 'single', 'id' => 17)); }, 'transcript read failure', RuntimeException::class);
    TLIT_CB_Store::$message_error = false;
    rejects(static function () use ($lead) { TLIT_CB_Export::create_docx($lead, array(array('role' => 'visitor', 'content' => str_repeat('x', TLIT_CB_Export::MAX_DOCUMENT_BYTES + 1)))); }, 'document byte limit');
    no_temporary_files('validation failure cleanup');

    $failing_rows = (static function () use ($lead) { yield $lead; throw new RuntimeException('simulated failure after first DOCX'); })();
    rejects(static function () use ($failing_rows) { TLIT_CB_Export::create_archive($failing_rows); }, 'partial archive exception', RuntimeException::class);
    no_temporary_files('partial archive cleanup');

    TLIT_CB_Store::$rows = array();
    for ($id = 1; $id <= 205; ++$id) { TLIT_CB_Store::$rows[$id] = array_merge($lead, array('id' => $id)); }
    TLIT_CB_Store::$inject_new = true;
    $filtered = TLIT_CB_Export::build_download(array('scope' => 'filtered', 'filters' => array('course' => 'AI Digital Marketing', 'arbitrary_input' => 'ignored')));
    $zip = open_zip($filtered['path']);
    check($zip->numFiles === 206, '205 filtered conversations plus manifest');
    check($zip->locateName('conversation-9999-20261013.docx') === false, 'new inserts excluded by snapshot');
    check(count(TLIT_CB_Store::$query_calls) === 3, 'filtered export uses three bounded pages');
    foreach (TLIT_CB_Store::$query_calls as $i => $call) {
        check($call['page'] === 1 && $call['per_page'] === 100, 'keyset page size');
        check($call['filters']['max_id'] === 205 && $call['filters']['after_id'] === $i * 100, 'stable ID snapshot and cursor');
        check($call['filters']['export_order'] === 'id_asc', 'stable ascending ID order');
        check(!isset($call['filters']['arbitrary_input']), 'filter whitelist');
    }
    $zip->close();
    TLIT_CB_Export::cleanup();
    no_temporary_files('filtered cleanup');
    TLIT_CB_Store::$query_error = true;
    rejects(static function () { TLIT_CB_Export::build_download(array('scope' => 'filtered')); }, 'query failures are not exported as empty transcripts', RuntimeException::class);
    TLIT_CB_Store::$query_error = false;
    no_temporary_files('query error cleanup');
    TLIT_CB_Store::$rows = array();
    rejects(static function () { TLIT_CB_Export::build_download(array('scope' => 'filtered')); }, 'empty filter snapshot');
    for ($id = 1; $id <= 2001; ++$id) { TLIT_CB_Store::$rows[$id] = array_merge($lead, array('id' => $id)); }
    rejects(static function () { TLIT_CB_Export::build_download(array('scope' => 'filtered')); }, 'filtered row limit');
    no_temporary_files('all temporary paths cleaned');
    echo "PASS: conversation DOCX/ZIP exports ($checks checks).\n";
} finally {
    TLIT_CB_Export::cleanup();
    foreach ($temp_history as $path) { if (is_file($path)) { unlink($path); } }
    rmdir($test_dir);
    rmdir(ABSPATH);
}
