<?php
/** Administrator-only aggregate progress and a bounded historical-analysis action. */
if (!defined('ABSPATH')) { exit; }

final class TLIT_CB_Goal_Admin {
    const ACTION = 'tlit_cb_goal_backfill_batch';

    public static function register() {
        add_action('admin_post_' . self::ACTION, array(__CLASS__, 'process_batch'));
    }

    /** Counts only: no names, contacts, evidence or transcripts leave the database. */
    public static function counts() {
        global $wpdb;
        $table = $wpdb->prefix . 'tlit_cb_conversations';
        $current="(COALESCE(JSON_EXTRACT(IF(JSON_VALID(goal_analysis_json),goal_analysis_json,'{}'),'$.version'),0)=".(int)TLIT_CB_Goals::VERSION.")";
        $fields = array('COUNT(*) AS total', "COALESCE(SUM(NOT $current),0) AS pending", "COALESCE(SUM(TRIM(goal)=''),0) AS blank_goal");
        foreach (array('stated', 'inferred', 'interest', 'insufficient') as $status) {
            $fields[] = "COALESCE(SUM(CASE WHEN JSON_VALID(goal_analysis_json) AND $current THEN JSON_UNQUOTE(JSON_EXTRACT(goal_analysis_json,'$.status'))='$status' ELSE 0 END),0) AS $status";
        }
        $previous = $wpdb->suppress_errors(true);
        try {
            $row = $wpdb->get_row("SELECT " . implode(',', $fields) . " FROM $table WHERE course='digital_marketing'", ARRAY_A);
            if ($wpdb->last_error !== '' || !is_array($row)) {
                return new WP_Error('tlit_cb_goal_counts', 'Learning-goal progress is temporarily unavailable.');
            }
        } finally { $wpdb->suppress_errors($previous); }
        $counts = array_map('intval', $row);
        $counts['analysed'] = $counts['stated'] + $counts['inferred'] + $counts['interest'] + $counts['insufficient'];
        $counts['unrecognised'] = max(0, $counts['total'] - $counts['pending'] - $counts['analysed']);
        return $counts;
    }

    public static function render() {
        if (!current_user_can('manage_options')) { return; }
        $counts = self::counts();
        echo '<section class="tlit-cb-side-panel tlit-cb-goal-progress" aria-labelledby="tlit-cb-goal-progress-title"><h2 id="tlit-cb-goal-progress-title">Learning goal analysis</h2>';
        if (is_wp_error($counts)) {
            echo '<p>Progress could not be loaded. Check database availability and reload this page.</p></section>';
            return;
        }
        $finished = (int) get_option(TLIT_CB_Goal_Backfill::VERSION_OPTION, 0) === TLIT_CB_Goals::VERSION;
        $retry = (bool) get_option(TLIT_CB_Goal_Backfill::ERROR_OPTION, false);
        $complete = $finished && $counts['pending'] === 0 && $counts['unrecognised'] === 0 && $counts['blank_goal'] === 0;
        echo '<p><strong>' . esc_html(number_format_i18n($counts['analysed'])) . ' of ' . esc_html(number_format_i18n($counts['total'])) . ' sessions analysed</strong> · ' . esc_html($complete ? 'Up to date' : ($retry ? 'Waiting to retry' : 'Analysis pending')) . '</p>';
        echo '<p class="tlit-cb-help">Stated in chat: ' . esc_html(number_format_i18n($counts['stated'])) . ' · Inferred from chat: ' . esc_html(number_format_i18n($counts['inferred'])) . ' · Course interest: ' . esc_html(number_format_i18n($counts['interest'])) . ' · More context needed: ' . esc_html(number_format_i18n($counts['insufficient'])) . '</p>';
        echo '<p class="tlit-cb-help">New sessions update automatically. Older saved conversations are analysed in the background without asking visitors a learning-goal question.</p>';
        if ($retry) {
            echo '<p class="tlit-cb-help">The last batch could not finish. Check database availability, then try one batch again.</p>';
        }
        if ($counts['unrecognised'] > 0 || ($finished && ($counts['pending'] > 0 || $counts['blank_goal'] > 0))) {
            echo '<p class="tlit-cb-help">Some saved analysis needs a storage check. Existing transcripts and learning goals have been preserved.</p>';
        }
        if (!$finished) {
            echo '<p class="tlit-cb-help">' . esc_html(number_format_i18n($counts['pending'])) . ' older sessions awaiting analysis. If background processing is delayed, process up to ' . esc_html(TLIT_CB_Goal_Backfill::BATCH_SIZE) . ' sessions now.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">';
            wp_nonce_field(self::ACTION);
            echo '<button class="button" type="submit">' . esc_html($counts['pending'] > 0 ? 'Process next batch' : 'Finish analysis check') . '</button></form>';
        }
        echo '</section>';
    }

    public static function process_batch() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to process learning goals.', '', array('response' => 403));
        }
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
            wp_die('Use the learning-goal progress form to process a batch.', '', array('response' => 405));
        }
        check_admin_referer(self::ACTION);
        // The existing version guard is intentional: completed analysis is never rewritten here.
        TLIT_CB_Goal_Backfill::run();
        wp_safe_redirect(admin_url('admin.php?page=tlit-chat-bridge'));
        exit;
    }
}
