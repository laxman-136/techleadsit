<?php
/**
 * Plugin Name: TechLeadsIT Landing Pages
 * Description: Serves high-performance custom HTML landing pages at clean URLs and handles secure lead routing to TeleCRM.
 * Version: 1.0.0
 * Author: TechLeadsIT
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// 1. DYNAMIC ROUTING: Intercept clean URLs and serve HTML landing pages
add_action('template_redirect', 'techleadsit_route_landing_pages');

function techleadsit_route_landing_pages() {
    $request_uri = $_SERVER['REQUEST_URI'];
    
    // Define your landing pages and their corresponding HTML files here
    // Slug key => HTML filename
    $landing_pages = array(
        'scm-demo' => 'scm-demo/index.html',
        'scm-demo-v2' => 'scm-demo-v2/index.html',
        // You can add more pages here in the future! E.g. 'scm-offer' => 'scm-offer/index.html'
    );

    foreach ($landing_pages as $slug => $file) {
        // Match the request URL (e.g. /scm-demo or /scm-demo/)
        if (preg_match('#^/' . preg_quote($slug, '#') . '/?(\?.*)?$#', $request_uri)) {
            $html_filepath = plugin_dir_path(__FILE__) . $file;
            
            if (file_exists($html_filepath)) {
                $html_content = file_get_contents($html_filepath);
                
                // Get folder directory relative to the plugin (e.g., scm-demo/)
                $folder = dirname($file);
                $folder_path = ($folder !== '.' && $folder !== '/') ? $folder . '/' : '';
                
                // Dynamically rewrite relative paths for CSS and JS to point to the correct subfolder
                $plugin_url = plugin_dir_url(__FILE__) . $folder_path;
                $html_content = str_replace('href="index.css"', 'href="' . $plugin_url . 'index.css"', $html_content);
                $html_content = str_replace('src="index.js"', 'src="' . $plugin_url . 'index.js"', $html_content);
                
                // Output headers and HTML content
                header('Content-Type: text/html; charset=utf-8');
                echo $html_content;
                exit;
            }
        }
    }
}

// 2. SECURE API: Register WP REST API endpoint for secure lead routing to TeleCRM
add_action('rest_api_init', function () {
    register_rest_route('techleadsit/v1', '/submit-lead', array(
        'methods' => 'POST',
        'callback' => 'techleadsit_handle_crm_lead',
        'permission_callback' => '__return_true'
    ));
});

function techleadsit_handle_crm_lead(WP_REST_Request $request) {
    $params = $request->get_json_params();

    // Validate name and 10-digit phone number
    $name = sanitize_text_field($params['name'] ?? '');
    $phone = sanitize_text_field($params['phone'] ?? '');
    $email = sanitize_email($params['email'] ?? '');
    $role = sanitize_text_field($params['role'] ?? '');
    $salary = sanitize_text_field($params['salary'] ?? '');
    $experience = sanitize_text_field($params['experience'] ?? '');
    $utm_source = sanitize_text_field($params['utm_source'] ?? 'Direct');
    $utm_campaign = sanitize_text_field($params['utm_campaign'] ?? '');

    if (empty($name) || !preg_match('/^[0-9]{10}$/', $phone)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Invalid validation requirements.'), 400);
    }

    // -------------------------------------------------------------
    // TELECRM API INTEGRATION CONFIGURATION
    // -------------------------------------------------------------
    // Replace 'YOUR_TELECRM_API_KEY' with your actual TeleCRM API token.
    // Replace 'YOUR_TELECRM_PIPELINE_URL' with your TeleCRM API URL.
    $telecrm_api_url = 'https://api.telecrm.in/api/v1/leads/create'; 
    $api_key = 'YOUR_TELECRM_API_KEY'; 

    // Build the payload matching TeleCRM API specification
    $payload = array(
        'fields' => array(
            'name' => $name,
            'phone' => '+91' . $phone,
            'email' => $email,
            'role' => $role,
            'salary' => $salary,
            'experience' => $experience,
            'source' => $utm_source,
            'campaign' => $utm_campaign
        )
    );

    // Call TeleCRM API securely via WordPress HTTP API
    $response = wp_remote_post($telecrm_api_url, array(
        'headers'     => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'body'        => json_encode($payload),
        'method'      => 'POST',
        'data_format' => 'body',
        'timeout'     => 15
    ));

    if (is_wp_error($response)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'CRM submission error.'), 500);
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code >= 400) {
        return new WP_REST_Response(array('success' => false, 'message' => 'CRM rejected request.'), $response_code);
    }

    return new WP_REST_Response(array('success' => true, 'message' => 'Lead successfully saved and routed.'), 200);
}
