<?php
// Isolated WordPress / MariaDB integration checks. All HTTP is intercepted.
if (PHP_SAPI !== 'cli') { exit; }
$bootstrap=$argv[1]??'';
if(!is_file($bootstrap)) { fwrite(STDERR, 'Pass the isolated WordPress wp-load.php path.');exit(1); }
require $bootstrap;
if(!defined('WP_HTTP_BLOCK_EXTERNAL')||!WP_HTTP_BLOCK_EXTERNAL||!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['127.0.0.1','localhost'],true)) { throw new RuntimeException('Use an isolated localhost WordPress database with external HTTP blocked.'); }
if(!function_exists('tlit_dm_crm_url')) { function tlit_dm_crm_url(){return $GLOBALS['lp_test_existing_url']??'';} }
$GLOBALS['lp_test_existing_url']='https://next.telecrm.in/api/b1/enterprise/qa-existing-enterprise/autoupdatelead';
$checks=0;$made=[];
function lp_check($value,$label){global $checks;if(!$value){throw new RuntimeException($label);} $checks++;echo "PASS $label\n";}
function lp_request($payload){$r=new WP_REST_Request('POST','/tlit-leads/v1/submit');$r->set_header('Content-Type','application/json');$r->set_body(wp_json_encode($payload));return $r;}
function lp_fixture($changes=[]){return array_replace(['request_id'=>wp_generate_uuid4(),'name'=>'Synthetic QA','phone'=>'+447712345678','email'=>'qa@example.test','goal'=>'Grow my business','intent'=>'brochure','page_variant'=>'v3','submission_url'=>'https://lp.techleadsit.com/ai-powered-digital-marketing-v3/?email=secret','tracking'=>['utm_source'=>'google','utm_campaign'=>'qa-only','gclid'=>'qa-click','landing_page'=>'https://lp.techleadsit.com/ai-powered-digital-marketing-v3/?email=secret','utm_id'=>'campaign-123','ga_client_id'=>'100.200','session_id'=>'300','city'=>'Hyderabad','state'=>'Telangana'],'details'=>['student_status'=>'graduated','preferred_call_time'=>'morning'],'marketingConsent'=>false],$changes);}
global $wpdb;$table=TLIT_LP_Leads::table();$old=get_option(TLIT_LP_Leads::OPTION,[]);$calls=0;$mode='success';$last_payload=null;$last_headers=[];$last_url='';$during_send=null;
$hook=function($pre,$args,$url)use(&$calls,&$mode,&$last_payload,&$last_headers,&$last_url,&$during_send){if(str_contains($url,'next.telecrm.in')){$calls++;$last_payload=json_decode($args['body'],true);$last_headers=$args['headers'];$last_url=$url;if(is_callable($during_send)){$callback=$during_send;$during_send=null;$callback();}if($mode==='timeout'){return new WP_Error('timeout','mock timeout');}if($mode==='forbidden'){return ['response'=>['code'=>401],'body'=>'{}'];}if($mode==='body_error'){return ['response'=>['code'=>200],'body'=>'{"status":"Error","errorMessage":"Mock rejection"}'];}if($mode==='legacy_error'){return ['response'=>['code'=>200],'body'=>'{"status":"Success","errorString":"Mock rejection"}'];}if($mode==='created'){return ['response'=>['code'=>200],'body'=>'{"index":0,"uniqueIdentifier":"447712345678","modifiedLeadIds":["0123456789abcdef01234567"],"status":"Created","errorMessage":""}'];}if($mode==='updated'){return ['response'=>['code'=>200],'body'=>'[{"index":0,"uniqueIdentifier":"447712345678","modifiedLeadIds":["0123456789abcdef01234567"],"status":"Updated","errorMessage":""}]'];}if($mode==='unknown'){return ['response'=>['code'=>200],'body'=>'{}'];}return ['response'=>['code'=>200],'body'=>'{"success":true,"leadId":"mock-same-lead-123"}'];}return new WP_Error('no_network','No external calls allowed in this test');};
add_filter('pre_http_request',$hook,10,3);
try {
    lp_check(get_option('tlit_lp_version')===TLIT_LP_Leads::VERSION,'schema upgraded');
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
    update_option(TLIT_LP_Leads::OPTION,['enabled'=>0,'reuse_existing'=>0,'enterprise'=>'obsolete-enterprise','token'=>'obsolete-token','mapping'=>['Remarks'=>'remarks','phone'=>'goal','Course Enrollment date'=>'goal','status'=>'goal']],false);
    lp_check(TLIT_LP_Leads::credentials()['enterprise']==='qa-existing-enterprise','existing connection authoritative despite disabled legacy flags');
    lp_check(!isset(TLIT_LP_Leads::settings()['token'])&&TLIT_LP_Leads::settings()['enabled'],'connection status never reveals token');
    lp_check(is_wp_error(TLIT_LP_Leads::save_settings(['enabled'=>0,'enterprise'=>'replacement'])),'obsolete settings cannot change or disable existing connection');
    $test=TLIT_LP_Leads::test_connection();lp_check($test['success']&&!$test['network_tested']&&$calls===0,'configuration test sends no synthetic CRM lead');
    lp_check(is_wp_error(TLIT_LP_Leads::save_settings(['mapping'=>'{"Course Enrollment date":"goal"}'])),'automatic enrollment mapping prohibited');
    lp_check(is_wp_error(TLIT_LP_Leads::save_settings(['mapping'=>'{"phone":"goal"}'])),'required contact fields cannot be remapped');
    TLIT_LP_Leads::save_status($id,'junk','Not qualified',1);lp_check($calls===0&&TLIT_LP_Leads::get($id)->lead_status==='junk','junk saved without CRM');
    $during_send=function()use($id){lp_check(TLIT_LP_Leads::push($id)===false,'concurrent push cannot duplicate in-flight send');lp_check(is_wp_error(TLIT_LP_Leads::save_status($id,'enrolled','Concurrent',2)),'concurrent review cannot overtake in-flight send');};
    TLIT_LP_Leads::save_status($id,'potential','Spoke with learner',1);lp_check($calls===1&&TLIT_LP_Leads::get($id)->crm_status==='sent','explicit potential qualification sends after save');$first_key=$last_headers['Idempotency-Key'];
    lp_check($last_url===$GLOBALS['lp_test_existing_url'],'only existing endpoint receives lead');
    lp_check($last_payload['fields']['status']==='50 % Potential','potential updates native TeleCRM status');
    lp_check(TLIT_LP_Leads::get($id)->crm_remote_id==='mock-same-lead-123','returned CRM identifier retained safely');
    lp_check(!isset($last_payload['fields']['Course Enrollment date']),'stored legacy mapping cannot set enrollment date');
    lp_check($last_payload['fields']['phone']==='+447712345678','international CRM phone preserved');
    foreach(['Course Name','Lead date','lead_date','leaddate','date','Your preferred time to call','City Name','State Name','Lead Source','Mode of Training','Exp Level','Remarks','gclid','gbraid','wbraid','fbclid','fbp','fbc','gaclientid','sessionid','landingpage','referrer','utmsource','utmmedium','utmcampaign','utmadgroup','utmterm','utmcontent','Facebook Campaign','Facebook Ad','Facebook Ad set Name','Facebook Ad set ID','Facebook Lead ID','utm_id'] as $key) { lp_check(array_key_exists($key,$last_payload['fields']),'CRM contract '.$key); }
    lp_check(str_contains($last_payload['fields']['Remarks'],'Person downloaded brochure')&&str_contains($last_payload['fields']['Remarks'],'v3'),'CRM remarks identify form/download/page');
    lp_check($last_payload['fields']['remarks']===$last_payload['fields']['Remarks'],'remarks includes actual workspace API name');
    lp_check($last_payload['fields']['lead_source_1']==='Google ads'&&!isset($last_payload['fields']['facebook_ad_set_id'])&&!isset($last_payload['fields']['facebook_lead_id']),'actual dropdown label and omitted empty numeric aliases follow workspace schema');
    TLIT_LP_Leads::save_status($id,'high_potential','Very interested',1);lp_check($calls===2&&$last_payload['fields']['status']==='60-80 Potential','changed qualification updates same TeleCRM phone');$second_key=$last_headers['Idempotency-Key'];
    lp_check($first_key!==$second_key,'new qualification gets a new idempotency revision');
    lp_check(array_keys($last_payload['fields'])===['phone','Remarks','remarks','status'],'subsequent sync preserves CRM contact enrichment');
    TLIT_LP_Leads::push($id);TLIT_LP_Leads::save_status($id,'high_potential','Very interested',1);lp_check($calls===2,'unchanged review and repeated push send nothing');
    lp_check(count(json_decode(TLIT_LP_Leads::get($id)->review_history,true))===4,'counsellor review history persists');
    $p2=lp_fixture(['page_variant'=>'v2','intent'=>'popup','phone'=>'9000012345']);$r2=TLIT_LP_Leads::ingest($p2,['source'=>'popup','otp_verified'=>true]);$id2=$r2['lead_id'];$made[]=$id2;$mode='timeout';
    TLIT_LP_Leads::save_status($id2,'intended_to_join','Called',1);lp_check($calls===3&&TLIT_LP_Leads::get($id2)->crm_status==='uncertain'&&$last_payload['fields']['status']==='Potential Lead 100','timeout becomes uncertain with attempted intended-to-join status');
    TLIT_LP_Leads::save_status($id2,'enrolled','No duplicates',1);lp_check($calls===3,'uncertain delivery never automatically retries');
    lp_check(TLIT_LP_Leads::reconcile($id2,false)===true,'counsellor confirms latest update was not applied');$mode='success';TLIT_LP_Leads::save_status($id2,'potential','Retry authorised',1);lp_check($calls===4&&TLIT_LP_Leads::get($id2)->crm_status==='sent','confirmed absence permits explicit retry');
    lp_check($last_payload['fields']['phone']==='+919000012345','legacy Indian number gets plus91 once');
    $p3=lp_fixture(['page_variant'=>'v1']);$r3=TLIT_LP_Leads::ingest($p3,['source'=>'general']);$id3=$r3['lead_id'];$made[]=$id3;
    $wpdb->update($table,['crm_status'=>'sending','crm_updated'=>current_time('mysql',true)],['id'=>$id3]);lp_check(is_wp_error(TLIT_LP_Leads::reconcile($id3,false)),'in-flight delivery cannot be reset');
    $wpdb->update($table,['crm_updated'=>gmdate('Y-m-d H:i:s',time()-130)],['id'=>$id3]);lp_check(TLIT_LP_Leads::reconcile($id3,true)===true&&TLIT_LP_Leads::get($id3)->crm_status==='sent','confirmed received marks sent without another request');
    $filtered=TLIT_LP_Leads::query(['utm_campaign'=>'qa-only','page_variant'=>'v3','search'=>'Synthetic QA']);lp_check($filtered['total']>=1,'UTM page and search filters compose');
    lp_check(TLIT_LP_Leads::counts(['utm_campaign'=>'qa-only'])['high_potential']>=1,'status counts use same filters');
    $rejected=TLIT_LP_Leads::ingest(lp_fixture(),['source'=>'general']);$rejected_id=$rejected['lead_id'];$made[]=$rejected_id;
    $wpdb->update($table,['created_at'=>'2026-01-02 04:05:06'],['id'=>$rejected_id]);$mode='forbidden';
    TLIT_LP_Leads::save_status($rejected_id,'potential','Before correction',1);lp_check(TLIT_LP_Leads::get($rejected_id)->crm_status==='failed','definite provider rejection is retryable');
    $mode='success';TLIT_LP_Leads::save_status($rejected_id,'high_potential','After correction',1);
    lp_check(($last_payload['fields']['status']??'')==='60-80 Potential'&&str_contains($last_payload['fields']['Remarks'],'After correction'),'definitely rejected payload rebuilds from latest review');
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
    $fail_filter=function($sql){return str_starts_with($sql,'INSERT INTO `'.TLIT_LP_Leads::table().'`')?'INSERT INTO nonexistent_tlit_failclosed VALUES (1)':$sql;};add_filter('query',$fail_filter);$failure=TLIT_LP_Leads::ingest(lp_fixture(),['source'=>'brochure']);remove_filter('query',$fail_filter);lp_check(is_wp_error($failure),'failed durable insert returns error instead of success');lp_check($calls===6,'capture paths never trigger external delivery');
    $before=$calls;TLIT_LP_Leads::save_status($id,'enrolled','Ready to join',1);
    lp_check($calls===$before+1&&$last_payload['fields']['status']==='Enrolled','approved enrolled stage synchronises automatically');
    lp_check(!isset($last_payload['fields']['Course Enrollment date'])&&!isset($last_payload['fields']['payment']),'enrollment and payment fields remain manual');
    $before=$calls;TLIT_LP_Leads::save_status($id,'junk','Do not contact',1);TLIT_LP_Leads::push($id);
    lp_check($calls===$before,'junk after a prior send never updates or deletes CRM');
    TLIT_LP_Leads::save_status($id,'enrolled','Ready to join',1);lp_check($calls===$before,'return to already-synced qualification is idempotent');
    TLIT_LP_Leads::save_status($id,'enrolled','Confirmed schedule',1);lp_check($calls===$before+1&&str_contains($last_payload['fields']['remarks'],'Confirmed schedule'),'changed counsellor notes synchronise without another lead');
    $wpdb->update($table,['phone'=>'+447700900123'],['id'=>$id]);$before=$calls;TLIT_LP_Leads::save_status($id,'potential','Contact correction',1);
    lp_check($calls===$before&&TLIT_LP_Leads::get($id)->crm_status==='failed'&&str_contains(TLIT_LP_Leads::get($id)->crm_message,'phone'),'changed synced phone holds delivery for identity review');
    $wpdb->update($table,['phone'=>'+447712345678'],['id'=>$id]);TLIT_LP_Leads::save_status($id,'potential','Contact correction',1);
    lp_check($calls===$before+1,'restored CRM identity can sync latest qualification');
    $mode='body_error';$before=$calls;TLIT_LP_Leads::save_status($id,'high_potential','Provider rejected',1);$rejected_key=$last_headers['Idempotency-Key'];
    lp_check($calls===$before+1&&TLIT_LP_Leads::get($id)->crm_status==='failed','HTTP 200 provider error is not reported as synced');
    $mode='success';TLIT_LP_Leads::save_status($id,'high_potential','Provider rejected',1);
    lp_check(TLIT_LP_Leads::get($id)->crm_status==='sent'&&$last_headers['Idempotency-Key']===$rejected_key,'definite rejection retries the same revision key');
    $mode='unknown';TLIT_LP_Leads::save_status($id,'intended_to_join','Unrecognized result',1);$before=$calls;
    lp_check(TLIT_LP_Leads::get($id)->crm_status==='uncertain','unrecognized successful HTTP body remains uncertain');
    TLIT_LP_Leads::save_status($id,'enrolled','Newer local review',1);lp_check($calls===$before,'newer review cannot silently retry unknown delivery');
    lp_check(TLIT_LP_Leads::reconcile($id,true)===true,'confirmation records only attempted payload revision');$mode='success';
    TLIT_LP_Leads::save_status($id,'enrolled','Newer local review',1);lp_check($calls===$before+1&&$last_payload['fields']['status']==='Enrolled','newer local review syncs after older attempt is reconciled');
    $chat=lp_fixture(['request_id'=>'cb-conversation-'.random_int(1000000,1999999),'email'=>'','source'=>'chatbot','details'=>['language'=>'Telugu']]);$before=$calls;
    $chat_result=TLIT_LP_Leads::sync_conversation($chat);lp_check(!is_wp_error($chat_result),'chat contact can be stored without email');$chat_id=$chat_result['lead_id'];$made[]=$chat_id;
    lp_check($calls===$before&&TLIT_LP_Leads::get($chat_id)->source==='chatbot','chat contact capture does not send CRM');
    $chat_again=TLIT_LP_Leads::sync_conversation($chat);lp_check($chat_again['lead_id']===$chat_id&&$chat_again['duplicate'],'stable conversation id avoids another contact row');
    TLIT_LP_Leads::save_status($chat_id,'potential','Chat caller interested',1);lp_check($calls===$before+1&&!isset($last_payload['fields']['email']),'qualified chat uses same handoff and omits missing email');
    lp_check(str_contains($last_payload['fields']['remarks'],'Conversation: CB-'),'CRM notes retain conversation link');
    $chat['source']='chatbot_brochure';$chat['email']='brochure@example.test';$chat['brochure_downloaded']=true;$chat_result2=TLIT_LP_Leads::sync_conversation($chat,$chat_id);
    lp_check($chat_result2['lead_id']===$chat_id&&TLIT_LP_Leads::get($chat_id)->source==='chatbot_brochure','brochure after qualification reuses same contact');
    $before=$calls;$chat['source']='chatbot';$chat['email']='';$chat['page_variant']='v1';$chat['tracking']['utm_source']='changed';TLIT_LP_Leads::sync_conversation($chat,$chat_id);$chat_row=TLIT_LP_Leads::get($chat_id);$chat_data=json_decode($chat_row->payload,true);
    lp_check($calls===$before&&$chat_row->email==='brochure@example.test'&&$chat_row->source==='chatbot_brochure'&&$chat_row->page_variant==='v3'&&$chat_data['tracking']['utm_source']==='google','later chat capture preserves brochure contact and original attribution without CRM');
    $chat['goal']='';TLIT_LP_Leads::sync_conversation($chat,$chat_id);lp_check(json_decode(TLIT_LP_Leads::get($chat_id)->payload,true)['goal']==='','explicitly cleared chat goal removes stale inference');
    $chat['goal']='Start freelancing';TLIT_LP_Leads::sync_conversation($chat,$chat_id);unset($chat['goal']);TLIT_LP_Leads::sync_conversation($chat,$chat_id);lp_check(json_decode(TLIT_LP_Leads::get($chat_id)->payload,true)['goal']==='Start freelancing','omitted goal preserves previous inference');
    $chat['phone']='+447700900999';lp_check(is_wp_error(TLIT_LP_Leads::sync_conversation($chat,$chat_id)),'different linked phone cannot hijack or duplicate CRM identity');
    $brochure_link=lp_fixture(['request_id'=>'cb-conversation-'.random_int(2000000,2999999),'source'=>'chatbot']);$before=$calls;$link=TLIT_LP_Leads::sync_conversation($brochure_link,$id);
    lp_check(!is_wp_error($link)&&$link['lead_id']===$id&&$calls===$before&&TLIT_LP_Leads::get($id)->source==='brochure','trusted previous brochure link reuses record without losing form source');
    $cid=(int)substr($chat['request_id'],16);lp_check(TLIT_LP_Leads::erase_conversation_lead($chat_id,$cid)&&!TLIT_LP_Leads::get($chat_id),'conversation privacy erasure removes its canonical contact copy');
    lp_check(TLIT_LP_Leads::erase_conversation_lead($id,(int)substr($brochure_link['request_id'],16))&&TLIT_LP_Leads::get($id)!==null,'conversation-only erasure preserves separate brochure-purpose record');
    $mode='legacy_error';TLIT_LP_Leads::save_status($id,'potential','Legacy API error',1);lp_check(TLIT_LP_Leads::get($id)->crm_status==='failed','legacy errorString overrides apparent successful acknowledgment');
    $mode='created';TLIT_LP_Leads::save_status($id,'potential','Legacy API error',1);lp_check(TLIT_LP_Leads::get($id)->crm_status==='sent'&&TLIT_LP_Leads::get($id)->crm_remote_id==='0123456789abcdef01234567','documented Created response records modified lead identifier');
    $mode='updated';TLIT_LP_Leads::save_status($id,'high_potential','Updated response',1);lp_check(TLIT_LP_Leads::get($id)->crm_status==='sent','documented single-item Updated response confirms revision');
    $native_stages=['demo_interested'=>'Interested to join the Demo','intended_to_join'=>'Potential Lead 100','demo_attended'=>'Demo Attended','next_batch'=>'Looking for Next batch','call_not_answered'=>'Call not answered and Shared the Data','number_email'=>'Number is not working and sent an email','wrong_number'=>'Wrong Number &Number Not working','different_course'=>'Different Course','not_interested'=>'Not Interested','potential'=>'50 % Potential','below_50_potential'=>'below 50 % Potential','high_potential'=>'60-80 Potential','become_trainer'=>'Become Trainer','different_requirement'=>'Different Requirement','enrolled'=>'Enrolled','lost'=>'Lost'];
    foreach($native_stages as $key=>$label) {
        $before=$calls;TLIT_LP_Leads::save_status($id,$key,'Review: '.$label,1);
        lp_check($calls===$before+1&&$last_payload['fields']['status']===$label&&TLIT_LP_Leads::get($id)->crm_status==='sent','native counsellor status saved and synced: '.$label);
    }
    $before=$calls;TLIT_LP_Leads::save_status($id,'new','Hold locally',1);TLIT_LP_Leads::save_status($id,'junk','Hold locally',1);lp_check($calls===$before,'New and Junk still stay local with expanded TeleCRM statuses');
    $previous_timezone=get_option('timezone_string');update_option('timezone_string','UTC');
    try {
        lp_check(TLIT_LP_Leads::display_date('2026-10-15 07:17:00','Y-m-d H:i:s')==='2026-10-15 12:47:00 IST','received time explicitly converts UTC to IST despite WordPress timezone');
        $campaign='qa-ist-'.wp_generate_uuid4();$boundary_ids=[];
        foreach(['2026-10-14 18:29:59','2026-10-14 18:30:00','2026-10-15 18:29:59','2026-10-15 18:30:00'] as $stamp) {
            $boundary=lp_fixture();$boundary['tracking']['utm_campaign']=$campaign;$stored=TLIT_LP_Leads::ingest($boundary,['source'=>'general']);$made[]=$boundary_ids[]=$stored['lead_id'];$wpdb->update($table,['created_at'=>$stamp],['id'=>$stored['lead_id']]);
        }
        $filtered=TLIT_LP_Leads::query(['from'=>'2026-10-15','to'=>'2026-10-15','utm_campaign'=>$campaign]);$found=array_map('intval',array_column($filtered['rows'],'id'));sort($found);$expected=[$boundary_ids[1],$boundary_ids[2]];sort($expected);
        lp_check($found===$expected,'lead filters include exactly the full IST calendar day at UTC midnight boundaries');
        lp_check(TLIT_LP_Leads::query(['from'=>'2026-02-30','utm_campaign'=>$campaign])['total']===0,'invalid IST date cannot broaden results');
    } finally { update_option('timezone_string',$previous_timezone); }
    $GLOBALS['lp_test_existing_url']='';$before=$calls;$missing=TLIT_LP_Leads::ingest(lp_fixture(),['source'=>'brochure']);$made[]=$missing['lead_id'];TLIT_LP_Leads::save_status($missing['lead_id'],'potential','Connection unavailable',1);
    lp_check($calls===$before&&TLIT_LP_Leads::get($missing['lead_id'])->crm_status==='pending','unavailable existing connection retains lead without fallback to stale credentials');
    echo "ALL $checks checks passed; actual isolated WordPress/MariaDB; all network mocked.\n";
} finally {
    remove_filter('pre_http_request',$hook,10);update_option(TLIT_LP_Leads::OPTION,$old,false);foreach($made as $id){$wpdb->delete($table,['id'=>$id]);}
}
