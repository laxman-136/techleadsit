<?php
/** Local-only persistence and CRM handoff, every outbound request intercepted. */
if(PHP_SAPI!=='cli') { http_response_code(404);exit; }
$bootstrap=$argv[1]??'';if(!is_file($bootstrap))throw new RuntimeException('Pass an isolated WordPress bootstrap.');
require $bootstrap;
if(!defined('WP_HTTP_BLOCK_EXTERNAL')||!WP_HTTP_BLOCK_EXTERNAL||!in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('localhost','127.0.0.1'),true))throw new RuntimeException('Use localhost with external HTTP blocked.');
require_once dirname(__DIR__).'/techleadsit.php';
TLIT_LP_Leads::install();TLIT_CB_Lead_Ops::install();TLIT_Teams_Notifications::install();
global $wpdb;$ids=array();$sent=array();
$mock=function($pre,$args,$url)use(&$sent){if(str_contains($url,'next.telecrm.in')){$sent[]=json_decode($args['body'],true);return array('response'=>array('code'=>200),'body'=>'{"success":true,"leadId":"synthetic-attribution"}');}return new WP_Error('blocked','All other providers blocked in attribution test');};
add_filter('pre_http_request',$mock,PHP_INT_MAX,3);
function check_stored_attribution($ok,$label){if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label."\n";}
try {
 foreach(array('google','facebook') as $source) {
  $tracking=array_fill_keys(explode(' ','fbp fbc gclid gbraid wbraid fbclid ga_client_id session_id utm_source utm_medium utm_campaign utm_adgroup utm_term utm_content landing_page referrer'),'fixture-'.$source);
  $tracking=array_replace($tracking,array('utm_source'=>$source,'campaign_name'=>'QA SCM Campaign','ad_name'=>'QA SCM Video','adset_name'=>'QA Freshers','ad_id'=>'12000123','adset_id'=>'12000456','landing_page'=>home_url('/oracle-fusion-scm-course-it/'),'referrer'=>'https://example.invalid/advert'));
  if($source==='facebook')foreach(array('gclid','gbraid','wbraid') as $key)$tracking[$key]='';
  $details=array('highest_qualification'=>'Engineering','training_budget'=>'Discuss fee first','custom_learning_answer'=>'Procurement consultant');
  $payload=array('request_id'=>wp_generate_uuid4(),'name'=>'Attribution QA','email'=>'attribution@example.invalid','phone'=>'+447700900123','goal'=>'First ERP job','details'=>$details,'tracking'=>$tracking,'submission_url'=>home_url('/oracle-fusion-scm-course-it/'),'page_variant'=>'oracle_scm');
  $result=TLIT_LP_Leads::ingest($payload,array('source'=>'general','otp_verified'=>false));
  check_stored_attribution(!is_wp_error($result),'Lead saved in main Bridge');$id=$result['lead_id'];$ids[]=$id;
  $row=TLIT_LP_Leads::get($id);$stored=json_decode($row->payload,true);
  foreach($tracking as $key=>$value)check_stored_attribution(($stored['tracking'][$key]??null)===$value,'Stored '.$source.' '.$key);
  check_stored_attribution($stored['details']['custom_learning_answer']==='Procurement consultant','All human form answers saved');
  $record=TLIT_CB_Dashboard_Data::record('LP-'.$id);
  check_stored_attribution(!is_wp_error($record)&&($record['campaign']??'')==='QA SCM Campaign','Main Conversion Bridge resolves explicit campaign name');
  $lead=TLIT_Teams_Notifications::lead('form',$id);$card=json_encode(TLIT_Teams_Notifications::card($lead,'offline-attribution'));
  check_stored_attribution(str_contains($card,'QA SCM Campaign')&&str_contains($card,'QA SCM Video')&&str_contains($card,'QA Freshers'),'Teams names come from saved record');
  TLIT_LP_Leads::save_status($id,'potential','Synthetic local review',1);
  $out=end($sent);
  check_stored_attribution($out&&str_contains($out['fields']['remarks'],'Procurement consultant')&&str_contains($out['fields']['remarks'],'QA SCM Video'),'Real handoff body retains attribution and all answers');
  foreach($tracking as $key=>$value)check_stored_attribution(($out['fields'][$key]??null)===$value,'Handoff retains '.$key);
 }
 check_stored_attribution(count($sent)===2,'Exactly two mocked CRM handoffs; no provider requests');
} finally {
 foreach($ids as $id){$wpdb->delete(TLIT_Teams_Notifications::table(),array('identity_key'=>'LP-'.$id));$wpdb->delete($wpdb->prefix.'tlit_cb_lead_activity',array('reference'=>'LP-'.$id));$wpdb->delete($wpdb->prefix.'tlit_cb_lead_ops',array('reference'=>'LP-'.$id));$wpdb->delete(TLIT_LP_Leads::table(),array('id'=>$id));}
 remove_filter('pre_http_request',$mock,PHP_INT_MAX);
}
