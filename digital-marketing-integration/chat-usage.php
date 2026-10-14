<?php
if(!defined('ABSPATH'))exit;

// Private, bounded accounting only: never store request text, contacts or keys.
function tlit_dm_ai_usage_number($value,$max,$integer=true){
 if(is_bool($value)||(!is_int($value)&&!is_float($value)&&!is_string($value)))return null;
 if(is_string($value)&&!preg_match($integer?'/^[0-9]{1,10}$/D':'/^[0-9]{1,8}(?:\.[0-9]{1,12})?$/D',$value))return null;
 if(!is_numeric($value))return null;$n=(float)$value;
 if(!is_finite($n)||$n<0||$n>$max||($integer&&floor($n)!==$n))return null;
 return $integer?(int)$n:$n;
}
function tlit_dm_ai_usage_model($value){
 return is_string($value)&&strlen($value)<=128&&preg_match('~^[a-zA-Z0-9][a-zA-Z0-9._:-]*/[a-zA-Z0-9][a-zA-Z0-9._:-]*$~D',$value)?$value:null;
}
function tlit_dm_ai_usage_metrics(){return array('prompt_tokens'=>'Input tokens','completion_tokens'=>'Output tokens','reasoning_tokens'=>'Reasoning tokens','cached_tokens'=>'Cached input tokens','cost'=>'Reported cost (USD)');}
function tlit_dm_ai_usage_state(){
 $state=get_option('tlit_dm_ai_usage_v1',array());if(!is_array($state))$state=array();
 if(!is_array($state['days']??null))$state['days']=array();
 $cutoff=gmdate('Y-m-d',time()-29*86400);$today=gmdate('Y-m-d');
 $state['days']=array_filter($state['days'],function($day,$date)use($cutoff,$today){return is_string($date)&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)&&$date>=$cutoff&&$date<=$today&&is_array($day);},ARRAY_FILTER_USE_BOTH);
 krsort($state['days']);$state['days']=array_slice($state['days'],0,30,true);
 foreach(array('last','last_error') as $key)if(isset($state[$key])&&(!is_array($state[$key])||(int)($state[$key]['time']??0)<time()-30*86400))unset($state[$key]);
 return $state;
}
function tlit_dm_ai_usage_lock(){
 // A usage update is tiny; bounded retries avoid losing concurrent response totals.
 for($i=0;$i<5;$i++){$lock=tlit_dm_lock('ai-usage');if($lock)return $lock;if($i<4)usleep(20000);}
 return false;
}
function tlit_dm_ai_record_usage($body,$requestedModel){
 if(!is_array($body))return false;
 $usage=is_array($body['usage']??null)?$body['usage']:array();
 $last=array('time'=>time(),'requested_model'=>tlit_dm_ai_usage_model($requestedModel),'model'=>tlit_dm_ai_usage_model($body['model']??null));
 foreach(array('prompt_tokens','completion_tokens') as $key)$last[$key]=tlit_dm_ai_usage_number($usage[$key]??null,10000000);
 $completion=is_array($usage['completion_tokens_details']??null)?$usage['completion_tokens_details']:array();
 $prompt=is_array($usage['prompt_tokens_details']??null)?$usage['prompt_tokens_details']:array();
 $last['reasoning_tokens']=tlit_dm_ai_usage_number($completion['reasoning_tokens']??null,10000000);
 $last['cached_tokens']=tlit_dm_ai_usage_number($prompt['cached_tokens']??null,10000000);
 $last['cost']=tlit_dm_ai_usage_number($usage['cost']??null,10000,false);
 $lock=tlit_dm_ai_usage_lock();if(!$lock)return false;
 try{
  $state=tlit_dm_ai_usage_state();$date=gmdate('Y-m-d',$last['time']);$day=$state['days'][$date]??array();
  $day['calls']=min(100000000,(int)($day['calls']??0)+1);
  foreach(tlit_dm_ai_usage_metrics() as $key=>$label){
   $previous=tlit_dm_ai_usage_number($day[$key]??null,100000000000000,$key!=='cost');
   $known=max(0,min($day['calls']-1,(int)($day['known_'.$key]??0)));
   if($last[$key]!==null){$day[$key]=($previous??0)+$last[$key];$known++;}elseif($previous===null)$day[$key]=null;
   $day['known_'.$key]=$known;
  }
  $state['last']=$last;$state['days'][$date]=$day;krsort($state['days']);$state['days']=array_slice($state['days'],0,30,true);
  update_option('tlit_dm_ai_usage_v1',$state,false);return true;
 }finally{delete_option($lock);}
}
function tlit_dm_ai_record_error($status,$providerCode=''){
 $status=tlit_dm_ai_usage_number($status,599);if($status===null||$status<100)$status=null;
 // Error messages and bodies can contain request data; accept only short identifiers.
 $safeCodes=array('invalid_api_key','insufficient_credits','rate_limit_exceeded','model_not_found','invalid_request_error','authentication_error','permission_error','insufficient_quota','timeout','request_timeout','provider_error','service_unavailable','unsupported_parameters','answer_validation','network_limit','daily_budget','budget_lock','accounting_error');
 $code=is_string($providerCode)&&(in_array($providerCode,$safeCodes,true)||preg_match('/^[0-9]{3,6}$/D',$providerCode))?$providerCode:null;
 if(is_int($providerCode)&&$providerCode>=0&&$providerCode<=999999)$code=(string)$providerCode;
 $lock=tlit_dm_ai_usage_lock();if(!$lock)return false;
 try{$state=tlit_dm_ai_usage_state();$state['last_error']=array('time'=>time(),'status'=>$status,'code'=>$code);update_option('tlit_dm_ai_usage_v1',$state,false);return true;}finally{delete_option($lock);}
}
function tlit_dm_ai_usage_value($value,$cost=false){
 if($value===null)return 'Unknown';return $cost?'$'.number_format((float)$value,6):number_format((float)$value,0);
}
function tlit_dm_ai_usage_admin(){
 if(!current_user_can('manage_options'))return;
 $state=tlit_dm_ai_usage_state();$last=is_array($state['last']??null)?$state['last']:array();
 echo '<section style="margin:24px 0;padding:20px;background:#fff;border:1px solid #ccd9ce;border-radius:10px;max-width:1200px"><h2 style="margin-top:0">Private AI usage</h2><p>Provider-reported usage for the most recent request and the last 30 UTC days. Includes paid responses rejected by answer validation. Cached chatbot retries do not make a new provider request. Missing provider fields are shown as Unknown.</p>';
 if(!$last)echo '<p>No provider response has been recorded yet.</p>';
 else{
  echo '<p><strong>Last response:</strong> '.esc_html(gmdate('Y-m-d H:i:s',(int)$last['time'])).' UTC<br><strong>Returned model:</strong> '.esc_html($last['model']??'Unknown').'<br><strong>Requested model:</strong> '.esc_html($last['requested_model']??'Unknown').'</p><table class="widefat striped" style="max-width:700px"><tbody>';
  foreach(tlit_dm_ai_usage_metrics() as $key=>$label)echo '<tr><th scope="row">'.esc_html($label).'</th><td>'.esc_html(tlit_dm_ai_usage_value($last[$key]??null,$key==='cost')).'</td></tr>';
  echo '</tbody></table><p class="description">Reasoning and cached tokens are breakdowns of the reported output/input counts; do not add them again. Cost is OpenRouter’s reported amount, not a local estimate.</p>';
 }
 if($state['days']){
  echo '<h3>Daily totals</h3><div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>UTC date</th><th>Responses</th>';
  foreach(tlit_dm_ai_usage_metrics() as $label)echo '<th>'.esc_html($label).'</th>';echo '</tr></thead><tbody>';
  foreach($state['days'] as $date=>$day){$calls=(int)($day['calls']??0);echo '<tr><td>'.esc_html($date).'</td><td>'.(int)$calls.'</td>';
   foreach(tlit_dm_ai_usage_metrics() as $key=>$label){$known=(int)($day['known_'.$key]??0);$value=$known?tlit_dm_ai_usage_value($day[$key]??null,$key==='cost'):'Unknown';if($known>0&&$known<$calls)$value.=' ('.$known.'/'.$calls.' reported)';echo '<td>'.esc_html($value).'</td>';}
   echo '</tr>';
  }echo '</tbody></table></div>';
 }
 $error=is_array($state['last_error']??null)?$state['last_error']:array();
 if($error)echo '<p><strong>Most recent AI request failure:</strong> '.esc_html(gmdate('Y-m-d H:i:s',(int)$error['time'])).' UTC · Status '.esc_html($error['status']??'Unknown').' · Code: '.esc_html($error['code']??'Unknown').'. A later successful response does not erase this diagnostic history.</p>';
 echo '<p class="description">Admin-only accounting. No prompts, responses, contacts, API keys or provider error messages are stored in these statistics.</p></section>';
}
add_action('tlit_dm_ai_cleanup',function(){
 $lock=tlit_dm_ai_usage_lock();if(!$lock)return;
 try{update_option('tlit_dm_ai_usage_v1',tlit_dm_ai_usage_state(),false);}finally{delete_option($lock);}
});
