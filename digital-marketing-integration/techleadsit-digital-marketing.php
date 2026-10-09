<?php
/**
 * Plugin Name: Tech Leads IT — Digital Marketing Enquiries
 * Description: Isolated phone verification and TeleCRM routing for the fresher course.
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) exit;
require_once __DIR__.'/lead-schema.php';
function tlit_dm_options(){return function_exists('techleadsit_get_course_options')?techleadsit_get_course_options('digital_marketing'):array();}
function tlit_dm_ready() {
    $o=tlit_dm_options();$enabled=defined('TLIT_DM_ENABLED')?TLIT_DM_ENABLED:!empty($o['dm_live_enabled']);
    $otp=(!isset($o['otp_verification_enabled'])||!empty($o['otp_verification_enabled'])) && empty($o['otp_disabled_slugs']['ai-powered-digital-marketing']) && (!isset($o['otp_required_modal'])||!empty($o['otp_required_modal']));
    return $enabled && $otp && tlit_dm_crm_url()!=='' && function_exists('techleadsit_send_vispl_sms');
}
function tlit_dm_crm_url(){
    if(defined('TLIT_DM_TELECRM_URL'))$url=TLIT_DM_TELECRM_URL;
    elseif(function_exists('techleadsit_telecrm_key'))$url='https://next.telecrm.in/api/b1/enterprise/'.techleadsit_telecrm_key().'/autoupdatelead';
    else $url='';
    return preg_match('~^https://next\.telecrm\.in/api/b1/enterprise/[a-zA-Z0-9_-]+/autoupdatelead$~D',$url)?$url:'';
}
function tlit_dm_error($text, $status=400) { return new WP_REST_Response(array('success'=>false,'message'=>$text), $status); }
function tlit_dm_permission($request) {
    $origin = rtrim((string)$request->get_header('origin'), '/');
    $parts = wp_parse_url(home_url());
    $expected = $parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');
    if ($origin !== $expected) return new WP_Error('origin', 'Use the course form on this website.', array('status'=>403));
    if (!tlit_dm_ready()) return new WP_Error('not_configured', 'Online requests are not open yet. Please contact the team on WhatsApp.', array('status'=>503));
    return true;
}
add_action('rest_api_init', function() {
    foreach(array('send-otp'=>'tlit_dm_send', 'submit'=>'tlit_dm_submit', 'followup'=>'tlit_dm_followup') as $route=>$callback) {
        register_rest_route('tlit-dm/v1', '/'.$route, array('methods'=>'POST','callback'=>$callback,'permission_callback'=>'tlit_dm_permission'));
    }
});
function tlit_dm_hash($value) { return hash_hmac('sha256', $value, wp_salt('auth')); }
function tlit_dm_phone($raw) {
    if (!is_string($raw) || !preg_match('/^[+0-9 ()-]+$/D',$raw)) return '';
    $phone=preg_replace('/\D/','',$raw);
    if(strlen($phone)===12 && substr($phone,0,2)==='91') $phone=substr($phone,2);
    return preg_match('/^[6-9][0-9]{9}$/D',$phone)?$phone:'';
}
function tlit_dm_body($request) {
    if(strlen($request->get_body())>16000) return null;
    $p=$request->get_json_params();return is_array($p)?$p:null;
}
// Atomic option locks prevent duplicate SMS/CRM calls and counter races.
function tlit_dm_lock($id) {
    $key='tlit_dm_lock_'.tlit_dm_hash($id);$old=get_option($key);
    if($old && (int)$old<time()) delete_option($key);
    return add_option($key,time()+60,'','no')?$key:false;
}
function tlit_dm_rate($id,$max,$ttl) {
    $lock=tlit_dm_lock('rate-'.$id);if(!$lock)return false;
    try{$key='tlit_dm_rate_'.tlit_dm_hash($id);$n=(int)get_transient($key);if($n>=$max)return false;set_transient($key,$n+1,$ttl);return true;}finally{delete_option($lock);}
}
function tlit_dm_send($request) {
    $p=tlit_dm_body($request);if(!$p)return tlit_dm_error('Invalid request.');
    $phone=tlit_dm_phone($p['phone']??'');$name=is_string($p['name']??null)?sanitize_text_field($p['name']):'';
    if(!$phone||strlen($name)<2||strlen($name)>80)return tlit_dm_error('Enter your name and a valid Indian mobile number.');
    $ip=$_SERVER['REMOTE_ADDR']??'unknown';
    if(!tlit_dm_rate('sms-ip-'.$ip,10,3600)||!tlit_dm_rate('sms-phone-'.$phone,3,600))return tlit_dm_error('Too many code requests. Please try later.',429);
    $lock=tlit_dm_lock('sms-'.$phone);if(!$lock)return tlit_dm_error('Please wait before requesting another code.',429);
    try {
        $key='tlit_dm_cooldown_'.tlit_dm_hash($phone);
        if(get_transient($key))return tlit_dm_error('Wait 30 seconds before requesting another code.',429);
        set_transient($key,1,30);
        $code=(string)random_int(1000,9999);$challenge=bin2hex(random_bytes(24));
        $sent=techleadsit_send_vispl_sms($phone,$code,$name);
        if(empty($sent['success']))return tlit_dm_error('The SMS could not be sent. Please try again later.',502);
        // A resend invalidates the previous code for this number.
        $active='tlit_dm_active_'.tlit_dm_hash($phone);$old=get_transient($active);if($old)delete_transient('tlit_dm_ch_'.tlit_dm_hash($old));
        set_transient('tlit_dm_ch_'.tlit_dm_hash($challenge),array('phone'=>$phone,'code'=>tlit_dm_hash($challenge.'|'.$code),'tries'=>0,'expires'=>time()+300),300);
        set_transient($active,$challenge,300);
        return new WP_REST_Response(array('success'=>true,'challenge'=>$challenge),200);
    } finally {delete_option($lock);}
}
function tlit_dm_crm($fields,$note) {
    $headers=array('Content-Type'=>'application/json');if(defined('TLIT_DM_TELECRM_TOKEN')&&TLIT_DM_TELECRM_TOKEN!=='')$headers['Authorization']='Bearer '.TLIT_DM_TELECRM_TOKEN;
    $r=wp_remote_post(tlit_dm_crm_url(),array('timeout'=>20,'redirection'=>0,'headers'=>$headers,'body'=>wp_json_encode(array('fields'=>$fields,'actions'=>array(array('type'=>'SYSTEM_NOTE','text'=>$note))))));
    if(is_wp_error($r))return false;
    $status=wp_remote_retrieve_response_code($r);$body=json_decode(wp_remote_retrieve_body($r),true);
    return $status>=200 && $status<300 && !(is_array($body) && (isset($body['error']) || (isset($body['success']) && $body['success']===false)));
}
function tlit_dm_submit($request) {
    $p=tlit_dm_body($request);if(!$p)return tlit_dm_error('Invalid request.');
    $challenge=$p['challenge']??'';$phone=tlit_dm_phone($p['phone']??'');$otp=$p['otp']??'';
    if(!is_string($challenge)||!preg_match('/^[a-f0-9]{48}$/D',$challenge)||!is_string($otp)||!preg_match('/^[0-9]{4}$/D',$otp)||!$phone)return tlit_dm_error('Enter your phone verification code.');
    $name=is_string($p['name']??null)?sanitize_text_field($p['name']):'';$email=is_string($p['email']??null)?sanitize_email($p['email']):'';
    $goal=is_string($p['goal']??null)?sanitize_text_field($p['goal']):'';
    if(strlen($name)<2||strlen($name)>80||!is_email($email)||strlen($email)>254||strlen($goal)>160||$goal==='')return tlit_dm_error('Check your name, email and learning goal.');
    $lock=tlit_dm_lock('submit-'.$challenge);if(!$lock)return tlit_dm_error('Your request is being processed. Please try again shortly.',409);
    try {
        $key='tlit_dm_ch_'.tlit_dm_hash($challenge);$state=get_transient($key);
        if(!$state||$state['phone']!==$phone)return tlit_dm_error('Code expired. Request a new code.');
        if(!empty($state['complete'])) {
            if(!hash_equals($state['code'],tlit_dm_hash($challenge.'|'.$otp)))return tlit_dm_error('Invalid verification code.');
            return new WP_REST_Response(array('success'=>true,'receipt'=>$state['receipt'],'leadToken'=>$state['token']),200);
        }
        if($state['expires']<time()||$state['tries']>=5)return tlit_dm_error('Code expired or attempt limit reached. Request a new code.');
        if(!hash_equals($state['code'],tlit_dm_hash($challenge.'|'.$otp))){$state['tries']++;set_transient($key,$state,max(1,$state['expires']-time()));return tlit_dm_error('Incorrect code. Check the SMS or request a new code.');}
        $consent=($p['emailMarketing']??false)===true;
        $reference='DM-'.strtoupper(substr(tlit_dm_hash($challenge),0,10));
        $fields=array('name'=>$name,'phone'=>'+91'.$phone,'email'=>$email);
        $goal=tlit_dm_answer('goal',$goal);
        $batch=tlit_dm_options()['batch_start_date']??'30 November 2026';
        $note='Course: AI Powered+ Digital Marketing | Reference: '.$reference.' | Goal: '.$goal.' | Language: English | Batch: '.$batch.' | Email marketing consent: '.($consent?'Yes':'No').' | Contact permission: demo enquiry | Captured: '.gmdate('c');
        $details=$p['details']??array();
        if(is_array($details)){foreach(array('student_status','start_timeline','main_question','preferred_call_time') as $detailKey){if(isset($details[$detailKey])&&is_string($details[$detailKey])&&$details[$detailKey]!=='')$note.=' | '.$detailKey.': '.tlit_dm_answer($detailKey,substr(sanitize_text_field($details[$detailKey]),0,200));}}
        $note=str_replace(' | ',"\n",$note);
        $fields=tlit_dm_lead_fields($p,$name,$phone,$email,$goal,$note);
        if(!tlit_dm_crm($fields,$note))return tlit_dm_error('Your phone is verified, but the enquiry could not be confirmed. Please retry; do not close this page.',502);
        $token=bin2hex(random_bytes(24));$state['complete']=true;$state['receipt']=$reference;$state['token']=$token;
        set_transient($key,$state,1800);set_transient('tlit_dm_receipt_'.tlit_dm_hash($reference),array('phone'=>$phone,'token'=>tlit_dm_hash($token)),1800);
        return new WP_REST_Response(array('success'=>true,'receipt'=>$reference,'leadToken'=>$token),200);
    }finally{delete_option($lock);}
}
function tlit_dm_followup($request) {
    $p=tlit_dm_body($request);if(!$p)return tlit_dm_error('Invalid request.');
    $receipt=$p['receipt']??'';$token=$p['leadToken']??'';$phone=tlit_dm_phone($p['phone']??'');
    if(!is_string($receipt)||!is_string($token)||!preg_match('/^DM-[A-F0-9]{10}$/D',$receipt)||!preg_match('/^[a-f0-9]{48}$/D',$token))return tlit_dm_error('Please complete your demo request first.',403);
    $state=get_transient('tlit_dm_receipt_'.tlit_dm_hash($receipt));
    if(!$state||$state['phone']!==$phone||!hash_equals($state['token'],tlit_dm_hash($token)))return tlit_dm_error('This session has expired. Discuss these details on your call.',403);
    $details=$p['details']??array();if(!is_array($details))return tlit_dm_error('Invalid optional details.');
    $parts=array();foreach(array('student_status','start_timeline','main_question','preferred_call_time') as $key){if(isset($details[$key])&&is_string($details[$key]))$parts[]= $key.': '.substr(sanitize_text_field($details[$key]),0,200);}
    if(!$parts)return tlit_dm_error('Choose an optional answer to save.');
    $note='Digital Marketing counselling | '.$receipt.' | '.implode(' | ',$parts);
    $fingerprint='tlit_dm_followup_'.tlit_dm_hash($note);if(get_transient($fingerprint))return new WP_REST_Response(array('success'=>true),200);
    if(!tlit_dm_rate('followup-'.$receipt,3,1800))return tlit_dm_error('Please discuss any further changes on your call.',429);
    if(!tlit_dm_crm(array('phone'=>'+91'.$phone),$note))return tlit_dm_error('Could not save optional details. Your original demo request is still received.',502);
    set_transient($fingerprint,1,1800);return new WP_REST_Response(array('success'=>true),200);
}
