<?php
if(!defined('ABSPATH'))exit;
function tlit_dm_chat_settings(){
 $o=tlit_dm_options();$otp=!isset($o['dm_chat_otp_required'])||!empty($o['dm_chat_otp_required']);
 $enabled=!isset($o['dm_chat_enabled'])||!empty($o['dm_chat_enabled']);
 $live=defined('TLIT_DM_ENABLED')?TLIT_DM_ENABLED:!empty($o['dm_live_enabled']);
 return array('enabled'=>$enabled,'otpRequired'=>$otp,'ready'=>$enabled&&$live&&tlit_dm_crm_url()!==''&&(!$otp||tlit_dm_ready()),'batch'=>$o['batch_start_date']??'30 November 2026','schedule'=>$o['batch_timing']??'Monday–Friday, 8–9 PM IST');
}
function tlit_dm_chat_permission($request){
 $parts=wp_parse_url(home_url());$origin=$parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');
 if(rtrim((string)$request->get_header('origin'),'/')!==$origin)return new WP_Error('origin','Use the course website.',array('status'=>403));
 $s=tlit_dm_chat_settings();if(!$s['enabled']||!$s['ready'])return new WP_Error('closed','Chatbot enquiries are temporarily unavailable.',array('status'=>503));
 return true;
}
add_action('rest_api_init',function(){
 register_rest_route('tlit-dm/v1','/chat-settings',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>function(){$r=new WP_REST_Response(tlit_dm_chat_settings(),200);$r->header('Cache-Control','no-store');return $r;}));
 register_rest_route('tlit-dm/v1','/chat-submit',array('methods'=>'POST','permission_callback'=>'tlit_dm_chat_permission','callback'=>'tlit_dm_chat_submit'));
});
function tlit_dm_chat_submit($request){
 // The server, not a browser flag, decides whether OTP can be skipped.
 if(tlit_dm_chat_settings()['otpRequired'])return tlit_dm_error('Phone verification is required. Request an OTP.',403);
 $p=tlit_dm_body($request);if(!$p||!empty($p['website']))return tlit_dm_error('Invalid request.');
 $phone=tlit_dm_phone($p['phone']??'');$name=is_string($p['name']??null)?sanitize_text_field($p['name']):'';$email=is_string($p['email']??null)?sanitize_email($p['email']):'';
 $pain=is_string($p['painPoint']??null)?substr(sanitize_text_field($p['painPoint']),0,600):'';
 $id=$p['requestId']??'';if(!$phone||strlen($name)<2||strlen($name)>80||!is_email($email)||strlen($email)>254||!is_string($id)||!preg_match('/^[a-zA-Z0-9-]{20,64}$/D',$id)||strlen($pain)<3)return tlit_dm_error('Check your name, email, mobile number and learning goal.');
 $key='chat-submit-'.tlit_dm_hash($id.'|'.$phone);$lock=tlit_dm_lock($key);if(!$lock)return tlit_dm_error('Please wait before retrying.',409);
 try{
  $done=get_transient($key);if($done)return new WP_REST_Response($done,200);
  if(!tlit_dm_rate('chat-ip-'.($_SERVER['REMOTE_ADDR']??'unknown'),5,3600)||!tlit_dm_rate('chat-phone-'.$phone,3,3600))return tlit_dm_error('Too many requests. Please try later.',429);
  $reference='DM-'.strtoupper(substr(tlit_dm_hash($id.'|'.$phone),0,10));
  $note="Person downloaded brochure — AI Powered Digital Marketing\nEnquiry channel: Course chatbot\nPain point / goal: ".$pain."\nPhone verification: Not performed (chatbot OTP disabled in WordPress)\nEmail marketing consent: No\nReference: ".$reference;
  $fields=tlit_dm_lead_fields($p,$name,$phone,$email,$pain,$note);
  if(!tlit_dm_crm($fields,$note))return tlit_dm_error('Your enquiry could not be confirmed. Please retry.',502);
  $done=array('success'=>true,'receipt'=>$reference);set_transient($key,$done,1800);return new WP_REST_Response($done,200);
 }finally{delete_option($lock);}
}
