<?php
/**
 * Plugin Name: Tech Leads IT Conversation Bridge
 * Description: A private conversation inbox, DOCX exports and controlled integrations for course chatbots.
 * Version: 1.5.3
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Tech Leads IT
 * License: GPL-2.0-or-later
 */
if (!defined('ABSPATH')) { exit; }
// The same module can be installed independently or bundled with Landing Pages.
if (!defined('TLIT_CB_FILE')) {
define('TLIT_CB_FILE', __FILE__);
define('TLIT_CB_VERSION', '1.5.3');
define('TLIT_CB_URL', plugin_dir_url(__FILE__));

function tlit_cb_boot() {
    require_once __DIR__ . '/includes/store.php';
    require_once __DIR__ . '/includes/goal-backfill.php';
    require_once __DIR__ . '/includes/export.php';
    require_once __DIR__ . '/includes/admin.php';
    require_once __DIR__ . '/includes/integrations.php';
    require_once __DIR__ . '/includes/qualification.php';
    require_once __DIR__ . '/includes/teams-notifications.php';
    require_once __DIR__ . '/includes/team-access.php';
    require_once __DIR__ . '/includes/lead-operations.php';
    require_once __DIR__ . '/includes/dashboard-data.php';
    require_once __DIR__ . '/includes/dashboard.php';
    TLIT_CB_Admin::register();
    TLIT_CB_Export::register();
    TLIT_CB_Integrations::register();
    TLIT_CB_Qualification::register();
    TLIT_Teams_Notifications::register();
    TLIT_CB_Team_Access::register();
    TLIT_CB_Lead_Ops::register();
    TLIT_CB_Dashboard::register();
    TLIT_CB_Goal_Backfill::register();
    add_action('init', 'tlit_cb_install', 5);
    add_action('tlit_cb_capture', 'tlit_cb_capture', 10, 1);
    add_action('admin_notices', 'tlit_cb_storage_notice');
}
function tlit_cb_install() {
    $result = TLIT_CB_Store::install();
    if (is_wp_error($result)) { update_option('tlit_cb_storage_error', time(), false); return; }
    TLIT_CB_Integrations::install();
    if (class_exists('TLIT_Teams_Notifications')) { TLIT_Teams_Notifications::install(); }
    if (class_exists('TLIT_CB_Team_Access')) { TLIT_CB_Team_Access::install(); }
    if (class_exists('TLIT_CB_Lead_Ops')) { TLIT_CB_Lead_Ops::install(); }
}
function tlit_cb_activate() {
    require_once __DIR__ . '/includes/store.php';
    require_once __DIR__ . '/includes/goal-backfill.php';
    require_once __DIR__ . '/includes/integrations.php';
    tlit_cb_install();
}
function tlit_cb_capture($event) {
    // A reporting failure must not interrupt a visitor's chat or lead submission.
    try {
        $result = TLIT_CB_Store::capture($event);
        if (is_wp_error($result)) { update_option('tlit_cb_storage_error', time(), false); return; }
        delete_option('tlit_cb_storage_error');
        // Qualification is an admin action. This only refreshes the private lead copy.
        if (class_exists('TLIT_CB_Qualification')) { TLIT_CB_Qualification::capture($result, $event); }
        do_action('tlit_cb_contact_saved', $result);
    } catch (Throwable $error) {
        update_option('tlit_cb_storage_error', time(), false);
    }
}
function tlit_cb_storage_notice() {
    if (!current_user_can('manage_options')) { return; }
    if (get_option('tlit_cb_storage_error')) {
        echo '<div class="notice notice-error"><p><strong>Conversation Bridge:</strong> Some conversation data could not be saved. Check database availability and the plugin tables before relying on exports.</p></div>';
    }
    if (get_option(TLIT_CB_Goal_Backfill::ERROR_OPTION)) {
        echo '<div class="notice notice-warning"><p><strong>Conversation Bridge:</strong> Analysis of older learning goals is waiting to retry. Check database availability and WordPress Cron if this notice continues. Existing transcripts are preserved.</p></div>';
    }
}
add_action('plugins_loaded', 'tlit_cb_boot', 5);
if (function_exists('register_activation_hook')) { register_activation_hook(__FILE__, 'tlit_cb_activate'); }
}
