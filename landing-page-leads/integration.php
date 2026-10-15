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
        return ['enabled'=>!empty($o['enabled']),'enterprise'=>self::clean($o['enterprise']??'',100),'has_token'=>!empty($o['token_sealed'])||!empty($o['token']),'mapping'=>is_array($o['mapping']??null)?$o['mapping']:['Remarks'=>'remarks'],'reuse_existing'=>!empty($o['reuse_existing']),'existing_available'=>self::existing_connection()!==null];
    }
    static function existing_connection() {
        $url=function_exists('tlit_dm_crm_url')?tlit_dm_crm_url():'';
        if(!preg_match('~^https://next\.telecrm\.in/api/b1/enterprise/([A-Za-z0-9_-]+)/autoupdatelead$~D',$url,$match)) { return null; }
        // Some existing enterprise integrations authenticate through the configured endpoint.
        return ['enterprise'=>$match[1],'token'=>defined('TLIT_DM_TELECRM_TOKEN')?(string)TLIT_DM_TELECRM_TOKEN:'','url'=>$url];
    }
    static function credentials() {
        $o=get_option(self::OPTION,[]);
        if(empty($o['enabled'])) { return self::error('TeleCRM is disabled. The lead is saved in this inbox.'); }
        if(!empty($o['reuse_existing'])) {
            $connection=self::existing_connection();return $connection?:self::error('The existing Digital Marketing TeleCRM connection is unavailable.');
        }
        $enterprise=$o['enterprise']??'';$token=!empty($o['token_sealed'])?self::secret($o['token_sealed']):($o['token']??'');
        if(!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$enterprise)||!is_string($token)||$token==='') { return self::error('Save the TeleCRM enterprise ID and bearer token before sending.'); }
        return ['enterprise'=>$enterprise,'token'=>$token,'url'=>'https://next.telecrm.in/api/b1/enterprise/'.rawurlencode($enterprise).'/autoupdatelead'];
    }
    static function mapping_keys() { return array_merge(['goal','status','timeline','question','source','remarks','marketingConsent','page_variant','submission_url','lead_status','reference'],self::tracking_keys()); }
    static function protected_fields() {
        $fields=tlit_lp_schema_lead_fields([],'','','','','');
        unset($fields['Remarks']);return array_map('strtolower',array_keys($fields));
    }
    static function save_settings(array $p) {
        $old=get_option(self::OPTION,[]);$old=is_array($old)?$old:[];
        $enterprise=self::clean($p['enterprise']??'',101);
        if($enterprise!==''&&!preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',$enterprise)) { return self::error('Enter a valid TeleCRM enterprise ID.'); }
        $raw=$p['mapping']??'{}';$mapping=is_string($raw)?json_decode($raw,true):$raw;
        if(!is_array($mapping)) { return self::error('Field mapping must be a JSON object.'); }
        $clean=[];$protected=self::protected_fields();
        foreach($mapping as $field=>$value) {
            if(!is_string($field)||!is_string($value)||!in_array($value,self::mapping_keys(),true)||strlen($field)>100||in_array(strtolower(trim($field)),$protected,true)||preg_match('/enrol|enroll|payment|paid|receipt|fee/i',$field)) { return self::error('Unsupported mapping. Required contact and attribution fields are protected; enrollment dates and payment fields remain manual.'); }
            $clean[sanitize_text_field($field)]=$value;
        }
        $token=is_string($p['token']??null)?trim($p['token']):'';
        if(strlen($token)>4096||preg_match('/[\r\n]/',$token)) { return self::error('Enter a valid bearer token.'); }
        $sealed=$old['token_sealed']??'';
        if($token==='') { $token=is_string($old['token']??null)?$old['token']:''; }
        if($token!=='') { $sealed=self::seal($token);if($sealed==='') { return self::error('The token could not be stored securely. Check OpenSSL.'); } }
        if(!empty($p['clear_token'])) { $sealed=''; }
        $o=['enabled'=>!empty($p['enabled'])?1:0,'enterprise'=>$enterprise,'token_sealed'=>$sealed,'reuse_existing'=>!empty($p['reuse_existing'])?1:0,'mapping'=>$clean];
        if($o['enabled']&&!$o['reuse_existing']&&($enterprise===''||$sealed==='')) { return self::error('Add the enterprise ID and token before enabling TeleCRM.'); }
        if($o['enabled']&&$o['reuse_existing']&&self::existing_connection()===null) { return self::error('The existing Digital Marketing connection is unavailable.'); }
        $saved=update_option(self::OPTION,$o,false);
        return $saved||get_option(self::OPTION) === $o?true:self::error('The integration settings could not be saved. Please retry.',503);
    }
    static function test_connection() {
        $credentials=self::credentials();if(is_wp_error($credentials)) { return $credentials; }
        // The provided TeleCRM endpoint creates/updates a lead. Never send a test lead.
        return ['success'=>true,'message'=>'Configuration is ready. Credentials will be verified when a counsellor qualifies a real lead; no test lead was sent.','network_tested'=>false];
    }
    static function crm_payload($row) {
        $d=json_decode($row->payload,true)?:[];$tracking=$d['tracking']??[];$details=$d['details']??[];
        $details['student_status']=$details['student_status']??($d['status']??'');
        $note="Landing Page Leads | LP-{$row->id}\nPage: {$row->page_variant}\nSubmitted from: {$row->submission_url}\nForm: ".(self::labels()[$row->source]??$row->source)."\nCounsellor qualification: ".(self::statuses()[$row->lead_status]??$row->lead_status)."\nGoal: ".($d['goal']??'')."\nMarketing consent: ".(!empty($d['marketingConsent'])?'Yes':'No')."\nPhone verified: ".(!empty($row->otp_verified)?'Yes':'No')."\nCounsellor notes: {$row->advisor_note}";
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
            if(is_string($field)&&is_string($key)&&!in_array(strtolower(trim($field)),self::protected_fields(),true)&&!preg_match('/enrol|enroll|payment|paid|receipt|fee/i',$field)&&in_array($key,self::mapping_keys(),true)) { $fields[$field]=$data[$key]??''; }
        }
        return ['fields'=>$fields,'actions'=>[['type'=>'SYSTEM_NOTE','text'=>$note]]];
    }
    static function push($id) {
        global $wpdb;$row=self::get($id);
        if(!$row||!in_array($row->lead_status,['potential','high_potential','intended_to_join','enrolled'],true)||!in_array($row->crm_status,['pending','failed'],true)) { return false; }
        $connection=self::credentials();
        if(is_wp_error($connection)) { $wpdb->update(self::table(),['crm_message'=>$connection->get_error_message()],['id'=>$row->id,'crm_status'=>$row->crm_status]);return false; }
        // A definite rejection can be corrected through settings or notes. Only
        // uncertain deliveries keep their original snapshot after reconciliation.
        $payload=$row->crm_status!=='failed'&&!empty($row->crm_payload)?$row->crm_payload:wp_json_encode(self::crm_payload($row));
        if(!$payload) { return false; }
        // A durable atomic claim makes simultaneous saves and repeated status changes one send.
        $won=$wpdb->query($wpdb->prepare('UPDATE '.self::table()." SET crm_status='sending',crm_payload=%s,crm_attempts=crm_attempts+1,crm_updated=%s,crm_message='' WHERE id=%d AND crm_status IN ('pending','failed') AND lead_status IN ('potential','high_potential','intended_to_join','enrolled')",$payload,current_time('mysql',true),$row->id));
        if($won!==1) { return false; }
        $headers=['Content-Type'=>'application/json','Idempotency-Key'=>'tlit-lp-'.self::hash((string)$row->id)];
        if($connection['token']!=='') { $headers['Authorization']='Bearer '.$connection['token']; }
        $status='uncertain';$message='Delivery could not be confirmed. Check TeleCRM before retrying.';
        try {
            $response=wp_remote_post($connection['url'],['timeout'=>20,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>16384,'headers'=>$headers,'body'=>$payload]);
            if(!is_wp_error($response)) {
                $code=wp_remote_retrieve_response_code($response);$body=json_decode(wp_remote_retrieve_body($response),true);
                if($code>=200&&$code<300&&!(is_array($body)&&(isset($body['error'])||($body['success']??true)===false))) { $status='sent';$message='Sent to TeleCRM successfully.'; }
                elseif($code>=400&&$code<500&&$code!==408) { $status='failed';$message='TeleCRM rejected this request (HTTP '.$code.'). Check the connection, then save the qualification again.'; }
            }
        } catch(Throwable $error) { /* The durable sending claim is never silently reset. */ }
        $wpdb->update(self::table(),['crm_status'=>$status,'crm_message'=>$message,'crm_updated'=>current_time('mysql',true)],['id'=>$row->id,'crm_status'=>'sending']);return $status==='sent';
    }
    static function reconcile($id,$received) {
        global $wpdb;$row=self::get($id);
        if(!$row||!in_array($row->crm_status,['sending','uncertain'],true)) { return self::error('This delivery does not need reconciliation.'); }
        if($row->crm_status==='sending'&&strtotime($row->crm_updated.' UTC')>time()-120) { return self::error('Delivery is still in progress. Wait two minutes before reconciling.',409); }
        $data=['crm_status'=>$received?'sent':'pending','crm_message'=>$received?'Counsellor confirmed the lead is present in TeleCRM.':'Counsellor checked TeleCRM and confirmed the lead is absent. Save its qualification to retry.','crm_updated'=>current_time('mysql',true)];
        return $wpdb->update(self::table(),$data,['id'=>$row->id,'crm_status'=>$row->crm_status])===1?true:self::error('Delivery changed. Reload before reconciling.',409);
    }
}
