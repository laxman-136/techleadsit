<?php
if (!defined('ABSPATH')) exit;
function tlit_financials_bant_options() { return array(
'bant_need'=>array('Move from finance or accounting into an ERP role','Build practical skills for Oracle Financials interviews','Return to work after a career gap','Improve my skills for my current job','I am only exploring career options'),
'bant_timeline'=>array('Within 30 days','Within 1 to 3 months','After 3 months','I have not decided yet'),
'bant_budget'=>array('Yes, I can invest if the course and fees suit me','I need to understand the fees and payment options first','My family or employer would need to fund it','I am only looking for free learning right now'),
'bant_authority'=>array('I will decide myself','I will decide together with my family','My employer needs to approve it','I am enquiring for someone else'),
); }
function tlit_financials_bant_validate($details) {
    if (!is_array($details)) return false;
    foreach (tlit_financials_bant_options() as $key=>$allowed) {
        if (!isset($details[$key]) || !is_string($details[$key]) || !in_array($details[$key],$allowed,true)) return false;
    }
    return true;
}
function tlit_financials_bant_priority($details) {
    if (!tlit_financials_bant_validate($details)) return 'Not assessed';
    $o=tlit_financials_bant_options();
    if ($details['bant_need']===$o['bant_need'][4] || $details['bant_budget']===$o['bant_budget'][3] || in_array($details['bant_timeline'],array($o['bant_timeline'][2],$o['bant_timeline'][3]),true)) return 'Nurture — exploring or later';
    if ($details['bant_budget']===$o['bant_budget'][0] && $details['bant_authority']===$o['bant_authority'][0] && $details['bant_timeline']===$o['bant_timeline'][0]) return 'Priority follow-up — confirm fit and fees';
    return 'Discuss budget, decision and timing';
}
function tlit_financials_bant_fields($details) {
    if (empty($details['bant_need'])) return array();
    $fields=array('BANT follow-up (self-reported)'=>tlit_financials_bant_priority($details));
    foreach(array('Budget'=>'bant_budget','Decision maker'=>'bant_authority','Learning need'=>'bant_need','Start timeline'=>'bant_timeline') as $label=>$key) $fields['BANT '.$label]=$details[$key]??'Not recorded';
    return $fields;
}

// Resolve the existing admin controls on the server. Client flags never grant bypass.
function tlit_financials_otp_required() {
    $o=get_option('techleadsit_course_settings_financials',array());
    if(!is_array($o)) return true;
    return (!isset($o['otp_verification_enabled']) || !empty($o['otp_verification_enabled']))
        && empty($o['otp_disabled_slugs']['oracle-fusion-financials-course-online'])
        && (!isset($o['otp_required_hero']) || !empty($o['otp_required_hero']));
}
