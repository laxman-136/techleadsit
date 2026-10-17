<?php
/** Actual endpoint checks; synthetic local key, no external provider requests. */
if(PHP_SAPI!=='cli'){exit;}
define('TLIT_ROUTING_TEST',true);
define('TLIT_DM_OPENROUTER_KEY','synthetic-local-provider-key');
require $argv[1];
if(home_url()!=='http://127.0.0.1:4187'||!WP_HTTP_BLOCK_EXTERNAL){throw new RuntimeException('Isolated localhost required.');}
set_exception_handler(function($error){fwrite(STDERR,$error->getMessage()."\n");exit(1);});
add_filter('pre_option_tlit_dm_ai_settings',function(){return array('enabled'=>1);});
add_filter('pre_option_'.TLIT_Teams_Notifications::OPTION,function(){return array('enabled'=>0);});
$calls=0;add_filter('pre_http_request',function()use(&$calls){++$calls;return new WP_Error('local_provider','Synthetic provider failure.');},PHP_INT_MAX,3);
$checks=0;$token=bin2hex(random_bytes(24));$key='tlit_dm_ai_conversation_'.tlit_dm_hash($token);$id=0;
$_SERVER['REMOTE_ADDR']='deferred-goal-local-'.bin2hex(random_bytes(5));
function dg_check($ok,$label){if(!$ok){throw new RuntimeException('FAIL '.$label);}++$GLOBALS['checks'];echo 'PASS '.$label."\n";}
function dg_request($p){$request=new WP_REST_Request('POST');$request->set_header('Content-Type','application/json');$request->set_body(wp_json_encode($p));return tlit_dm_ai_answer($request);}
try{
 $state=array('answers'=>3,'history'=>array(),'goal'=>'','contact_sealed'=>tlit_dm_chat_seal(array('name'=>'Deferred goal QA')));
 tlit_dm_archive_intro($token,$state,'Deferred goal QA','English');set_transient($key,$state,86400);
 // The hash is private to Store; resolve this isolated fixture through its captured name.
 global $wpdb;$id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}tlit_cb_conversations WHERE name=%s ORDER BY id DESC LIMIT 1",'Deferred goal QA'));
 $question='How can I get my first digital marketing job?';$base=array('conversation'=>$token,'language'=>'English');
 $response=dg_request($base+array('question'=>$question));$data=$response->get_data();
 dg_check($response->get_status()===200&&!empty($data['questionDeferred'])&&$data['stage']==='phone','question waits for mobile capture');
 $row=TLIT_CB_Store::get($id);dg_check(stripos($row['goal'],'job')!==false,'deferred question supplies goal evidence before the phone is saved');
 dg_check(get_transient($key)['answers']===3&&$calls===0,'deferring records no answer and makes no provider request');
 dg_request($base+array('question'=>$question));
 $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}tlit_cb_messages WHERE conversation_id=%d AND type='question'",$id));
 dg_check($count===1,'delivery retries do not duplicate the deferred transcript');
 $response=dg_request($base+array('question'=>'7700900123','action'=>'contact','contactStage'=>'phone','country'=>'GB'));$data=$response->get_data();
 dg_check($response->get_status()===200&&!empty($data['contactAccepted'])&&$data['stage']==='question','valid international phone resumes the existing question flow');
 $row=TLIT_CB_Store::get($id);$lead=TLIT_Teams_Notifications::lead('chatbot',$id);
 dg_check($lead&&$lead['goal']===$row['goal']&&stripos($lead['goal'],'job')!==false,'first contact-ready Teams data includes the pending question goal');
 $response=dg_request($base+array('question'=>$question));
 $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}tlit_cb_messages WHERE conversation_id=%d AND type='question'",$id));
 dg_check($response->get_status()===503&&$calls===1&&$count===1,'answer engine reuses the archived question even on provider failure');
 dg_check(TLIT_CB_Store::get($id)['lead_id']==0,'goal inference and contact capture do not qualify or sync TeleCRM');
 echo 'PASS '.$checks." deferred-goal endpoint checks.\n";
}finally{
 delete_transient($key);if($id){foreach(array('messages','events','conversations') as $table){$wpdb->delete($wpdb->prefix.'tlit_cb_'.$table,array($table==='conversations'?'id':'conversation_id'=>$id));}TLIT_CB_Lead_Ops::erase(true,$id);$wpdb->delete(TLIT_Teams_Notifications::table(),array('identity_key'=>'CB-'.$id));}
}
