<?php
/** Isolated WordPress routing regression. No requests to live sites or providers. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$child=($argv[1]??'')==='--request';
$bootstrap=$argv[$child?2:1]??'';
if (!is_file($bootstrap)) { fwrite(STDERR,"Pass an isolated WordPress wp-load.php path.\n");exit(1); }
define('TLIT_ROUTING_TEST',true);
$bootstrap_complete=false;
register_shutdown_function(function()use(&$bootstrap_complete){if(!$bootstrap_complete){fwrite(STDERR,"WordPress bootstrap failed before routing checks ran.\n");exit(1);}});
require $bootstrap;
$bootstrap_complete=true;
if (!defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL || !in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('localhost','127.0.0.1'),true)) {
    throw new RuntimeException('Use isolated localhost WordPress with external requests blocked.');
}
require_once dirname(__DIR__,2).'/techleadsit.php';
if ($child) {
    $_SERVER['REQUEST_URI']=$argv[3];
    $_SERVER['REQUEST_METHOD']=$argv[4]??'GET';
    $host=wp_parse_url(home_url());
    $_SERVER['HTTP_HOST']=$host['host'].(isset($host['port'])?':'.$host['port']:'');
    global $wp_query;
    $wp_query=new WP_Query();
    $wp_query->set_404();
    $wp_query->query_vars['name']=trim((string)wp_parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/');
    add_filter('wp_redirect',function($url){throw new RuntimeException('Unexpected redirect: '.$url);});
    register_shutdown_function(function(){fwrite(STDERR, 'ROUTE_STATUS:'.(int)http_response_code()."\n");});
    do_action('template_redirect');
    throw new RuntimeException('The owned landing-page route was not served.');
}
$checks=0;$created=array();$saved_options=array();
function routing_check($ok,$label) { if(!$ok)throw new RuntimeException('FAIL: '.$label);++$GLOBALS['checks'];echo "PASS $label\n"; }
function routing_request($bootstrap,$path,$method='GET') {
    $pipes=array();
    $process=proc_open(array(PHP_BINARY,__FILE__,'--request',$bootstrap,$path,$method),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes,dirname(__DIR__,2));
    if(!is_resource($process))throw new RuntimeException('Cannot start isolated routing request.');
    fclose($pipes[0]);$html=stream_get_contents($pipes[1]);fclose($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($process);
    routing_check($exit===0&&trim($errors)==='ROUTE_STATUS:200','HTTP 200 without WordPress redirect: '.$method.' '.$path.' '.($exit!==0?$errors:''));
    return $html;
}
try {
    wp_set_current_user(1);
    routing_check(current_user_can('manage_options'),'isolated test administrator available');
    $early=has_action('template_redirect','techleadsit_route_digital_marketing_early');
    routing_check($early!==false&&$early<has_action('template_redirect','redirect_canonical')&&$early<has_action('template_redirect','wp_old_slug_redirect'),'Digital Marketing routes precede both native WordPress redirect callbacks');
    $slugs=array('v1'=>'ai-powered-digital-marketing','v2'=>'ai-powered-digital-marketing-course','v3'=>'ai-powered-digital-marketing-training');
    $before=array();
    foreach($slugs as $variant=>$slug) {
        $option='tlit_dm_'.$variant.'_page_registered';$saved_options[$option]=get_option($option,null);
        $before[$variant]=get_page_by_path($slug,OBJECT,'page');
        // Simulate the next admin visit after an update, without disturbing content.
        delete_option($option);
    }
    tlit_dm_v2_register_page();$ids=array();
    foreach($slugs as $variant=>$slug) {
        $page=get_page_by_path($slug,OBJECT,'page');
        if(!$before[$variant]&&$page)$created[]=$page->ID;
        routing_check($page&&$page->post_type==='page','WordPress Pages contains '.$variant);
        if($before[$variant])routing_check($page->ID===$before[$variant]->ID&&$page->post_content===$before[$variant]->post_content&&$page->post_status===$before[$variant]->post_status,'existing '.$variant.' content and publication state preserved');
        else routing_check($page->post_status==='publish','new '.$variant.' entry published');
        $ids[$variant]=$page->ID;
    }
    routing_check(count(array_unique($ids))===3,'all three variants have distinct WordPress page IDs');
    tlit_dm_v2_register_page();
    foreach($slugs as $variant=>$slug)routing_check(get_page_by_path($slug,OBJECT,'page')->ID===$ids[$variant],'repeat admin visit reuses '.$variant.' page');
    $titles=array('v1'=>'AI Digital Marketing for Freshers','v2'=>'AI Powered Digital Marketing Course','v3'=>'AI Powered Digital Marketing Training');
    $bodies=array();
    foreach($slugs as $variant=>$slug) {
        $html=routing_request($bootstrap,'/'.$slug.'/');$bodies[$variant]=$html;
        routing_check(strpos($html,$titles[$variant])!==false,'correct '.$variant.' template rendered');
        routing_check(strpos($html,'"pageVariant":"'.$variant.'"')!==false,'correct '.$variant.' lead attribution injected');
        routing_check(strpos($html,'noindex')!==false,'ads-only noindex retained for '.$variant);
    }
    routing_check(count(array_unique(array_map('sha256_hash',$bodies)))===3,'three URLs serve distinct designs');
    routing_check(strpos(routing_request($bootstrap,'/ai-powered-digital-marketing'),'AI Digital Marketing for Freshers')!==false,'V1 without trailing slash keeps its design');
    routing_check(strpos(routing_request($bootstrap,'/ai-powered-digital-marketing/?utm_source=qa&gclid=qa-click'),'AI Digital Marketing for Freshers')!==false,'V1 with advertising query parameters keeps its design');
    routing_check(routing_request($bootstrap,'/ai-powered-digital-marketing/','HEAD')==='','HEAD returns 200 without a response body');
    routing_check(strpos(routing_request($bootstrap,'/ai-powered-digital-marketing-course/thank-you/'),'Thank you')!==false,'V2 thank-you route still works');
    routing_check(strpos(routing_request($bootstrap,'/ai-powered-digital-marketing-training/thank-you/'),'Thank you')!==false,'V3 thank-you route still works');
    $_SERVER['REQUEST_URI']='/ordinary-wordpress-page/';
    ob_start();techleadsit_route_digital_marketing_early();$ordinary=ob_get_clean();
    routing_check($ordinary===''&&has_action('template_redirect','redirect_canonical')===10,'ordinary WordPress pages retain native canonical routing');
    echo "ALL $checks isolated WordPress routing checks passed; no provider requests.\n";
} finally {
    foreach($created as $id)wp_delete_post($id,true);
    foreach($saved_options as $key=>$value){if($value===null)delete_option($key);else update_option($key,$value,false);}
}
function sha256_hash($body){return hash('sha256',$body);}
