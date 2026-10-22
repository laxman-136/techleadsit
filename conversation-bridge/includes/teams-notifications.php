<?php
/** Automatic new-lead alerts. This service never qualifies leads or calls TeleCRM. */
if (!defined('ABSPATH')) { exit; }

final class TLIT_Teams_Notifications {
    const OPTION='tlit_teams_notifications';
    const SCHEMA='1';
    const DROPOFF_SECONDS=180;
    private static $queued=false;
    private static $erased=array();

    public static function register() {
        add_action('tlit_lp_lead_created',array(__CLASS__,'form_saved'),10,1);
        add_action('tlit_lp_draft_saved',array(__CLASS__,'draft_saved'),10,1);
        add_action('tlit_cb_contact_saved',array(__CLASS__,'chat_saved'),10,1);
        add_action('admin_post_tlit_teams_settings',array(__CLASS__,'admin_action'));
        add_action('tlit_teams_deliver',array(__CLASS__,'deliver'),10,1);
        add_action('tlit_teams_dropoff_check',array(__CLASS__,'dropoff_check'),10,1);
        add_action('tlit_teams_sweep',array(__CLASS__,'sweep'));
        add_action('init',array(__CLASS__,'ensure_schedule'),8);
        add_filter('cron_schedules',array(__CLASS__,'schedules'));
        add_action('shutdown',array(__CLASS__,'wake_queue'));
        add_filter('tlit_cb_before_conversation_erase',array(__CLASS__,'erase'),30,2);
        add_action('tlit_cb_conversation_erased',array(__CLASS__,'erased'),20,1);
    }
    public static function table() { global $wpdb;return $wpdb->prefix.'tlit_teams_notifications'; }
    public static function schedules($items) { $items['tlit_teams_minute']=array('interval'=>60,'display'=>'Teams notification queue: every minute');return $items; }
    public static function install() {
        if(get_option('tlit_teams_schema')===self::SCHEMA) { return; }
        global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta('CREATE TABLE '.self::table()." (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            identity_key varchar(100) NOT NULL,
            entity_type varchar(20) NOT NULL,
            entity_id bigint(20) unsigned NOT NULL,
            notification_id varchar(64) NOT NULL,
            endpoint_key varchar(64) NOT NULL,
            state varchar(20) NOT NULL DEFAULT 'pending',
            attempts int(10) unsigned NOT NULL DEFAULT 0,
            retry_count int(10) unsigned NOT NULL DEFAULT 0,
            response_code int(10) unsigned NOT NULL DEFAULT 0,
            last_error varchar(255) NOT NULL DEFAULT '',
            next_attempt datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY identity_key (identity_key),
            KEY delivery_state (state,next_attempt)
        ) ENGINE=InnoDB ".$wpdb->get_charset_collate().';');
        $columns=$wpdb->get_col('SHOW COLUMNS FROM '.self::table());
        if(is_array($columns)&&!array_diff(array('identity_key','entity_type','entity_id','notification_id','endpoint_key','state','next_attempt'),$columns)) { update_option('tlit_teams_schema',self::SCHEMA,false); }
    }
    public static function options() {
        $o=get_option(self::OPTION,array());
        return wp_parse_args(is_array($o)?$o:array(),array('enabled'=>0,'forms'=>1,'chatbot'=>1,'endpoint_sealed'=>'','dropoffs_started_at'=>''));
    }
    private static function error($message) { return new WP_Error('tlit_teams',$message); }
    public static function valid_url($url) {
        if(!is_string($url)||strlen($url)>8192||preg_match('/[\r\n]/',$url)) { return false; }
        $p=wp_parse_url($url);
        return is_array($p)&&($p['scheme']??'')==='https'&&!isset($p['user'])&&!isset($p['pass'])&&!isset($p['fragment'])
            &&(!isset($p['port'])||(int)$p['port']===443)
            &&preg_match('/\A[a-z0-9.-]+\.(?:logic\.azure\.com|environment\.api\.powerplatform\.com)\z/i',$p['host']??'')===1
            &&preg_match('~/workflows/[A-Za-z0-9_-]+/triggers/[A-Za-z0-9_-]+/paths/invoke/?$~D',$p['path']??'')===1;
    }
    private static function seal($value) {
        if(!function_exists('openssl_encrypt')) { return ''; }
        $iv=random_bytes(12);$tag='';$body=openssl_encrypt($value,'aes-256-gcm',hash('sha256',wp_salt('auth').'teams-notifications',true),OPENSSL_RAW_DATA,$iv,$tag);
        return $body===false?'':base64_encode($iv.$tag.$body);
    }
    private static function endpoint($settings=null) {
        if(!function_exists('openssl_decrypt')) { return ''; }
        $raw=base64_decode(($settings??self::options())['endpoint_sealed'],true);
        if(!$raw||strlen($raw)<29) { return ''; }
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',wp_salt('auth').'teams-notifications',true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
        return is_string($plain)&&self::valid_url($plain)?$plain:'';
    }
    private static function endpoint_key($url) { return hash_hmac('sha256',$url,wp_salt('auth')); }
    public static function save_settings(array $p) {
        $o=self::options();$previous=$o;$url=is_string($p['webhook_url']??null)?trim($p['webhook_url']):'';
        if($url!=='') {
            if(!self::valid_url($url)) { return self::error('Enter the HTTPS webhook URL generated by Microsoft Teams Workflows or Power Automate.'); }
            $sealed=self::seal($url);if($sealed==='') { return self::error('OpenSSL is required to store the webhook URL securely.'); }
            $o['endpoint_sealed']=$sealed;
        }
        if(!empty($p['remove_url'])) { $o['endpoint_sealed']=''; }
        $o['enabled']=empty($p['enabled'])?0:1;$o['forms']=empty($p['forms'])?0:1;$o['chatbot']=empty($p['chatbot'])?0:1;
        if($o['enabled']&&(self::endpoint($o)===''||(!$o['forms']&&!$o['chatbot']))) { return self::error('Save a valid webhook URL and select at least one lead source before enabling alerts.'); }
        if($o['enabled']) {
            global $wpdb;
            if($o['forms']&&(empty($previous['enabled'])||empty($previous['forms']))) {
                $o['forms_after_id']=is_callable(array('TLIT_LP_Leads','table'))?(int)$wpdb->get_var('SELECT MAX(id) FROM '.TLIT_LP_Leads::table()):0;
            }
            if($o['chatbot']&&(empty($previous['enabled'])||empty($previous['chatbot']))) { $o['chat_started_at']=gmdate('Y-m-d H:i:s'); }
            if($o['forms']&&(empty($previous['enabled'])||empty($previous['forms'])||empty($o['dropoffs_started_at']))) { $o['dropoffs_started_at']=gmdate('Y-m-d H:i:s'); }
        }
        if(update_option(self::OPTION,$o,false)===false&&self::options()!==$o) { return self::error('The notification settings could not be saved.'); }
        if($o['enabled']&&!wp_next_scheduled('tlit_teams_sweep')) { wp_schedule_event(time()+60,'tlit_teams_minute','tlit_teams_sweep'); }
        if(!$o['enabled']) { wp_clear_scheduled_hook('tlit_teams_sweep'); }
        return true;
    }
    public static function ensure_schedule() {
        $o=self::options();
        if(!empty($o['enabled'])&&!wp_next_scheduled('tlit_teams_sweep')) { wp_schedule_event(time()+60,'tlit_teams_minute','tlit_teams_sweep'); }
        // Start abandonment tracking at deployment time. Existing historical
        // drafts are intentionally excluded from new Teams notifications.
        if(!empty($o['enabled'])&&!empty($o['forms'])&&empty($o['dropoffs_started_at'])) {
            $o['dropoffs_started_at']=gmdate('Y-m-d H:i:s');update_option(self::OPTION,$o,false);
        }
    }
    private static function enabled($type) { $o=self::options();return !empty($o['enabled'])&&!empty($o[$type==='chatbot'?'chatbot':'forms']); }
    public static function form_saved($id) {
        try {
            if(!is_callable(array('TLIT_LP_Leads','get'))) { return; }
            $row=TLIT_LP_Leads::get((int)$id);if(!$row||$row->source==='chatbot') { return; }
            $data=json_decode($row->payload,true)?:array();
            $conversation_id=(int)($data['details']['conversation_id']??0);
            if($row->source==='chatbot_brochure'&&$conversation_id>0) { self::chat_saved($conversation_id);return; }
            self::queue('form',(int)$id);
        } catch(Throwable $error) { update_option('tlit_teams_queue_error',1,false); }
    }
    public static function chat_saved($id) {
        try { self::queue('chatbot',(int)$id); }
        catch(Throwable $error) { update_option('tlit_teams_queue_error',1,false); }
    }
    public static function draft_saved($id) {
        try {
            if(!self::enabled('dropoff')||!is_callable(array('TLIT_LP_Leads','get_draft'))) { return; }
            $row=TLIT_LP_Leads::get_draft((int)$id);
            if(!$row||($row->lifecycle_status??'')==='verified'||trim((string)($row->name??''))===''||self::canonical_phone((string)($row->phone??''))==='') { return; }
            if(!wp_next_scheduled('tlit_teams_dropoff_check',array((int)$id))) { wp_schedule_single_event(time()+self::DROPOFF_SECONDS,'tlit_teams_dropoff_check',array((int)$id)); }
            self::$queued=true;
        } catch(Throwable $error) { update_option('tlit_teams_queue_error',1,false); }
    }
    public static function dropoff_check($id) {
        try {
            if(!self::enabled('dropoff')||!is_callable(array('TLIT_LP_Leads','get_draft'))) { return; }
            $row=TLIT_LP_Leads::get_draft((int)$id);
            if(!$row||($row->lifecycle_status??'')==='verified'||trim((string)($row->name??''))===''||self::canonical_phone((string)($row->phone??''))==='') { return; }
            $started=self::options()['dropoffs_started_at']??'';
            if($started===''||(string)$row->updated_at<$started) { return; }
            $last=strtotime((string)$row->updated_at.' UTC');$due=($last===false?time():$last)+self::DROPOFF_SECONDS;
            if($due>time()) { wp_schedule_single_event($due,'tlit_teams_dropoff_check',array((int)$id));return; }
            self::queue('dropoff',(int)$id);
        } catch(Throwable $error) { update_option('tlit_teams_queue_error',1,false); }
    }
    /** No network and no contact snapshot: safe even inside a caller's transaction. */
    public static function queue($type,$id) {
        if(!in_array($type,array('form','chatbot','dropoff'),true)||$id<1||!self::enabled($type)) { return false; }
        $url=self::endpoint();if($url===''||!self::lead($type,$id)) { return false; }
        global $wpdb;$prefix=array('form'=>'LP-','chatbot'=>'CB-','dropoff'=>'FD-')[$type];$identity=$prefix.$id;$now=gmdate('Y-m-d H:i:s');
        $ok=$wpdb->query($wpdb->prepare('INSERT INTO '.self::table()." (identity_key,entity_type,entity_id,notification_id,endpoint_key,state,next_attempt,created_at,updated_at) VALUES (%s,%s,%d,%s,%s,'pending',%s,%s,%s) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)",$identity,$type,$id,wp_generate_uuid4(),self::endpoint_key($url),$now,$now,$now));
        if($ok===false) { update_option('tlit_teams_queue_error',1,false);return false; }
        $job_id=(int)$wpdb->insert_id;
        $job=$wpdb->get_row($wpdb->prepare('SELECT state,next_attempt FROM '.self::table().' WHERE id=%d',$job_id),ARRAY_A);
        // Scheduling is only a wake-up hint. The minute sweep repairs a missed event.
        if($job&&in_array($job['state'],array('pending','retry'),true)) {
            $due=max(time(),strtotime($job['next_attempt'].' UTC'));
            if(!wp_next_scheduled('tlit_teams_deliver',array($job_id))) { wp_schedule_single_event($due,'tlit_teams_deliver',array($job_id)); }
            self::$queued=true;
        }
        return $job_id;
    }
    public static function wake_queue() {
        if(!self::$queued||(defined('DISABLE_WP_CRON')&&DISABLE_WP_CRON)||(defined('WP_HTTP_BLOCK_EXTERNAL')&&WP_HTTP_BLOCK_EXTERNAL)) { return; }
        if(function_exists('spawn_cron')) { spawn_cron(time()); }
    }
    private static function canonical_phone($phone) {
        if(is_callable(array('TLIT_LP_Leads','canonical_phone'))) { return TLIT_LP_Leads::canonical_phone($phone); }
        return is_string($phone)&&preg_match('/^\+[1-9][0-9]{6,14}$/D',$phone)?$phone:'';
    }
    public static function lead($type,$id) {
        if($type==='form') {
            if(!is_callable(array('TLIT_LP_Leads','get'))) { return null; }
            $row=TLIT_LP_Leads::get($id);if(!$row||$row->source==='chatbot') { return null; }
            $data=json_decode($row->payload,true)?:array();$tracking=$data['tracking']??array();
            $lead=array('reference'=>'LP-'.$id,'event'=>'form','name'=>$row->name,'phone'=>$row->phone,'email'=>$row->email,'goal'=>$data['goal']??'','variant'=>$row->page_variant,'source'=>TLIT_LP_Leads::labels()[$row->source]??$row->source,'page'=>$row->submission_url,'created'=>$row->created_at,'verified'=>!empty($row->otp_verified),'language'=>$data['details']['preferred_language']??'','tracking'=>$tracking,'url'=>admin_url('admin.php?page=tlit-landing-leads&lead='.$id));
        } elseif($type==='chatbot') {
            if(!class_exists('TLIT_CB_Store')) { return null; }
            $row=TLIT_CB_Store::get($id);
            if(!$row||($row['course']??'')!=='digital_marketing') { return null; }
            $tracking=json_decode($row['tracking_json']??'{}',true)?:array();$variant=is_callable(array('TLIT_LP_Leads','variant'))?TLIT_LP_Leads::variant('',$row['source_url']??''):'';
            $lead=array('reference'=>'CB-'.$id,'event'=>'chatbot','name'=>$row['name'],'phone'=>$row['phone'],'email'=>$row['email'],'goal'=>$row['goal'],'variant'=>$variant,'source'=>!empty($row['has_brochure'])?'Chatbot / brochure request':'Chatbot enquiry','page'=>$row['source_url'],'created'=>$row['created_at'],'verified'=>false,'language'=>$row['language'],'tracking'=>$tracking,'url'=>admin_url('admin.php?page=tlit-chat-bridge&conversation='.$id));
        } elseif($type==='dropoff') {
            if(!is_callable(array('TLIT_LP_Leads','get_draft'))) { return null; }
            $row=TLIT_LP_Leads::get_draft($id);
            if(!$row||($row->lifecycle_status??'')==='verified') { return null; }
            $started=self::options()['dropoffs_started_at']??'';
            if($started===''||(string)$row->updated_at<$started) { return null; }
            $last=strtotime((string)$row->updated_at.' UTC');
            if($last===false||$last>time()-self::DROPOFF_SECONDS) { return null; }
            $data=json_decode($row->payload??'{}',true)?:array();$tracking=$data['tracking']??array();
            $status=is_callable(array('TLIT_LP_Leads','draft_status'))?TLIT_LP_Leads::draft_status($row):($row->lifecycle_status??'incomplete');
            $labels=array('incomplete'=>'Incomplete form','otp_pending'=>'OTP pending','otp_failed'=>'OTP failed or expired');
            $lead=array('reference'=>'FD-'.$id,'event'=>'dropoff','name'=>$row->name,'phone'=>$row->phone,'email'=>$row->email,'goal'=>$data['values']['goal']??'','variant'=>$row->page_variant,'source'=>TLIT_LP_Leads::labels()[$row->source]??$row->source,'page'=>$row->submission_url,'created'=>$row->created_at,'updated'=>$row->updated_at,'verified'=>false,'language'=>$data['details']['preferred_language']??'','tracking'=>$tracking,'progress'=>'Step '.(int)$row->current_step.' of '.max(1,(int)$row->total_steps),'dropoff_status'=>$labels[$status]??'Incomplete form','url'=>admin_url('admin.php?page=tlit-landing-leads&view=dropoffs&draft='.$id));
        } else { return null; }
        return trim($lead['name'])!==''&&self::canonical_phone($lead['phone'])!==''?$lead:null;
    }
    private static function text($value,$limit=600) {
        $text=is_scalar($value)?sanitize_text_field((string)$value):'';
        $text=function_exists('mb_substr')?mb_substr($text,0,$limit):substr($text,0,$limit);
        // Teams supports Markdown in facts. Keep visitor-controlled values literal.
        return str_replace(array('\\','*','_','[',']','`'),array('\\\\','\\*','\\_','\\[','\\]','\\`'),$text);
    }
    public static function card(array $lead,$notification_id,$test=false) {
        $event=$lead['event']??'form';$dropoff=$event==='dropoff';$chatbot=$event==='chatbot';$stamp=strtotime(($dropoff?($lead['updated']??''):($lead['created']??'')).' UTC');
        $facts=array('Name'=>$lead['name'],'Mobile'=>$lead['phone'],'Course'=>'AI Powered Digital Marketing','Page version'=>strtoupper($lead['variant']??''),'Enquiry'=>$lead['source'],($dropoff?'Last activity (IST)':'Submitted (IST)')=>$stamp===false?'Not recorded':wp_date('d M Y, g:i a',$stamp,new DateTimeZone('Asia/Kolkata')));
        if($dropoff) { $facts['Progress']=$lead['progress']??'';$facts['Status']=$lead['dropoff_status']??'Incomplete form'; }
        foreach(array('Email'=>'email','Language'=>'language','Learning goal'=>'goal','Submitted from'=>'page') as $label=>$key) { if(!empty($lead[$key])) { $facts[$label]=$lead[$key]; } }
        if(!empty($lead['team_pin'])) { $facts['Bridge PIN']=$lead['team_pin'];$facts['Bridge access until (IST)']=$lead['team_expires']; }
        foreach(array('Campaign source'=>'utm_source','Campaign'=>'utm_campaign') as $label=>$key) { if(!empty($lead['tracking'][$key])) { $facts[$label]=$lead['tracking'][$key]; } }
        $facts['Mobile verification']=!empty($lead['verified'])?'OTP verified':'Not OTP verified';
        $facts['Reference']=$lead['reference'];$facts['Notification ID']=$notification_id;
        $items=array();foreach($facts as $title=>$value) { if($value!=='') { $items[]=array('title'=>$title,'value'=>self::text($value)); } }
        $title=$dropoff?'Form started but not completed':($chatbot?'New chatbot lead':'Successful landing-page submission');
        $summary=$dropoff?self::text($lead['name']).' started filling the form on '.strtoupper(self::text($lead['variant']??'the landing page')).' but did not complete it.':($chatbot?'A chatbot visitor shared a valid mobile number and is ready for contact.':'A landing-page enquiry was submitted successfully and is ready for your team to contact.');
        $card=array('$schema'=>'http://adaptivecards.io/schemas/adaptive-card.json','type'=>'AdaptiveCard','version'=>'1.2','body'=>array(
            array('type'=>'TextBlock','text'=>$test?'TEST — Teams notifications connected':$title,'weight'=>'Bolder','size'=>'Medium','wrap'=>true),
            array('type'=>'TextBlock','text'=>$test?'Synthetic test only. No visitor was submitted and no CRM action was taken.':$summary,'wrap'=>true),
            array('type'=>'FactSet','facts'=>$items)));
        if(!$test&&!empty($lead['url'])) { $card['actions']=array(array('type'=>'Action.OpenUrl','title'=>'Open in WordPress Bridge','url'=>$lead['url'])); }
        return array('type'=>'message','attachments'=>array(array('contentType'=>'application/vnd.microsoft.card.adaptive','contentUrl'=>null,'content'=>$card)));
    }
    private static function post($url,$payload,$id) {
        return wp_safe_remote_post($url,array('timeout'=>15,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>4096,'headers'=>array('Content-Type'=>'application/json','Idempotency-Key'=>$id),'body'=>wp_json_encode($payload)));
    }
    public static function deliver($id) {
        global $wpdb;$table=self::table();$id=absint($id);$now=gmdate('Y-m-d H:i:s');
        $won=$wpdb->query($wpdb->prepare("UPDATE $table SET state='sending',attempts=attempts+1,updated_at=%s WHERE id=%d AND state IN ('pending','retry') AND next_attempt<=%s",$now,$id,$now));
        if($won!==1) { return; }
        $job=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id),ARRAY_A);if(!$job) { return; }
        $patch=array('state'=>'uncertain','last_error'=>'Delivery could not be confirmed. Check the flow run history before retrying.','updated_at'=>$now);
        try {
            $url=self::endpoint();$lead=self::lead($job['entity_type'],(int)$job['entity_id']);
            if(!$lead) { $patch['state']='cancelled';$patch['last_error']='The contact is missing or no longer has a valid mobile number.'; }
            elseif(!self::enabled($job['entity_type'])||$url===''||!hash_equals($job['endpoint_key'],self::endpoint_key($url))) { $patch['state']='held';$patch['last_error']='Settings changed or alerts were disabled. Review before retrying.'; }
            else {
                if($job['entity_type']!=='dropoff'&&class_exists('TLIT_CB_Team_Access')) {
                    $prepared=TLIT_CB_Team_Access::for_notification($lead);
                    if(is_wp_error($prepared)) { $wpdb->update($table,array('state'=>'held','last_error'=>'The private Bridge link could not be prepared. Check team access settings before retrying.','updated_at'=>$now),array('id'=>$id,'state'=>'sending'));return; }
                    $lead=$prepared;
                }
                $response=self::post($url,self::card($lead,$job['notification_id']),$job['notification_id']);
                if(!is_wp_error($response)) {
                    $code=(int)wp_remote_retrieve_response_code($response);$patch['response_code']=$code;
                    if($code>=200&&$code<300) { $patch['state']='accepted';$patch['last_error']='Power Automate accepted the alert. Teams posting is shown in the flow run history.'; }
                    elseif($code===429&&(int)$job['retry_count']<3) {
                        $count=(int)$job['retry_count'];$delay=array(60,300,900)[$count];$retry_after=wp_remote_retrieve_header($response,'retry-after');
                        if(is_scalar($retry_after)&&ctype_digit((string)$retry_after)) { $delay=max($delay,min(3600,(int)$retry_after)); }
                        $patch['state']='retry';$patch['retry_count']=$count+1;$patch['next_attempt']=gmdate('Y-m-d H:i:s',time()+$delay);$patch['last_error']='Power Automate is throttling requests. A retry is scheduled.';
                        wp_schedule_single_event(time()+$delay,'tlit_teams_deliver',array($id));
                    } elseif($code>=300&&$code<500) { $patch['state']='failed';$patch['last_error']=$code===429?'Retry limit reached. Check the flow before retrying.':'Power Automate rejected the request (HTTP '.$code.'). Check the URL, Anyone trigger access and flow configuration.'; }
                }
            }
        } catch(Throwable $error) { /* Never log a webhook secret, response body or contact data. */ }
        if ($wpdb->update($table,$patch,array('id'=>$id,'state'=>'sending')) !== false) { do_action('tlit_teams_delivery_updated',$job['entity_type'],(int)$job['entity_id'],$patch['state']); }
    }
    public static function sweep() {
        self::repair_queue();
        global $wpdb;$table=self::table();$now=gmdate('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare("UPDATE $table SET state='uncertain',last_error='An interrupted delivery needs a flow-history check before retrying.' WHERE state='sending' AND updated_at<%s",gmdate('Y-m-d H:i:s',time()-120)));
        $ids=$wpdb->get_col($wpdb->prepare("SELECT id FROM $table WHERE state IN ('pending','retry') AND next_attempt<=%s ORDER BY id ASC LIMIT 10",$now));
        foreach($ids as $id) { self::deliver((int)$id); }
    }
    /** Repair missed enqueue events from future captures, without forwarding a historical backlog. */
    public static function repair_queue() {
        global $wpdb;$o=self::options();$table=self::table();$ok=true;
        if(empty($o['enabled'])||self::endpoint()==='') { return; }
        if(!empty($o['forms'])&&isset($o['forms_after_id'])&&is_callable(array('TLIT_LP_Leads','table'))) {
            $leads=TLIT_LP_Leads::table();
            $ids=$wpdb->get_col($wpdb->prepare("SELECT l.id FROM $leads l LEFT JOIN $table n ON n.identity_key=CONCAT('LP-',l.id) WHERE l.id>%d AND (l.source IN ('general','popup','brochure') OR (l.source='chatbot_brochure' AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(l.payload,'$.details.conversation_id')),'') NOT REGEXP '^[1-9][0-9]*$')) AND n.id IS NULL ORDER BY l.id ASC LIMIT 25",(int)$o['forms_after_id']));
            if($wpdb->last_error!=='') { $ok=false; }
            else { foreach($ids as $id) { if(!self::queue('form',(int)$id)) { $ok=false; } } }
        }
        if(!empty($o['forms'])&&!empty($o['dropoffs_started_at'])&&is_callable(array('TLIT_LP_Leads','draft_table'))) {
            $drafts=TLIT_LP_Leads::draft_table();
            $ids=$wpdb->get_col($wpdb->prepare("SELECT d.id FROM $drafts d LEFT JOIN $table n ON n.identity_key=CONCAT('FD-',d.id) WHERE d.updated_at>=%s AND d.updated_at<=%s AND d.lifecycle_status<>'verified' AND d.name<>'' AND d.phone REGEXP '^[+][1-9][0-9]{6,14}$' AND n.id IS NULL ORDER BY d.id ASC LIMIT 25",$o['dropoffs_started_at'],gmdate('Y-m-d H:i:s',time()-self::DROPOFF_SECONDS)));
            if($wpdb->last_error!=='') { $ok=false; }
            else { foreach($ids as $id) { if(!self::queue('dropoff',(int)$id)) { $ok=false; } } }
        }
        if(!empty($o['chatbot'])&&!empty($o['chat_started_at'])&&class_exists('TLIT_CB_Store')) {
            $conversations=$wpdb->prefix.'tlit_cb_conversations';
            $ids=$wpdb->get_col($wpdb->prepare("SELECT c.id FROM $conversations c LEFT JOIN $table n ON n.identity_key=CONCAT('CB-',c.id) WHERE c.course='digital_marketing' AND c.updated_at>=%s AND c.phone REGEXP '^[+][1-9][0-9]{6,14}$' AND c.name<>'' AND n.id IS NULL ORDER BY c.id ASC LIMIT 25",$o['chat_started_at']));
            if($wpdb->last_error!=='') { $ok=false; }
            else { foreach($ids as $id) { if(!self::queue('chatbot',(int)$id)) { $ok=false; } } }
        }
        if($ok) { delete_option('tlit_teams_queue_error'); }
    }
    public static function retry($id,$confirmed=false) {
        global $wpdb;$table=self::table();$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",absint($id)),ARRAY_A);
        if(!$row||!in_array($row['state'],array('failed','held','uncertain'),true)) { return self::error('Only failed, held or uncertain notifications can be retried. Accepted alerts are not resent.'); }
        if($row['state']==='uncertain'&&!$confirmed) { return self::error('Check that this notification is absent from Teams and the flow run history, then confirm before retrying.'); }
        $url=self::endpoint();if(!self::enabled($row['entity_type'])||$url==='') { return self::error('Enable this notification source and save a valid webhook URL first.'); }
        $now=gmdate('Y-m-d H:i:s');
        $saved=$wpdb->update($table,array('state'=>'pending','retry_count'=>0,'endpoint_key'=>self::endpoint_key($url),'next_attempt'=>$now,'updated_at'=>$now,'last_error'=>''),array('id'=>$row['id'],'state'=>$row['state']));
        if($saved!==1) { return self::error('The notification changed. Reload before retrying.'); }
        wp_schedule_single_event(time(),'tlit_teams_deliver',array((int)$row['id']));self::$queued=true;return true;
    }
    public static function test() {
        $url=self::endpoint();if($url==='') { return self::error('Save the webhook URL before sending a test.'); }
        $id='test-'.wp_generate_uuid4();
        $payload=self::card(array('reference'=>'TEST ONLY','name'=>'Sample lead — not a real visitor','phone'=>'+91 90000 00000','email'=>'sample@example.invalid','variant'=>'v1 / v2 / v3','source'=>'Connection test','goal'=>'Example course enquiry','created'=>gmdate('Y-m-d H:i:s'),'verified'=>false),$id,true);
        $response=self::post($url,$payload,$id);
        if(is_wp_error($response)) { return self::error('The test response was not confirmed. Check the flow run history before sending another test.'); }
        $code=(int)wp_remote_retrieve_response_code($response);
        return $code>=200&&$code<300?true:self::error('Power Automate returned HTTP '.$code.'. Check the URL, trigger access and flow run history.');
    }
    public static function admin_action() {
        if(!current_user_can('manage_options')) { wp_die('Access denied.','',array('response'=>403)); }
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST') { wp_die('Use the notifications form.','',array('response'=>405)); }
        check_admin_referer('tlit_teams_settings');$p=wp_unslash($_POST);$operation=is_string($p['operation']??null)?$p['operation']:'';
        if($operation==='save') { $result=self::save_settings($p); }
        elseif($operation==='test') { $result=self::test(); }
        elseif($operation==='retry') { $result=self::retry(is_scalar($p['job_id']??null)?absint($p['job_id']):0,!empty($p['confirmed'])); }
        else { $result=self::error('Unknown notification action.'); }
        if(is_wp_error($result)) { wp_die(esc_html($result->get_error_message()),'',array('response'=>400)); }
        wp_safe_redirect(admin_url('admin.php?page=tlit-chat-bridge&view=integrations&teams_notice='.sanitize_key($operation)));exit;
    }
    private static function form($operation) {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="tlit_teams_settings"><input type="hidden" name="operation" value="'.esc_attr($operation).'">';wp_nonce_field('tlit_teams_settings');
    }
    public static function render() {
        if(!current_user_can('manage_options')) { return; }
        global $wpdb;$o=self::options();$configured=self::endpoint()!=='';
        echo '<div class="tlit-teams-grid"><section class="tlit-cb-side-panel"><p class="tlit-cb-eyebrow">MICROSOFT TEAMS</p><h2>Lead and drop-off notifications</h2><p>Alert your team when a landing-page form is submitted, when a named visitor with a valid mobile number leaves a form incomplete for 3 minutes, or when a chatbot contact is captured. Counsellor review and TeleCRM continue separately.</p>';
        $notice=is_string($_GET['teams_notice']??null)?$_GET['teams_notice']:'';
        $messages=array('save'=>'Notification settings saved. Existing leads are not sent retrospectively.','test'=>'Power Automate accepted the synthetic test. Check your Teams group and flow run history.','retry'=>'Notification queued for retry.');
        if(isset($messages[$notice])) { echo '<div class="notice notice-success inline"><p>'.esc_html($messages[$notice]).'</p></div>'; }
        if(get_option('tlit_teams_queue_error')) { echo '<div class="notice notice-error inline"><p>Some alerts could not be queued. Leads remain saved in WordPress. Check database availability and the notification table.</p></div>'; }
        self::form('save');
        echo '<label class="tlit-teams-check"><input type="checkbox" name="enabled" value="1" '.checked(!empty($o['enabled']),true,false).'> Enable Teams notifications</label><fieldset><legend>Notify for</legend><label class="tlit-teams-check"><input type="checkbox" name="forms" value="1" '.checked(!empty($o['forms']),true,false).'> Successful form submissions and qualified form drop-offs — all three Digital Marketing pages</label><p class="description">A drop-off alert is sent once after 3 minutes without activity, only when both name and a valid mobile number were saved. Completing the form before then cancels it.</p><label class="tlit-teams-check"><input type="checkbox" name="chatbot" value="1" '.checked(!empty($o['chatbot']),true,false).'> Chatbot leads — after a valid mobile number is saved</label></fieldset><label for="tlit-teams-url"><strong>Power Automate webhook URL</strong></label><input id="tlit-teams-url" class="large-text" type="password" name="webhook_url" value="" autocomplete="new-password" placeholder="'.esc_attr($configured?'URL saved — leave blank to keep it':'Paste the URL generated by the Teams webhook trigger').'"><p class="description">'.($configured?'A webhook URL is saved securely.':'No webhook URL saved yet.').' The saved URL is encrypted and never displayed.</p><label class="tlit-teams-check"><input type="checkbox" name="remove_url" value="1"> Remove the saved webhook URL</label><p><button class="button button-primary tlit-cb-button">Save notification settings</button></p></form></section>';
        echo '<section class="tlit-cb-side-panel"><h2>Connect your Teams group</h2><ol><li>Create a Power Automate cloud flow with <strong>When a Teams webhook request is received</strong>.</li><li>Set who can trigger the flow to <strong>Anyone</strong>. Keep the generated URL private.</li><li>Use <strong>Apply to each</strong> on <code>triggerBody()?[\'attachments\']</code>. Inside it, add <strong>Post card in a chat or channel</strong> and choose your Teams group.</li><li>Set the adaptive-card content to <code>string(item()?[\'content\'])</code>. Save the flow and paste its webhook URL here.</li></ol><p>Save the URL, then send a test. A test contains sample details only and works while automatic notifications are off.</p>';
        self::form('test');echo '<button class="button"'.(!$configured?' disabled':'').'>Send test notification</button></form><p class="description">HTTP success means Power Automate accepted the request. Confirm the actual post in Teams or the flow run history. Notifications depend on WordPress Cron; configure a one-minute server cron for dependable delivery.</p><p><a target="_blank" rel="noopener noreferrer" href="https://learn.microsoft.com/en-us/connectors/teams/#when-a-teams-webhook-request-is-received">Microsoft webhook documentation ↗</a></p></section></div>';
        $page=max(1,absint($_GET['teams_paged']??1));$limit=20;$table=self::table();$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d",$limit,($page-1)*$limit),ARRAY_A);$read_error=$wpdb->last_error!=='';
        $labels=array('pending'=>'Queued','sending'=>'Sending','accepted'=>'Accepted by Power Automate','retry'=>'Retry scheduled','failed'=>'Action needed','uncertain'=>'Check flow before retrying','held'=>'Held','cancelled'=>'Contact unavailable');
        echo '<section class="tlit-cb-side-panel tlit-teams-history"><h2>Notification delivery history</h2><p>One alert per completed form, qualified drop-off or chatbot session. Delivery records contain references, not copies of contact details.</p><div class="tlit-cb-table-wrap"><table class="widefat striped"><thead><tr><th>Lead</th><th>Queued (IST)</th><th>Status</th><th>Attempts</th><th>Result</th><th>Action</th></tr></thead><tbody>';
        foreach(is_array($rows)?$rows:array() as $row) {
            $stamp=strtotime($row['created_at'].' UTC');
            if($row['entity_type']==='dropoff') { $url=admin_url('admin.php?page=tlit-landing-leads&view=dropoffs&draft='.(int)$row['entity_id']); }
            else { $url=admin_url('admin.php?page='.($row['entity_type']==='form'?'tlit-landing-leads&lead=':'tlit-chat-bridge&conversation=').(int)$row['entity_id']); }
            echo '<tr><td><a href="'.esc_url($url).'">'.esc_html($row['identity_key']).'</a><small>'.esc_html($row['notification_id']).'</small></td><td>'.esc_html(wp_date('d M Y, g:i a',$stamp,new DateTimeZone('Asia/Kolkata'))).' IST</td><td>'.esc_html($labels[$row['state']]??$row['state']).'</td><td>'.(int)$row['attempts'].'</td><td>'.esc_html($row['last_error']).(!empty($row['response_code'])?' · HTTP '.(int)$row['response_code']:'').'</td><td>';
            if(in_array($row['state'],array('failed','held','uncertain'),true)) { self::form('retry');echo '<input type="hidden" name="job_id" value="'.(int)$row['id'].'">';if($row['state']==='uncertain') { echo '<label><input type="checkbox" name="confirmed" value="1" required> I checked Teams and the flow history; this alert was not posted.</label>'; }echo '<button class="button">Retry alert</button></form>'; }
            echo '</td></tr>';
        }
        if(!$rows) { echo '<tr><td colspan="6">'.($read_error?'Delivery history could not be loaded. Check database availability.':'No alerts queued yet. Enable notifications to start with new leads.').'</td></tr>'; }
        echo '</tbody></table></div><p>';
        if($page>1) { echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=tlit-chat-bridge&view=integrations&teams_paged='.($page-1))).'">Previous</a> '; }
        if(count(is_array($rows)?$rows:array())===$limit) { echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=tlit-chat-bridge&view=integrations&teams_paged='.($page+1))).'">Next</a>'; }
        echo '</p></section>';
    }
    public static function erase($result,$id) {
        if($result!==true) { return $result; }
        global $wpdb;$table=self::table();$ids=$wpdb->get_col($wpdb->prepare("SELECT id FROM $table WHERE entity_type='chatbot' AND entity_id=%d",(int)$id));
        if($wpdb->last_error!==''||$wpdb->delete($table,array('entity_type'=>'chatbot','entity_id'=>(int)$id))===false) { return self::error('Teams notification references could not be erased.'); }
        self::$erased[$id]=$ids;return true;
    }
    public static function erased($id) {
        foreach(self::$erased[$id]??array() as $job_id) { wp_clear_scheduled_hook('tlit_teams_deliver',array((int)$job_id)); }
        unset(self::$erased[$id]);
    }
}
