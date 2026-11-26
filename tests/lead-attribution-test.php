<?php
/** Offline contract test. No SMS, Teams messages, CRM records or network calls. */
if(PHP_SAPI!=='cli') { http_response_code(404);exit; }
require dirname(__DIR__).'/run_test.php';
require_once dirname(__DIR__).'/conversation-bridge/includes/teams-notifications.php';
require_once dirname(__DIR__).'/conversation-bridge/includes/admin.php';
function wp_date($format,$time,$zone=null) { return (new DateTimeImmutable('@'.$time))->setTimezone($zone??new DateTimeZone('UTC'))->format($format); }
function verify_attribution($ok,$message) { if(!$ok) throw new RuntimeException($message); }
$required=explode(' ','fbp fbc gclid gbraid wbraid fbclid ga_client_id session_id utm_source utm_medium utm_campaign utm_adgroup utm_term utm_content landing_page referrer');
verify_attribution(!array_diff($required,TLIT_LP_Leads::tracking_keys()),'The mandatory hidden fields must remain in Bridge storage and CSV exports.');
foreach(array('google','facebook') as $source) {
    $tracking=array_fill_keys($required,'fixture-'.$source);
    $tracking=array_replace($tracking,array('utm_source'=>$source,'campaign_name'=>'SCM November Campaign','ad_name'=>'SCM Consultant Video','adset_name'=>'SCM Freshers','campaign_id'=>'120000001','adset_id'=>'120000002','ad_id'=>'120000003','landing_page'=>'https://lp.techleadsit.com/oracle-fusion-scm-course-it?email=private#details','referrer'=>'https://example.test/ad?token=private','api_key'=>'MUST-NOT-LEAK','otp'=>'1234'));
    if($source==='google') foreach(array('fbp','fbc','fbclid') as $key) $tracking[$key]='';
    else foreach(array('gclid','gbraid','wbraid') as $key) $tracking[$key]='';
    $normalized=TLIT_Attribution::normalize(array('tracking'=>$tracking));
    verify_attribution(TLIT_Attribution::normalize($tracking)===$normalized,'Flat and nested capture must have the same attribution.');
    verify_attribution(!isset($normalized['api_key'],$normalized['otp']),'Private fields cannot enter attribution.');
    $details=array('target_package'=>'₹4–8 LPA','highest_qualification'=>'Engineering','coding_comfort'=>'No coding knowledge','training_budget'=>'Yes, I have a budget of ₹25,000–₹30,000','enrollment_decision'=>'With family','custom_learning_answer'=>'Procurement consultant','otp'=>'1234','access_token'=>'MUST-NOT-LEAK');
    $row=(object)array('id'=>123,'payload'=>json_encode(array('tracking'=>$normalized,'details'=>$details,'goal'=>'First ERP job')),'page_variant'=>'oracle_scm','submission_url'=>'https://lp.techleadsit.com/oracle-fusion-scm-course-it','source'=>'general','lead_status'=>'potential','advisor_note'=>'Contacted','otp_verified'=>true,'name'=>'Synthetic QA','phone'=>'+447700900123','email'=>'qa@example.invalid','created_at'=>'2026-11-26 04:30:00');
    $payload=TLIT_LP_Leads::crm_payload($row);$fields=$payload['fields'];
    foreach($required as $key) verify_attribution(array_key_exists($key,$fields),'Missing CRM tracking field '.$key);
    foreach(array('gaclientid'=>'ga_client_id','sessionid'=>'session_id','utmcampaign'=>'utm_campaign','utmadgroup'=>'utm_adgroup','utmcontent'=>'utm_content') as $api=>$key) verify_attribution($fields[$api]===$normalized[$key],'Incorrect TeleCRM API alias '.$api);
    foreach(array('SCM November Campaign','SCM Consultant Video','SCM Freshers','Procurement consultant','Engineering','₹4–8 LPA','₹25,000–₹30,000') as $value) verify_attribution(str_contains($fields['remarks'],$value)&&str_contains($payload['actions'][0]['text'],$value),'CRM field and note must retain '.$value);
    verify_attribution(TLIT_LP_Leads::scm_fields($details)['Target salary package']==='₹4–8 LPA','Target package must be visible in Bridge and consultant fields.');
    verify_attribution($fields['course']==='Oracle Fusion SCM','Correct course must survive handoff.');
    verify_attribution($fields['lead_date']==='2026-11-26 10:00:00','Original lead time must remain in IST.');
    foreach(array_keys($fields) as $key) verify_attribution(!preg_match('/enrol|payment|amount_paid/i',$key),'Counsellor enrolment/payment fields must stay untouched.');
    if($source==='facebook') { verify_attribution($fields['facebook_campaign']==='SCM November Campaign'&&$fields['facebook_ad']==='SCM Consultant Video'&&$fields['facebook_ad_set_name']==='SCM Freshers','Map explicit Meta names to the existing workspace fields.'); }
    $card=TLIT_Teams_Notifications::card(array('event'=>'form','name'=>$row->name,'phone'=>$row->phone,'variant'=>'oracle_scm','source'=>'General form','created'=>$row->created_at,'tracking'=>$normalized,'reference'=>'LP-123'), 'offline-notification');
    $facts=array_column($card['attachments'][0]['content']['body'][2]['facts'],'value','title');
    verify_attribution($facts['Campaign name']==='SCM November Campaign'&&$facts['Ad name']==='SCM Consultant Video'&&$facts['Ad set / ad group name']==='SCM Freshers','Teams must show all three names.');
    foreach(array('form','chatbot','dropoff') as $event) {
        $body=json_encode(TLIT_Teams_Notifications::card(array('event'=>$event,'source'=>'General form','name'=>$row->name,'phone'=>$row->phone,'created'=>$row->created_at,'tracking'=>$tracking,'reference'=>'QA'), 'offline'));
        verify_attribution(str_contains($body,'SCM Consultant Video')&&!str_contains($body,'MUST-NOT-LEAK'),'Every Teams event must use approved attribution only.');
    }
    ob_start();(new ReflectionMethod('TLIT_CB_Admin','attribution'))->invoke(null,array('tracking_json'=>json_encode($tracking)));$html=ob_get_clean();
    verify_attribution(str_contains($html,'SCM Consultant Video')&&str_contains($html,'Analytics session ID')&&!str_contains($html,'MUST-NOT-LEAK'),'Administrator attribution must show captured fields without private data.');
    verify_attribution(!str_contains(json_encode($fields),'MUST-NOT-LEAK'),'Private fields cannot enter CRM notes.');
}
$missing=TLIT_Attribution::summary(array());verify_attribution(count(array_unique($missing))===1&&reset($missing)==='Not recorded','Missing names must never be fabricated.');
$fallback=TLIT_Attribution::summary(array('utm_campaign'=>'UTM campaign','utm_content'=>'UTM ad','utm_adgroup'=>'UTM ad group'));verify_attribution($fallback['Ad name']==='UTM ad'&&$fallback['Ad set / ad group name']==='UTM ad group','UTM name fallback must be preserved.');
$aliases=TLIT_Attribution::normalize(array('gaclientid'=>'100.200','utmcampaign'=>'Legacy name','ad_set_name'=>'Legacy set'));verify_attribution($aliases['ga_client_id']==='100.200'&&$aliases['utm_campaign']==='Legacy name'&&$aliases['adset_name']==='Legacy set','Legacy field names must normalize.');
$injected=techleadsit_with_tracking('<html><head></head><body></body></html>');verify_attribution(str_contains($injected,'lead-attribution.js?v=1'),'Tracking capture must load even without GTM configured.');
verify_attribution(substr_count(techleadsit_with_tracking($injected),'lead-attribution.js?v=1')===1,'Collector must not be injected twice.');
echo "PASS: Mandatory hidden fields, Google/Meta names, Bridge visibility, all Teams alerts, TeleCRM API fields and full form-answer notes. No external requests.\n";
