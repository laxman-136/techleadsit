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
        'rise-v1' => 'rise-v1/index.html',
        'rise-v2' => 'rise-v2/index.html',
        'oracle-fusion-scm-training' => 'oracle-fusion-scm-training/index.html',
        'rise-form-16465496' => 'rise-form-16465496/index.html',
        'free-sql-training-44819' => 'free-sql-training-44819/index.html',
        'oracle-fusion-hcm-training' => 'f-hcm-course/index.html',
        'f-hcm-course' => 'f-hcm-course/index.html',
        'mb-hr-to' => 'f-hcm-course/index.html',
        'oracle-fusion-hcm-training-in-hyderabad' => 'f-hcm-course/index.html',
        'oracle-fusion-hcm-training-in-bangalore' => 'f-hcm-course/index.html',
        'oracle-fusion-hcm-training-in-delhi' => 'f-hcm-course/index.html',
        'oracle-fusion-hcm-online-training' => 'f-hcm-course/index.html',
        'oracle-fusion-hcm-course' => 'f-hcm-course/index.html',
        'oracle-fusion-hcm-functional-training' => 'f-hcm-course/index.html',
        'oracle-fusion-hcm-modules-training' => 'f-hcm-course/index.html',
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
                $html_content = str_replace('href="styles.css"', 'href="' . $plugin_url . 'styles.css?v=9.2"', $html_content);
                $html_content = str_replace('src="script.js"', 'src="' . $plugin_url . 'script.js?v=9.2"', $html_content);
                $html_content = str_replace('src="logo-dark.png"', 'src="' . $plugin_url . 'logo-dark.png"', $html_content);
                $html_content = str_replace('src="logo-light.png"', 'src="' . $plugin_url . 'logo-light.png"', $html_content);
                $html_content = str_replace('src="images/', 'src="' . $plugin_url . 'images/', $html_content);
                
                // Dynamically inject GTM Container code if GTM4WP is active and configured
                $gtm4wp_options = get_option('gtm4wp-options');
                if (is_array($gtm4wp_options) && !empty($gtm4wp_options['gtm-code'])) {
                    $gtm_code = sanitize_text_field($gtm4wp_options['gtm-code']);
                    
                    // Head Script
                    $gtm_head = "\n<!-- Google Tag Manager (Injected by TechLeadsIT Plugin via GTM4WP) -->\n" .
                                "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n" .
                                "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n" .
                                "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n" .
                                "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n" .
                                "})(window,document,'script','dataLayer','" . $gtm_code . "');</script>\n" .
                                "<!-- End Google Tag Manager -->\n";
                    
                    // Body Script (noscript)
                    $gtm_body = "\n<!-- Google Tag Manager (noscript) (Injected by TechLeadsIT Plugin via GTM4WP) -->\n" .
                                '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $gtm_code . '"' . "\n" .
                                'height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n" .
                                "<!-- End Google Tag Manager (noscript) -->\n";

                    // Insert head script right after <head>
                    $html_content = str_replace('<head>', '<head>' . $gtm_head, $html_content);
                    // Insert body script right after <body>
                    $html_content = str_replace('<body>', '<body>' . $gtm_body, $html_content);
                }

                // Dynamically inject the active slug variable to window.pageSlug context
                $slug_inject = "\n<script>window.pageSlug = '" . esc_js($slug) . "';</script>\n";
                $html_content = str_replace('<head>', '<head>' . $slug_inject, $html_content);

                // Dynamically inject the self-referential canonical URL
                $canonical_url = home_url('/' . $slug);
                $canonical_tag = "\n<link rel=\"canonical\" href=\"" . esc_url($canonical_url) . "\" />\n";
                $html_content = str_replace('<head>', '<head>' . $canonical_tag, $html_content);

                // Apply slug-specific dynamic text replacements for Google Ads Ad Groups
                $slug_replacements = array(
                    'oracle-fusion-hcm-training-in-hyderabad' => array(
                        'title' => 'Oracle Fusion HCM Training in Hyderabad | Certification + Placement Support',
                        'description' => 'Join Oracle Fusion HCM Training in Hyderabad with Core HR, Payroll, Absence, Talent, Compensation, real-time projects, certification guidance, and placement support.',
                        'eyebrow' => 'ORACLE FUSION HCM TRAINING IN HYDERABAD | LIVE ONLINE · CERTIFICATION · PLACEMENT SUPPORT',
                        'h1' => '<span class="text-gradient">Oracle Fusion HCM</span> Training in Hyderabad with Real-Time Projects',
                        'hero_sub' => 'Learn Oracle Fusion HCM from expert trainers and master Core HR, Payroll, Absence, Talent, Compensation, and OTL through live classes, real-time implementation practice, mock interviews, and placement support for Hyderabad learners.',
                        'primary_cta' => 'Book Free Demo Class',
                        'wa_cta' => 'Get HCM Training Details on WhatsApp →',
                        'form_heading' => 'Get Your Free Oracle HCM Demo Class',
                        'audience_heading' => 'Who Should Join Oracle Fusion HCM Training in Hyderabad?',
                        'curriculum_heading' => 'Oracle Fusion HCM Training Curriculum for Hyderabad Learners',
                        'key_features_heading' => 'Oracle Fusion HCM Training in Hyderabad - Key Features',
                        'modules_heading' => 'Oracle Fusion HCM Modules You Will Master',
                        'details_heading' => 'Oracle Fusion HCM Training Details, Duration & Fee',
                        'faq_heading' => 'Oracle Fusion HCM Training in Hyderabad FAQs',
                        'final_cta_heading' => 'Ready to Start Oracle Fusion HCM Training in Hyderabad?',
                        'final_cta_sub' => 'Join the next Oracle Fusion HCM demo class and understand the course, trainer, modules, fee, and placement support before enrolling.'
                    ),
                    'oracle-fusion-hcm-training-in-bangalore' => array(
                        'title' => 'Oracle Fusion HCM Training in Bangalore | Online Classes + Placement Support',
                        'description' => 'Learn Oracle Fusion HCM in Bangalore with live online classes, Core HR, Payroll, Absence, Talent, Compensation, real-time projects, certification guidance, and placement support.',
                        'eyebrow' => 'ORACLE FUSION HCM TRAINING IN BANGALORE | LIVE ONLINE · PROJECTS · JOB SUPPORT',
                        'h1' => '<span class="text-gradient">Oracle Fusion HCM</span> Training in Bangalore for HR & ERP Careers',
                        'hero_sub' => 'Build job-ready Oracle Fusion HCM skills with live online training for Bangalore learners. Learn Core HR, Payroll, Absence, Talent, Compensation, and implementation workflows with practical projects and interview preparation.',
                        'primary_cta' => 'Book Free Demo Class',
                        'wa_cta' => 'Get Bangalore Batch Details on WhatsApp →',
                        'form_heading' => 'Get Your Free HCM Demo Class',
                        'audience_heading' => 'Looking for Oracle Fusion HCM Training in Bangalore?',
                        'curriculum_heading' => 'Oracle Fusion HCM Course Curriculum for Bangalore Learners',
                        'key_features_heading' => 'Oracle Fusion HCM Training for Bangalore - Key Features',
                        'modules_heading' => 'Oracle Fusion HCM Modules Covered in This Course',
                        'details_heading' => 'Oracle Fusion HCM Course Details, Duration & Fee',
                        'faq_heading' => 'Oracle Fusion HCM Training in Bangalore FAQs',
                        'final_cta_heading' => 'Start Your Oracle Fusion HCM Training from Bangalore',
                        'final_cta_sub' => 'Book a free demo class and see how the Oracle Fusion HCM course, modules, projects, and placement support work before joining.'
                    ),
                    'oracle-fusion-hcm-training-in-delhi' => array(
                        'title' => 'Oracle Fusion HCM Training in Delhi | Certification + Job Support',
                        'description' => 'Join Oracle Fusion HCM Training in Delhi with live online classes, Core HR, Payroll, Absence, Talent, Compensation, real-time projects, certification guidance, and placement support.',
                        'eyebrow' => 'ORACLE FUSION HCM TRAINING IN DELHI | LIVE ONLINE · CERTIFICATION · PLACEMENT SUPPORT',
                        'h1' => '<span class="text-gradient">Oracle Fusion HCM</span> Training in Delhi with Live Online Classes',
                        'hero_sub' => 'Learn Oracle Fusion HCM through live online training for Delhi learners. Master Core HR, Payroll, Absence, Talent, Compensation, and real-time implementation workflows with expert trainer guidance and job support.',
                        'primary_cta' => 'Book Free Demo Class',
                        'wa_cta' => 'Get Delhi Batch Details on WhatsApp →',
                        'form_heading' => 'Get Your Free Oracle HCM Demo Class',
                        'audience_heading' => 'Is This Oracle Fusion HCM Course Right for Delhi Learners?',
                        'curriculum_heading' => 'Oracle Fusion HCM Training Curriculum for Delhi Learners',
                        'key_features_heading' => 'Oracle Fusion HCM Training in Delhi - Key Features',
                        'modules_heading' => 'Oracle Fusion HCM Modules You Will Learn',
                        'details_heading' => 'Oracle Fusion HCM Course Details, Duration & Fee',
                        'faq_heading' => 'Oracle Fusion HCM Training in Delhi FAQs',
                        'final_cta_heading' => 'Ready to Join Oracle Fusion HCM Training in Delhi?',
                        'final_cta_sub' => 'Book a free demo class and understand the Oracle HCM course structure, trainer, modules, fee, and placement support before enrolling.'
                    ),
                    'oracle-fusion-hcm-online-training' => array(
                        'title' => 'Oracle Fusion HCM Online Training | Live Classes + Projects',
                        'description' => 'Join Oracle Fusion HCM Online Training with live classes, Core HR, Payroll, Absence, Talent, Compensation, hands-on projects, certification guidance, and placement support.',
                        'eyebrow' => 'ORACLE FUSION HCM ONLINE TRAINING | LIVE CLASSES · LMS ACCESS · PLACEMENT SUPPORT',
                        'h1' => '<span class="text-gradient">Oracle Fusion HCM</span> Online Training with Live Projects',
                        'hero_sub' => 'Join live Oracle Fusion HCM online training and learn Core HR, Payroll, Absence, Talent Management, Compensation, OTL, and implementation workflows with recordings, LMS access, mock interviews, and placement support.',
                        'primary_cta' => 'Book Free Online Demo',
                        'wa_cta' => 'Get Online HCM Course Details on WhatsApp →',
                        'form_heading' => 'Get Your Free Online Demo Class',
                        'audience_heading' => 'Who Should Join Oracle Fusion HCM Online Training?',
                        'curriculum_heading' => 'Oracle Fusion HCM Online Training Curriculum',
                        'key_features_heading' => 'Oracle Fusion HCM Online Training - Key Features',
                        'modules_heading' => 'Oracle Fusion HCM Modules Covered Online',
                        'details_heading' => 'Oracle Fusion HCM Online Course Details, Duration & Fee',
                        'faq_heading' => 'Oracle Fusion HCM Online Training FAQs',
                        'final_cta_heading' => 'Start Oracle Fusion HCM Online Training with a Free Demo',
                        'final_cta_sub' => 'Attend a free live demo class and see the trainer, course modules, LMS access, projects, and placement support before joining.'
                    ),
                    'oracle-fusion-hcm-course' => array(
                        'title' => 'Oracle Fusion HCM Course | Fees, Curriculum & Placement Support',
                        'description' => 'Explore Oracle Fusion HCM Course details including curriculum, fees, duration, Core HR, Payroll, Absence, Talent, Compensation, certification guidance, and placement support.',
                        'eyebrow' => 'ORACLE FUSION HCM COURSE | CURRICULUM · FEES · CERTIFICATION · PLACEMENT SUPPORT',
                        'h1' => '<span class="text-gradient">Oracle Fusion HCM</span> Course with Complete Curriculum & Job Support',
                        'hero_sub' => 'Explore a complete Oracle Fusion HCM course covering Core HR, Payroll, Absence, Talent, Compensation, OTL, real-time projects, certification guidance, course fee details, and placement preparation.',
                        'primary_cta' => 'Get Course Details',
                        'wa_cta' => 'Ask for HCM Course Fee on WhatsApp →',
                        'form_heading' => 'Get Oracle HCM Course Details',
                        'audience_heading' => 'Is This Oracle Fusion HCM Course Right for You?',
                        'curriculum_heading' => 'Oracle Fusion HCM Course Curriculum',
                        'key_features_heading' => 'Oracle Fusion HCM Course - Key Features',
                        'modules_heading' => 'Oracle Fusion HCM Course Modules',
                        'details_heading' => 'Oracle Fusion HCM Course Details, Duration & Fee',
                        'faq_heading' => 'Oracle Fusion HCM Course FAQs',
                        'final_cta_heading' => 'Want the Oracle HCM Course Fee and Batch Details?',
                        'final_cta_sub' => 'Book a free demo class and get complete details about the Oracle HCM course fee, modules, duration, trainer, projects, and placement support.'
                    ),
                    'oracle-fusion-hcm-functional-training' => array(
                        'title' => 'Oracle Fusion HCM Functional Training | Core HR, Payroll, Talent',
                        'description' => 'Join Oracle Fusion HCM Functional Training covering Core HR, Payroll, Absence, Talent, Compensation, OTL, functional setup, real-time projects, and placement support.',
                        'eyebrow' => 'ORACLE FUSION HCM FUNCTIONAL TRAINING | CORE HR · PAYROLL · TALENT · ABSENCE',
                        'h1' => '<span class="text-gradient">Oracle Fusion HCM</span> Functional Training with Real-Time Implementation',
                        'hero_sub' => 'Learn Oracle Fusion HCM functional modules including Core HR, Payroll, Absence, Talent Management, Compensation, OTL, and functional setup through live projects, implementation workflows, and expert trainer support.',
                        'primary_cta' => 'Book Functional Demo Class',
                        'wa_cta' => 'Get Functional Training Details on WhatsApp →',
                        'form_heading' => 'Get Your Free Functional Demo Class',
                        'audience_heading' => 'Who Should Join Oracle Fusion HCM Functional Training?',
                        'curriculum_heading' => 'Oracle Fusion HCM Functional Training Curriculum',
                        'key_features_heading' => 'Oracle Fusion HCM Functional Training - Key Features',
                        'modules_heading' => 'Oracle Fusion HCM Functional Modules You Will Master',
                        'details_heading' => 'Oracle Fusion HCM Functional Training Details',
                        'faq_heading' => 'Oracle Fusion HCM Functional Training FAQs',
                        'final_cta_heading' => 'Ready to Build Oracle HCM Functional Skills?',
                        'final_cta_sub' => 'Attend a free demo class and understand how Core HR, Payroll, Talent, Absence, and other functional modules are taught with real-time implementation practice.'
                    ),
                    'oracle-fusion-hcm-modules-training' => array(
                        'title' => 'Oracle Fusion HCM Modules Training | Payroll, Core HR, Talent, Absence',
                        'description' => 'Master Oracle Fusion HCM modules including Payroll, Core HR, Talent Management, Absence Management, Compensation, OTL, hands-on projects, and placement support.',
                        'eyebrow' => 'ORACLE FUSION HCM MODULES TRAINING | PAYROLL · CORE HR · TALENT · ABSENCE',
                        'h1' => '<span class="text-gradient">Oracle Fusion HCM</span> Modules Training: Core HR, Payroll, Talent & Absence',
                        'hero_sub' => 'Master key Oracle Fusion HCM modules including Core HR, Payroll, Absence Management, Talent Management, Compensation, OTL, and implementation workflows with hands-on practice and expert trainer support.',
                        'primary_cta' => 'Book Module Demo Class',
                        'wa_cta' => 'Get HCM Module Details on WhatsApp →',
                        'form_heading' => 'Get Your Free Module Demo Class',
                        'audience_heading' => 'Who Should Learn Oracle Fusion HCM Modules?',
                        'curriculum_heading' => 'Oracle Fusion HCM Modules Curriculum',
                        'key_features_heading' => 'Oracle Fusion HCM Modules Training - Key Features',
                        'modules_heading' => 'Oracle Fusion HCM Modules You Will Master',
                        'details_heading' => 'Oracle Fusion HCM Modules Training Details',
                        'faq_heading' => 'Oracle Fusion HCM Modules Training FAQs',
                        'final_cta_heading' => 'Want to Learn Payroll, Core HR, Talent and Absence Modules?',
                        'final_cta_sub' => 'Book a free demo class and see how each Oracle HCM module is taught through practical configuration, implementation flows, and real-time project scenarios.'
                    )
                );

                if (isset($slug_replacements[$slug])) {
                    $v = $slug_replacements[$slug];
                    
                    // 1. Title replacement
                    $html_content = preg_replace('/<title>.*?<\/title>/i', '<title>' . $v['title'] . '</title>', $html_content);
                    
                    // 2. Meta description replacement
                    $html_content = preg_replace('/<meta\s+name="description"\s+content=".*?"\s*\/?>/i', '<meta name="description" content="' . esc_attr($v['description']) . '" />', $html_content);
                    
                    // 3. Eyebrow injection (above headline-container)
                    if (!empty($v['eyebrow'])) {
                        $eyebrow_html = '<span class="eyebrow">' . esc_html($v['eyebrow']) . '</span>';
                        $html_content = str_replace('<div class="headline-container">', $eyebrow_html . "\n                    " . '<div class="headline-container">', $html_content);
                    }
                    
                    // 4. H1 Headline replacement
                    $html_content = preg_replace('/<h1 id="hero-headline" class="hero-title">.*?<\/h1>/is', '<h1 id="hero-headline" class="hero-title">' . $v['h1'] . '</h1>', $html_content);
                    
                    // 5. Hero Subtitle replacement
                    $html_content = preg_replace('/<p class="hero-subtitle">.*?<\/p>/is', '<p class="hero-subtitle">' . esc_html($v['hero_sub']) . '</p>', $html_content);
                    
                    // 6. Primary CTA button replacement (two occurrences)
                    $html_content = str_replace('Book Your Free Demo Class', $v['primary_cta'], $html_content);
                    
                    // 7. WhatsApp CTA replacement
                    $html_content = str_replace('Talk to a Counselor on WhatsApp →', $v['wa_cta'], $html_content);
                    
                    // 8. Form headings replacement
                    $html_content = str_replace('<h3 class="form-title">Get Your Free Demo Class</h3>', '<h3 class="form-title">' . esc_html($v['form_heading']) . '</h3>', $html_content);
                    
                    // 9. Audience section heading replacement
                    $html_content = str_replace('<h2 class="section-title">Is This Oracle Fusion HCM Course Right for You?</h2>', '<h2 class="section-title">' . esc_html($v['audience_heading']) . '</h2>', $html_content);
                    
                    // 10. Curriculum section heading replacement
                    $html_content = str_replace('<h2 class="section-title">Oracle Fusion HCM Course Curriculum</h2>', '<h2 class="section-title">' . esc_html($v['curriculum_heading']) . '</h2>', $html_content);
                    
                    // 11. Key features section heading replacement
                    $html_content = str_replace('<h2 class="section-title">Oracle Fusion HCM Training - Key Features</h2>', '<h2 class="section-title">' . esc_html($v['key_features_heading']) . '</h2>', $html_content);
                    
                    // 12. Modules section heading replacement
                    $html_content = str_replace('<h3 class="snapshot-title text-center">Oracle Fusion HCM Modules You Will Master</h3>', '<h3 class="snapshot-title text-center">' . esc_html($v['modules_heading']) . '</h3>', $html_content);
                    $html_content = str_replace('<h3 class="snapshot-title text-center">Tools & Modules You Will Master</h3>', '<h3 class="snapshot-title text-center">' . esc_html($v['modules_heading']) . '</h3>', $html_content);
                    
                    // 13. Details section heading replacement
                    $html_content = str_replace('<h2 class="section-title">Program Details</h2>', '<h2 class="section-title">' . esc_html($v['details_heading']) . '</h2>', $html_content);
                    
                    // 14. FAQ section heading replacement
                    $html_content = str_replace('<h2 class="section-title">Frequently Asked Questions</h2>', '<h2 class="section-title">' . esc_html($v['faq_heading']) . '</h2>', $html_content);
                    
                    // 15. Final CTA heading and subheading replacement
                    $html_content = str_replace('<h2 class="final-title">Ready to Start Your Oracle Fusion HCM Career?</h2>', '<h2 class="final-title">' . esc_html($v['final_cta_heading']) . '</h2>', $html_content);
                    $html_content = str_replace('<h2 class="final-title">Ready to Get Incubated in Oracle Fusion HCM?</h2>', '<h2 class="final-title">' . esc_html($v['final_cta_heading']) . '</h2>', $html_content);
                    
                    $html_content = preg_replace('/<p class="final-subtitle">.*?<\/p>/is', '<p class="final-subtitle">' . esc_html($v['final_cta_sub']) . '</p>', $html_content);
                }

                // Force HTTP status header to 200 OK (overriding WordPress automatic 404 query status)
                status_header(200);

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

