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
    private static function date($utc) {
        $time = strtotime($utc . ' UTC');
        return $time ? wp_date('M j, Y · g:i a', $time, wp_timezone()) : '—';
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
    private static function crm_states() { return array('pending'=>'Not sent','sending'=>'Sending','sent'=>'Sent to TeleCRM','failed'=>'Action needed','uncertain'=>'Check TeleCRM'); }
    private static function export_url($args) {
        return wp_nonce_url(add_query_arg(array_merge($args,array('action'=>'tlit_lp_export')),admin_url('admin-post.php')),'tlit_lp_export');
    }
    public static function render() {
        TLIT_LP_Leads::admin();
        wp_enqueue_style('tlit-lp-admin',plugin_dir_url(__FILE__).'admin.css',array(),'1.0.0');
        $settings = self::input('view') === 'integrations';
        echo '<div class="wrap lp-workspace"><header class="lp-header"><div><p class="lp-eyebrow">TECH LEADS IT / COUNSELLOR WORKSPACE</p><h1>Landing page leads</h1><p>Every enquiry in one place. Review, call and qualify before sending to TeleCRM.</p></div><span class="lp-private"><span class="dashicons dashicons-lock" aria-hidden="true"></span> Private workspace</span></header>';
        echo '<nav class="lp-tabs" aria-label="Bridge sections"><a href="'.esc_url(admin_url('admin.php?page=tlit-chat-bridge')).'">Conversations</a><a class="'.(!$settings?'is-active':'').'" href="'.esc_url(self::url()).'"'.(!$settings?' aria-current="page"':'').'>Landing page leads</a><a class="'.($settings?'is-active':'').'" href="'.esc_url(self::url(array('view'=>'integrations'))).'"'.($settings?' aria-current="page"':'').'>Lead integrations</a></nav>';
        $notice = self::input('notice');
        $notices = array('saved'=>'Your changes have been saved. Check the TeleCRM delivery status for the result.','configuration_checked'=>'The saved configuration is ready. No test lead was sent; authentication is confirmed during an actual qualified lead delivery.');
        if (isset($notices[$notice])) { echo '<div class="lp-notice" role="status">'.esc_html($notices[$notice]).'</div>'; }
        if ($settings) { self::settings(); }
        elseif ($id = absint(self::input('lead'))) { self::detail($id); }
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
        echo '<label>From (UTC)<input type="date" name="from" value="'.esc_attr($filters['from']??'').'"></label><label>To (UTC)<input type="date" name="to" value="'.esc_attr($filters['to']??'').'"></label><label>UTM source<input name="utm_source" placeholder="e.g. google" value="'.esc_attr($filters['utm_source']??'').'"></label><label>UTM campaign<input name="utm_campaign" placeholder="Campaign name" value="'.esc_attr($filters['utm_campaign']??'').'"></label><div class="lp-filter-actions"><button class="button button-primary">Apply filters</button><a href="'.esc_url(self::url()).'">Reset</a></div></form>';
        echo '<div class="lp-table-scroll"><table class="lp-table"><thead><tr><th>Learner</th><th>Page &amp; form</th><th>Campaign</th><th>Qualification</th><th>TeleCRM</th><th>Received</th><th><span class="screen-reader-text">Actions</span></th></tr></thead><tbody>';
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
    private static function fields($values) {
        echo '<dl class="lp-fields">';
        foreach ($values as $label=>$value) { echo '<div><dt>'.esc_html($label).'</dt><dd>'.esc_html($value !== '' ? $value : 'Not provided').'</dd></div>'; }
        echo '</dl>';
    }
    private static function detail($id) {
        $lead = TLIT_LP_Leads::get($id);
        if (!$lead || is_wp_error($lead)) { echo '<div class="lp-panel"><h2>Lead not found</h2></div>'; return; }
        $lead = (array)$lead; $data = json_decode($lead['payload'],true) ?: array();
        echo '<p><a href="'.esc_url(self::url()).'">← Back to leads</a></p><div class="lp-detail-heading"><div><p class="lp-eyebrow">LANDING PAGE LEAD / LP-'.(int)$id.'</p><h2>'.esc_html($lead['name']).'</h2><p>Received '.esc_html(self::date($lead['created_at'])).'</p></div><a class="button" href="'.esc_url(self::export_url(array('lead'=>$id))).'">Download lead CSV ↓</a></div><div class="lp-review-grid"><div><section class="lp-panel"><h3>Contact details</h3><div class="lp-contact"><a href="tel:'.esc_attr($lead['phone']).'">'.esc_html($lead['phone']).' ↗</a><a href="mailto:'.esc_attr($lead['email']).'">'.esc_html($lead['email']).'</a></div>';
        self::fields(array('Page version'=>self::variants()[$lead['page_variant']??'']??'Not recorded','Form type'=>TLIT_LP_Leads::labels()[$lead['source']]??$lead['source'],'Brochure'=>!empty($data['brochure_downloaded'])?'Download recorded':(in_array($lead['source'],array('brochure','chatbot_brochure'),true)?'Requested':'Not requested'),'Mobile verification'=>!empty($lead['otp_verified'])?'OTP verified':'Not verified','Learning goal'=>$data['goal']??'','Current profile'=>(($data['status']??'') ?: ($data['details']['student_status']??'')),'Joining timeline'=>(($data['timeline']??'') ?: ($data['details']['start_timeline']??'')),'Question or interest'=>(($data['question']??'') ?: ($data['details']['main_question']??'')),'Preferred time to call'=>$data['details']['preferred_call_time']??'','Marketing consent'=>!empty($data['marketingConsent'])?'Given':'Not given'));
        echo '</section><section class="lp-panel"><h3>Page and campaign attribution</h3>';
        $values = array('Submitted from'=>$lead['submission_url']??'');
        foreach (($data['tracking']??array()) as $key=>$value) { if (is_scalar($value) && $value !== '') { $values[ucwords(str_replace('_',' ',$key))] = (string)$value; } }
        self::fields($values); echo '</section></div><aside><section class="lp-panel lp-review"><h3>Counsellor review</h3><p>Call the learner, then choose their interest level.</p>';
        self::form('status',$id);
        self::select('lead_status','Lead qualification',TLIT_LP_Leads::statuses(),$lead['lead_status']??'new');
        echo '<label>Call notes<textarea name="note" rows="6" maxlength="5000" placeholder="What did you discuss? What should happen next?">'.esc_textarea($lead['advisor_note']).'</textarea></label><p class="lp-help">Potential, High potential, Intended to join and Enrolled send the lead to TeleCRM after saving. New and Junk leads are not sent.</p><button class="button button-primary lp-full">Save review &amp; sync qualified lead</button></form></section><section class="lp-panel"><h3>TeleCRM delivery</h3>'.self::badge($lead['crm_status'],self::crm_states()[$lead['crm_status']]??$lead['crm_status']).'<p>'.esc_html($lead['crm_message'] ?: 'Waiting for counsellor qualification.').'</p>';
        if (!empty($lead['crm_updated'])) { echo '<p class="lp-help">Last update: '.esc_html(self::date($lead['crm_updated'])).'</p>'; }
        if (in_array($lead['crm_status'],array('sending','uncertain'),true)) {
            self::form('resolve',$id);
            echo '<p class="lp-help">Check TeleCRM before resolving this delivery.</p><label class="lp-check"><input type="checkbox" name="confirmed" value="1" required> I checked this lead in TeleCRM.</label>';
            self::select('resolution','Result',array('received'=>'Lead is present in TeleCRM','not_received'=>'Lead was not received'),'received');
            echo '<button class="button lp-full">Save delivery result</button></form>';
        }
        echo '<p><a href="'.esc_url(self::url(array('view'=>'integrations'))).'">Manage TeleCRM connection →</a></p></section></aside></div>';
    }
    private static function settings() {
        $options = TLIT_LP_Leads::settings();
        echo '<div class="lp-settings-grid"><section class="lp-panel"><p class="lp-eyebrow">LANDING LEADS → TELECRM</p><h2>Connect your counsellor workflow</h2><p>Every form is saved in WordPress first. Qualified leads are sent using this connection when a counsellor saves their review.</p>';
        self::form('settings');
        echo '<label class="lp-check"><input type="checkbox" name="enabled" value="1" '.checked(!empty($options['enabled']),true,false).'> Enable TeleCRM handoff for qualified leads</label><label class="lp-check"><input type="checkbox" name="reuse_existing" value="1" '.checked(!empty($options['reuse_existing']),true,false).'> Use the existing landing-page TeleCRM connection</label><p class="lp-help">Or enter a dedicated connection below. Find the enterprise ID and API bearer token in your TeleCRM API integration settings.</p><label>TeleCRM enterprise ID<input name="enterprise" value="'.esc_attr($options['enterprise']??'').'" autocomplete="off" placeholder="Your enterprise ID"></label><label>API bearer token<input type="password" name="token" value="" autocomplete="new-password" placeholder="'.(!empty($options['has_token'])?'Saved — leave blank to keep':'Paste your TeleCRM API token').'"></label><label class="lp-check"><input type="checkbox" name="clear_token" value="1"> Remove the saved token</label><p class="lp-help">The token stays on the server and is never included in page forms or exports.</p><details class="lp-advanced"><summary>Optional custom field mapping</summary><p>Map additional TeleCRM field names to captured lead fields. The standard contact and campaign fields are included automatically.</p><label>Field mapping (JSON)<textarea name="mapping" rows="6" spellcheck="false">'.esc_textarea(wp_json_encode($options['mapping']??array(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</textarea></label><p class="lp-help">Available values: goal, status, timeline, question, source, remarks, marketingConsent, page_variant, submission_url, lead_status and campaign tracking fields.</p></details><button class="button button-primary">Save integration settings</button></form></section><aside><section class="lp-panel"><h3>Delivery rules</h3><ol class="lp-rules"><li><strong>Capture</strong><span>General forms, popups and brochure requests are saved with the page version and campaign data.</span></li><li><strong>Review</strong><span>The counsellor calls the learner and saves a qualification status.</span></li><li><strong>Send</strong><span>Qualified leads go to TeleCRM once. Junk leads stay in the inbox.</span></li></ol><p class="lp-help">Enrolled is a review label. It does not fill payment amounts or course enrolment dates in TeleCRM.</p></section><section class="lp-panel"><h3>Connection readiness</h3><p>Validate the saved settings without sending a lead.</p>';
        self::form('test_connection'); echo '<button class="button lp-full">Check saved configuration</button></form><p class="lp-help">API authentication is confirmed on an actual qualified lead delivery.</p></section></aside></div>';
    }
}
