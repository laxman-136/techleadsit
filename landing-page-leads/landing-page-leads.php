<?php
/**
 * Plugin Name: Tech Leads IT — Landing Page Leads
 * Description: Bridge-first landing-page lead inbox with counsellor-controlled TeleCRM handoff.
 * Version: 0.3.0
 * Requires PHP: 8.0
 */
if (!defined('ABSPATH')) { exit; }
if (!class_exists('TLIT_LP_Leads')) {
require_once __DIR__.'/lead-schema.php';
require_once __DIR__.'/api.php';
require_once __DIR__.'/integration.php';
final class TLIT_LP_Leads {
    use TLIT_LP_API, TLIT_LP_Integration;
    const VERSION = '0.3.0';
    const OPTION = 'tlit_lp_integration';
    static function table() { global $wpdb; return $wpdb->prefix.'tlit_landing_leads'; }
    static function install() {
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $table=self::table();
        dbDelta("CREATE TABLE $table (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            request_id varchar(80) NOT NULL,
            fingerprint varchar(64) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            name varchar(80) NOT NULL,
            phone varchar(20) NOT NULL,
            email varchar(254) NOT NULL,
            source varchar(24) NOT NULL,
            page_variant varchar(80) NOT NULL DEFAULT '',
            submission_url varchar(2000) NOT NULL DEFAULT '',
            payload longtext NOT NULL,
            otp_verified tinyint NOT NULL DEFAULT 0,
            lead_status varchar(24) NOT NULL DEFAULT 'new',
            reviewed_by bigint unsigned NOT NULL DEFAULT 0,
            reviewed_at datetime DEFAULT NULL,
            crm_status varchar(20) NOT NULL DEFAULT 'pending',
            crm_updated datetime DEFAULT NULL,
            crm_message varchar(255) NOT NULL DEFAULT '',
            crm_attempts int unsigned NOT NULL DEFAULT 0,
            crm_payload longtext NULL,
            crm_revision varchar(64) NOT NULL DEFAULT '',
            crm_synced_revision varchar(64) NOT NULL DEFAULT '',
            crm_remote_id varchar(100) NOT NULL DEFAULT '',
            advisor_note text NOT NULL,
            review_history longtext NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY request_id (request_id),
            KEY created_at (created_at),
            KEY source (source),
            KEY page_variant (page_variant),
            KEY lead_status (lead_status),
            KEY crm_status (crm_status)
        ) ENGINE=InnoDB ".$wpdb->get_charset_collate().';');
        $columns=$wpdb->get_col("SHOW COLUMNS FROM $table");
        if (is_array($columns) && !array_diff(['lead_status','page_variant','crm_payload','crm_revision','crm_synced_revision','crm_remote_id','review_history'],$columns)) {
            update_option('tlit_lp_version',self::VERSION,false);
        }
    }
    static function boot() {
        if(get_option('tlit_lp_version')!==self::VERSION) { self::install(); }
        add_action('rest_api_init',[__CLASS__,'routes']);
        add_action('admin_menu',[__CLASS__,'menu'],30);
        add_action('admin_post_tlit_lp_action',[__CLASS__,'action']);
        add_action('admin_post_tlit_lp_export',[__CLASS__,'export']);
        add_action('tlit_lp_backfill_sources',[__CLASS__,'backfill_sources']);
        if(get_option('tlit_lp_sources_backfill')!=='1'&&!wp_next_scheduled('tlit_lp_backfill_sources')) { wp_schedule_single_event(time()+10,'tlit_lp_backfill_sources'); }
        if (is_file(__DIR__.'/admin.php')) { require_once __DIR__.'/admin.php'; }
    }
    static function error($message,$status=400) { return new WP_Error('tlit_landing_lead',$message,['status'=>$status]); }
    static function hash($value) { return hash_hmac('sha256',$value,wp_salt('auth')); }
    static function clean($value,$length=500) { return is_scalar($value)?substr(sanitize_text_field((string)$value),0,$length):''; }
    static function phone($value) {
        if(!is_string($value)||!preg_match('/^[+0-9 ()-]+$/D',$value)) { return ''; }
        $n=preg_replace('/\D/','',$value);
        if(strlen($n)===12&&str_starts_with($n,'91')) { $n=substr($n,2); }
        return preg_match('/^[6-9][0-9]{9}$/D',$n)?$n:'';
    }
    static function canonical_phone($value) {
        if (!is_string($value)) { return ''; }
        if (function_exists('tlit_dm_chat_phone_international') && str_starts_with(trim($value),'+')) {
            return tlit_dm_chat_phone_international(trim($value));
        }
        $india=self::phone($value);
        if($india) { return '+91'.$india; }
        // Trusted server-side adapters validate international numbers before ingest.
        return preg_match('/^\+[1-9][0-9]{6,14}$/D',$value)?$value:'';
    }
    static function lock($value) {
        global $wpdb;
        $key='tlit_lp_lock_'.self::hash($value);
        $old=get_option($key);
        if($old && (int)$old<time()) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",$key,(string)$old));
            wp_cache_delete($key,'options');
        }
        return add_option($key,time()+90,'','no')?$key:false;
    }
    static function rate($value,$maximum,$seconds) {
        $lock=self::lock('rate'.$value);
        if(!$lock) { return false; }
        try {
            $key='tlit_lp_rate_'.self::hash($value);$count=(int)get_transient($key);
            if($count>=$maximum) { return false; }
            set_transient($key,$count+1,$seconds);return true;
        } finally { delete_option($lock); }
    }
    static function labels() { return ['general'=>'General form','popup'=>'Popup form','brochure'=>'Brochure form','chatbot_brochure'=>'Chatbot brochure','chatbot'=>'Chatbot conversation']; }
    static function statuses() {
        // Keep saved keys stable while displaying the account's exact TeleCRM labels.
        return ['new'=>'New','junk'=>'Junk Lead',
            'demo_interested'=>'Interested to join the Demo','intended_to_join'=>'Potential Lead 100',
            'demo_attended'=>'Demo Attended','next_batch'=>'Looking for Next batch',
            'call_not_answered'=>'Call not answered and Shared the Data',
            'number_email'=>'Number is not working and sent an email',
            'wrong_number'=>'Wrong Number &Number Not working','different_course'=>'Different Course',
            'not_interested'=>'Not Interested','potential'=>'50 % Potential',
            'below_50_potential'=>'below 50 % Potential','high_potential'=>'60-80 Potential',
            'become_trainer'=>'Become Trainer','different_requirement'=>'Different Requirement',
            'enrolled'=>'Enrolled','lost'=>'Lost'];
    }
    static function timezone() { return new DateTimeZone('Asia/Kolkata'); }
    static function display_date($utc,$format='M j, Y · g:i a') {
        if(!is_string($utc)||$utc==='') { return '—'; }
        $timestamp=strtotime($utc.' UTC');
        return $timestamp===false?'—':wp_date($format,$timestamp,self::timezone()).' IST';
    }
    static function date_bound($value,$next_day=false) {
        if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)) { return null; }
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,self::timezone());
        $errors=DateTimeImmutable::getLastErrors();
        if(!$date||($errors!==false&&($errors['warning_count']||$errors['error_count']))||$date->format('Y-m-d')!==$value) { return null; }
        if($next_day) { $date=$date->modify('+1 day'); }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    static function variants() { return ['v1'=>'Digital Marketing — V1','v2'=>'Digital Marketing — V2','v3'=>'Digital Marketing — V3']; }
    static function tracking_keys() { return explode(' ','utm_source utm_medium utm_campaign utm_adgroup utm_term utm_content utm_id gclid fbclid gbraid wbraid landing_page referrer fbp fbc ga_client_id session_id fb_ad fb_campaign fb_adset_name fb_adset_id fb_lead_id adset_id ad_id city state'); }
    static function url($value) {
        $value=self::clean($value,2000);$parts=wp_parse_url($value);
        if(!is_array($parts)||!in_array($parts['scheme']??'',['https','http'],true)||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])) { return ''; }
        // Query attribution is stored in dedicated fields, never URL contact parameters.
        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'').($parts['path']??'/');
    }
    static function variant($value,$url='') {
        $value=self::clean($value,80);
        if (in_array($value,['v1','v2','v3'],true)) { return $value; }
        $path=trim((string)wp_parse_url($url,PHP_URL_PATH),'/');
        if($path==='ai-powered-digital-marketing-course') { return 'v2'; }
        if($path==='ai-powered-digital-marketing-training') { return 'v3'; }
        if(str_contains($path,'digital-marketing-v3')||str_contains($path,'digital-marketing-3')) { return 'v3'; }
        if(str_contains($path,'digital-marketing-v2')||str_contains($path,'digital-marketing-2')) { return 'v2'; }
        if($path==='ai-powered-digital-marketing') { return 'v1'; }
        return $value?:'unknown';
    }
    static function clean_details($details) {
        $clean=[];
        foreach(is_array($details)?array_slice($details,0,40,true):[] as $key=>$value) {
            if(is_string($key)&&is_scalar($value)) { $clean[sanitize_key($key)]=self::clean($value,1000); }
        }
        ksort($clean);return $clean;
    }
    /** Called only after the owning form adapter has validated its verification policy. Never sends to CRM. */
    static function ingest(array $p,array $context=[]) {
        global $wpdb;
        $id=self::clean($p['request_id']??'',80);
        $name=self::clean($p['name']??'',80);$phone=self::canonical_phone($p['phone']??'');
        $email=is_string($p['email']??null)?sanitize_email($p['email']):'';
        $source=$context['source']??($p['intent']??'');
        $optional_email=$source==='chatbot'&&$email==='';
        if(!preg_match('/^[A-Za-z0-9_.:-]{8,80}$/D',$id)||strlen($name)<2||!$phone||(!$optional_email&&!is_email($email))||strlen($email)>254||!isset(self::labels()[$source])) { return self::error('Please check the lead contact details.'); }
        $tracking=[];
        foreach(self::tracking_keys() as $key) { $tracking[$key]=self::clean($p['tracking'][$key]??'',500); }
        foreach(['landing_page','referrer'] as $key) { $tracking[$key]=self::url($tracking[$key])?:($key==='referrer'?'direct':''); }
        $url=self::url($p['submission_url']??($p['tracking']['submission_url']??''))?:$tracking['landing_page'];
        $variant=self::variant($context['page_variant']??($p['page_variant']??($p['tracking']['page_variant']??'')),$url);
        $data=['goal'=>self::clean($p['goal']??'',250),'details'=>self::clean_details($p['details']??[]),'status'=>self::clean($p['status']??'',250),'timeline'=>self::clean($p['timeline']??'',250),'question'=>self::clean($p['question']??'',500),'marketingConsent'=>($p['marketingConsent']??false)===true,'consent_text'=>self::clean($p['consent_text']??'Course news and marketing messages on WhatsApp and email; opt out anytime.',500),'request_permission'=>'Contact requested about course enquiry or brochure','tracking'=>$tracking,'page_variant'=>$variant,'submission_url'=>$url,'brochure_downloaded'=>($p['brochure_downloaded']??false)===true];
        $verified=!empty($context['otp_verified']);
        $fingerprint=self::hash(wp_json_encode([$name,$phone,$email,$source,$data,$verified]));
        $previous=$wpdb->suppress_errors(true);
        try {
            $existing=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE request_id=%s',$id));
            if($wpdb->last_error!=='') { return self::error('Your request could not be saved. Please retry.',503); }
            if($existing) { return self::existing($existing,$fingerprint,[$name,$phone,$email,$source,$data,$p]); }
            $now=current_time('mysql',true);$data['captured_at']=gmdate('c');
            $ok=$wpdb->insert(self::table(),['request_id'=>$id,'fingerprint'=>$fingerprint,'created_at'=>$now,'updated_at'=>$now,'name'=>$name,'phone'=>$phone,'email'=>$email,'source'=>$source,'page_variant'=>$variant,'submission_url'=>$url,'payload'=>wp_json_encode($data),'otp_verified'=>$verified?1:0,'advisor_note'=>'','review_history'=>'[]']);
            if($ok===1) { return ['success'=>true,'receipt'=>'LP-'.$wpdb->insert_id,'lead_id'=>(int)$wpdb->insert_id,'duplicate'=>false,'download_token'=>self::download_token($wpdb->insert_id,$id)]; }
            // A competing request can win the unique request_id insertion.
            $existing=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE request_id=%s',$id));
            if($existing) { return self::existing($existing,$fingerprint,[$name,$phone,$email,$source,$data,$p]); }
            return self::error('Your request could not be saved. Please retry.',503);
        } finally { $wpdb->suppress_errors($previous); }
    }
    static function existing($row,$fingerprint,$incoming=[]) {
        $matches=hash_equals($row->fingerprint,$fingerprint);
        // v0.1 receipts remain replayable after the schema upgrade. Reconstruct only
        // their old field set; never create a second row or send it to TeleCRM.
        if(!$matches && !array_key_exists('details',json_decode($row->payload,true)?:[]) && count($incoming)===6) {
            [$name,$phone,$email,$source,$data,$raw]=$incoming;
            $old=json_decode($row->payload,true)?:[];unset($old['captured_at']);
            $candidate=[];
            foreach($old as $key=>$value) {
                if($key==='tracking') {
                    $candidate[$key]=[];
                    foreach($value as $tracking_key=>$tracking_value) { $candidate[$key][$tracking_key]=self::clean($raw['tracking'][$tracking_key]??'',500); }
                } else { $candidate[$key]=$data[$key]??null; }
            }
            $legacy_phone=str_starts_with($phone,'+91')?substr($phone,3):$phone;
            $matches=hash_equals($row->fingerprint,self::hash(wp_json_encode([$name,$legacy_phone,$email,$source,$candidate])));
        }
        if(!$matches) { return self::error('This request was already saved with different details.',409); }
        return ['success'=>true,'receipt'=>'LP-'.$row->id,'lead_id'=>(int)$row->id,'duplicate'=>true,'download_token'=>self::download_token($row->id,$row->request_id)];
    }
    static function download_token($id,$request_id) { return self::hash('brochure-download|'.(int)$id.'|'.$request_id); }
    static function get($id) { global $wpdb;return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE id=%d',absint($id))); }
    /** Trusted Conversation Bridge adapter. Capturing/enriching a contact never sends to CRM. */
    static function sync_conversation(array $p,$existing_lead_id=0) {
        global $wpdb;
        $request_id=self::clean($p['request_id']??'',80);
        if(!preg_match('/^cb-conversation-([1-9][0-9]*)$/D',$request_id,$match)) { return self::error('A valid conversation reference is required.'); }
        $conversation_id=(int)$match[1];$phone=self::canonical_phone($p['phone']??'');$name=self::clean($p['name']??'',80);
        $email=is_string($p['email']??null)?sanitize_email($p['email']):'';
        $source=($p['source']??'')==='chatbot_brochure'?'chatbot_brochure':'chatbot';
        if(!$phone||strlen($name)<2||($email!==''&&!is_email($email))||strlen($email)>254||($source==='chatbot_brochure'&&$email==='')) { return self::error('A name and valid phone number are needed before this chat can be qualified.'); }
        $conversation_lock=self::lock('conversation-link-'.$conversation_id);if(!$conversation_lock) { return self::error('This conversation contact is being updated. Please retry.',409); }
        $lead_lock=false;
        try {
            $row=$existing_lead_id?self::get($existing_lead_id):$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE request_id=%s',$request_id));
            if($wpdb->last_error!=='') { return self::error('The conversation lead could not be loaded.',503); }
            if($existing_lead_id&&!$row) { return self::error('The linked lead is unavailable. Reload the conversation before retrying.',409); }
            if(!$row) {
                $p['request_id']=$request_id;$p['phone']=$phone;$p['email']=$email;$p['name']=$name;
                $p['details']=array_merge(is_array($p['details']??null)?$p['details']:[],['conversation_reference'=>'CB-'.$conversation_id,'conversation_id'=>(string)$conversation_id]);
                return self::ingest($p,['source'=>$source,'otp_verified'=>!empty($p['otp_verified'])]);
            }
            // A stable, server-owned link is required. Never associate sessions by phone alone.
            if(self::canonical_phone($row->phone)!==$phone) { return self::error('The conversation and linked lead have different phone numbers. Review the contact before syncing.',409); }
            $lead_lock=self::lock('lead-review-'.(int)$row->id);if(!$lead_lock) { return self::error('This lead is being reviewed. Please retry.',409); }
            $row=self::get($row->id);$data=json_decode($row->payload,true)?:[];
            $existing_reference=$data['details']['conversation_reference']??'';
            if($existing_reference!==''&&$existing_reference!=='CB-'.$conversation_id) { return self::error('This lead belongs to another conversation.',409); }
            $data['details']=array_merge($data['details']??[],self::clean_details($p['details']??[]),['conversation_reference'=>'CB-'.$conversation_id,'conversation_id'=>(string)$conversation_id]);
            if(array_key_exists('goal',$p)) { $data['goal']=self::clean($p['goal'],250); }
            if(($p['marketingConsent']??false)===true) { $data['marketingConsent']=true;$data['consent_text']=self::clean($p['consent_text']??($data['consent_text']??''),500); }
            foreach(self::tracking_keys() as $key) {
                if(empty($data['tracking'][$key])&&!empty($p['tracking'][$key])) { $data['tracking'][$key]=self::clean($p['tracking'][$key],500); }
            }
            foreach(['landing_page','referrer'] as $key) { $data['tracking'][$key]=self::url($data['tracking'][$key]??'')?:($key==='referrer'?'direct':''); }
            if(!empty($p['brochure_downloaded'])) { $data['brochure_downloaded']=true;if(empty($data['brochure_downloaded_at'])) { $data['brochure_downloaded_at']=gmdate('c'); } }
            $patch=['name'=>$name,'payload'=>wp_json_encode($data),'updated_at'=>current_time('mysql',true)];
            if($email!=='') { $patch['email']=$email; }
            if($source==='chatbot_brochure'&&$row->source==='chatbot') { $patch['source']='chatbot_brochure'; }
            if(!empty($p['otp_verified'])) { $patch['otp_verified']=1; }
            // Preserve the original form/page attribution when linking a brochure receipt.
            if($row->submission_url==='') { $patch['submission_url']=self::url($p['submission_url']??''); }
            if($row->page_variant===''||$row->page_variant==='unknown') { $patch['page_variant']=self::variant($p['page_variant']??'',$patch['submission_url']??$row->submission_url); }
            if($wpdb->update(self::table(),$patch,['id'=>$row->id])===false) { return self::error('The conversation contact could not be saved.',503); }
            return ['success'=>true,'receipt'=>'LP-'.$row->id,'lead_id'=>(int)$row->id,'duplicate'=>true,'download_token'=>self::download_token($row->id,$row->request_id)];
        } finally { if($lead_lock) { delete_option($lead_lock); }delete_option($conversation_lock); }
    }
    /** Called inside the Conversation Bridge privacy transaction; separate brochure records remain. */
    static function erase_conversation_lead($id,$conversation_id) {
        global $wpdb;$row=self::get($id);if(!$row) { return $wpdb->last_error===''; }
        if($row->request_id!=='cb-conversation-'.absint($conversation_id)) { return true; }
        return $wpdb->delete(self::table(),['id'=>(int)$id,'request_id'=>$row->request_id])!==false;
    }
    static function backfill_sources() {
        global $wpdb;$lock=self::lock('source-backfill');if(!$lock) { return ['processed'=>0,'updated'=>0,'complete'=>false]; }
        try {
            $cursor=(int)get_option('tlit_lp_sources_cursor',0);
            $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table().' WHERE id>%d ORDER BY id ASC LIMIT 100',$cursor));
            if($wpdb->last_error!=='') { return ['processed'=>0,'updated'=>0,'complete'=>false]; }
            $updated=0;
            foreach($rows as $row) {
                $data=json_decode($row->payload,true)?:[];$patch=[];
                $url=$row->submission_url?:self::url($data['submission_url']??($data['tracking']['submission_url']??($data['tracking']['landing_page']??'')));
                if($row->submission_url===''&&$url!=='') { $patch['submission_url']=$url; }
                if($row->page_variant===''||$row->page_variant==='unknown') {
                    $variant=self::variant($data['page_variant']??($data['tracking']['page_variant']??''),$url);
                    if(in_array($variant,['v1','v2','v3'],true)) { $patch['page_variant']=$variant; }
                }
                if($patch) { if($wpdb->update(self::table(),$patch,['id'=>$row->id])===false) { return ['processed'=>0,'updated'=>$updated,'complete'=>false]; }$updated++; }
                $cursor=(int)$row->id;
            }
            update_option('tlit_lp_sources_cursor',$cursor,false);$complete=count($rows)<100;
            if($complete) { update_option('tlit_lp_sources_backfill','1',false); }
            elseif(!wp_next_scheduled('tlit_lp_backfill_sources')) { wp_schedule_single_event(time()+10,'tlit_lp_backfill_sources'); }
            return ['processed'=>count($rows),'updated'=>$updated,'complete'=>$complete];
        } finally { delete_option($lock); }
    }
    static function update_details($id,array $updates) {
        global $wpdb;$lock=self::lock('lead-review-'.absint($id));if(!$lock) { return self::error('This lead is being updated. Please retry.',409); }
        try {
            $row=self::get($id);if(!$row) { return self::error('Lead not found.',404); }
            $data=json_decode($row->payload,true)?:[];
            $data['details']=array_merge($data['details']??[],self::clean_details($updates['details']??[]));
            if(!empty($updates['brochure_downloaded'])) { $data['brochure_downloaded']=true;$data['brochure_downloaded_at']=gmdate('c'); }
            return $wpdb->update(self::table(),['payload'=>wp_json_encode($data),'updated_at'=>current_time('mysql',true)],['id'=>$row->id])!==false?true:self::error('The details could not be saved.',503);
        } finally { delete_option($lock); }
    }
    static function filters($input=null) {
        global $wpdb;$input=is_array($input)?$input:wp_unslash($_GET);$sql=' WHERE 1=1';$values=[];
        foreach(['source','crm_status','lead_status','page_variant'] as $key) {
            $value=self::clean($input[$key]??'',80);if($value!=='') { $sql.=" AND $key=%s";$values[]=$value; }
        }
        foreach(['from'=>'>=','to'=>'<'] as $key=>$op) {
            $value=self::clean($input[$key]??'',10);
            if($value!=='') {
                $bound=self::date_bound($value,$key==='to');
                if($bound===null) { $sql.=' AND 1=0'; }
                else { $sql.=" AND created_at $op %s";$values[]=$bound; }
            }
        }
        foreach(['utm_source','utm_campaign'] as $key) {
            $value=self::clean($input[$key]??'',200);
            if($value!=='') { $sql.=" AND JSON_UNQUOTE(JSON_EXTRACT(payload,'$.tracking.$key'))=%s";$values[]=$value; }
        }
        $search=self::clean($input['search']??'',200);
        if($search!=='') { $sql.=' AND (name LIKE %s OR phone LIKE %s OR email LIKE %s)';$value='%'.$wpdb->esc_like($search).'%';array_push($values,$value,$value,$value); }
        return $values?$wpdb->prepare($sql,$values):$sql;
    }
    static function query($filters=[],$page=1,$per_page=25) {
        global $wpdb;$page=max(1,(int)$page);$per_page=max(1,min(100,(int)$per_page));$where=self::filters($filters);
        $rows=$wpdb->get_results('SELECT * FROM '.self::table().$where.' ORDER BY id DESC LIMIT '.$per_page.' OFFSET '.(($page-1)*$per_page));
        if($wpdb->last_error!=='') { return self::error('The lead inbox could not be loaded.',503); }
        $total=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.self::table().$where);
        if($wpdb->last_error!=='') { return self::error('The lead count could not be loaded.',503); }
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'per_page'=>$per_page];
    }
    static function counts($filters=[]) { global $wpdb;$rows=$wpdb->get_results('SELECT lead_status,COUNT(*) AS total FROM '.self::table().self::filters($filters).' GROUP BY lead_status');$counts=array_fill_keys(array_keys(self::statuses()),0);foreach($rows as $row) { $counts[$row->lead_status]=(int)$row->total; }return $counts; }
    static function save_note($id,$note) { $row=self::get($id);return $row?self::save_status($id,$row->lead_status,$note,get_current_user_id(),false):self::error('Lead not found.',404); }
    static function save_status($id,$status,$note,$actor=0,$deliver=true) {
        global $wpdb;
        if(!isset(self::statuses()[$status])) { return self::error('Choose a valid counsellor status.'); }
        $lock=self::lock('lead-review-'.absint($id));if(!$lock) { return self::error('This lead is being reviewed. Please retry.',409); }
        try {
            $row=self::get($id);if(!$row) { return self::error('Lead not found.',404); }
            $history=json_decode($row->review_history??'[]',true)?:[];$now=current_time('mysql',true);
            $history[]=['from'=>$row->lead_status,'to'=>$status,'actor'=>(int)$actor,'at'=>$now];$history=array_slice($history,-100);
            $saved=$wpdb->update(self::table(),['lead_status'=>$status,'advisor_note'=>substr(sanitize_textarea_field((string)$note),0,5000),'reviewed_by'=>absint($actor),'reviewed_at'=>$now,'updated_at'=>$now,'review_history'=>wp_json_encode($history)],['id'=>$row->id]);
            if($saved===false) { return self::error('The review could not be saved. TeleCRM was not contacted.',503); }
            if($deliver && isset(self::crm_statuses()[$status])) { self::push_locked($row->id); }
            return self::get($row->id);
        } finally { delete_option($lock); }
    }
    static function menu() {
        add_submenu_page('tlit-chat-bridge','Landing Page Leads','Landing Page Leads','manage_options','tlit-landing-leads',[__CLASS__,'render']);
        if(!class_exists('TLIT_CB_Admin')) { add_menu_page('Landing Page Leads','Landing Page Leads','manage_options','tlit-landing-leads',[__CLASS__,'render'],'dashicons-forms',59); }
    }
    static function admin() { if(!current_user_can('manage_options')) { wp_die('Access denied.','',['response'=>403]); } }
    static function render() { self::admin();if(class_exists('TLIT_LP_Admin')) { TLIT_LP_Admin::render(); }else { echo '<div class="wrap"><h1>Landing Page Leads</h1><p>The lead inbox interface is unavailable.</p></div>'; } }
    static function form_start($operation,$id=0) { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="tlit_lp_action"><input type="hidden" name="operation" value="'.esc_attr($operation).'"><input type="hidden" name="id" value="'.(int)$id.'">';wp_nonce_field('tlit_lp_action'); }
    static function action() {
        self::admin();if(($_SERVER['REQUEST_METHOD']??'')!=='POST') { wp_die('Use the review form.','',['response'=>405]); }check_admin_referer('tlit_lp_action');
        $p=wp_unslash($_POST);$operation=sanitize_key($p['operation']??'');$id=absint($p['id']??0);$result=true;
        if($operation==='settings') { $result=self::save_settings($p); }
        elseif($operation==='test_connection') { $result=self::test_connection(); }
        elseif($operation==='status') { $result=self::save_status($id,sanitize_key($p['lead_status']??($p['status']??'')),$p['note']??'',get_current_user_id()); }
        elseif($operation==='note') { $result=self::save_note($id,$p['note']??''); }
        elseif($operation==='resolve' && !empty($p['confirmed'])) { $result=self::reconcile($id,($p['resolution']??'')==='received'); }
        elseif($operation==='push') { $row=self::get($id);$result=$row?self::save_status($id,$row->lead_status,$row->advisor_note,get_current_user_id()):self::error('Lead not found.',404); }
        else { $result=self::error('Unknown action.'); }
        if(is_wp_error($result)) { wp_die(esc_html($result->get_error_message())); }
        $notice=$operation==='test_connection'?'configuration_checked':'saved';
        wp_safe_redirect(admin_url('admin.php?page=tlit-landing-leads'.($id?'&lead='.$id:'').($operation==='settings'||$operation==='test_connection'?'&view=integrations':'').'&notice='.$notice));exit;
    }
    static function export() {
        self::admin();check_admin_referer('tlit_lp_export');global $wpdb;$id=absint($_GET['lead']??0);$where=$id?$wpdb->prepare(' WHERE id=%d',$id):self::filters();
        nocache_headers();header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="landing-page-leads-'.gmdate('Ymd').'.csv"');
        $out=fopen('php://output','w');fputcsv($out,array_merge(['Reference','Received IST','Name','Phone','Email','Form','Page version','Submission URL','Goal','Profile','Timeline','Question','Brochure downloaded','Marketing consent','OTP verified','Counsellor status','TeleCRM status','Counsellor note'],self::tracking_keys(),['Details JSON']));$last=0;
        do { $rows=$wpdb->get_results('SELECT * FROM '.self::table().$where.' AND id>'.(int)$last.' ORDER BY id ASC LIMIT 500');foreach($rows as $row) { $data=json_decode($row->payload,true)?:[];$cells=['LP-'.$row->id,self::display_date($row->created_at,'Y-m-d H:i:s'),$row->name,$row->phone,$row->email,self::labels()[$row->source]??$row->source,$row->page_variant,$row->submission_url,$data['goal']??'',$data['status']??'',$data['timeline']??'',$data['question']??'',!empty($data['brochure_downloaded'])?'Yes':'No',!empty($data['marketingConsent'])?'Yes':'No',$row->otp_verified?'Yes':'No',self::statuses()[$row->lead_status]??$row->lead_status,$row->crm_status,$row->advisor_note];foreach(self::tracking_keys() as $tracking_key) { $cells[]=$data['tracking'][$tracking_key]??''; }$cells[]=wp_json_encode($data['details']??[]);fputcsv($out,array_map(function($v){$v=(string)$v;return preg_match('/^[\s]*[=+@-]/',$v)?"'".$v:$v;},$cells));$last=$row->id; } }while(count($rows)===500);fclose($out);exit;
    }
}
if(function_exists('register_activation_hook')) { register_activation_hook(__FILE__,['TLIT_LP_Leads','install']); }
add_action('plugins_loaded',['TLIT_LP_Leads','boot'],20);
}