/**
 * Resolves geolocation (City, State, Country) from a given IP address using ip-api.com
 */
function techleadsit_get_ip_location($ip) {
    if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return 'Unknown IP';
    }
    
    // Ignore private/local IP ranges
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return 'Local/Private IP';
    }

    $geo_url = 'http://ip-api.com/json/' . $ip;
    $response = wp_remote_get($geo_url, array('timeout' => 3));

    if (is_wp_error($response)) {
        return 'Geo API Error';
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (is_array($data) && ($data['status'] ?? '') === 'success') {
        $city = $data['city'] ?? '';
        $state = $data['regionName'] ?? '';
        $country = $data['country'] ?? '';

        $parts = array_filter(array($city, $state, $country));
        return !empty($parts) ? implode(', ', $parts) : 'Unknown Location';
    }

    return 'Location Not Found';
}

function techleadsit_handle_crm_lead(WP_REST_Request $request) {
    $params = $request->get_json_params();

    // Validate name and 10-digit phone number
    $name = sanitize_text_field($params['name'] ?? '');
    $phone = sanitize_text_field($params['phone'] ?? '');
    $email = sanitize_email($params['email'] ?? '');
    $role = sanitize_text_field($params['role'] ?? '');
    $salary = sanitize_text_field($params['salary'] ?? '');
    $experience = sanitize_text_field($params['experience'] ?? '');
    $landing_page = esc_url_raw($params['landing_page'] ?? '');
    $location = sanitize_text_field($params['location'] ?? '');
    $scm_year = sanitize_text_field($params['scm_year'] ?? '');

    // Auto-detect real IP address and resolve location via Geolocation API
    $user_ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $user_ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        $ip_list = explode(',', $user_ip);
        $user_ip = trim($ip_list[0]);
    } else {
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }

    $ip_location = techleadsit_get_ip_location($user_ip);
    if (empty($location) || $location === 'Unknown') {
        $location = $ip_location;
    }

    // Verify OTP first if email is provided, unless bypassed for specific landing pages
    $bypass_otp = false;
    if (!empty($landing_page) && (
        strpos($landing_page, 'oracle-fusion-scm-training') !== false || 
        strpos($landing_page, 'scm-training') !== false ||
        strpos($landing_page, 'rise-form-16465496') !== false ||
        strpos($landing_page, 'free-sql-training-44819') !== false ||
        strpos($landing_page, 'oracle-fusion-hcm') !== false ||
        strpos($landing_page, 'f-hcm-course') !== false ||
        strpos($landing_page, 'mb-hr-to') !== false
    )) {
        $bypass_otp = true;
    }

    if (!empty($email) && !$bypass_otp) {
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
    $referrer = sanitize_text_field($params['referrer'] ?? '');

    // If it's one of the two HCM landing pages, customize and normalize course name and source
    if (!empty($landing_page) && (
        strpos($landing_page, 'f-hcm-course') !== false ||
        strpos($landing_page, 'mb-hr-to') !== false ||
        strpos($landing_page, 'oracle-fusion-hcm') !== false
    )) {
        $scm_year = 'Oracle Fusion HCM';
        
        $normalized_source = 'Direct';
        if (!empty($utm_source) && strtolower($utm_source) !== 'direct') {
            if (stripos($utm_source, 'google') !== false) {
                $normalized_source = 'Google Ads';
            } elseif (stripos($utm_source, 'facebook') !== false || stripos($utm_source, 'fb') !== false || stripos($utm_source, 'ig') !== false || stripos($utm_source, 'instagram') !== false) {
                $normalized_source = 'Facebook Ads';
            } else {
                $normalized_source = $utm_source;
            }
        } else {
            if (!empty($gclid) || !empty($gbraid) || !empty($wbraid)) {
                $normalized_source = 'Google Ads';
            } elseif (!empty($fbclid)) {
                $normalized_source = 'Facebook Ads';
            } elseif (!empty($referrer) && $referrer !== 'direct') {
                $normalized_source = $referrer;
            }
        }
        $utm_source = $normalized_source;
    }

    if (empty($name) || !preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
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

    // Get current date/time in Indian Standard Time (IST)
    $date_ist = new DateTime("now", new DateTimeZone("Asia/Kolkata"));
    $lead_date = $date_ist->format("Y-m-d H:i:s");

    // Build the payload matching TeleCRM API specification
    $payload = array(
        'fields' => array(
            'name' => $name,
            'phone' => (strpos($phone, '+') === 0) ? $phone : '+91' . $phone,
            'email' => $email,
            'role' => $role,
            'salary' => $salary,
            'experience' => $experience,
            'location' => $location,
            'scm_year' => $scm_year,
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
            'leaddate' => $lead_date,
            'date' => $lead_date,
            // Custom fields without underscores (matching your TeleCRM account fields)
            'utmsource' => $utm_source,
            'utmmedium' => $utm_medium,
            'utmcampaign' => $utm_campaign,
            'utmadgroup' => $utm_adgroup,
            'utmterm' => $utm_term,
            'utmcontent' => $utm_content,
            'landingpage' => $landing_page,
            'location' => $location,
            'scmyear' => $scm_year,
            'date' => $lead_date,
            'leaddate' => $lead_date,
            'ipaddress' => $user_ip,
            'iplocation' => $ip_location
        ),
        'actions' => array(
            array(
                'type' => 'SYSTEM_NOTE',
                'text' => "Location: " . $location . "\n" .
                          "IP Address: " . $user_ip . "\n" .
                          "IP Location: " . $ip_location . "\n" .
                          "SCM Training Year: " . $scm_year . "\n" .
                          "Lead Date: " . $lead_date . "\n\n" .
                          "Marketing Tracking Details:\n" .
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

