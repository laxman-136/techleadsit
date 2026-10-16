<?php
/** No WordPress admin shell, tracking scripts or cross-lead navigation on the team page. */
if (!defined('ABSPATH')) { exit; }

final class TLIT_CB_Team_View {
    private static function start($title) {
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>' . esc_html($title) . ' · Tech Leads IT</title><link rel="stylesheet" href="' . esc_url(TLIT_CB_URL . 'assets/team.css?v=1') . '"></head><body><header class="team-brand"><div class="team-brand-mark" aria-hidden="true">T</div><strong>Tech Leads IT</strong><span>Conversation Bridge</span></header><main class="team-main">';
    }
    private static function end() { echo '</main><footer class="team-footer">Private team workspace <span>All times shown in IST</span></footer></body></html>'; }
    private static function hidden($name, $value) { echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">'; }
    private static function message($text, $success = false) { if ($text !== '') { echo '<p class="team-alert' . ($success ? ' team-alert-success' : '') . '" role="' . ($success ? 'status' : 'alert') . '">' . esc_html($text) . '</p>'; } }
    private static function date($value) { return TLIT_LP_Leads::display_date($value); }
    private static function fields($values) {
        echo '<dl class="team-fields">';
        foreach ($values as $label => $value) {
            if (!is_scalar($value)) { continue; }
            echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html((string) $value !== '' ? (string) $value : 'Not provided') . '</dd></div>';
        }
        echo '</dl>';
    }

    public static function unavailable() {
        self::start('Private team access');
        echo '<section class="team-gate"><p class="team-eyebrow">PRIVATE LEAD ACCESS</p><h1>This link is unavailable.</h1><p>It may have expired or been disabled. Ask your WordPress administrator for access to this lead.</p></section>';
        self::end();
    }

    public static function login($url, $csrf, $error) {
        self::start('Open a lead');
        echo '<section class="team-gate"><span class="team-lock" aria-hidden="true">&#128274;</span><p class="team-eyebrow">YOUR TEAM’S NEXT CONVERSATION</p><h1>One lead.<br>One private code.</h1><p>Enter the four-digit PIN from this lead’s Teams message to view the details and save your review.</p>';
        self::message($error);
        echo '<form method="post" action="' . esc_url($url) . '">'; self::hidden('operation', 'login'); self::hidden('csrf', $csrf);
        echo '<label for="team-pin">Lead PIN</label><input class="team-pin" id="team-pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="off" placeholder="••••" required autofocus><button class="team-button" type="submit">Open lead <span aria-hidden="true">→</span></button></form><p class="team-help">This link opens only its own lead. Your session ends after 30 minutes.</p></section>';
        self::end();
    }

    public static function detail($grant, $row, $session, $url, $error, $saved) {
        $chat = $grant['entity_type'] === 'chatbot';
        $data = $chat ? array() : (json_decode($row['payload'], true) ?: array());
        $linked = $chat ? TLIT_CB_Qualification::lead($row) : (object) $row;
        $status = $linked ? $linked->lead_status : ($row['qualification_status'] ?? 'new');
        $note = $linked ? $linked->advisor_note : ($row['qualification_note'] ?? '');
        $reviewer = '';
        if (preg_match('/^\[Team Bridge(?: — ([^\]\r\n]*))?\]\r?\n/', $note, $prefix)) {
            $reviewer = $prefix[1] ?? ''; $note = substr($note, strlen($prefix[0]));
        }
        $statuses = TLIT_CB_Qualification::statuses();
        $tracking = $chat ? (json_decode($row['tracking_json'], true) ?: array()) : ($data['tracking'] ?? array());
        $analysis = $chat ? (json_decode($row['goal_analysis_json'] ?? '{}', true) ?: array()) : array();
        $goal = $analysis['summary'] ?? ($chat ? ($row['goal'] ?? '') : ($data['goal'] ?? ''));
        $source_url = $chat ? $row['source_url'] : $row['submission_url'];
        $variant = $chat ? TLIT_LP_Leads::variant('', $source_url) : $row['page_variant'];
        self::start('Lead ' . $grant['identity_key']);
        echo '<div class="team-page-top"><div><p class="team-eyebrow">' . esc_html($grant['identity_key']) . ' / ' . ($chat ? 'CHATBOT ENQUIRY' : 'LANDING PAGE LEAD') . '</p><h1>' . esc_html($row['name'] ?: 'Course enquiry') . '</h1><p>Received ' . esc_html(self::date($row['created_at'])) . '</p></div><form method="post" action="' . esc_url($url) . '">';
        self::hidden('operation', 'logout'); self::hidden('csrf', $session['csrf']);
        echo '<button class="team-button team-button-secondary" type="submit">Lock this lead</button></form></div>';
        self::message($error); self::message($saved ? 'Review saved. The latest TeleCRM result is shown below.' : '', true);
        echo '<div class="team-contact-bar"><a href="' . esc_url('tel:' . $row['phone']) . '">' . esc_html($row['phone'] ?: 'Mobile not provided') . '</a>';
        if (!empty($row['email'])) { echo '<a href="' . esc_url('mailto:' . $row['email']) . '">' . esc_html($row['email']) . '</a>'; }
        echo '<span class="team-tag">' . esc_html($statuses[$status] ?? 'New') . '</span></div><div class="team-layout"><div class="team-content"><section class="team-panel"><div class="team-panel-head"><h2>Learner context</h2><span>' . esc_html(strtoupper($variant)) . '</span></div><div class="team-panel-body"><div class="team-goal"><p class="team-eyebrow">LEARNING GOAL</p><h3>' . esc_html($goal ?: 'More context needed to identify a learning goal.') . '</h3>';
        if (!empty($analysis['evidence'])) { echo '<p>Based on: “' . esc_html($analysis['evidence']) . '”</p>'; }
        echo '</div>';
        self::fields(array('Course' => 'AI Powered Digital Marketing', 'Language' => $chat ? $row['language'] : ($data['details']['preferred_language'] ?? ''), 'Enquiry' => $chat ? 'Chatbot conversation' : (TLIT_LP_Leads::labels()[$row['source']] ?? $row['source']), 'Brochure' => ($chat ? !empty($row['has_brochure']) : !empty($data['brochure_downloaded'])) ? 'Requested / downloaded' : 'Not recorded'));
        if (!$chat) { self::fields(array('Current profile' => $data['status'] ?? ($data['details']['student_status'] ?? ''), 'Joining timeline' => $data['timeline'] ?? ($data['details']['start_timeline'] ?? ''), 'Question or interest' => $data['question'] ?? ($data['details']['main_question'] ?? ''), 'Preferred time to call' => $data['details']['preferred_call_time'] ?? '')); }
        echo '</div></section>';
        if ($chat) {
            $messages = TLIT_CB_Store::messages((int) $grant['entity_id']); $failed = TLIT_CB_Store::read_error();
            echo '<section class="team-panel"><div class="team-panel-head"><h2>Conversation</h2><span>' . ($failed ? 'Unavailable' : count($messages) . ' messages') . '</span></div><div class="team-transcript">';
            if ($failed) { self::message('The conversation could not be loaded. Please reload this page.'); }
            elseif (!$messages) { echo '<p class="team-help">No messages have been saved yet.</p>'; }
            foreach ($messages as $message) {
                $visitor = $message['role'] === 'visitor';
                echo '<article class="team-message' . ($visitor ? ' team-message-visitor' : '') . '"><div class="team-message-label"><strong>' . esc_html($visitor ? ($row['name'] ?: 'Visitor') : ($message['role'] === 'assistant' ? 'Lakshmi' : 'Conversation event')) . '</strong><time>' . esc_html(self::date($message['created_at'])) . '</time></div><p>' . esc_html($message['content']) . '</p></article>';
            }
            echo '</div></section>';
        }
        echo '<details class="team-panel team-attribution"><summary>Page and campaign details</summary><div class="team-panel-body">';
        $fields = array('Submitted from' => $source_url);
        foreach (array('utm_source' => 'Campaign source', 'utm_medium' => 'Medium', 'utm_campaign' => 'Campaign', 'utm_content' => 'Ad content', 'city' => 'City', 'state' => 'State') as $key => $label) { if (!empty($tracking[$key]) && is_scalar($tracking[$key])) { $fields[$label] = (string) $tracking[$key]; } }
        self::fields($fields); echo '</div></details></div><aside class="team-review"><section class="team-panel"><div class="team-panel-head"><h2>Counsellor review</h2></div><div class="team-panel-body"><p class="team-help">Contact the learner, then save the next step.</p><form method="post" action="' . esc_url($url) . '">';
        self::hidden('operation', 'review'); self::hidden('csrf', $session['csrf']);
        echo '<label for="team-reviewer">Counsellor name <span>(optional)</span></label><input id="team-reviewer" name="reviewer" maxlength="80" autocomplete="name" value="' . esc_attr($reviewer) . '"><label for="team-status">Lead qualification</label><select id="team-status" name="lead_status">';
        foreach ($statuses as $value => $label) { echo '<option value="' . esc_attr($value) . '"' . selected($status, $value, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select><label for="team-note">Call notes</label><textarea id="team-note" name="note" rows="5" maxlength="4000" placeholder="What did you discuss? What should happen next?">' . esc_textarea($note) . '</textarea><p class="team-review-rule"><strong>New and Junk stay in WordPress.</strong> Saving another status sends or updates this lead in TeleCRM.</p><button class="team-button" type="submit">Save review &amp; sync lead</button></form></div></section><section class="team-panel"><div class="team-panel-body"><p class="team-eyebrow">TELECRM SYNC</p>';
        echo '<h3>' . esc_html($linked ? (array('sent' => 'Synced to TeleCRM', 'pending' => 'Not sent', 'held' => 'Held in WordPress', 'failed' => 'Sync failed', 'uncertain' => 'Check TeleCRM before retrying', 'sending' => 'Sync in progress', 'blocked' => 'Connection needs attention')[$linked->crm_status] ?? $linked->crm_status) : 'Not sent') . '</h3><p class="team-help">' . esc_html($linked && $linked->crm_message !== '' ? $linked->crm_message : 'Waiting for a counsellor review.') . '</p>';
        if ($linked && !empty($linked->crm_updated)) { echo '<p class="team-help">Last update: ' . esc_html(self::date($linked->crm_updated)) . '</p>'; }
        echo '</div></section><p class="team-access-note">This access is limited to ' . esc_html($grant['identity_key']) . '.<br>Link expires ' . esc_html(self::date($grant['expires_at'])) . '.</p></aside></div>';
        self::end();
    }
}
