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
    register_rest_route('techleadsit/v1', '/send-otp', array(
        'methods' => 'POST',
        'callback' => 'techleadsit_handle_send_otp',
        'permission_callback' => '__return_true'
    ));
    register_rest_route('techleadsit/v1', '/verify-otp', array(
        'methods' => 'POST',
        'callback' => 'techleadsit_handle_verify_otp',
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

    // Verify OTP first if email is provided
    if (!empty($email)) {
        $is_verified = get_transient('techleads_verified_' . md5($email));
        if ($is_verified === false || $is_verified !== '1') {
            return new WP_REST_Response(array('success' => false, 'message' => 'Please verify your email address first using OTP.'), 400);
        }
    }

    // Capture the 16 tracking fields
    $fbp = sanitize_text_field($params['fbp'] ?? '');
    $fbc = sanitize_text_field($params['fbc'] ?? '');
    $gclid = sanitize_text_field($params['gclid'] ?? '');
    $gbraid = sanitize_text_field($params['gbraid'] ?? '');
    $wbraid = sanitize_text_field($params['wbraid'] ?? '');
    $fbclid = sanitize_text_field($params['fbclid'] ?? '');
    $ga_client_id = sanitize_text_field($params['ga_client_id'] ?? '');
    $session_id = sanitize_text_field($params['session_id'] ?? '');
    $utm_source = sanitize_text_field($params['utm_source'] ?? 'Direct');
    $utm_medium = sanitize_text_field($params['utm_medium'] ?? '');
    $utm_campaign = sanitize_text_field($params['utm_campaign'] ?? '');
    $utm_adgroup = sanitize_text_field($params['utm_adgroup'] ?? '');
    $utm_term = sanitize_text_field($params['utm_term'] ?? '');
    $utm_content = sanitize_text_field($params['utm_content'] ?? '');
    $landing_page = esc_url_raw($params['landing_page'] ?? '');
    $referrer = sanitize_text_field($params['referrer'] ?? '');

    if (empty($name) || !preg_match('/^[0-9]{10}$/', $phone)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Invalid validation requirements.'), 400);
    }

    if (!empty($params['email']) && !is_email($email)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Please enter a valid email address.'), 400);
    }

    // -------------------------------------------------------------
    // TELECRM API INTEGRATION CONFIGURATION
    // -------------------------------------------------------------
    // For security on public repos, define 'TELECRM_API_KEY' in your server's wp-config.php:
    // define('TELECRM_API_KEY', 'your-actual-api-key-here');
    $api_key = defined('TELECRM_API_KEY') ? TELECRM_API_KEY : ''; 
    $telecrm_api_url = 'https://app.telecrm.in/api/b1/enterprise/' . $api_key . '/autoupdatelead'; 

    // Build the payload matching TeleCRM API specification
    $payload = array(
        'fields' => array(
            'name' => $name,
            'phone' => '+91' . $phone,
            'email' => $email,
            'role' => $role,
            'salary' => $salary,
            'experience' => $experience,
            'source' => $utm_source,      // Maps to TeleCRM default source field
            'campaign' => $utm_campaign,  // Maps to TeleCRM default campaign field
            'fbp' => $fbp,
            'fbc' => $fbc,
            'gclid' => $gclid,
            'gbraid' => $gbraid,
            'wbraid' => $wbraid,
            'fbclid' => $fbclid,
            'ga_client_id' => $ga_client_id,
            'session_id' => $session_id,
            'utm_source' => $utm_source,
            'utm_medium' => $utm_medium,
            'utm_campaign' => $utm_campaign,
            'utm_adgroup' => $utm_adgroup,
            'utm_term' => $utm_term,
            'utm_content' => $utm_content,
            'landing_page' => $landing_page,
            'referrer' => $referrer,
            // Custom fields without underscores (matching your TeleCRM account fields)
            'utmsource' => $utm_source,
            'utmmedium' => $utm_medium,
            'utmcampaign' => $utm_campaign,
            'utmadgroup' => $utm_adgroup,
            'utmterm' => $utm_term,
            'utmcontent' => $utm_content,
            'landingpage' => $landing_page
        ),
        'actions' => array(
            array(
                'type' => 'SYSTEM_NOTE',
                'text' => "Marketing Tracking Details:\n" .
                          "- Source: " . $utm_source . "\n" .
                          "- Medium: " . $utm_medium . "\n" .
                          "- Campaign: " . $utm_campaign . "\n" .
                          "- Adgroup: " . $utm_adgroup . "\n" .
                          "- Term: " . $utm_term . "\n" .
                          "- Content: " . $utm_content . "\n" .
                          "- GCLID: " . $gclid . "\n" .
                          "- FBCLID: " . $fbclid . "\n" .
                          "- FBC: " . $fbc . "\n" .
                          "- FBP: " . $fbp . "\n" .
                          "- GA Client ID: " . $ga_client_id . "\n" .
                          "- Session ID: " . $session_id . "\n" .
                          "- Landing Page: " . $landing_page . "\n" .
                          "- Referrer: " . $referrer
            )
        )
    );

    // Call TeleCRM API securely via WordPress HTTP API
    $response = wp_remote_post($telecrm_api_url, array(
        'headers'     => array(
            'Content-Type' => 'application/json'
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
        $body = wp_remote_retrieve_body($response);
        return new WP_REST_Response(array('success' => false, 'message' => 'CRM rejected request: ' . $body), $response_code);
    }

    // Success! Consume the verification transient so it cannot be reused for multiple submissions
    if (!empty($email)) {
        delete_transient('techleads_verified_' . md5($email));
    }

    return new WP_REST_Response(array('success' => true, 'message' => 'Lead successfully saved and routed.'), 200);
}

function techleadsit_handle_send_otp(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $email = sanitize_email($params['email'] ?? '');

    if (empty($email) || !is_email($email)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Please provide a valid email address.'), 400);
    }

    $otp = strval(rand(100000, 999999));
    set_transient('techleads_otp_' . md5($email), $otp, 300); // 5 min expiry

    $subject = "Your Verification Code - TechLeadsIT";
    $message = "Hello,\n\nYour 6-digit verification code is: " . $otp . "\n\nThis code will expire in 5 minutes.\n\nBest regards,\nTechLeadsIT";
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: TechLeadsIT <support@lp.techleadsit.com>'
    );
    
    $sent = wp_mail($email, $subject, $message, $headers);

    if (!$sent) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Failed to send verification email. Please check your email host config.'), 500);
    }

    return new WP_REST_Response(array('success' => true, 'message' => 'Verification code sent to your email.'), 200);
}

function techleadsit_handle_verify_otp(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $email = sanitize_email($params['email'] ?? '');
    $otp = sanitize_text_field($params['otp'] ?? '');

    if (empty($email) || empty($otp)) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Email and code are required.'), 400);
    }

    $stored_otp = get_transient('techleads_otp_' . md5($email));

    if ($stored_otp === false || $stored_otp !== $otp) {
        return new WP_REST_Response(array('success' => false, 'message' => 'Invalid or expired verification code.'), 400);
    }

    // Mark as verified for 10 minutes
    set_transient('techleads_verified_' . md5($email), '1', 600);
    // Delete the OTP transient so it cannot be reused
    delete_transient('techleads_otp_' . md5($email));

    return new WP_REST_Response(array('success' => true, 'message' => 'Email verified successfully.'), 200);
}

