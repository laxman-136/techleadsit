<?php
if (!defined('ABSPATH')) { exit; }
final class TLIT_CB_Dashboard {
    public static function register() {
        add_action('admin_post_tlit_cb_dashboard',array(__CLASS__,'action'));
        add_action('admin_post_tlit_cb_dashboard_export',array(__CLASS__,'export'));
    }
    public static function url($args=array()) { return add_query_arg(array_merge(array('page'=>'tlit-chat-bridge','view'=>'dashboard'),$args),admin_url('admin.php')); }
    public static function date($value) { return $value ? wp_date('d M Y, g:i a',strtotime($value.' UTC'),new DateTimeZone('Asia/Kolkata')).' IST' : '—'; }
    public static function sources() { return array('general'=>'General form','popup'=>'Popup form','brochure'=>'Brochure form','chatbot'=>'Chatbot','chatbot_brochure'=>'Chatbot brochure'); }
    public static function traffic() { return array('google_ads'=>'Google Ads','meta'=>'Meta / Facebook / Instagram','organic'=>'Organic search','campaign'=>'Other campaign','referral'=>'Referral','direct_unknown'=>'Direct / attribution unavailable'); }
    public static function viewed($row) { return array('opened'=>'Opened with PIN','unopened'=>'Not opened with PIN','unknown'=>'Earlier views unknown')[$row['viewed']]??'View history unavailable'; }
    private static function select($key,$label,$options,$value='',$empty='All') {
        echo '<label>'.esc_html($label).'<select name="'.esc_attr($key).'">';
        if($empty!==null) { echo '<option value="">'.esc_html($empty).'</option>'; }
        foreach($options as $k=>$v) { echo '<option value="'.esc_attr($k).'"'.selected($value,$k,false).'>'.esc_html($v).'</option>'; } echo '</select></label>';
    }
    private static function form($operation,$ref='') {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="tlit_cb_dashboard"><input type="hidden" name="operation" value="'.esc_attr($operation).'"><input type="hidden" name="reference" value="'.esc_attr($ref).'">';wp_nonce_field('tlit_cb_dashboard');
    }
    public static function action() {
        if(!current_user_can('manage_options')) { wp_die('Access denied.','',array('response'=>403)); }
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST') { wp_die('Use a dashboard form.','',array('response'=>405)); }
        check_admin_referer('tlit_cb_dashboard');$p=wp_unslash($_POST);$op=is_string($p['operation']??null)?$p['operation']:'';$ref=is_string($p['reference']??null)?$p['reference']:'';
        if($op==='members') { $result=TLIT_CB_Lead_Ops::save_members($p['members']??''); }
        elseif($op==='assign') { $user=wp_get_current_user();$result=TLIT_CB_Lead_Ops::assign($ref,is_string($p['member']??null)?$p['member']:'',$user->display_name.' (WordPress)',absint($p['version']??0)); }
        else { $result=new WP_Error('dashboard','This action is unavailable.'); }
        if(is_wp_error($result)) { wp_die(esc_html($result->get_error_message()).' Use Back, reload the dashboard, and try again.','',array('response'=>409)); }
        wp_safe_redirect(self::url(array('notice'=>$op==='members'?'members':'assigned','record'=>$op==='assign'?$ref:'')));exit;
    }
    public static function render() {
        wp_enqueue_style('tlit-cb-dashboard',TLIT_CB_URL.'assets/dashboard.css',array('tlit-cb-admin'),TLIT_CB_VERSION);
        echo '<div class="cb-dash"><div class="cb-dash-title"><div><p class="cb-dash-eyebrow">LEAD OPERATIONS</p><h2>Your team’s lead desk</h2><p>See what arrived, what was opened and who owns the next step.</p></div><a class="button" href="'.esc_url(self::url()).'">Refresh dashboard</a></div>';
        if(!TLIT_CB_Dashboard_Data::ready()) { echo '<div class="cb-dash-panel"><h3>Dashboard setup is incomplete</h3><p>Update the Bridge and Landing Page Leads modules and check the database connection.</p></div></div>';return; }
        $filters=TLIT_CB_Dashboard_Data::filters(wp_unslash($_GET));
        if(isset($_GET['notice'])) { echo '<p class="cb-dash-notice" role="status">'.esc_html($_GET['notice']==='members'?'Team members saved. Existing assignments are preserved.':'Assignment saved. Qualification and TeleCRM were not changed.').'</p>'; }
        if(!empty($filters['record'])) { self::detail($filters['record']);echo '</div>';return; }
        $result=TLIT_CB_Dashboard_Data::query($filters,max(1,absint($_GET['paged']??1)));$overview=TLIT_CB_Dashboard_Data::overview($filters);
        if(is_wp_error($result)||is_wp_error($overview)) { echo '<p class="cb-dash-notice">'.esc_html(is_wp_error($result)?$result->get_error_message():$overview->get_error_message()).'</p></div>';return; }
        $s=$overview['stats'];
        echo '<div class="cb-dash-metrics">';
        foreach(array('total'=>'Captured leads','unassigned'=>'Unassigned','unopened'=>'Not opened with PIN','awaiting_review'=>'Awaiting review','opened'=>'Opened with PIN','qualified'=>'Qualified / reviewed','synced'=>'Synced to TeleCRM','enrolled'=>'Enrolled') as $key=>$label) {
            $quick=$filters;if($key==='unassigned'){$quick['owner']='unassigned';}elseif(in_array($key,array('opened','unopened'),true)){$quick['viewed']=$key;}elseif($key==='awaiting_review'){$quick['qualification']='new';}elseif($key==='qualified'){$quick['qualification']='qualified';}elseif($key==='enrolled'){$quick['qualification']='enrolled';}elseif($key==='synced'){$quick['crm']='sent';}
            echo '<a class="cb-dash-metric'.(in_array($key,array('unassigned','unopened'),true)?' cb-dash-attention':'').'" href="'.esc_url(self::url($quick)).'"><span>'.esc_html($label).'</span><strong>'.esc_html(number_format_i18n((int)($s[$key]??0))).'</strong></a>';
        }echo '</div><p class="cb-dash-caption">Totals follow the filters below. Linked chatbot and form records count once. Chats without a captured name and mobile stay in Conversations. '.(int)($s['unknown_views']??0).' older leads have no prior PIN view history.</p>';
        echo '<section class="cb-dash-panel"><form method="get" class="cb-dash-filters"><input type="hidden" name="page" value="tlit-chat-bridge"><input type="hidden" name="view" value="dashboard"><label>Search<input type="search" name="search" placeholder="Name, phone, email or reference" value="'.esc_attr($filters['search']??'').'"></label><label>From (IST)<input type="date" name="from" value="'.esc_attr($filters['from']??'').'"></label><label>To (IST)<input type="date" name="to" value="'.esc_attr($filters['to']??'').'"></label>';
        self::select('variant','Landing page',array('v1'=>'V1 · Original','v2'=>'V2 · Course','v3'=>'V3 · Training','unknown'=>'Not recorded'),$filters['variant']??'','All pages');
        self::select('source','Capture source',self::sources(),$filters['source']??'','All sources');self::select('traffic','Traffic source',self::traffic(),$filters['traffic']??'','All traffic');
        echo '<label>UTM campaign<input name="campaign" value="'.esc_attr($filters['campaign']??'').'" placeholder="Exact campaign name"></label>';
        $members=TLIT_CB_Lead_Ops::members();$owners=array_merge(array('unassigned'=>'Unassigned'),$members);global $wpdb;
        foreach((array)$wpdb->get_results('SELECT DISTINCT owner_key,owner_name FROM '.TLIT_CB_Lead_Ops::table()." WHERE owner_key<>''",ARRAY_A) as $owner) { if(!isset($owners[$owner['owner_key']])){$owners[$owner['owner_key']]=$owner['owner_name'].' (former member)';} }
        self::select('owner','Assigned to',$owners,$filters['owner']??'','All ownership');self::select('viewed','PIN access',array('opened'=>'Opened','unopened'=>'Not opened','unknown'=>'Earlier views unknown'),$filters['viewed']??'','All access');self::select('qualification','Qualification',array_merge(array('qualified'=>'All reviewed statuses except New / Junk'),TLIT_LP_Leads::statuses()),$filters['qualification']??'','All statuses');
        self::select('crm','TeleCRM',array('pending'=>'Not sent','held'=>'Held','sending'=>'Syncing','sent'=>'Synced','failed'=>'Failed','uncertain'=>'Check delivery'),$filters['crm']??'','All sync states');
        echo '<div class="cb-dash-filter-actions"><button class="button button-primary">Apply filters</button><a href="'.esc_url(self::url()).'">Reset</a></div></form></section>';
        echo '<div class="cb-dash-sources"><section class="cb-dash-panel"><h3>Where leads are coming from</h3><p class="cb-dash-caption">Capture type and page version</p>';
        foreach($overview['sources'] as $source) { self::bar((self::sources()[$source['source']]??$source['source']).' · '.strtoupper($source['page_variant']),$source['total'],$s['total']); }
        if(!$overview['sources']) { echo '<p>No leads match these filters.</p>'; }echo '</section><section class="cb-dash-panel"><h3>Traffic attribution</h3><p class="cb-dash-caption">Recorded campaign and referral information</p>';
        foreach($overview['traffic'] as $traffic) { self::bar(self::traffic()[$traffic['traffic']]??$traffic['traffic'],$traffic['total'],$s['total']); }echo '</section></div>';
        $export=wp_nonce_url(add_query_arg(array_merge($filters,array('action'=>'tlit_cb_dashboard_export')),admin_url('admin-post.php')),'tlit_cb_dashboard_export');
        echo '<section class="cb-dash-panel cb-dash-inbox"><div class="cb-dash-panel-heading"><h3>All captured leads <span>'.(int)$result['total'].'</span></h3><a class="button" href="'.esc_url($export).'">Download filtered CSV</a></div><div class="cb-dash-table-wrap"><table class="cb-dash-table"><thead><tr><th>Lead</th><th>Source / page</th><th>PIN access</th><th>Ownership</th><th>Qualification / CRM</th><th>Received (IST)</th><th>Activity</th></tr></thead><tbody>';
        foreach($result['rows'] as $row) {
            $url=self::url(array('record'=>$row['reference']));echo '<tr><td><a class="cb-dash-name" href="'.esc_url($url).'">'.esc_html($row['name']).'</a><span>'.esc_html($row['phone']).'</span><small>'.esc_html($row['reference']).'</small></td><td><strong>'.esc_html(self::sources()[$row['source']]??$row['source']).' · '.esc_html(strtoupper($row['page_variant'])).'</strong><span>'.esc_html(self::traffic()[$row['traffic']]??$row['traffic']).'</span><small>'.esc_html($row['campaign']?:'No campaign recorded').'</small></td><td><span class="cb-dash-pill cb-dash-'.esc_attr($row['viewed']).'">'.esc_html(self::viewed($row)).'</span><small>'.esc_html($row['first_view_at']?self::date($row['first_view_at']):'').'</small></td><td><strong class="'.($row['owner_key']===''?'cb-dash-unassigned':'').'">'.esc_html($row['owner_name']?:'Unassigned').'</strong><small>'.esc_html($row['assigned_by']?'By '.$row['assigned_by']:'No owner selected').'</small></td><td><strong>'.esc_html(TLIT_LP_Leads::statuses()[$row['qualification']]??$row['qualification']).'</strong><span>'.esc_html(TLIT_CB_Lead_Ops::crm_label($row['crm_status'])).'</span></td><td>'.esc_html(self::date($row['created_at'])).'</td><td><a class="button" href="'.esc_url($url).'">Manage lead →</a></td></tr>';
        }
        if(!$result['rows']) { echo '<tr><td colspan="7"><h3>No matching leads</h3><p>Clear a filter or choose a different date range.</p></td></tr>'; }
        echo '</tbody></table></div><div class="cb-dash-pagination"><span>'.(int)$result['total'].' leads · page '.(int)$result['page'].' of '.max(1,(int)ceil($result['total']/25)).'</span><div>';
        if($result['page']>1){echo '<a class="button" href="'.esc_url(self::url(array_merge($filters,array('paged'=>$result['page']-1)))).'">Previous</a> ';}
        if($result['page']*25<$result['total']){echo '<a class="button" href="'.esc_url(self::url(array_merge($filters,array('paged'=>$result['page']+1)))).'">Next</a>'; }echo '</div></div></section>';
        echo '<div class="cb-dash-bottom"><section class="cb-dash-panel"><h3>Recent activity</h3>';self::history(TLIT_CB_Dashboard_Data::recent($filters));echo '</section><section class="cb-dash-panel"><h3>Your counsellor team</h3><p>Add one name per line. Team PIN users select their name when taking a lead; this is self-reported, not a verified login.</p>';
        self::form('members');echo '<label class="screen-reader-text" for="cb-members">Team members</label><textarea id="cb-members" name="members" rows="6" placeholder="Veera&#10;Counsellor name">'.esc_textarea(implode("\n",$members)).'</textarea><p><button class="button button-primary">Save team members</button></p></form><p class="cb-dash-caption">PIN access tracking began '.esc_html(self::date(get_option('tlit_cb_ops_started_at'))).'. Old views cannot be reconstructed. Viewing and assignment never send a lead to TeleCRM.</p></section></div></div>';
    }
    private static function bar($label,$count,$total) { echo '<div class="cb-dash-source"><div><span>'.esc_html($label).'</span><strong>'.(int)$count.'</strong></div><progress max="'.max(1,(int)$total).'" value="'.(int)$count.'">'.(int)$count.'</progress></div>'; }
    public static function history($rows) {
        echo '<ol class="cb-dash-history">';foreach($rows as $row){echo '<li><span class="cb-dash-event">'.esc_html(str_replace('_',' ',$row['kind'])).'</span><p>'.esc_html($row['summary']).'</p><small>'.esc_html($row['reference'].' · '.$row['actor'].' · '.self::date($row['created_at'])).'</small></li>';}
        if(!$rows){echo '<li>No recorded activity yet. New openings, assignments and reviews will appear here.</li>';}echo '</ol>';
    }
    private static function detail($ref) {
        $row=TLIT_CB_Dashboard_Data::record($ref);if(!$row){echo '<p>Lead not found. <a href="'.esc_url(self::url()).'">Return to dashboard</a></p>';return;}
        echo '<p><a href="'.esc_url(self::url()).'">← All leads</a></p><div class="cb-dash-panel-heading"><div><h2>'.esc_html($row['name']).'</h2><p>'.esc_html($ref.' · '.$row['phone'].' · '.$row['email']).'</p></div><a class="button button-primary" href="'.esc_url(TLIT_CB_Dashboard_Data::admin_link($ref)).'">Open full record &amp; review</a></div><div class="cb-dash-bottom"><section class="cb-dash-panel"><h3>Ownership</h3><p><strong>'.esc_html($row['owner_name']?:'Unassigned').'</strong></p><p>'.esc_html($row['assigned_by']?'Assigned by '.$row['assigned_by'].' · '.self::date($row['assigned_at']):'Nobody has taken responsibility for this lead.').'</p>';
        self::form('assign',$ref);echo '<input type="hidden" name="version" value="'.(int)$row['version'].'">';self::select('member','Assigned counsellor',TLIT_CB_Lead_Ops::members(),$row['owner_key'],'Unassigned');echo '<p><button class="button button-primary">Save assignment</button></p></form><p class="cb-dash-caption">Assignment does not qualify this lead or trigger TeleCRM.</p><h3>PIN access</h3><p>'.esc_html(self::viewed($row)).'</p><p>First opening: '.esc_html(self::date($row['first_view_at'])).'<br>Latest opening: '.esc_html(self::date($row['last_view_at'])).'<br>Recorded PIN sessions: '.(int)$row['view_count'].'</p><p class="cb-dash-caption">PIN use confirms access to this lead. It does not identify who viewed it or confirm that they contacted the learner.</p><h3>Lead source</h3><p>'.esc_html((self::sources()[$row['source']]??$row['source']).' · '.strtoupper($row['page_variant'])).'<br>'.esc_html(self::traffic()[$row['traffic']]??$row['traffic']).'<br>'.esc_html($row['campaign']).'</p><h3>TeleCRM</h3><p>'.esc_html(TLIT_CB_Lead_Ops::crm_label($row['crm_status'])).' · '.esc_html(TLIT_LP_Leads::statuses()[$row['qualification']]??$row['qualification']).'</p></section><section class="cb-dash-panel"><h3>Activity history</h3><p class="cb-dash-caption">Lead received '.esc_html(self::date($row['created_at'])).'. Audit tracking began '.esc_html(self::date(get_option('tlit_cb_ops_started_at'))).'.</p>';self::history(TLIT_CB_Lead_Ops::history($ref));echo '</section></div>';
    }
    public static function export() {
        if(!current_user_can('manage_options')){wp_die('Access denied.','',array('response'=>403));}check_admin_referer('tlit_cb_dashboard_export');
        $filters=TLIT_CB_Dashboard_Data::filters(wp_unslash($_GET));$result=TLIT_CB_Dashboard_Data::query($filters,1,500);if(is_wp_error($result)){wp_die(esc_html($result->get_error_message()));}
        $out=fopen('php://temp/maxmemory:2097152','w+');if(!$out){wp_die('The export could not be prepared.');}
        fputcsv($out,array('Reference','Name','Phone','Email','Received IST','Source','Page','Traffic','UTM source','Campaign','PIN access','First PIN view IST','Last PIN view IST','Owner','Assigned by','Assigned IST','Qualification','TeleCRM'));
        $page=1;do{foreach($result['rows'] as $row){$cells=array($row['reference'],$row['name'],$row['phone'],$row['email'],self::date($row['created_at']),$row['source'],$row['page_variant'],$row['traffic'],$row['utm_source'],$row['campaign'],self::viewed($row),self::date($row['first_view_at']),self::date($row['last_view_at']),$row['owner_name'],$row['assigned_by'],self::date($row['assigned_at']),$row['qualification'],$row['crm_status']);fputcsv($out,array_map(function($v){return preg_match('/^[\s]*[=+@-]/',(string)$v)?"'".$v:$v;},$cells));}if(count($result['rows'])<500){break;}$result=TLIT_CB_Dashboard_Data::query($filters,++$page,500);}while(!is_wp_error($result));
        if(is_wp_error($result)){fclose($out);wp_die('The full export could not be prepared. Please retry.');}
        rewind($out);nocache_headers();header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="bridge-dashboard-'.gmdate('Y-m-d').'.csv"');fpassthru($out);fclose($out);exit;
    }
}
