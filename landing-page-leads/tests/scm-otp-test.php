<?php
// Isolated adapter tests. SMS, transients and persistence are in-memory; no network.
define('ABSPATH', true);
class WP_Error {
    public $code, $message, $data;
    function __construct($code, $message, $data = []) { $this->code=$code; $this->message=$message; $this->data=$data; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
function home_url($path='') { return 'https://example.test'.$path; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key,$value,$ttl) { $GLOBALS['transients'][$key]=$value; return true; }
function delete_transient($key) { unset($GLOBALS['transients'][$key]); return true; }
function delete_option($key) { unset($GLOBALS['locks'][$key]); return true; }
function techleadsit_send_vispl_sms($phone,$code,$name) {
    $GLOBALS['sms'][]=compact('phone','code','name');
    return ['success'=>empty($GLOBALS['sms_failure'])];
}
require __DIR__.'/../api.php';
require __DIR__.'/../scm-otp.php';
class SCM_OTP_Test {
    use TLIT_LP_API, TLIT_LP_SCM_OTP;
    static $rows=[], $ingestCalls=0, $rejectRate=false, $storageFailure=false;
    static function error($message,$status=400) { return new WP_Error('test',$message,['status'=>$status]); }
    static function clean($value,$max=500) { return is_string($value)?substr(sanitize_text_field($value),0,$max):''; }
    static function phone($value) {
        if(!is_string($value)||!preg_match('/^[+0-9 ()-]+$/D',$value)) return '';
        $n=preg_replace('/\D/','',$value);
        if(strlen($n)===12&&str_starts_with($n,'91')) $n=substr($n,2);
        return preg_match('/^[6-9][0-9]{9}$/D',$n)?$n:'';
    }
    static function hash($value) { return hash_hmac('sha256',$value,'local-test-only'); }
    static function rate($value,$maximum,$seconds) { return !self::$rejectRate; }
    static function lock($value) {
        $key=self::hash($value);
        if(isset($GLOBALS['locks'][$key])) return false;
        $GLOBALS['locks'][$key]=true; return $key;
    }
    static function ingest($payload,$context=[]) {
        self::$ingestCalls++;
        if(self::$storageFailure) return self::error('Mock storage unavailable',503);
        $id=$payload['request_id']; $fingerprint=hash('sha256',serialize([$payload,$context]));
        if(isset(self::$rows[$id])) {
            if(self::$rows[$id]['fingerprint']!==$fingerprint) return self::error('Changed request',409);
            return ['success'=>true,'receipt'=>self::$rows[$id]['receipt'],'duplicate'=>true];
        }
        $receipt='LP-'.(count(self::$rows)+1);
        self::$rows[$id]=compact('payload','context','fingerprint','receipt');
        return ['success'=>true,'receipt'=>$receipt,'duplicate'=>false];
    }
}
class SCM_Request {
    private $data;
    function __construct($data) { $this->data=$data; }
    function get_body() { return json_encode($this->data); }
    function get_json_params() { return $this->data; }
    function get_body_params() { return $this->data; }
}
function check($value,$message) { if(!$value) throw new RuntimeException($message); }
function fixture($changes=[]) {
    return array_replace([
        'registrationId'=>'11111111-1111-4111-8111-111111111111','intent'=>'demo','source'=>'inline',
        'name'=>'Synthetic SCM QA','email'=>'qa@example.invalid','countryCode'=>'+91','mobile'=>'9123456789',
        'currentStatus'=>'recent-graduate','highestQualification'=>'engineering','codingComfort'=>'none',
        'careerObjective'=>'first-erp-job','startTimeline'=>'one-month','enrollmentDecision'=>'self',
        'trainingBudget'=>'has-budget','targetPackage'=>'4-8','termsAccepted'=>'true','website'=>'',
        'utm_source'=>'facebook','campaign_name'=>'SCM Campaign QA','ad_name'=>'SCM Video QA','adset_name'=>'SCM Freshers QA','ga_client_id'=>'100.200','session_id'=>'300',
    ],$changes);
}
function register($p) { return SCM_OTP_Test::scm_registration(new SCM_Request($p)); }
function send_code($p) {
    delete_transient('tlit_lp_cool_'.SCM_OTP_Test::hash($p['mobile']));
    return SCM_OTP_Test::scm_otp(new SCM_Request([
        'name'=>$p['name'],'phone'=>$p['countryCode'].$p['mobile'],
        'registrationId'=>$p['registrationId'],'intent'=>$p['intent'],'source'=>$p['source'],
    ]));
}
function state_key($challenge) { return 'tlit_lp_scm_otp_'.SCM_OTP_Test::hash($challenge); }

$india=fixture();
check(is_wp_error(register($india))&&SCM_OTP_Test::$ingestCalls===0,'Indian submissions must not reach storage without OTP.');
$foreign=fixture(['registrationId'=>'22222222-2222-4222-8222-222222222222','countryCode'=>'+1','mobile'=>'2025550123','otp_verified'=>true]);
check(!is_wp_error(register($foreign))&&empty($GLOBALS['sms']),'International submissions should not request SMS.');
$row=SCM_OTP_Test::$rows[$foreign['registrationId']];
check(($row['payload']['details']['target_package']??'')==='₹4–8 LPA','Target package must be saved in the main Bridge details.');
check(($row['payload']['details']['training_budget']??'')==='Yes, I have a budget of ₹25,000–₹30,000','Budget answer must retain the selected amount.');
foreach(['campaign_name'=>'SCM Campaign QA','ad_name'=>'SCM Video QA','adset_name'=>'SCM Freshers QA','ga_client_id'=>'100.200','session_id'=>'300'] as $key=>$value)check(($row['payload']['tracking'][$key]??'')===$value,'SCM adapter must preserve '.$key.' in the main Bridge payload.');
check($row['context']['otp_verified']===false&&str_contains($row['payload']['details']['phone_verification'],'international'),'Foreign leads must not be labelled verified, even with forged client flags.');
check(is_wp_error(register(fixture(['countryCode'=>'+9','mobile'=>'19123456789']))),'Splitting the +91 prefix must not bypass OTP.');

$sent=send_code($india);
check(!is_wp_error($sent)&&preg_match('/^[a-f0-9]{48}$/D',$sent['challenge']),'An Indian number should receive an opaque challenge.');
check(!isset($sent['otp'])&&!isset($sent['code']),'The response must never disclose the SMS code.');
$challenge=$sent['challenge']; $key=state_key($challenge); $original=get_transient($key);
$smsCount=count($GLOBALS['sms']);
$cooldown=SCM_OTP_Test::scm_otp(new SCM_Request([
    'name'=>$india['name'],'phone'=>'+91'.$india['mobile'],'registrationId'=>$india['registrationId'],'intent'=>'demo','source'=>'inline',
]));
check(is_wp_error($cooldown)&&$cooldown->data['status']===429&&count($GLOBALS['sms'])===$smsCount,'The resend cooldown must prevent another SMS.');
$valid=fixture(['challenge'=>$challenge,'otp'=>end($GLOBALS['sms'])['code']]);
foreach(['scope'=>'other-page','phone'=>'9876543210','registration_id'=>'other-request','intent'=>'brochure','placement'=>'popup'] as $field=>$value) {
    set_transient($key,array_replace($original,[$field=>$value]),300);
    check(is_wp_error(register($valid)),'Challenge binding failed for '.$field);
}
set_transient($key,$original,300);
$wrong=$valid;$wrong['otp']=$valid['otp']==='1234'?'1235':'1234';
for($i=0;$i<5;$i++) check(is_wp_error(register($wrong)),'Wrong codes must be rejected.');
check(is_wp_error(register($valid)),'Five failed attempts must lock out the challenge.');

$resent=send_code($india);
check(get_transient($key)===false,'A successful resend must invalidate the old challenge.');
$valid=fixture(['challenge'=>$resent['challenge'],'otp'=>end($GLOBALS['sms'])['code']]);
$saved=register($valid);
check(!is_wp_error($saved)&&SCM_OTP_Test::$rows[$india['registrationId']]['context']['otp_verified']===true,'A verified Indian lead must be marked verified.');
$retry=register($valid);
check(!is_wp_error($retry)&&$retry['receipt']===$saved['receipt']&&count(SCM_OTP_Test::$rows)===2,'An exact retry must reuse the receipt without duplicating the lead.');
check(is_wp_error(register(array_replace($valid,['email'=>'changed@example.invalid']))),'Changed details must not replay a saved request.');
check(is_wp_error(register(array_replace($valid,['mobile'=>'9876543210']))),'A code must not verify a different phone number.');
check(is_wp_error(register(array_replace($valid,['source'=>'popup']))),'An inline code must not verify the popup.');

$next=fixture(['registrationId'=>'33333333-3333-4333-8333-333333333333']);
$sent=send_code($next);$key=state_key($sent['challenge']);
$p=array_replace($next,['challenge'=>$sent['challenge'],'otp'=>end($GLOBALS['sms'])['code']]);
SCM_OTP_Test::$storageFailure=true;
check(is_wp_error(register($p))&&is_array(get_transient($key)),'A database failure must preserve verification for a legitimate retry.');
SCM_OTP_Test::$storageFailure=false;
check(!is_wp_error(register($p)),'A storage retry with the same code should succeed.');
$expired=get_transient($key);$expired['expires']=time()-1;set_transient($key,$expired,300);
check(is_wp_error(register($p)),'Expired codes must be rejected.');
delete_transient($key);set_transient('tlit_lp_otp_'.SCM_OTP_Test::hash($sent['challenge']),$expired,300);
check(is_wp_error(register($p)),'A generic page challenge must not authorize the SCM adapter.');

SCM_OTP_Test::$rejectRate=true;
check(is_wp_error(send_code($next)),'Rate-limited SMS requests must fail.');
SCM_OTP_Test::$rejectRate=false;
$GLOBALS['sms_failure']=true;
$failed=send_code($next);
check(is_wp_error($failed)&&$failed->data['status']===502,'Gateway failure must not show a usable challenge.');
$option_id=1000;
foreach(['below-4'=>'Below ₹4 LPA','4-8'=>'₹4–8 LPA','job-first'=>'Package isn’t important; I’m looking for a job.'] as $target=>$label) {
    foreach(['no-budget'=>'No budget right now','has-budget'=>'Yes, I have a budget of ₹25,000–₹30,000'] as $budget=>$budget_label) {
        $id=sprintf('%08x-0000-4000-8000-%012x',++$option_id,$option_id);
        $form=fixture(['registrationId'=>$id,'countryCode'=>'+1','mobile'=>'2025550123','targetPackage'=>$target,'trainingBudget'=>$budget,'highestQualification'=>'computer-studies','careerObjective'=>'explore','startTimeline'=>'exploring']);
        check(!is_wp_error(register($form)),'All requested package and budget options must be accepted.');
        $stored=SCM_OTP_Test::$rows[$id]['payload'];
        check($stored['details']['target_package']===$label&&$stored['details']['training_budget']===$budget_label,'Selected package and budget must be retained exactly.');
        check($stored['details']['highest_qualification']==='BCA / MCA / B.Sc (IT/CS)','Education must include MCA.');
        check($stored['goal']==='Casual Exploring Options'&&$stored['timeline']==='Casual Exploring Options','Exploration labels must match the form.');
    }
}
foreach([['targetPackage'=>''],['targetPackage'=>'unknown'],['trainingBudget'=>'fee-first'],['trainingBudget'=>'skills'],['enrollmentDecision'=>'employer']] as $invalid) {
    check(is_wp_error(register(fixture(array_merge(['countryCode'=>'+1','mobile'=>'2025550123'], $invalid)))),'Missing/unknown or removed form options must not reach storage.');
}
echo "PASS: SCM package, budget, MCA and exploration choices; India-only OTP enforcement, international bypass, request binding, resend, expiry, safe retries and SMS failures. No real SMS sent.\n";
