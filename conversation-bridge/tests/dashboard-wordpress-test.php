<?php
/** Real local WordPress/MariaDB tests. All outbound requests are intercepted. */
if (PHP_SAPI !== 'cli') { exit; }
function tlit_dm_crm_url() { return 'https://next.telecrm.in/api/b1/enterprise/qa-dashboard-only/autoupdatelead'; }
require $argv[1];
if (!WP_HTTP_BLOCK_EXTERNAL || home_url() !== 'http://127.0.0.1:4187') { throw new RuntimeException('Use the isolated localhost WordPress runtime.'); }
TLIT_CB_Lead_Ops::install(); TLIT_CB_Team_Access::install(); TLIT_LP_Leads::install(); TLIT_Teams_Notifications::install();
$checks=0;$run=bin2hex(random_bytes(5));$forms=array();$chats=array();$refs=array();$sessions=array();$calls=0;
$old_members=get_option('tlit_cb_team_members',array());$old_access=TLIT_CB_Team_Access::options();
add_filter('pre_http_request',function($pre,$args,$url) use (&$calls){if(str_starts_with($url,'https://next.telecrm.in/')){++$calls;return array('headers'=>array(),'response'=>array('code'=>200),'body'=>'{"success":true,"leadId":"dashboard-qa"}');}return new WP_Error('local_only','No external requests in local tests.');},PHP_INT_MAX,3);
function dash_check($test,$label){if(!$test){throw new RuntimeException('FAIL: '.$label);}++$GLOBALS['checks'];echo 'PASS '.$label."\n";}
function dash_form($source,$variant,$tracking=array()){
    global $run,$forms,$refs;
    $r=TLIT_LP_Leads::ingest(array('request_id'=>wp_generate_uuid4(),'name'=>'Dashboard QA '.$run,'phone'=>'+447700900123','email'=>'qa@example.invalid','intent'=>$source,'page_variant'=>$variant,'submission_url'=>home_url('/ai-powered-digital-marketing/'),'goal'=>'First marketing job','tracking'=>array_merge(array('utm_campaign'=>$run),$tracking)),array('source'=>$source));
    if(is_wp_error($r)){throw new RuntimeException($r->get_error_message());}$forms[]=$r['lead_id'];$refs[]='LP-'.$r['lead_id'];return $r['lead_id'];
}
try {
    dash_check(TLIT_CB_Dashboard_Data::ready(),'schema is installed');
    TLIT_CB_Lead_Ops::save_members("QA A\nQA B\nQA A");$members=TLIT_CB_Lead_Ops::members();$keys=array_keys($members);
    dash_check(count($members)===2,'roster deduplicates names and preserves stable keys');
    $a=dash_form('general','v1',array('utm_source'=>'google','utm_medium'=>'cpc','gclid'=>'qa'));
    $b=dash_form('popup','v2',array('utm_source'=>'facebook','utm_medium'=>'paid_social'));
    $c=dash_form('brochure','v3',array('utm_source'=>'google','utm_medium'=>'organic'));
    $filtered=array('campaign'=>$run);$q=TLIT_CB_Dashboard_Data::query($filtered);
    dash_check(!is_wp_error($q)&&$q['total']===3,'all three form sources appear in one inbox');
    $row=TLIT_CB_Dashboard_Data::record('LP-'.$a);
    dash_check($row['traffic']==='google_ads'&&$row['viewed']==='unopened'&&$row['owner_key']==='','new lead has independent unviewed and unassigned states');
    dash_check(TLIT_CB_Dashboard_Data::query(array_merge($filtered,array('variant'=>'v2','source'=>'popup')))['total']===1,'page and capture source filters intersect');
    dash_check(TLIT_CB_Dashboard_Data::query(array_merge($filtered,array('traffic'=>'organic')))['total']===1,'organic attribution is separate from paid Google traffic');
    dash_check(TLIT_CB_Dashboard_Data::query(array('search'=>"' OR 1=1 --",'campaign'=>$run))['total']===0,'search text cannot escape prepared filters');
    dash_check(TLIT_CB_Dashboard_Data::query(array_merge($filtered,array('from'=>'2026-02-31')))['total']===0,'invalid dates fail closed');
    global $wpdb;$wpdb->update(TLIT_LP_Leads::table(),array('created_at'=>'2026-10-01 18:45:00'),array('id'=>$b));
    dash_check(TLIT_CB_Dashboard_Data::query(array_merge($filtered,array('from'=>'2026-10-02','to'=>'2026-10-02')))['total']===1,'IST date filtering includes previous UTC evening');
    dash_check(TLIT_CB_Dashboard_Data::record('LP-'.$b)['viewed']==='unknown','historical lead never falsely says it was not viewed');
    TLIT_CB_Team_Access::save_settings(array('enabled'=>1,'days'=>7));$invite=TLIT_CB_Team_Access::issue('LP-'.$a);parse_str(wp_parse_url($invite['url'],PHP_URL_QUERY),$parts);$grant=TLIT_CB_Team_Access::find($parts['tlit_team_bridge']);
    $bad=TLIT_CB_Team_Access::login($grant,'not-a-pin','qa-'.$run);
    dash_check(is_wp_error($bad)&&TLIT_CB_Lead_Ops::state('LP-'.$a)['view_count']===0,'failed PIN never marks a lead opened');
    $session=TLIT_CB_Team_Access::login($grant,$invite['pin'],'qa-'.$run);$sessions[]=$session['token'];
    dash_check(TLIT_CB_Lead_Ops::viewed($grant,$session),'authorized PIN opening is saved');
    TLIT_CB_Lead_Ops::viewed($grant,$session);$row=TLIT_CB_Dashboard_Data::record('LP-'.$a);
    dash_check($row['viewed']==='opened'&&(int)$row['view_count']===1&&$row['owner_key']==='','refresh deduplicates the PIN session and leaves lead unassigned');
    $before=$calls;
    dash_check(is_wp_error(TLIT_CB_Team_Access::claim($grant,$session['token'],array('csrf'=>'wrong','member'=>$keys[0],'version'=>0))),'claim requires the current scoped CSRF token');
    $claimed=TLIT_CB_Team_Access::claim($grant,$session['token'],array('csrf'=>$session['csrf'],'member'=>$keys[0],'version'=>0,'lead_id'=>$b));
    dash_check($claimed===true&&TLIT_CB_Lead_Ops::state('LP-'.$a)['owner_name']==='QA A'&&TLIT_CB_Lead_Ops::state('LP-'.$b)['owner_key']==='','PIN claim ignores submitted cross-lead IDs');
    dash_check(is_wp_error(TLIT_CB_Lead_Ops::assign('LP-'.$a,$keys[1],'QA B',0,true)),'competing claim cannot steal an assigned lead');
    dash_check(TLIT_CB_Lead_Ops::assign('LP-'.$a,$keys[1],'QA Admin (WordPress)',1)===true,'administrator can explicitly reassign with current version');
    dash_check(is_wp_error(TLIT_CB_Lead_Ops::assign('LP-'.$a,'','QA Admin',1)),'stale assignment forms cannot overwrite newer ownership');
    dash_check($calls===$before&&TLIT_LP_Leads::get($a)->lead_status==='new'&&TLIT_LP_Leads::get($a)->crm_status==='pending','views and assignments make zero CRM calls and do not qualify');
    dash_check(TLIT_CB_Dashboard_Data::query(array_merge($filtered,array('owner'=>$keys[1],'viewed'=>'opened')))['total']===1,'owner and PIN filters reflect recorded activity');
    dash_check(count(TLIT_CB_Lead_Ops::history('LP-'.$a))===4,'received, PIN open and both assignments are in activity history');
    $fault=function($sql){return str_starts_with($sql,'INSERT IGNORE INTO '.TLIT_CB_Lead_Ops::table(true))?'INSERT INTO missing_dashboard_atomicity_fixture (id) VALUES (1)':$sql;};
    add_filter('query',$fault);try{$failed=TLIT_CB_Lead_Ops::assign('LP-'.$a,'','QA Admin',2);}finally{remove_filter('query',$fault);}
    dash_check(is_wp_error($failed)&&TLIT_CB_Lead_Ops::state('LP-'.$a)['owner_key']===$keys[1],'audit failure rolls back ownership atomically');
    dash_check(is_wp_error(TLIT_CB_Lead_Ops::assign('LP-'.$a,'removed-member','QA Admin',2)),'invalid or removed member cannot be assigned');
    TLIT_LP_Leads::save_status($a,'junk','Local QA',0);
    dash_check($calls===$before&&TLIT_LP_Leads::get($a)->crm_status==='pending','Junk counsellor review stays in WordPress');
    TLIT_LP_Leads::save_status($a,'potential','Local QA',0);
    dash_check($calls===$before+1&&TLIT_LP_Leads::get($a)->crm_status==='sent','qualified review alone triggers the existing TeleCRM flow');
    dash_check(count(array_filter(TLIT_CB_Lead_Ops::history('LP-'.$a),function($r){return $r['kind']==='review';}))===2,'both counsellor reviews are audited');
    $token=bin2hex(random_bytes(24));$cid=TLIT_CB_Store::capture(array('session'=>$token,'event_id'=>'intro-'.$run,'course'=>'digital_marketing','contact'=>array('name'=>'Chat QA '.$run,'phone'=>'+447700900124'),'source_url'=>home_url('/ai-powered-digital-marketing-course/'),'tracking'=>array('utm_campaign'=>$run),'messages'=>array()));$chats[]=$cid;$refs[]='CB-'.$cid;
    dash_check(TLIT_CB_Dashboard_Data::query($filtered)['total']===4,'captured chatbot lead joins form leads');
    $r=TLIT_CB_Qualification::qualify($cid,'potential','Local QA',0);$chat=TLIT_CB_Store::get($cid);$forms[]=$chat['lead_id'];
    dash_check(!is_wp_error($r)&&TLIT_CB_Dashboard_Data::query($filtered)['total']===4,'chat-to-LP handoff is not counted twice');
    dash_check(TLIT_CB_Lead_Ops::reference('form',$chat['lead_id'])==='CB-'.$cid,'linked form reviews resolve to canonical chat history');
    $overview=TLIT_CB_Dashboard_Data::overview($filtered);
    dash_check(!is_wp_error($overview)&&(int)$overview['stats']['total']===4&&(int)$overview['stats']['synced']===2,'aggregate totals match filtered canonical rows');
    require_once dirname(__DIR__).'/includes/team-view.php';ob_start();TLIT_CB_Team_View::detail($grant,(array)TLIT_LP_Leads::get($a),$session,$invite['url'],'',false);$html=ob_get_clean();
    dash_check(str_contains($html,'QA B')&&!str_contains($html,'name="operation" value="claim"'),'private lead page shows owner without offering to steal assignment');
    dash_check(!str_contains(wp_json_encode(TLIT_CB_Lead_Ops::history('LP-'.$a)),$invite['pin'])&&!str_contains(wp_json_encode(TLIT_CB_Lead_Ops::history('LP-'.$a)),$parts['tlit_team_bridge']),'activity never stores PIN or private URL');
    $result=TLIT_CB_Lead_Ops::erase(true,$cid);dash_check($result===true&&!TLIT_CB_Lead_Ops::history('CB-'.$cid),'privacy erase removes corresponding operations history');
    echo 'PASS '.$checks." dashboard checks.\n";
} finally {
    foreach($sessions as $s){TLIT_CB_Team_Access::logout($s);}foreach($refs as $ref){$wpdb->delete(TLIT_CB_Lead_Ops::table(),array('reference'=>$ref));$wpdb->delete(TLIT_CB_Lead_Ops::table(true),array('reference'=>$ref));$wpdb->delete(TLIT_CB_Team_Access::table(),array('identity_key'=>$ref));$wpdb->delete(TLIT_Teams_Notifications::table(),array('identity_key'=>$ref));}
    foreach($chats as $id){foreach(array('messages','events','conversations') as $t){$wpdb->delete($wpdb->prefix.'tlit_cb_'.$t,array($t==='conversations'?'id':'conversation_id'=>$id));}}
    foreach($forms as $id){$wpdb->delete(TLIT_LP_Leads::table(),array('id'=>$id));}update_option('tlit_cb_team_members',$old_members,false);update_option(TLIT_CB_Team_Access::OPTION,$old_access,false);
}
