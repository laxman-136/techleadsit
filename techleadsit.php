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
                $html_content = str_replace('href="styles.css"', 'href="' . $plugin_url . 'styles.css?v=9.9"', $html_content);
                $html_content = str_replace('src="script.js"', 'src="' . $plugin_url . 'script.js?v=9.9"', $html_content);
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
            
            // Location fields
            'location' => $location,
            'city' => $city,
            'cityname' => $city,
            'city_name' => $city,
            'state' => $state,
            'statename' => $state,
            'state_name' => $state,
            'country' => $country,
            'ipaddress' => $user_ip,
            'iplocation' => $ip_location,
            
            // Course name fields
            'scm_year' => $scm_year,
            'scmyear' => $scm_year,
            'coursename' => $scm_year,
            'course_name' => $scm_year,
            'course' => $scm_year,
            
            // Experience level variations
            'explevel' => $experience,
            'exp_level' => $experience,
            'experiencelevel' => $experience,
            'experience_level' => $experience,
            
            // Remarks/Comments fields
            'remarks' => $remarks_text,
            'remark' => $remarks_text,
            'description' => $remarks_text,
            'comments' => $remarks_text,
            'comment' => $remarks_text,
            'notes' => $remarks_text,
            'note' => $remarks_text,
            'leaddescription' => $remarks_text,
            'lead_description' => $remarks_text,
            'remarks_text' => $remarks_text,
            'message' => $remarks_text,
            'messages' => $remarks_text,
            
            // Lead Source fields
            'source' => $utm_source,
            'leadsource' => $utm_source,
            'lead_source' => $utm_source,
            'utmsource' => $utm_source,
            
            // Date fields
            'date' => $lead_date,
            'leaddate' => $lead_date,
            'lead_date' => $lead_date,
            'courseenrollmentdate' => $lead_date,
            'course_enrollment_date' => $lead_date,
            
            // Tracking fields
            'fbp' => $fbp,
            'fbc' => $fbc,
            'gclid' => $gclid,
            'gbraid' => $gbraid,
            'wbraid' => $wbraid,
            'fbclid' => $fbclid,
            'ga_client_id' => $ga_client_id,
            'gaclient_id' => $ga_client_id,
            'gaclientid' => $ga_client_id,
            'session_id' => $session_id,
            'sessionid' => $session_id,
            'utm_source' => $utm_source,
            'utm_medium' => $utm_medium,
            'utmmedium' => $utm_medium,
            'utm_campaign' => $utm_campaign,
            'utmcampaign' => $utm_campaign,
            'utm_adgroup' => $utm_adgroup,
            'utmadgroup' => $utm_adgroup,
            'utm_term' => $utm_term,
            'utmterm' => $utm_term,
            'utm_content' => $utm_content,
            'utmcontent' => $utm_content,
            'landing_page' => $landing_page,
            'landingpage' => $landing_page,
            'referrer' => $referrer,
            'campaign' => $utm_campaign
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

