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
        'oracle-fusion-hcm-online-training' => 'f-hcm-course/index.html',
        'oracle-fusion-hcm-course' => 'f-hcm-course/index.html',
        'oracle-fusion-hcm-functional-training' => 'f-hcm-course/index.html',
        'oracle-fusion-hcm-modules-training' => 'f-hcm-course/index.html',
        'oracle-fusion-hcm-training-in-pune' => 'f-hcm-course/index.html',
        'oracle-fusion-hcm-training-in-chennai' => 'f-hcm-course/index.html',
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
                $html_content = str_replace('href="styles.css"', 'href="' . $plugin_url . 'styles.css?v=12.0"', $html_content);
                $html_content = str_replace('src="script.js"', 'src="' . $plugin_url . 'script.js?v=12.0"', $html_content);
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

                // Dynamically inject Microsoft Clarity tracking code
                $clarity_code = "\n<!-- Microsoft Clarity -->\n" .
                                "<script type=\"text/javascript\">\n" .
                                "    (function(c,l,a,r,i,t,y){\n" .
                                "        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};\n" .
                                "        t=l.createElement(r);t.async=1;t.src=\"https://www.clarity.ms/tag/\"+i;\n" .
                                "        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);\n" .
                                "    })(window, document, \"clarity\", \"script\", \"u5xu6vnb88\");\n" .
                                "</script>\n" .
                                "<!-- End Microsoft Clarity -->\n";
                $html_content = str_replace('<head>', '<head>' . $clarity_code, $html_content);

                // Dynamically inject the active slug variable to window.pageSlug context
                $slug_inject = "\n<script>window.pageSlug = '" . esc_js($slug) . "';</script>\n";
                $html_content = str_replace('<head>', '<head>' . $slug_inject, $html_content);

                // Multi-Course Context & Dynamic Batch Settings from WordPress Admin
                $matched_course_id = techleadsit_find_course_for_slug($slug);
                $course_opts = techleadsit_get_course_options($matched_course_id);

                $countdown_enabled = isset($course_opts['countdown_enabled']) ? (bool)$course_opts['countdown_enabled'] : true;
                $countdown_text = !empty($course_opts['countdown_text']) ? $course_opts['countdown_text'] : 'Next Batch starts soon - Only 4 seats left!';
                $countdown_target = !empty($course_opts['countdown_target_datetime']) ? $course_opts['countdown_target_datetime'] : '2026-09-23T20:30';
                $countdown_seats = !empty($course_opts['countdown_seats_count']) ? $course_opts['countdown_seats_count'] : '4';

                $batch_section_enabled = isset($course_opts['batch_section_enabled']) ? (bool)$course_opts['batch_section_enabled'] : true;
                $batch_title = !empty($course_opts['batch_title']) ? $course_opts['batch_title'] : 'Next Batch Starts Soon';
                $batch_subtitle = !empty($course_opts['batch_subtitle']) ? $course_opts['batch_subtitle'] : 'Limited seats available - Reserve your spot today';
                $batch_start_date = !empty($course_opts['batch_start_date']) ? $course_opts['batch_start_date'] : '23rd Sep, 26';
                $batch_timing = !empty($course_opts['batch_timing']) ? $course_opts['batch_timing'] : '8:30 PM to 9:30 PM';
                $batch_pills = !empty($course_opts['batch_pills']) ? $course_opts['batch_pills'] : 'Online, Weekday (TTS)';
                $batch_seats_left = !empty($course_opts['batch_seats_left']) ? $course_opts['batch_seats_left'] : '10';
                $base_visitor_floor = !empty($course_opts['base_visitor_floor']) ? (int)$course_opts['base_visitor_floor'] : 1840;

                // Server-side persistent monotonic visitor counter (Never decreases)
                $db_views_key = 'techleadsit_views_' . $matched_course_id;
                $current_db_views = (int) get_option($db_views_key, $base_visitor_floor);
                if ($current_views_stored = max($base_visitor_floor, $current_db_views + 1)) {
                    update_option($db_views_key, $current_views_stored, false);
                    $base_visitor_floor = $current_views_stored;
                }

                // Inject global JS config object
                $js_config = array(
                    'courseId' => $matched_course_id,
                    'countdownEnabled' => $countdown_enabled,
                    'countdownTarget' => $countdown_target,
                    'countdownSeats' => $countdown_seats,
                    'batchSectionEnabled' => $batch_section_enabled,
                    'batchStartDate' => $batch_start_date,
                    'batchTiming' => $batch_timing,
                    'batchSeatsLeft' => $batch_seats_left,
                    'baseVisitorFloor' => $base_visitor_floor,
                );
                $config_script = "\n<script>window.TECHLEADSIT_BATCH_CONFIG = " . json_encode($js_config) . ";</script>\n";
                $html_content = str_replace('<head>', '<head>' . $config_script, $html_content);

                // Handle countdown bar visibility and dynamic text
                if (!$countdown_enabled) {
                    $html_content = str_replace('id="countdownBar"', 'id="countdownBar" style="display: none !important;"', $html_content);
                } else {
                    // Embed seats number if present
                    $clean_text = esc_html($countdown_text);
                    if (!empty($countdown_seats) && strpos($clean_text, $countdown_seats) === false) {
                        // Replace any digit before 'seats left' with the exact seats count
                        $clean_text = preg_replace('/\d+(\s+seats\s+left)/i', $countdown_seats . '$1', $clean_text);
                    }
                    $formatted_msg = '<span class="countdown-text" id="countdownText"><i class="ri-flashlight-fill ri-flash-icon"></i> <span id="countdownMsg">' . $clean_text . '</span></span>';
                    $html_content = preg_replace('/<span class="countdown-text"[^>]*>.*?<\/span>\s*<\/span>|<span class="countdown-text"[^>]*>.*?<\/span>/s', $formatted_msg, $html_content);
                }

                // Handle batch schedule section visibility and dynamic text
                if (!$batch_section_enabled) {
                    $html_content = str_replace('id="cohort-schedule"', 'id="cohort-schedule" style="display: none !important;"', $html_content);
                } else {
                    $html_content = preg_replace('/<h2 class="cohort-title">.*?<\/h2>/', '<h2 class="cohort-title">' . esc_html($batch_title) . '</h2>', $html_content);
                    $html_content = preg_replace('/<p class="cohort-subtitle">.*?<\/p>/', '<p class="cohort-subtitle">' . esc_html($batch_subtitle) . '</p>', $html_content);
                    $html_content = preg_replace('/<span class="cohort-date-val">.*?<\/span>/', '<span class="cohort-date-val">' . esc_html($batch_start_date) . '</span>', $html_content);
                    $html_content = preg_replace('/<div class="cohort-time-item"><i class="ri-time-line"><\/i><span>.*?<\/span><\/div>/', '<div class="cohort-time-item"><i class="ri-time-line"></i><span>' . esc_html($batch_timing) . '</span></div>', $html_content);
                    
                    // Generate pills HTML
                    $pills_array = array_map('trim', explode(',', $batch_pills));
                    $pills_html = '';
                    foreach ($pills_array as $pill) {
                        if (!empty($pill)) {
                            $pills_html .= '<span class="cohort-pill">' . esc_html($pill) . '</span> ';
                        }
                    }
                    $html_content = preg_replace('/<div class="cohort-pills-wrap">.*?<\/div>/s', '<div class="cohort-pills-wrap">' . $pills_html . '</div>', $html_content);
                    $html_content = preg_replace('/<span>Filling Fast • <strong>\d+<\/strong> seats left<\/span>/', '<span>Filling Fast • <strong>' . esc_html($batch_seats_left) . '</strong> seats left</span>', $html_content);
                }
                
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
                        'final_cta_sub' => 'Join the next Oracle Fusion HCM demo class and understand the course, trainer, modules, fee, and placement support before enrolling.',
                        'exit_subtitle' => 'Did you know an Oracle Fusion HCM consultant in Hyderabad earns an average of ₹10.5L – ₹12.5L per year?'
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
                        'final_cta_sub' => 'Book a free demo class and see how the Oracle Fusion HCM course, modules, projects, and placement support work before joining.',
                        'exit_subtitle' => 'Did you know an Oracle Fusion HCM consultant in Bangalore earns an average of ₹11.5L – ₹13.5L per year?'
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
                        'final_cta_sub' => 'Attend a free live demo class and see the trainer, course modules, LMS access, projects, and placement support before joining.',
                        'exit_subtitle' => 'Did you know an Oracle Fusion HCM consultant earns an average of ₹10.5L – ₹12.5L per year?'
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
                        'final_cta_sub' => 'Book a free demo class and get complete details about the Oracle HCM course fee, modules, duration, trainer, projects, and placement support.',
                        'exit_subtitle' => 'Did you know an Oracle Fusion HCM consultant earns an average of ₹10.5L – ₹12.5L per year?'
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
                        'final_cta_sub' => 'Attend a free demo class and understand how Core HR, Payroll, Talent, Absence, and other functional modules are taught with real-time implementation practice.',
                        'exit_subtitle' => 'Did you know an Oracle Fusion HCM consultant earns an average of ₹10.5L – ₹12.5L per year?'
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
                        'final_cta_sub' => 'Book a free demo class and see how each Oracle HCM module is taught through practical configuration, implementation flows, and real-time project scenarios.',
                        'exit_subtitle' => 'Did you know an Oracle Fusion HCM consultant earns an average of ₹10.5L – ₹12.5L per year?'
                    ),
                    'oracle-fusion-hcm-training-in-pune' => array(
                        'title' => 'Top-Rated Oracle HCM Training in Pune | Live Projects',
                        'description' => 'Join top-rated Oracle Fusion HCM training in Pune. Learn 11 modules through live classes, projects, certification guidance, and placement assistance.',
                        'eyebrow' => 'TOP-RATED ORACLE FUSION HCM TRAINING IN PUNE | LIVE ONLINE · PROJECTS · CERTIFICATION GUIDANCE',
                        'h1' => 'Top-Rated Oracle Fusion HCM Training in Pune with Live Projects',
                        'hero_sub' => 'Build practical Oracle Fusion HCM skills with expert-led live training for Pune learners. Master Core HR, Payroll, Absence, Talent, Compensation, OTL, and implementation workflows with hands-on projects, 6-month HCM instance access, 2-year LMS access, free mock interviews, and placement assistance.',
                        'primary_cta' => 'Book My Free HCM Demo',
                        'wa_cta' => 'Get Pune HCM Batch Details on WhatsApp →',
                        'form_heading' => 'Get Your Free Oracle HCM Demo Class',
                        'audience_heading' => 'Is This Oracle Fusion HCM Course Right for Pune Learners?',
                        'audience_sub' => 'This program is designed for Pune learners who want practical Oracle Fusion HCM skills—not a general HR qualification. The training focuses on enterprise HCM configuration, implementation workflows, Core HR, Payroll, Absence, Talent, Compensation, OTL, reporting, and real project scenarios.',
                        'spec_box_title' => 'Specialized Oracle HCM Training — Not a General HR Course',
                        'spec_box_body' => 'This is not an MBA HR, HR generalist, recruitment, labor-law, or generic human-resources course. It is specialized Oracle Fusion HCM training focused on configuring and implementing enterprise HCM modules in a live Oracle environment.',
                        'cert_card_title' => 'Certification Guidance Built Into Complete HCM Training',
                        'cert_card_body' => 'Certification guidance is included as part of the complete Oracle Fusion HCM learning path. We help learners understand relevant certification topics and preparation requirements, but the program is not an exam-only, voucher-only, dumps, or certification-questions service.',
                        'curriculum_heading' => 'Oracle Fusion HCM Training Curriculum for Pune Learners',
                        'curriculum_sub' => 'Follow a complete, expert-guided learning path across 11 Oracle Fusion HCM modules with 60+ hours of live instruction, practical configuration exercises, case studies, and a real-time implementation project.',
                        'key_features_heading' => 'Why Pune Learners Choose This Oracle Fusion HCM Training',
                        'modules_heading' => 'Master 11 Oracle Fusion HCM Modules',
                        'modules_sub' => 'Build complete HCM implementation skills across Core HR, Payroll, Absence, Talent, Compensation, Goal Management, Performance, Security, OTL, Profile Management, and reporting.',
                        'career_heading' => 'Build Career-Ready Oracle HCM Skills in Pune',
                        'career_sub' => 'Move from learning concepts to configuring real Oracle HCM workflows. Build the practical knowledge, project exposure, interview confidence, and functional skills required for Oracle Fusion HCM support and consulting opportunities.',
                        'details_heading' => 'Oracle Fusion HCM Training in Pune — Duration, Access and Support',
                        'faq_heading' => 'Oracle Fusion HCM Training in Pune FAQs',
                        'final_cta_heading' => 'Ready to Master Oracle Fusion HCM in Pune?',
                        'final_cta_sub' => 'Book a free live demo and explore the trainer, 11-module curriculum, projects, 6-month HCM instance access, 2-year LMS access, certification guidance, free mock interviews, and placement assistance before enrolling.',
                        'exit_subtitle' => 'Did you know an Oracle Fusion HCM consultant in Pune earns an average of ₹10.5L – ₹12.5L per year?',
                        'details_table' => '
                        <table class="details-table">
                            <tbody>
                                <tr>
                                    <td><strong>Duration</strong></td>
                                    <td>2.5 months, including a real-time project</td>
                                </tr>
                                <tr>
                                    <td><strong>Live Training</strong></td>
                                    <td>60+ hours</td>
                                </tr>
                                <tr>
                                    <td><strong>Modules</strong></td>
                                    <td>11 Oracle Fusion HCM modules</td>
                                </tr>
                                <tr>
                                    <td><strong>Mode</strong></td>
                                    <td>Live online training for Pune learners</td>
                                </tr>
                                <tr>
                                    <td><strong>HCM Instance Access</strong></td>
                                    <td>6 months</td>
                                </tr>
                                <tr>
                                    <td><strong>LMS Access</strong></td>
                                    <td>2 years</td>
                                </tr>
                                <tr>
                                    <td><strong>Mock Interviews</strong></td>
                                    <td>Free</td>
                                </tr>
                                <tr>
                                    <td><strong>Certification</strong></td>
                                    <td>Guidance and preparation support included</td>
                                </tr>
                                <tr>
                                    <td><strong>Placement</strong></td>
                                    <td>100% placement assistance; no guaranteed-job claim</td>
                                </tr>
                                <tr>
                                    <td><strong>Prerequisites</strong></td>
                                    <td>No coding required; no prior Oracle experience required</td>
                                </tr>
                            </tbody>
                        </table>',
                        'faq_block' => '
                <div class="faq-accordion-wrapper">
                    <!-- Q1 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">Is this a general HR course?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                No. This is specialized Oracle Fusion HCM functional training. It focuses on configuring and implementing enterprise HCM modules rather than general HR theory, recruitment basics, labor law, or MBA HR topics.
                            </p>
                        </div>
                    </div>

                    <!-- Q2 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">Do you provide Oracle HCM certification?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                We provide certification guidance and preparation support as part of the complete HCM course. We do not provide an exam-only, voucher-only, dumps, or certification-questions service.
                            </p>
                        </div>
                    </div>

                    <!-- Q3 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">Do I need an HR or coding background?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                No coding is required, and prior Oracle experience is not mandatory. The course starts with the required HCM and functional foundations before moving into implementation.
                            </p>
                        </div>
                    </div>

                    <!-- Q4 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">Is the training available for Pune learners?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                Yes. Pune learners can attend live online, instructor-led sessions, interact with the trainer, access recordings, practise on the HCM instance, and complete real-time project exercises.
                            </p>
                        </div>
                    </div>

                    <!-- Q5 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">How long can I access the Oracle HCM instance?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                Students receive six months of Oracle Fusion HCM instance access for practical exercises and implementation practice.
                            </p>
                        </div>
                    </div>

                    <!-- Q6 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">How long is LMS access available?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                Students receive two years of LMS access for recordings, materials, lab guides, and course resources.
                            </p>
                        </div>
                    </div>

                    <!-- Q7 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">Are mock interviews included?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                Yes. Free mock interviews, resume preparation support, guided questions, and interview-readiness assistance are included.
                            </p>
                        </div>
                    </div>

                    <!-- Q8 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">Is placement guaranteed?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                No job is guaranteed. Tech Leads IT provides 100% placement assistance through resume support, mock interviews, job-readiness guidance, and opportunity assistance.
                            </p>
                        </div>
                    </div>
                </div>'
                    ),
                    'oracle-fusion-hcm-training-in-chennai' => array(
                        'title' => 'Expert-Led Oracle HCM Training in Chennai | Live Projects',
                        'description' => 'Join expert-led Oracle Fusion HCM training in Chennai. Learn 11 modules through live classes, projects, certification guidance, and placement assistance.',
                        'eyebrow' => 'EXPERT-LED ORACLE FUSION HCM TRAINING IN CHENNAI | LIVE ONLINE · PROJECTS · CERTIFICATION GUIDANCE',
                        'h1' => 'Expert-Led Oracle Fusion HCM Training in Chennai with Live Projects',
                        'hero_sub' => 'Build practical Oracle Fusion HCM skills with expert-led live training for Chennai learners. Master Core HR, Payroll, Absence, Talent, Compensation, OTL, and implementation workflows with hands-on projects, 6-month HCM instance access, 2-year LMS access, free mock interviews, and placement assistance.',
                        'primary_cta' => 'Book My Free HCM Demo',
                        'wa_cta' => 'Get Chennai HCM Batch Details on WhatsApp →',
                        'form_heading' => 'Get Your Free Oracle HCM Demo Class',
                        'audience_heading' => 'Is This Oracle Fusion HCM Course Right for Chennai Learners?',
                        'audience_sub' => 'This program is designed for Chennai learners who want practical Oracle Fusion HCM skills—not a general HR qualification. The training focuses on enterprise HCM configuration, implementation workflows, Core HR, Payroll, Absence, Talent, Compensation, OTL, reporting, and real project scenarios.',
                        'spec_box_title' => 'Specialized Oracle HCM Training — Not a General HR Course',
                        'spec_box_body' => 'This is not an MBA HR, HR generalist, recruitment, labor-law, or generic human-resources course. It is specialized Oracle Fusion HCM training focused on configuring and implementing enterprise HCM modules in a live Oracle environment.',
                        'cert_card_title' => 'Certification Guidance Built Into Complete HCM Training',
                        'cert_card_body' => 'Certification guidance is included as part of the complete Oracle Fusion HCM learning path. We help learners understand relevant certification topics and preparation requirements, but the program is not an exam-only, voucher-only, dumps, or certification-questions service.',
                        'curriculum_heading' => 'Oracle Fusion HCM Training Curriculum for Chennai Learners',
                        'curriculum_sub' => 'Follow a complete, expert-guided learning path across 11 Oracle Fusion HCM modules with 60+ hours of live instruction, practical configuration exercises, case studies, and a real-time implementation project.',
                        'key_features_heading' => 'Why Chennai Learners Choose This Oracle Fusion HCM Training',
                        'modules_heading' => 'Master 11 Oracle Fusion HCM Modules',
                        'modules_sub' => 'Build complete HCM implementation skills across Core HR, Payroll, Absence, Talent, Compensation, Goal Management, Performance, Security, OTL, Profile Management, and reporting.',
                        'career_heading' => 'Build Career-Ready Oracle HCM Skills in Chennai',
                        'career_sub' => 'Move from learning concepts to configuring real Oracle HCM workflows. Build the practical knowledge, project exposure, interview confidence, and functional skills required for Oracle Fusion HCM support and consulting opportunities.',
                        'details_heading' => 'Oracle Fusion HCM Training in Chennai — Duration, Access and Support',
                        'faq_heading' => 'Oracle Fusion HCM Training in Chennai FAQs',
                        'final_cta_heading' => 'Ready to Master Oracle Fusion HCM in Chennai?',
                        'final_cta_sub' => 'Book a free live demo and explore the trainer, 11-module curriculum, projects, 6-month HCM instance access, 2-year LMS access, certification guidance, free mock interviews, and placement assistance before enrolling.',
                        'exit_subtitle' => 'Did you know an Oracle Fusion HCM consultant in Chennai earns an average of ₹10.5L – ₹12.5L per year?',
                        'details_table' => '
                        <table class="details-table">
                            <tbody>
                                <tr>
                                    <td><strong>Duration</strong></td>
                                    <td>2.5 months, including a real-time project</td>
                                </tr>
                                <tr>
                                    <td><strong>Live Training</strong></td>
                                    <td>60+ hours</td>
                                </tr>
                                <tr>
                                    <td><strong>Modules</strong></td>
                                    <td>11 Oracle Fusion HCM modules</td>
                                </tr>
                                <tr>
                                    <td><strong>Mode</strong></td>
                                    <td>Live online training for Chennai learners</td>
                                </tr>
                                <tr>
                                    <td><strong>HCM Instance Access</strong></td>
                                    <td>6 months</td>
                                </tr>
                                <tr>
                                    <td><strong>LMS Access</strong></td>
                                    <td>2 years</td>
                                </tr>
                                <tr>
                                    <td><strong>Mock Interviews</strong></td>
                                    <td>Free</td>
                                </tr>
                                <tr>
                                    <td><strong>Certification</strong></td>
                                    <td>Guidance and preparation support included</td>
                                </tr>
                                <tr>
                                    <td><strong>Placement</strong></td>
                                    <td>100% placement assistance; no guaranteed-job claim</td>
                                </tr>
                                <tr>
                                    <td><strong>Prerequisites</strong></td>
                                    <td>No coding required; no prior Oracle experience required</td>
                                </tr>
                            </tbody>
                        </table>',
                        'faq_block' => '
                <div class="faq-accordion-wrapper">
                    <!-- Q1 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">Is this a general HR course?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                No. This is specialized Oracle Fusion HCM functional training. It focuses on configuring and implementing enterprise HCM modules rather than general HR theory, recruitment basics, labor law, or MBA HR topics.
                            </p>
                        </div>
                    </div>

                    <!-- Q2 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">Do you provide Oracle HCM certification?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                We provide certification guidance and preparation support as part of the complete HCM course. We do not provide an exam-only, voucher-only, dumps, or certification-questions service.
                            </p>
                        </div>
                    </div>

                    <!-- Q3 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">Do I need an HR or coding background?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                No coding is required, and prior Oracle experience is not mandatory. The course starts with the required HCM and functional foundations before moving into implementation.
                            </p>
                        </div>
                    </div>

                    <!-- Q4 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">Is the training available for Chennai learners?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                Yes. Chennai learners can attend live online, instructor-led sessions, interact with the trainer, access recordings, practise on the HCM instance, and complete real-time project exercises.
                            </p>
                        </div>
                    </div>

                    <!-- Q5 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">How long can I access the Oracle HCM instance?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                Students receive six months of Oracle Fusion HCM instance access for practical exercises and implementation practice.
                            </p>
                        </div>
                    </div>

                    <!-- Q6 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">How long is LMS access available?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                Students receive two years of LMS access for recordings, materials, lab guides, and course resources.
                            </p>
                        </div>
                    </div>

                    <!-- Q7 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">Are mock interviews included?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                Yes. Free mock interviews, resume preparation support, guided questions, and interview-readiness assistance are included.
                            </p>
                        </div>
                    </div>

                    <!-- Q8 -->
                    <div class="faq-item">
                        <button class="faq-trigger" aria-expanded="false">
                            <span class="faq-question">Is placement guaranteed?</span>
                            <span class="faq-chevron"></span>
                        </button>
                        <div class="faq-body">
                            <p class="faq-answer">
                                No job is guaranteed. Tech Leads IT provides 100% placement assistance through resume support, mock interviews, job-readiness guidance, and opportunity assistance.
                            </p>
                        </div>
                    </div>
                </div>'
                    )
                );

                if (isset($slug_replacements[$slug])) {
                    $v = $slug_replacements[$slug];
                    
                    // 1. Title replacement
                    $html_content = preg_replace('/<title>.*?<\/title>/i', '<title>' . $v['title'] . '</title>', $html_content);
                    
                    // 2. Meta description replacement
                    $html_content = preg_replace('/<meta\s+name="description"\s+content=".*?"\s*\/?>/i', '<meta name="description" content="' . esc_attr($v['description']) . '" />', $html_content);
                    
                    // 3. Eyebrow injection (above headline-container) - DISABLED
                    // if (!empty($v['eyebrow'])) {
                    //     $eyebrow_html = '<span class="eyebrow">' . esc_html($v['eyebrow']) . '</span>';
                    //     $html_content = str_replace('<div class="headline-container">', $eyebrow_html . "\n                    " . '<div class="headline-container">', $html_content);
                    // }
                    
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
                    
                    // 16. Exit Intent subtitle replacement
                    if (isset($v['exit_subtitle'])) {
                        $html_content = str_replace('<p class="exit-subtitle">Did you know an Oracle Fusion HCM consultant in Hyderabad earns an average of ₹10.5L – ₹12.5L per year?</p>', '<p class="exit-subtitle">' . esc_html($v['exit_subtitle']) . '</p>', $html_content);
                    }
                    
                    // 17. Audience section subtitle / intro replacement
                    if (isset($v['audience_sub'])) {
                        $html_content = str_replace('<p class="section-subtitle">Gain expertise in Human Capital Management with training designed for learners at every level - no prior Oracle experience required.</p>', '<p class="section-subtitle">' . esc_html($v['audience_sub']) . '</p>', $html_content);
                    }
                    
                    // 18. Specialization clarification box replacement
                    if (isset($v['spec_box_title']) && isset($v['spec_box_body'])) {
                        $html_content = str_replace('<h3>Why Oracle Fusion HCM Skills Matter for HR Careers</h3>', '<h3>' . esc_html($v['spec_box_title']) . '</h3>', $html_content);
                        $html_content = preg_replace('/<p class="warning-text">.*?<\/p>/is', '<p class="warning-text">' . esc_html($v['spec_box_body']) . '</p>', $html_content);
                    }
                    
                    // 19. Certification card text replacement
                    if (isset($v['cert_card_title']) && isset($v['cert_card_body'])) {
                        $html_content = str_replace('<h3>Prepare for Oracle Cloud Certification</h3>', '<h3>' . esc_html($v['cert_card_title']) . '</h3>', $html_content);
                        $html_content = str_replace('<p>This program fully maps to and prepares you for the following global credential exam:</p>', '<p>' . esc_html($v['cert_card_body']) . '</p>', $html_content);
                    }
                    
                    // 20. Curriculum section subtitle replacement
                    if (isset($v['curriculum_sub'])) {
                        $html_content = str_replace('<p class="section-subtitle">A detailed overview of our Oracle Fusion HCM training course, including all modules, technical concepts, and more.</p>', '<p class="section-subtitle">' . esc_html($v['curriculum_sub']) . '</p>', $html_content);
                    }
                    
                    // 21. Details table replacement
                    if (isset($v['details_table'])) {
                        $html_content = preg_replace('/<div class="details-table-wrapper">.*?<\/div>/is', '<div class="details-table-wrapper">' . $v['details_table'] . '</div>', $html_content);
                    }
                    
                    // 22. FAQ block replacement
                    if (isset($v['faq_block'])) {
                        $html_content = preg_replace('/<div class="faq-accordion-wrapper">.*?<\/div>\s*<\/div>\s*<\/div>\s*<\/section>\s*<!-- Final CTA Section -->/is', $v['faq_block'] . "\n            </div>\n        </section>\n\n        <!-- Final CTA Section -->", $html_content);
                    }
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
 * Resolves geolocation data array (City, State, Country) from a given IP address using ip-api.com
 */
function techleadsit_get_ip_geo_data($ip) {
    $default = array(
        'city' => '',
        'state' => '',
        'country' => '',
        'location' => 'Unknown'
    );
    
    if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return $default;
    }
    
    // Ignore private/local IP ranges
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return array_merge($default, array('location' => 'Local/Private IP'));
    }

    $geo_url = 'http://ip-api.com/json/' . $ip;
    $response = wp_remote_get($geo_url, array('timeout' => 3));

    if (is_wp_error($response)) {
        return array_merge($default, array('location' => 'Geo API Error'));
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (is_array($data) && ($data['status'] ?? '') === 'success') {
        $city = $data['city'] ?? '';
        $state = $data['regionName'] ?? '';
        $country = $data['country'] ?? '';

        $parts = array_filter(array($city, $state, $country));
        $location = !empty($parts) ? implode(', ', $parts) : 'Unknown Location';
        
        return array(
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'location' => $location
        );
    }

    return array_merge($default, array('location' => 'Location Not Found'));
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

    // Extract conversational form parameters with fallback to legacy keys (role/salary/experience)
    $language = sanitize_text_field($params['language'] ?? '');
    $segment = sanitize_text_field($params['segment'] ?? $params['role'] ?? '');
    $motivation = sanitize_text_field($params['motivation'] ?? $params['salary'] ?? '');
    $background = sanitize_text_field($params['background'] ?? $params['experience'] ?? '');

    // Normalize experience level to human-friendly Exp Level
    $exp_mapping = array(
        'none' => 'Fresher',
        'fresher' => 'Fresher',
        'hr_only' => 'Experienced',
        'ebs' => 'Experienced',
        'fusion' => 'Experienced',
        'pro' => 'Experienced',
        'hr' => 'Experienced',
        'owner' => 'Recruitment / Hiring Manager',
        'Not Provided' => 'Not Provided'
    );
    
    // Map raw experience if available, otherwise check segment
    $normalized_exp = $exp_mapping[$background] ?? $exp_mapping[$segment] ?? $experience;
    if (empty($normalized_exp) || $normalized_exp === 'Not Provided') {
        $normalized_exp = 'Fresher'; // default fallback
    }
    $experience = $normalized_exp;

    // Build comprehensive remarks text summarizing conversational choices
    $remarks_parts = array();
    if (!empty($language) && $language !== 'Not Provided') {
        $remarks_parts[] = "Language: " . $language;
    }
    if (!empty($segment) && $segment !== 'Not Provided') {
        $segment_labels = array(
            'fresher' => 'Fresher/Student (New to HR/IT)',
            'pro' => 'IT/ERP Professional (Switching to HCM)',
            'hr' => 'HR Professional (Upskilling)',
            'owner' => 'Recruitment / Hiring Manager'
        );
        $segment_val = $segment_labels[$segment] ?? $segment;
        $remarks_parts[] = "Segment: " . $segment_val;
    }
    if (!empty($motivation) && $motivation !== 'Not Provided') {
        $motivation_labels = array(
            'job' => 'Land my first job in HCM',
            'switch' => 'Switch careers / get a higher package',
            'cert' => 'Get certified for my current role',
            'team' => 'Upskill for better career growth'
        );
        $motivation_val = $motivation_labels[$motivation] ?? $motivation;
        $remarks_parts[] = "Goal: " . $motivation_val;
    }
    if (!empty($background) && $background !== 'Not Provided') {
        $background_labels = array(
            'none' => 'None - starting fresh',
            'hr_only' => 'Some HR experience, no Oracle',
            'ebs' => 'Used Oracle EBS or another ERP',
            'fusion' => 'Already work on Fusion HCM'
        );
        $background_val = $background_labels[$background] ?? $background;
        $remarks_parts[] = "Background: " . $background_val;
    }

    // Default remarks if empty
    if (empty($remarks_parts)) {
        $remarks_text = "Oracle Fusion HCM Course Lead";
    } else {
        $remarks_text = implode(" | ", $remarks_parts);
    }

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

    $geo_data = techleadsit_get_ip_geo_data($user_ip);
    $ip_location = $geo_data['location'];
    $city = $geo_data['city'];
    $state = $geo_data['state'];
    $country = $geo_data['country'];

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
    $fb_ad = sanitize_text_field($params['fb_ad'] ?? ($params['ad_name'] ?? ''));
    $fb_campaign = sanitize_text_field($params['fb_campaign'] ?? ($params['campaign_name'] ?? ''));
    $fb_adset_name = sanitize_text_field($params['fb_adset_name'] ?? ($params['adset_name'] ?? ''));
    $fb_adset_id = sanitize_text_field($params['fb_adset_id'] ?? ($params['adset_id'] ?? ''));
    $fb_lead_id = sanitize_text_field($params['fb_lead_id'] ?? ($params['lead_id'] ?? ''));
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
    $api_key = (defined('TELECRM_API_KEY') && !empty(TELECRM_API_KEY)) ? TELECRM_API_KEY : '68ca5820ff2a2eda16382e4a'; 
    $telecrm_api_url = 'https://next.telecrm.in/api/b1/enterprise/' . $api_key . '/autoupdatelead'; 

    // Get current date/time in Indian Standard Time (IST)
    $date_ist = new DateTime("now", new DateTimeZone("Asia/Kolkata"));
    $lead_date = $date_ist->format("Y-m-d H:i:s");

    // Humanize profile segment and goal values for clean CRM records
    $segment_map = array(
        'grad' => 'Student / Fresh Graduate',
        'pro' => 'Working Professional',
        'career_gap' => 'Career Gap / Returning to Tech',
        'owner' => 'Recruitment / Hiring Manager'
    );
    $goal_map = array(
        'job' => 'Land first job in Oracle Fusion HCM',
        'switch' => 'Switch careers / get a higher package',
        'cert' => 'Get certified for current role',
        'team' => 'Upskill for better career growth'
    );
    $human_profile = $segment_map[$role] ?? ($segment_map[$params['segment'] ?? ''] ?? $role);
    $human_goal = $goal_map[$salary] ?? ($goal_map[$params['motivation'] ?? ''] ?? $salary);
    if (empty($human_profile)) $human_profile = 'Working Professional';
    if (empty($human_goal)) $human_goal = 'Career switch to Oracle Fusion HCM';
    if (empty($call_time)) $call_time = 'Anytime / Call as soon as possible';

    // Format clean professional remarks
    $formatted_remarks = "Preferred Call Time: " . $call_time . " | Profile: " . $human_profile . " | Goal: " . $human_goal . " | Language: " . ($params['language'] ?? 'English');

    $final_source = !empty($utm_source) ? $utm_source : 'Website - Direct';
    $final_course = !empty($scm_year) ? $scm_year : 'Oracle Fusion HCM';
    $final_city = !empty($city) ? $city : (!empty($location) ? $location : 'Hyderabad');
    $final_state = !empty($state) ? $state : 'Telangana';

    // Build the payload matching exact TeleCRM field labels and system keys
    $payload = array(
        'fields' => array(
            'name' => $name,
            'phone' => (strpos($phone, '+') === 0) ? $phone : '+91' . $phone,
            'email' => $email,
            'role' => $human_profile,
            'salary' => $human_goal,
            'experience' => $human_profile,
            
            // Exact TeleCRM Field Labels (from user dashboard)
            'Course Name' => $final_course,
            'Course Name 2' => $final_course,
            'course_name' => $final_course,
            'coursename' => $final_course,
            'course' => $final_course,
            'scm_year' => $final_course,
            'scmyear' => $final_course,
            
            'Lead Source' => $final_source,
            'lead_source' => $final_source,
            'leadsource' => $final_source,
            'source' => $final_source,
            'utmsource' => $final_source,
            'utm_source' => $final_source,
            
            'Lead date' => $lead_date,
            'lead_date' => $lead_date,
            'leaddate' => $lead_date,
            'leadDate' => $lead_date,
            'date' => $lead_date,
            'Date' => $lead_date,
            
            'Your preferred time to call' => $call_time,
            'your_preferred_time_to_call' => $call_time,
            'preferred_call_time' => $call_time,
            'call_time' => $call_time,
            'call_slot' => $call_time,
            
            'City Name' => $final_city,
            'city_name' => $final_city,
            'cityname' => $final_city,
            'city' => $final_city,
            'location' => $final_city,
            'State Name' => $final_state,
            'state_name' => $final_state,
            'statename' => $final_state,
            'state' => $final_state,
            'country' => $country,
            'ipaddress' => $user_ip,
            'iplocation' => $ip_location,
            
            'Mode of Training' => 'Online Live Interactive Batch',
            'mode_of_training' => 'Online Live Interactive Batch',
            'Exp Level' => $human_profile,
            'exp_level' => $human_profile,
            'explevel' => $human_profile,
            
            'Remarks' => $formatted_remarks,
            'remarks' => $formatted_remarks,
            'description' => $formatted_remarks,
            'comments' => $formatted_remarks,
            'notes' => $formatted_remarks,
            
            // Dedicated Facebook Ads Fields (exact TeleCRM dashboard labels)
            'Facebook Ad' => $fb_ad ?: ((stripos($final_source, 'facebook') !== false || !empty($fbclid)) ? $utm_content : ''),
            'Facebook Campaign' => $fb_campaign ?: ((stripos($final_source, 'facebook') !== false || !empty($fbclid)) ? $utm_campaign : ''),
            'Facebook Ad set Name' => $fb_adset_name ?: ((stripos($final_source, 'facebook') !== false || !empty($fbclid)) ? $utm_adgroup : ''),
            'Facebook Ad set ID' => $fb_adset_id,
            'Facebook Lead ID' => $fb_lead_id,

            // Tracking fields
            'landingpage' => $landing_page ?: ($params['landing_page'] ?? ''),
            'landing_page' => $landing_page ?: ($params['landing_page'] ?? ''),
            'referrer' => $referrer ?: ($params['referrer'] ?? 'Direct'),
            'utmmedium' => $utm_medium ?: ($params['utm_medium'] ?? ''),
            'utm_medium' => $utm_medium ?: ($params['utm_medium'] ?? ''),
            'utmcampaign' => $utm_campaign ?: ($params['utm_campaign'] ?? ''),
            'utm_campaign' => $utm_campaign ?: ($params['utm_campaign'] ?? ''),
            'utmadgroup' => $utm_adgroup ?: ($params['utm_adgroup'] ?? ''),
            'utm_adgroup' => $utm_adgroup ?: ($params['utm_adgroup'] ?? ''),
            'utmterm' => $utm_term ?: ($params['utm_term'] ?? ''),
            'utm_term' => $utm_term ?: ($params['utm_term'] ?? ''),
            'utmcontent' => $utm_content ?: ($params['utm_content'] ?? ''),
            'utm_content' => $utm_content ?: ($params['utm_content'] ?? ''),
            'gclid' => $gclid ?: ($params['gclid'] ?? ''),
            'gbraid' => $gbraid ?: ($params['gbraid'] ?? ''),
            'wbraid' => $wbraid ?: ($params['wbraid'] ?? ''),
            'fbclid' => $fbclid ?: ($params['fbclid'] ?? ''),
            'fbp' => $fbp ?: ($params['fbp'] ?? ''),
            'fbc' => $fbc ?: ($params['fbc'] ?? ''),
            'gaclientid' => $ga_client_id ?: ($params['ga_client_id'] ?? ''),
            'ga_client_id' => $ga_client_id ?: ($params['ga_client_id'] ?? ''),
            'sessionid' => $session_id ?: ($params['session_id'] ?? ''),
            'session_id' => $session_id ?: ($params['session_id'] ?? '')
        ),
        'actions' => array(
            array(
                'type' => 'SYSTEM_NOTE',
                'text' => "Location: " . $location . "\n" .
                          "IP Address: " . $user_ip . "\n" .
                          "IP Location: " . $ip_location . "\n" .
                          "Course Name: " . $scm_year . "\n" .
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
        return new WP_REST_Response(array('success' => false, 'message' => 'CRM submission error: ' . $response->get_error_message() . ' (URL: ' . $telecrm_api_url . ')'), 500);
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



// ==========================================================================
// ==========================================================================
// MULTI-COURSE ARCHITECTURE & WORDPRESS ADMIN CONTROLS
// ==========================================================================

function techleadsit_get_courses_registry() {
    return array(
        'hcm' => array(
            'id' => 'hcm',
            'name' => 'Oracle Fusion HCM',
            'icon' => 'dashicons-groups',
            'badge_color' => '#8b5cf6',
            'default_template' => 'f-hcm-course/index.html',
            'slugs' => array(
                'oracle-fusion-hcm-training' => 'f-hcm-course/index.html',
                'f-hcm-course' => 'f-hcm-course/index.html',
                'mb-hr-to' => 'f-hcm-course/index.html',
                'oracle-fusion-hcm-training-in-hyderabad' => 'f-hcm-course/index.html',
                'oracle-fusion-hcm-training-in-bangalore' => 'f-hcm-course/index.html',
                'oracle-fusion-hcm-online-training' => 'f-hcm-course/index.html',
                'oracle-fusion-hcm-course' => 'f-hcm-course/index.html',
                'oracle-fusion-hcm-functional-training' => 'f-hcm-course/index.html',
                'oracle-fusion-hcm-modules-training' => 'f-hcm-course/index.html',
                'oracle-fusion-hcm-training-in-pune' => 'f-hcm-course/index.html',
                'oracle-fusion-hcm-training-in-chennai' => 'f-hcm-course/index.html',
            ),
            'defaults' => array(
                'countdown_enabled' => 1,
                'countdown_text' => 'Next HCM Batch starts soon - Only 4 seats left!',
                'countdown_target_datetime' => '2026-09-23T20:30',
                'countdown_seats_count' => '4',
                'batch_section_enabled' => 1,
                'batch_title' => 'Next Batch Starts Soon',
                'batch_subtitle' => 'Limited seats available - Reserve your spot today',
                'batch_start_date' => '23rd Sep, 26',
                'batch_timing' => '8:30 PM to 9:30 PM',
                'batch_pills' => 'Online, Weekday (TTS)',
                'batch_seats_left' => '10',
                'base_visitor_floor' => '1840'
            )
        ),
        'scm' => array(
            'id' => 'scm',
            'name' => 'Oracle Fusion SCM',
            'icon' => 'dashicons-cart',
            'badge_color' => '#0284c7',
            'default_template' => 'oracle-fusion-scm-training/index.html',
            'slugs' => array(
                'oracle-fusion-scm-training' => 'oracle-fusion-scm-training/index.html',
                'scm-demo' => 'scm-demo/index.html',
                'scm-demo-v2' => 'scm-demo-v2/index.html',
                'rise-v1' => 'rise-v1/index.html',
                'rise-v2' => 'rise-v2/index.html',
                'rise-form-16465496' => 'rise-form-16465496/index.html',
            ),
            'defaults' => array(
                'countdown_enabled' => 1,
                'countdown_text' => 'Next SCM Batch starts soon - Only 6 seats left!',
                'countdown_target_datetime' => '2026-09-28T19:00',
                'countdown_seats_count' => '6',
                'batch_section_enabled' => 1,
                'batch_title' => 'Next Batch Starts Soon',
                'batch_subtitle' => 'Live Interactive Training with Real-Time Implementation',
                'batch_start_date' => '28th Sep, 26',
                'batch_timing' => '7:00 PM to 8:30 PM',
                'batch_pills' => 'Online Live, Weekend / Weekday',
                'batch_seats_left' => '8'
            )
        ),
        'sql' => array(
            'id' => 'sql',
            'name' => 'Free SQL Training',
            'icon' => 'dashicons-database',
            'badge_color' => '#10b981',
            'default_template' => 'free-sql-training-44819/index.html',
            'slugs' => array(
                'free-sql-training-44819' => 'free-sql-training-44819/index.html'
            ),
            'defaults' => array(
                'countdown_enabled' => 1,
                'countdown_text' => 'Free SQL Masterclass Starting Soon!',
                'countdown_target_datetime' => '2026-09-25T18:00',
                'countdown_seats_count' => '15',
                'batch_section_enabled' => 1,
                'batch_title' => 'Next Masterclass Starts Soon',
                'batch_subtitle' => 'Zero-cost foundation class for Oracle Cloud aspirants',
                'batch_start_date' => '25th Sep, 26',
                'batch_timing' => '6:00 PM to 7:30 PM',
                'batch_pills' => '100% Free, Online Live',
                'batch_seats_left' => '25'
            )
        ),
        'financials' => array(
            'id' => 'financials',
            'name' => 'Oracle Fusion Financials',
            'icon' => 'dashicons-money-alt',
            'badge_color' => '#f59e0b',
            'default_template' => 'f-hcm-course/index.html',
            'slugs' => array(
                'oracle-fusion-financials-training' => 'f-hcm-course/index.html'
            ),
            'defaults' => array(
                'countdown_enabled' => 1,
                'countdown_text' => 'Next Financials Batch starts soon!',
                'countdown_target_datetime' => '2026-10-05T20:00',
                'countdown_seats_count' => '5',
                'batch_section_enabled' => 1,
                'batch_title' => 'Next Batch Starts Soon',
                'batch_subtitle' => 'GL, AP, AR, FA, Cash Management & Tax masterclass',
                'batch_start_date' => '5th Oct, 26',
                'batch_timing' => '8:00 PM to 9:30 PM',
                'batch_pills' => 'Online Live, Project-Based',
                'batch_seats_left' => '12'
            )
        )
    );
}

// Find matching course for a given slug
function techleadsit_find_course_for_slug($slug) {
    $courses = techleadsit_get_courses_registry();
    foreach ($courses as $course_id => $course) {
        if (isset($course['slugs'][$slug])) {
            return $course_id;
        }
    }
    return 'hcm'; // Default fallback
}

// Get options for a specific course
function techleadsit_get_course_options($course_id) {
    $courses = techleadsit_get_courses_registry();
    $defaults = $courses[$course_id]['defaults'] ?? array();
    $saved = get_option('techleadsit_course_settings_' . $course_id, array());
    return wp_parse_args($saved, $defaults);
}

add_action('admin_menu', 'techleadsit_add_admin_menu');
function techleadsit_add_admin_menu() {
    add_menu_page(
        'Landing Page Settings',
        'Landing Page Settings',
        'manage_options',
        'techleadsit-batch-settings',
        'techleadsit_render_multi_course_settings_page',
        'dashicons-welcome-learn-more',
        28
    );
    add_options_page(
        'Landing Page Settings',
        'Landing Page Settings',
        'manage_options',
        'techleadsit-batch-settings',
        'techleadsit_render_multi_course_settings_page'
    );
}

add_action('admin_init', 'techleadsit_register_multi_course_settings');
function techleadsit_register_multi_course_settings() {
    $courses = techleadsit_get_courses_registry();
    foreach ($courses as $course_id => $course) {
        register_setting('techleadsit_course_group_' . $course_id, 'techleadsit_course_settings_' . $course_id, 'techleadsit_sanitize_course_settings');
    }
}

function techleadsit_sanitize_course_settings($input) {
    $sanitized = array();
    $sanitized['countdown_enabled'] = isset($input['countdown_enabled']) ? 1 : 0;
    $sanitized['countdown_text'] = sanitize_text_field($input['countdown_text'] ?? '');
    $sanitized['countdown_target_datetime'] = sanitize_text_field($input['countdown_target_datetime'] ?? '');
    $sanitized['countdown_seats_count'] = sanitize_text_field($input['countdown_seats_count'] ?? '');

    $sanitized['batch_section_enabled'] = isset($input['batch_section_enabled']) ? 1 : 0;
    $sanitized['batch_title'] = sanitize_text_field($input['batch_title'] ?? '');
    $sanitized['batch_subtitle'] = sanitize_text_field($input['batch_subtitle'] ?? '');
    $sanitized['batch_start_date'] = sanitize_text_field($input['batch_start_date'] ?? '');
    $sanitized['batch_timing'] = sanitize_text_field($input['batch_timing'] ?? '');
    $sanitized['batch_pills'] = sanitize_text_field($input['batch_pills'] ?? '');
    $sanitized['batch_seats_left'] = sanitize_text_field($input['batch_seats_left'] ?? '');
    $sanitized['base_visitor_floor'] = sanitize_text_field($input['base_visitor_floor'] ?? '1840');

    return $sanitized;
}

function techleadsit_render_multi_course_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $courses = techleadsit_get_courses_registry();
    $active_tab = isset($_GET['tab']) && isset($courses[$_GET['tab']]) ? sanitize_key($_GET['tab']) : 'hcm';
    $current_course = $courses[$active_tab];
    $opts = techleadsit_get_course_options($active_tab);
    ?>
    <div class="wrap" style="max-width: 1000px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
            <div>
                <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-welcome-learn-more" style="font-size: 28px; width: 28px; height: 28px; color: #4f46e5;"></span>
                    TechLeadsIT Campaign Strategy & Landing Pages
                </h1>
                <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">Manage batch schedules, countdown timers, and launch fresh marketing campaigns across all courses.</p>
            </div>
            <div style="display: flex; gap: 8px;">
                <span style="background: #e0e7ff; color: #4338ca; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                    <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block;"></span> Dynamic Marketing Engine Active
                </span>
            </div>
        </div>

        <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']) : ?>
            <div class="notice notice-success is-dismissible" style="border-left-color: #10b981; margin-bottom: 20px;">
                <p><strong>Campaign Updated!</strong> Landing pages for <strong><?php echo esc_html($current_course['name']); ?></strong> are now broadcasting your updated schedule.</p>
            </div>
        <?php endif; ?>

        <!-- Course Category Tabs -->
        <nav class="nav-tab-wrapper" style="margin-bottom: 20px; border-bottom: 2px solid #cbd5e1;">
            <?php foreach ($courses as $cid => $cdata) : 
                $is_active = ($cid === $active_tab);
                $tab_url = add_query_arg(array('page' => 'techleadsit-batch-settings', 'tab' => $cid), admin_url('admin.php'));
            ?>
                <a href="<?php echo esc_url($tab_url); ?>" class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>" style="display: inline-flex; align-items: center; gap: 8px; font-size: 14px; font-weight: <?php echo $is_active ? '700' : '600'; ?>; padding: 9px 18px; <?php echo $is_active ? 'border-top: 3px solid ' . esc_attr($cdata['badge_color']) . '; border-bottom: 1px solid #fff; background: #fff;' : 'background: #f8fafc; color: #64748b;'; ?>">
                    <span class="dashicons <?php echo esc_attr($cdata['icon']); ?>" style="color: <?php echo esc_attr($cdata['badge_color']); ?>; font-size: 18px; width: 18px; height: 18px;"></span>
                    <?php echo esc_html($cdata['name']); ?>
                    <span style="background: <?php echo $is_active ? esc_attr($cdata['badge_color']) : '#e2e8f0'; ?>; color: <?php echo $is_active ? '#fff' : '#475569'; ?>; border-radius: 12px; padding: 1px 7px; font-size: 11px; font-weight: 700;">
                        <?php echo count($cdata['slugs']); ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- 🚀 Marketing Strategy Quick Presets Panel -->
        <div style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color: #fff; border-radius: 12px; padding: 18px 24px; margin-bottom: 24px; box-shadow: 0 4px 15px rgba(49, 46, 129, 0.2);">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 12px;">
                <div>
                    <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #facc15; display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-superhero-alt" style="font-size: 18px; width: 18px; height: 18px;"></span>
                        1-Click Marketing Strategy Presets (Launch or Reset Fast)
                    </h3>
                    <p style="margin: 3px 0 0; font-size: 12.5px; color: #c7d2fe;">Click any preset to instantly populate the form below, then click Save.</p>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="button" onclick="applyCampaignPreset('sprint3d')" style="background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.3); font-weight: 700; font-size: 12px; border-radius: 6px; padding: 4px 12px; cursor: pointer;">
                    ⚡ 3-Day Urgency Sprint
                </button>
                <button type="button" class="button" onclick="applyCampaignPreset('weekend')" style="background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.3); font-weight: 700; font-size: 12px; border-radius: 6px; padding: 4px 12px; cursor: pointer;">
                    🎯 Next Weekend Cohort
                </button>
                <button type="button" class="button" onclick="applyCampaignPreset('upcoming_weekday')" style="background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.3); font-weight: 700; font-size: 12px; border-radius: 6px; padding: 4px 12px; cursor: pointer;">
                    🗓️ Upcoming Weekday Batch (Auto-Calc)
                </button>
                <button type="button" class="button" onclick="applyCampaignPreset('resetEvergreen')" style="background: #ef4444; color: #fff; border: 1px solid #dc2626; font-weight: 700; font-size: 12px; border-radius: 6px; padding: 4px 12px; cursor: pointer;">
                    🔄 Reset to Clean Default
                </button>
            </div>
        </div>

        <form method="post" action="options.php" id="techleadsitForm">
            <?php settings_fields('techleadsit_course_group_' . $active_tab); ?>

            <!-- Section 1: Sticky Countdown Bar -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px; margin-bottom: 18px;">
                    <div>
                        <h2 style="font-size: 17px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                            ⚡ Top Sticky Countdown Bar (<?php echo esc_html($current_course['name']); ?>)
                        </h2>
                        <p style="margin: 4px 0 0; font-size: 12.5px; color: #64748b;">Broadcasts live countdown urgency across all <?php echo count($current_course['slugs']); ?> landing pages in this course.</p>
                    </div>
                    <label style="display: inline-flex; align-items: center; cursor: pointer; font-weight: 700; color: #1e293b; font-size: 14px; background: #f8fafc; padding: 6px 14px; border-radius: 8px; border: 1px solid #cbd5e1;">
                        <input type="checkbox" id="field_countdown_enabled" name="techleadsit_course_settings_<?php echo esc_attr($active_tab); ?>[countdown_enabled]" value="1" <?php checked(1, (int)$opts['countdown_enabled']); ?> style="margin-right: 8px;">
                        Enable Top Bar
                    </label>
                </div>

                <table class="form-table" style="margin-top: 0;">
                    <tr>
                        <th scope="row" style="width: 220px; font-weight: 600; color: #475569;">Target Demo Date & Time</th>
                        <td>
                            <input type="datetime-local" id="field_countdown_target_datetime" name="techleadsit_course_settings_<?php echo esc_attr($active_tab); ?>[countdown_target_datetime]" value="<?php echo esc_attr($opts['countdown_target_datetime']); ?>" class="regular-text" style="padding: 7px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 600;">
                            <p class="description" style="color: #64748b; margin-top: 5px;">Countdown ticks down to this exact datetime (e.g. <code>2026-09-23 20:30</code>).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" style="font-weight: 600; color: #475569;">Announcement Text</th>
                        <td>
                            <input type="text" id="field_countdown_text" name="techleadsit_course_settings_<?php echo esc_attr($active_tab); ?>[countdown_text]" value="<?php echo esc_attr($opts['countdown_text']); ?>" class="large-text" style="padding: 7px 12px; border-radius: 6px; border: 1px solid #cbd5e1;">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" style="font-weight: 600; color: #475569;">Seats Left Number</th>
                        <td>
                            <input type="text" id="field_countdown_seats_count" name="techleadsit_course_settings_<?php echo esc_attr($active_tab); ?>[countdown_seats_count]" value="<?php echo esc_attr($opts['countdown_seats_count']); ?>" style="width: 90px; padding: 7px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 700;">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Section 2: Next Batch Schedule Section -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px; margin-bottom: 18px;">
                    <div>
                        <h2 style="font-size: 17px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                            🎓 "Next Batch Starts Soon" Section (<?php echo esc_html($current_course['name']); ?>)
                        </h2>
                        <p style="margin: 4px 0 0; font-size: 12.5px; color: #64748b;">Controls the royal purple batch schedule section above "How Our Training Program Works".</p>
                    </div>
                    <label style="display: inline-flex; align-items: center; cursor: pointer; font-weight: 700; color: #1e293b; font-size: 14px; background: #f8fafc; padding: 6px 14px; border-radius: 8px; border: 1px solid #cbd5e1;">
                        <input type="checkbox" id="field_batch_section_enabled" name="techleadsit_course_settings_<?php echo esc_attr($active_tab); ?>[batch_section_enabled]" value="1" <?php checked(1, (int)$opts['batch_section_enabled']); ?> style="margin-right: 8px;">
                        Show Batch Section
                    </label>
                </div>

                <table class="form-table" style="margin-top: 0;">
                    <tr>
                        <th scope="row" style="width: 220px; font-weight: 600; color: #475569;">Section Headline</th>
                        <td>
                            <input type="text" id="field_batch_title" name="techleadsit_course_settings_<?php echo esc_attr($active_tab); ?>[batch_title]" value="<?php echo esc_attr($opts['batch_title']); ?>" class="large-text" style="padding: 7px 12px; border-radius: 6px; border: 1px solid #cbd5e1;">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" style="font-weight: 600; color: #475569;">Section Subtitle</th>
                        <td>
                            <input type="text" id="field_batch_subtitle" name="techleadsit_course_settings_<?php echo esc_attr($active_tab); ?>[batch_subtitle]" value="<?php echo esc_attr($opts['batch_subtitle']); ?>" class="large-text" style="padding: 7px 12px; border-radius: 6px; border: 1px solid #cbd5e1;">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" style="font-weight: 600; color: #475569;">Batch Start Date Badge</th>
                        <td>
                            <input type="text" id="field_batch_start_date" name="techleadsit_course_settings_<?php echo esc_attr($active_tab); ?>[batch_start_date]" value="<?php echo esc_attr($opts['batch_start_date']); ?>" class="regular-text" style="padding: 7px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 700;" placeholder="23rd Sep, 26">
                            <p class="description" style="color: #64748b; margin-top: 5px;">Displayed in the purple date pill box (e.g. <code>23rd Sep, 26</code>).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" style="font-weight: 600; color: #475569;">Batch Timing</th>
                        <td>
                            <input type="text" id="field_batch_timing" name="techleadsit_course_settings_<?php echo esc_attr($active_tab); ?>[batch_timing]" value="<?php echo esc_attr($opts['batch_timing']); ?>" class="regular-text" style="padding: 7px 12px; border-radius: 6px; border: 1px solid #cbd5e1;" placeholder="8:30 PM to 9:30 PM">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" style="font-weight: 600; color: #475569;">Mode Badges (Comma-separated)</th>
                        <td>
                            <input type="text" id="field_batch_pills" name="techleadsit_course_settings_<?php echo esc_attr($active_tab); ?>[batch_pills]" value="<?php echo esc_attr($opts['batch_pills']); ?>" class="large-text" style="padding: 7px 12px; border-radius: 6px; border: 1px solid #cbd5e1;" placeholder="Online, Weekday (TTS)">
                        </td>
                    </tr>
                                        <tr>
                        <th scope="row" style="font-weight: 600; color: #475569;">Live Visitor Counter Base Floor</th>
                        <td>
                            <input type="text" id="field_base_visitor_floor" name="techleadsit_course_settings_<?php echo esc_attr($active_tab); ?>[base_visitor_floor]" value="<?php echo esc_attr($opts['base_visitor_floor'] ?? '1840'); ?>" style="width: 120px; padding: 7px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 700;" placeholder="1840">
                            <p class="description" style="color: #64748b; margin-top: 5px;">Starting baseline for live visitor count. Increments on every real visit and never decreases.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" style="font-weight: 600; color: #475569;">Seats Left Count</th>
                        <td>
                            <input type="text" id="field_batch_seats_left" name="techleadsit_course_settings_<?php echo esc_attr($active_tab); ?>[batch_seats_left]" value="<?php echo esc_attr($opts['batch_seats_left']); ?>" style="width: 90px; padding: 7px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 700;" placeholder="10">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Section 3: Mapped Landing Pages in this Course -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                <h3 style="font-size: 15px; font-weight: 700; color: #334155; margin: 0 0 12px 0; display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-admin-links" style="color: #6366f1;"></span>
                    Connected Landing Pages for <?php echo esc_html($current_course['name']); ?> (<?php echo count($current_course['slugs']); ?> URLs auto-updated)
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 8px;">
                    <?php foreach ($current_course['slugs'] as $slug => $file) : 
                        $url = home_url('/' . $slug);
                    ?>
                        <div style="background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; display: flex; align-items: center; justify-content: space-between; font-size: 12.5px;">
                            <span style="font-family: monospace; color: #0f172a; font-weight: 600;">/<?php echo esc_html($slug); ?></span>
                            <a href="<?php echo esc_url($url); ?>" target="_blank" style="color: #4f46e5; text-decoration: none; font-weight: 700; font-size: 11.5px; display: flex; align-items: center; gap: 3px;">
                                Preview ↗
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="position: sticky; bottom: 20px; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); padding: 14px 20px; border-radius: 10px; border: 1px solid #cbd5e1; box-shadow: 0 10px 25px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between;">
                <span style="font-size: 13px; color: #475569; font-weight: 600;">
                    Changes will apply to all <strong><?php echo count($current_course['slugs']); ?> pages</strong> under <strong><?php echo esc_html($current_course['name']); ?></strong>.
                </span>
                <?php submit_button('Save ' . esc_html($current_course['name']) . ' Settings', 'primary', 'submit', false, array('style' => 'background: #4f46e5; border-color: #4338ca; padding: 8px 24px; font-weight: 800; font-size: 14px; border-radius: 8px; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25); cursor: pointer;')); ?>
            </div>
        </form>

        <script>
        function applyCampaignPreset(type) {
            const now = new Date();
            function pad(n) { return String(n).padStart(2, '0'); }

            if (type === 'sprint3d') {
                const target = new Date(now.getTime() + 3 * 24 * 60 * 60 * 1000);
                target.setHours(20, 30, 0, 0);
                const isoStr = target.getFullYear() + '-' + pad(target.getMonth()+1) + '-' + pad(target.getDate()) + 'T' + pad(target.getHours()) + ':' + pad(target.getMinutes());
                
                document.getElementById('field_countdown_enabled').checked = true;
                document.getElementById('field_countdown_target_datetime').value = isoStr;
                document.getElementById('field_countdown_text').value = 'Next Batch starts in 3 Days - Only 4 seats left!';
                document.getElementById('field_countdown_seats_count').value = '4';

                document.getElementById('field_batch_section_enabled').checked = true;
                document.getElementById('field_batch_title').value = 'Next Live Sprint Batch Starts Soon';
                document.getElementById('field_batch_subtitle').value = 'High-velocity fast track batch with real-time lab setup';
                document.getElementById('field_batch_start_date').value = pad(target.getDate()) + 'th ' + target.toLocaleString('default', { month: 'short' }) + ', 26';
                document.getElementById('field_batch_timing').value = '8:30 PM to 9:30 PM';
                document.getElementById('field_batch_pills').value = 'Online Live, Fast Track';
                document.getElementById('field_batch_seats_left').value = '4';
            } 
            else if (type === 'weekend') {
                const daysUntilSat = (6 - now.getDay() + 7) % 7 || 7;
                const target = new Date(now.getTime() + daysUntilSat * 24 * 60 * 60 * 1000);
                target.setHours(10, 0, 0, 0);
                const isoStr = target.getFullYear() + '-' + pad(target.getMonth()+1) + '-' + pad(target.getDate()) + 'T' + pad(target.getHours()) + ':' + pad(target.getMinutes());

                document.getElementById('field_countdown_enabled').checked = true;
                document.getElementById('field_countdown_target_datetime').value = isoStr;
                document.getElementById('field_countdown_text').value = 'Weekend Masterclass Starts This Saturday - Limited Slots!';
                document.getElementById('field_countdown_seats_count').value = '6';

                document.getElementById('field_batch_section_enabled').checked = true;
                document.getElementById('field_batch_title').value = 'Upcoming Weekend Batch Starts Soon';
                document.getElementById('field_batch_subtitle').value = 'Convenient weekend training designed for working professionals';
                document.getElementById('field_batch_start_date').value = pad(target.getDate()) + 'th ' + target.toLocaleString('default', { month: 'short' }) + ', 26';
                document.getElementById('field_batch_timing').value = 'Sat & Sun 10:00 AM to 1:00 PM';
                document.getElementById('field_batch_pills').value = 'Weekend Only, Live Online';
                document.getElementById('field_batch_seats_left').value = '6';
            }
            else if (type === 'upcoming_weekday') {
                // Calculate the next upcoming Monday or Wednesday dynamically
                const day = now.getDay();
                let daysToAdd = 1;
                if (day === 1) daysToAdd = 2; // Monday -> Wednesday
                else if (day === 2) daysToAdd = 1; // Tuesday -> Wednesday
                else if (day === 3) daysToAdd = 5; // Wednesday -> next Monday
                else if (day === 4) daysToAdd = 4; // Thursday -> next Monday
                else if (day === 5) daysToAdd = 3; // Friday -> next Monday
                else if (day === 6) daysToAdd = 2; // Saturday -> next Monday
                else if (day === 0) daysToAdd = 1; // Sunday -> Monday

                const target = new Date(now.getTime() + daysToAdd * 24 * 60 * 60 * 1000);
                target.setHours(20, 30, 0, 0);
                const isoStr = target.getFullYear() + '-' + pad(target.getMonth()+1) + '-' + pad(target.getDate()) + 'T' + pad(target.getHours()) + ':' + pad(target.getMinutes());

                document.getElementById('field_countdown_enabled').checked = true;
                document.getElementById('field_countdown_target_datetime').value = isoStr;
                document.getElementById('field_countdown_text').value = 'Next Batch starts soon - Only 4 seats left!';
                document.getElementById('field_countdown_seats_count').value = '4';

                document.getElementById('field_batch_section_enabled').checked = true;
                document.getElementById('field_batch_title').value = 'Next Batch Starts Soon';
                document.getElementById('field_batch_subtitle').value = 'Limited seats available - Reserve your spot today';
                document.getElementById('field_batch_start_date').value = pad(target.getDate()) + 'th ' + target.toLocaleString('default', { month: 'short' }) + ', ' + String(target.getFullYear()).slice(-2);
                document.getElementById('field_batch_timing').value = '8:30 PM to 9:30 PM';
                document.getElementById('field_batch_pills').value = 'Online, Weekday (TTS)';
                document.getElementById('field_batch_seats_left').value = '10';
            }
            else if (type === 'resetEvergreen') {
                document.getElementById('field_countdown_enabled').checked = true;
                document.getElementById('field_countdown_target_datetime').value = '2026-09-23T20:30';
                document.getElementById('field_countdown_text').value = 'Next Batch starts soon - Only 4 seats left!';
                document.getElementById('field_countdown_seats_count').value = '4';

                document.getElementById('field_batch_section_enabled').checked = true;
                document.getElementById('field_batch_title').value = 'Next Batch Starts Soon';
                document.getElementById('field_batch_subtitle').value = 'Limited seats available - Reserve your spot today';
                document.getElementById('field_batch_start_date').value = '23rd Sep, 26';
                document.getElementById('field_batch_timing').value = '8:30 PM to 9:30 PM';
                document.getElementById('field_batch_pills').value = 'Online, Weekday (TTS)';
                document.getElementById('field_batch_seats_left').value = '10';
            }

            // Visual pulse feedback
            const form = document.getElementById('techleadsitForm');
            form.style.transition = 'opacity 0.2s ease';
            form.style.opacity = '0.5';
            setTimeout(() => { form.style.opacity = '1'; }, 200);
        }
        </script>
    </div>
    <?php
}
