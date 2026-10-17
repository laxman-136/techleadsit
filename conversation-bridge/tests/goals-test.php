<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/goals.php';
$checks = 0;
function goal_check($condition, $label) {
    global $checks;
    $checks++;
    if (!$condition) { fwrite(STDERR, 'FAIL: ' . $label . PHP_EOL); exit(1); }
}
function visitor($text, $type = 'question') { return array('role' => 'user', 'content' => $text, 'type' => $type); }
function analyze_goal($texts, $model = '', $cleared = false) { return TLIT_CB_Goals::analyze(array_map('visitor', $texts), $model, $cleared); }
function goal_contains($result, $text) { return strpos($result['summary'], $text) !== false; }

$result = analyze_goal(array());
goal_check($result['status'] === 'insufficient' && $result['summary'] !== '', 'Empty conversation has an honest nonblank status');
goal_check(array_keys($result) === array('summary', 'status', 'source', 'evidence', 'version') && $result['version'] === TLIT_CB_Goals::VERSION, 'Stable analysis contract');
goal_check(analyze_goal(array('Hi', 'Thank you'))['status'] === 'insufficient', 'Greeting-only conversation does not invent intent');
$contact = array(visitor('Jobs Business', 'contact_name'), visitor('person@example.com', 'contact_email'), visitor('9876543210', 'contact_phone'), visitor('English', 'language'), array('role' => 'assistant', 'type' => 'question', 'content' => 'You want to start a business and get a job.'));
goal_check(TLIT_CB_Goals::analyze($contact)['status'] === 'insufficient', 'Contact fields and assistant replies never become evidence');
goal_check(TLIT_CB_Goals::analyze(array(array('role' => 'system', 'content' => 'I want a job')))['status'] === 'insufficient', 'System content ignored');
$result = analyze_goal(array('What is the fee?', 'Can I pay in instalments?'));
goal_check($result['status'] === 'interest' && goal_contains($result, 'fees and payment options'), 'Fees-only chat records course interest, not employment');
goal_check(!goal_contains($result, 'job'), 'Fees do not imply job goal');
goal_check(analyze_goal(array('What placement assistance is included?'))['status'] === 'interest', 'General placement question does not become personal ambition');
goal_check(analyze_goal(array('I want to know about job placement'))['status'] === 'interest', 'Want to know is not want a job');
goal_check(analyze_goal(array('I want to know what freelance marketing is'))['status'] === 'interest', 'Learning the definition of freelancing does not imply freelance ambition');
goal_check(analyze_goal(array('I already have a job. What are class timings?'))['status'] === 'interest', 'Existing job status does not invent job search');
$result = analyze_goal(array('Can I get a job after this course?'));
goal_check($result['status'] === 'inferred' && goal_contains($result, 'finding a digital marketing job'), 'Personal job outcome question is inferred');
$result = analyze_goal(array('I want to get my first marketing job'));
goal_check($result['status'] === 'stated' && goal_contains($result, 'Find a digital marketing job'), 'Explicit personal employment goal');
goal_check(analyze_goal(array('My brother wants a job. Is this course suitable?'))['status'] === 'interest', 'Third-party job wish not assigned to visitor');
goal_check(analyze_goal(array('My brother asked. Can I get a job after this course?'))['status'] === 'interest', 'Reported question stays third-party across sentence break');
goal_check(analyze_goal(array('My brother asked, can I get a job?'))['status'] === 'interest', 'Reported question stays third-party across comma');
goal_check(analyze_goal(array('My brother said: "I want a job. I want freelance clients."'))['status'] === 'interest', 'Multiple quoted first-person sentences never become visitor ambition');
goal_check(analyze_goal(array('I am asking for my brother.', 'Can I get a job after this course?'))['status'] === 'interest', 'Explicit third-party enquiry context survives message boundary');
goal_check(analyze_goal(array('I am asking for my brother.', 'For myself, I want a job'))['status'] === 'stated', 'Explicit own direction exits third-party context');
goal_check(analyze_goal(array('Can I get a job for my sister?'))['status'] === 'interest', 'Third-party purpose wins over first-person wording');
goal_check(analyze_goal(array('Hypothetically, can I get a job after this course?'))['status'] === 'interest', 'Hypothetical is not inferred goal');
goal_check(analyze_goal(array('What does "I want a job" mean?'))['status'] === 'interest', 'Quoted example does not become visitor intent');
goal_check(analyze_goal(array('If I wanted to get a job, would this help?'))['status'] === 'interest', 'Conditional scenario not treated as personal ambition');
goal_check(analyze_goal(array('Suppose I join. Can I get a job?'))['status'] === 'interest', 'Hypothetical context survives sentence splitting');
goal_check(analyze_goal(array('I want a job', 'My brother said. I want to grow my business.'))['status'] === 'stated' && goal_contains(analyze_goal(array('I want a job', 'My brother said. I want to grow my business.')), 'job'), 'Third-party quoted correction cannot override own established goal');
goal_check(analyze_goal(array("I don't want a job"))['status'] === 'interest', 'Negated goal never assigned');
$result = analyze_goal(array('I want a job', "I don't want a job anymore"));
goal_check($result['status'] === 'insufficient' && goal_contains($result, 'revised'), 'Explicit rejection clears earlier matching goal');
$result = analyze_goal(array('I want a job', 'I was not selected for a job yesterday'));
goal_check($result['status'] === 'stated' && goal_contains($result, 'Find a digital marketing job'), 'Job rejection does not mean rejecting employment goal');
$result = analyze_goal(array('I want a job', "I don't want a job, I want to grow my business"));
goal_check($result['status'] === 'stated' && goal_contains($result, 'grow their business'), 'Latest correction and replacement in same message win');
$result = analyze_goal(array('Can I get a digital marketing job after this course?', 'Actually I want to grow my business, not get a job.'));
goal_check($result['status'] === 'stated' && goal_contains($result, 'grow their business'), 'Positive business goal before contrastive negative job tail wins');
$result = analyze_goal(array('I want a job', 'I am not looking for work anymore'));
goal_check($result['status'] === 'insufficient', 'Explicit work-goal rejection clears job goal');
goal_check(analyze_goal(array('I want a job', 'Jobs are not for me'))['status'] === 'insufficient', 'Not-for-me rejection clears matching goal');
goal_check(analyze_goal(array('I want a job', 'Forget the job plan'))['status'] === 'insufficient', 'Explicit forget-goal command clears matching goal');
goal_check(analyze_goal(array('Can I get freelance clients?', 'I am no longer sure I want to freelance. I have not decided what to do yet.'))['status'] === 'insufficient', 'Historical freelance retraction leaves goal undecided');
$result = analyze_goal(array('I want a job', 'Actually I want freelance clients'));
goal_check(goal_contains($result, 'freelance'), 'Later freelance direction replaces employment');
goal_check(goal_contains(analyze_goal(array('I want to switch my career to marketing')), 'career'), 'Career switch supported');
goal_check(goal_contains(analyze_goal(array('I want to promote my bakery on Instagram')), 'business'), 'Own business marketing goal');
goal_check(goal_contains(analyze_goal(array('I run a bakery and want more local enquiries through Instagram')), 'business'), 'Business owner context supported');
goal_check(analyze_goal(array('I own a bakery'))['status'] === 'inferred', 'Ownership alone is an inference, not stated learning purpose');
goal_check(goal_contains(analyze_goal(array('I want to learn SEO and analytics')), 'skills'), 'Personal skills goal');
goal_check(goal_contains(analyze_goal(array('నాకు ఉద్యోగం కావాలి')), 'job'), 'Telugu explicit job goal');
goal_check(goal_contains(analyze_goal(array('నాకు నా వ్యాపారం పెంచాలి')), 'business'), 'Telugu business growth goal');
goal_check(goal_contains(analyze_goal(array('मुझे नौकरी चाहिए')), 'job'), 'Hindi explicit job goal');
goal_check(goal_contains(analyze_goal(array('Mujhe job chahiye')), 'job'), 'Common transliterated Hindi');
goal_check(goal_contains(analyze_goal(array('Naaku job kavali')), 'job'), 'Common transliterated Telugu');
goal_check(analyze_goal(array('కోర్సు ఫీజు ఎంత?'))['status'] === 'interest', 'Telugu fee topic stays an interest');
goal_check(analyze_goal(array('नौकरी नहीं चाहिए'))['status'] === 'interest', 'Hindi negated job statement');
$result = analyze_goal(array('Can I get a job?'), 'Likely wants to improve tracking for their shop.');
goal_check($result['source'] === 'model' && $result['status'] === 'inferred' && goal_contains($result, 'shop'), 'Approved semantic model summary takes precedence');
goal_check(analyze_goal(array(), 'Start a marketing agency.')['status'] === 'stated', 'Explicit model goal status');
goal_check(analyze_goal(array('I want a job'), 'Exploring course details: fees and payment options.')['source'] === 'transcript', 'Old generic fallback never suppresses new evidence');
goal_check(TLIT_CB_Goals::is_fallback(TLIT_CB_Goals::EMPTY_SUMMARY) && TLIT_CB_Goals::is_fallback('Not provided'), 'Fallback identification');
goal_check(!TLIT_CB_Goals::is_fallback('Likely wants a marketing job.'), 'Genuine inferred model goal preserved');
$result = analyze_goal(array('I want a job'), 'I want a job', true);
goal_check($result['status'] === 'insufficient' && goal_contains($result, 'revised'), 'Confirmed clear never resurrects old goal or model');
$result = analyze_goal(array('I want a marketing job, email is person@example.com and mobile +91 98765 43210'));
goal_check(strpos($result['evidence'], 'person@example.com') === false && strpos($result['evidence'], '98765') === false, 'Evidence redacts email and phone');
$result = analyze_goal(array('My name is Akshi. I want a job.'));
goal_check(strpos($result['evidence'], 'Akshi') === false, 'Name introduction not copied to evidence');
$result = analyze_goal(array(str_repeat('I want a job ', 80)));
goal_check(preg_match_all('/./us', $result['evidence']) <= 240, 'Evidence length bounded');
goal_check(TLIT_CB_Goals::analyze(array((object) visitor('I want freelance clients')))['status'] === 'stated', 'WordPress object-shaped rows supported');
goal_check(TLIT_CB_Goals::analyze(array(visitor('Grow my business', 'learning_goal')))['status'] === 'stated', 'Legacy explicit goal selection supported');
goal_check(analyze_goal(array('I want a job', 'What is the fee?'))['status'] === 'stated', 'Routine follow-up does not erase established goal');
goal_check(analyze_goal(array('What is the fee?', 'Thank you'))['evidence'] === 'What is the fee', 'Topic evidence stays relevant after greeting');
echo 'Goal analyzer: ' . $checks . ' checks passed.' . PHP_EOL;
