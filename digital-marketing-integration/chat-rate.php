<?php
if (!defined('ABSPATH')) exit;

// AI-only accounting. SMS and lead-submission limits use their existing limiter.
function tlit_dm_ai_rate_key($id) {
    return 'tlit_dm_rate_'.tlit_dm_hash($id);
}

function tlit_dm_ai_rate_number($value) {
    return is_int($value) ? $value >= 0 : (is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/D', $value) && strlen($value) < 19);
}

function tlit_dm_ai_rate_bucket($key, $now, $ttl, $dailyReset = null) {
    $stored = get_transient($key);
    $reset = $dailyReset === null ? $now + $ttl : $dailyReset;
    if ($stored === false) return array('value'=>array('count'=>0, 'reset'=>$reset), 'write'=>false);
    if (is_array($stored)) {
        if (!tlit_dm_ai_rate_number($stored['count']??null) || !tlit_dm_ai_rate_number($stored['reset']??null) || (int)$stored['reset'] < 1) return null;
        $value = array('count'=>(int)$stored['count'], 'reset'=>(int)$stored['reset']);
        // The date is part of a daily key, so keep its count until this UTC day ends.
        if ($dailyReset !== null) $value['reset'] = $dailyReset;
        elseif ($value['reset'] <= $now) $value = array('count'=>0, 'reset'=>$reset);
        return array('value'=>$value, 'write'=>$value !== $stored);
    }
    if (!tlit_dm_ai_rate_number($stored)) return null;
    if ($dailyReset === null) {
        // WordPress stores the legacy transient deadline separately without an external cache.
        // With an external cache, retain the count for one bounded window from migration.
        $external = function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
        $legacyReset = $external ? false : get_option('_transient_timeout_'.$key);
        if (tlit_dm_ai_rate_number($legacyReset) && (int)$legacyReset > 0) $reset = min($reset, (int)$legacyReset);
    }
    $count = (int)$stored;
    if ($reset <= $now) { $count = 0; $reset = $now + $ttl; }
    return array('value'=>array('count'=>$count, 'reset'=>$reset), 'write'=>true);
}

function tlit_dm_ai_rate_save($key, $value, $now) {
    return set_transient($key, $value, max(1, $value['reset'] - $now)) !== false;
}

function tlit_dm_ai_rate_result($reason = '', $retry = 0) {
    return array('allowed'=>$reason === '', 'reason'=>$reason, 'retry_after'=>max(0, (int)$retry));
}

function tlit_dm_ai_reserve_request($ip, $dailyLimit, $now = null) {
    $now = $now === null ? time() : (int)$now;
    $dailyLimit = max(1, (int)$dailyLimit);
    // The caller supplies REMOTE_ADDR. Never trust visitor-controlled forwarding headers.
    $ip = is_string($ip) && $ip !== '' ? $ip : 'unknown';
    $networkKey = tlit_dm_ai_rate_key('ai-ip-'.$ip);
    $dayKey = tlit_dm_ai_rate_key('ai-day-'.gmdate('Y-m-d', $now));
    $dayReset = (int)(floor($now / 86400) + 1) * 86400;
    $lock = tlit_dm_lock('ai-budget-reservation-v1');
    if (!$lock) return tlit_dm_ai_rate_result('budget_lock', 2);
    try {
        $network = tlit_dm_ai_rate_bucket($networkKey, $now, 3600);
        $day = tlit_dm_ai_rate_bucket($dayKey, $now, 86400, $dayReset);
        if ($network === null || $day === null) return tlit_dm_ai_rate_result('accounting_error', 5);
        // Check both before reserving either, so a daily rejection cannot consume network capacity.
        $reason = $day['value']['count'] >= $dailyLimit ? 'daily_budget' : ($network['value']['count'] >= 120 ? 'network_limit' : '');
        if ($reason !== '') {
            // Fix the deadline of numeric legacy buckets even on a rejected attempt.
            if (($day['write'] && !tlit_dm_ai_rate_save($dayKey, $day['value'], $now)) || ($network['write'] && !tlit_dm_ai_rate_save($networkKey, $network['value'], $now))) return tlit_dm_ai_rate_result('accounting_error', 5);
            return tlit_dm_ai_rate_result($reason, ($reason === 'daily_budget' ? $day['value']['reset'] : $network['value']['reset']) - $now);
        }
        $day['value']['count']++;
        $network['value']['count']++;
        // Count attempted provider calls, including failures. Do not refund or reset paid-call budgets.
        // Persist the global budget first; storage failure fails closed, never permits an uncounted call.
        if (!tlit_dm_ai_rate_save($dayKey, $day['value'], $now) || !tlit_dm_ai_rate_save($networkKey, $network['value'], $now)) return tlit_dm_ai_rate_result('accounting_error', 5);
        return tlit_dm_ai_rate_result();
    } finally {
        // This lock only protects reservation. The network/model request happens after release.
        delete_option($lock);
    }
}
