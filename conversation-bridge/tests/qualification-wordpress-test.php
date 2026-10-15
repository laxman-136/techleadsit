<?php
/** Actual isolated WordPress/MariaDB checks. No production data or real provider requests. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$bootstrap=$argv[1]??'';
if (!is_file($bootstrap)) { fwrite(STDERR,"Pass the isolated WordPress wp-load.php path.\n");exit(1); }
require $bootstrap;
if (!defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL || !in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('localhost','127.0.0.1'),true)) { throw new RuntimeException('Only run on isolated localhost with external HTTP blocked.'); }
if (!function_exists('tlit_dm_crm_url')) { function tlit_dm_crm_url(){return 'https://next.telecrm.in/api/b1/enterprise/qa-conversation-only/autoupdatelead';} }
if (!class_exists('TLIT_CB_Qualification') || !TLIT_CB_Qualification::ready()) { throw new RuntimeException('Install updated Conversation Bridge and Landing Page Leads modules first.'); }
wp_set_current_user(1);
if (!current_user_can('manage_options')) { throw new RuntimeException('The isolated test administrator is unavailable.'); }
$checks=0;$made=array();$lead_ids=array();$calls=0;$last_payload=array();$run=bin2hex(random_bytes(5));
function cbq_check($value,$label){++$GLOBALS['checks'];if(!$value)throw new RuntimeException('FAIL: '.$label);echo "PASS $label\n";}
function cbq_event($tag,$phone='+447700900123',$email='') {
    $token=substr(hash('sha256',$GLOBALS['run'].'|'.$tag),0,48);
    return array('session'=>$token,'event_id'=>'intro','course'=>'digital_marketing','source_url'=>home_url('/ai-powered-digital-marketing-course/'),
        'contact'=>array('name'=>'QA Conversation '.$tag,'phone'=>$phone,'email'=>$email),'language'=>'English','goal'=>'Find my first digital marketing job','stage'=>'question','answer_count'=>1,
        'tracking'=>array('utm_source'=>'qa_verification','utm_campaign'=>'isolated-'.$GLOBALS['run'],'gclid'=>'qa-click','fb_adset_id'=>'qa-adset','landing_page'=>home_url('/ai-powered-digital-marketing-course/')),
        'messages'=>array(array('role'=>'visitor','type'=>'question','content'=>'I want my first digital marketing job. What will I learn?')));
}
function cbq_capture($event){$id=TLIT_CB_Store::capture($event);cbq_check(is_int($id)&&$id>0,'synthetic conversation saved');$GLOBALS['made'][]=$id;return $id;}
function cbq_brochure($token,$email){$row=TLIT_CB_Store::for_session($token);return TLIT_CB_Qualification::brochure($token,array('name'=>$row['name'],'phone'=>$row['phone'],'email'=>$email,'goal'=>'Not provided','tracking'=>json_decode($row['tracking_json'],true),'submission_url'=>$row['source_url'],'page_variant'=>'v2','otp_verified'=>false));}
global $wpdb;$parents=$wpdb->prefix.'tlit_cb_conversations';$table=TLIT_LP_Leads::table();
$http=function($pre,$args,$url)use(&$calls,&$last_payload,$wpdb){
    if (strpos($url,'https://next.telecrm.in/')===0) {
        cbq_check((int)$wpdb->get_var('SELECT @@in_transaction')===0,'CRM request happens after conversation transaction commits');
        ++$calls;$last_payload=json_decode($args['body'],true);
        return array('response'=>array('code'=>200),'body'=>'{"success":true,"leadId":"qa-existing-id"}','headers'=>array());
    }
    return new WP_Error('blocked','External requests disabled by the test.');
};
add_filter('pre_http_request',$http,PHP_INT_MAX,3);
try {
    cbq_check(TLIT_CB_Store::DB_VERSION==='4'&&get_option(TLIT_CB_Store::VERSION_OPTION)==='4','live module boot upgrades conversation schema to version 4');
    $event=cbq_event('qualify-first','');$id=cbq_capture($event);
    $columns=$wpdb->get_col("SHOW COLUMNS FROM $parents");
    cbq_check(!array_diff(array('lead_id','qualification_status','qualification_note'),$columns),'qualification linkage columns installed in MariaDB');
    TLIT_CB_Store::update_status($id,'contacted');$before=TLIT_CB_Store::get($id);
    update_option(TLIT_CB_Store::VERSION_OPTION,'3',false);cbq_check(TLIT_CB_Store::install()===true,'version 3 upgrade safely runs');
    cbq_check(TLIT_CB_Store::get($id)['name']===$before['name']&&TLIT_CB_Store::get($id)['status']==='contacted','upgrade preserves old conversation and review status');
    cbq_check(is_wp_error(TLIT_CB_Qualification::qualify($id,'potential','',1))&&$calls===0,'missing phone blocks qualification without provider request');
    cbq_check(TLIT_CB_Qualification::qualify($id,'junk','Incomplete enquiry',1)===true,'missing-phone conversation can be marked junk');
    cbq_check((int)TLIT_CB_Store::get($id)['lead_id']===0,'junk-only incomplete chat does not create canonical lead');
    $event['event_id']='phone';$event['contact']['phone']='+447700900123';$event['messages']=array();TLIT_CB_Store::capture($event);
    $result=TLIT_CB_Qualification::qualify($id,'potential','First call completed',1);
    cbq_check(!is_wp_error($result)&&$result->lead_status==='potential'&&$result->crm_status==='sent','first qualification creates and syncs canonical lead');$lead_ids[]=$lead_id=(int)$result->id;
    cbq_check($calls===1&&$last_payload['fields']['status']==='50 % Potential','approved Potential stage reaches mocked TeleCRM');
    cbq_check($result->email===''&&$result->phone==='+447700900123','ordinary chat qualification permits missing email and keeps country code');
    cbq_check((int)TLIT_CB_Store::get($id)['lead_id']===$lead_id&&TLIT_CB_Store::get($id)['status']==='contacted','shared link persists independently of review status');
    $data=json_decode($result->payload,true);
    cbq_check($data['tracking']['gclid']==='qa-click'&&$data['tracking']['fb_adset_id']==='qa-adset'&&$result->page_variant==='v2','chat qualification keeps page variant and hidden attribution');
    TLIT_CB_Qualification::qualify($id,'potential','First call completed',1);cbq_check($calls===1,'same review revision is idempotent');
    $email='cb-qualify-'.$run.'@example.invalid';$brochure=cbq_brochure($event['session'],$email);
    cbq_check(!is_wp_error($brochure)&&$brochure['lead_id']===$lead_id&&$brochure['receipt']==='LP-'.$lead_id,'brochure after qualification reuses canonical lead');
    cbq_check(TLIT_LP_Leads::get($lead_id)->source==='chatbot_brochure'&&$calls===1,'brochure upgrades local source without sending again');
    cbq_check($brochure['download_token']===TLIT_LP_Leads::download_token($lead_id,'cb-conversation-'.$id),'brochure proof uses the canonical lead reference');
    $download=new WP_REST_Request('POST');$download->set_header('Content-Type','application/json');$download->set_body(wp_json_encode(array('receipt'=>$brochure['receipt'],'download_token'=>$brochure['download_token'])));
    cbq_check(!is_wp_error(TLIT_LP_Leads::download($download)),'canonical brochure receipt authorises download tracking');
    $event['event_id']='goal-update';$event['goal']='Grow my own business';$event['goal_authoritative']=true;$event['contact']['email']=$email;$event['messages']=array(array('role'=>'visitor','type'=>'question','content'=>'Actually I run my own business and want to grow it.'));
    tlit_cb_capture($event);$data=json_decode(TLIT_LP_Leads::get($lead_id)->payload,true);
    cbq_check(stripos($data['goal'],'business')!==false&&$data['brochure_downloaded']&&$calls===1,'later goal capture refreshes canonical metadata while retaining brochure proof and avoiding CRM');
    foreach(TLIT_LP_Leads::crm_statuses() as $status=>$native){$before_calls=$calls;TLIT_CB_Qualification::qualify($id,$status,'Reviewed '.$status,1);cbq_check($calls===$before_calls+1&&$last_payload['fields']['status']===$native&&TLIT_CB_Store::get($id)['qualification_status']===$status,'later qualified status syncs same lead: '.$native);}
    $before_calls=$calls;TLIT_CB_Qualification::qualify($id,'junk','Do not send',1);cbq_check($calls===$before_calls&&TLIT_LP_Leads::get($lead_id)->lead_status==='junk','Junk after qualification blocks additional CRM updates');
    TLIT_LP_Leads::save_status($lead_id,'high_potential','Changed from landing lead inbox',1,false);
    ob_start();TLIT_CB_Qualification::panel(TLIT_CB_Store::get($id));$panel=ob_get_clean();
    cbq_check(strpos($panel,'value="high_potential" selected')!==false&&strpos($panel,'60-80 Potential')!==false,'conversation UI reads canonical status changed in Landing Page Leads');
    $event2=cbq_event('brochure-first','+919000012345','cb-brochure-'.$run.'@example.invalid');$id2=cbq_capture($event2);
    $brochure2=cbq_brochure($event2['session'],$event2['contact']['email']);cbq_check(!is_wp_error($brochure2),'brochure-first shared lead is stored');$lead_ids[]=$lead2=(int)$brochure2['lead_id'];
    $before_calls=$calls;$qualified2=TLIT_CB_Qualification::qualify($id2,'potential','Brochure reviewed',1);
    cbq_check(!is_wp_error($qualified2)&&(int)$qualified2->id===$lead2&&$calls===$before_calls+1,'qualification after brochure uses same canonical row');
    cbq_check((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE request_id=%s",'cb-conversation-'.$id2))===1,'both capture orders keep one canonical row per conversation');
    // Simulate a brochure link appearing after an unlocked read but before the parent lock.
    $race=cbq_event('race');$race_id=cbq_capture($race);$p=TLIT_CB_Qualification::data(TLIT_CB_Store::get($race_id));$precreated=TLIT_LP_Leads::sync_conversation($p);$lead_ids[]=$race_lead=(int)$precreated['lead_id'];
    $inject=null;$inject=function($sql)use(&$inject,$race_id,$race_lead,$parents){if(strpos($sql,"SELECT * FROM $parents WHERE id=$race_id FOR UPDATE")!==false){remove_filter('query',$inject);TLIT_CB_Store::update_qualification($race_id,$race_lead,'potential','Concurrent link');}return $sql;};add_filter('query',$inject);
    $before_calls=$calls;$held=TLIT_CB_Qualification::qualify($race_id,'junk','Latest counsellor decision',1);remove_filter('query',$inject);
    cbq_check(!is_wp_error($held)&&(int)TLIT_CB_Store::get($race_id)['lead_id']===$race_lead&&TLIT_LP_Leads::get($race_lead)->lead_status==='junk'&&$calls===$before_calls,'New/Junk rechecks concurrent canonical link and never detaches or sends it');
    $missing=TLIT_CB_Store::with_locked_conversation(PHP_INT_MAX,function(){throw new RuntimeException('Must not run');});cbq_check(is_wp_error($missing),'erased or missing parent cannot create orphan lead');
    $fail=function($sql)use($table){return strpos($sql,'DELETE FROM `'.$table.'`')===0?'DELETE FROM nonexistent_cb_qualification_test':$sql;};add_filter('query',$fail);
    $erased=TLIT_CB_Store::privacy_erase($event2['contact']['email'],1);remove_filter('query',$fail);
    cbq_check(is_wp_error($erased)&&TLIT_CB_Store::get($id2)&&TLIT_LP_Leads::get($lead2),'failed canonical erasure rolls back parent and lead together');
    $erased=TLIT_CB_Store::privacy_erase($event2['contact']['email'],1);
    cbq_check(!is_wp_error($erased)&&!TLIT_CB_Store::get($id2)&&!TLIT_LP_Leads::get($lead2),'successful privacy erasure removes conversation and canonical lead atomically');
    cbq_check(!isset(TLIT_CB_Integrations::connections()['telecrm']),'manual TeleCRM connector cannot bypass classification');
    echo "ALL $checks actual WordPress/MariaDB qualification checks passed; all provider HTTP mocked.\n";
} finally {
    remove_filter('pre_http_request',$http,PHP_INT_MAX);
    foreach($made as $cid){$linked=TLIT_CB_Store::get($cid);if(!empty($linked['lead_id']))$lead_ids[]=(int)$linked['lead_id'];foreach(array('tlit_cb_messages','tlit_cb_events','tlit_cb_deliveries') as $suffix)$wpdb->delete($wpdb->prefix.$suffix,array('conversation_id'=>$cid));$wpdb->delete($parents,array('id'=>$cid));$wpdb->delete($table,array('request_id'=>'cb-conversation-'.$cid));}
    foreach(array_unique($lead_ids) as $lid)$wpdb->delete($table,array('id'=>$lid));
}
