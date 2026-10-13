<?php
if(!defined('ABSPATH'))exit;
add_action('admin_menu',function(){add_options_page('Digital Marketing AI Guide','Digital Marketing AI Guide','manage_options','tlit-dm-ai','tlit_dm_ai_admin');});
function tlit_dm_ai_course_key_field(){
 $configured=tlit_dm_ai_key()!=='';
 echo '<fieldset style="margin:20px 0;padding:20px;background:#fff;border:1px solid #ccd9ce;border-radius:10px"><legend><strong>Course chatbot · DeepSeek / OpenRouter</strong></legend><p><label for="dm-course-api-key"><strong>OpenRouter API key</strong></label></p><input id="dm-course-api-key" type="password" name="techleadsit_course_settings_digital_marketing[dm_ai_api_key]" value="" autocomplete="new-password" class="regular-text" placeholder="'.($configured?'Key saved — leave blank to keep it':'Enter your OpenRouter API key').'">';
 echo '<p>'.($configured?'Key saved.':'No API key saved yet.').' Enter a valid key and click <strong>Save Settings</strong> below to enable the chatbot and AI answers. Leaving this field blank keeps the existing key. The key is never displayed or sent to visitors.</p><p><a href="'.esc_url(admin_url('options-general.php?page=tlit-dm-ai')).'">Advanced AI settings, connection test and knowledge review →</a></p></fieldset>';
}
function tlit_dm_ai_save_course_key($input,&$sanitized){
 if(!array_key_exists('dm_ai_api_key',$input)||!current_user_can('manage_options'))return;
 $key=is_string($input['dm_ai_api_key'])?trim($input['dm_ai_api_key']):'';
 if($key==='')return;
 $settings=tlit_dm_ai_options();$settings['api_key']=$key;$settings['enabled']=1;
 $error=tlit_dm_ai_admin_save($settings);
 if($error){add_settings_error('techleadsit_course_settings_digital_marketing','dm_ai_key',$error,'error');return;}
 $sanitized['dm_chat_enabled']=1;
 // Never return the key as part of the course settings option.
}
function tlit_dm_ai_admin_save($post){
 $o=tlit_dm_ai_options();
 $model=is_string($post['model']??null)?trim($post['model']):'';
 if(!preg_match('~^deepseek/[a-zA-Z0-9._:-]+$~D',$model))return 'Use a valid DeepSeek model ID from OpenRouter.';
 $fee=filter_var($post['fee']??null,FILTER_VALIDATE_INT);$regular=filter_var($post['regular_fee']??null,FILTER_VALIDATE_INT);$limit=filter_var($post['daily_limit']??null,FILTER_VALIDATE_INT);
 if(!$fee||$fee<1||$fee>1000000||!$regular||$regular<$fee||$regular>1000000||!$limit||$limit<1||$limit>5000)return 'Check the fees and daily request limit (1–5,000).';
 $knowledge=$post['knowledge']??'';if(!is_string($knowledge)||strlen($knowledge)>18000)return 'Approved knowledge must be plain text, under 18,000 bytes.';
 $key=$post['api_key']??'';if(!is_string($key))return 'Invalid API key.';$key=trim($key);
 if($key!==''){
  if(defined('TLIT_DM_OPENROUTER_KEY'))return 'The API key is managed in wp-config.php. Change it there.';
  if(!preg_match('/^sk-or-[a-zA-Z0-9_-]{20,240}$/D',$key))return 'Enter an OpenRouter API key.';
  if(!function_exists('openssl_encrypt'))return 'This server needs OpenSSL, or set TLIT_DM_OPENROUTER_KEY in wp-config.php.';
  $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($key,'aes-256-gcm',hash('sha256',wp_salt('auth'),true),OPENSSL_RAW_DATA,$iv,$tag);
  if($cipher===false)return 'The key could not be stored securely.';
  update_option('tlit_dm_ai_secret',base64_encode($iv.$tag.$cipher),false);
 }elseif(!empty($post['remove_key']))delete_option('tlit_dm_ai_secret');
 $o=array('enabled'=>!empty($post['enabled'])?1:0,'model'=>$model,'fee'=>$fee,'regular_fee'=>$regular,'daily_limit'=>$limit,'knowledge'=>sanitize_textarea_field($knowledge));
 update_option('tlit_dm_ai_settings',$o,false);return '';
}
function tlit_dm_ai_admin(){
 if(!current_user_can('manage_options'))return;
 $message='';$error='';
 if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
  check_admin_referer('tlit_dm_ai_admin');$p=wp_unslash($_POST);
  if(($p['operation']??'')==='save'){$error=tlit_dm_ai_admin_save($p);if(!$error)$message='Settings saved. Purge the landing-page cache if you changed fees.';}
  elseif(($p['operation']??'')==='test'){
   $request=new WP_REST_Request('POST');$request->set_header('Content-Type','application/json');$request->set_body(wp_json_encode(array('question'=>'What is the course fee? Answer in one sentence.')));
   $result=tlit_dm_ai_answer($request);$data=$result->get_data();
   if($result->get_status()===200)$message='Connection test passed. '.$data['answer'];else $error=$data['message']??'The connection test failed. Check the key, model access and OpenRouter credits.';
  }
  elseif(in_array($p['operation']??'',array('approve','dismiss'),true)){
   $rows=get_option('tlit_dm_ai_review',array());$id=sanitize_text_field($p['review_id']??'');
   if(isset($rows[$id])){
    if($p['operation']==='approve'){
     $answer=is_string($p['approved_answer']??null)?trim(sanitize_textarea_field($p['approved_answer'])):'';
     $o=tlit_dm_ai_options();$entry='Question: '.$rows[$id]['question']."\nApproved answer: ".$answer;
     if(strlen($answer)<3||strlen($entry)>1600||strlen($o['knowledge'])+strlen($entry)>18000)$error='Enter a concise approved answer; the knowledge limit is 18,000 bytes.';
     else{$o['knowledge']=trim($o['knowledge']."\n\n".$entry);update_option('tlit_dm_ai_settings',$o,false);}
    }
    if(!$error){unset($rows[$id]);update_option('tlit_dm_ai_review',$rows,false);$message='Review updated.';}
   }
  }
 }
 $o=tlit_dm_ai_options();$configured=tlit_dm_ai_key()!=='';
 echo '<div class="wrap"><h1>Digital Marketing AI Guide</h1><p>DeepSeek through OpenRouter. The AI answers questions; your existing WordPress form submits leads. No lead contact fields are sent to the model.</p>';
 if($message)echo '<div class="notice notice-success"><p>'.esc_html($message).'</p></div>';
 if($error)echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>';
 echo '<p><strong>API key: '.($configured?'Configured':'Not configured').'</strong> · Chatbot OTP: '.(!empty(tlit_dm_chat_settings()['otpRequired'])?'Required':'Off — name, email and mobile only').'</p><p>Change chatbot visibility and OTP under Landing Page Settings → AI Powered+ Digital Marketing. Batch date/timing are read from the same settings.</p><form method="post">';
 wp_nonce_field('tlit_dm_ai_admin');echo '<input type="hidden" name="operation" value="save"><table class="form-table"><tbody>';
 echo '<tr><th>AI answers</th><td><label><input type="checkbox" name="enabled" value="1" '.(!empty($o['enabled'])?'checked':'').'> Enable AI answers</label></td></tr>';
 echo '<tr><th><label for="dm-api-key">OpenRouter API key</label></th><td><input id="dm-api-key" type="password" name="api_key" value="" autocomplete="new-password" class="regular-text"><p>Leave blank to keep the existing key. It is encrypted at rest using the WordPress auth salt and never returned to browsers. A TLIT_DM_OPENROUTER_KEY constant takes precedence. Rotating WordPress salts requires re-entering a stored key.</p><label><input type="checkbox" name="remove_key" value="1"> Remove saved key</label></td></tr>';
 foreach(array('model'=>'DeepSeek model ID','daily_limit'=>'Maximum AI requests per day (UTC)','fee'=>'Course fee (INR)','regular_fee'=>'Regular price (INR)') as $field=>$title){
  echo '<tr><th><label for="ai-'.esc_attr($field).'">'.esc_html($title).'</label></th><td><input id="ai-'.esc_attr($field).'" name="'.esc_attr($field).'" type="'.($field==='model'?'text':'number').'" value="'.esc_attr($o[$field]).'" class="regular-text"></td></tr>';
 }
 echo '<tr><th><label for="dm-knowledge">Approved additions</label></th><td><textarea id="dm-knowledge" name="knowledge" rows="12" class="large-text">'.esc_textarea($o['knowledge']).'</textarea><p>One fact or question/answer per paragraph. These are approved course facts, not prompt instructions. Do not paste student data, secrets or whole web pages. Current fee and schedule settings take priority.</p></td></tr></tbody></table>';
 echo '<p>Use an OpenRouter key spending limit as an additional budget cap. Requests require structured-output support; if your chosen endpoint is unavailable, the bot offers a team handoff instead of guessing.</p>';
 submit_button('Save AI settings');echo '</form><form method="post">';wp_nonce_field('tlit_dm_ai_admin');echo '<input type="hidden" name="operation" value="test"><p><button class="button" type="submit">Test saved AI connection</button> Sends one small, billable course question using the saved settings. No lead is created.</p></form><h2>Knowledge used by the guide</h2><details><summary>View approved records and sources</summary><ul>';
 foreach(tlit_dm_ai_knowledge() as $r)echo '<li><strong>'.esc_html($r['id']).'</strong> — '.esc_html($r['text']).' <em>('.esc_html($r['source']).')</em></li>';
 echo '</ul></details><h2>Questions needing review</h2><p>Up to 100 unresolved questions from the last 30 days. Email addresses, phone-like numbers and common API key patterns are removed automatically; other personal text may remain. Approve only confirmed course facts. New answers are never learned automatically.</p>';
 $rows=get_option('tlit_dm_ai_review',array());if(!is_array($rows))$rows=array();$rows=array_filter($rows,function($r){return ($r['time']??0)>time()-30*86400;});
 if(!$rows)echo '<p>No unanswered questions awaiting review.</p>';
 foreach($rows as $id=>$r){echo '<form method="post" style="padding:16px;background:#fff;margin:12px 0;max-width:850px">';wp_nonce_field('tlit_dm_ai_admin');echo '<input type="hidden" name="review_id" value="'.esc_attr($id).'"><p><strong>'.esc_html($r['question']).'</strong> · '.(int)$r['count'].' occurrence(s)</p><label>Approved answer<textarea name="approved_answer" rows="3" class="large-text" maxlength="1400"></textarea></label><p><button class="button button-primary" name="operation" value="approve">Approve into knowledge</button> <button class="button" name="operation" value="dismiss">Dismiss</button></p></form>';}
 echo '</div>';
}
// Remove expired review text even when no visitor or admin opens the chatbot.
add_action('init',function(){if(!wp_next_scheduled('tlit_dm_ai_cleanup'))wp_schedule_event(time()+3600,'daily','tlit_dm_ai_cleanup');});
add_action('tlit_dm_ai_cleanup',function(){
 $rows=get_option('tlit_dm_ai_review',array());if(!is_array($rows))return;
 $rows=array_filter($rows,function($r){return ($r['time']??0)>time()-30*86400;});update_option('tlit_dm_ai_review',$rows,false);
});
