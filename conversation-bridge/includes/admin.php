<?php
/** Conversation Bridge inbox. All screens and mutations are administrator-only. */
if (!defined('ABSPATH')) {
    exit;
}

class TLIT_CB_Admin {
    const SLUG = 'tlit-chat-bridge';

    public static function register() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('admin_post_tlit_cb_status', array(__CLASS__, 'save_status'));
    }

    public static function menu() {
        add_menu_page('Conversation Bridge', 'Conversation Bridge', 'manage_options', self::SLUG, array(__CLASS__, 'render'), 'dashicons-format-chat', 58);
    }

    public static function assets($hook) {
        if ($hook !== 'toplevel_page_' . self::SLUG) {
            return;
        }
        wp_enqueue_style('tlit-cb-admin', TLIT_CB_URL . 'assets/admin.css', array(), TLIT_CB_VERSION);
        if (($_GET['view'] ?? '') === 'dashboard') { wp_enqueue_style('tlit-cb-dashboard', TLIT_CB_URL . 'assets/dashboard.css', array('tlit-cb-admin'), TLIT_CB_VERSION . '-desk2'); }
        wp_enqueue_script('tlit-cb-admin', TLIT_CB_URL . 'assets/admin.js', array(), TLIT_CB_VERSION, true);
    }

    /** Collect only supported scalar filters; the store validates them again. */
    public static function filters($input = null) {
        if ($input === null) {
            $input = wp_unslash($_GET);
        }
        $input = is_array($input) ? $input : array();
        $filters = array();
        foreach (array('search' => 200, 'course' => 100, 'language' => 50) as $key => $limit) {
            if (isset($input[$key]) && is_scalar($input[$key])) {
                $value = trim(sanitize_text_field((string) $input[$key]));
                $value = function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
                if ($value !== '') {
                    $filters[$key] = $value;
                }
            }
        }
        foreach (array('date_from', 'date_to') as $key) {
            $value = isset($input[$key]) && is_string($input[$key]) ? $input[$key] : '';
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                $filters[$key] = $value;
            }
        }
        if (isset($filters['date_from'], $filters['date_to']) && $filters['date_from'] > $filters['date_to']) {
            $from = $filters['date_to'];
            $filters['date_to'] = $filters['date_from'];
            $filters['date_from'] = $from;
        }
        $status = isset($input['status']) && is_string($input['status']) ? $input['status'] : '';
        if (isset(self::statuses()[$status])) {
            $filters['status'] = $status;
        }
        foreach (array('has_phone', 'has_brochure') as $key) {
            if (isset($input[$key]) && in_array($input[$key], array('yes', 'no'), true)) {
                $filters[$key] = $input[$key];
            }
        }
        return $filters;
    }

    public static function statuses() {
        return array('new' => 'New', 'reviewed' => 'Reviewed', 'contacted' => 'Contacted', 'archived' => 'Archived');
    }

    private static function integer($input, $default = 0) {
        return is_scalar($input) ? max(0, (int) $input) : $default;
    }

    private static function value($row, $key, $fallback = '') {
        return isset($row[$key]) && is_scalar($row[$key]) ? (string) $row[$key] : $fallback;
    }

    public static function url($args = array()) {
        return add_query_arg(array_merge(array('page' => self::SLUG), $args), admin_url('admin.php'));
    }

    private static function hidden($name, $value) {
        echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
    }

    private static function hidden_filters($filters) {
        foreach ($filters as $key => $value) {
            self::hidden('filters[' . $key . ']', $value);
        }
    }

    private static function export_fields($scope, $filters = array()) {
        self::hidden('action', 'tlit_cb_export');
        self::hidden('scope', $scope);
        wp_nonce_field('tlit_cb_export');
        self::hidden_filters($filters);
    }

    private static function day($utc, $format = 'M j, Y') {
        if (!$utc || !is_string($utc)) {
            return '—';
        }
        $timestamp = strtotime($utc . ' UTC');
        return $timestamp === false ? '—' : wp_date($format, $timestamp, new DateTimeZone('Asia/Kolkata'));
    }

    private static function badge($status) {
        $labels = self::statuses();
        $status = isset($labels[$status]) ? $status : 'new';
        return '<span class="tlit-cb-status tlit-cb-status--' . esc_attr($status) . '"><span aria-hidden="true"></span>' . esc_html($labels[$status]) . '</span>';
    }

    private static function label($key) {
        return ucwords(str_replace(array('_', '-'), ' ', $key));
    }

    /** Read analysis without writing from an admin GET or fetching transcripts per inbox row. */
    private static function goal_analysis($row, $messages = null) {
        $analysis = json_decode(self::value($row, 'goal_analysis_json'), true);
        if (is_array($analysis) && !empty($analysis['summary']) && is_string($analysis['summary'])) {
            return $analysis;
        }
        $goal = self::value($row, 'goal');
        if ($messages !== null && self::value($row, 'course') === 'digital_marketing' && class_exists('TLIT_CB_Goals')) {
            return TLIT_CB_Goals::analyze($messages, TLIT_CB_Goals::is_fallback($goal) ? '' : $goal);
        }
        return array('summary' => $goal, 'status' => $goal !== '' ? (stripos($goal, 'Likely') === 0 ? 'inferred' : 'stated') : (self::value($row, 'course') === 'digital_marketing' ? 'pending' : 'unrecorded'));
    }

    private static function goal_label($status) {
        return array('stated' => 'Stated in chat', 'inferred' => 'Inferred from chat', 'interest' => 'Course interest', 'insufficient' => 'More context needed', 'pending' => 'Analysis pending', 'unrecorded' => 'Not recorded')[$status] ?? 'Conversation analysis';
    }

    private static function goal_detail($row, $messages, $messages_error) {
        $analysis = self::goal_analysis($row, $messages_error ? null : $messages);
        $summary = self::value($analysis, 'summary');
        $status = self::value($analysis, 'status', 'pending');
        echo '<div class="tlit-cb-goal-detail"><dt>Learning goal</dt><dd>';
        echo '<span class="tlit-cb-goal-label">' . esc_html(self::goal_label($status)) . '</span>';
        echo '<span class="tlit-cb-goal-summary">' . esc_html($summary ?: ($status === 'unrecorded' ? 'No learning goal recorded.' : ($messages_error ? 'Waiting for the transcript to load.' : 'Saved conversations are being analysed in the background.'))) . '</span>';
        $evidence = self::value($analysis, 'evidence');
        if ($evidence !== '') {
            echo '<span class="tlit-cb-goal-evidence">Based on: “' . esc_html($evidence) . '”</span>';
        }
        echo '</dd></div>';
    }

    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view conversations.', 'tlit-conversation-bridge'), '', array('response' => 403));
        }
        $filters = self::filters();
        $view = isset($_GET['view']) && is_string($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
        $id = self::integer($_GET['conversation'] ?? 0);
        echo '<div class="wrap tlit-cb'.($view==='dashboard'?' tlit-cb--dashboard':'').'">';
        echo '<header class="tlit-cb-header"><div><p class="tlit-cb-eyebrow">TECH LEADS IT <span aria-hidden="true">/</span> '.($view==='dashboard'?'TEAM WORKSPACE':'CHATBOT WORKSPACE').'</p><h1>'.($view==='dashboard'?'Lead dashboard':'Conversation Bridge').'</h1><p class="tlit-cb-intro">'.($view==='dashboard'?'Every enquiry, its owner and the next step.':'Every conversation, ready for your next step.').'</p></div><span class="tlit-cb-admin-label"><span class="dashicons dashicons-lock" aria-hidden="true"></span> Admin workspace</span></header>';
        echo '<nav class="tlit-cb-tabs" aria-label="Conversation Bridge sections"><a class="'.($view==='dashboard'?'is-active':'').'" href="'.esc_url(self::url(array('view'=>'dashboard'))).'"'.($view==='dashboard'?' aria-current="page"':'').'><span class="dashicons dashicons-chart-area" aria-hidden="true"></span> Dashboard</a><a class="' . (!in_array($view,array('dashboard','integrations'),true) ? 'is-active' : '') . '" href="' . esc_url(self::url()) . '"' . (!in_array($view,array('dashboard','integrations'),true) ? ' aria-current="page"' : '') . '><span class="dashicons dashicons-format-chat" aria-hidden="true"></span> Conversations</a>';
        if (class_exists('TLIT_LP_Leads')) { echo '<a href="'.esc_url(admin_url('admin.php?page=tlit-landing-leads')).'"><span class="dashicons dashicons-forms" aria-hidden="true"></span> Landing page leads</a>'; }
        echo '<a class="' . ($view === 'integrations' ? 'is-active' : '') . '" href="' . esc_url(self::url(array('view' => 'integrations'))) . '"' . ($view === 'integrations' ? ' aria-current="page"' : '') . '><span class="dashicons dashicons-admin-links" aria-hidden="true"></span> Integrations</a></nav>';
        if ($view === 'dashboard') {
            if (class_exists('TLIT_CB_Dashboard')) { TLIT_CB_Dashboard::render(); }
        } elseif ($view === 'integrations') {
            if (function_exists('tlit_cb_render_integrations')) { tlit_cb_render_integrations(); }
        } elseif ($id) {
            self::detail($id, $filters);
        } else {
            if (class_exists('TLIT_CB_Goal_Admin')) { TLIT_CB_Goal_Admin::render(); }
            self::inbox($filters);
        }
        echo '<footer class="tlit-cb-footer"><span>Conversation Bridge</span><span>Dates shown in IST (Asia/Kolkata) · Administrator access</span></footer></div>';
    }

    private static function inbox($filters) {
        $page = max(1, self::integer($_GET['paged'] ?? 1, 1));
        $per_page = self::integer($_GET['per_page'] ?? 25, 25);
        $per_page = in_array($per_page, array(25, 50, 100), true) ? $per_page : 25;
        $result = TLIT_CB_Store::query($filters, $page, $per_page);
        $total = max(0, (int) ($result['total'] ?? 0));
        $pages = max(1, (int) ceil($total / $per_page));
        if ($page > $pages && empty($result['error'])) {
            $page = $pages;
            $result = TLIT_CB_Store::query($filters, $page, $per_page);
        }
        $rows = isset($result['rows']) && is_array($result['rows']) ? $result['rows'] : array();
        $stats = TLIT_CB_Store::stats($filters);
        echo '<section class="tlit-cb-stats" aria-label="Conversation summary">';
        foreach (array('total' => array('Conversations', 'dashicons-format-chat', 'Matching your filters'), 'with_phone' => array('With a mobile number', 'dashicons-smartphone', 'Contact details captured'), 'brochure' => array('Brochure requests', 'dashicons-media-document', 'Interested in course details'), 'handoff' => array('Team handoffs', 'dashicons-groups', 'Ready for a human response')) as $key => $data) {
            echo '<div class="tlit-cb-stat"><div class="tlit-cb-stat-label"><span>' . esc_html($data[0]) . '</span><span class="dashicons ' . esc_attr($data[1]) . '" aria-hidden="true"></span></div><strong>' . esc_html(number_format_i18n((int) ($stats[$key] ?? 0))) . '</strong><span class="tlit-cb-stat-note">' . esc_html($data[2]) . '</span></div>';
        }
        echo '</section>';
        self::filter_form($filters, $per_page);
        if (!empty($result['error'])) {
            echo '<div class="notice notice-error inline"><p>Conversations could not be loaded. Refresh the page, or check the database connection if this continues.</p></div>';
        }
        echo '<section class="tlit-cb-inbox" aria-labelledby="tlit-cb-inbox-heading"><div class="tlit-cb-section-heading"><div><h2 id="tlit-cb-inbox-heading">Conversation inbox <span class="tlit-cb-count">' . esc_html(number_format_i18n($total)) . '</span></h2><p>' . ($filters ? 'Conversations matching your filters.' : 'All captured chatbot conversations, newest first.') . '</p></div><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="tlit-cb-export-filtered">';
        self::export_fields('filtered', $filters);
        echo '<button type="submit" class="button tlit-cb-button tlit-cb-button-secondary"' . (!$total || $total > 2000 ? ' disabled' : '') . '><span class="dashicons dashicons-download" aria-hidden="true"></span> Download ' . ($filters ? 'filtered' : 'all') . ' ZIP</button></form></div>';
        echo '<div class="tlit-cb-export-note"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><span>Each ZIP contains one Word document per conversation. Maximum 2,000 conversations per export; use a custom date range to narrow your selection.' . ($total > 2000 ? ' <strong>Apply narrower filters to enable this download.</strong>' : '') . '</span></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="tlit-cb-selection-form">';
        self::export_fields('selected', $filters);
        echo '<div class="tlit-cb-table-toolbar"><div class="tlit-cb-selection-summary" aria-live="polite"><span data-tlit-selection-count>0</span> selected <span class="tlit-cb-muted">on this page</span></div><button type="submit" class="button tlit-cb-button tlit-cb-button-small" data-tlit-download-selected><span class="dashicons dashicons-download" aria-hidden="true"></span> Download selected ZIP</button></div>';
        if ($rows) {
            echo '<div class="tlit-cb-table-scroll"><table class="tlit-cb-table"><caption class="screen-reader-text">Captured chatbot conversations. Select records to download Word documents in a ZIP.</caption><thead><tr><td class="tlit-cb-check"><input type="checkbox" data-tlit-select-all aria-label="Select all conversations on this page"></td><th scope="col">Visitor</th><th scope="col">Course &amp; language</th><th scope="col">Learning goal</th><th scope="col">Progress</th><th scope="col">Status</th><th scope="col">Created</th><th scope="col"><span class="screen-reader-text">Open conversation</span></th></tr></thead><tbody>';
            foreach ($rows as $row) {
                self::table_row($row, $filters, $page, $per_page);
            }
            echo '</tbody></table></div>';
        } else {
            echo '<div class="tlit-cb-empty"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span><h3>' . ($filters ? 'No conversations match these filters' : 'Your conversation inbox is ready') . '</h3><p>' . ($filters ? 'Try a wider date range, a different search, or clear your filters.' : 'New chatbot conversations will appear here when visitors start chatting on a connected landing page.') . '</p>';
            if ($filters) {
                echo '<a class="button tlit-cb-button" href="' . esc_url(self::url()) . '">Clear filters</a>';
            }
            echo '</div>';
        }
        echo '</form>';
        self::pagination($filters, $page, $per_page, $total);
        echo '</section>';
    }

    private static function filter_form($filters, $per_page) {
        $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata'));
        echo '<section class="tlit-cb-filters" aria-labelledby="tlit-cb-filter-heading"><div class="tlit-cb-filter-heading"><h2 id="tlit-cb-filter-heading"><span class="dashicons dashicons-filter" aria-hidden="true"></span> Filter conversations</h2><div class="tlit-cb-date-shortcuts" aria-label="Quick date ranges">';
        foreach (array(0 => 'Today', 6 => 'Last 7 days', 29 => 'Last 30 days') as $days => $label) {
            $range = array('date_from' => $today->modify('-' . $days . ' days')->format('Y-m-d'), 'date_to' => $today->format('Y-m-d'));
            $active = ($filters['date_from'] ?? '') === $range['date_from'] && ($filters['date_to'] ?? '') === $range['date_to'];
            echo '<a class="' . ($active ? 'is-active' : '') . '" href="' . esc_url(self::url(array_merge($filters, $range, array('per_page' => $per_page)))) . '"' . ($active ? ' aria-current="true"' : '') . '>' . esc_html($label) . '</a>';
        }
        echo '</div></div><form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        self::hidden('page', self::SLUG);
        self::hidden('per_page', $per_page);
        echo '<div class="tlit-cb-filter-grid"><label class="tlit-cb-field tlit-cb-search"><span>Search visitors</span><span class="tlit-cb-input-icon"><span class="dashicons dashicons-search" aria-hidden="true"></span><input type="search" name="search" value="' . esc_attr($filters['search'] ?? '') . '" maxlength="200" placeholder="Name, email or mobile number"></span></label>';
        self::select_field('course', 'Course', self::distinct_options('course', $filters), $filters, 'All courses');
        self::select_field('language', 'Language', self::distinct_options('language', $filters), $filters, 'All languages');
        self::select_field('status', 'Status', self::statuses(), $filters, 'All statuses');
        echo '<label class="tlit-cb-field"><span>From date</span><input type="date" name="date_from" value="' . esc_attr($filters['date_from'] ?? '') . '"></label><label class="tlit-cb-field"><span>Through date</span><input type="date" name="date_to" value="' . esc_attr($filters['date_to'] ?? '') . '"></label>';
        self::select_field('has_phone', 'Mobile number', array('yes' => 'Captured', 'no' => 'Not captured'), $filters, 'Any');
        self::select_field('has_brochure', 'Brochure', array('yes' => 'Requested', 'no' => 'Not requested'), $filters, 'Any');
        echo '</div><div class="tlit-cb-filter-bottom"><p>Dates include the full day in ' . 'IST (Asia/Kolkata)' . '.</p><div><a href="' . esc_url(self::url(array('per_page' => $per_page))) . '" class="tlit-cb-reset">Reset filters</a><button class="button button-primary tlit-cb-button" type="submit">Apply filters</button></div></div></form></section>';
    }

    private static function distinct_options($column, $filters) {
        $values = method_exists('TLIT_CB_Store', 'distinct_values') ? TLIT_CB_Store::distinct_values($column) : array();
        $options = array();
        foreach ((array) $values as $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $options[(string) $value] = self::label((string) $value);
            }
        }
        if (!empty($filters[$column]) && !isset($options[$filters[$column]])) {
            $options[$filters[$column]] = self::label($filters[$column]);
        }
        return $options;
    }

    private static function select_field($name, $label, $options, $filters, $empty) {
        echo '<label class="tlit-cb-field"><span>' . esc_html($label) . '</span><select name="' . esc_attr($name) . '"><option value="">' . esc_html($empty) . '</option>';
        foreach ($options as $value => $text) {
            echo '<option value="' . esc_attr($value) . '"' . selected($filters[$name] ?? '', $value, false) . '>' . esc_html($text) . '</option>';
        }
        echo '</select></label>';
    }

    private static function table_row($row, $filters, $page, $per_page) {
        $id = self::integer($row['id'] ?? 0);
        $name = self::value($row, 'name', '') ?: 'Visitor #' . $id;
        $phone = self::value($row, 'phone');
        $email = self::value($row, 'email');
        $initial = function_exists('mb_substr') ? mb_substr($name, 0, 1) : substr($name, 0, 1);
        $url = self::url(array_merge($filters, array('conversation' => $id, 'paged' => $page, 'per_page' => $per_page)));
        echo '<tr><td class="tlit-cb-check"><input type="checkbox" name="ids[]" value="' . esc_attr($id) . '" data-tlit-select-row aria-label="' . esc_attr('Select conversation with ' . $name) . '"></td><td><div class="tlit-cb-person"><span class="tlit-cb-avatar" aria-hidden="true">' . esc_html($initial) . '</span><div><a class="tlit-cb-person-name" href="' . esc_url($url) . '">' . esc_html($name) . '</a><span class="tlit-cb-person-phone' . (!$phone ? ' tlit-cb-muted' : '') . '">' . esc_html($phone ?: 'No mobile number') . '</span>';
        if ($email) {
            echo '<span class="tlit-cb-person-email">' . esc_html($email) . '</span>';
        }
        $analysis = self::goal_analysis($row);
        echo '</div></div></td><td><span class="tlit-cb-course">' . esc_html(self::label(self::value($row, 'course', 'Unspecified'))) . '</span><span class="tlit-cb-cell-secondary">' . esc_html(self::label(self::value($row, 'language') ?: 'Not specified')) . '</span></td><td class="tlit-cb-goal-cell"><span class="tlit-cb-goal-label">' . esc_html(self::goal_label(self::value($analysis, 'status'))) . '</span><span class="tlit-cb-goal-summary">' . esc_html(self::value($analysis, 'summary') ?: (self::value($analysis, 'status') === 'unrecorded' ? '—' : 'Waiting for analysis')) . '</span></td><td><span class="tlit-cb-stage">' . esc_html(self::label(self::value($row, 'stage') ?: 'Started')) . '</span><span class="tlit-cb-cell-secondary">' . esc_html(number_format_i18n((int) ($row['answer_count'] ?? 0))) . ' answers' . (!empty($row['has_brochure']) ? ' <span class="tlit-cb-brochure" title="Brochure requested"><span class="dashicons dashicons-media-document" aria-hidden="true"></span><span class="screen-reader-text">Brochure requested</span></span>' : '') . '</span></td><td>' . self::badge(self::value($row, 'status', 'new')) . '</td><td class="tlit-cb-date-cell"><span>' . esc_html(self::day(self::value($row, 'created_at'))) . '</span><span class="tlit-cb-cell-secondary">' . esc_html(self::day(self::value($row, 'created_at'), 'g:i a')) . '</span></td><td><a class="tlit-cb-open" href="' . esc_url($url) . '" aria-label="' . esc_attr('Open conversation with ' . $name) . '"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a></td></tr>';
    }

    private static function pagination($filters, $page, $per_page, $total) {
        $pages = max(1, (int) ceil($total / $per_page));
        $start = $total ? (($page - 1) * $per_page) + 1 : 0;
        $end = min($page * $per_page, $total);
        echo '<div class="tlit-cb-pagination"><p>Showing <strong>' . esc_html(number_format_i18n($start)) . '–' . esc_html(number_format_i18n($end)) . '</strong> of <strong>' . esc_html(number_format_i18n($total)) . '</strong></p><div class="tlit-cb-pagination-controls"><form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        self::hidden('page', self::SLUG);
        foreach ($filters as $key => $value) {
            self::hidden($key, $value);
        }
        echo '<label>Rows <select name="per_page" aria-label="Conversations per page">';
        foreach (array(25, 50, 100) as $size) {
            echo '<option value="' . esc_attr($size) . '"' . selected($per_page, $size, false) . '>' . esc_html($size) . '</option>';
        }
        echo '</select></label><button type="submit" class="button tlit-cb-button tlit-cb-button-small">Go</button></form><nav aria-label="Conversation pages">';
        $args = array_merge($filters, array('per_page' => $per_page));
        echo $page > 1 ? '<a class="button tlit-cb-page-button" href="' . esc_url(self::url(array_merge($args, array('paged' => $page - 1)))) . '" aria-label="Previous page">‹</a>' : '<span class="button tlit-cb-page-button" aria-disabled="true" aria-label="Previous page">‹</span>';
        echo '<span class="tlit-cb-page-position">Page ' . esc_html(number_format_i18n($page)) . ' of ' . esc_html(number_format_i18n($pages)) . '</span>';
        echo $page < $pages ? '<a class="button tlit-cb-page-button" href="' . esc_url(self::url(array_merge($args, array('paged' => $page + 1)))) . '" aria-label="Next page">›</a>' : '<span class="button tlit-cb-page-button" aria-disabled="true" aria-label="Next page">›</span>';
        echo '</nav></div></div>';
    }

    private static function detail($id, $filters) {
        $context = array_merge($filters, array('paged' => max(1, self::integer($_GET['paged'] ?? 1, 1)), 'per_page' => self::integer($_GET['per_page'] ?? 25, 25)));
        echo '<a href="' . esc_url(self::url($context)) . '" class="tlit-cb-back"><span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span> Back to conversations</a>';
        $row = TLIT_CB_Store::get($id);
        $retry_url = self::url(array_merge($context, array('conversation' => $id)));
        if (self::read_failed()) {
            echo '<div class="tlit-cb-empty tlit-cb-load-error" role="alert"><h2>Conversation could not be loaded</h2><p>The database did not return this record. Try again, or check the database connection if this continues.</p><a class="button tlit-cb-button" href="' . esc_url($retry_url) . '">Try again</a></div>';
            return;
        }
        if (!$row) {
            echo '<div class="tlit-cb-empty"><h2>Conversation not found</h2><p>This conversation may have been removed. Return to the inbox to view the available records.</p></div>';
            return;
        }
        if (isset($_GET['updated']) && $_GET['updated'] === '1') {
            echo '<div class="notice notice-success inline"><p>Conversation status updated.</p></div>';
        }
        $messages = TLIT_CB_Store::messages($id);
        $messages_error = self::read_failed();
        $messages = !$messages_error && is_array($messages) ? $messages : array();
        $name = self::value($row, 'name') ?: 'Visitor #' . $id;
        echo '<div class="tlit-cb-detail-heading"><div><p class="tlit-cb-eyebrow">CONVERSATION #' . esc_html($id) . '</p><h2>' . esc_html($name) . ' ' . self::badge(self::value($row, 'status', 'new')) . '</h2><p>Started ' . esc_html(self::day(self::value($row, 'created_at'), 'M j, Y · g:i a')) . ' · ' . esc_html(self::label(self::value($row, 'course'))) . '</p></div><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        self::export_fields('single');
        self::hidden('id', $id);
        echo '<button class="button button-primary tlit-cb-button" type="submit"><span class="dashicons dashicons-download" aria-hidden="true"></span> Download Word document</button></form></div>';
        echo '<div class="tlit-cb-detail-layout"><section class="tlit-cb-transcript" aria-labelledby="tlit-cb-transcript-heading"><div class="tlit-cb-panel-heading"><h3 id="tlit-cb-transcript-heading">Conversation transcript</h3><span>' . ($messages_error ? 'Temporarily unavailable' : esc_html(number_format_i18n(count($messages))) . ' messages') . '</span></div><div class="tlit-cb-transcript-body">';
        if ($messages_error) {
            echo '<div class="tlit-cb-empty tlit-cb-load-error" role="alert"><h3>Transcript could not be loaded</h3><p>The database did not return the messages. Try again to load the complete conversation.</p><a class="button tlit-cb-button" href="' . esc_url($retry_url) . '">Try again</a></div>';
        } elseif (!$messages) {
            echo '<div class="tlit-cb-empty"><h3>No messages captured yet</h3><p>This record has contact details but no saved transcript.</p></div>';
        }
        foreach ($messages as $message) {
            $role = self::value($message, 'role');
            $role = in_array($role, array('visitor', 'assistant', 'system'), true) ? $role : 'system';
            $speaker = array('visitor' => $name, 'assistant' => 'Course assistant', 'system' => 'Conversation event');
            $initial = array('visitor' => 'V', 'assistant' => 'AI', 'system' => '•');
            $type = self::value($message, 'type');
            echo '<article class="tlit-cb-message tlit-cb-message--' . esc_attr($role) . '"><span class="tlit-cb-message-avatar" aria-hidden="true">' . esc_html($initial[$role]) . '</span><div class="tlit-cb-message-main"><div class="tlit-cb-message-meta"><strong>' . esc_html($speaker[$role]) . '</strong><time>' . esc_html(self::day(self::value($message, 'created_at'), 'M j · g:i a')) . '</time></div><div class="tlit-cb-message-content">' . esc_html(self::value($message, 'content')) . '</div>';
            if ($type && !in_array($type, array('text', 'message'), true)) {
                echo '<span class="tlit-cb-message-type">' . esc_html(self::label($type)) . '</span>';
            }
            echo '</div></article>';
        }
        echo '</div></section><aside class="tlit-cb-sidebar" aria-label="Visitor details and actions"><section class="tlit-cb-side-panel"><h3>Visitor details</h3><dl class="tlit-cb-details">';
        foreach (array('name' => 'Name', 'phone' => 'Mobile number', 'email' => 'Email', 'course' => 'Course', 'language' => 'Language') as $key => $label) {
            $value = self::value($row, $key);
            if (in_array($key, array('course', 'language'), true)) {
                $value = self::label($value);
            }
            echo '<div><dt>' . esc_html($label) . '</dt><dd' . (!$value ? ' class="tlit-cb-muted"' : '') . '>' . esc_html($value ?: 'Not provided') . '</dd></div>';
        }
        self::goal_detail($row, $messages, $messages_error);
        echo '</dl></section><section class="tlit-cb-side-panel"><h3>Conversation progress</h3><dl class="tlit-cb-details tlit-cb-details-compact">';
        foreach (array('Stage' => self::label(self::value($row, 'stage') ?: 'Started'), 'Answers' => number_format_i18n((int) ($row['answer_count'] ?? 0)), 'Brochure' => !empty($row['has_brochure']) ? 'Requested' : 'Not requested', 'Last activity' => self::day(self::value($row, 'updated_at'), 'M j, Y · g:i a')) as $label => $value) {
            echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>';
        }
        $source = self::value($row, 'source_url');
        echo '</dl>';
        if ($source) {
            echo '<div class="tlit-cb-source"><span>Source page</span><a href="' . esc_url($source, array('http', 'https')) . '" target="_blank" rel="noopener noreferrer">' . esc_html($source) . '<span class="dashicons dashicons-external" aria-hidden="true"></span></a></div>';
        }
        echo '</section>';
        self::attribution($row);
        if (class_exists('TLIT_CB_Qualification')) { TLIT_CB_Qualification::panel($row); }
        echo '<section class="tlit-cb-side-panel"><h3>Review status</h3><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        self::hidden('action', 'tlit_cb_status');
        self::hidden('id', $id);
        self::hidden('paged', $context['paged']);
        self::hidden('per_page', $context['per_page']);
        self::hidden_filters($filters);
        wp_nonce_field('tlit_cb_status');
        self::select_field('status', 'Conversation status', self::statuses(), array('status' => self::value($row, 'status', 'new')), 'Choose status');
        echo '<button class="button tlit-cb-button tlit-cb-status-save" type="submit">Save review status</button><p class="tlit-cb-help">Review progress is separate from lead qualification above and does not change TeleCRM.</p></form></section>';
        do_action('tlit_cb_conversation_actions', $row);
        echo '</aside></div>';
    }

    private static function read_failed() {
        return method_exists('TLIT_CB_Store', 'read_error') && TLIT_CB_Store::read_error();
    }

    /** Only the public campaign/analytics fields captured by the store belong here. */
    private static function attribution($row) {
        $tracking = json_decode(self::value($row, 'tracking_json'), true);
        $tracking = is_array($tracking) ? $tracking : array();
        $labels = array(
            'utm_source' => 'UTM source', 'utm_medium' => 'UTM medium', 'utm_campaign' => 'UTM campaign',
            'utm_adgroup' => 'UTM ad group', 'utm_term' => 'UTM term', 'utm_content' => 'UTM content',
            'gclid' => 'Google click ID', 'gbraid' => 'Google GBRAID', 'wbraid' => 'Google WBRAID',
            'fbclid' => 'Facebook click ID', 'fbp' => 'Meta browser ID', 'fbc' => 'Meta click cookie',
            'ga_client_id' => 'Analytics client ID', 'session_id' => 'Analytics session ID',
            'landing_page' => 'Landing page', 'referrer' => 'Referrer', 'fb_campaign' => 'Facebook campaign',
            'fb_ad' => 'Facebook ad', 'fb_adset_name' => 'Facebook ad set', 'fb_adset_id' => 'Facebook ad set ID',
            'fb_lead_id' => 'Facebook lead ID', 'city' => 'City', 'state' => 'State',
        );
        $values = array();
        foreach ($labels as $key => $label) {
            if (isset($tracking[$key]) && is_scalar($tracking[$key]) && trim((string) $tracking[$key]) !== '') {
                $values[$label] = (string) $tracking[$key];
            }
        }
        echo '<details class="tlit-cb-side-panel tlit-cb-attribution"><summary>Attribution details <span class="tlit-cb-count">' . esc_html(count($values)) . '</span></summary>';
        if ($values) {
            echo '<p class="tlit-cb-attribution-intro">Captured campaign and source information.</p><dl class="tlit-cb-details">';
            foreach ($values as $label => $value) {
                echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>';
            }
            echo '</dl>';
        } else {
            echo '<p class="tlit-cb-attribution-intro">No campaign or attribution details were captured for this conversation.</p>';
        }
        echo '</details>';
    }

    public static function save_status() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to update conversations.', 'tlit-conversation-bridge'), '', array('response' => 403));
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_die(esc_html__('Use the conversation form to update its status.', 'tlit-conversation-bridge'), '', array('response' => 405));
        }
        check_admin_referer('tlit_cb_status');
        $post = wp_unslash($_POST);
        $id = self::integer($post['id'] ?? 0);
        $status = isset($post['status']) && is_string($post['status']) ? $post['status'] : '';
        if (!$id || !isset(self::statuses()[$status]) || !TLIT_CB_Store::get($id)) {
            wp_die(esc_html__('Choose an existing conversation and a valid status.', 'tlit-conversation-bridge'), '', array('response' => 400));
        }
        $result = TLIT_CB_Store::update_status($id, $status);
        if ($result === false || is_wp_error($result)) {
            wp_die(esc_html__('The status could not be saved. Return to the conversation and try again.', 'tlit-conversation-bridge'), '', array('response' => 500));
        }
        $args = array_merge(self::filters($post['filters'] ?? array()), array('conversation' => $id, 'updated' => '1', 'paged' => max(1, self::integer($post['paged'] ?? 1, 1)), 'per_page' => self::integer($post['per_page'] ?? 25, 25)));
        wp_safe_redirect(self::url($args));
        exit;
    }
}
