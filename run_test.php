<?php
define('ABSPATH', true);
function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {}
function register_rest_route($namespace, $route, $args = array(), $override = false) {}
function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {}
function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null) {}
function add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null) {}
function register_setting($option_group, $option_name, $args = array()) {}
function get_option($option, $default = false) { return $GLOBALS['test_options'][$option] ?? $default; }
function wp_json_encode($value, $options = 0) { return json_encode($value, $options); }
function add_settings_error($setting, $code, $message) { $GLOBALS['test_settings_errors'][$setting][$code] = $message; }
function get_settings_errors($setting) { return $GLOBALS['test_settings_errors'][$setting] ?? array(); }
function settings_errors($setting) {}
function wp_parse_args($args, $defaults = array()) { return array_merge($defaults, is_array($args) ? $args : array()); }
function plugin_dir_url($file) { return getenv('TLIT_TEST_PLUGIN_URL') ?: 'http://example.com/'; }
function plugin_dir_path($file) { return __DIR__ . '/'; }
function sanitize_text_field($str) { return trim(strip_tags($str)); }
function sanitize_key($key) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $key)); }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_url($url) { return htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function rest_url($path) { return home_url('/wp-json/'.$path); }
function nocache_headers() {}
function status_header($status) { $GLOBALS['test_status'] = $status; }
function esc_js($js) { return addslashes($js); }
function home_url($path = '') { return (getenv('TLIT_TEST_SITE_URL') ?: 'https://techleadsit.com') . $path; }
function admin_url($path = '') { return 'https://techleadsit.com/wp-admin/' . $path; }
function add_query_arg($args, $url, $target = null) { if (!is_array($args)) { $args = array($args=>$url); $url=$target; } return $url . (strpos($url,'?')===false?'?':'&') . http_build_query($args); }
function current_user_can($capability) { return true; }
function checked($checked, $current = true, $echo = true) { return $checked == $current ? 'checked="checked"' : ''; }
function submit_button($text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null) {}
function settings_fields($option_group) {}

include 'techleadsit.php';

// Render real routes with deterministic saved settings for local integration checks.
if (($argv[1] ?? '') === '--route') {
    $test_options['techleadsit_course_settings_scm'] = array(
        'scm_demo_datetime' => '2027-01-21T19:15',
        'scm_batch_datetime' => '2027-01-25T20:30',
        'scm_demo_platform' => 'Live on Google Meet',
        'scm_demo_join_url' => 'https://meet.google.com/abc-defg-hij',
    );
    $_SERVER['REQUEST_URI'] = $argv[2] ?? '/oracle-fusion-scm-course-it';
    $_SERVER['REQUEST_METHOD'] = $argv[3] ?? 'GET';
    techleadsit_route_digital_marketing_early();
    throw new RuntimeException('Expected the SCM route to be served before canonical redirects.');
}

// Validate Course Registry
if (function_exists('techleadsit_get_courses_registry')) {
    $courses = techleadsit_get_courses_registry();
    if (!is_array($courses) || empty($courses)) {
        echo "ERROR: Course registry is empty or invalid!\n";
        exit(1);
    }
    echo "SUCCESS: Validated " . count($courses) . " course categories in registry.\n";
    foreach ($courses as $cid => $cdata) {
        if (empty($cdata['slugs'])) {
            echo "WARNING: Course '$cid' has no mapped slugs.\n";
        } else {
            echo " - Course [$cid] {$cdata['name']}: " . count($cdata['slugs']) . " mapped slugs.\n";
        }
    }
}

echo "SUCCESS: Included techleadsit.php and validated multi-course registry without runtime errors!\n";

$scm_option = 'techleadsit_course_settings_scm';
$schedule = techleadsit_get_scm_schedule();
if ($schedule['scm_demo_datetime']->format(DATE_ATOM) !== '2026-12-12T08:00:00+05:30' ||
    $schedule['scm_batch_datetime']->format(DATE_ATOM) !== '2026-12-14T07:00:00+05:30') {
    throw new RuntimeException('Expected the existing SCM schedule on a fresh install.');
}

$test_options[$scm_option] = techleadsit_sanitize_course_settings(array(
    'course_id' => 'scm',
    'scm_demo_datetime' => '2027-01-21T19:15',
    'scm_batch_datetime' => '2027-01-25T20:30',
    'scm_demo_platform' => 'Live on Google Meet',
    'scm_demo_join_url' => 'https://meet.google.com/abc-defg-hij',
));
$invalid = techleadsit_sanitize_course_settings(array('course_id' => 'scm', 'scm_demo_datetime' => '2027-02-30T24:00', 'scm_demo_join_url' => 'javascript:alert(1)'));
if ($invalid['scm_demo_datetime'] !== '2027-01-21T19:15' || $invalid['scm_batch_datetime'] !== '2027-01-25T20:30' ||
    !isset($test_settings_errors[$scm_option]['scm_demo_datetime'])) {
    throw new RuntimeException('Invalid or missing dates should preserve the saved SCM schedule.');
}
if ($invalid['scm_demo_join_url'] !== 'https://meet.google.com/abc-defg-hij' ||
    $invalid['scm_demo_platform'] !== 'Live on Google Meet' ||
    !isset($test_settings_errors[$scm_option]['scm_demo_join_url'])) {
    throw new RuntimeException('Invalid or missing demo details should preserve saved values.');
}
if (isset(techleadsit_sanitize_course_settings(array('course_id' => 'hcm'))['scm_demo_datetime'])) {
    throw new RuntimeException('SCM landing page settings should only be stored for the SCM course.');
}

$landing = techleadsit_render_scm_schedule(file_get_contents(__DIR__.'/oracle-fusion-scm-course-it/index.html'));
preg_match('~<script type="application/ld\+json" id="scm-course-schema">(.*?)</script>~s', $landing, $schema);
$course_schema = json_decode($schema[1] ?? '', true);
if (substr_count($landing, 'datetime="2027-01-21T19:15:00+05:30"') !== 4 ||
    !str_contains($landing, 'datetime="2027-01-25T20:30:00+05:30"') ||
    !str_contains($landing, 'Thursday, 21 January 2027 · 7:15 PM IST') ||
    ($course_schema['@type'] ?? '') !== 'Course' || str_contains($landing, '2026-12-12') ||
    substr_count($landing, 'Live on Google Meet') !== 2 ||
    !str_contains($landing, 'data-result-intent="demo" href="https://meet.google.com/abc-defg-hij"') ||
    str_contains($landing, 'scm-batch-80')) {
    throw new RuntimeException('The course page should use its own metadata and saved demo details.');
}
if (($courses['scm']['slugs']['oracle-fusion-scm-course-it'] ?? '') !== 'oracle-fusion-scm-course-it/index.html' ||
    isset($courses['scm']['slugs']['scm-batch-80'])) {
    throw new RuntimeException('Only the course page should appear in connected SCM landing pages.');
}
if (TLIT_LP_Leads::variant('', home_url('/oracle-fusion-scm-course-it/')) !== 'oracle_scm') {
    throw new RuntimeException('The new URL must retain SCM lead attribution.');
}
$event = file_get_contents(__DIR__.'/oracle-fusion-scm-live-demo/index.html');
if (!str_contains($event, 'https://lp.techleadsit.com/scm-batch-80') ||
    !str_contains($event, '"@type": "Event"') || !str_contains($event, '2026-12-12T08:00:00+05:30') ||
    str_contains($event, 'data-scm-time') || str_contains($event, 'hero-demo-form')) {
    throw new RuntimeException('The event template must remain separate from the configurable course page.');
}

$_GET = array('tab' => 'scm');
ob_start();
techleadsit_render_multi_course_settings_page();
$settings_page = ob_get_clean();
if (!str_contains($settings_page, 'name="techleadsit_course_settings_scm[scm_demo_datetime]" value="2027-01-21T19:15"') ||
    !str_contains($settings_page, 'name="techleadsit_course_settings_scm[scm_batch_datetime]" value="2027-01-25T20:30"') ||
    !str_contains($settings_page, 'name="techleadsit_course_settings_scm[scm_demo_platform]" value="Live on Google Meet"') ||
    !str_contains($settings_page, 'name="techleadsit_course_settings_scm[scm_demo_join_url]" value="https://meet.google.com/abc-defg-hij"')) {
    throw new RuntimeException('Landing Page Settings should display all saved demo details.');
}
echo "SUCCESS: Verified separate SCM pages, lead attribution, editable demo details, validation and IST formatting.\n";

// Offline payload dry-run required by .agents/rules.md. Never send synthetic leads.
$test_options[TLIT_LP_Leads::OPTION] = array('mapping' => array());
foreach (array('google' => 'Google Ads', 'facebook' => 'Facebook Ads') as $channel => $source) {
    $tracking = array(
        'utm_source' => $channel, 'utm_medium' => 'paid', 'utm_campaign' => 'synthetic-scm',
        'utm_adgroup' => 'synthetic-group', 'utm_term' => 'scm course', 'utm_content' => 'synthetic-ad',
        'gclid' => $channel === 'google' ? 'synthetic-click' : '',
        'gbraid' => $channel === 'google' ? 'synthetic-braid' : '',
        'wbraid' => $channel === 'google' ? 'synthetic-web-braid' : '',
        'fbclid' => $channel === 'facebook' ? 'synthetic-fb-click' : '',
        'fbp' => 'synthetic-fbp', 'fbc' => 'synthetic-fbc', 'ga_client_id' => '100.200',
        'session_id' => 'synthetic-session', 'landing_page' => home_url('/oracle-fusion-scm-course-it'),
        'referrer' => 'https://example.invalid/ad', 'city' => 'Hyderabad', 'state' => 'Telangana',
        'fb_campaign' => 'synthetic-campaign', 'fb_ad' => 'synthetic-ad',
        'fb_adset_name' => 'synthetic-set', 'fb_adset_id' => '123456', 'fb_lead_id' => '654321',
    );
    $row = (object) array(
        'id' => 1, 'name' => 'Synthetic SCM QA', 'phone' => '+919123456789', 'email' => 'scm-qa@example.invalid',
        'source' => 'general', 'page_variant' => 'oracle_scm', 'submission_url' => $tracking['landing_page'],
        'lead_status' => 'new', 'otp_verified' => 0, 'advisor_note' => '', 'created_at' => '2026-11-24 04:30:00',
        'payload' => wp_json_encode(array('tracking' => $tracking, 'goal' => 'Start an SCM career',
            'details' => array('student_status' => 'Working Professional', 'preferred_call_time' => 'afternoon'))),
    );
    $fields = TLIT_LP_Leads::crm_payload($row)['fields'];
    foreach (array('name','phone','email','Course Name','Your preferred time to call','City Name','State Name','Mode of Training','Exp Level','Remarks','gaclientid','sessionid','landingpage','referrer','utmsource','utmmedium','utmcampaign','utmadgroup','utmterm','utmcontent','Facebook Campaign','Facebook Ad','Facebook Ad set Name','Facebook Ad set ID','Facebook Lead ID') as $key) {
        if (empty($fields[$key])) throw new RuntimeException('Missing SCM CRM field: '.$key);
    }
    if ($fields['Course Name'] !== 'Oracle Fusion SCM' || $fields['Lead Source'] !== $source ||
        $fields['phone'] !== '+919123456789' || $fields['landingpage'] !== home_url('/oracle-fusion-scm-course-it')) {
        throw new RuntimeException('SCM CRM identity or source attribution changed.');
    }
    foreach (array('Lead date','lead_date','leaddate','date') as $key) {
        if ($fields[$key] !== '2026-11-24 10:00:00') throw new RuntimeException('SCM capture date must remain IST.');
    }
    foreach (array_keys($fields) as $key) {
        if (preg_match('/enrol|enroll|payment|paid|receipt|fee/i', $key)) throw new RuntimeException('Counsellor-only field populated.');
    }
}
echo "SUCCESS: Offline SCM Google/Facebook payload dry-runs preserved CRM fields, attribution and counsellor safeguards.\n";
