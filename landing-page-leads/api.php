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
        register_rest_route('tlit-leads/v1','/draft',['methods'=>'POST','permission_callback'=>[__CLASS__,'permission'],'callback'=>[__CLASS__,'draft']]);
        register_rest_route('tlit-leads/v1','/scm-registration',['methods'=>'POST','permission_callback'=>[__CLASS__,'permission'],'callback'=>[__CLASS__,'scm_registration']]);
        register_rest_route('tlit-leads/v1','/scm-otp',['methods'=>'POST','permission_callback'=>[__CLASS__,'permission'],'callback'=>[__CLASS__,'scm_otp']]);
    }
    static function draft($request) {
        $p=self::body($request);
        if(!$p) { return self::error('Invalid form progress update.'); }
        $result=self::save_draft($p);
        return is_wp_error($result)?$result:new WP_REST_Response($result,200);
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
    static function scm_registration($request) {
        $form = strlen($request->get_body()) <= 16000 ? $request->get_body_params() : [];
        $field = static function ($key) use ($form) {
            return is_string($form[$key] ?? null) ? trim($form[$key]) : '';
        };
        $id = $field('registrationId');
        $intent = $field('intent');
        $placement = $field('source');
        $name = sanitize_text_field($field('name'));
        $email = $field('email');
        $country = $field('countryCode');
        $mobile = preg_replace('/[\s()-]/', '', $field('mobile'));
        $phone = $country.ltrim($mobile, '0');
        if ($field('website') !== '' || strlen($name) < 2 || strlen($name) > 80 ||
            !is_email($email) || strlen($email) > 254 ||
            !preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/Di', $id) ||
            !preg_match('/^\+[1-9][0-9]{0,3}$/D', $country) || !preg_match('/^[0-9]{6,14}$/D', $mobile) ||
            !preg_match('/^\+[1-9][0-9]{7,14}$/D', $phone) || (str_starts_with($phone, '+91') && !self::phone($phone))) {
            return self::error('Please check your contact details.');
        }

        $brochure = $field('brochureType');
        $brochures = ['scm' => 'Oracle Fusion SCM Brochure', 'ai' => 'AI Agents Overview'];
        if (!in_array($placement, ['inline','popup'], true) || !in_array($intent, ['demo','brochure'], true) ||
            ($intent === 'brochure' ? $placement !== 'popup' || !isset($brochures[$brochure]) : $brochure !== '') ||
            $field('termsAccepted') !== 'true') {
            return self::error('Please complete the course questions and accept the Terms and Conditions.');
        }

        $choices = [
            'currentStatus' => [
                'final-year' => 'Final-year student',
                'recent-graduate' => 'Recently graduated (0 to 1 year)',
                'graduate-not-working' => 'Graduated more than 1 year ago, not working',
                'working-switch' => 'Working, but want to switch to IT',
            ],
            'highestQualification' => [
                'engineering' => 'B.Tech / B.E.',
                'computer-studies' => 'BCA / B.Sc (IT/CS)',
                'commerce-management' => 'B.Com / BBA / MBA',
                'other-degree' => 'Other degree',
            ],
            'codingComfort' => [
                'none' => 'I have no coding knowledge',
                'basic' => 'I know the basics',
                'comfortable' => 'I’m comfortable with coding',
            ],
            'careerObjective' => [
                'start-it' => 'Start a career in IT from scratch',
                'first-erp-job' => 'Get my first job in Oracle / SAP / ERP',
                'switch-to-it' => 'Switch from a non-IT role to IT',
                'explore' => 'Just exploring options',
            ],
            'startTimeline' => [
                'two-weeks' => 'Immediately (within 2 weeks)',
                'one-month' => 'Within 1 month',
                'two-three-months' => 'In 2 to 3 months',
                'exploring' => 'Just exploring for now',
            ],
            'enrollmentDecision' => [
                'self' => 'I will decide myself',
                'with-family' => 'I’ll decide with my parents or family',
                'family' => 'My parents or family will decide',
                'employer' => 'My employer will sponsor it',
            ],
            'trainingBudget' => [
                'skills' => 'Yes, if the course gives me real job skills',
                'installments' => 'Yes, if I can pay in installments',
                'fee-first' => 'I’d like to know the fee first',
                'free-only' => 'I’m looking for a free option only',
            ],
        ];
        $answers = [];
        foreach ($choices as $key => $values) {
            $choice = $field($key);
            if (!isset($values[$choice])) { return self::error('Please answer all of the course questions.'); }
            $answers[$key] = $values[$choice];
        }

        if (!self::rate('scm-registration-'.($_SERVER['REMOTE_ADDR'] ?? ''), 30, 3600)) {
            return self::error('Too many requests. Please try later.', 429);
        }

        $details = [
            'highest_qualification' => $answers['highestQualification'],
            'coding_comfort' => $answers['codingComfort'],
            'enrollment_decision' => $answers['enrollmentDecision'],
            'training_budget' => $answers['trainingBudget'],
            'terms_accepted' => 'Yes',
        ];
        if ($intent === 'brochure') { $details['brochure_name'] = $brochures[$brochure]; }
        $source = $intent === 'brochure' ? 'brochure' : ($placement === 'popup' ? 'popup' : 'general');
        $payload = [
            'request_id' => $id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'goal' => $answers['careerObjective'],
            'status' => $answers['currentStatus'],
            'timeline' => $answers['startTimeline'],
            'details' => $details,
            'page_variant' => 'oracle_scm',
            'submission_url' => home_url('/oracle-fusion-scm-course-it'),
            'consent_text' => 'I accept the Terms and Conditions.',
        ];
        if (str_starts_with($phone, '+91')) {
            $result = self::scm_save_verified_registration($form, $payload, $source);
        } else {
            $payload['details']['phone_verification'] = 'Not required for international number — not OTP verified';
            $result = self::ingest($payload, ['source' => $source, 'otp_verified' => false]);
        }
        if (is_wp_error($result)) { return $result; }
        return ['success' => true, 'receipt' => $result['receipt'], 'registrationId' => $id, 'intent' => $intent];
    }
    static function otp($request) {
        $p=self::body($request);$phone=self::phone($p['phone']??'');$name=self::clean($p['name']??'',81);
        if(!$phone||strlen($name)<2||strlen($name)>80||!in_array($p['intent']??'',['general','popup','brochure'],true)) { return self::error('Check your name and mobile number.'); }
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
        $financials=($p['page_variant']??'')==='oracle_financials';
        $goals=$financials?['Student or recent graduate','Finance or accounting professional','Oracle EBS or ERP professional','Exploring a career change']:['Get my first marketing job','Grow in my marketing career','Start freelancing','Grow my business'];
        if($financials&&($p['contact_consent']??false)!==true) { return self::error('Please agree to be contacted about your demo request.'); }
        if(strlen($name)<2||strlen($name)>80||!$phone||!is_email($email)||strlen($email)>254||!in_array($source,['general','popup','brochure'],true)||!is_string($id)||!preg_match('/^[a-f0-9-]{36}$/D',$id)||!in_array($p['goal']??'',$goals,true)) { return self::error('Please check your contact details and career goal.'); }
        if($financials&&!tlit_financials_bant_validate($p['details']??null)) { return self::error('Please answer all four questions about your learning plans. Refresh the page if these questions are not visible.'); }
        $options=['status'=>['I’m currently studying','I’ve recently graduated','I’m working and exploring a change','I’m freelancing or running a business'],'timeline'=>['As soon as possible','Within one month','In one to three months','I’m exploring for now'],'question'=>['Fees and payment options','Jobs and career support','Classes, schedule and learning','Projects, tools and practical skills']];
        foreach($options as $key=>$allowed) {
            if($source==='general'&&!$financials&&!in_array($p[$key]??'',$allowed,true)) { return self::error('Please complete the course questions.'); }
            if($source!=='general') { $p[$key]=''; }
        }
        $p['name']=$name;$p['phone']='+91'.$phone;$p['email']=$email;
        $p['submission_url']=$p['submission_url']??($p['tracking']['submission_url']??($p['tracking']['landing_page']??''));
        $p['page_variant']=$p['page_variant']??($p['tracking']['page_variant']??'');
        $otp_required=!($financials&&$source==='general')||tlit_financials_otp_required();
        $context=['source'=>$source,'otp_verified'=>false];
        $lock=self::lock('submit'.$id);if(!$lock) { return self::error('Your request is being saved. Please retry shortly.',409); }
        $otp_lock=false;
        try {
            $existing=$wpdb->get_row($wpdb->prepare('SELECT id,otp_verified,payload FROM '.self::table().' WHERE request_id=%s',$id));
            if($existing) { $context['otp_verified']=!empty($existing->otp_verified);if($financials){$old=json_decode($existing->payload,true)?:[];if(isset($old['details']['phone_verification']))$p['details']['phone_verification']=$old['details']['phone_verification'];else unset($p['details']['phone_verification']);}return self::ingest($p,$context); }
            if(!self::rate('submit-'.($_SERVER['REMOTE_ADDR']??''),30,3600)) { return self::error('Too many requests. Please try later.',429); }
            if($otp_required) {
                $challenge=$p['challenge']??'';$code=$p['otp']??'';
                if(!is_string($challenge)||!preg_match('/^[a-f0-9]{48}$/D',$challenge)||!is_string($code)||!preg_match('/^[0-9]{4}$/D',$code)) { return self::error('Enter the four-digit verification code.'); }
                $otp_lock=self::lock('verify'.$challenge);if(!$otp_lock) { return self::error('Verification in progress.',409); }
                $key='tlit_lp_otp_'.self::hash($challenge);$state=get_transient($key);
                if(!$state||$state['phone']!==$phone||$state['intent']!==$source||$state['expires']<time()||$state['tries']>=5) { return self::error('Code expired. Request a new code.'); }
                if(!hash_equals($state['hash'],self::hash($challenge.'|'.$code))) {
                    $state['tries']++;set_transient($key,$state,max(1,$state['expires']-time()));return self::error('Incorrect code. Please check and try again.');
                }
            }
            $context['otp_verified']=$otp_required;
            if($financials) $p['details']['phone_verification']=$otp_required?'OTP verified':'Bypassed by admin setting — not OTP verified';
            $result=self::ingest($p,$context);
            // Failed database writes preserve OTP so a legitimate retry can complete.
            if(!is_wp_error($result)&&$otp_required) { delete_transient($key); }
            return $result;
        } finally { if($otp_lock) { delete_option($otp_lock); }delete_option($lock); }
    }
}
