<?php
if (!defined('ABSPATH')) { exit; }

final class TLIT_CB_Integrations {
    public static function register() {
        add_action('admin_post_tlit_cb_connection_save', array(__CLASS__, 'save'));
        add_action('admin_post_tlit_cb_send', array(__CLASS__, 'enqueue'));
        add_action('tlit_cb_deliver', array(__CLASS__, 'deliver'), 10, 1);
        add_action('tlit_cb_conversation_actions', array(__CLASS__, 'actions'));
        add_action('tlit_cb_conversation_erased', array(__CLASS__, 'erase'), 10, 1);
        add_filter('tlit_cb_before_conversation_erase', array(__CLASS__, 'erase_payload'), 10, 2);
    }
    public static function table() { global $wpdb; return $wpdb->prefix . 'tlit_cb_deliveries'; }
    public static function install() {
        if (get_option('tlit_cb_delivery_schema') === '1') { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table(); $collate = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            connector varchar(60) NOT NULL,
            idempotency_key varchar(64) NOT NULL,
            state varchar(20) NOT NULL DEFAULT 'pending',
            payload longtext NOT NULL,
            attempts int(10) unsigned NOT NULL DEFAULT 0,
            response_code int(10) unsigned NOT NULL DEFAULT 0,
            last_error varchar(200) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY delivery_identity (conversation_id,connector),
            KEY delivery_state (state,updated_at)
        ) ENGINE=InnoDB $collate;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) {
            update_option('tlit_cb_delivery_schema', '1', false);
        }
    }
    public static function options() {
        return wp_parse_args(get_option('tlit_cb_connections', array()), array('webhook_enabled'=>0,'webhook_url'=>'','webhook_secret'=>'','telecrm_enabled'=>0,'include_transcript'=>0));
    }
    public static function seal($secret) {
        $iv=random_bytes(12); $tag='';
        $encrypted=openssl_encrypt($secret,'aes-256-gcm',hash('sha256',wp_salt('auth').'bridge-integration',true),OPENSSL_RAW_DATA,$iv,$tag);
        return $encrypted === false ? '' : base64_encode($iv.$tag.$encrypted);
    }
    public static function secret($encoded) {
        if (!function_exists('openssl_decrypt')) { return ''; }
        $raw=base64_decode($encoded,true);
        if (!$raw || strlen($raw)<29) { return ''; }
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',wp_salt('auth').'bridge-integration',true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
        return is_string($plain) ? $plain : '';
    }
    public static function valid_url($url) {
        $parts=wp_parse_url($url);
        if (!is_array($parts) || ($parts['scheme']??'')!=='https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || !wp_http_validate_url($url)) { return false; }
        // WordPress allows its own host, even on localhost. Connectors must always be public.
        $host=trim($parts['host']??'', '[]');
        $ip=filter_var($host,FILTER_VALIDATE_IP)?$host:gethostbyname($host);
        return filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)!==false;
    }
    public static function connections() {
        $o=self::options();
        $items=array(
            'webhook'=>array('label'=>'Custom API / webhook','enabled'=>!empty($o['webhook_enabled']) && $o['webhook_url']!=='','send'=>array(__CLASS__,'send_webhook')),
            'telecrm'=>array('label'=>'TeleCRM','enabled'=>!empty($o['telecrm_enabled']) && function_exists('tlit_dm_crm') && function_exists('tlit_dm_lead_fields') && function_exists('tlit_dm_crm_url') && tlit_dm_crm_url()!=='','send'=>array(__CLASS__,'send_telecrm'))
        );
        // Additional integrations are registered by trusted server-side plugins.
        return apply_filters('tlit_cb_connections', $items);
    }
    public static function save() {
        if (!current_user_can('manage_options')) { wp_die('Access denied.', '', array('response'=>403)); }
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { wp_die('Use the settings form.', '', array('response'=>405)); }
        check_admin_referer('tlit_cb_connection_save');
        $p=wp_unslash($_POST); $o=self::options();
        $url=is_string($p['webhook_url']??null)?trim($p['webhook_url']):'';
        if ($url!=='' && !self::valid_url($url)) { wp_die('Enter a public HTTPS endpoint without embedded credentials.'); }
        if (!empty($p['webhook_enabled']) && $url==='') { wp_die('Enter an endpoint before enabling this connection.'); }
        $secret=is_string($p['webhook_secret']??null)?trim($p['webhook_secret']):'';
        if (strlen($secret)>2048 || preg_match('/[\r\n]/', $secret)) { wp_die('Invalid authorization token.'); }
        if ($secret!=='') {
            if (!function_exists('openssl_encrypt')) { wp_die('OpenSSL is required to store the authorization token.'); }
            $o['webhook_secret']=self::seal($secret);
            if ($o['webhook_secret']==='') { wp_die('The token could not be saved.'); }
        }
        if (!empty($p['clear_secret'])) { $o['webhook_secret']=''; }
        $o['webhook_url']=$url;
        $o['webhook_enabled']=empty($p['webhook_enabled'])?0:1;
        $o['telecrm_enabled']=empty($p['telecrm_enabled'])?0:1;
        $o['include_transcript']=empty($p['include_transcript'])?0:1;
        update_option('tlit_cb_connections',$o,false);
        wp_safe_redirect(admin_url('admin.php?page=tlit-chat-bridge&view=integrations&saved=1')); exit;
    }
    public static function payload($row) {
        $payload=array('schema_version'=>1,'conversation_id'=>(int)$row['id'],'course'=>$row['course'],'source_url'=>$row['source_url'],'name'=>$row['name'],'phone'=>$row['phone'],'email'=>$row['email'],'language'=>$row['language'],'goal'=>$row['goal'],'status'=>$row['status'],'stage'=>$row['stage'],'brochure_requested'=>(bool)$row['has_brochure'],'created_at_utc'=>$row['created_at'],'updated_at_utc'=>$row['updated_at'],'phone_verified'=>false);
        $tracking=json_decode($row['tracking_json']??'{}',true);
        $payload['tracking']=is_array($tracking)?$tracking:array();
        if (!empty(self::options()['include_transcript'])) {
            $messages=TLIT_CB_Store::messages($row['id']);
            if (TLIT_CB_Store::read_error()) { throw new RuntimeException('The transcript could not be read.'); }
            $payload['messages']=array_map(function($m){return array('role'=>$m['role'],'content'=>$m['content'],'type'=>$m['type'],'created_at_utc'=>$m['created_at']);},$messages);
        }
        return $payload;
    }
    public static function enqueue() {
        if (!current_user_can('manage_options')) { wp_die('Access denied.', '', array('response'=>403)); }
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { wp_die('Use the delivery form.', '', array('response'=>405)); }
        check_admin_referer('tlit_cb_send');
        $id=is_scalar($_POST['conversation_id']??null)?absint($_POST['conversation_id']):0;
        $connector=is_string($_POST['connector']??null)?sanitize_key(wp_unslash($_POST['connector'])):'';
        $queued=self::queue($id,$connector);
        if (is_wp_error($queued)) { wp_die(esc_html($queued->get_error_message())); }
        if ($queued['status']==='existing') {
            wp_safe_redirect(admin_url('admin.php?page=tlit-chat-bridge&conversation='.$id.'&delivery=existing')); exit;
        }
        $job_id=$queued['job_id'];
        // queue() has committed before Cron or a redirect can run.
        $scheduled=wp_schedule_single_event(time()+1,'tlit_cb_deliver',array($job_id));
        if (!$scheduled) {
            global $wpdb;
            $wpdb->update(self::table(),array('state'=>'failed','last_error'=>'WordPress could not schedule delivery. Check Cron before retrying.'),array('id'=>$job_id,'state'=>'pending'));
            wp_die('WordPress could not schedule delivery. Check Cron and the delivery history before retrying.');
        }
        wp_safe_redirect(admin_url('admin.php?page=tlit-chat-bridge&conversation='.$id.'&delivery=queued')); exit;
    }

    /** Internal queue builder; the admin-post handler enforces authorization. */
    public static function queue($id,$connector) {
        global $wpdb;
        $table=self::table(); $parents=$wpdb->prefix.'tlit_cb_conversations';
        $id=absint($id); $connector=is_string($connector)?sanitize_key($connector):'';
        $previous=$wpdb->suppress_errors(true); $started=false;
        try {
            if ($wpdb->query('START TRANSACTION')===false) { throw new RuntimeException('Cannot start delivery transaction.'); }
            $started=true;
            // The eraser locks the same parent first. Once erased, a concurrent
            // queue request cannot create an orphan snapshot of contact data.
            $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $parents WHERE id=%d FOR UPDATE",$id),ARRAY_A);
            if ($wpdb->last_error!=='') { throw new RuntimeException('Cannot read conversation.'); }
            $connections=self::connections();
            if (!$row || empty($connections[$connector]['enabled'])) {
                return new WP_Error('tlit_cb_connection','Choose an enabled connection and an existing conversation.');
            }
            if (empty($row['phone']) && empty($row['email'])) {
                return new WP_Error('tlit_cb_contact','This conversation does not have a mobile number or email yet.');
            }
            $job=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE conversation_id=%d AND connector=%s FOR UPDATE",$id,$connector),ARRAY_A);
            if ($wpdb->last_error!=='') { throw new RuntimeException('Cannot read delivery.'); }
            $status='queued';
            if ($job && in_array($job['state'],array('sent','sending','pending'),true)) {
                $job_id=(int)$job['id']; $status='existing';
            } elseif ($job) {
                // Keep the original snapshot and idempotency key on a retry.
                $updated=$wpdb->update($table,array('state'=>'pending','last_error'=>'','updated_at'=>gmdate('Y-m-d H:i:s')),array('id'=>$job['id'],'state'=>'failed'));
                if ($updated!==1) { throw new RuntimeException('Cannot retry delivery.'); }
                $job_id=(int)$job['id'];
            } else {
                $payload=apply_filters('tlit_cb_delivery_payload',self::payload($row),$connector,$row);
                $body=wp_json_encode($payload);
                if (!$body || strlen($body)>2*1024*1024) {
                    return new WP_Error('tlit_cb_payload_limit','This delivery exceeds the payload limit. Use a DOCX export.');
                }
                $ok=$wpdb->insert($table,array('conversation_id'=>$id,'connector'=>$connector,'idempotency_key'=>wp_generate_uuid4(),'state'=>'pending','payload'=>$body,'created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s')));
                if (!$ok || (int)$wpdb->insert_id<1) { throw new RuntimeException('Cannot create delivery.'); }
                $job_id=(int)$wpdb->insert_id;
            }
            if ($wpdb->query('COMMIT')===false) { throw new RuntimeException('Cannot commit delivery.'); }
            $started=false;
            return array('job_id'=>$job_id,'status'=>$status);
        } catch (Throwable $error) {
            return new WP_Error('tlit_cb_queue','The conversation could not be queued. Reload and try again.');
        } finally {
            if ($started) { $wpdb->query('ROLLBACK'); }
            $wpdb->suppress_errors($previous);
        }
    }
    public static function deliver($id) {
        global $wpdb; $table=self::table(); $id=absint($id);
        // Atomic state transition stops overlapping WP-Cron requests from double-sending.
        $won=$wpdb->query($wpdb->prepare("UPDATE $table SET state='sending',attempts=attempts+1,updated_at=%s WHERE id=%d AND state='pending'",gmdate('Y-m-d H:i:s'),$id));
        if ($won!==1) { return; }
        $job=$wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d",$id),ARRAY_A);
        if (!$job) { return; }
        try {
            if (!TLIT_CB_Store::get($job['conversation_id'])) { throw new RuntimeException('Conversation is unavailable.'); }
            $connections=self::connections(); $connection=$connections[$job['connector']]??null;
            if (!$connection || empty($connection['enabled']) || !is_callable($connection['send'])) { throw new RuntimeException('Connection is disabled.'); }
            $result=call_user_func($connection['send'],json_decode($job['payload'],true),$job['idempotency_key']);
            $code=is_array($result)?(int)($result['code']??0):0;
            $success=is_array($result)&&!empty($result['success']);
            $wpdb->update($table,array('state'=>$success?'sent':'failed','response_code'=>$code,'last_error'=>$success?'':'Delivery was not confirmed. Check the destination before retrying.','updated_at'=>gmdate('Y-m-d H:i:s')),array('id'=>$id));
        } catch (Throwable $error) {
            $wpdb->update($table,array('state'=>'failed','last_error'=>'Delivery could not run. Check the connection settings.','updated_at'=>gmdate('Y-m-d H:i:s')),array('id'=>$id));
        }
    }
    public static function send_webhook($payload,$key) {
        $o=self::options(); if (!self::valid_url($o['webhook_url'])) { return array('success'=>false); }
        $headers=array('Content-Type'=>'application/json','Idempotency-Key'=>$key);
        $secret=self::secret($o['webhook_secret']);
        if ($o['webhook_secret']!=='' && $secret==='') { return array('success'=>false,'code'=>0); }
        if ($secret!=='') { $headers['Authorization']='Bearer '.$secret; }
        $result=wp_safe_remote_post($o['webhook_url'],array('headers'=>$headers,'body'=>wp_json_encode($payload),'timeout'=>20,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>4096));
        if (is_wp_error($result)) { return array('success'=>false,'code'=>0); }
        $code=(int)wp_remote_retrieve_response_code($result); return array('success'=>$code>=200&&$code<300,'code'=>$code);
    }
    public static function send_telecrm($payload,$key) {
        if (($payload['course']??'')!=='digital_marketing' || !function_exists('tlit_dm_crm') || !function_exists('tlit_dm_lead_fields')) { return array('success'=>false); }
        $raw_phone=$payload['phone']??'';
        if (function_exists('tlit_dm_chat_phone_international')) {
            $phone=is_string($raw_phone)&&substr(trim($raw_phone),0,1)==='+'?tlit_dm_chat_phone_international($raw_phone):tlit_dm_chat_phone($raw_phone,'IN');
            if ($phone==='') { return array('success'=>false); }
        } else {
            // Compatibility with an older Landing Pages mapper: keep its Indian contract.
            $phone=preg_replace('/\D/','',is_string($raw_phone)?$raw_phone:''); if (strlen($phone)===12 && substr($phone,0,2)==='91') { $phone=substr($phone,2); }
            if (!preg_match('/^[6-9][0-9]{9}$/D',$phone)) { return array('success'=>false); }
        }
        $note='Conversation Bridge | #'.(int)$payload['conversation_id']."\nGoal: ".$payload['goal']."\nPreferred language: ".$payload['language']."\nPhone verification: Not verified by chat capture.\nReference: ".$key;
        if (!empty($payload['brochure_requested'])) { $note.="\nPerson downloaded brochure — AI Powered Digital Marketing"; }
        if (!empty($payload['messages'])) { foreach ($payload['messages'] as $m) { $note.="\n".$m['role'].': '.$m['content']; } }
        $note=function_exists('mb_substr')?mb_substr($note,0,20000,'UTF-8'):wp_check_invalid_utf8(substr($note,0,20000),true);
        $tracking=is_array($payload['tracking']??null)?$payload['tracking']:array();
        $tracking['landing_page']=$payload['source_url'];
        $fields=tlit_dm_lead_fields(array('tracking'=>$tracking),$payload['name'],$phone,$payload['email'],$payload['goal'],$note);
        $success=tlit_dm_crm($fields,$note); return array('success'=>$success,'code'=>$success?200:0);
    }
    private static $erased_jobs=array();
    public static function erase_payload($result,$conversation_id) {
        if ($result!==true) { return $result; }
        global $wpdb; $table=self::table();
        $ids=$wpdb->get_col($wpdb->prepare("SELECT id FROM $table WHERE conversation_id=%d",$conversation_id));
        if ($wpdb->last_error!=='' || $wpdb->delete($table,array('conversation_id'=>(int)$conversation_id),array('%d'))===false) {
            return new WP_Error('tlit_cb_delivery_erase','Delivery data could not be erased. Please retry.');
        }
        self::$erased_jobs[$conversation_id]=$ids;
        return $result;
    }
    public static function erase($conversation_id) {
        foreach (self::$erased_jobs[$conversation_id]??array() as $id) { wp_clear_scheduled_hook('tlit_cb_deliver',array((int)$id)); }
        unset(self::$erased_jobs[$conversation_id]);
    }
    public static function actions($row) {
        if (!current_user_can('manage_options')) { return; }
        echo '<section class="tlit-cb-integration-actions"><h3>Send to a platform</h3><p>Manually send the stored contact details and goal. Transcript sharing follows the integration settings.</p>';
        $connections=self::connections(); $enabled=array_filter($connections,function($c){return !empty($c['enabled']);});
        if (!$enabled) { echo '<p><a href="'.esc_url(admin_url('admin.php?page=tlit-chat-bridge&view=integrations')).'">Configure an integration</a> when you are ready to forward this data.</p>'; }
        else {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="tlit_cb_send"><input type="hidden" name="conversation_id" value="'.(int)$row['id'].'">'; wp_nonce_field('tlit_cb_send');
            echo '<label>Destination <select name="connector">'; foreach ($enabled as $key=>$item) { echo '<option value="'.esc_attr($key).'">'.esc_html($item['label']).'</option>'; } echo '</select></label> <button class="button button-primary">Queue delivery</button></form>';
        }
        global $wpdb; $table=self::table(); $jobs=$wpdb->get_results($wpdb->prepare("SELECT connector,state,attempts,response_code,last_error,updated_at FROM $table WHERE conversation_id=%d ORDER BY id DESC",$row['id']),ARRAY_A);
        foreach ($jobs as $job) { echo '<p><strong>'.esc_html($connections[$job['connector']]['label']??$job['connector']).'</strong> · '.esc_html($job['state']).' · attempts '.(int)$job['attempts'].'<br>'.esc_html($job['last_error']).'</p>'; }
        echo '<p class="description">Pending deliveries run through WordPress Cron. Sent records are not sent again. Failed deliveries can be retried with the same reference.</p></section>';
    }
}

