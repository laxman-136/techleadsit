<?php
/** Local lead ownership and audit history. This module never calls a CRM or webhook. */
if (!defined('ABSPATH')) { exit; }
final class TLIT_CB_Lead_Ops {
    const SCHEMA = '1';
    public static function table($activity = false) { global $wpdb; return $wpdb->prefix . ($activity ? 'tlit_cb_lead_activity' : 'tlit_cb_lead_ops'); }
    public static function register() {
        add_action('tlit_lp_lead_created', array(__CLASS__, 'form_received'));
        add_action('tlit_cb_contact_saved', array(__CLASS__, 'chat_received'));
        add_action('tlit_lp_review_saved', array(__CLASS__, 'form_reviewed'), 10, 3);
        add_action('tlit_cb_review_saved', array(__CLASS__, 'chat_reviewed'), 10, 3);
        add_action('tlit_teams_delivery_updated', array(__CLASS__, 'notification'), 10, 3);
        add_filter('tlit_cb_before_conversation_erase', array(__CLASS__, 'erase'), 40, 2);
    }
    public static function install() {
        if (get_option('tlit_cb_ops_schema') === self::SCHEMA) { return; }
        global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta('CREATE TABLE ' . self::table() . " (
            reference varchar(40) NOT NULL,
            owner_key varchar(80) NOT NULL DEFAULT '',
            owner_name varchar(160) NOT NULL DEFAULT '',
            assigned_by varchar(180) NOT NULL DEFAULT '',
            assigned_at datetime DEFAULT NULL,
            first_view_at datetime DEFAULT NULL,
            last_view_at datetime DEFAULT NULL,
            view_count int unsigned NOT NULL DEFAULT 0,
            version int unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (reference),
            KEY owner_key (owner_key),
            KEY first_view_at (first_view_at)
        ) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';');
        dbDelta('CREATE TABLE ' . self::table(true) . " (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            reference varchar(40) NOT NULL,
            event_key char(64) NOT NULL,
            kind varchar(24) NOT NULL,
            actor varchar(180) NOT NULL DEFAULT '',
            summary varchar(600) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_key (event_key),
            KEY lead_history (reference,id),
            KEY created_at (created_at)
        ) ENGINE=InnoDB " . $wpdb->get_charset_collate() . ';');
        if ($wpdb->get_var('SHOW TABLES LIKE ' . "'" . $wpdb->esc_like(self::table()) . "'") === self::table() && $wpdb->get_var('SHOW TABLES LIKE ' . "'" . $wpdb->esc_like(self::table(true)) . "'") === self::table(true)) {
            update_option('tlit_cb_ops_schema', self::SCHEMA, false);
            add_option('tlit_cb_ops_started_at', gmdate('Y-m-d H:i:s'), '', false);
        }
    }
    public static function valid_ref($ref) { return is_string($ref) && preg_match('/^(?:LP|CB)-[1-9][0-9]{0,17}$/D', $ref) === 1; }
    private static function text($value,$max) {
        $value=sanitize_text_field(is_string($value)?$value:'');
        return function_exists('mb_substr')?mb_substr($value,0,$max,'UTF-8'):(preg_match('/^.{0,'.(int)$max.'}/us',$value,$m)?$m[0]:'');
    }
    public static function reference($type, $id) {
        if ($type === 'chatbot') { return 'CB-' . (int) $id; }
        $row = class_exists('TLIT_LP_Leads') ? TLIT_LP_Leads::get($id) : null;
        if ($row && preg_match('/^cb-conversation-([1-9][0-9]*)$/D', $row->request_id, $m)) { return 'CB-' . $m[1]; }
        global $wpdb;
        $chat = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . $wpdb->prefix . 'tlit_cb_conversations WHERE lead_id=%d LIMIT 1', (int) $id));
        return $chat ? 'CB-' . (int) $chat : 'LP-' . (int) $id;
    }
    public static function members() {
        $names = get_option('tlit_cb_team_members', array()); $out = array();
        foreach (is_array($names) ? $names : array() as $key => $name) {
            if (is_string($key) && is_string($name) && $name !== '') { $out[$key] = $name; }
        }
        return $out;
    }
    public static function save_members($text) {
        if (!is_string($text) || strlen($text) > 12000) { return new WP_Error('team_members', 'Enter up to 100 team members, one name per line.'); }
        $out = array();
        foreach (preg_split('/\r?\n/', $text) as $name) {
            $name = trim(sanitize_text_field($name));
            if ($name === '') { continue; }
            $name = self::text($name,80);
            $key = 'member_' . substr(hash('sha256', function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name)), 0, 32);
            $out[$key] = $name;
            if (count($out) > 100) { return new WP_Error('team_members', 'Keep the team list to 100 members.'); }
        }
        update_option('tlit_cb_team_members', $out, false); return true;
    }
    public static function state($ref) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE reference=%s', $ref), ARRAY_A);
        return is_array($row) ? $row : array('reference'=>$ref, 'owner_key'=>'', 'owner_name'=>'', 'assigned_by'=>'', 'assigned_at'=>null, 'first_view_at'=>null, 'last_view_at'=>null, 'view_count'=>0, 'version'=>0);
    }
    public static function log($ref, $kind, $summary, $actor = '', $key = '', $at = '') {
        if (!self::valid_ref($ref)) { return false; }
        global $wpdb;
        $key = hash('sha256', $key !== '' ? $key : wp_generate_uuid4());
        return $wpdb->query($wpdb->prepare('INSERT IGNORE INTO ' . self::table(true) . ' (reference,event_key,kind,actor,summary,created_at) VALUES (%s,%s,%s,%s,%s,%s)', $ref, $key, substr(sanitize_key($kind),0,24), self::text($actor,180), self::text($summary,600), $at ?: gmdate('Y-m-d H:i:s'))) === 1;
    }
    private static function ensure($ref) {
        global $wpdb;
        return $wpdb->query($wpdb->prepare('INSERT IGNORE INTO ' . self::table() . ' (reference) VALUES (%s)', $ref)) !== false;
    }
    /** One recorded opening per authorized PIN session. Failed PINs and link previews never call this. */
    public static function viewed($grant, $session) {
        if (empty($session['csrf']) || (int)($session['grant_id']??0) !== (int)($grant['id']??-1)) { return false; }
        $ref = self::reference($grant['entity_type'], $grant['entity_id']);
        if (!self::valid_ref($ref)) { return false; }
        global $wpdb; $started = false;
        try {
            if ($wpdb->query('START TRANSACTION') === false) { return false; } $started = true;
            if (!self::ensure($ref)) { return false; }
            $wpdb->get_row($wpdb->prepare('SELECT reference FROM ' . self::table() . ' WHERE reference=%s FOR UPDATE', $ref));
            $key = hash_hmac('sha256', 'view|' . $ref . '|' . $session['csrf'], wp_salt('auth'));
            $now = gmdate('Y-m-d H:i:s');
            $new = self::log($ref, 'pin_view', 'Lead opened after successful PIN verification. Viewer identity is not verified.', 'PIN access', $key, $now);
            if ($wpdb->last_error !== '') { return false; }
            if ($new && $wpdb->query($wpdb->prepare('UPDATE ' . self::table() . ' SET first_view_at=COALESCE(first_view_at,%s),last_view_at=%s,view_count=view_count+1 WHERE reference=%s', $now, $now, $ref)) === false) { return false; }
            if ($wpdb->query('COMMIT') === false) { return false; } $started = false; return true;
        } finally { if ($started) { $wpdb->query('ROLLBACK'); } }
    }
    /** Explicit ownership only; never changes qualification, contact data or CRM state. */
    public static function assign($ref, $member, $actor, $expected_version, $claim = false) {
        $members = self::members();
        if (!self::valid_ref($ref) || ($member !== '' && !isset($members[$member])) || ($claim && $member === '')) { return new WP_Error('assignment', 'Choose a current team member.'); }
        if (!TLIT_CB_Dashboard_Data::record($ref)) { return new WP_Error('assignment', 'This lead is unavailable.'); }
        global $wpdb; $started = false;
        try {
            if ($wpdb->query('START TRANSACTION') === false) { return new WP_Error('assignment','Ownership could not be saved.'); } $started = true;
            if (!self::ensure($ref)) { return new WP_Error('assignment','Ownership could not be saved.'); }
            $old = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE reference=%s FOR UPDATE', $ref), ARRAY_A);
            if (!$old) { return new WP_Error('assignment','Ownership could not be loaded.'); }
            if ($claim && $old['owner_key'] !== '' && $old['owner_key'] !== $member) { return new WP_Error('assignment','This lead has already been assigned. Ask an administrator to reassign it.'); }
            if ((int)$old['version'] !== (int)$expected_version) { return new WP_Error('assignment','Assignment changed. Reload this lead before saving.'); }
            if ($old['owner_key'] !== $member) {
                $name = $members[$member] ?? ''; $now = gmdate('Y-m-d H:i:s');
                $patch = array('owner_key'=>$member,'owner_name'=>$name,'assigned_by'=>self::text($actor,180),'assigned_at'=>$now,'version'=>1+(int)$old['version']);
                if ($wpdb->update(self::table(), $patch, array('reference'=>$ref)) === false) { return new WP_Error('assignment','Ownership could not be saved.'); }
                $summary = $member === '' ? 'Lead unassigned. Previous owner: ' . $old['owner_name'] . '.' : ($old['owner_key'] === '' ? 'Lead assigned to ' . $name . '.' : 'Lead reassigned from ' . $old['owner_name'] . ' to ' . $name . '.');
                if (!self::log($ref,'assignment',$summary,$actor)) { return new WP_Error('assignment','Assignment history could not be saved.'); }
            }
            if ($wpdb->query('COMMIT') === false) { return new WP_Error('assignment','Ownership could not be saved.'); } $started = false; return true;
        } finally { if ($started) { $wpdb->query('ROLLBACK'); } }
    }
    public static function form_received($id) {
        $row = TLIT_LP_Leads::get($id); if (!$row) { return; }
        self::log(self::reference('form',$id),'received','Lead captured in WordPress.','Website','received|' . self::reference('form',$id),$row->created_at);
    }
    public static function chat_received($id) {
        $row = TLIT_CB_Store::get($id); if (!$row || ($row['course']??'') !== 'digital_marketing' || empty($row['phone']) || empty($row['name'])) { return; }
        self::log('CB-' . (int)$id,'received','Chatbot contact captured in WordPress.','Chatbot','received|CB-' . (int)$id);
    }
    public static function actor($id, $note = '') {
        if ($id && ($user = get_userdata($id))) { return $user->display_name . ' (WordPress)'; }
        if (preg_match('/^\[Team Bridge — ([^\]\r\n]+)\]/u',$note,$m)) { return $m[1] . ' (self-reported)'; }
        return 'Team PIN session (identity not verified)';
    }
    public static function form_reviewed($id, $status, $actor) {
        $row = TLIT_LP_Leads::get($id); if (!$row) { return; }
        $ref = self::reference('form',$id);
        self::log($ref,'review','Counsellor review saved: ' . (TLIT_LP_Leads::statuses()[$status]??$status) . '.',self::actor($actor,$row->advisor_note));
        if (!in_array($status,array('new','junk'),true)) {
            self::log($ref,'crm','TeleCRM result after review: ' . self::crm_label($row->crm_status) . '.', 'TeleCRM');
        }
    }
    public static function chat_reviewed($id, $status, $actor) {
        $row = TLIT_CB_Store::get($id); if (!$row || !empty($row['lead_id'])) { return; }
        self::log('CB-' . (int)$id,'review','Counsellor review saved: ' . (TLIT_CB_Qualification::statuses()[$status]??$status) . '.',self::actor($actor,$row['qualification_note']??''));
    }
    public static function notification($type,$id,$state) {
        $labels = array('accepted'=>'Power Automate accepted the lead alert.','failed'=>'Lead alert failed.','retry'=>'Lead alert queued for retry.','held'=>'Lead alert held for configuration review.','uncertain'=>'Lead alert delivery needs a flow-history check.','cancelled'=>'Lead alert cancelled.');
        if (isset($labels[$state])) { self::log(self::reference($type,$id),'notification',$labels[$state],'Power Automate'); }
    }
    public static function crm_label($state) { return array('pending'=>'Not sent','held'=>'Held in WordPress','sent'=>'Synced','failed'=>'Sync failed','uncertain'=>'Check TeleCRM','sending'=>'Syncing','blocked'=>'Connection needs attention')[$state]??$state; }
    public static function history($ref, $limit = 100) {
        global $wpdb; return (array)$wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table(true) . ' WHERE reference=%s ORDER BY id DESC LIMIT %d', $ref, min(200,max(1,(int)$limit))),ARRAY_A);
    }
    public static function erase($result,$id) {
        if ($result !== true) { return $result; } global $wpdb;
        foreach (array(self::table(),self::table(true)) as $table) { if ($wpdb->delete($table,array('reference'=>'CB-' . (int)$id)) === false) { return new WP_Error('ops_erase','Lead operations history could not be erased.'); } }
        return true;
    }
}
