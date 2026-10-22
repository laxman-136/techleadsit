<?php
/** Private, server-rendered counsellor workspace. */
if (!defined('ABSPATH')) { exit; }
final class TLIT_LP_Admin {
    private static function value($row, $key, $default = '') {
        $row = (array) $row;
        return isset($row[$key]) && is_scalar($row[$key]) ? (string) $row[$key] : $default;
    }
    private static function url($args = array()) {
        return add_query_arg(array_merge(array('page'=>'tlit-landing-leads'), $args), admin_url('admin.php'));
    }
    private static function input($key) {
        return isset($_GET[$key]) && is_string($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
    }
    private static function filters() {
        $filters = array();
        foreach (array('from','to','page_variant','source','lead_status','crm_status','search','utm_source','utm_campaign') as $key) {
            $value = self::input($key); if ($value !== '') { $filters[$key] = $value; }
        }
        return $filters;
    }
    private static function draft_filters() {
        $filters = array();
        foreach (array('from','to','page_variant','source','lifecycle_status','search') as $key) {
            $value = self::input($key); if ($value !== '') { $filters[$key] = $value; }
        }
        return $filters;
    }
    private static function date($utc) {
        return TLIT_LP_Leads::display_date($utc);
    }
    private static function badge($value, $label = '') {
        return '<span class="lp-badge lp-badge--'.esc_attr(sanitize_key($value)).'">'.esc_html($label ?: ucwords(str_replace('_',' ',$value))).'</span>';
    }
    private static function form($operation, $id = 0) {
        TLIT_LP_Leads::form_start($operation, $id);
    }
    private static function select($name, $label, $options, $selected = '', $empty = null) {
        echo '<label>'.esc_html($label).'<select name="'.esc_attr($name).'">';
        if ($empty !== null) { echo '<option value="">'.esc_html($empty).'</option>'; }
        foreach ($options as $key=>$value) { echo '<option value="'.esc_attr($key).'" '.selected($selected,$key,false).'>'.esc_html($value).'</option>'; }
        echo '</select></label>';
    }
    private static function variants() { return array('v1'=>'Version 1 · Original','v2'=>'Version 2 · Course','v3'=>'Version 3 · Training'); }
    private static function crm_states() { return array('pending'=>'Not synced','sending'=>'Syncing','sent'=>'Synced to TeleCRM','failed'=>'Action needed','uncertain'=>'Check TeleCRM'); }
    private static function export_url($args) {
        return wp_nonce_url(add_query_arg(array_merge($args,array('action'=>'tlit_lp_export')),admin_url('admin-post.php')),'tlit_lp_export');
    }
    public static function render() {
        TLIT_LP_Leads::admin();
        wp_enqueue_style('tlit-lp-admin',plugin_dir_url(__FILE__).'admin.css',array(),TLIT_LP_Leads::VERSION.'-workspace5');
        wp_enqueue_script('tlit-lp-theme',plugins_url('../conversation-bridge/assets/admin.js',__FILE__),array(),'1.5.1-workspace5',true);
        $view = self::input('view');$dropoffs=$view==='dropoffs';
        $is_light_theme=isset($_COOKIE['tlit_bridge_theme'])&&$_COOKIE['tlit_bridge_theme']==='light';
        echo '<div class="wrap lp-workspace'.($is_light_theme?' is-light':'').'" data-tlit-theme-root><script>(function(r){try{if(localStorage.getItem("tlit_bridge_theme")==="light"){r.classList.add("is-light");r.dataset.theme="light";}}catch(e){}})(document.currentScript.parentElement);</script><header class="lp-header"><div><p class="lp-eyebrow">TECH LEADS IT / COUNSELLOR WORKSPACE</p><h1>'.($dropoffs?'Form progress &amp; drop-offs':'Landing page leads').'</h1><p>'.($dropoffs?'See every saved form journey, its latest completed step and OTP outcome.':'Every enquiry in one place. Review, call and qualify before syncing with TeleCRM.').'</p></div><div class="lp-header-tools"><span class="lp-private"><span class="dashicons dashicons-lock" aria-hidden="true"></span> Private workspace</span><button type="button" class="tlit-cb-theme-toggle" data-tlit-theme-toggle aria-pressed="false" aria-label="Switch to light theme" title="Switch to light theme"><span aria-hidden="true"></span></button></div></header>';
        echo '<nav class="lp-tabs" data-tlit-tabbar aria-label="Bridge sections"><span class="tlit-tab-indicator" data-tlit-tab-indicator aria-hidden="true"></span><a href="'.esc_url(admin_url('admin.php?page=tlit-chat-bridge&view=dashboard')).'">Dashboard</a><a href="'.esc_url(admin_url('admin.php?page=tlit-chat-bridge')).'">Conversations</a><a class="'.(!$dropoffs?'is-active':'').'" href="'.esc_url(self::url()).'"'.(!$dropoffs?' aria-current="page"':'').'>Landing page leads</a><a class="'.($dropoffs?'is-active':'').'" href="'.esc_url(self::url(array('view'=>'dropoffs'))).'"'.($dropoffs?' aria-current="page"':'').'>Form drop-offs</a><a href="'.esc_url(admin_url('admin.php?page=tlit-chat-bridge&view=integrations')).'">Integrations</a></nav>';
        $notice = self::input('notice');
        $notices = array('saved'=>'Your review has been saved. Check the TeleCRM sync status for the result.','configuration_checked'=>'The existing connection is available. Authentication is confirmed during an actual qualified lead sync.');
        if (isset($notices[$notice])) { echo '<div class="lp-notice" role="status">'.esc_html($notices[$notice]).'</div>'; }
        if (!$dropoffs && TLIT_LP_Leads::existing_connection() === null) {
            echo '<div class="lp-notice" role="status">The existing landing-page TeleCRM connection is unavailable. Leads remain saved here; restore that connection before syncing a qualified review.</div>';
        } elseif(!$dropoffs) {
            echo '<p class="lp-help">TeleCRM uses your existing landing-page connection automatically. Save a counsellor review to sync the lead and later status changes. New and Junk leads stay in WordPress.</p>';
        }
        // Old bookmarks continue to open the inbox; there is no second CRM setup.
        if($dropoffs) {
            if($draft_id=absint(self::input('draft'))) { self::dropoff_detail($draft_id); }
            else { self::dropoffs(); }
        } elseif ($id = absint(self::input('lead'))) { self::detail($id); }
        else { self::inbox(); }
        echo '</div>';
    }
    private static function inbox() {
        $filters = self::filters(); $page = max(1,absint(self::input('paged')));
        $result = TLIT_LP_Leads::query($filters,$page,25);
        if (is_wp_error($result)) { echo '<div class="lp-panel"><h2>Leads could not be loaded</h2><p>Please refresh or check the database connection.</p></div>'; return; }
        $rows = $result['rows']; $total = (int)$result['total'];
        echo '<section class="lp-panel lp-inbox"><div class="lp-panel-header"><div><h2>Lead inbox <span class="lp-count">'.(int)$total.'</span></h2><p>Newest enquiries first. Page and form source stay attached to every lead.</p></div><a class="button" href="'.esc_url(self::export_url($filters)).'">Download filtered leads ↓</a></div>';
        echo '<form method="get" class="lp-filters"><input type="hidden" name="page" value="tlit-landing-leads"><label class="lp-search">Search leads<input type="search" name="search" placeholder="Name, email or phone" value="'.esc_attr($filters['search']??'').'"></label>';
        self::select('page_variant','Landing page',self::variants(),$filters['page_variant']??'','All versions');
        self::select('source','Form type',TLIT_LP_Leads::labels(),$filters['source']??'','All forms');
        self::select('lead_status','Qualification',TLIT_LP_Leads::statuses(),$filters['lead_status']??'','All leads');
        self::select('crm_status','TeleCRM',self::crm_states(),$filters['crm_status']??'','All deliveries');
        echo '<label>From (IST)<input type="date" name="from" value="'.esc_attr($filters['from']??'').'"></label><label>To (IST)<input type="date" name="to" value="'.esc_attr($filters['to']??'').'"></label><label>UTM source<input name="utm_source" placeholder="e.g. google" value="'.esc_attr($filters['utm_source']??'').'"></label><label>UTM campaign<input name="utm_campaign" placeholder="Campaign name" value="'.esc_attr($filters['utm_campaign']??'').'"></label><div class="lp-filter-actions"><button class="button button-primary">Apply filters</button><a href="'.esc_url(self::url()).'">Reset</a></div></form>';
        echo '<div class="lp-table-scroll"><table class="lp-table"><thead><tr><th>Learner</th><th>Page &amp; form</th><th>Campaign</th><th>Qualification</th><th>TeleCRM</th><th>Received (IST)</th><th><span class="screen-reader-text">Actions</span></th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $row = (array)$row; $data = json_decode($row['payload'],true) ?: array(); $tracking = $data['tracking']??array();
            echo '<tr><td><a class="lp-name" href="'.esc_url(self::url(array('lead'=>$row['id']))).'">'.esc_html($row['name']).'</a><span>'.esc_html($row['phone']).'</span><span>'.esc_html($row['email']).'</span></td><td><strong>'.esc_html(self::variants()[$row['page_variant']??'']??'Page not recorded').'</strong><span>'.esc_html(TLIT_LP_Leads::labels()[$row['source']]??$row['source']).'</span><small>LP-'.(int)$row['id'].'</small></td><td><strong>'.esc_html($tracking['utm_source']??'').'</strong><span>'.esc_html(($tracking['utm_campaign']??'') ?: 'No campaign recorded').'</span><small>'.esc_html($tracking['utm_medium']??'').'</small></td><td>'.self::badge($row['lead_status']??'new',TLIT_LP_Leads::statuses()[$row['lead_status']??'new']??'New').'</td><td>'.self::badge($row['crm_status'],self::crm_states()[$row['crm_status']]??$row['crm_status']).'</td><td><time>'.esc_html(self::date($row['created_at'])).'</time></td><td><a class="button" href="'.esc_url(self::url(array('lead'=>$row['id']))).'">Review →</a></td></tr>';
        }
        if (!$rows) { echo '<tr><td colspan="7" class="lp-empty"><h3>No leads found</h3><p>Try another date range or clear the filters. New form submissions will appear here automatically.</p></td></tr>'; }
        echo '</tbody></table></div><footer class="lp-pagination"><span>'.esc_html(number_format_i18n($total)).' matching leads · 25 per page</span><nav aria-label="Lead pages">';
        $pages = (int)ceil($total/25);
        for ($i=max(1,$page-2);$i<=min($pages,$page+2);$i++) { echo '<a class="button '.($i===$page?'button-primary':'').'" href="'.esc_url(self::url(array_merge($filters,array('paged'=>$i)))).'"'.($i===$page?' aria-current="page"':'').'>'.$i.'</a> '; }
        echo '</nav></footer></section>';
    }
    private static function dropoffs() {
        $filters=self::draft_filters();$page=max(1,absint(self::input('paged')));$result=TLIT_LP_Leads::query_drafts($filters,$page,25);
        if(is_wp_error($result)){echo '<div class="lp-panel"><h2>Form sessions could not be loaded</h2><p>Please refresh or check the database connection.</p></div>';return;}
        $counts=TLIT_LP_Leads::draft_counts();
        echo '<section class="lp-dropoff-stats" aria-label="Form completion summary">';
        foreach(TLIT_LP_Leads::draft_statuses() as $status=>$label){
            $notes=array('incomplete'=>'Left before OTP','otp_pending'=>'Code sent; awaiting verification','otp_failed'=>'Failed or expired verification','verified'=>'Completed and saved as a lead');
            echo '<a class="lp-dropoff-stat" href="'.esc_url(self::url(array('view'=>'dropoffs','lifecycle_status'=>$status))).'"><span>'.esc_html($label).'</span><strong>'.esc_html(number_format_i18n($counts[$status]??0)).'</strong><small>'.esc_html($notes[$status]).'</small></a>';
        }
        echo '</section>';
        echo '<section class="lp-panel lp-inbox"><div class="lp-panel-header"><div><h2>Saved form journeys <span class="lp-count">'.(int)$result['total'].'</span></h2><p>Progress saves after every completed step. These records never sync to TeleCRM until a verified lead is reviewed by a counsellor.</p></div></div>';
        echo '<form method="get" class="lp-filters lp-dropoff-filters"><input type="hidden" name="page" value="tlit-landing-leads"><input type="hidden" name="view" value="dropoffs"><label class="lp-search">Search sessions<input type="search" name="search" placeholder="Name, phone, email or session" value="'.esc_attr($filters['search']??'').'"></label>';
        self::select('lifecycle_status','Classification',TLIT_LP_Leads::draft_statuses(),$filters['lifecycle_status']??'','All states');
        self::select('page_variant','Landing page',self::variants(),$filters['page_variant']??'','All versions');
        self::select('source','Form type',array_intersect_key(TLIT_LP_Leads::labels(),array_flip(array('general','popup','brochure'))),$filters['source']??'','All forms');
        echo '<label>From (IST)<input type="date" name="from" value="'.esc_attr($filters['from']??'').'"></label><label>To (IST)<input type="date" name="to" value="'.esc_attr($filters['to']??'').'"></label><div class="lp-filter-actions"><button class="button button-primary">Apply filters</button><a href="'.esc_url(self::url(array('view'=>'dropoffs'))).'">Reset</a></div></form>';
        echo '<div class="lp-table-scroll"><table class="lp-table lp-dropoff-table"><thead><tr><th>Visitor</th><th>Page &amp; form</th><th>Progress</th><th>Classification</th><th>Last activity (IST)</th><th><span class="screen-reader-text">Actions</span></th></tr></thead><tbody>';
        foreach($result['rows'] as $row){$data=json_decode($row->payload,true)?:array();$status=TLIT_LP_Leads::draft_status($row);$current=max(0,(int)$row->current_step);$total=max(1,(int)$row->total_steps);$percent=min(100,(int)round($current/$total*100));
            echo '<tr><td><a class="lp-name" href="'.esc_url(self::url(array('view'=>'dropoffs','draft'=>$row->id))).'">'.esc_html($row->name?:'Anonymous visitor').'</a><span>'.esc_html($row->phone?:'Phone not reached').'</span><span>'.esc_html($row->email?:'Email not reached').'</span></td><td><strong>'.esc_html(self::variants()[$row->page_variant]??'Page not recorded').'</strong><span>'.esc_html(TLIT_LP_Leads::labels()[$row->source]??$row->source).'</span><small>FD-'.(int)$row->id.'</small></td><td><div class="lp-progress"><span style="width:'.$percent.'%"></span></div><strong>Step '.$current.' of '.$total.'</strong><small>'.esc_html($row->last_field?ucwords(str_replace('_',' ',$row->last_field)):'Started').'</small></td><td>'.self::badge($status,TLIT_LP_Leads::draft_statuses()[$status]).($status==='verified'&&$row->lead_id?'<small><a href="'.esc_url(self::url(array('lead'=>$row->lead_id))).'">Open LP-'.(int)$row->lead_id.'</a></small>':'').'</td><td><time>'.esc_html(self::date($row->updated_at)).'</time></td><td><a class="button" href="'.esc_url(self::url(array('view'=>'dropoffs','draft'=>$row->id))).'">View journey →</a></td></tr>';
        }
        if(!$result['rows'])echo '<tr><td colspan="6" class="lp-empty"><h3>No form sessions found</h3><p>Try another filter. New journeys appear after a visitor completes the first form step.</p></td></tr>';
        echo '</tbody></table></div><footer class="lp-pagination"><span>'.esc_html(number_format_i18n($result['total'])).' matching sessions · 25 per page</span><nav aria-label="Form journey pages">';
        $pages=(int)ceil($result['total']/25);for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++){echo '<a class="button '.($i===$page?'button-primary':'').'" href="'.esc_url(self::url(array_merge(array('view'=>'dropoffs'),$filters,array('paged'=>$i)))).'"'.($i===$page?' aria-current="page"':'').'>'.$i.'</a> ';}
        echo '</nav></footer></section>';
    }
    private static function dropoff_detail($id) {
        $row=TLIT_LP_Leads::get_draft($id);if(!$row){echo '<div class="lp-panel"><h2>Form session not found</h2></div>';return;}
        $data=json_decode($row->payload,true)?:array();$values=is_array($data['values']??null)?$data['values']:array();$tracking=is_array($data['tracking']??null)?$data['tracking']:array();$status=TLIT_LP_Leads::draft_status($row);
        echo '<p><a href="'.esc_url(self::url(array('view'=>'dropoffs'))).'">← Back to form drop-offs</a></p><div class="lp-detail-heading"><div><p class="lp-eyebrow">FORM JOURNEY / FD-'.(int)$id.'</p><h2>'.esc_html($row->name?:'Anonymous visitor').'</h2><p>Last activity '.esc_html(self::date($row->updated_at)).'</p></div>'.self::badge($status,TLIT_LP_Leads::draft_statuses()[$status]).'</div>';
        echo '<div class="lp-review-grid"><div><section class="lp-panel"><h3>Captured form data</h3>';
        $captured=array('Name'=>$row->name,'Mobile number'=>$row->phone,'Email'=>$row->email);
        foreach($values as $key=>$value){if(in_array($key,array('name','phone','email'),true)||!is_scalar($value)||$value==='')continue;$captured[ucwords(str_replace('_',' ',$key))]=(string)$value;}
        self::fields($captured);echo '</section><section class="lp-panel"><h3>Page and campaign attribution</h3>';
        $attribution=array('Submitted from'=>$row->submission_url);foreach($tracking as $key=>$value){if(is_scalar($value)&&$value!=='')$attribution[ucwords(str_replace('_',' ',$key))]=(string)$value;}self::fields($attribution);echo '</section></div><aside><section class="lp-panel"><h3>Journey status</h3>';
        self::fields(array('Classification'=>TLIT_LP_Leads::draft_statuses()[$status],'Progress'=>'Step '.(int)$row->current_step.' of '.max(1,(int)$row->total_steps),'Last completed field'=>ucwords(str_replace('_',' ',$row->last_field)),'Form type'=>TLIT_LP_Leads::labels()[$row->source]??$row->source,'Page version'=>self::variants()[$row->page_variant]??'Not recorded','First activity'=>self::date($row->created_at),'Last activity'=>self::date($row->updated_at),'OTP requested'=>$row->otp_requested_at?self::date($row->otp_requested_at):'Not requested','OTP issue'=>$row->otp_error,'Verified lead'=>$row->lead_id?'LP-'.$row->lead_id:'Not created'));
        if($row->lead_id)echo '<p><a class="button button-primary lp-full" href="'.esc_url(self::url(array('lead'=>$row->lead_id))).'">Open verified lead →</a></p>';
        echo '<p class="lp-help">Incomplete and OTP-pending sessions stay outside TeleCRM. A verified lead can sync only after a counsellor saves a qualifying review.</p></section></aside></div>';
    }
    private static function fields($values) {
        echo '<dl class="lp-fields">';
        foreach ($values as $label=>$value) { echo '<div><dt>'.esc_html($label).'</dt><dd>'.esc_html($value !== '' ? $value : 'Not provided').'</dd></div>'; }
        echo '</dl>';
    }
    private static function detail($id) {
        $lead = TLIT_LP_Leads::get($id);
        if (!$lead || is_wp_error($lead)) { echo '<div class="lp-panel"><h2>Lead not found</h2></div>'; return; }
        $lead = (array)$lead; $data = json_decode($lead['payload'],true) ?: array();
        echo '<p><a href="'.esc_url(self::url()).'">← Back to leads</a></p><div class="lp-detail-heading"><div><p class="lp-eyebrow">LANDING PAGE LEAD / LP-'.(int)$id.'</p><h2>'.esc_html($lead['name']).'</h2><p>Received '.esc_html(self::date($lead['created_at'])).'</p></div><a class="button" href="'.esc_url(self::export_url(array('lead'=>$id))).'">Download lead CSV ↓</a></div><div class="lp-review-grid"><div><section class="lp-panel"><h3>Contact details</h3><div class="lp-contact"><a href="tel:'.esc_attr($lead['phone']).'">'.esc_html($lead['phone']).' ↗</a>';
        if ($lead['email'] !== '') { echo '<a href="mailto:'.esc_attr($lead['email']).'">'.esc_html($lead['email']).'</a>'; }
        echo '</div>';
        $conversation_id = absint($data['details']['conversation_id']??0);
        if ($conversation_id && in_array($lead['source'],array('chatbot','chatbot_brochure'),true)) {
            echo '<p><a href="'.esc_url(add_query_arg(array('page'=>'tlit-chat-bridge','conversation'=>$conversation_id),admin_url('admin.php'))).'">Open chatbot conversation →</a></p>';
        }
        self::fields(array('Page version'=>self::variants()[$lead['page_variant']??'']??'Not recorded','Form type'=>TLIT_LP_Leads::labels()[$lead['source']]??$lead['source'],'Brochure'=>!empty($data['brochure_downloaded'])?'Download recorded':(in_array($lead['source'],array('brochure','chatbot_brochure'),true)?'Requested':'Not requested'),'Mobile verification'=>!empty($lead['otp_verified'])?'OTP verified':'Not verified','Learning goal'=>$data['goal']??'','Current profile'=>(($data['status']??'') ?: ($data['details']['student_status']??'')),'Joining timeline'=>(($data['timeline']??'') ?: ($data['details']['start_timeline']??'')),'Question or interest'=>(($data['question']??'') ?: ($data['details']['main_question']??'')),'Preferred time to call'=>$data['details']['preferred_call_time']??'','Marketing consent'=>!empty($data['marketingConsent'])?'Given':'Not given'));
        echo '</section><section class="lp-panel"><h3>Page and campaign attribution</h3>';
        $values = array('Submitted from'=>$lead['submission_url']??'');
        foreach (($data['tracking']??array()) as $key=>$value) { if (is_scalar($value) && $value !== '') { $values[ucwords(str_replace('_',' ',$key))] = (string)$value; } }
        self::fields($values); echo '</section></div><aside><section class="lp-panel lp-review"><h3>Counsellor review</h3><p>Call the learner, then choose their interest level.</p>';
        self::form('status',$id);
        self::select('lead_status','Lead qualification',TLIT_LP_Leads::statuses(),$lead['lead_status']??'new');
        echo '<label>Call notes<textarea name="note" rows="6" maxlength="5000" placeholder="What did you discuss? What should happen next?">'.esc_textarea($lead['advisor_note']).'</textarea></label><p class="lp-help">All TeleCRM statuses except New and Junk Lead sync automatically when saved. Later qualification changes update the same TeleCRM lead. New and Junk leads are not sent.</p><button class="button button-primary lp-full">Save review &amp; sync lead</button></form></section><section class="lp-panel"><h3>TeleCRM sync</h3>'.self::badge($lead['crm_status'],self::crm_states()[$lead['crm_status']]??$lead['crm_status']).'<p>'.esc_html($lead['crm_message'] ?: 'Waiting for counsellor qualification.').'</p>';
        if (!empty($lead['crm_updated'])) { echo '<p class="lp-help">Last update: '.esc_html(self::date($lead['crm_updated'])).'</p>'; }
        $target_status = TLIT_LP_Leads::crm_statuses()[$lead['lead_status']]??'';
        if ($target_status !== '') { echo '<p class="lp-help">TeleCRM status for this qualification: <strong>'.esc_html($target_status).'</strong>.</p>'; }
        if (in_array($lead['crm_status'],array('sending','uncertain'),true)) {
            self::form('resolve',$id);
            echo '<p class="lp-help">Check the latest attempted qualification and notes in TeleCRM before resolving this sync. An older copy of the lead does not confirm the latest update.</p><label class="lp-check"><input type="checkbox" name="confirmed" value="1" required> I checked the latest update in TeleCRM.</label>';
            self::select('resolution','Result',array('received'=>'Latest update is present in TeleCRM','not_received'=>'Latest update was not applied'),'received');
            echo '<button class="button lp-full">Save delivery result</button></form>';
        }
        echo '<p class="lp-help">Uses the existing landing-page connection. Marking an already synced lead New or Junk stops further updates; it does not delete its existing TeleCRM record. Payment amounts and enrolment dates remain manual.</p></section></aside></div>';
    }
}
