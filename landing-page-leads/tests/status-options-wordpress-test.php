<?php
/** Isolated local database save/readback only. Delivery is disabled for every review. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$completed = false;
register_shutdown_function(function () use (&$completed) {
    if (!$completed) { fwrite(STDERR, "FAIL: local status verification did not complete.\n"); exit(1); }
});
$bootstrap = $argv[1] ?? '';
if (!is_file($bootstrap)) { throw new RuntimeException('Pass an isolated localhost WordPress bootstrap.'); }
require $bootstrap;
if (!defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL || !in_array(wp_parse_url(home_url(), PHP_URL_HOST), array('localhost','127.0.0.1'), true) || !in_array(explode(':', DB_HOST)[0], array('localhost','127.0.0.1'), true)) {
    throw new RuntimeException('Use an isolated localhost database with external HTTP blocked.');
}
$source = realpath(dirname(__DIR__).'/landing-page-leads.php');
if (realpath((new ReflectionClass('TLIT_LP_Leads'))->getFileName()) !== $source) { throw new RuntimeException('The current checkout must supply the lead service.'); }
$requests = 0;
$block = function () use (&$requests) { $requests++; throw new RuntimeException('All network requests are forbidden in status save tests.'); };
add_filter('pre_http_request', $block, PHP_INT_MAX, 3);
function stored_status_check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
TLIT_LP_Leads::install();
global $wpdb; $table = TLIT_LP_Leads::table(); $id = 0;
try {
    $now = current_time('mysql', true); $statuses = TLIT_LP_Leads::statuses();
    stored_status_check(count($statuses) === 18, 'Expected the original 18 statuses.');
    $inserted = $wpdb->insert($table, array('request_id'=>'offline-status-'.wp_generate_uuid4(), 'fingerprint'=>str_repeat('a',64),
        'created_at'=>$now, 'updated_at'=>$now, 'name'=>'LOCAL STATUS QA', 'phone'=>'+447700900123', 'email'=>'status@example.invalid',
        'source'=>'general', 'page_variant'=>'oracle_financials', 'submission_url'=>home_url('/offline-status-qa/'),
        'payload'=>wp_json_encode(array('goal'=>'Offline status verification')), 'lead_status'=>'new', 'crm_status'=>'pending', 'advisor_note'=>''));
    stored_status_check($inserted === 1, 'Create the isolated local record.'); $id = (int) $wpdb->insert_id;
    $detail = new ReflectionMethod('TLIT_LP_Admin', 'detail');
    foreach ($statuses as $key=>$label) {
        $saved = TLIT_LP_Leads::save_status($id, $key, 'Offline review: '.$label, 0, false);
        stored_status_check(!is_wp_error($saved), 'Save '.$key.' without delivery.');
        $fresh = TLIT_LP_Leads::get($id);
        stored_status_check($fresh->lead_status === $key && $fresh->advisor_note === 'Offline review: '.$label, 'Read back '.$key.' and its note from the database.');
        $history = json_decode($fresh->review_history, true); $latest = end($history);
        stored_status_check($latest['to'] === $key, 'Persist '.$key.' in review history.');
        stored_status_check($fresh->crm_status === 'pending' && (int)$fresh->crm_attempts === 0 && empty($fresh->crm_payload), 'Saving '.$key.' with delivery disabled must never attempt a sync.');
        ob_start(); $detail->invoke(null, $id); $page = ob_get_clean();
        $dom = new DOMDocument(); $dom->loadHTML('<?xml encoding="UTF-8">'.$page, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom); $options = $xpath->query('//select[@name="lead_status"]/option');
        $selected = $xpath->query('//select[@name="lead_status"]/option[@selected]');
        stored_status_check($options->length === 18 && $selected->length === 1 && $selected->item(0)->getAttribute('value') === $key, 'The real review page must reload all choices with '.$key.' selected.');
        echo 'PASS local save/readback: '.$label."\n";
    }
    $before = TLIT_LP_Leads::get($id);
    stored_status_check(is_wp_error(TLIT_LP_Leads::save_status($id, 'invalid_status', 'Invalid', 0, false)), 'Reject a status outside the original list.');
    stored_status_check(TLIT_LP_Leads::get($id)->lead_status === $before->lead_status, 'An invalid choice must not overwrite the saved status.');
    stored_status_check($requests === 0, 'No network or sync attempt is allowed.');
    echo "PASS: all 18 options persisted with notes/history; invalid status rejected; zero sync attempts and zero network requests.\n";
} finally {
    if ($id) {
        $wpdb->delete($table, array('id'=>$id));
        if (class_exists('TLIT_CB_Lead_Ops')) {
            $wpdb->delete(TLIT_CB_Lead_Ops::table(true), array('reference'=>'LP-'.$id));
            $wpdb->delete(TLIT_CB_Lead_Ops::table(), array('reference'=>'LP-'.$id));
        }
    }
    remove_filter('pre_http_request', $block, PHP_INT_MAX);
}
$completed = true;
