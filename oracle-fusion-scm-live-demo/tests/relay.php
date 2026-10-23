<?php
define('ABSPATH', true);
$config = array(); $store = array(); $calls = array(); $admin = false;
$captcha = array('success'=>true,'hostname'=>'lp.techleadsit.com','action'=>'scm_registration'); $upstream = 'success';
function add_action(...$args) {}
function get_option($k,$default=false) { global $config,$store; return $k === 'tlit_scm_registration' ? $config : ($store[$k]??$default); }
function add_option($k,$v,...$args) { global $store; if(isset($store[$k])) return false; $store[$k]=$v; return true; }
function delete_option($k) { global $store; unset($store[$k]); }
function get_transient($k) { return get_option($k); }
function set_transient($k,$v,$ttl) { global $store; $store[$k]=$v; }
function wp_salt($x) { return 'test-salt'; }
function current_user_can($x) { global $admin; return $admin; }
function is_email($s) { return filter_var($s,FILTER_VALIDATE_EMAIL); }
function wp_json_encode($s,$flags=0) { return json_encode($s,$flags); }
function is_wp_error($v) { return false; }
function wp_remote_retrieve_body($r) { return $r['body']??''; }
function wp_remote_retrieve_response_code($r) { return $r['status']??200; }
function wp_remote_retrieve_header($r,$k) { return $r[$k]??''; }
function wp_remote_post($url,$args) {
    global $captcha,$upstream,$calls,$config;
    $calls[]=$url;
    if(str_contains($url,'cloudflare')) return array('body'=>json_encode($captcha));
    $envelope=json_decode($args['body'],true);
    check(hash_equals(hash_hmac('sha256',$envelope['timestamp'].'.'.$envelope['payload'],$config['secret']),$envelope['signature']),'signed relay');
    $fields=json_decode($envelope['payload'],true);
    check($fields['mobile']==='+12025550123','international phone');
    check(!isset($fields['captchaToken']),'captcha not forwarded');
    if($upstream==='hostile') return array('status'=>302,'location'=>'https://attacker.invalid/');
    return array('body'=>json_encode(array('success'=>$upstream==='success','registrationId'=>$fields['registrationId'])));
}
function wp_remote_get($url,$args) { throw new Exception('Unexpected redirected fetch'); }
class WP_REST_Response { public $data,$status; function __construct($d,$s,$h=[]) {$this->data=$d;$this->status=$s;} }
class Request { public $body,$origin='https://lp.techleadsit.com'; function __construct($b){$this->body=$b;} function get_header($h){return $this->origin;} function get_body(){return http_build_query($this->body);} function get_body_params(){return $this->body;} }
function check($v,$m){if(!$v) throw new Exception($m);}
require __DIR__.'/../registration.php';
$base=array('name'=>'Security test','email'=>'security-test@example.invalid','mobile'=>'2025550123','countryCode'=>'+1','careerObjective'=>'I want to increase my salary.','source'=>'inline','registrationId'=>'00000000-0000-4000-8000-000000000001','website'=>'','captchaToken'=>'test-token');
$request=new Request($base);
check(tlit_scm_register($request)->status===503,'default pause');check(!$calls,'pause sends nothing');
$config=array('enabled'=>true,'url'=>'https://script.google.com/macros/s/test/exec','secret'=>str_repeat('a',64),'sitekey'=>'test','captcha_secret'=>'test');
$request->origin='https://attacker.invalid';check(tlit_scm_register($request)->status===403,'origin rejection');$request->origin='https://lp.techleadsit.com';
$request->body['captchaToken']='';check(tlit_scm_register($request)->status===400,'missing CAPTCHA rejected');$request->body=$base;
foreach(array('hostname'=>'attacker.invalid','action'=>'other','success'=>false) as $key=>$value){$old=$captcha[$key];$captcha[$key]=$value;$store=[];check(tlit_scm_register($request)->status===403,'CAPTCHA gate '.$key);$captcha[$key]=$old;}
$store=[];check(tlit_scm_register($request)->data['success']===true,'valid relay');
$upstream='failure';$store=[];check(tlit_scm_register($request)->status===502,'upstream failure');
$upstream='hostile';$store=[];check(tlit_scm_register($request)->status===502,'hostile redirect rejected');
$store=[];for($i=0;$i<10;$i++) check(tlit_scm_rate('ip:one',10,600),'rate within limit');check(!tlit_scm_rate('ip:one',10,600),'rate blocks excess');
check(tlit_scm_fields(array_merge($base,array('email'=>array('bad'))))===false,'array injection');
check(tlit_scm_fields(array_merge($base,array('website'=>'spam')))===false,'honeypot');
check(tlit_scm_fields(array_merge($base,array('careerObjective'=>'bad')))===false,'enum validation');
$config['enabled']=false;check(tlit_scm_register($request)->status===503,'disabled public');$admin=true;check(tlit_scm_allowed($config),'authenticated admin test allowed');
echo "PASS: paused by default, validation, rate limits, CAPTCHA hostname/action, signed relay, error handling and admin-only test mode.\n";
