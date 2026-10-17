<?php
/** Resume a bounded, local-only analysis of historical transcripts through WP Cron. */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/goal-admin.php';

final class TLIT_CB_Goal_Backfill {
    const HOOK = 'tlit_cb_goal_backfill';
    const VERSION_OPTION = 'tlit_cb_goal_backfill_version';
    const CURSOR_OPTION = 'tlit_cb_goal_backfill_cursor';
    const ERROR_OPTION = 'tlit_cb_goal_backfill_error';
    const RUN_VERSION_OPTION = 'tlit_cb_goal_backfill_run_version';
    const BATCH_SIZE = 20;

    public static function register() {
        add_action(self::HOOK, array(__CLASS__, 'run'));
        TLIT_CB_Goal_Admin::register();
    }

    /** Installation only queues work; never scan the transcript archive in a page request. */
    public static function schedule($delay = 10) {
        if ((int) get_option(self::VERSION_OPTION, 0) === TLIT_CB_Goals::VERSION || !function_exists('wp_next_scheduled') || !function_exists('wp_schedule_single_event')) { return; }
        if((int)get_option(self::RUN_VERSION_OPTION,0)!==TLIT_CB_Goals::VERSION){delete_option(self::CURSOR_OPTION);update_option(self::RUN_VERSION_OPTION,TLIT_CB_Goals::VERSION,false);}
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_single_event(time() + max(10, (int) $delay), self::HOOK);
        }
    }

    public static function run() {
        if ((int) get_option(self::VERSION_OPTION, 0) === TLIT_CB_Goals::VERSION) { return; }
        try {
            $result = TLIT_CB_Store::backfill_goals(max(0, (int) get_option(self::CURSOR_OPTION, 0)), self::BATCH_SIZE);
            if (is_wp_error($result)) {
                update_option(self::ERROR_OPTION, time(), false);
                self::schedule(60);
                return;
            }
            delete_option(self::ERROR_OPTION);
            update_option(self::CURSOR_OPTION, $result['last_id'], false);
            if ($result['has_more']) {
                self::schedule();
            } else {
                update_option(self::VERSION_OPTION, TLIT_CB_Goals::VERSION, false);
                delete_option(self::CURSOR_OPTION);
            }
        } catch (Throwable $error) {
            // No database text or transcript is written to logs or notices.
            update_option(self::ERROR_OPTION, time(), false);
            self::schedule(60);
        }
    }
}
