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
    public static function queue_url($filters, $bucket) {
        return self::url(TLIT_CB_Dashboard_Data::bucket_filters($filters, $bucket)) . '#bridge-leads';
    }

    private static function form_filters($filters) {
        $defaults = array(
            'unassigned'=>array('owner'=>'unassigned'), 'awaiting_review'=>array('qualification'=>'new'),
            'unopened'=>array('viewed'=>'unopened'), 'opened'=>array('viewed'=>'opened'),
            'qualified'=>array('qualification'=>'qualified'), 'synced'=>array('crm'=>'sent'),
            'enrolled'=>array('qualification'=>'enrolled'),
        );
        return array_merge($defaults[$filters['bucket']??'all']??array(), $filters);
    }

    public static function render() {
        wp_enqueue_style('tlit-cb-dashboard', TLIT_CB_URL.'assets/dashboard.css', array('tlit-cb-admin'), TLIT_CB_VERSION.'-workspace4');
        echo '<div class="cb-dash">';
        if (!TLIT_CB_Dashboard_Data::ready()) {
            echo '<section class="cb-dash-panel"><h2>Dashboard setup is incomplete</h2><p>Update the Bridge and Landing Page Leads modules and check the database connection.</p></section></div>';
            return;
        }
        $filters = TLIT_CB_Dashboard_Data::filters(wp_unslash($_GET));
        if (isset($_GET['notice'])) {
            echo '<p class="cb-dash-notice" role="status">'.esc_html($_GET['notice']==='members'?'Team members saved.':'Assignment saved. Qualification and TeleCRM were not changed.').'</p>';
        }
        if (!empty($filters['record'])) { self::detail($filters['record']); echo '</div>'; return; }
        $result = TLIT_CB_Dashboard_Data::query($filters, max(1,absint($_GET['paged']??1)));
        $context = TLIT_CB_Dashboard_Data::bucket_filters($filters, 'all');
        $overview = TLIT_CB_Dashboard_Data::overview($context);
        if (is_wp_error($result) || is_wp_error($overview)) {
            echo '<p class="cb-dash-notice cb-dash-error" role="alert">'.esc_html(is_wp_error($result)?$result->get_error_message():$overview->get_error_message()).'</p></div>';
            return;
        }
        $bucket = $filters['bucket']??(array_intersect_key($filters,array_flip(array('owner','viewed','qualification','crm')))?'custom':'all');
        $stats = $overview['stats'];
        echo '<div class="cb-dash-toolbar"><p><strong>'.esc_html(self::date_range($filters)).'</strong><span>All times in IST</span></p><div><a href="#bridge-team">Manage team</a><a class="button" href="'.esc_url(self::url($filters)).'">Refresh</a></div></div>';
        echo '<nav class="cb-dash-metrics" aria-label="Lead queues">';
        foreach (TLIT_CB_Dashboard_Data::buckets() as $key=>$definition) {
            $active = $bucket===$key;
            echo '<a class="cb-dash-metric'.($active?' is-active':'').'" href="'.esc_url(self::queue_url($filters,$key)).'" aria-controls="bridge-leads"'.($active?' aria-current="page"':'').'><span>'.esc_html($definition[1]).'</span><strong>'.esc_html(number_format_i18n((int)($stats[$definition[0]]??0))).'</strong><span class="cb-dash-metric-action" aria-hidden="true">View leads <span>→</span></span></a>';
        }
        echo '</nav><p class="cb-dash-caption cb-dash-metric-note">Counts use your search, date and source filters. Choose a count to open that lead list.</p>';
        self::filters_view($filters);
        self::inbox($filters,$result,$bucket);
        echo '<details class="cb-dash-panel cb-dash-disclosure"><summary>Source breakdown <span>Pages, forms and campaigns</span></summary><div class="cb-dash-sources"><section><h2>Capture source</h2>';
        foreach ($overview['sources'] as $source) {
            $label=(self::sources()[$source['source']]??$source['source']).' · '.self::variant($source['page_variant']);
            $args=$context; $args['source']=$source['source']; $args['variant']=$source['page_variant'];
            self::bar($label,$source['total'],$stats['total'],self::url($args).'#bridge-leads');
        }
        if (!$overview['sources']) { echo '<p>No leads match this date and source selection.</p>'; }
        echo '</section><section><h2>Traffic attribution</h2>';
        foreach ($overview['traffic'] as $traffic) {
            $args=$context; $args['traffic']=$traffic['traffic'];
            self::bar(self::traffic()[$traffic['traffic']]??$traffic['traffic'],$traffic['total'],$stats['total'],self::url($args).'#bridge-leads');
        }
        echo '</section></div></details>';
        echo '<section class="cb-dash-panel cb-dash-activity"><div class="cb-dash-panel-heading"><h2>Recent activity</h2><span>For the current lead list</span></div>';
        self::history(TLIT_CB_Dashboard_Data::recent($filters)); echo '</section>';
        $members=TLIT_CB_Lead_Ops::members();
        echo '<details id="bridge-team" class="cb-dash-panel cb-dash-disclosure"><summary>Counsellor team <span>'.count($members).' members</span></summary><div class="cb-dash-team"><p>Add one counsellor name per line. Team PIN users select their name when taking a lead; the selected name is self-reported.</p>';
        self::form('members'); echo '<label for="cb-members">Team members</label><textarea id="cb-members" name="members" rows="5" placeholder="One counsellor name per line">'.esc_textarea(implode("\n",$members)).'</textarea><button class="button button-primary">Save team members</button></form></div></details>';
        echo '<p class="cb-dash-caption cb-dash-footnote">Linked chatbot and form records count once. Conversations without a captured name and mobile remain in Conversations. PIN tracking began '.esc_html(self::date(get_option('tlit_cb_ops_started_at'))).'; '.(int)($stats['unknown_views']??0).' earlier leads have unknown prior view history.</p></div>';
    }

    private static function date_range($filters) {
        if (!empty($filters['from'])&&!empty($filters['to'])) { return $filters['from'].' — '.$filters['to']; }
        if (!empty($filters['from'])) { return 'From '.$filters['from']; }
        if (!empty($filters['to'])) { return 'Until '.$filters['to']; }
        return 'All time';
    }

    private static function variant($value) {
        return array('v1'=>'V1 · Original','v2'=>'V2 · Course','v3'=>'V3 · Training')[$value]??'Page not recorded';
    }

    private static function filters_view($filters) {
        $values=self::form_filters($filters);
        $advanced=array('variant','source','traffic','campaign','owner','viewed','qualification','crm');
        $active_values=array_filter(array_intersect_key($values,array_flip($advanced)),function($v){return $v!=='';});
        $active=count($active_values);
        $active_labels=array();
        $owners=array_merge(array('unassigned'=>'Unassigned'),TLIT_CB_Lead_Ops::members()); global $wpdb;
        foreach ((array)$wpdb->get_results('SELECT DISTINCT owner_key,owner_name FROM '.TLIT_CB_Lead_Ops::table()." WHERE owner_key<>''",ARRAY_A) as $owner) { if(!isset($owners[$owner['owner_key']])){$owners[$owner['owner_key']]=$owner['owner_name'].' (former member)';} }
        $filter_labels=array('variant'=>'Page','source'=>'Source','traffic'=>'Traffic','campaign'=>'Campaign','owner'=>'Owner','viewed'=>'PIN access','qualification'=>'Status','crm'=>'TeleCRM');
        $filter_options=array(
            'variant'=>array('v1'=>'V1 · Original','v2'=>'V2 · Course','v3'=>'V3 · Training','unknown'=>'Not recorded'),
            'source'=>self::sources(),
            'traffic'=>self::traffic(),
            'owner'=>$owners,
            'viewed'=>array('opened'=>'Opened','unopened'=>'Not opened','unknown'=>'Earlier views unknown'),
            'qualification'=>array_merge(array('qualified'=>'Reviewed'),TLIT_LP_Leads::statuses()),
            'crm'=>array('pending'=>'Not sent','held'=>'Held','sending'=>'Syncing','sent'=>'Synced','failed'=>'Failed','uncertain'=>'Check delivery'),
        );
        foreach($active_values as $key=>$value){
            $display=$filter_options[$key][$value]??($key==='owner'&&$value==='unassigned'?'Unassigned':$value);
            $active_labels[]=($filter_labels[$key]??ucfirst($key)).': '.$display;
        }
        echo '<section class="cb-dash-panel cb-dash-filter-panel" aria-label="Filter leads"><form method="get" action="'.esc_url(admin_url('admin.php')).'#bridge-leads"><input type="hidden" name="page" value="tlit-chat-bridge"><input type="hidden" name="view" value="dashboard"><div class="cb-dash-filter-primary"><label class="cb-dash-search">Search leads<input type="search" name="search" placeholder="Name, phone, email or reference" value="'.esc_attr($values['search']??'').'"></label><label>From (IST)<input type="date" name="from" value="'.esc_attr($values['from']??'').'"></label><label>To (IST)<input type="date" name="to" value="'.esc_attr($values['to']??'').'"></label><button class="button button-primary">Apply filters</button></div>';
        echo '<div class="cb-dash-filter-rail"><button type="button" class="cb-dash-filter-toggle" aria-expanded="false" aria-controls="cb-dash-advanced-filters"><span class="cb-dash-filter-chevron" aria-hidden="true"></span><span>More filters</span>'.($active?'<span class="cb-dash-filter-count">'.$active.' active</span>':'').'</button><div class="cb-dash-active-filters" aria-label="Active advanced filters">';
        foreach($active_labels as $label){echo '<span>'.esc_html($label).'</span>';}
        echo '</div><div class="cb-dash-date-shortcuts" aria-label="Quick date range">';
        $today=new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata'));
        foreach(array(1=>'Today',7=>'7 days',30=>'30 days') as $days=>$label){$args=$filters;unset($args['record']);$args['from']=$today->modify('-'.($days-1).' days')->format('Y-m-d');$args['to']=$today->format('Y-m-d');echo '<a href="'.esc_url(self::url($args).'#bridge-leads').'">'.esc_html($label).'</a>';}
        echo '</div><a class="cb-dash-clear-filters" href="'.esc_url(self::url()).'">Clear</a></div><div id="cb-dash-advanced-filters" class="cb-dash-filter-drawer" aria-hidden="true" inert><div class="cb-dash-filter-drawer-inner"><div class="cb-dash-filter-grid">';
        self::select('variant','Landing page',array('v1'=>'V1 · Original','v2'=>'V2 · Course','v3'=>'V3 · Training','unknown'=>'Not recorded'),$values['variant']??'','All pages');
        self::select('source','Capture source',self::sources(),$values['source']??'','All sources');
        self::select('traffic','Traffic source',self::traffic(),$values['traffic']??'','All traffic');
        echo '<label>UTM campaign<input name="campaign" value="'.esc_attr($values['campaign']??'').'" placeholder="Exact campaign name"></label>';
        self::select('owner','Assigned to',$owners,$values['owner']??'','All ownership');
        self::select('viewed','PIN access',array('opened'=>'Opened','unopened'=>'Not opened','unknown'=>'Earlier views unknown'),$values['viewed']??'','All access');
        self::select('qualification','Qualification',array_merge(array('qualified'=>'Reviewed: except New / Junk'),TLIT_LP_Leads::statuses()),$values['qualification']??'','All statuses');
        self::select('crm','TeleCRM',array('pending'=>'Not sent','held'=>'Held','sending'=>'Syncing','sent'=>'Synced','failed'=>'Failed','uncertain'=>'Check delivery'),$values['crm']??'','All sync states');
        echo '</div><div class="cb-dash-filter-drawer-actions"><button class="button button-primary cb-dash-apply-extra">Apply advanced filters</button><button type="button" class="button cb-dash-filter-close">Close</button></div></div></div></form></section>';
    }

    private static function inbox($filters,$result,$bucket) {
        $definition=TLIT_CB_Dashboard_Data::buckets()[$bucket]??null;
        $title=$definition?$definition[2]:'Filtered leads';
        $export=wp_nonce_url(add_query_arg(array_merge($filters,array('action'=>'tlit_cb_dashboard_export')),admin_url('admin-post.php')),'tlit_cb_dashboard_export');
        echo '<section id="bridge-leads" tabindex="-1" class="cb-dash-panel cb-dash-inbox"><div class="cb-dash-panel-heading"><div><h2>'.esc_html($title).' <span class="cb-dash-count">'.(int)$result['total'].'</span></h2><p>Newest enquiries first</p></div><a class="button" href="'.esc_url($export).'">Download CSV</a></div>';
        if($bucket!=='all'){echo '<div class="cb-dash-list-context"><span>Showing '.esc_html(strtolower($title)).'</span><a href="'.esc_url(self::queue_url($filters,'all')).'">Show all captured leads →</a></div>';}
        echo '<div class="cb-dash-table-wrap"><table class="cb-dash-table"><caption class="screen-reader-text">'.esc_html($title).' with source, access, ownership and qualification.</caption><thead><tr><th scope="col">Lead</th><th scope="col">Source</th><th scope="col">Ownership</th><th scope="col">PIN access</th><th scope="col">Qualification</th><th scope="col">Received (IST)</th><th scope="col"><span class="screen-reader-text">Actions</span></th></tr></thead><tbody>';
        foreach ($result['rows'] as $row) {
            $url=self::url(array('record'=>$row['reference']));
            echo '<tr><td data-label="Lead" class="cb-dash-lead-cell"><a class="cb-dash-name" href="'.esc_url($url).'">'.esc_html($row['name']).'</a><span>'.esc_html($row['phone']).'</span><small>'.esc_html($row['reference']).'</small></td><td data-label="Source"><strong>'.esc_html(self::sources()[$row['source']]??$row['source']).'</strong><span>'.esc_html(self::variant($row['page_variant'])).'</span><small>'.esc_html($row['campaign']?:self::traffic()[$row['traffic']]??$row['traffic']).'</small></td><td data-label="Ownership"><strong class="'.($row['owner_key']===''?'cb-dash-unassigned':'').'">'.esc_html($row['owner_name']?:'Unassigned').'</strong><small>'.esc_html($row['assigned_by']?'By '.$row['assigned_by']:'No owner selected').'</small></td><td data-label="PIN access"><span class="cb-dash-pill cb-dash-'.esc_attr($row['viewed']).'">'.esc_html(self::viewed($row)).'</span><small>'.esc_html($row['first_view_at']?self::date($row['first_view_at']):'').'</small></td><td data-label="Qualification"><strong>'.esc_html(TLIT_LP_Leads::statuses()[$row['qualification']]??$row['qualification']).'</strong><small>'.esc_html(TLIT_CB_Lead_Ops::crm_label($row['crm_status'])).'</small></td><td data-label="Received (IST)"><time>'.esc_html(self::date($row['created_at'])).'</time></td><td data-label="Action" class="cb-dash-action-cell"><a class="button" href="'.esc_url($url).'" aria-label="Manage '.esc_attr($row['name']).'">Manage lead</a></td></tr>';
        }
        if (!$result['rows']) { echo '<tr class="cb-dash-empty"><td colspan="7"><h3>No leads in this list</h3><p>Try another count or adjust the date and source filters.</p><a class="button" href="'.esc_url(self::queue_url($filters,'all')).'">View all captured leads</a></td></tr>'; }
        echo '</tbody></table></div><div class="cb-dash-pagination"><span>'.(int)$result['total'].' leads · Page '.(int)$result['page'].' of '.max(1,(int)ceil($result['total']/25)).'</span><nav aria-label="Lead list pages">';
        if ($result['page']>1) { echo '<a class="button" href="'.esc_url(self::url(array_merge($filters,array('paged'=>$result['page']-1))).'#bridge-leads').'">Previous</a>'; }
        if ($result['page']*25<$result['total']) { echo '<a class="button" href="'.esc_url(self::url(array_merge($filters,array('paged'=>$result['page']+1))).'#bridge-leads').'">Next</a>'; }
        echo '</nav></div></section>';
    }

    private static function bar($label,$count,$total,$url='') {
        echo '<a class="cb-dash-source" href="'.esc_url($url).'"><span>'.esc_html($label).'</span><strong>'.(int)$count.'</strong><progress max="'.max(1,(int)$total).'" value="'.(int)$count.'">'.(int)$count.'</progress></a>';
    }

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
