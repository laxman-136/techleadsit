<?php
if(!defined('ABSPATH'))exit;
// Contact capture is held only in the private conversation transient for 24 hours.
// It is never forwarded to OpenRouter or TeleCRM by this flow.
function tlit_dm_chat_seal($data){
 $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt(wp_json_encode($data),'aes-256-gcm',hash('sha256',wp_salt('auth').'chat-contact',true),OPENSSL_RAW_DATA,$iv,$tag);
 return $cipher===false?'':base64_encode($iv.$tag.$cipher);
}
function tlit_dm_chat_unseal($data){
 $raw=base64_decode($data,true);if(!$raw||strlen($raw)<29)return array();
 $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',wp_salt('auth').'chat-contact',true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
 return $plain?json_decode($plain,true):array();
}
function tlit_dm_chat_stage($state){
 if(($state['answers']??0)<2)return 'question';
 if(empty($state['contact_complete']))return 'phone';
 if(($state['followup_answers']??0)>=10||get_option($state['lead_quota_key'],0)>=10)return 'limit';
 return 'question';
}
function tlit_dm_chat_prompt($stage,$language){
 $messages=array(
 'English'=>array('name'=>'Hi, I’m Lakshmi. What should I call you?','phone'=>'I’d be happy to help you find the right next step. What’s your mobile number?','email'=>'Where would you like to receive the brochure?','question'=>'Thanks! What else would you like to know?','limit'=>'Let’s take the next step with our course team. Call or WhatsApp +91 81429 29394 to discuss your goals and the course.'),
 'Telugu'=>array('name'=>'నమస్కారం, నేను లక్ష్మి. మీ పేరు ఏమిటి?','phone'=>'మీకు సరైన తదుపరి అడుగు ఎంచుకోవడంలో సహాయం చేస్తాను. మీ మొబైల్ నంబర్ చెప్పగలరా?','email'=>'బ్రోచర్ కోసం మీ ఇమెయిల్ చిరునామా చెప్పగలరా?','question'=>'ధన్యవాదాలు! ఇంకా ఏమి తెలుసుకోవాలనుకుంటున్నారు?','limit'=>'మీ లక్ష్యాలు, కోర్సు గురించి మా టీమ్‌తో మాట్లాడండి. +91 81429 29394 నంబర్‌కు కాల్ లేదా వాట్సాప్ చేయండి.'),
 'Hindi'=>array('name'=>'नमस्ते, मैं लक्ष्मी हूँ। आपका नाम क्या है?','phone'=>'आपके लिए सही अगला कदम चुनने में मदद करूँगी। आपका मोबाइल नंबर क्या है?','email'=>'ब्रोशर किस ईमेल पर भेजें?','question'=>'धन्यवाद! आप और क्या जानना चाहेंगे?','limit'=>'अपने लक्ष्य और कोर्स के बारे में हमारी टीम से बात करें। +91 81429 29394 पर कॉल या WhatsApp करें।'));
 return ($messages[$language]??$messages['English'])[$stage]??'';
}
function tlit_dm_chat_flow_reply($state,$token,$language,$answer=''){
 $stage=tlit_dm_chat_stage($state);return array('success'=>true,'answer'=>$answer!==''?$answer:tlit_dm_chat_prompt($stage,$language),'conversation'=>$token,'stage'=>$stage,'remaining'=>!empty($state['contact_complete'])?max(0,10-max($state['followup_answers']??0,(int)get_option($state['lead_quota_key'],0))):max(0,2-($state['answers']??0)));
}
function tlit_dm_chat_capture(&$state,$stage,$value){
 $contact=tlit_dm_chat_unseal($state['contact_sealed']??'');if(!is_array($contact))$contact=array();
 if($stage==='name'){
  $value=sanitize_text_field($value);if(strlen($value)<2||strlen($value)>240||preg_match('/^[0-9\s]+$/D',$value))return 'Please enter your name.';
 }elseif($stage==='phone'){
  if(!preg_match('/^[+0-9 ()-]+$/D',$value))return 'Enter a valid 10-digit Indian mobile number.';
  $value=preg_replace('/\D/','',$value);if(strlen($value)===12&&substr($value,0,2)==='91')$value=substr($value,2);
  if(!preg_match('/^[6-9][0-9]{9}$/D',$value))return 'Enter a valid 10-digit Indian mobile number.';
 }elseif($stage==='email'){
  if(strlen($value)>254||!filter_var($value,FILTER_VALIDATE_EMAIL))return 'Enter a valid email address.';
 }else return 'Please continue the current chat step.';
 $contact[$stage]=$value;$sealed=tlit_dm_chat_seal($contact);if(!$sealed)return 'Could not save this chat step. Please try again.';
 unset($state['last'],$state['response']);$state['contact_sealed']=$sealed;$state['capture_stage']='question';
 if($stage==='phone'){$state['contact_complete']=true;$state['lead_quota_key']='tlit_dm_chat_quota_'.tlit_dm_hash($contact['phone']);$state['followup_answers']=(int)get_option($state['lead_quota_key'],0);}
 return '';
}
