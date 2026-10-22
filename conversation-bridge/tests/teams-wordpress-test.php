<?php
/** Real local WordPress storage, with every provider request intercepted. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$bootstrap=$argv[1]??'';
if(!is_file($bootstrap)){fwrite(STDERR,"Pass an isolated WordPress wp-load.php path.\n");exit(1);}
define('TLIT_ROUTING_TEST',true); // The local SMS fixture must not redeclare the real main-plugin helper.
$ready=false;register_shutdown_function(function()use(&$ready){if(!$ready){fwrite(STDERR,"WordPress bootstrap failed.\n");exit(1);}});
require $bootstrap;$ready=true;
set_exception_handler(function($error){fwrite(STDERR,$error->getMessage()."\n");exit(1);});
if(!defined('WP_HTTP_BLOCK_EXTERNAL')||!WP_HTTP_BLOCK_EXTERNAL||!in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('localhost','127.0.0.1'),true)){throw new RuntimeException('Use isolated localhost WordPress with external HTTP blocked.');}
require_once dirname(__DIR__,2).'/techleadsit.php';
if(!class_exists('TLIT_Teams_Notifications'))throw new RuntimeException('Update the isolated runtime plugin copies first.');
wp_set_current_user(1);
$checks=0;$leads=array();$drafts=array();$chats=array();$jobs=array();$calls=0;$crm_calls=0;$last=array();$mode='accepted';$run=bin2hex(random_bytes(5));
$old=get_option(TLIT_Teams_Notifications::OPTION,null);$old_error=get_option('tlit_teams_queue_error',null);$table=TLIT_Teams_Notifications::table();
$url='https://prod-01.westus.logic.azure.com/workflows/qaOnlyWorkflow/triggers/manual/paths/invoke?api-version=2016-10-01&sig=synthetic-secret-only';
function tn_check($value,$label){if(!$value)throw new RuntimeException('FAIL: '.$label);++$GLOBALS['checks'];echo "PASS $label\n";}
function tn_settings($changes=array()){return TLIT_Teams_Notifications::save_settings(array_replace(array('enabled'=>1,'forms'=>1,'chatbot'=>1),$changes));}
function tn_form($variant='v1',$source='general'){
    $slugs=array('v1'=>'ai-powered-digital-marketing','v2'=>'ai-powered-digital-marketing-course','v3'=>'ai-powered-digital-marketing-training');
    $p=array('request_id'=>wp_generate_uuid4(),'name'=>'Synthetic Teams QA','phone'=>'+447700900123','email'=>'teams-'.$GLOBALS['run'].'@example.invalid','goal'=>'Build digital marketing skills','page_variant'=>$variant,'submission_url'=>home_url('/'.$slugs[$variant].'/'),'tracking'=>array('utm_source'=>'qa_only','utm_campaign'=>$GLOBALS['run']),'intent'=>$source);
    $result=TLIT_LP_Leads::ingest($p,array('source'=>$source,'otp_verified'=>true));
    tn_check(!is_wp_error($result)&&$result['success'],'form saved before notification');$GLOBALS['leads'][]=$result['lead_id'];return array($result,$p);
}
function tn_job($type,$id){global $wpdb;$prefix=array('form'=>'LP-','chatbot'=>'CB-','dropoff'=>'FD-')[$type];$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.TLIT_Teams_Notifications::table().' WHERE identity_key=%s',$prefix.$id),ARRAY_A);if($row)$GLOBALS['jobs'][]=(int)$row['id'];return $row;}
function tn_draft($variant='v1'){
    $slugs=array('v1'=>'ai-powered-digital-marketing','v2'=>'ai-powered-digital-marketing-course','v3'=>'ai-powered-digital-marketing-training');
    $result=TLIT_LP_Leads::save_draft(array('request_id'=>wp_generate_uuid4(),'intent'=>'general','page_variant'=>$variant,'submission_url'=>home_url('/'.$slugs[$variant].'/'),'current_step'=>2,'total_steps'=>7,'last_field'=>'phone','values'=>array('name'=>'Veera Dropoff QA','phone'=>'+919515793373'),'tracking'=>array('utm_source'=>'qa_only','utm_campaign'=>$GLOBALS['run'])));
    tn_check(!is_wp_error($result)&&!empty($result['draft_id']),'name and mobile progress creates a protected draft');$GLOBALS['drafts'][]=(int)$result['draft_id'];return $result;
}
class TeamsTestDie extends RuntimeException{}
$die_filter=function(){return function($message,$title='',$args=array()){throw new TeamsTestDie(strip_tags((string)$message),(int)($args['response']??500));};};
$http=function($pre,$args,$request_url)use(&$calls,&$crm_calls,&$last,&$mode,$wpdb){
    if(strpos($request_url,'telecrm')!==false){++$crm_calls;return new WP_Error('blocked','No CRM writes in notification tests.');}
    if(strpos($request_url,'.logic.azure.com/')!==false||strpos($request_url,'.environment.api.powerplatform.com/')!==false){
        ++$calls;$last=array('url'=>$request_url,'args'=>$args,'body'=>json_decode($args['body'],true));
        tn_check((int)$wpdb->get_var('SELECT @@in_transaction')===0,'notification HTTP runs after the parent storage transaction');
        if($mode==='timeout')return new WP_Error('http_request_failed','synthetic timeout; never log provider details');
        $code=array('accepted'=>202,'throttle'=>429,'forbidden'=>401,'server'=>503,'redirect'=>302)[$mode];
        return array('response'=>array('code'=>$code),'headers'=>array('retry-after'=>'75'),'body'=>'LEAK_THIS_WEBHOOK_SECRET');
    }
    return new WP_Error('blocked','No external requests in this test.');
};
add_filter('pre_http_request',$http,PHP_INT_MAX,3);
try{
    tn_check(current_user_can('manage_options'),'isolated administrator available');
    tn_check(get_option('tlit_teams_schema')===TLIT_Teams_Notifications::SCHEMA,'notification outbox installed');
    foreach(array('http://localhost/workflows/a/triggers/b/paths/invoke','https://evil.test/workflows/a/triggers/b/paths/invoke','https://prod-01.westus.logic.azure.com.evil.test/workflows/a/triggers/b/paths/invoke','https://user:secret@prod-01.westus.logic.azure.com/workflows/a/triggers/b/paths/invoke') as $bad){tn_check(!TLIT_Teams_Notifications::valid_url($bad),'unsafe or unrelated webhook endpoint rejected');}
    tn_check(TLIT_Teams_Notifications::valid_url('https://default-qa.environment.api.powerplatform.com/powerautomate/automations/direct/workflows/qa/triggers/manual/paths/invoke?sig=qa'),'current Power Platform webhook format supported');
    tn_check(tn_settings(array('enabled'=>0,'webhook_url'=>$url))===true,'URL can be saved while alerts are off');
    $o=TLIT_Teams_Notifications::options();tn_check(strpos(wp_json_encode($o),'synthetic-secret-only')===false,'signed webhook URL encrypted at rest');
    list($off)=tn_form();tn_check(tn_job('form',$off['lead_id'])===null&&$calls===0,'disabled notifications never send or queue');
    tn_check(tn_settings()===true,'notification sources enabled without reentering the URL');
    TLIT_Teams_Notifications::repair_queue();tn_check(tn_job('form',$off['lead_id'])===null,'enabling does not forward historical form leads');
    $enabled_options=TLIT_Teams_Notifications::options();$enabled_options['dropoffs_started_at']=gmdate('Y-m-d H:i:s',time()-3600);update_option(TLIT_Teams_Notifications::OPTION,$enabled_options,false);
    $first=null;
    foreach(array('v1','v2','v3') as $variant)foreach(array('general','popup','brochure') as $source){
        list($r,$p)=tn_form($variant,$source);$job=tn_job('form',$r['lead_id']);
        tn_check($job&&$job['state']==='pending'&&$calls===0,'durable '.$variant.' '.$source.' alert queued without blocking form on HTTP');
        tn_check($r['receipt']==='LP-'.$r['lead_id']&&TLIT_LP_Leads::get($r['lead_id'])->name==='Synthetic Teams QA','notification insertion preserves original lead receipt');
        TLIT_LP_Leads::ingest($p,array('source'=>$source,'otp_verified'=>true));
        tn_check((int)tn_job('form',$r['lead_id'])['id']===(int)$job['id'],'submission retry reuses its notification');
        if(!$first)$first=$job;
    }
    $drop=tn_draft('v1');$drop_id=(int)$drop['draft_id'];
    tn_check(tn_job('dropoff',$drop_id)===null,'active form progress does not notify Teams immediately');
    $wpdb->update(TLIT_LP_Leads::draft_table(),array('updated_at'=>gmdate('Y-m-d H:i:s',time()-TLIT_Teams_Notifications::DROPOFF_SECONDS-5)),array('id'=>$drop_id));
    TLIT_Teams_Notifications::dropoff_check($drop_id);$drop_job=tn_job('dropoff',$drop_id);
    tn_check($drop_job&&$drop_job['state']==='pending','stale form with name and valid mobile queues one drop-off alert');
    $drop_lead=TLIT_Teams_Notifications::lead('dropoff',$drop_id);$drop_card=wp_json_encode(TLIT_Teams_Notifications::card($drop_lead,$drop_job['notification_id']));
    tn_check(strpos($drop_card,'Form started but not completed')!==false&&strpos($drop_card,'Veera Dropoff QA started filling the form on V1')!==false&&strpos($drop_card,'Step 2 of 7')!==false,'drop-off message includes name, page, mobile context and progress');
    TLIT_Teams_Notifications::dropoff_check($drop_id);tn_check((int)tn_job('dropoff',$drop_id)['id']===(int)$drop_job['id'],'drop-off checks remain idempotent');
    $before=$calls;TLIT_Teams_Notifications::deliver($drop_job['id']);
    tn_check($calls===$before+1&&tn_job('dropoff',$drop_id)['state']==='accepted'&&strpos(wp_json_encode($last['body']),'Form started but not completed')!==false,'qualified drop-off reaches Power Automate as its own notification type');
    $completed=tn_draft('v2');$completed_id=(int)$completed['draft_id'];$completed_row=TLIT_LP_Leads::get_draft($completed_id);TLIT_LP_Leads::complete_draft($completed_row->request_id,999999);
    $wpdb->update(TLIT_LP_Leads::draft_table(),array('updated_at'=>gmdate('Y-m-d H:i:s',time()-TLIT_Teams_Notifications::DROPOFF_SECONDS-5)),array('id'=>$completed_id));
    TLIT_Teams_Notifications::dropoff_check($completed_id);tn_check(tn_job('dropoff',$completed_id)===null,'completed forms cancel their pending drop-off alert');
    $missing=TLIT_LP_Leads::save_draft(array('request_id'=>wp_generate_uuid4(),'intent'=>'general','page_variant'=>'v3','submission_url'=>home_url('/ai-powered-digital-marketing-training/'),'current_step'=>1,'total_steps'=>7,'last_field'=>'name','values'=>array('name'=>'Name Only QA')));$missing_id=(int)$missing['draft_id'];$drafts[]=$missing_id;
    $wpdb->update(TLIT_LP_Leads::draft_table(),array('updated_at'=>gmdate('Y-m-d H:i:s',time()-TLIT_Teams_Notifications::DROPOFF_SECONDS-5)),array('id'=>$missing_id));
    TLIT_Teams_Notifications::dropoff_check($missing_id);tn_check(tn_job('dropoff',$missing_id)===null,'drop-off without both name and valid mobile never notifies Teams');
    TLIT_Teams_Notifications::deliver($first['id']);$before=$calls;TLIT_Teams_Notifications::deliver($first['id']);
    tn_check($calls===$before&&tn_job('form',$first['entity_id'])['state']==='accepted','accepted notification is never automatically resent');
    tn_check(strpos(wp_json_encode($last['body']),'Successful landing-page submission')!==false,'completed form uses the successful-submission notification template');
    tn_check($last['body']['type']==='message'&&$last['body']['attachments'][0]['contentType']==='application/vnd.microsoft.card.adaptive','Teams webhook receives the official message and adaptive-card envelope');
    tn_check(($last['args']['redirection']??null)===0&&!isset($last['args']['headers']['Authorization']),'Anyone webhook uses no bearer token and never follows redirects');
    $card=wp_json_encode($last['body']);tn_check(strpos($card,'Submitted (IST)')!==false&&strpos($card,'Campaign source')!==false&&strpos($card,'Open in WordPress Bridge')!==false,'alert includes IST, source and a private Bridge link');
    tn_check(strpos($card,'Synthetic Teams QA')!==false&&strpos($card,'verification_code')===false&&strpos($card,'session_hash')===false,'only approved lead fields are included');
    $token=substr(hash('sha256','teams-'.$run),0,48);$event=array('session'=>$token,'event_id'=>'intro','course'=>'digital_marketing','source_url'=>home_url('/ai-powered-digital-marketing-training/'),'contact'=>array('name'=>'Synthetic Chat QA'),'language'=>'English','stage'=>'question','messages'=>array());
    do_action('tlit_cb_capture',$event);$chat=TLIT_CB_Store::for_session($token);$chats[]=$cid=(int)$chat['id'];
    tn_check(tn_job('chatbot',$cid)===null,'name-only chatbot does not notify the team');
    $event['event_id']='phone';$event['contact']['phone']='+447700900456';do_action('tlit_cb_capture',$event);$chat_job=tn_job('chatbot',$cid);
    tn_check($chat_job&&$chat_job['state']==='pending'&&empty(TLIT_CB_Store::get($cid)['lead_id']),'chat phone capture queues without qualification or an LP duplicate');
    do_action('tlit_cb_capture',$event);$event['event_id']='next-question';do_action('tlit_cb_capture',$event);
    tn_check(tn_job('chatbot',$cid)['id']===$chat_job['id'],'chat retries and later conversation turns keep one alert');
    TLIT_Teams_Notifications::deliver($chat_job['id']);$facts=array_column($last['body']['attachments'][0]['content']['body'][2]['facts'],'value','title');
    tn_check(strpos(wp_json_encode($last['body']),'New chatbot lead')!==false,'chatbot contact uses its own notification template');
    tn_check(($facts['Page version']??'')==='V3'&&!isset($facts['Email']),'chat alert keeps page attribution and permits missing email');
    $brochure=TLIT_CB_Qualification::brochure($token,array('name'=>'Synthetic Chat QA','phone'=>'+447700900456','email'=>'brochure-'.$run.'@example.invalid','page_variant'=>'v3'));
    tn_check(!is_wp_error($brochure),'chat brochure saved through canonical lead adapter');$leads[]=$brochure['lead_id'];
    tn_check(tn_job('form',$brochure['lead_id'])===null&&tn_job('chatbot',$cid)['id']===$chat_job['id'],'brochure and chatbot contact share one alert identity');
    tn_check(TLIT_LP_Leads::get($brochure['lead_id'])->lead_status==='new'&&$crm_calls===0,'notification and brochure capture do not qualify or send TeleCRM leads');
    list($throttled)=tn_form();$job=tn_job('form',$throttled['lead_id']);$mode='throttle';TLIT_Teams_Notifications::deliver($job['id']);$retry=tn_job('form',$throttled['lead_id']);
    tn_check($retry['state']==='retry'&&(int)$retry['retry_count']===1&&strtotime($retry['next_attempt'].' UTC')>=time()+70,'429 schedules bounded automatic retry and honors Retry-After');
    $wpdb->update($table,array('next_attempt'=>gmdate('Y-m-d H:i:s',time()-1)),array('id'=>$job['id']));$mode='accepted';TLIT_Teams_Notifications::deliver($job['id']);
    tn_check(tn_job('form',$throttled['lead_id'])['state']==='accepted'&&$last['args']['headers']['Idempotency-Key']===$job['notification_id'],'automatic retry retains the same notification reference');
    list($uncertain)=tn_form();$job=tn_job('form',$uncertain['lead_id']);$mode='timeout';TLIT_Teams_Notifications::deliver($job['id']);$before=$calls;TLIT_Teams_Notifications::deliver($job['id']);
    tn_check(tn_job('form',$uncertain['lead_id'])['state']==='uncertain'&&$calls===$before,'timeout stays uncertain without blind duplicate sending');
    tn_check(is_wp_error(TLIT_Teams_Notifications::retry($job['id'])),'uncertain retry requires an explicit flow-history check');
    tn_check(TLIT_Teams_Notifications::retry($job['id'],true)===true,'confirmed missing notification can be queued for retry');$mode='accepted';TLIT_Teams_Notifications::deliver($job['id']);
    tn_check(is_wp_error(TLIT_Teams_Notifications::retry($job['id'],true)),'accepted alerts cannot be manually resent');
    list($held)=tn_form();$job=tn_job('form',$held['lead_id']);tn_settings(array('enabled'=>0));TLIT_Teams_Notifications::deliver($job['id']);tn_check(tn_job('form',$held['lead_id'])['state']==='held','switching off holds a queued alert');tn_settings();
    list($rotated)=tn_form();$job=tn_job('form',$rotated['lead_id']);tn_settings(array('webhook_url'=>$url.'&rotated=1'));TLIT_Teams_Notifications::deliver($job['id']);
    tn_check(tn_job('form',$rotated['lead_id'])['state']==='held','URL rotation cannot silently send an old queued alert elsewhere');
    tn_check(TLIT_Teams_Notifications::retry($job['id'])===true,'explicit retry authorizes the current URL');TLIT_Teams_Notifications::deliver($job['id']);
    tn_check(strpos($last['url'],'rotated=1')!==false,'authorized retry uses the new endpoint');
    foreach(array('forbidden'=>'failed','server'=>'uncertain','redirect'=>'failed') as $failure=>$state){list($failed)=tn_form();$job=tn_job('form',$failed['lead_id']);$mode=$failure;TLIT_Teams_Notifications::deliver($job['id']);tn_check(tn_job('form',$failed['lead_id'])['state']===$state,'provider '.$failure.' has a safe visible outcome');}
    $mode='accepted';$before=$calls;tn_check(TLIT_Teams_Notifications::test()===true&&$calls===$before+1,'synthetic connection test reaches the mocked flow');
    tn_check(strpos(wp_json_encode($last['body']),'TEST ONLY')!==false&&!isset($last['body']['attachments'][0]['content']['actions']),'test never uses a real visitor or Bridge link');
    $_GET=array();ob_start();TLIT_Teams_Notifications::render();$html=ob_get_clean();
    tn_check(strpos($html,'synthetic-secret-only')===false&&strpos($html,'LEAK_THIS_WEBHOOK_SECRET')===false,'admin UI never exposes signed URL or provider response bodies');
    tn_check(strpos($html,'Accepted by Power Automate')!==false&&strpos($html,'Save notification settings')!==false,'settings and honest delivery status render');
    $inject=function($sql){return strpos($sql,'INSERT INTO '.TLIT_Teams_Notifications::table())===0?'INSERT INTO missing_teams_test_table (id) VALUES (1)':$sql;};add_filter('query',$inject);
    list($missed)=tn_form();remove_filter('query',$inject);tn_check(TLIT_LP_Leads::get($missed['lead_id'])!==null&&tn_job('form',$missed['lead_id'])===null,'outbox failure does not lose a saved lead');
    TLIT_Teams_Notifications::repair_queue();tn_check(tn_job('form',$missed['lead_id'])!==null,'queue repair recovers a missed new-form notification');
    $missed_event=$event;$missed_event['session']=substr(hash('sha256','missed-chat-'.$run),0,48);$missed_event['event_id']='captured-phone';
    add_filter('query',$inject);do_action('tlit_cb_capture',$missed_event);remove_filter('query',$inject);
    $missed_chat=TLIT_CB_Store::for_session($missed_event['session']);$chats[]=$missed_cid=(int)$missed_chat['id'];
    tn_check($missed_cid>0&&tn_job('chatbot',$missed_cid)===null,'outbox failure does not lose a chatbot contact');
    TLIT_Teams_Notifications::repair_queue();tn_check(tn_job('chatbot',$missed_cid)!==null,'queue repair recovers a missed chatbot-phone alert');
    add_filter('wp_die_handler',$die_filter);$_SERVER['REQUEST_METHOD']='POST';wp_set_current_user(0);
    try{TLIT_Teams_Notifications::admin_action();throw new RuntimeException('Nonadmin request accepted');}catch(TeamsTestDie $e){tn_check($e->getCode()===403,'nonadmins cannot configure or test notifications');}
    wp_set_current_user(1);$_SERVER['REQUEST_METHOD']='GET';try{TLIT_Teams_Notifications::admin_action();throw new RuntimeException('GET accepted');}catch(TeamsTestDie $e){tn_check($e->getCode()===405,'configuration requires POST');}
    $_SERVER['REQUEST_METHOD']='POST';$_REQUEST['_wpnonce']='invalid';try{TLIT_Teams_Notifications::admin_action();throw new RuntimeException('Invalid nonce accepted');}catch(TeamsTestDie $e){tn_check($e->getCode()===403,'configuration and test actions require a valid nonce');}remove_filter('wp_die_handler',$die_filter);
    $before=$calls;tn_check(TLIT_CB_Store::privacy_erase('brochure-'.$run.'@example.invalid')['items_removed']===false,'privacy eraser does not invent email linkage');
    $event['event_id']='email';$event['contact']['email']='chat-erase-'.$run.'@example.invalid';do_action('tlit_cb_capture',$event);
    tn_check(TLIT_CB_Store::privacy_erase('chat-erase-'.$run.'@example.invalid')['items_removed']===true&&tn_job('chatbot',$cid)===null,'chat privacy erasure also removes notification references');
    TLIT_Teams_Notifications::deliver($chat_job['id']);tn_check($calls===$before&&$crm_calls===0,'erased notifications never send and the entire feature never calls TeleCRM');
    echo "ALL $checks Teams notification WordPress checks passed; every HTTP request mocked.\n";
} finally {
    remove_filter('pre_http_request',$http,PHP_INT_MAX);remove_filter('wp_die_handler',$die_filter);
    foreach(array_unique($jobs) as $id){wp_clear_scheduled_hook('tlit_teams_deliver',array($id));$wpdb->delete($table,array('id'=>$id));}
    foreach($leads as $id)$wpdb->delete(TLIT_LP_Leads::table(),array('id'=>$id));
    foreach($drafts as $id){wp_clear_scheduled_hook('tlit_teams_dropoff_check',array($id));$wpdb->delete(TLIT_LP_Leads::draft_table(),array('id'=>$id));}
    foreach($chats as $id){foreach(array('messages','events') as $name)$wpdb->delete($wpdb->prefix.'tlit_cb_'.$name,array('conversation_id'=>$id));$wpdb->delete($wpdb->prefix.'tlit_cb_conversations',array('id'=>$id));}
    if($old===null)delete_option(TLIT_Teams_Notifications::OPTION);else update_option(TLIT_Teams_Notifications::OPTION,$old,false);
    if($old_error===null)delete_option('tlit_teams_queue_error');else update_option('tlit_teams_queue_error',$old_error,false);
    if(empty($old['enabled']))wp_clear_scheduled_hook('tlit_teams_sweep');
}
