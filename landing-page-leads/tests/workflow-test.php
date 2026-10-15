<?php
// Isolated WordPress / MariaDB integration checks. All HTTP is intercepted.
if (PHP_SAPI !== 'cli') { exit; }
$bootstrap=$argv[1]??'';
if(!is_file($bootstrap)) { fwrite(STDERR, 'Pass the isolated WordPress wp-load.php path.');exit(1); }
require $bootstrap;
if(!defined('WP_HTTP_BLOCK_EXTERNAL')||!WP_HTTP_BLOCK_EXTERNAL||!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['127.0.0.1','localhost'],true)) { throw new RuntimeException('Use an isolated localhost WordPress database with external HTTP blocked.'); }
$checks=0;$made=[];
function lp_check($value,$label){global $checks;if(!$value){throw new RuntimeException($label);} $checks++;echo "PASS $label\n";}
function lp_request($payload){$r=new WP_REST_Request('POST','/tlit-leads/v1/submit');$r->set_header('Content-Type','application/json');$r->set_body(wp_json_encode($payload));return $r;}
function lp_fixture($changes=[]){return array_replace(['request_id'=>wp_generate_uuid4(),'name'=>'Synthetic QA','phone'=>'+447712345678','email'=>'qa@example.test','goal'=>'Grow my business','intent'=>'brochure','page_variant'=>'v3','submission_url'=>'https://lp.techleadsit.com/ai-powered-digital-marketing-v3/?email=secret','tracking'=>['utm_source'=>'google','utm_campaign'=>'qa-only','gclid'=>'qa-click','landing_page'=>'https://lp.techleadsit.com/ai-powered-digital-marketing-v3/?email=secret','utm_id'=>'campaign-123','ga_client_id'=>'100.200','session_id'=>'300','city'=>'Hyderabad','state'=>'Telangana'],'details'=>['student_status'=>'graduated','preferred_call_time'=>'morning'],'marketingConsent'=>false],$changes);}
global $wpdb;$table=TLIT_LP_Leads::table();$old=get_option(TLIT_LP_Leads::OPTION,[]);$calls=0;$mode='success';$last_payload=null;
$hook=function($pre,$args,$url)use(&$calls,&$mode,&$last_payload){if(str_contains($url,'next.telecrm.in')){$calls++;$last_payload=json_decode($args['body'],true);if($mode==='timeout'){return new WP_Error('timeout','mock timeout');}if($mode==='forbidden'){return ['response'=>['code'=>401],'body'=>'{}'];}return ['response'=>['code'=>200],'body'=>'{"success":true}'];}return new WP_Error('no_network','No external calls allowed in this test');};
add_filter('pre_http_request',$hook,10,3);
try {
    lp_check(get_option('tlit_lp_version')==='0.2.0','schema upgraded');
    $p=lp_fixture();$r=TLIT_LP_Leads::ingest($p,['source'=>'brochure']);lp_check(!is_wp_error($r)&&$r['success'],'international lead durably stored');$id=$r['lead_id'];$made[]=$id;
    $row=TLIT_LP_Leads::get($id);lp_check($row->phone==='+447712345678'&&$row->page_variant==='v3','country code and page variant retained');lp_check($row->submission_url==='https://lp.techleadsit.com/ai-powered-digital-marketing-v3/','submission URL removes contact query');
    lp_check($row->lead_status==='new'&&$row->crm_status==='pending'&&$calls===0,'new lead never sends TeleCRM');
    $again=TLIT_LP_Leads::ingest($p,['source'=>'brochure']);lp_check($again['duplicate']&&$again['lead_id']===$id,'ingest retries reuse durable receipt');
    lp_check($r['download_token']===$again['download_token'],'download proof stable on retry');
    lp_check(is_wp_error(TLIT_LP_Leads::download(lp_request(['receipt'=>$r['receipt'],'download_token'=>str_repeat('0',64)]))),'forged brochure-download proof rejected');
    lp_check(TLIT_LP_Leads::download(lp_request(['receipt'=>$r['receipt'],'download_token'=>$r['download_token']]))['success'],'actual brochure click stores with authorised proof');
    $changed=$p;$changed['email']='other@example.test';lp_check(is_wp_error(TLIT_LP_Leads::ingest($changed,['source'=>'brochure'])),'changed idempotency replay rejected');
    lp_check(TLIT_LP_Leads::update_details($id,['details'=>['preferred_call_time'=>'evening'],'brochure_downloaded'=>true])===true,'followup and brochure saved locally');
    $data=json_decode(TLIT_LP_Leads::get($id)->payload,true);lp_check($data['brochure_downloaded']&&$data['tracking']['utm_id']==='campaign-123','brochure and attribution persist together');
    lp_check(!is_wp_error(TLIT_LP_Leads::ingest($p,['source'=>'brochure'])),'followup does not invalidate original replay');
    lp_check(TLIT_LP_Leads::save_settings(['enabled'=>1,'enterprise'=>'qa-enterprise','token'=>'secret-test-token','mapping'=>'{"Remarks":"remarks"}'])===true,'integration saved');
    $saved=get_option(TLIT_LP_Leads::OPTION);lp_check(!isset($saved['token'])&&!str_contains(wp_json_encode($saved),'secret-test-token'),'token encrypted server-side');
    lp_check(!isset(TLIT_LP_Leads::settings()['token'])&&TLIT_LP_Leads::settings()['has_token'],'admin settings never reveal token');
    lp_check(TLIT_LP_Leads::save_settings(['enabled'=>1,'enterprise'=>'qa-enterprise','token'=>'','mapping'=>'{"Remarks":"remarks"}'])===true,'blank token preserves existing');
    lp_check(TLIT_LP_Leads::credentials()['token']==='secret-test-token','preserved token decrypts');
    $test=TLIT_LP_Leads::test_connection();lp_check($test['success']&&!$test['network_tested']&&$calls===0,'configuration test sends no synthetic CRM lead');
    lp_check(is_wp_error(TLIT_LP_Leads::save_settings(['mapping'=>'{"Course Enrollment date":"goal"}'])),'automatic enrollment mapping prohibited');
    lp_check(is_wp_error(TLIT_LP_Leads::save_settings(['mapping'=>'{"phone":"goal"}'])),'required contact fields cannot be remapped');
    TLIT_LP_Leads::save_status($id,'junk','Not qualified',1);lp_check($calls===0&&TLIT_LP_Leads::get($id)->lead_status==='junk','junk saved without CRM');
    TLIT_LP_Leads::save_status($id,'potential','Spoke with learner',1);lp_check($calls===1&&TLIT_LP_Leads::get($id)->crm_status==='sent','explicit potential qualification sends after save');
    lp_check($last_payload['fields']['phone']==='+447712345678','international CRM phone preserved');
    foreach(['Course Name','Lead date','lead_date','leaddate','date','Your preferred time to call','City Name','State Name','Lead Source','Mode of Training','Exp Level','Remarks','gclid','gbraid','wbraid','fbclid','fbp','fbc','gaclientid','sessionid','landingpage','referrer','utmsource','utmmedium','utmcampaign','utmadgroup','utmterm','utmcontent','Facebook Campaign','Facebook Ad','Facebook Ad set Name','Facebook Ad set ID','Facebook Lead ID','utm_id'] as $key) { lp_check(array_key_exists($key,$last_payload['fields']),'CRM contract '.$key); }
    lp_check(str_contains($last_payload['fields']['Remarks'],'Person downloaded brochure')&&str_contains($last_payload['fields']['Remarks'],'v3'),'CRM remarks identify form/download/page');
    TLIT_LP_Leads::save_status($id,'high_potential','Very interested',1);TLIT_LP_Leads::push($id);lp_check($calls===1,'status changes and repeated push do not resend');
    lp_check(count(json_decode(TLIT_LP_Leads::get($id)->review_history,true))===3,'counsellor review history persists');
    $p2=lp_fixture(['page_variant'=>'v2','intent'=>'popup','phone'=>'9000012345']);$r2=TLIT_LP_Leads::ingest($p2,['source'=>'popup','otp_verified'=>true]);$id2=$r2['lead_id'];$made[]=$id2;$mode='timeout';
    TLIT_LP_Leads::save_status($id2,'intended_to_join','Called',1);lp_check($calls===2&&TLIT_LP_Leads::get($id2)->crm_status==='uncertain','timeout becomes uncertain');
    TLIT_LP_Leads::save_status($id2,'enrolled','No duplicates',1);lp_check($calls===2,'uncertain delivery never automatically retries');
    lp_check(TLIT_LP_Leads::reconcile($id2,false)===true,'counsellor confirms missing CRM lead');$mode='success';TLIT_LP_Leads::save_status($id2,'potential','Retry authorised',1);lp_check($calls===3&&TLIT_LP_Leads::get($id2)->crm_status==='sent','confirmed absence permits explicit retry');
    lp_check($last_payload['fields']['phone']==='+919000012345','legacy Indian number gets plus91 once');
    $p3=lp_fixture(['page_variant'=>'v1']);$r3=TLIT_LP_Leads::ingest($p3,['source'=>'general']);$id3=$r3['lead_id'];$made[]=$id3;
    $wpdb->update($table,['crm_status'=>'sending','crm_updated'=>current_time('mysql',true)],['id'=>$id3]);lp_check(is_wp_error(TLIT_LP_Leads::reconcile($id3,false)),'in-flight delivery cannot be reset');
    $wpdb->update($table,['crm_updated'=>gmdate('Y-m-d H:i:s',time()-130)],['id'=>$id3]);lp_check(TLIT_LP_Leads::reconcile($id3,true)===true&&TLIT_LP_Leads::get($id3)->crm_status==='sent','confirmed received marks sent without another request');
    $filtered=TLIT_LP_Leads::query(['utm_campaign'=>'qa-only','page_variant'=>'v3','search'=>'Synthetic QA']);lp_check($filtered['total']>=1,'UTM page and search filters compose');
    lp_check(TLIT_LP_Leads::counts(['utm_campaign'=>'qa-only'])['high_potential']>=1,'status counts use same filters');
    $rejected=TLIT_LP_Leads::ingest(lp_fixture(),['source'=>'general']);$rejected_id=$rejected['lead_id'];$made[]=$rejected_id;
    $wpdb->update($table,['created_at'=>'2026-01-02 04:05:06'],['id'=>$rejected_id]);$mode='forbidden';
    TLIT_LP_Leads::save_status($rejected_id,'potential','Before correction',1);lp_check(TLIT_LP_Leads::get($rejected_id)->crm_status==='failed','definite provider rejection is retryable');
    $corrected=TLIT_LP_Leads::save_settings(['enabled'=>1,'enterprise'=>'qa-enterprise','mapping'=>'{"Remarks":"remarks","Lead qualification":"lead_status"}']);lp_check($corrected===true,'mapping correction saved after rejection');
    $mode='success';TLIT_LP_Leads::save_status($rejected_id,'high_potential','After correction',1);
    lp_check(($last_payload['fields']['Lead qualification']??'')==='High potential'&&str_contains($last_payload['fields']['Remarks'],'After correction'),'definitely rejected payload rebuilds from corrected mapping and review');
    foreach(['Lead date','lead_date','leaddate','date'] as $date_key) { lp_check($last_payload['fields'][$date_key]==='2026-01-02 09:35:06','original capture date retained in '.$date_key); }
    $legacy=lp_fixture(['phone'=>'9000098765','page_variant'=>'','submission_url'=>'','tracking'=>['utm_source'=>'legacy','referrer'=>'','landing_page'=>'https://lp.techleadsit.com/ai-powered-digital-marketing-v2/?utm_source=legacy']]);
    $legacy_data=['goal'=>$legacy['goal'],'marketingConsent'=>false,'consent_text'=>'Course news and marketing messages on WhatsApp and email; opt out anytime.','request_permission'=>'Contact requested about course enquiry or brochure','tracking'=>$legacy['tracking'],'status'=>'','timeline'=>'','question'=>''];
    $legacy_hash=TLIT_LP_Leads::hash(wp_json_encode([$legacy['name'],$legacy['phone'],$legacy['email'],'brochure',$legacy_data]));
    $wpdb->insert($table,['request_id'=>$legacy['request_id'],'fingerprint'=>$legacy_hash,'created_at'=>current_time('mysql',true),'updated_at'=>current_time('mysql',true),'name'=>$legacy['name'],'phone'=>'+91'.$legacy['phone'],'email'=>$legacy['email'],'source'=>'brochure','payload'=>wp_json_encode($legacy_data),'advisor_note'=>'']);$legacy_id=(int)$wpdb->insert_id;$made[]=$legacy_id;
    delete_option('tlit_lp_sources_cursor');$backfilled=TLIT_LP_Leads::backfill_sources();
    lp_check(TLIT_LP_Leads::get($legacy_id)->page_variant==='v2'&&TLIT_LP_Leads::get($legacy_id)->submission_url==='https://lp.techleadsit.com/ai-powered-digital-marketing-v2/','legacy attribution backfills source page and filterable variant');
    lp_check(TLIT_LP_Leads::variant('','https://example.test/unrelated/')==='unknown','source backfill does not guess unknown pages');
    $legacy_result=TLIT_LP_Leads::ingest($legacy,['source'=>'brochure']);lp_check(!is_wp_error($legacy_result)&&$legacy_result['lead_id']===$legacy_id&&$legacy_result['duplicate'],'pre-upgrade V2 receipt replays without duplicate');
    $legacy['email']='altered@example.test';lp_check(is_wp_error(TLIT_LP_Leads::ingest($legacy,['source'=>'brochure'])),'pre-upgrade altered replay still rejected');
    $lock=TLIT_LP_Leads::lock('lead-review-'.$id);lp_check(is_wp_error(TLIT_LP_Leads::save_status($id,'junk','Concurrent',2)),'concurrent counsellor changes fail closed');delete_option($lock);
    $plain=['name'=>'Synthetic QA','phone'=>'9'.random_int(100000000,999999999),'email'=>'qa@example.test','intent'=>'brochure','goal'=>'Get my first marketing job','request_id'=>wp_generate_uuid4(),'page_variant'=>'v2','tracking'=>['utm_source'=>'qa'],'marketingConsent'=>false];$_SERVER['REMOTE_ADDR']='landing-workflow-test-'.random_int(1,1000000);
    $rest=TLIT_LP_Leads::submit(lp_request($plain));lp_check(!is_wp_error($rest)&&$rest['success'],'existing V2 brochure REST contract works');$made[]=$rest['lead_id'];lp_check(TLIT_LP_Leads::submit(lp_request($plain))['lead_id']===$rest['lead_id'],'REST replay returns same receipt');
    $plain['intent']='popup';$plain['request_id']=wp_generate_uuid4();lp_check(is_wp_error(TLIT_LP_Leads::submit(lp_request($plain))),'popup still requires OTP');
    $otp=TLIT_LP_Leads::otp(lp_request($plain));lp_check(!is_wp_error($otp)&&isset($otp['challenge']),'existing OTP route issues challenge');$plain['challenge']=$otp['challenge'];$plain['otp']='0000';lp_check(is_wp_error(TLIT_LP_Leads::submit(lp_request($plain))),'wrong OTP rejected');$plain['otp']=$GLOBALS['tlit_local_test_code'];
    $verified=TLIT_LP_Leads::submit(lp_request($plain));lp_check(!is_wp_error($verified)&&$verified['success'],'verified popup saves to Bridge');$made[]=$verified['lead_id'];$plain['request_id']=wp_generate_uuid4();lp_check(is_wp_error(TLIT_LP_Leads::submit(lp_request($plain))),'consumed OTP cannot create duplicate');
    $rest_request=lp_request([]);$rest_request->set_header('origin','https://wrong.example');lp_check(is_wp_error(TLIT_LP_Leads::permission($rest_request)),'foreign-origin REST blocked');
    $fail_filter=function($sql){return str_starts_with($sql,'INSERT INTO `'.TLIT_LP_Leads::table().'`')?'INSERT INTO nonexistent_tlit_failclosed VALUES (1)':$sql;};add_filter('query',$fail_filter);$failure=TLIT_LP_Leads::ingest(lp_fixture(),['source'=>'brochure']);remove_filter('query',$fail_filter);lp_check(is_wp_error($failure),'failed durable insert returns error instead of success');lp_check($calls===5,'capture paths never trigger external delivery');
    echo "ALL $checks checks passed; actual isolated WordPress/MariaDB; all network mocked.\n";
} finally {
    remove_filter('pre_http_request',$hook,10);update_option(TLIT_LP_Leads::OPTION,$old,false);foreach($made as $id){$wpdb->delete($table,['id'=>$id]);}
}
