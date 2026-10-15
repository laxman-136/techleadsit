<?php
if (!defined('ABSPATH')) { exit; }
trait TLIT_LP_API {
    static function permission($request) {
        $origin=rtrim($request->get_header('origin'),'/');$url=wp_parse_url(home_url());
        $expected=$url['scheme'].'://'.$url['host'].(isset($url['port'])?':'.$url['port']:'');
        return $origin===$expected?true:self::error('Please use the course form on this website.',403);
    }
    static function routes() {
        foreach(['otp','submit','download'] as $route) { register_rest_route('tlit-leads/v1','/'.$route,['methods'=>'POST','permission_callback'=>[__CLASS__,'permission'],'callback'=>[__CLASS__,$route]]); }
    }
    static function download($request) {
        $p=self::body($request);$receipt=$p['receipt']??'';$token=$p['download_token']??($p['token']??'');
        if(!is_string($receipt)||!preg_match('/^LP-([1-9][0-9]*)$/D',$receipt,$match)||!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)) { return self::error('Invalid brochure receipt.',403); }
        $row=self::get((int)$match[1]);
        if(!$row||!hash_equals(self::download_token($row->id,$row->request_id),$token)) { return self::error('Invalid brochure receipt.',403); }
        $saved=self::update_details($row->id,['brochure_downloaded'=>true]);
        return is_wp_error($saved)?$saved:['success'=>true];
    }
    static function body($request) { return strlen($request->get_body())>16000?[]:(array)$request->get_json_params(); }
    static function otp($request) {
        $p=self::body($request);$phone=self::phone($p['phone']??'');$name=self::clean($p['name']??'',81);
        if(!$phone||strlen($name)<2||strlen($name)>80||!in_array($p['intent']??'',['general','popup'],true)) { return self::error('Check your name and mobile number.'); }
        if(!function_exists('techleadsit_send_vispl_sms')) { return self::error('SMS verification is not configured. Please call the career advisor.',503); }
        if(!self::rate('ip-'.($_SERVER['REMOTE_ADDR']??''),10,3600)||!self::rate('phone-'.$phone,3,600)) { return self::error('Too many code requests. Please try later.',429); }
        $lock=self::lock('sms'.$phone);if(!$lock) { return self::error('A code request is already in progress.',409); }
        try {
            $cool='tlit_lp_cool_'.self::hash($phone);if(get_transient($cool)) { return self::error('Wait 30 seconds before requesting another code.',429); }
            set_transient($cool,1,30);$code=(string)random_int(1000,9999);$challenge=bin2hex(random_bytes(24));
            $sent=techleadsit_send_vispl_sms($phone,$code,$name);if(empty($sent['success'])) { return self::error('We could not send the code. Please try again.',502); }
            $active='tlit_lp_active_'.self::hash($phone);$old=get_transient($active);if($old) { delete_transient('tlit_lp_otp_'.self::hash($old)); }
            set_transient('tlit_lp_otp_'.self::hash($challenge),['phone'=>$phone,'intent'=>$p['intent'],'hash'=>self::hash($challenge.'|'.$code),'tries'=>0,'expires'=>time()+300],300);
            set_transient($active,$challenge,300);return ['success'=>true,'challenge'=>$challenge];
        } finally { delete_option($lock); }
    }
    static function submit($request) {
        global $wpdb;
        $p=self::body($request);$name=self::clean($p['name']??'',81);$phone=self::phone($p['phone']??'');
        $email=is_string($p['email']??null)?sanitize_email($p['email']):'';$source=$p['intent']??'';$id=$p['request_id']??'';
        $goals=['Get my first marketing job','Grow in my marketing career','Start freelancing','Grow my business'];
        if(strlen($name)<2||strlen($name)>80||!$phone||!is_email($email)||strlen($email)>254||!in_array($source,['general','popup','brochure'],true)||!is_string($id)||!preg_match('/^[a-f0-9-]{36}$/D',$id)||!in_array($p['goal']??'',$goals,true)) { return self::error('Please check your contact details and career goal.'); }
        $options=['status'=>['I’m currently studying','I’ve recently graduated','I’m working and exploring a change','I’m freelancing or running a business'],'timeline'=>['As soon as possible','Within one month','In one to three months','I’m exploring for now'],'question'=>['Fees and payment options','Jobs and career support','Classes, schedule and learning','Projects, tools and practical skills']];
        foreach($options as $key=>$allowed) {
            if($source==='general'&&!in_array($p[$key]??'',$allowed,true)) { return self::error('Please complete the course questions.'); }
            if($source!=='general') { $p[$key]=''; }
        }
        $p['name']=$name;$p['phone']='+91'.$phone;$p['email']=$email;
        $p['submission_url']=$p['submission_url']??($p['tracking']['submission_url']??($p['tracking']['landing_page']??''));
        $p['page_variant']=$p['page_variant']??($p['tracking']['page_variant']??'');
        $context=['source'=>$source,'otp_verified'=>$source!=='brochure'];
        $lock=self::lock('submit'.$id);if(!$lock) { return self::error('Your request is being saved. Please retry shortly.',409); }
        $otp_lock=false;
        try {
            $existing=$wpdb->get_row($wpdb->prepare('SELECT id FROM '.self::table().' WHERE request_id=%s',$id));
            if($existing) { return self::ingest($p,$context); }
            if(!self::rate('submit-'.($_SERVER['REMOTE_ADDR']??''),30,3600)) { return self::error('Too many requests. Please try later.',429); }
            if($source!=='brochure') {
                $challenge=$p['challenge']??'';$code=$p['otp']??'';
                if(!is_string($challenge)||!preg_match('/^[a-f0-9]{48}$/D',$challenge)||!is_string($code)||!preg_match('/^[0-9]{4}$/D',$code)) { return self::error('Enter the four-digit verification code.'); }
                $otp_lock=self::lock('verify'.$challenge);if(!$otp_lock) { return self::error('Verification in progress.',409); }
                $key='tlit_lp_otp_'.self::hash($challenge);$state=get_transient($key);
                if(!$state||$state['phone']!==$phone||$state['intent']!==$source||$state['expires']<time()||$state['tries']>=5) { return self::error('Code expired. Request a new code.'); }
                if(!hash_equals($state['hash'],self::hash($challenge.'|'.$code))) {
                    $state['tries']++;set_transient($key,$state,max(1,$state['expires']-time()));return self::error('Incorrect code. Please check and try again.');
                }
            }
            $result=self::ingest($p,$context);
            // Failed database writes preserve OTP so a legitimate retry can complete.
            if(!is_wp_error($result)&&$source!=='brochure') { delete_transient($key); }
            return $result;
        } finally { if($otp_lock) { delete_option($otp_lock); }delete_option($lock); }
    }
}
