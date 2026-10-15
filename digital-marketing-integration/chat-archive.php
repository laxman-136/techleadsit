<?php
if (!defined('ABSPATH')) { exit; }

/** Emits approved server events, never a browser-supplied transcript. */
function tlit_dm_archive($token, $event_id, $state, $language, $messages = array(), $extra = array()) {
    if (!function_exists('do_action')) { return; }
    $contact = tlit_dm_chat_unseal($state['contact_sealed'] ?? '');
    // Structured memory is the latest confirmed state, including an explicit clear.
    // A missing/expired state must not erase a goal already saved by the bridge.
    $memory = is_array($state['memory'] ?? null) ? $state['memory'] : array();
    $has_memory_goal = array_key_exists('goal', $memory) && (is_string($memory['goal']) || $memory['goal'] === null);
    $goal = $has_memory_goal ? ($memory['goal'] ?? '') : ($state['goal'] ?? '');
    $goal_authoritative = $has_memory_goal && ($goal !== '' || $memory['goal'] === null || !empty($state['goal_clear_confirmed']));
    $event = array_merge(array(
        'session' => $token,
        'event_id' => $event_id,
        'course' => 'digital_marketing',
        'source_url' => rtrim(home_url(), '/') . '/ai-powered-digital-marketing/',
        'contact' => is_array($contact) ? $contact : array(),
        'language' => $language,
        'goal' => is_string($goal) ? $goal : '',
        'goal_authoritative' => $goal_authoritative,
        'stage' => tlit_dm_chat_stage($state),
        'answer_count' => (int) ($state['answers'] ?? 0),
        'messages' => $messages,
    ), $extra);
    do_action('tlit_cb_capture', $event);
}

function tlit_dm_archive_intro($token, $state, $name, $language, $tracking = array()) {
    $greeting = $language === 'Telugu' ? $name . ', డిజిటల్ మార్కెటింగ్ గురించి ఏమి తెలుసుకోవాలనుకుంటున్నారు?' : ($language === 'Hindi' ? $name . ', आप डिजिटल मार्केटिंग के बारे में क्या जानना चाहते हैं?' : $name . ', what would you like to know about digital marketing?');
    $messages = array(
        array('role'=>'assistant', 'type'=>'introduction', 'content'=>'Hi, I’m Lakshmi. What should I call you?'),
        array('role'=>'visitor', 'type'=>'contact_name', 'content'=>$name),
        array('role'=>'assistant', 'type'=>'language', 'content'=>'Which language feels most comfortable?'),
        array('role'=>'visitor', 'type'=>'language', 'content'=>$language),
    );
    $goal_choice = $state['intro_goal'] ?? '';
    $goal_choices = tlit_dm_chat_goal_choices($language);
    if (is_string($goal_choice) && isset($goal_choices[$goal_choice])) {
        $messages[] = array('role'=>'assistant', 'type'=>'learning_goal', 'content'=>tlit_dm_chat_goal_prompt($language));
        $messages[] = array('role'=>'visitor', 'type'=>'learning_goal', 'content'=>$goal_choices[$goal_choice]);
    }
    $messages[] = array('role'=>'assistant', 'type'=>'introduction', 'content'=>$greeting);
    tlit_dm_archive($token, 'intro:' . hash('sha256', $name . '|' . $language . '|' . (is_string($goal_choice) ? $goal_choice : '')), $state, $language, $messages, array('tracking'=>is_array($tracking)?$tracking:array()));
}

function tlit_dm_archive_brochure($payload, $reference) {
    $token = $payload['conversation'] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{48}$/D', $token)) { return; }
    $state = get_transient('tlit_dm_ai_conversation_' . tlit_dm_hash($token));
    if (!is_array($state)) { $state=array(); }
    $contact=array('name'=>$payload['name']??'', 'phone'=>$payload['phone']??'', 'email'=>$payload['email']??'');
    tlit_dm_archive($token, 'brochure:' . $reference, $state, $state['language']??'English', array(
        array('role'=>'system','type'=>'brochure','content'=>'Brochure request submitted. Reference: ' . $reference),
    ), array('contact'=>$contact, 'has_brochure'=>true, 'tracking'=>is_array($payload['tracking']??null)?$payload['tracking']:array()));
}
