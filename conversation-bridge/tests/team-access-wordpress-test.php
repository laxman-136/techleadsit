<?php
/** Isolated real WordPress/DB tests; all outbound HTTP is intercepted. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$bootstrap = $argv[1] ?? '';
if (!is_file($bootstrap)) { fwrite(STDERR, "Pass isolated WordPress wp-load.php.\n"); exit(1); }
define('TLIT_ROUTING_TEST', true);
require $bootstrap;
if (!defined('WP_HTTP_BLOCK_EXTERNAL') || !WP_HTTP_BLOCK_EXTERNAL || !in_array(wp_parse_url(home_url(), PHP_URL_HOST), array('localhost', '127.0.0.1'), true)) { throw new RuntimeException('Use isolated localhost WordPress with external requests blocked.'); }
require_once dirname(__DIR__, 2) . '/techleadsit.php';
TLIT_CB_Team_Access::install();
$checks = 0; $made = array(); $chats = array(); $sessions = array(); $calls = 0; $payload = array();
$run = bin2hex(random_bytes(5)); $old = get_option(TLIT_CB_Team_Access::OPTION, null);
$http = function ($pre, $args, $url) use (&$calls, &$payload) {
    if (str_starts_with($url, 'https://next.telecrm.in/')) { ++$calls; $payload = json_decode($args['body'], true); return array('headers' => array(), 'response' => array('code' => 200), 'body' => '{"success":true,"leadId":"team-qa-only"}'); }
    return new WP_Error('test_only', 'External requests are blocked.');
};
add_filter('pre_http_request', $http, PHP_INT_MAX, 3);
function team_check($test, $label) { if (!$test) { throw new RuntimeException('FAIL ' . $label); } ++$GLOBALS['checks']; echo 'PASS ' . $label . "\n"; }
function team_token($invite) { parse_str(wp_parse_url($invite['url'], PHP_URL_QUERY), $query); return $query['tlit_team_bridge']; }
function team_form($tag, $variant = 'v1') {
    $result = TLIT_LP_Leads::ingest(array('request_id' => wp_generate_uuid4(), 'name' => 'Team QA ' . $tag, 'phone' => '+447700900123', 'email' => 'team-' . $GLOBALS['run'] . '-' . $tag . '@example.invalid', 'intent' => 'general', 'page_variant' => $variant, 'submission_url' => home_url('/ai-powered-digital-marketing/'), 'goal' => 'Grow my business', 'tracking' => array('utm_source' => 'qa_only', 'utm_campaign' => $GLOBALS['run'])), array('source' => 'general', 'otp_verified' => false));
    if (is_wp_error($result)) { throw new RuntimeException('Synthetic lead could not be created.'); }
    $GLOBALS['made'][] = (int) $result['lead_id']; return (int) $result['lead_id'];
}
try {
    TLIT_CB_Team_Access::save_settings(array('days' => 7));
    team_check(!TLIT_CB_Team_Access::enabled(), 'disabled by default and existing admin access unchanged');
    team_check(is_wp_error(TLIT_CB_Team_Access::issue('LP-1')), 'disabled feature cannot issue team links');
    team_check(is_wp_error(TLIT_CB_Team_Access::save_settings(array('enabled' => 1, 'days' => 99))), 'unsupported lifetimes rejected');
    TLIT_CB_Team_Access::save_settings(array('enabled' => 1, 'days' => 7));
    $first = team_form('first'); $other = team_form('other', 'v3');
    $invite = TLIT_CB_Team_Access::issue('LP-' . $first); $invite2 = TLIT_CB_Team_Access::issue('LP-' . $other);
    team_check(!is_wp_error($invite) && preg_match('/^[0-9]{4}$/D', $invite['pin']), 'server generates exactly four PIN digits');
    team_check($invite['pin'] !== $invite2['pin'] && $invite['url'] !== $invite2['url'], 'active leads have different PINs and strong private links');
    team_check(TLIT_CB_Team_Access::issue('LP-' . $first) === $invite, 'notification retries reuse the same unexpired PIN and link');
    $raw = team_token($invite); $grant = TLIT_CB_Team_Access::find($raw); $grant2 = TLIT_CB_Team_Access::find(team_token($invite2));
    parse_str(wp_parse_url($invite['url'], PHP_URL_QUERY), $url_fields);
    team_check(strlen($raw) === 64 && array_keys($url_fields) === array('tlit_team_bridge'), 'unguessable link carries no PIN field');
    team_check(TLIT_CB_Team_Access::find(str_repeat('0', 64)) === null && TLIT_CB_Team_Access::find(array()) === null, 'unknown and malformed links fail closed');
    team_check($grant['pin_hash'] !== $invite['pin'] && $grant['pin_sealed'] !== $invite['pin'] && wp_check_password($invite['pin'], $grant['pin_hash']), 'PIN is hashed for checks and encrypted only for notification retries');
    team_check(is_wp_error(TLIT_CB_Team_Access::login($grant, $invite2['pin'], 'qa-' . $run)), 'another lead PIN cannot unlock this lead');
    $session = TLIT_CB_Team_Access::login($grant, $invite['pin'], 'qa-' . $run); $sessions[] = $session['token'];
    team_check(!is_wp_error($session) && strlen($session['csrf']) === 64, 'correct PIN creates a new server-held session and CSRF token');
    team_check(TLIT_CB_Team_Access::session($grant, $session['token']) !== null && TLIT_CB_Team_Access::session($grant2, $session['token']) === null, 'session is limited to one lead');
    $expired = TLIT_CB_Team_Access::login($grant, $invite['pin'], 'expiry-' . $run); $sessions[] = $expired['token'];
    $expired_key = 'tlit_bt_session_' . hash_hmac('sha256', 'team-bridge|' . $expired['token'], wp_salt('auth'));
    $expired_data = get_transient($expired_key); $expired_data['expires'] = time() - 1; set_transient($expired_key, $expired_data, 60);
    team_check(TLIT_CB_Team_Access::session($grant, $expired['token']) === null, 'session expires independently of a still-valid lead link');
    set_transient($expired_key, array('grant_id' => $grant['id'], 'nonce' => array()), 60);
    team_check(TLIT_CB_Team_Access::session($grant, $expired['token']) === null, 'malformed session data fails closed without granting access');
    $before = $calls;
    team_check(is_wp_error(TLIT_CB_Team_Access::review($grant, $session['token'], array('csrf' => 'wrong', 'lead_status' => 'potential'))) && $calls === $before, 'invalid CSRF cannot review or send to TeleCRM');
    team_check(is_wp_error(TLIT_CB_Team_Access::review($grant2, $session['token'], array('csrf' => $session['csrf'], 'lead_status' => 'potential'))) && $calls === $before, 'changing target grant cannot use a different lead session');
    foreach (array('new', 'junk') as $status) {
        $saved = TLIT_CB_Team_Access::review($grant, $session['token'], array('csrf' => $session['csrf'], 'lead_status' => $status, 'note' => 'Local QA only', 'reviewer' => 'QA team', 'lead_id' => $other));
        team_check(!is_wp_error($saved) && TLIT_LP_Leads::get($first)->lead_status === $status && $calls === $before, $status . ' stays in WordPress and ignores a submitted cross-lead ID');
    }
    team_check(TLIT_LP_Leads::get($other)->lead_status === 'new', 'other lead remains unchanged');
    $saved = TLIT_CB_Team_Access::review($grant, $session['token'], array('csrf' => $session['csrf'], 'lead_status' => 'potential', 'note' => 'Interested after call', 'reviewer' => 'QA team'));
    team_check(!is_wp_error($saved) && $calls === $before + 1 && $saved->crm_status === 'sent' && $payload['fields']['status'] === '50 % Potential', 'qualified team review uses the existing TeleCRM connection and exact status mapping');
    team_check(str_contains($saved->advisor_note, '[Team Bridge — QA team]') && (int) $saved->reviewed_by === 0, 'shared team review is labelled and never impersonates a WordPress administrator');
    team_check(!str_contains(wp_json_encode($payload), $invite['url']), 'private access credential is not forwarded to TeleCRM');
    $before = $calls;
    TLIT_CB_Team_Access::review($grant, $session['token'], array('csrf' => $session['csrf'], 'lead_status' => 'potential', 'note' => 'Interested after call', 'reviewer' => 'QA team'));
    team_check($calls === $before, 'duplicate review does not resend an identical TeleCRM revision');
    $prepared = TLIT_CB_Team_Access::for_notification(TLIT_Teams_Notifications::lead('form', $first));
    $card = TLIT_Teams_Notifications::card($prepared, 'qa-' . $run)['attachments'][0]['content'];
    $facts = array_column($card['body'][2]['facts'], 'value', 'title');
    team_check($facts['Bridge PIN'] === $invite['pin'] && $facts['Learning goal'] === 'Grow my business' && $card['actions'][0]['url'] === $invite['url'], 'Teams message receives this lead PIN, learning goal, expiry and its scoped Bridge link');
    team_check($calls === $before, 'creating an alert does not qualify or send a lead');
    require_once dirname(__DIR__) . '/includes/team-view.php';
    ob_start(); TLIT_CB_Team_View::login($invite['url'], 'synthetic-csrf', ''); $gate = ob_get_clean();
    team_check(!str_contains($gate, 'Team QA first') && !str_contains($gate, 'team-' . $run) && !str_contains($gate, $invite['pin']), 'PIN gate exposes no lead contact data or PIN');
    ob_start(); TLIT_CB_Team_View::detail($grant, (array) TLIT_LP_Leads::get($first), $session, $invite['url'], '', false); $view = ob_get_clean();
    team_check(str_contains($view, 'Grow my business') && str_contains($view, 'Save review &amp; sync lead') && !str_contains($view, 'Team QA other') && !str_contains($view, 'wp-admin'), 'unlocked page includes context and review but no other leads, settings or admin links');
    $event = array('session' => substr(hash('sha256', 'team-' . $run), 0, 48), 'event_id' => 'team-qa', 'course' => 'digital_marketing', 'source_url' => home_url('/ai-powered-digital-marketing-course/'), 'contact' => array('name' => 'Chat QA ' . $run, 'phone' => '+447700900123'), 'language' => 'English', 'stage' => 'question', 'messages' => array(array('role' => 'visitor', 'type' => 'question', 'content' => 'I want to get a digital marketing job.')));
    $cid = TLIT_CB_Store::capture($event); $chats[] = $cid;
    $chat_invite = TLIT_CB_Team_Access::issue('CB-' . $cid); $cg = TLIT_CB_Team_Access::find(team_token($chat_invite));
    $cs = TLIT_CB_Team_Access::login($cg, $chat_invite['pin'], 'chat-' . $run); $sessions[] = $cs['token'];
    $saved = TLIT_CB_Team_Access::review($cg, $cs['token'], array('csrf' => $cs['csrf'], 'lead_status' => 'high_potential', 'note' => 'Chat lead reviewed'));
    $made[] = (int) TLIT_CB_Store::get($cid)['lead_id'];
    team_check(!is_wp_error($saved) && $calls === $before + 1 && $payload['fields']['status'] === '60-80 Potential', 'chat review links the canonical lead and syncs through the same approved CRM service');
    ob_start(); TLIT_CB_Team_View::detail($cg, TLIT_CB_Store::get($cid), $cs, $chat_invite['url'], '', false); $view = ob_get_clean();
    team_check(str_contains($view, 'I want to get a digital marketing job.') && !str_contains($view, 'Team QA first'), 'chat page contains only the granted conversation transcript');
    for ($i = 0; $i < 6; ++$i) { $s = TLIT_CB_Team_Access::login($grant, $invite['pin'], 'valid-' . $run); if (!is_wp_error($s)) { $sessions[] = $s['token']; } }
    team_check(!is_wp_error($s), 'successful staff sign-ins do not consume failed-PIN allowance');
    for ($i = 0; $i < 5; ++$i) { $bad = TLIT_CB_Team_Access::login($grant2, 'not-a-pin', 'attempt-' . $run . '-' . $i); }
    $blocked = TLIT_CB_Team_Access::login($grant2, $invite2['pin'], 'different-ip-' . $run);
    team_check(is_wp_error($blocked) && $blocked->get_error_data()['status'] === 429, 'five wrong attempts lock the lead across browsers and IPs');
    TLIT_CB_Team_Access::logout($session['token']);
    team_check(TLIT_CB_Team_Access::session($grant, $session['token']) === null, 'logout revokes server-side access');
    global $wpdb;
    $wpdb->update(TLIT_CB_Team_Access::table(), array('expires_at' => gmdate('Y-m-d H:i:s', time() - 60)), array('id' => $grant['id']));
    team_check(TLIT_CB_Team_Access::find($raw) === null && TLIT_CB_Team_Access::session($grant, end($sessions)) === null, 'expired links cannot be reopened or used with an existing session');
    $wpdb->delete(TLIT_LP_Leads::table(), array('id' => $other));
    team_check(TLIT_CB_Team_Access::record($grant2) === null, 'erased lead cannot be read through an old link');
    TLIT_CB_Team_Access::save_settings(array('enabled' => 1, 'days' => 7, 'revoke' => 1));
    team_check(TLIT_CB_Team_Access::find(team_token($chat_invite)) === null && TLIT_CB_Team_Access::session($cg, $cs['token']) === null, 'administrator revocation immediately invalidates links and sessions');
    TLIT_CB_Team_Access::save_settings(array('days' => 7));
    team_check(!TLIT_CB_Team_Access::enabled(), 'administrator can disable all public PIN access');
    echo "ALL $checks team access WordPress/DB checks passed; external requests mocked.\n";
} finally {
    remove_filter('pre_http_request', $http, PHP_INT_MAX);
    global $wpdb;
    foreach ($sessions as $token) { TLIT_CB_Team_Access::logout($token); }
    foreach (array_unique($made) as $id) { $wpdb->delete(TLIT_LP_Leads::table(), array('id' => $id)); $wpdb->delete(TLIT_CB_Team_Access::table(), array('identity_key' => 'LP-' . $id)); }
    foreach ($chats as $id) { foreach (array('messages', 'events') as $kind) { $wpdb->delete($wpdb->prefix . 'tlit_cb_' . $kind, array('conversation_id' => $id)); } $wpdb->delete($wpdb->prefix . 'tlit_cb_conversations', array('id' => $id)); $wpdb->delete(TLIT_CB_Team_Access::table(), array('identity_key' => 'CB-' . $id)); }
    if ($old === null) { delete_option(TLIT_CB_Team_Access::OPTION); } else { update_option(TLIT_CB_Team_Access::OPTION, $old, false); }
}
