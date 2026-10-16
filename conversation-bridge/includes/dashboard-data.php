<?php
/** A deduplicated reporting view; no writes to lead qualification or CRM delivery. */
if (!defined('ABSPATH')) { exit; }
final class TLIT_CB_Dashboard_Data {
    public static function ready() { return class_exists('TLIT_LP_Leads') && get_option('tlit_cb_ops_schema') === TLIT_CB_Lead_Ops::SCHEMA; }
    private static function json($column,$path) { return "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID($column),$column,'{}'),'$path')),'')"; }
    public static function sql() {
        global $wpdb; $c=$wpdb->prefix.'tlit_cb_conversations';$l=TLIT_LP_Leads::table();$o=TLIT_CB_Lead_Ops::table();$n=TLIT_Teams_Notifications::table();
        $lp_tracking="COALESCE(JSON_EXTRACT(IF(JSON_VALID(l.payload),l.payload,'{}'),'$.tracking'),'{}')";
        // A chat remains the canonical reference after its local LP handoff is created.
        $union="SELECT CONCAT('LP-',l.id) reference,l.name,l.phone,l.email,l.created_at,l.source,l.page_variant,l.submission_url,$lp_tracking tracking_json,l.lead_status qualification,l.crm_status,l.reviewed_at,".self::json('l.payload','$.goal')." goal,'' language FROM $l l WHERE NOT EXISTS (SELECT 1 FROM $c cx WHERE cx.course='digital_marketing' AND (cx.lead_id=l.id OR l.request_id=CONCAT('cb-conversation-',cx.id)))
            UNION ALL SELECT CONCAT('CB-',c.id),c.name,c.phone,c.email,c.created_at,COALESCE(l.source,'chatbot'),CASE WHEN c.source_url LIKE '%/ai-powered-digital-marketing-course/%' THEN 'v2' WHEN c.source_url LIKE '%/ai-powered-digital-marketing-training/%' THEN 'v3' WHEN c.source_url LIKE '%/ai-powered-digital-marketing/%' THEN 'v1' ELSE 'unknown' END,c.source_url,c.tracking_json,COALESCE(l.lead_status,c.qualification_status),COALESCE(l.crm_status,'pending'),l.reviewed_at,c.goal,c.language FROM $c c LEFT JOIN $l l ON l.id=COALESCE(NULLIF(c.lead_id,0),(SELECT lx.id FROM $l lx WHERE lx.request_id=CONCAT('cb-conversation-',c.id) LIMIT 1)) WHERE c.course='digital_marketing' AND c.phone<>'' AND c.name<>''";
        $utm=self::json('b.tracking_json','$.utm_source');$medium=self::json('b.tracking_json','$.utm_medium');$referrer=self::json('b.tracking_json','$.referrer');
        $traffic="CASE WHEN ".self::json('b.tracking_json','$.gclid')."<>'' OR ".self::json('b.tracking_json','$.gbraid')."<>'' OR ".self::json('b.tracking_json','$.wbraid')."<>'' OR (LOWER($utm) LIKE '%google%' AND LOWER($medium) IN ('cpc','ppc','paid','paid_search')) THEN 'google_ads' WHEN ".self::json('b.tracking_json','$.fbclid')."<>'' OR LOWER($utm) IN ('facebook','fb','instagram','ig','meta') THEN 'meta' WHEN LOWER($medium)='organic' THEN 'organic' WHEN $utm<>'' THEN 'campaign' WHEN $referrer<>'' AND $referrer<>'direct' THEN 'referral' ELSE 'direct_unknown' END";
        return "SELECT b.*, $traffic traffic,".self::json('b.tracking_json','$.utm_source')." utm_source,".self::json('b.tracking_json','$.utm_campaign')." campaign,COALESCE(o.owner_key,'') owner_key,COALESCE(o.owner_name,'') owner_name,COALESCE(o.assigned_by,'') assigned_by,o.assigned_at,o.first_view_at,o.last_view_at,COALESCE(o.view_count,0) view_count,COALESCE(o.version,0) version,CASE WHEN o.first_view_at IS NOT NULL THEN 'opened' WHEN b.created_at<'".esc_sql((string)get_option('tlit_cb_ops_started_at',gmdate('Y-m-d H:i:s')))."' THEN 'unknown' ELSE 'unopened' END viewed,COALESCE(n.state,'not_queued') notification_state FROM ($union) b LEFT JOIN $o o ON o.reference=b.reference LEFT JOIN $n n ON n.identity_key=b.reference";
    }
    public static function filters($input) {
        $out=array();
        foreach(array('search','from','to','source','variant','traffic','campaign','owner','viewed','qualification','crm','record') as $key) {
            if(isset($input[$key])&&is_string($input[$key])) { $out[$key]=substr(sanitize_text_field($input[$key]),0,200); }
        }
        return $out;
    }
    private static function bound($day,$next=false) {
        if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$day,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1])) { return false; }
        $d=new DateTimeImmutable($day.' 00:00:00',new DateTimeZone('Asia/Kolkata'));
        if($next) { $d=$d->modify('+1 day'); } return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    public static function where($filters) {
        global $wpdb;$out=array('1=1');
        foreach(array('source'=>'source','variant'=>'page_variant','traffic'=>'traffic','campaign'=>'campaign','viewed'=>'viewed','qualification'=>'qualification','crm'=>'crm_status','record'=>'reference') as $key=>$column) {
            if(($filters[$key]??'')!=='') { $out[]=$key==='qualification'&&$filters[$key]==='qualified'?"d.qualification NOT IN ('new','junk')":$wpdb->prepare("d.$column=%s",$filters[$key]); }
        }
        if(($filters['owner']??'')!=='') { $out[]=$filters['owner']==='unassigned'?"d.owner_key=''":$wpdb->prepare('d.owner_key=%s',$filters['owner']); }
        foreach(array('from','to') as $key) {
            if(($filters[$key]??'')!=='') { $day=self::bound($filters[$key],$key==='to');$out[]=$day===false?'1=0':$wpdb->prepare('d.created_at '.($key==='from'?'>=':'<').' %s',$day); }
        }
        if(($filters['search']??'')!=='') {
            $like='%'.$wpdb->esc_like($filters['search']).'%';$out[]=$wpdb->prepare('(d.name LIKE %s OR d.phone LIKE %s OR d.email LIKE %s OR d.reference LIKE %s)',$like,$like,$like,$like);
        }
        return implode(' AND ',$out);
    }
    public static function query($filters=array(),$page=1,$limit=25) {
        global $wpdb;if(!self::ready()) { return new WP_Error('dashboard','Update both Bridge and Landing Page Leads before opening the dashboard.'); }
        $page=max(1,(int)$page);$limit=min(500,max(1,(int)$limit));$base=' FROM ('.self::sql().') d WHERE '.self::where($filters);
        $count=$wpdb->get_var('SELECT COUNT(*)'.$base);
        if($wpdb->last_error!=='') { return new WP_Error('dashboard','Lead totals could not be loaded. Check the database and retry.'); }
        $rows=$wpdb->get_results($wpdb->prepare('SELECT d.*'.$base.' ORDER BY d.created_at DESC,d.reference DESC LIMIT %d OFFSET %d',$limit,($page-1)*$limit),ARRAY_A);
        if($wpdb->last_error!=='') { return new WP_Error('dashboard','Leads could not be loaded. Check the database and retry.'); }
        return array('rows'=>(array)$rows,'total'=>(int)$count,'page'=>$page,'limit'=>$limit);
    }
    public static function record($ref) { if(!TLIT_CB_Lead_Ops::valid_ref($ref)) { return null; }$q=self::query(array('record'=>$ref),1,1);return !is_wp_error($q)&&!empty($q['rows'])?$q['rows'][0]:null; }
    public static function overview($filters) {
        global $wpdb;$base=' FROM ('.self::sql().') d WHERE '.self::where($filters);
        $stats=$wpdb->get_row("SELECT COUNT(*) total,COALESCE(SUM(owner_key=''),0) unassigned,COALESCE(SUM(viewed='unopened'),0) unopened,COALESCE(SUM(viewed='opened'),0) opened,COALESCE(SUM(viewed='unknown'),0) unknown_views,COALESCE(SUM(qualification='new'),0) awaiting_review,COALESCE(SUM(qualification NOT IN ('new','junk')),0) qualified,COALESCE(SUM(qualification='enrolled'),0) enrolled,COALESCE(SUM(crm_status='sent'),0) synced,COALESCE(SUM(notification_state='accepted'),0) alerts_accepted".$base,ARRAY_A);
        if($wpdb->last_error!=='') { return new WP_Error('dashboard','Dashboard totals could not be loaded.'); }
        $sources=$wpdb->get_results('SELECT source,page_variant,COUNT(*) total'.$base.' GROUP BY source,page_variant ORDER BY total DESC',ARRAY_A);
        $traffic=$wpdb->get_results('SELECT traffic,COUNT(*) total'.$base.' GROUP BY traffic ORDER BY total DESC',ARRAY_A);
        if($wpdb->last_error!=='') { return new WP_Error('dashboard','Source totals could not be loaded.'); }
        return array('stats'=>$stats,'sources'=>$sources,'traffic'=>$traffic);
    }
    public static function recent($filters,$limit=20) {
        global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare('SELECT a.* FROM '.TLIT_CB_Lead_Ops::table(true).' a INNER JOIN ('.self::sql().') d ON d.reference=a.reference WHERE '.self::where($filters).' ORDER BY a.created_at DESC,a.id DESC LIMIT %d',min(100,max(1,(int)$limit))),ARRAY_A);
        return $wpdb->last_error===''?(array)$rows:array();
    }
    public static function admin_link($ref) { $chat=str_starts_with($ref,'CB-');return admin_url('admin.php?page='.($chat?'tlit-chat-bridge&conversation=':'tlit-landing-leads&lead=').(int)substr($ref,3)); }
}
