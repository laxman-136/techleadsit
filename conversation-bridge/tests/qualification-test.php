<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
/** Offline qualification safety tests. No database, SMS or external CRM calls. */
define('ABSPATH',__DIR__.'/');
$checks=0;$allowed=true;$nonce=true;$hooks=array();
class WP_Error { public $message; function __construct($code,$message){$this->message=$message;}function get_error_message(){return $this->message;} }
class Stop extends RuntimeException {}
function is_wp_error($v){return $v instanceof WP_Error;}
function add_action($a,$b,$p=10,$n=1){$GLOBALS['hooks'][$a]=$b;}
function do_action($name,...$args){$GLOBALS['review_events'][]=array($name,$args);}
function add_filter($a,$b,$p=10,$n=1){$GLOBALS['hooks'][$a]=$b;}
function current_user_can($c){return $c==='manage_options'&&$GLOBALS['allowed'];}
function check_admin_referer($a){if(!$GLOBALS['nonce'])throw new Stop('nonce',403);}
function wp_die($m,$t='',$a=array()){throw new Stop($m,$a['response']??0);}
function wp_unslash($v){return $v;}
function absint($v){return abs((int)$v);}
function get_current_user_id(){return 9;}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function esc_attr($v){return esc_html($v);}
function esc_url($v){return esc_html($v);}
function admin_url($v){return 'https://example.test/wp-admin/'.$v;}
function wp_safe_redirect($v){throw new Stop('redirect:'.$v);}
function wp_nonce_field($v){echo '<input name="_wpnonce" value="test">';}
function selected($a,$b,$echo=false){return $a===$b?' selected':'';}
function check($ok,$m){++$GLOBALS['checks'];if(!$ok)throw new RuntimeException('FAIL: '.$m);}
function blocked($f,$code,$m){try{$f();}catch(Stop $e){check($e->getCode()===$code,$m);return;}throw new RuntimeException('FAIL: '.$m);}
final class TLIT_CB_Store {
    static $rows=array();static $error=false;static $tokens=array();static $write_fail=false;
    static function get($id){return self::$rows[$id]??null;}
    static function for_session($token){return self::get(self::$tokens[$token]??0);}
    static function read_error(){return self::$error;}
    static function with_locked_conversation($id,$callback){$row=self::get($id);return $row?$callback($row):new WP_Error('missing','Missing conversation');}
    static function update_qualification($id,$lead,$status,$note){if(self::$write_fail)return false;self::$rows[$id]['lead_id']=$lead;self::$rows[$id]['qualification_status']=$status;self::$rows[$id]['qualification_note']=$note;return true;}
}
final class TLIT_LP_Leads {
    static $rows=array();static $syncs=array();static $saves=array();static $fail=false;static $erase_ok=true;static $erases=array();
    static function get($id){return self::$rows[$id]??null;}
    static function variant($v,$url){return strpos($url,'training')!==false?'v3':(strpos($url,'course')!==false?'v2':'v1');}
    static function canonical_phone($p){return is_string($p)&&preg_match('/^\+[1-9][0-9]{6,14}$/D',$p)?$p:'';}
    static function sync_conversation($p,$id=0){self::$syncs[]=array($p,$id);if(self::$fail)return new WP_Error('db','Sync failed');if(!$id)$id=101;self::$rows[$id]=self::$rows[$id]??(object)array('id'=>$id,'lead_status'=>'new','advisor_note'=>'','crm_status'=>'pending');return array('success'=>true,'lead_id'=>$id,'receipt'=>'LP-'.$id,'download_token'=>'proof-'.$id);}
    static function save_status($id,$status,$note,$actor,$deliver=true){self::$saves[]=array($id,$status,$note,$actor,$deliver);$r=self::$rows[$id];$r->lead_status=$status;$r->advisor_note=$note;if($deliver&&!in_array($status,array('new','junk'),true))$r->crm_status='sent';return $r;}
    static function erase_conversation_lead($id,$cid){self::$erases[]=array($id,$cid);return self::$erase_ok;}
}
require dirname(__DIR__).'/includes/qualification.php';
TLIT_CB_Qualification::register();
check(isset($hooks['admin_post_tlit_cb_qualify'])&&isset($hooks['tlit_cb_before_conversation_erase']),'secure admin and privacy hooks registered');
$row=array('id'=>7,'course'=>'digital_marketing','name'=>'QA Tester','phone'=>'','email'=>'','goal'=>'Likely first marketing job','source_url'=>'https://example.test/ai-powered-digital-marketing-course/','tracking_json'=>json_encode(array('utm_source'=>'google','gclid'=>'qa-click','fb_adset_id'=>'qa-adset')),'language'=>'Telugu','stage'=>'question','status'=>'contacted','has_brochure'=>0,'lead_id'=>0,'qualification_status'=>'new','qualification_note'=>'');
TLIT_CB_Store::$rows[7]=$row;
check(TLIT_CB_Qualification::qualify(7,'junk','No contact',9)===true,'incomplete chat can be marked junk without invented phone');
check(count(TLIT_LP_Leads::$syncs)===0&&count(TLIT_LP_Leads::$saves)===0,'new/junk unlinked chat makes no shared-service or CRM call');
check(TLIT_CB_Store::$rows[7]['status']==='contacted','conversation review state remains independent');
check(is_wp_error(TLIT_CB_Qualification::qualify(7,'potential','',9)),'missing phone blocks qualification');
check(count(TLIT_LP_Leads::$saves)===0,'missing phone never reaches status delivery');
check(is_wp_error(TLIT_CB_Qualification::qualify(7,'invented','',9)),'invalid status rejected');
TLIT_CB_Store::$rows[7]['phone']='+447700900123';
TLIT_CB_Qualification::capture(7);
check(count(TLIT_LP_Leads::$syncs)===0,'unqualified capture does not create duplicate lead');
$qualified=TLIT_CB_Qualification::qualify(7,'potential','Ready for callback',9);
check(!is_wp_error($qualified)&&$qualified->lead_status==='potential'&&$qualified->crm_status==='sent','qualification delegates automatic delivery to shared service');
$data=TLIT_LP_Leads::$syncs[0][0];
check($data['request_id']==='cb-conversation-7'&&$data['conversation_id']===7,'canonical stable conversation reference');
check($data['email']===''&&$data['phone']==='+447700900123','optional email and international number retained');
check($data['goal']===$row['goal']&&$data['page_variant']==='v2'&&$data['tracking']['gclid']==='qa-click'&&$data['tracking']['fb_adset_id']==='qa-adset','goal page and campaign attribution retained');
check($data['details']['preferred_language']==='Telugu'&&$data['otp_verified']===false,'language retained without claiming OTP verification');
check(TLIT_CB_Store::$rows[7]['lead_id']===101&&TLIT_CB_Store::$rows[7]['status']==='contacted','canonical link saved without changing review status');
$before=count(TLIT_LP_Leads::$saves);TLIT_CB_Store::$rows[7]['goal']='Grow a business';TLIT_CB_Qualification::capture(7);
check(count(TLIT_LP_Leads::$saves)===$before&&end(TLIT_LP_Leads::$syncs)[0]['goal']==='Grow a business','linked capture refreshes local goal with no status or CRM send');
foreach(array('high_potential','intended_to_join','enrolled','junk','new') as $status){$saved=TLIT_CB_Qualification::qualify(7,$status,'Updated',9);check(!is_wp_error($saved)&&TLIT_LP_Leads::get(101)->lead_status===$status&&end(TLIT_LP_Leads::$saves)[0]===101,'status change reuses same lead: '.$status);}
$token=str_repeat('ab',24);TLIT_CB_Store::$tokens[$token]=7;
$brochure=TLIT_CB_Qualification::brochure($token,array('name'=>'QA Tester','phone'=>'+447700900123','email'=>'qa@example.invalid','goal'=>'Grow a business','otp_verified'=>false,'lead_id'=>999999));
check($brochure['lead_id']===101&&$brochure['download_token']==='proof-101','brochure after qualification reuses canonical lead and download proof');
check(end(TLIT_LP_Leads::$syncs)[1]===101&&!isset(end(TLIT_LP_Leads::$syncs)[0]['lead_id']),'browser supplied lead id excluded');
check(end(TLIT_LP_Leads::$syncs)[0]['source']==='chatbot_brochure','brochure source preserved');
check(TLIT_CB_Qualification::brochure('invalid',array())===null,'unknown session falls back to standalone brochure capture');
TLIT_CB_Store::$error=true;check(is_wp_error(TLIT_CB_Qualification::brochure($token,array())),'database read failure fails closed');TLIT_CB_Store::$error=false;
TLIT_LP_Leads::$fail=true;$before=count(TLIT_LP_Leads::$saves);check(is_wp_error(TLIT_CB_Qualification::qualify(7,'enrolled','',9))&&count(TLIT_LP_Leads::$saves)===$before,'failed local sync prevents delivery');TLIT_LP_Leads::$fail=false;
TLIT_CB_Store::$write_fail=true;check(is_wp_error(TLIT_CB_Qualification::qualify(7,'enrolled','',9))&&count(TLIT_LP_Leads::$saves)===$before,'failed durable link prevents delivery');TLIT_CB_Store::$write_fail=false;
TLIT_CB_Store::$rows[8]=array_replace($row,array('id'=>8,'phone'=>'+919000012345'));
TLIT_CB_Store::$tokens['brochure-first']=8;
$brochure=TLIT_CB_Qualification::brochure('brochure-first',array('email'=>'qa@example.invalid'));
check(!is_wp_error($brochure)&&TLIT_CB_Store::$rows[8]['lead_id']===$brochure['lead_id'],'brochure-first conversation gets durable canonical link');
TLIT_CB_Qualification::qualify(8,'potential','',9);check(end(TLIT_LP_Leads::$syncs)[1]===$brochure['lead_id'],'qualification after brochure reuses same row');
$_SERVER['REQUEST_METHOD']='POST';$_POST=array('id'=>7,'lead_status'=>'potential');
$allowed=false;blocked(array('TLIT_CB_Qualification','save'),403,'nonadmin cannot qualify');$allowed=true;
$_SERVER['REQUEST_METHOD']='GET';blocked(array('TLIT_CB_Qualification','save'),405,'GET cannot qualify');$_SERVER['REQUEST_METHOD']='POST';
$nonce=false;blocked(array('TLIT_CB_Qualification','save'),403,'invalid nonce cannot qualify');$nonce=true;
check(TLIT_CB_Qualification::erase(true,7)===true&&end(TLIT_LP_Leads::$erases)===array(101,7),'privacy erasure includes linked canonical conversation lead');
TLIT_LP_Leads::$erase_ok=false;check(is_wp_error(TLIT_CB_Qualification::erase(true,7)),'failed linked erasure propagates to parent rollback');
ob_start();TLIT_CB_Qualification::panel(TLIT_CB_Store::$rows[7]);$html=ob_get_clean();check(strpos($html,'Save qualification')!==false&&strpos($html,'Email is optional')===false,'qualified admin panel renders');
ob_start();TLIT_CB_Qualification::panel($row);$html=ob_get_clean();check(strpos($html,'Email is optional')!==false&&strpos($html,'No additional')===false,'missing phone has clear explanation without an email requirement');
echo "PASS: chatbot qualification ($checks checks; zero external requests).\n";
