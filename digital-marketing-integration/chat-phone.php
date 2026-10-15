<?php
// Local country metadata is shared with the chat UI. No phone number leaves the
// server during validation; a valid format does not establish ownership.
if (!defined('ABSPATH')) exit;

function tlit_dm_chat_phone_countries() {
    static $countries = null;
    if ($countries !== null) return $countries;
    $countries = array();
    $raw = file_get_contents(__DIR__ . '/phone-countries.json');
    $data = json_decode($raw === false ? '' : $raw, true);
    foreach (($data['countries'] ?? array()) as $country) {
        if (is_array($country) && isset($country['country'], $country['dialCode'], $country['nationalPattern'], $country['possibleLengths'])) {
            $countries[$country['country']] = $country;
        }
    }
    return $countries;
}

function tlit_dm_chat_phone_national_valid($digits, $country) {
    if (!is_string($digits) || !preg_match('/^[0-9]+$/D', $digits)) return false;
    if (!in_array(strlen($digits), $country['possibleLengths'], true)) return false;
    if (strlen($country['dialCode'] . $digits) > 15) return false;
    return preg_match('~^(?:' . $country['nationalPattern'] . ')$~D', $digits) === 1;
}

function tlit_dm_chat_phone_strip_prefix($digits, $country) {
    $prefix = $country['nationalPrefixForParsing'] ?? '';
    if ($prefix === '') return $digits;
    $pattern = '~^(?:' . $prefix . ')~';
    if (!preg_match($pattern, $digits, $matches)) return $digits;
    $last = count($matches) - 1;
    $transform = $country['nationalPrefixTransformRule'] ?? '';
    if ($transform !== '' && $last > 0 && $matches[$last] !== '') {
        return preg_replace($pattern, $transform, $digits, 1);
    }
    return substr($digits, strlen($matches[0]));
}

/** Normalize a national number or matching international number to +E.164. */
function tlit_dm_chat_phone($value, $country = 'IN') {
    if (!is_string($value) || !is_string($country) || strlen($value) > 80) return '';
    $country = strtoupper(trim($country));
    $countries = tlit_dm_chat_phone_countries();
    if (!isset($countries[$country])) return '';
    $plan = $countries[$country];
    $value = trim($value);
    if ($value === '' || !preg_match('/^\+?[0-9 ()\t\r\n.\-]+$/D', $value)) return '';
    $digits = preg_replace('/[^0-9]/', '', $value);
    if ($digits === '' || strlen($digits) > 20) return '';
    $international = substr($value, 0, 1) === '+';
    if (!$international && substr($digits, 0, 2) === '00') {
        $international = true;
        $digits = substr($digits, 2);
    }
    $calling = $plan['dialCode'];
    if ($international) {
        if (strlen($digits) > 15 || substr($digits, 0, strlen($calling)) !== $calling) return '';
        $digits = substr($digits, strlen($calling));
        // Explicit international input must already include the national
        // significant number. A mismatching selected region is not rewritten.
        return tlit_dm_chat_phone_national_valid($digits, $plan) ? '+' . $calling . $digits : '';
    }
    if (tlit_dm_chat_phone_national_valid($digits, $plan)) return '+' . $calling . $digits;
    $stripped = tlit_dm_chat_phone_strip_prefix($digits, $plan);
    if (tlit_dm_chat_phone_national_valid($stripped, $plan)) return '+' . $calling . $stripped;
    // Accept a pasted country-code-prefixed number without the leading +, but
    // only after checking the national form to avoid stripping meaningful digits.
    if (substr($digits, 0, strlen($calling)) === $calling) {
        $national = substr($digits, strlen($calling));
        if (tlit_dm_chat_phone_national_valid($national, $plan)) return '+' . $calling . $national;
    }
    return '';
}

/** Validate an existing +E.164 phone when no region selection is available. */
function tlit_dm_chat_phone_international($value) {
    if (!is_string($value) || !preg_match('/^\+[0-9 ()\t\r\n.\-]+$/D', trim($value))) return '';
    foreach (tlit_dm_chat_phone_countries() as $country => $plan) {
        $normalized = tlit_dm_chat_phone($value, $country);
        if ($normalized !== '') return $normalized;
    }
    return '';
}