function tlit_cb_render_integrations() {
    if (!current_user_can('manage_options')) { return; }
    $o=TLIT_CB_Integrations::options();
    echo '<section class="tlit-cb-integration-settings"><h2>Connect when you are ready</h2><p>Conversations are saved locally first. These connections are off by default, and nothing is sent automatically. Configure a destination, then queue individual records from the inbox.</p>';
    if (!empty($_GET['saved'])) { echo '<div class="notice notice-success inline"><p>Connection settings saved.</p></div>'; }
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="tlit_cb_connection_save">'; wp_nonce_field('tlit_cb_connection_save');
    echo '<h3>Custom API / webhook</h3><p><label><input type="checkbox" name="webhook_enabled" value="1" '.checked($o['webhook_enabled'],1,false).'> Enable manual webhook delivery</label></p><p><label for="tlit-cb-url">HTTPS endpoint</label><br><input class="large-text" type="url" id="tlit-cb-url" name="webhook_url" value="'.esc_attr($o['webhook_url']).'" placeholder="https://your-platform.example/webhook"></p>';
    echo '<p><label for="tlit-cb-secret">Bearer token (optional)</label><br><input class="regular-text" type="password" id="tlit-cb-secret" name="webhook_secret" autocomplete="new-password" placeholder="'.($o['webhook_secret']!==''?'Token saved — leave blank to keep it':'Enter token').'" value=""></p><p><label><input type="checkbox" name="clear_secret" value="1"> Remove the saved token</label></p>';
    echo '<h3>TeleCRM</h3><p>Uses the existing Landing Pages TeleCRM account and field mapping. It is available when that plugin is active and configured.</p><p><label><input type="checkbox" name="telecrm_enabled" value="1" '.checked($o['telecrm_enabled'],1,false).'> Enable manual TeleCRM delivery</label></p><h3>Data included</h3><p>Contact details, course, language, goal, source page and brochure status are included.</p><p><label><input type="checkbox" name="include_transcript" value="1" '.checked($o['include_transcript'],1,false).'> Also include the conversation transcript</label></p>';
    echo '<p class="description">The webhook receives JSON with schema_version=1 and an Idempotency-Key header. Your receiving API should use that key to avoid duplicates. Saving these settings does not send any visitor data.</p><p><button class="button button-primary">Save connections</button></p></form></section>';
}
