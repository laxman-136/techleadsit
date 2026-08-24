<?php
define('ABSPATH', true);
function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {}
function register_rest_route($namespace, $route, $args = array(), $override = false) {}
function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {}
function plugin_dir_url($file) { return 'http://example.com/'; }
function plugin_dir_path($file) { return '/tmp/'; }

include 'techleadsit.php';
echo "SUCCESS: Included techleadsit.php without runtime errors!\n";
