<?php
/** AJAX permissions and input rejection. No database or network is available. */
if(PHP_SAPI!=='cli') { http_response_code(404);exit; }
define('ABSPATH',__DIR__.'/');
class WP_Error {
    private $message; private $data;
    function __construct($code,$message,$data=null) { $this->message=$message;$this->data=$data; }
    function get_error_message() { return $this->message; }
    function get_error_data() { return $this->data; }
}
class Review_Response extends RuntimeException {
    public $data;
    function __construct($data,$status) { parent::__construct('', $status);$this->data=$data; }
}
function add_action(...$args) {}
function add_filter(...$args) {}
function current_user_can($capability) { return $GLOBALS['review_allowed'] && $capability==='manage_options'; }
function check_ajax_referer(...$args) { return $GLOBALS['review_nonce']; }
function wp_send_json_error($data,$status=400) { throw new Review_Response($data,$status); }
function wp_send_json_success($data) { throw new Review_Response($data,200); }
function wp_unslash($value) { return $value; }
function absint($value) { return abs((int)$value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_-]/i','',$value)); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function get_current_user_id() { return 1; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_post() { throw new RuntimeException('No HTTP request is allowed.'); }
require dirname(__DIR__).'/landing-page-leads.php';
function rejected_review($status,$label) {
    try { TLIT_LP_Leads::ajax_review(); }
    catch(Review_Response $response) { if($response->getCode()!==$status) { throw new RuntimeException($label); }return; }
    throw new RuntimeException('Missing response: '.$label);
}
$GLOBALS['review_allowed']=false;$GLOBALS['review_nonce']=true;
$_SERVER['REQUEST_METHOD']='POST';$_POST=[];
rejected_review(403,'Non-admins cannot save reviews.');
$GLOBALS['review_allowed']=true;$_SERVER['REQUEST_METHOD']='GET';
rejected_review(405,'GET cannot mutate a review.');
$_SERVER['REQUEST_METHOD']='POST';$GLOBALS['review_nonce']=false;
rejected_review(403,'An invalid nonce cannot mutate a review.');
$GLOBALS['review_nonce']=true;$_POST=['id'=>1,'lead_status'=>'potential','note'=>['invalid']];
rejected_review(400,'Structured call notes must be rejected.');
$_POST=['id'=>1,'sync_call_notes'=>'1','note'=>'  '];
rejected_review(400,'Blank notes cannot trigger delivery.');
$_POST=['id'=>1,'lead_status'=>['invalid'],'note'=>'Local fixture'];
rejected_review(400,'Structured qualification must be rejected.');
$_POST=['id'=>1,'lead_status'=>'not-a-status','note'=>'Local fixture'];
rejected_review(400,'Unknown qualification must be rejected.');
$_POST=['id'=>[1],'lead_status'=>'potential','note'=>'Local fixture'];
rejected_review(400,'Structured lead IDs must be rejected.');
$_POST=['id'=>1,'operation'=>'resolve','lead_status'=>'potential','note'=>'Local fixture'];
rejected_review(400,'The review endpoint must not execute another form action.');
$_POST=['id'=>1,'sync_call_notes'=>['1'],'lead_status'=>'potential','note'=>'Local fixture'];
rejected_review(400,'Structured button actions must be rejected.');
echo "PASS: AJAX capability, method, nonce and input checks reject invalid requests before any database or HTTP call.\n";
