<?php
/** Counsellor qualification uses the shared lead inbox and its existing CRM connection. */
if (!defined('ABSPATH')) { exit; }

final class TLIT_CB_Qualification {
    public static function register() {
        add_action('admin_post_tlit_cb_qualify', array(__CLASS__, 'save'));
        add_filter('tlit_cb_before_conversation_erase', array(__CLASS__, 'erase'), 20, 2);
    }
    public static function ready() {
        return is_callable(array('TLIT_LP_Leads','sync_conversation')) && is_callable(array('TLIT_LP_Leads','save_status'));
    }
    public static function statuses() {
        if (is_callable(array('TLIT_LP_Leads','statuses'))) { return TLIT_LP_Leads::statuses(); }
        return array('new'=>'New','junk'=>'Junk lead','potential'=>'Potential','high_potential'=>'High potential','intended_to_join'=>'Intended to join','enrolled'=>'Enrolled');
    }
    private static function error($message) { return new WP_Error('tlit_cb_qualification', $message); }
    public static function lead($row) {
        return self::ready() && !empty($row['lead_id']) ? TLIT_LP_Leads::get((int)$row['lead_id']) : null;
    }
    public static function status($row) {
        $lead=self::lead($row);
        return $lead ? $lead->lead_status : ($row['qualification_status']??'new');
    }
    public static function data($row) {
        $tracking=json_decode($row['tracking_json']??'{}',true);
        $url=$row['source_url']??'';
        return array(
            'request_id'=>'cb-conversation-'.(int)$row['id'], 'conversation_id'=>(int)$row['id'],
            'name'=>$row['name']??'', 'phone'=>$row['phone']??'', 'email'=>$row['email']??'',
            'goal'=>$row['goal']??'', 'tracking'=>is_array($tracking)?$tracking:array(),
            'submission_url'=>$url, 'page_variant'=>TLIT_LP_Leads::variant('', $url),
            'source'=>'chatbot', 'otp_verified'=>false,
            'details'=>array('conversation_id'=>(int)$row['id'],'preferred_language'=>$row['language']??'',
                'conversation_stage'=>$row['stage']??'', 'brochure_requested'=>!empty($row['has_brochure'])?'Yes':'No',
                'phone_verification'=>'Not verified by chat capture'),
        );
    }
    private static function sync($row, $overrides=array()) {
        if (!self::ready()) { return self::error('Update the Landing Page Leads module before qualifying this conversation.'); }
        // Same parent-first lock order as privacy erasure. No CRM/network calls here.
        return TLIT_CB_Store::with_locked_conversation((int)$row['id'],function($current) use ($overrides) {
            return self::sync_locked($current,$overrides);
        });
    }
    private static function sync_locked($row, $overrides) {
        if (($row['course']??'')!=='digital_marketing') { return self::error('Automatic TeleCRM handoff is available for Digital Marketing conversations.'); }
        $data=array_replace(self::data($row),$overrides);
        if (TLIT_LP_Leads::canonical_phone($data['phone'])==='') { return self::error('A valid mobile number is needed before this lead can be qualified and sent to TeleCRM. Email is optional.'); }
        $result=TLIT_LP_Leads::sync_conversation($data,(int)($row['lead_id']??0));
        if (is_wp_error($result)) { return $result; }
        $lead=TLIT_LP_Leads::get((int)$result['lead_id']);
        if ($lead && empty($row['lead_id']) && ($row['qualification_status']??'new')==='junk' && $lead->lead_status==='new') {
            $lead=TLIT_LP_Leads::save_status($lead->id,'junk',$row['qualification_note']??'',0,false);
            if (is_wp_error($lead)) { return $lead; }
        }
        if (!$lead || !TLIT_CB_Store::update_qualification($row['id'],$lead->id,$lead->lead_status,$lead->advisor_note)) {
            return self::error('The lead was saved, but its conversation link could not be updated. Please retry; the same lead reference will be reused.');
        }
        return $result;
    }
    /** Called inside the parent privacy transaction, so partial erasure rolls back. */
    public static function erase($result, $id) {
        if ($result!==true) { return $result; }
        $row=TLIT_CB_Store::get((int)$id);
        if (TLIT_CB_Store::read_error()) { return self::error('The linked lead could not be read for erasure. Please retry.'); }
        if (!$row || empty($row['lead_id'])) { return true; }
        if (!is_callable(array('TLIT_LP_Leads','erase_conversation_lead')) || !TLIT_LP_Leads::erase_conversation_lead((int)$row['lead_id'],(int)$id)) {
            return self::error('The linked lead could not be erased. Check the Landing Page Leads module and retry.');
        }
        return true;
    }
    /** New chat events never qualify or send a lead. Refresh existing local metadata only. */
    public static function capture($id, $event=array()) {
        if (!self::ready()) { return; }
        $row=TLIT_CB_Store::get((int)$id);
        if (!$row || empty($row['lead_id']) || ($row['course']??'')!=='digital_marketing') { return; }
        self::sync($row);
    }
    /** Trusted server adapter: resolve the raw session privately, never accept a browser lead ID. */
    public static function brochure($token, array $data) {
        if (!self::ready()) { return null; }
        $row=TLIT_CB_Store::for_session($token);
        if (TLIT_CB_Store::read_error()) { return self::error('The conversation could not be loaded. Please retry.'); }
        if (!$row || ($row['course']??'')!=='digital_marketing') { return null; }
        $overrides=array_intersect_key($data,array_flip(array('name','phone','email','goal','tracking','submission_url','page_variant','otp_verified')));
        if (trim((string)($row['goal']??''))!=='') { $overrides['goal']=$row['goal']; }
        $overrides['source']='chatbot_brochure';
        $overrides['details']=array_merge(self::data($row)['details'],array('brochure_requested'=>'Yes'));
        return self::sync($row,$overrides);
    }
    public static function qualify($id, $status, $note, $actor) {
        if (!isset(self::statuses()[$status])) { return self::error('Choose a valid lead qualification.'); }
        $row=TLIT_CB_Store::get((int)$id);
        if (!$row) { return self::error('Conversation not found. Reload the inbox and try again.'); }
        if (($row['course']??'')!=='digital_marketing') { return self::error('This qualification flow is for Digital Marketing conversations.'); }
        // Recheck linkage under the same parent lock as a concurrent brochure save.
        // New/Junk must also hold an existing canonical lead, even with incomplete contact data.
        if (in_array($status,array('new','junk'),true)) {
            $saved_review = TLIT_CB_Store::with_locked_conversation($id,function($current) use($status,$note,$actor) {
                $lead_id=(int)($current['lead_id']??0);
                if ($lead_id) {
                    if (!self::ready()) { return self::error('Update the Landing Page Leads module before changing this linked lead.'); }
                    $saved=TLIT_LP_Leads::save_status($lead_id,$status,$note,(int)$actor,false);
                    if (is_wp_error($saved)) { return $saved; }
                }
                return TLIT_CB_Store::update_qualification($current['id'],$lead_id,$status,$note) ? true : self::error('The qualification could not be saved.');
            });
            if (!is_wp_error($saved_review)) { do_action('tlit_cb_review_saved',$id,$status,(int)$actor); }
            return $saved_review;
        }
        $synced=self::sync($row);
        if (is_wp_error($synced)) { return $synced; }
        $saved=TLIT_LP_Leads::save_status((int)$synced['lead_id'],$status,$note,(int)$actor);
        if (is_wp_error($saved)) { return $saved; }
        // Canonical state remains in the lead service, including a failed or uncertain CRM result.
        if (!TLIT_CB_Store::update_qualification($id,$saved->id,$saved->lead_status,$saved->advisor_note)) {
            return self::error('The lead review was saved. Reload to see the delivery result before retrying.');
        }
        return $saved;
    }
    public static function save() {
        if (!current_user_can('manage_options')) { wp_die('Access denied.','',array('response'=>403)); }
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { wp_die('Use the qualification form.','',array('response'=>405)); }
        check_admin_referer('tlit_cb_qualify');
        $p=wp_unslash($_POST);
        $id=is_scalar($p['id']??null)?absint($p['id']):0;
        $status=is_string($p['lead_status']??null)?$p['lead_status']:'';
        $note=is_string($p['note']??null)?$p['note']:'';
        $result=self::qualify($id,$status,$note,get_current_user_id());
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()),'',array('response'=>400)); }
        wp_safe_redirect(admin_url('admin.php?page=tlit-chat-bridge&conversation='.$id.'&qualified=1')); exit;
    }
    public static function panel($row) {
        if (!current_user_can('manage_options') || ($row['course']??'')!=='digital_marketing') { return; }
        echo '<section class="tlit-cb-side-panel tlit-cb-qualification"><h3>Lead qualification</h3>';
        if (!self::ready()) { echo '<p>Update the Landing Page Leads module to enable qualification through your existing TeleCRM connection.</p></section>'; return; }
        $lead=self::lead($row);$status=$lead?$lead->lead_status:($row['qualification_status']??'new');
        $note=$lead?$lead->advisor_note:($row['qualification_note']??'');
        if (isset($_GET['qualified']) && $_GET['qualified']==='1') { echo '<p role="status">Lead qualification saved. Check the delivery status below.</p>'; }
        echo '<p>New and Junk leads stay in WordPress. Saving any other TeleCRM status automatically syncs through the existing connection.</p>';
        $mapping=is_callable(array('TLIT_LP_Leads','crm_statuses'))?TLIT_LP_Leads::crm_statuses():array();
        if (isset($mapping[$status])) { echo '<p class="tlit-cb-help">TeleCRM status: <strong>'.esc_html($mapping[$status]).'</strong></p>'; }
        if (TLIT_LP_Leads::canonical_phone($row['phone']??'')==='') { echo '<p class="tlit-cb-help">This visitor has not provided a valid mobile number. You can mark the conversation New or Junk; qualification needs a mobile number. Email is optional.</p>'; }
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="tlit_cb_qualify"><input type="hidden" name="id" value="'.(int)$row['id'].'">';
        wp_nonce_field('tlit_cb_qualify');
        echo '<label for="tlit-cb-lead-status">Counsellor status</label><select id="tlit-cb-lead-status" name="lead_status">';
        foreach(self::statuses() as $key=>$label) { echo '<option value="'.esc_attr($key).'"'.selected($status,$key,false).'>'.esc_html($label).'</option>'; }
        echo '</select><label for="tlit-cb-lead-note">Counsellor note</label><textarea id="tlit-cb-lead-note" name="note" rows="3" maxlength="4000">'.esc_html($note).'</textarea><button class="button tlit-cb-button tlit-cb-status-save" type="submit">Save qualification</button></form>';
        if ($lead) {
            $labels=array('sent'=>'Synced to TeleCRM','pending'=>'Not sent','held'=>'Held in WordPress','failed'=>'Sync failed — retry from the lead record','uncertain'=>'Check TeleCRM before retrying','sending'=>'Sync in progress','blocked'=>'Connection needs attention');
            echo '<p><strong>'.esc_html($labels[$lead->crm_status]??ucfirst($lead->crm_status)).'</strong><br><a href="'.esc_url(admin_url('admin.php?page=tlit-landing-leads&lead='.(int)$lead->id)).'">Open lead LP-'.(int)$lead->id.'</a></p>';
        }
        echo '</section>';
    }
}
