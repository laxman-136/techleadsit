<?php
if (!defined('ABSPATH')) { exit; }
trait TLIT_LP_Integration {
    static function migrate_legacy_secret() {
        $old=get_option(self::OPTION,[]);
        if(!is_array($old)||empty($old['token'])||!is_string($old['token'])) { return; }
        $sealed=self::seal($old['token']);
        if($sealed!=='') { $old['token_sealed']=$sealed;unset($old['token']);update_option(self::OPTION,$old,false); }
    }
    static function seal($value) {
        if(!function_exists('openssl_encrypt')) { return ''; }
        $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($value,'aes-256-gcm',hash('sha256',wp_salt('auth').'landing-leads',true),OPENSSL_RAW_DATA,$iv,$tag);
        return $cipher===false?'':base64_encode($iv.$tag.$cipher);
    }
    static function secret($encoded) {
        if(!function_exists('openssl_decrypt')) { return ''; }
        $raw=base64_decode($encoded,true);if(!$raw||strlen($raw)<29) { return ''; }
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',wp_salt('auth').'landing-leads',true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));return is_string($plain)?$plain:'';
    }
    static function settings() {
        $o=get_option(self::OPTION,[]);$o=is_array($o)?$o:[];
        $connection=self::existing_connection();
        // There is one connection for the website. Old Bridge flags and secrets
        // remain inert so an outdated settings screen cannot disable handoff.
        return ['enabled'=>$connection!==null,'enterprise'=>$connection['enterprise']??'','has_token'=>!empty($connection['token']),'mapping'=>is_array($o['mapping']??null)?$o['mapping']:['Remarks'=>'remarks'],'reuse_existing'=>true,'existing_available'=>$connection!==null];
    }
    static function existing_connection() {
        $url=function_exists('tlit_dm_crm_url')?tlit_dm_crm_url():'';
        if(!preg_match('~^https://next\.telecrm\.in/api/b1/enterprise/([A-Za-z0-9_-]+)/autoupdatelead$~D',$url,$match)) { return null; }
        // Some existing enterprise integrations authenticate through the configured endpoint.
        return ['enterprise'=>$match[1],'token'=>defined('TLIT_DM_TELECRM_TOKEN')?(string)TLIT_DM_TELECRM_TOKEN:'','url'=>$url];
    }
    static function credentials() {
        return self::existing_connection()?:self::error('The existing landing-page TeleCRM connection is unavailable. This lead remains saved in WordPress.');
    }
    static function mapping_keys() { return array_merge(['goal','status','timeline','question','source','remarks','marketingConsent','page_variant','submission_url','lead_status','reference'],self::tracking_keys()); }
    static function crm_statuses() { return array_diff_key(self::statuses(),array_flip(['new','junk'])); }
    static function protected_fields() {
        $fields=tlit_lp_schema_lead_fields([],'','','','','');
        unset($fields['Remarks']);return array_map('strtolower',array_keys($fields));
    }
    static function save_settings(array $p) {
        return self::error('Separate Bridge TeleCRM settings have been retired. Qualified leads use the existing landing-page connection automatically.',410);
    }
    static function test_connection() {
        $credentials=self::credentials();if(is_wp_error($credentials)) { return $credentials; }
        // The provided TeleCRM endpoint creates/updates a lead. Never send a test lead.
        return ['success'=>true,'message'=>'The existing landing-page connection is available. Qualified reviews synchronise automatically; no test lead was sent.','network_tested'=>false];
    }
    static function crm_payload($row) {
        $d=json_decode($row->payload,true)?:[];$tracking=$d['tracking']??[];$details=$d['details']??[];
        $details['student_status']=$details['student_status']??($d['status']??'');
        $note="Landing Page Leads | LP-{$row->id}\nPage: {$row->page_variant}\nSubmitted from: {$row->submission_url}\nForm: ".(self::labels()[$row->source]??$row->source)."\nCounsellor qualification: ".(self::statuses()[$row->lead_status]??$row->lead_status)."\nGoal: ".($d['goal']??'')."\nMarketing consent: ".(!empty($d['marketingConsent'])?'Yes':'No')."\nPhone verified: ".(!empty($row->otp_verified)?'Yes':'No')."\nCounsellor notes: {$row->advisor_note}";
        if(!empty($details['conversation_reference'])) { $note.="\nConversation: ".$details['conversation_reference']; }
        foreach(['status','timeline','question'] as $key) { if(!empty($d[$key])) { $note.="\n".ucfirst($key).': '.$d[$key]; } }
        if(!empty($d['brochure_downloaded'])) { $note.="\nPerson downloaded brochure — AI Powered Digital Marketing"; }
        elseif(in_array($row->source,['brochure','chatbot_brochure'],true)) { $note.="\nBrochure requested — AI Powered Digital Marketing"; }
        $fields=tlit_lp_schema_lead_fields(['tracking'=>$tracking,'details'=>$details],$row->name,$row->phone,$row->email,$d['goal']??'',$note);
        // The handoff may happen days later; report the original capture date in IST.
        try {
            $captured=new DateTimeImmutable($row->created_at,new DateTimeZone('UTC'));
            $lead_date=$captured->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('Y-m-d H:i:s');
            foreach(['Lead date','lead_date','leaddate','date'] as $field) { $fields[$field]=$lead_date; }
        } catch(Throwable $error) { /* Legacy malformed dates keep the schema fallback. */ }
        $data=array_merge($d,$tracking,['source'=>self::labels()[$row->source]??$row->source,'remarks'=>$note,'marketingConsent'=>!empty($d['marketingConsent'])?'Yes':'No','page_variant'=>$row->page_variant,'submission_url'=>$row->submission_url,'lead_status'=>self::statuses()[$row->lead_status]??$row->lead_status,'reference'=>'LP-'.$row->id]);
        foreach(self::settings()['mapping'] as $field=>$key) {
            if(is_string($field)&&is_string($key)&&strtolower(trim($field))!=='status'&&!in_array(strtolower(trim($field)),self::protected_fields(),true)&&!preg_match('/enrol|enroll|payment|paid|receipt|fee/i',$field)&&in_array($key,self::mapping_keys(),true)) { $fields[$field]=$data[$key]??''; }
        }
        $fields['Remarks']=$note;
        // Keep the existing endpoint's required schema and include the workspace's
        // real API field names. A note action alone is not enough for API clients.
        foreach(['Course Name'=>'course','Remarks'=>'remarks','Lead Source'=>'lead_source_1','City Name'=>'city_name','State Name'=>'state_name','Mode of Training'=>'mode_of_training','Exp Level'=>'exp_level','Your preferred time to call'=>'your_preferred_time_to_call','Facebook Campaign'=>'facebook_campaign','Facebook Ad'=>'facebook_ad','Facebook Ad set Name'=>'facebook_ad_set_name','Facebook Ad set ID'=>'facebook_ad_set_id','Facebook Lead ID'=>'facebook_lead_id','gclid'=>'google_gcl_id'] as $legacy=>$api_name) { $fields[$api_name]=$fields[$legacy]??''; }
        foreach(['facebook_ad_set_id','facebook_lead_id'] as $number_field) { if(!preg_match('/^[0-9]+$/D',(string)($fields[$number_field]??''))) { unset($fields[$number_field]); } }
        if($fields['lead_source_1']==='Google Ads') { $fields['lead_source_1']='Google ads'; }
        if($fields['state_name']==='Not detected'||$fields['state_name']==='') { unset($fields['state_name']); }
        if(isset(self::crm_statuses()[$row->lead_status])) { $fields['status']=self::crm_statuses()[$row->lead_status]; }
        // Chat visitors need not provide email; never erase a CRM email with a blank.
        if($row->email==='') { unset($fields['email']); }
        return ['fields'=>$fields,'actions'=>[['type'=>'SYSTEM_NOTE','text'=>$note]]];
    }
    static function push($id) {
        $lock=self::lock('lead-review-'.absint($id));if(!$lock) { return false; }
        try { return self::push_locked($id); } finally { delete_option($lock); }
    }
    /** Caller owns the review lock, including the HTTP attempt, so a stale review cannot overtake a newer one. */
    protected static function push_locked($id) {
        global $wpdb;$row=self::get($id);
        if(!$row||!isset(self::crm_statuses()[$row->lead_status])||!in_array($row->crm_status,['pending','failed','sent'],true)) { return false; }
        $intended=self::crm_payload($row);$payload=wp_json_encode($intended);
        if(!$payload) { return false; }
        $revision=hash('sha256',$payload);$synced=$row->crm_synced_revision??'';
        if($row->crm_status==='sent'&&$synced===''&&!empty($row->crm_payload)) {
            // Adopt the last successful legacy snapshot without resending it.
            $synced=hash('sha256',$row->crm_payload);
            if($wpdb->update(self::table(),['crm_revision'=>$synced,'crm_synced_revision'=>$synced],['id'=>$row->id,'crm_status'=>'sent','crm_synced_revision'=>''])!==1) { return false; }
        }
        $last_attempt=json_decode($row->crm_payload??'',true);
        $sent_phone=is_array($last_attempt)?self::canonical_phone($last_attempt['fields']['phone']??''):'';
        if($synced!==''&&($sent_phone===''||$sent_phone!==self::canonical_phone($row->phone))) {
            $wpdb->update(self::table(),['crm_status'=>'failed','crm_message'=>'The phone differs from the previously synced lead. Restore the original phone or reconcile its identity in TeleCRM before syncing.'],['id'=>$row->id,'crm_status'=>$row->crm_status]);
            return false;
        }
        if($synced!==''&&hash_equals($synced,$revision)) {
            if($row->crm_status!=='sent') { $wpdb->update(self::table(),['crm_status'=>'sent','crm_message'=>'This review is already synced to TeleCRM.'],['id'=>$row->id,'crm_status'=>$row->crm_status]); }
            return true;
        }
        $connection=self::credentials();
        if(is_wp_error($connection)) {
            $patch=['crm_message'=>$connection->get_error_message()];if($row->crm_status==='sent') { $patch['crm_status']='failed'; }
            $wpdb->update(self::table(),$patch,['id'=>$row->id,'crm_status'=>$row->crm_status]);return false;
        }
        if($synced!=='') {
            // Once created, update only the qualification and notes. Counsellors'
            // later contact enrichment in TeleCRM must not be overwritten by an
            // old capture snapshot, empty fields, or automatic default values.
            $fields=array_intersect_key($intended['fields'],array_flip(['phone','status','Remarks','remarks']));
            $payload=wp_json_encode(['fields'=>$fields,'actions'=>$intended['actions']]);
        }
        // One durable claim per payload revision; a changed qualified status or
        // note updates the same phone, while an unchanged save sends nothing.
        $won=$wpdb->query($wpdb->prepare('UPDATE '.self::table()." SET crm_status='sending',crm_payload=%s,crm_revision=%s,crm_attempts=crm_attempts+1,crm_updated=%s,crm_message='' WHERE id=%d AND crm_status=%s AND lead_status=%s",$payload,$revision,current_time('mysql',true),$row->id,$row->crm_status,$row->lead_status));
        if($won!==1) { return false; }
        $headers=['Content-Type'=>'application/json','Idempotency-Key'=>'tlit-lp-'.self::hash($row->id.'|'.$revision)];
        if($connection['token']!=='') { $headers['Authorization']='Bearer '.$connection['token']; }
        $status='uncertain';$message='The latest update could not be confirmed. Check its qualification and notes in TeleCRM before retrying.';$remote_id='';
        try {
            $response=wp_remote_post($connection['url'],['timeout'=>20,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>16384,'headers'=>$headers,'body'=>$payload]);
            if(!is_wp_error($response)) {
                $code=wp_remote_retrieve_response_code($response);$body=json_decode(wp_remote_retrieve_body($response),true);
                if(is_array($body)&&count($body)===1&&isset($body[0])&&is_array($body[0])) { $body=$body[0]; }
                $provider_status=is_array($body)&&is_string($body['status']??null)?strtolower(trim($body['status'])):'';
                $rejected=is_array($body)&&(!empty($body['error'])||!empty($body['errorMessage'])||!empty($body['errorString'])||($body['success']??null)===false||in_array($provider_status,['error','failed','failure'],true));
                $accepted=is_array($body)&&(($body['success']??null)===true||in_array($provider_status,['success','ok','created','updated'],true));
                if($code>=200&&$code<300&&!$rejected&&$accepted) {
                    $status='sent';$message='Latest qualification and notes synced to TeleCRM.';
                    $modified_ids=is_array($body['modifiedLeadIds']??null)?$body['modifiedLeadIds']:[];
                    foreach([$body['leadId']??null,$body['lead_id']??null,$body['id']??null,$modified_ids[0]??null,$body['data']['leadId']??null,$body['data']['id']??null] as $candidate) {
                        if((is_string($candidate)||is_int($candidate))&&preg_match('/^[A-Za-z0-9_-]{1,100}$/D',(string)$candidate)) { $remote_id=(string)$candidate;break; }
                    }
                }
                elseif($rejected||($code>=400&&$code<500&&$code!==408)) { $status='failed';$message='TeleCRM rejected this update (HTTP '.$code.'). Check the existing connection, then save the qualification again.'; }
            }
        } catch(Throwable $error) { /* The durable sending claim is never silently reset. */ }
        $patch=['crm_status'=>$status,'crm_message'=>$message,'crm_updated'=>current_time('mysql',true)];
        if($status==='sent') { $patch['crm_synced_revision']=$revision; }
        if($remote_id!=='') { $patch['crm_remote_id']=$remote_id; }
        $saved=$wpdb->update(self::table(),$patch,['id'=>$row->id,'crm_status'=>'sending','crm_revision'=>$revision]);return $status==='sent'&&$saved===1;
    }
    static function reconcile($id,$received) {
        global $wpdb;$row=self::get($id);
        if(!$row||!in_array($row->crm_status,['sending','uncertain'],true)) { return self::error('This delivery does not need reconciliation.'); }
        if($row->crm_status==='sending'&&strtotime($row->crm_updated.' UTC')>time()-120) { return self::error('Delivery is still in progress. Wait two minutes before reconciling.',409); }
        $data=['crm_status'=>$received?'sent':'pending','crm_message'=>$received?'Counsellor confirmed the latest attempted qualification and notes are present in TeleCRM.':'Counsellor confirmed the latest attempted update was not applied. Save the qualification to retry.','crm_updated'=>current_time('mysql',true)];
        if($received) { $data['crm_synced_revision']=$row->crm_revision?:(!empty($row->crm_payload)?hash('sha256',$row->crm_payload):''); }
        return $wpdb->update(self::table(),$data,['id'=>$row->id,'crm_status'=>$row->crm_status])===1?true:self::error('Delivery changed. Reload before reconciling.',409);
    }
}
