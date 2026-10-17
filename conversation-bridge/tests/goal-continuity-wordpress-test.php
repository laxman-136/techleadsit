<?php
/** Regression for lost intent after neutral answers/contact capture; mock every provider. */
if(PHP_SAPI!=='cli'){exit;}
require $argv[1];
if(home_url()!=='http://127.0.0.1:4187'||!WP_HTTP_BLOCK_EXTERNAL){throw new RuntimeException('Use isolated localhost WordPress.');}
set_exception_handler(function($e){fwrite(STDERR,$e->getMessage()."\n");exit(1);});
require_once dirname(__DIR__,2).'/digital-marketing-integration/chat-context.php';
TLIT_CB_Store::install();TLIT_Teams_Notifications::install();
$checks=0;$ids=array();$run=bin2hex(random_bytes(5));$calls=0;$sent=array();$before=get_option(TLIT_Teams_Notifications::OPTION,array());
add_filter('pre_http_request',function($pre,$args,$url)use(&$calls,&$sent){++$calls;$sent=json_decode($args['body']??'{}',true);return array('headers'=>array(),'response'=>array('code'=>202),'body'=>'');},PHP_INT_MAX,3);
function gc_check($ok,$label){if(!$ok){throw new RuntimeException('FAIL '.$label);}++$GLOBALS['checks'];echo 'PASS '.$label."\n";}
function gc_capture($session,$event,$text='',$goal='',$authoritative=false,$contact=array(),$type='question'){
    $id=TLIT_CB_Store::capture(array('session'=>$session,'event_id'=>$GLOBALS['run'].'-'.$event,'course'=>'digital_marketing','source_url'=>home_url('/ai-powered-digital-marketing-course/'),'language'=>'English','goal'=>$goal,'goal_authoritative'=>$authoritative,'contact'=>$contact,'messages'=>$text!==''?array(array('role'=>$type==='answer'?'assistant':'visitor','type'=>$type,'content'=>$text)):array()));
    if(is_wp_error($id)){throw new RuntimeException('Capture failed');}$GLOBALS['ids'][$id]=$id;return TLIT_CB_Store::get($id);
}
try{
 TLIT_Teams_Notifications::save_settings(array('enabled'=>1,'chatbot'=>1,'forms'=>1,'webhook_url'=>'https://prod-01.westus.logic.azure.com/workflows/qaGoalContinuity/triggers/manual/paths/invoke?sig=local-synthetic-only'));
 $token=bin2hex(random_bytes(24));$q='I am a fresher looking for my first digital marketing job. What practical projects will I build?';
 $row=gc_capture($token,'question-1',$q);$id=$row['id'];gc_check(stripos($row['goal'],'job')!==false,'first personal job question supplies a goal without asking the visitor');
 $row=gc_capture($token,'answer-1','Three practical projects.','Find my first digital marketing job.',true,array(),'answer');
 $row=gc_capture($token,'fee','What is the course fee and can I pay in instalments?','Find my first digital marketing job.',true);
 $row=gc_capture($token,'null-answer','The fee is 44999.','',true,array(),'answer');
 gc_check($row['goal']==='Find my first digital marketing job.','an authoritative empty model update on a fee answer cannot erase job intent');
 $row=gc_capture($token,'phone','+447700900123','',false,array('name'=>'Goal QA '.$run,'phone'=>'+447700900123'),'contact_phone');
 gc_check($row['goal']==='Find my first digital marketing job.','phone capture preserves the durable goal');
 $memory=tlit_dm_ai_reconcile_memory(array('goal'=>'Find my first digital marketing job.'),array('goal'=>null),array(array('role'=>'user','content'=>$q)),'What is the course fee?');
 gc_check($memory['memory']['goal']===$row['goal']&&!$memory['cleared'],'AI conversation memory also ignores unrelated null retractions');
 $change=tlit_dm_ai_reconcile_memory($memory['memory'],array('goal'=>null),array(array('role'=>'user','content'=>$q)),'Actually I do not want a job. I want to grow my business.');
 gc_check(stripos($change['memory']['goal'],'business')!==false,'a real change of direction replaces the prior goal');
 $clear=tlit_dm_ai_reconcile_memory($memory['memory'],array('goal'=>null),array(array('role'=>'user','content'=>$q)),'I do not want a job anymore.');
 gc_check($clear['memory']['goal']===''&&$clear['cleared'],'an actual visitor retraction clears model memory');
 $foreign=TLIT_CB_Goals::reconcile(array(array('role'=>'visitor','type'=>'question','content'=>'My brother wants a job. What are your fees?')));
 gc_check($foreign['status']==='interest','third-party goals are not assigned to this visitor');
 foreach(array('నాకు ఉద్యోగం కావాలి','Mujhe job chahiye') as $question){$r=TLIT_CB_Goals::reconcile(array(array('role'=>'visitor','type'=>'question','content'=>$question)));gc_check(stripos($r['summary'],'job')!==false,'multilingual personal goal evidence is recognised');}
 $queued=TLIT_Teams_Notifications::queue('chatbot',$id);global $wpdb;$job=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.TLIT_Teams_Notifications::table().' WHERE identity_key=%s','CB-'.$id),ARRAY_A);
 gc_check(is_array($job),'contact-ready conversation can queue its Teams alert');TLIT_Teams_Notifications::deliver($job['id']);
 $facts=array_column($sent['attachments'][0]['content']['body'][2]['facts'],'value','title');
 gc_check($facts['Learning goal']===$row['goal'],'actual outgoing Teams payload uses exactly the saved Bridge goal');
 $meta=json_decode($row['goal_analysis_json'],true);$meta['version']=1;$meta['source']='fallback';$meta['status']='insufficient';$meta['summary']=TLIT_CB_Goals::EMPTY_SUMMARY;$meta['cleared_through_message_id']=(int)$wpdb->get_var($wpdb->prepare("SELECT MAX(id) FROM {$wpdb->prefix}tlit_cb_messages WHERE conversation_id=%d AND type='question'",$id));
 $wpdb->update($wpdb->prefix.'tlit_cb_conversations',array('goal'=>TLIT_CB_Goals::EMPTY_SUMMARY,'goal_analysis_json'=>wp_json_encode($meta),'updated_at'=>'2026-10-01 01:02:03'),array('id'=>$id));$before_calls=$calls;
 $batch=TLIT_CB_Store::backfill_goals($id-1,1);$repaired=TLIT_CB_Store::get($id);
 gc_check(!is_wp_error($batch)&&stripos($repaired['goal'],'job')!==false,'older erroneous model cutoff is repaired from the saved transcript');
 gc_check($repaired['updated_at']==='2026-10-01 01:02:03'&&$calls===$before_calls,'repair preserves activity times and makes no provider requests');
 TLIT_Teams_Notifications::queue('chatbot',$id);TLIT_Teams_Notifications::deliver($job['id']);gc_check($calls===$before_calls,'historical repair does not resend accepted alerts');
 $t2=bin2hex(random_bytes(24));gc_capture($t2,'job','I want a job.');$r=gc_capture($t2,'retract','I do not want a job anymore.','',true);$r=gc_capture($t2,'after-retract','What is the course fee?');
 gc_check(stripos($r['goal'],'job')===false&&stripos($r['goal'],'fee')!==false,'a real retraction is respected across neutral follow-ups');
 $old=json_decode($r['goal_analysis_json'],true);$old['version']=1;$wpdb->update($wpdb->prefix.'tlit_cb_conversations',array('goal_analysis_json'=>wp_json_encode($old)),array('id'=>$r['id']));TLIT_CB_Store::backfill_goals($r['id']-1,1);$r=TLIT_CB_Store::get($r['id']);
 gc_check(stripos($r['goal'],'job')===false,'historical repair does not resurrect a genuinely retracted objective');
 $r=gc_capture($t2,'stale-model','The fee is 44999.','Find a digital marketing job.',true,array(),'answer');
 gc_check(stripos($r['goal'],'job')===false,'a stale model goal cannot restore an explicitly rejected goal after a neutral follow-up');
 echo 'PASS '.$checks." goal continuity checks.\n";
}finally{
 global $wpdb;foreach($ids as $id){foreach(array('messages','events','conversations') as $table){$wpdb->delete($wpdb->prefix.'tlit_cb_'.$table,array($table==='conversations'?'id':'conversation_id'=>$id));}$wpdb->delete(TLIT_Teams_Notifications::table(),array('identity_key'=>'CB-'.$id));if(class_exists('TLIT_CB_Team_Access')){$wpdb->delete(TLIT_CB_Team_Access::table(),array('identity_key'=>'CB-'.$id));}if(class_exists('TLIT_CB_Lead_Ops')){TLIT_CB_Lead_Ops::erase(true,$id);}}
 update_option(TLIT_Teams_Notifications::OPTION,$before,false);
}
