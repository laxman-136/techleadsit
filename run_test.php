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
function plugin_dir_url($file) { return 'http://example.com/'; }
function plugin_dir_path($file) { return __DIR__ . '/'; }
function sanitize_text_field($str) { return trim($str); }
function sanitize_key($key) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $key)); }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_url($url) { return $url; }
function esc_js($js) { return addslashes($js); }
function home_url($path = '') { return 'https://techleadsit.com' . $path; }
function admin_url($path = '') { return 'https://techleadsit.com/wp-admin/' . $path; }
function add_query_arg($args, $url, $target = null) { if (!is_array($args)) { $args = array($args=>$url); $url=$target; } return $url . (strpos($url,'?')===false?'?':'&') . http_build_query($args); }
function current_user_can($capability) { return true; }
function checked($checked, $current = true, $echo = true) { return $checked == $current ? 'checked="checked"' : ''; }
function submit_button($text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null) {}
function settings_fields($option_group) {}

include 'techleadsit.php';

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
));
$invalid = techleadsit_sanitize_course_settings(array('course_id' => 'scm', 'scm_demo_datetime' => '2027-02-30T24:00'));
if ($invalid['scm_demo_datetime'] !== '2027-01-21T19:15' || $invalid['scm_batch_datetime'] !== '2027-01-25T20:30' ||
    !isset($test_settings_errors[$scm_option]['scm_demo_datetime'])) {
    throw new RuntimeException('Invalid or missing dates should preserve the saved SCM schedule.');
}
if (isset(techleadsit_sanitize_course_settings(array('course_id' => 'hcm'))['scm_demo_datetime'])) {
    throw new RuntimeException('SCM event settings should only be stored for the SCM course.');
}

$landing = techleadsit_render_scm_schedule(file_get_contents(__DIR__.'/oracle-fusion-scm-live-demo/index.html'));
preg_match('~<script type="application/ld\+json" id="scm-event-schema">(.*?)</script>~s', $landing, $schema);
$event = json_decode($schema[1] ?? '', true);
if (substr_count($landing, 'datetime="2027-01-21T19:15:00+05:30"') !== 4 ||
    !str_contains($landing, 'datetime="2027-01-25T20:30:00+05:30"') ||
    !str_contains($landing, 'Thursday, 21 January 2027 · 7:15 PM IST') ||
    ($event['startDate'] ?? '') !== '2027-01-21T19:15:00+05:30' || str_contains($landing, '2026-12-12')) {
    throw new RuntimeException('The saved dates should render in the page and event metadata.');
}

$_GET = array('tab' => 'scm');
ob_start();
techleadsit_render_multi_course_settings_page();
$settings_page = ob_get_clean();
if (!str_contains($settings_page, 'name="techleadsit_course_settings_scm[scm_demo_datetime]" value="2027-01-21T19:15"') ||
    !str_contains($settings_page, 'name="techleadsit_course_settings_scm[scm_batch_datetime]" value="2027-01-25T20:30"')) {
    throw new RuntimeException('Landing Page Settings should display both saved values.');
}
echo "SUCCESS: Verified SCM date settings, validation, IST formatting and event metadata.\n";
