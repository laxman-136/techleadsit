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
 if(($state['answers']??0)<3)return 'question';
 if(empty($state['contact_complete']))return $state['capture_stage']??'name';
 if(($state['followup_answers']??0)>=10||get_option($state['lead_quota_key'],0)>=10)return 'limit';
 return 'question';
}
function tlit_dm_chat_prompt($stage,$language){
 $messages=array(
 'English'=>array('name'=>'Before we continue, what is your name? Your details stay in this chat for up to 24 hours and are not sent to our team yet.','phone'=>'Thank you. What is your mobile number?','email'=>'And what email address can we use for your course enquiry?','question'=>'Thank you. You can now ask me up to ten more digital marketing questions.','limit'=>'For more help, please call or WhatsApp our course team on +91 81429 29394.'),
 'Telugu'=>array('name'=>'మనం కొనసాగించే ముందు, మీ పేరు ఏమిటి? మీ వివరాలు ఈ చాట్‌లో 24 గంటల వరకు ఉంటాయి. ఇంకా మా టీమ్‌కు పంపబడవు.','phone'=>'ధన్యవాదాలు. మీ మొబైల్ నంబర్ ఏమిటి?','email'=>'మీ కోర్సు విచారణ కోసం మీ ఇమెయిల్ చిరునామా చెప్పగలరా?','question'=>'ధన్యవాదాలు. ఇప్పుడు డిజిటల్ మార్కెటింగ్ గురించి మరో పది ప్రశ్నలు అడగవచ్చు.','limit'=>'మరింత సహాయం కోసం +91 81429 29394 నంబర్‌కు కాల్ లేదా వాట్సాప్ చేయండి.'),
 'Hindi'=>array('name'=>'आगे बढ़ने से पहले, आपका नाम क्या है? आपकी जानकारी इस चैट में 24 घंटे तक रहेगी और अभी हमारी टीम को नहीं भेजी जाएगी।','phone'=>'धन्यवाद। आपका मोबाइल नंबर क्या है?','email'=>'आपकी कोर्स enquiry के लिए आपका ईमेल पता क्या है?','question'=>'धन्यवाद। अब आप डिजिटल मार्केटिंग के बारे में दस और सवाल पूछ सकते हैं।','limit'=>'आगे की मदद के लिए हमारी टीम को +91 81429 29394 पर कॉल या WhatsApp करें।'));
 return ($messages[$language]??$messages['English'])[$stage]??'';
}
function tlit_dm_chat_flow_reply($state,$token,$language,$answer=''){
 $stage=tlit_dm_chat_stage($state);return array('success'=>true,'answer'=>$answer!==''?$answer:tlit_dm_chat_prompt($stage,$language),'conversation'=>$token,'stage'=>$stage,'remaining'=>!empty($state['contact_complete'])?max(0,10-max($state['followup_answers']??0,(int)get_option($state['lead_quota_key'],0))):max(0,3-($state['answers']??0)));
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
 unset($state['last'],$state['response']);$state['contact_sealed']=$sealed;$state['capture_stage']=$stage==='name'?'phone':($stage==='phone'?'email':'question');
 if($stage==='email'){$state['contact_complete']=true;$state['lead_quota_key']='tlit_dm_chat_quota_'.tlit_dm_hash($contact['phone']);$state['followup_answers']=(int)get_option($state['lead_quota_key'],0);}
 return '';
}
