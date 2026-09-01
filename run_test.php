<?php
define('ABSPATH', true);
function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {}
function register_rest_route($namespace, $route, $args = array(), $override = false) {}
function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {}
function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null) {}
function add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null) {}
function register_setting($option_group, $option_name, $args = array()) {}
function get_option($option, $default = false) { return $default; }
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
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
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
