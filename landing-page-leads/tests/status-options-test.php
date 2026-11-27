<?php
/** Offline dropdown and payload contract. No sync method or network request is called. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2).'/run_test.php';
require_once dirname(__DIR__).'/admin.php';
function selected($value, $current, $echo = false) { return $value === $current ? ' selected="selected"' : ''; }
function status_check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
function wp_remote_post() { throw new RuntimeException('Network requests are forbidden in this test.'); }

$expected = array(
    'new'=>'New', 'junk'=>'Junk Lead',
    'demo_interested'=>'Interested to join the Demo', 'intended_to_join'=>'Potential Lead 100',
    'demo_attended'=>'Demo Attended', 'next_batch'=>'Looking for Next batch',
    'call_not_answered'=>'Call not answered and Shared the Data',
    'number_email'=>'Number is not working and sent an email',
    'wrong_number'=>'Wrong Number &Number Not working', 'different_course'=>'Different Course',
    'not_interested'=>'Not Interested', 'potential'=>'50 % Potential',
    'below_50_potential'=>'below 50 % Potential', 'high_potential'=>'60-80 Potential',
    'become_trainer'=>'Become Trainer', 'different_requirement'=>'Different Requirement',
    'enrolled'=>'Enrolled', 'lost'=>'Lost',
);
status_check(TLIT_LP_Leads::statuses() === $expected, 'The original 18 stored keys, labels and order must be preserved.');
require_once dirname(__DIR__, 2).'/conversation-bridge/includes/qualification.php';
status_check(TLIT_CB_Qualification::statuses() === $expected, 'Chatbot and private team reviews must use the same 18 statuses.');
status_check(TLIT_LP_Leads::crm_statuses() === array_diff_key($expected, array_flip(array('new','junk'))), 'Exactly New and Junk Lead must be excluded from CRM delivery.');

foreach (array_keys($expected) as $current) {
    ob_start(); TLIT_LP_Admin::qualification_field($current); $html = ob_get_clean();
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);
    $options = $xpath->query('//select[@name="lead_status"]/option'); $actual = array();
    foreach ($options as $option) {
        status_check(!$option->hasAttribute('disabled') && !$option->hasAttribute('hidden'), 'A valid status must remain selectable.');
        $actual[$option->getAttribute('value')] = $option->textContent;
    }
    status_check($options->length === 18 && $actual === $expected, 'The real review field must render all 18 original choices.');
    $selection = $xpath->query('//select[@name="lead_status"]/option[@selected]');
    status_check($selection->length === 1 && $selection->item(0)->getAttribute('value') === $current, 'The saved '.$current.' status must remain selected.');
    status_check(str_contains($html, '18 lead statuses available.'), 'The option count must be visible below the dropdown.');

    $row = (object) array('id'=>123, 'name'=>'Offline status QA', 'phone'=>'+447700900123',
        'email'=>'offline-status@example.invalid', 'source'=>'general', 'page_variant'=>'oracle_financials',
        'submission_url'=>'https://example.invalid/course/', 'lead_status'=>$current, 'advisor_note'=>'Offline review',
        'otp_verified'=>false, 'created_at'=>'2026-11-27 04:30:00', 'payload'=>json_encode(array('goal'=>'Course enquiry')));
    $payload = TLIT_LP_Leads::crm_payload($row); $fields = $payload['fields'];
    if (in_array($current, array('new','junk'), true)) {
        status_check(!array_key_exists('status', $fields), 'New and Junk must not produce a CRM status.');
    } else {
        status_check(($fields['status'] ?? '') === $expected[$current], 'The '.$current.' payload must use the exact original TeleCRM label.');
    }
    status_check($fields['phone'] === $row->phone && $fields['course'] === 'Oracle Fusion Financials', 'Changing status must retain the lead identity and course.');
    status_check(str_contains($fields['remarks'], 'Counsellor qualification: '.$expected[$current]), 'Remarks must retain the selected status.');
    foreach (array_keys($fields) as $key) {
        status_check(!preg_match('/enrol|enroll|payment|paid|receipt|fee/i', $key), 'Status review must not populate manual enrolment or payment fields.');
    }
}
ob_start(); TLIT_LP_Admin::qualification_field('invalid_status'); $unknown = ob_get_clean();
status_check(str_contains($unknown, '<option value="" disabled selected>Choose qualification</option>'), 'An unknown legacy status must require an explicit choice.');
echo "PASS: all 18 rendered choices, saved selections and exact 16 TeleCRM payload mappings; New/Junk remain local. No syncs or network requests.\n";
