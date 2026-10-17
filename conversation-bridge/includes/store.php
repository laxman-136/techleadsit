<?php
/** Private, durable storage for the TechLeads IT chatbot conversation bridge. */
if (!defined('ABSPATH')) {
    exit;
}
require_once __DIR__ . '/goals.php';

final class TLIT_CB_Store {
    const DB_VERSION = '4';
    const VERSION_OPTION = 'tlit_cb_db_version';
    const PRIVACY_PAGE_SIZE = 20;
    private static $read_failed = false;

    /** Whether the most recent store read failed; no database detail or PII is exposed. */
    public static function read_error() {
        return self::$read_failed;
    }

    private static function table($kind) {
        global $wpdb;
        $names = array('conversations' => 'tlit_cb_conversations', 'messages' => 'tlit_cb_messages', 'events' => 'tlit_cb_events');
        return $wpdb->prefix . $names[$kind];
    }

    /** Called from activation and init; schema upgrades never remove stored conversations. */
    public static function install() {
        global $wpdb;
        if (get_option(self::VERSION_OPTION) === self::DB_VERSION) {
            if (class_exists('TLIT_CB_Goal_Backfill')) { TLIT_CB_Goal_Backfill::schedule(); }
            return true;
        }
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        $conversations = self::table('conversations');
        $messages = self::table('messages');
        $events = self::table('events');
        $charset = $wpdb->get_charset_collate();
        $previous = $wpdb->suppress_errors(true);
        try {
            dbDelta("CREATE TABLE $conversations (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                session_hash char(64) NOT NULL,
                course varchar(64) NOT NULL DEFAULT 'digital_marketing',
                source_url varchar(2048) NOT NULL DEFAULT '',
                name varchar(160) NOT NULL DEFAULT '',
                email varchar(254) NOT NULL DEFAULT '',
                phone varchar(32) NOT NULL DEFAULT '',
                language varchar(32) NOT NULL DEFAULT '',
                goal text NOT NULL,
                goal_analysis_json longtext NOT NULL,
                tracking_json longtext NOT NULL,
                stage varchar(48) NOT NULL DEFAULT '',
                status varchar(20) NOT NULL DEFAULT 'new',
                lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
                qualification_status varchar(24) NOT NULL DEFAULT 'new',
                qualification_note text NOT NULL,
                has_brochure tinyint(1) unsigned NOT NULL DEFAULT 0,
                answer_count int(10) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY session_hash (session_hash),
                KEY created_at (created_at),
                KEY updated_at (updated_at,id),
                KEY status (status),
                KEY course (course),
                KEY email (email(191))
            ) ENGINE=InnoDB $charset;");
            dbDelta("CREATE TABLE $messages (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                conversation_id bigint(20) unsigned NOT NULL,
                event_key char(64) NOT NULL,
                role varchar(16) NOT NULL DEFAULT 'system',
                content longtext NOT NULL,
                type varchar(48) NOT NULL DEFAULT 'message',
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY event_key (event_key),
                KEY conversation_id (conversation_id,id),
                KEY created_at (created_at)
            ) ENGINE=InnoDB $charset;");
            dbDelta("CREATE TABLE $events (
                event_key char(64) NOT NULL,
                conversation_id bigint(20) unsigned NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (event_key),
                KEY conversation_id (conversation_id)
            ) ENGINE=InnoDB $charset;");
            // Do not mark a failed migration complete. Avoid exposing database error text.
            $conversation_columns = $wpdb->get_col("SHOW COLUMNS FROM $conversations", 0);
            $message_columns = $wpdb->get_col("SHOW COLUMNS FROM $messages", 0);
            $event_columns = $wpdb->get_col("SHOW COLUMNS FROM $events", 0);
            $required = array('id', 'session_hash', 'course', 'source_url', 'name', 'email', 'phone', 'language', 'goal', 'goal_analysis_json', 'tracking_json', 'stage', 'status', 'lead_id', 'qualification_status', 'qualification_note', 'has_brochure', 'answer_count', 'created_at', 'updated_at');
            if (array_diff($required, (array) $conversation_columns) || array_diff(array('id', 'conversation_id', 'event_key', 'role', 'content', 'type', 'created_at'), (array) $message_columns) || array_diff(array('event_key', 'conversation_id', 'created_at'), (array) $event_columns)) {
                return self::error('schema');
            }
            update_option(self::VERSION_OPTION, self::DB_VERSION, false);
            if (class_exists('TLIT_CB_Goal_Backfill')) { TLIT_CB_Goal_Backfill::schedule(); }
            return true;
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    private static function error($code) {
        return new WP_Error('tlit_cb_' . $code, 'The conversation could not be stored or loaded. Please retry.', array('status' => 503));
    }

    private static function scalar($value) {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    private static function cut($text, $length) {
        // WP discards malformed UTF-8 before it reaches a database or an export.
        $text = wp_check_invalid_utf8((string) $text, true);
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $length, 'UTF-8');
        }
        return preg_match('/^.{0,' . (int) $length . '}/us', $text, $match) ? $match[0] : '';
    }

    /** Credentials and verification codes have no place in an admin transcript. */
    private static function redact($text, $session = '') {
        if ($session !== '') {
            $text = str_ireplace($session, '[redacted session]', $text);
        }
        $patterns = array(
            '/\b(?:Bearer|Basic)\s+[A-Za-z0-9._~+\/-]+=*/i',
            '/\b(?:sk-(?:or-v1-|proj-)?|sk_live_|sk_test_|ghp_|github_pat_)[A-Za-z0-9_-]{12,}\b/i',
            '/\b(?:AKIA[0-9A-Z]{16}|AIza[A-Za-z0-9_-]{35})\b/',
            '/\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/',
            '/\b(?:api[ _-]?key|api[ _-]?secret|access[ _-]?token|refresh[ _-]?token|auth(?:orization)?[ _-]?token|token|client[ _-]?secret|password|passwd|secret)\b["\x27]?\s*(?:[:=]|\bis\b)\s*["\x27]?[^\s"\x27,;<>]+/i',
            '/\b(?:OTP|one[ -]?time(?:\s+password|\s+code)?|verification\s+code)\b\s*(?:(?:is|code|:|=|-)\s*){0,3}\d(?:[ -]?\d){3,7}\b/i',
            '/\b\d(?:[ -]?\d){3,7}\s+(?:is\s+)?(?:my|your|the)?\s*(?:OTP|verification\s+code)\b/i',
            '/-----BEGIN (?:[A-Z0-9 ]+ )?PRIVATE KEY-----[\s\S]*?-----END (?:[A-Z0-9 ]+ )?PRIVATE KEY-----/',
        );
        return preg_replace($patterns, '[redacted]', $text);
    }

    private static function clean_text($value, $length, $multiline = false, $session = '') {
        $text = self::scalar($value);
        $text = $multiline ? sanitize_textarea_field($text) : sanitize_text_field($text);
        return self::cut(self::redact($text, $session), $length);
    }

    /** Keep only scheme, host, port and path; query strings often contain contact details. */
    private static function source_url($value, $session = '') {
        $value = self::scalar($value);
        if ($session !== '') {
            $value = str_ireplace($session, 'redacted', $value);
        }
        $url = esc_url_raw($value, array('http', 'https'));
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
            return '';
        }
        $path = isset($parts['path']) ? $parts['path'] : '/';
        return self::cut(esc_url_raw(strtolower($parts['scheme']) . '://' . $parts['host'] . (isset($parts['port']) ? ':' . (int) $parts['port'] : '') . $path, array('http', 'https')), 2048);
    }

    private static function tracking($values, $session = '') {
        $values = is_array($values) ? $values : array();
        $allowed = array('utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'fbp', 'fbc', 'ga_client_id', 'session_id', 'landing_page', 'referrer', 'fb_ad', 'fb_campaign', 'fb_adset_name', 'fb_adset_id', 'fb_lead_id', 'city', 'state');
        $tracking = array();
        foreach ($allowed as $key) {
            if (!isset($values[$key])) {
                continue;
            }
            $value = in_array($key, array('landing_page', 'referrer'), true) ? self::cut(self::source_url($values[$key], $session), 512) : self::clean_text($values[$key], 512, false, $session);
            if ($value !== '') {
                $tracking[$key] = $value;
            }
        }
        return $tracking;
    }

    /** Capture one server event atomically; retries cannot duplicate transcript messages. */
    public static function capture($event) {
        global $wpdb;
        if (!is_array($event) || !isset($event['session']) || !is_string($event['session']) || !preg_match('/^[a-f0-9]{48}$/D', $event['session'])) {
            return new WP_Error('tlit_cb_invalid_session', 'The conversation reference is invalid.', array('status' => 400));
        }
        $event_id = isset($event['event_id']) && is_string($event['event_id']) ? $event['event_id'] : '';
        if ($event_id === '' || strlen($event_id) > 200 || !isset($event['messages']) || !is_array($event['messages']) || count($event['messages']) > 50) {
            return new WP_Error('tlit_cb_invalid_event', 'The conversation event is invalid.', array('status' => 400));
        }
        $installed = self::install();
        if (is_wp_error($installed)) {
            return $installed;
        }
        $session = $event['session'];
        $salt = wp_salt('auth');
        $session_hash = hash_hmac('sha256', 'tlit-cb-session|' . $session, $salt);
        $contact = isset($event['contact']) && is_array($event['contact']) ? $event['contact'] : array();
        $email = sanitize_email(self::scalar(isset($contact['email']) ? $contact['email'] : ''));
        $email = is_email($email) && strlen($email) <= 254 ? $email : '';
        if (stripos($email, $session) !== false) {
            $email = '';
        }
        $phone = preg_replace('/[^0-9+]/', '', self::scalar(isset($contact['phone']) ? $contact['phone'] : ''));
        if (!preg_match('/^\+?[0-9]{7,15}$/D', $phone)) {
            $phone = '';
        }
        $course = sanitize_key(self::scalar(isset($event['course']) ? $event['course'] : 'digital_marketing'));
        if (stripos($course, $session) !== false) {
            $course = '';
        }
        $course = $course !== '' ? substr($course, 0, 64) : 'digital_marketing';
        $tracking = self::tracking(isset($event['tracking']) ? $event['tracking'] : array(), $session);
        $now = gmdate('Y-m-d H:i:s');
        $row = array(
            $session_hash,
            $course,
            self::source_url(isset($event['source_url']) ? $event['source_url'] : '', $session),
            self::clean_text(isset($contact['name']) ? $contact['name'] : '', 160, false, $session),
            $email,
            $phone,
            self::clean_text(isset($event['language']) ? $event['language'] : '', 32, false, $session),
            self::clean_text(isset($event['goal']) ? $event['goal'] : '', 4000, true, $session),
            wp_json_encode($tracking),
            self::cut(sanitize_key(self::clean_text(isset($event['stage']) ? $event['stage'] : '', 48, false, $session)), 48),
            !empty($event['has_brochure']) ? 1 : 0,
            min(1000000, max(0, (int) (isset($event['answer_count']) && is_scalar($event['answer_count']) ? $event['answer_count'] : 0))),
            $now,
            $now,
        );
        $conversations = self::table('conversations');
        $messages = self::table('messages');
        $events = self::table('events');
        // Digital Marketing reconciles the durable goal against the locked
        // transcript below. Other adapters retain their existing update contract.
        $goal_update = $course === 'digital_marketing' ? 'goal=goal,' : (($event['goal_authoritative'] ?? false) === true
            ? 'goal=VALUES(goal),'
            : "goal=IF(VALUES(goal)<>'',VALUES(goal),goal),");
        $previous = $wpdb->suppress_errors(true);
        $started = false;
        try {
            if ($wpdb->query('START TRANSACTION') === false) {
                return self::error('write');
            }
            $started = true;
            $sql = "INSERT INTO $conversations (session_hash,course,source_url,name,email,phone,language,goal,tracking_json,stage,has_brochure,answer_count,created_at,updated_at,goal_analysis_json,qualification_note)
                VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%d,%d,%s,%s,'','')
                ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),
                source_url=IF(VALUES(source_url)<>'',VALUES(source_url),source_url),
                name=IF(VALUES(name)<>'',VALUES(name),name),
                email=IF(VALUES(email)<>'',VALUES(email),email),
                phone=IF(VALUES(phone)<>'',VALUES(phone),phone),
                language=IF(VALUES(language)<>'',VALUES(language),language),
                $goal_update
                stage=IF(VALUES(stage)<>'',VALUES(stage),stage),
                has_brochure=GREATEST(has_brochure,VALUES(has_brochure)),
                answer_count=GREATEST(answer_count,VALUES(answer_count)),updated_at=VALUES(updated_at)";
            if ($wpdb->query($wpdb->prepare($sql, $row)) === false) {
                return self::error('write');
            }
            $id = (int) $wpdb->insert_id;
            if ($id < 1) {
                $id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $conversations WHERE session_hash=%s", $session_hash));
            }
            if (!$id) {
                return self::error('write');
            }
            $capture_key = hash_hmac('sha256', 'tlit-cb-event|' . $session_hash . '|' . $event_id, $salt);
            // The parent upsert holds a row lock, serializing all captures for this session.
            // Roll back its metadata merge when a delayed event is retried after newer data.
            $seen = $wpdb->get_var($wpdb->prepare("SELECT event_key FROM $events WHERE event_key=%s", $capture_key));
            if ($wpdb->last_error !== '') {
                return self::error('write');
            }
            if ($seen !== null) {
                if ($wpdb->query('ROLLBACK') === false) {
                    return self::error('write');
                }
                $started = false;
                return $id;
            }
            if ($wpdb->query($wpdb->prepare("INSERT INTO $events (event_key,conversation_id,created_at) VALUES (%s,%d,%s)", $capture_key, $id, $now)) === false) {
                return self::error('write');
            }
            if ($tracking) {
                $old_tracking = $wpdb->get_var($wpdb->prepare("SELECT tracking_json FROM $conversations WHERE id=%d", $id));
                if ($wpdb->last_error !== '') {
                    return self::error('write');
                }
                $old_tracking = json_decode((string) $old_tracking, true);
                $merged_tracking = array_merge(self::tracking($old_tracking, $session), $tracking);
                if ($wpdb->query($wpdb->prepare("UPDATE $conversations SET tracking_json=%s WHERE id=%d", wp_json_encode($merged_tracking), $id)) === false) {
                    return self::error('write');
                }
            }
            foreach (array_values($event['messages']) as $index => $message) {
                if (!is_array($message)) {
                    continue;
                }
                $type = substr(sanitize_key(self::scalar(isset($message['type']) ? $message['type'] : 'message')), 0, 48);
                if (strpos($type, 'otp') !== false || strpos($type, 'verification_code') !== false || strpos($type, 'password') !== false) {
                    continue;
                }
                $content = self::clean_text(isset($message['content']) ? $message['content'] : '', 20000, true, $session);
                if ($content === '') {
                    continue;
                }
                $role = isset($message['role']) && in_array($message['role'], array('visitor', 'assistant', 'system'), true) ? $message['role'] : 'system';
                $key = hash_hmac('sha256', $session_hash . '|' . $event_id . '|' . $index, $salt);
                if ($wpdb->query($wpdb->prepare("INSERT INTO $messages (conversation_id,event_key,role,content,type,created_at) VALUES (%d,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE id=id", $id, $key, $role, $content, $type !== '' ? $type : 'message', $now)) === false) {
                    return self::error('write');
                }
            }
            if ($course === 'digital_marketing') {
                $goal_result = self::refresh_goal($id, $event);
                if (is_wp_error($goal_result)) { return $goal_result; }
            }
            if ($wpdb->query('COMMIT') === false) {
                return self::error('write');
            }
            $started = false;
            return $id;
        } finally {
            if ($started) {
                $wpdb->query('ROLLBACK');
            }
            $wpdb->suppress_errors($previous);
        }
    }

    /** Caller already owns the conversation row lock. Do not change business timestamps. */
    private static function refresh_goal($id, $event = array(), $row = null) {
        global $wpdb;
        $conversations = self::table('conversations');
        if (!is_array($row)) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT id,course,goal,goal_analysis_json FROM $conversations WHERE id=%d", (int) $id), ARRAY_A);
        }
        if ($wpdb->last_error !== '' || !is_array($row)) { return self::error('goal_read'); }
        if (($row['course'] ?? '') !== 'digital_marketing') { return true; }
        $meta = json_decode((string) ($row['goal_analysis_json'] ?? ''), true);
        $meta = is_array($meta) ? $meta : array();
        $session = isset($event['session']) && is_string($event['session']) ? $event['session'] : '';
        $incoming = self::clean_text($event['goal'] ?? '', 4000, true, $session);
        $cutoff = max(0, (int) ($meta['cleared_through_message_id'] ?? 0));
        $messages = self::table('messages');
        $history = $wpdb->get_results($wpdb->prepare("SELECT id,role,content,type FROM $messages WHERE conversation_id=%d AND role='visitor' AND id>%d ORDER BY id DESC LIMIT 200", (int) $id, 0), ARRAY_A);
        if ($wpdb->last_error !== '') { return self::error('goal_read'); }
        $history = is_array($history) ? array_reverse($history) : array();
        // Version 1 trusted model nulls. Recheck old cutoffs against actual visitor retractions.
        if($cutoff>0&&(int)($meta['version']??0)<TLIT_CB_Goals::VERSION){
            $before=array_values(array_filter($history,function($message)use($cutoff){return (int)$message['id']<=$cutoff;}));
            if(!TLIT_CB_Goals::confirms_retraction($before)){$cutoff=0;}
        }
        if($incoming!==''&&!TLIT_CB_Goals::is_fallback($incoming)){$cutoff=0;}
        $eligible=$cutoff>0?array_values(array_filter($history,function($message)use($cutoff){return (int)$message['id']>$cutoff;})):$history;
        $prior=$meta;$prior['summary']=(string)($row['goal']??'');
        $analysis = TLIT_CB_Goals::reconcile($eligible,$incoming,$prior);
        if($analysis['summary']===TLIT_CB_Goals::REVISED_SUMMARY&&TLIT_CB_Goals::confirms_retraction($eligible,$prior['summary'])){
            foreach($eligible as $message){$cutoff=max($cutoff,(int)($message['id']??0));}
        }
        // The analyzer emits only a bounded classification and excerpts, never credentials.
        $analysis['summary'] = self::clean_text($analysis['summary'], 4000, true, $session);
        $analysis['evidence'] = self::clean_text($analysis['evidence'] ?? '', 400, true, $session);
        if ($cutoff > 0) { $analysis['cleared_through_message_id'] = $cutoff; }
        if ($wpdb->query($wpdb->prepare("UPDATE $conversations SET goal=%s,goal_analysis_json=%s WHERE id=%d", $analysis['summary'], wp_json_encode($analysis), (int) $id)) === false) {
            return self::error('goal_write');
        }
        return true;
    }

    /** One bounded historical pass; no model calls, CRM events or timestamp mutations. */
    public static function backfill_goals($after_id = 0, $limit = 20) {
        global $wpdb;
        $table = self::table('conversations');
        $limit = min(50, max(1, (int) $limit));
        $result = array('last_id' => max(0, (int) $after_id), 'processed' => 0, 'has_more' => false);
        $previous = $wpdb->suppress_errors(true);
        $started = false;
        try {
            $stale="(goal_analysis_json='' OR COALESCE(JSON_EXTRACT(IF(JSON_VALID(goal_analysis_json),goal_analysis_json,'{}'),'$.version'),0)<".(int)TLIT_CB_Goals::VERSION.")";
            $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table WHERE course='digital_marketing' AND id>%d AND $stale ORDER BY id ASC LIMIT %d", $result['last_id'], $limit));
            if ($wpdb->last_error !== '') { return self::error('goal_read'); }
            foreach ((array) $ids as $id) {
                if ($wpdb->query('START TRANSACTION') === false) { return self::error('goal_write'); }
                $started = true;
                $row = $wpdb->get_row($wpdb->prepare("SELECT id,course,goal,goal_analysis_json FROM $table WHERE id=%d FOR UPDATE", (int) $id), ARRAY_A);
                if ($wpdb->last_error !== '') { return self::error('goal_read'); }
                // A new capture may have populated the analysis since the batch selected this ID.
                $analysis_meta=is_array($row)?json_decode($row['goal_analysis_json']??'',true):null;
                if (is_array($row) && ($row['course'] ?? '') === 'digital_marketing' && (int)($analysis_meta['version']??0)<TLIT_CB_Goals::VERSION) {
                    $saved = self::refresh_goal((int) $id, array(), $row);
                    if (is_wp_error($saved)) { return $saved; }
                    $result['processed']++;
                }
                if ($wpdb->query('COMMIT') === false) { return self::error('goal_write'); }
                $started = false;
                $result['last_id'] = max($result['last_id'], (int) $id);
            }
            $result['has_more'] = count((array) $ids) === $limit;
            return $result;
        } finally {
            if ($started) { $wpdb->query('ROLLBACK'); }
            $wpdb->suppress_errors($previous);
        }
    }

    private static function day_bound($value, $next_day = false) {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Kolkata'));
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d') !== $value) {
            return null;
        }
        if ($next_day) {
            $date = $date->modify('+1 day');
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function where($filters) {
        global $wpdb;
        $filters = is_array($filters) ? $filters : array();
        $clauses = array('1=1');
        if (isset($filters['search']) && self::scalar($filters['search']) !== '') {
            $search = '%' . $wpdb->esc_like(self::clean_text($filters['search'], 200)) . '%';
            $clauses[] = $wpdb->prepare('(name LIKE %s OR email LIKE %s OR phone LIKE %s)', $search, $search, $search);
        }
        foreach (array('course', 'language', 'status') as $column) {
            if (isset($filters[$column]) && self::scalar($filters[$column]) !== '') {
                $value = self::clean_text($filters[$column], $column === 'course' ? 64 : 32);
                if ($column === 'status' && !in_array($value, self::statuses(), true)) {
                    $clauses[] = '1=0';
                } else {
                    $clauses[] = $wpdb->prepare("$column=%s", $value);
                }
            }
        }
        foreach (array('has_phone', 'has_brochure') as $key) {
            if (isset($filters[$key]) && in_array($filters[$key], array('yes', 'no'), true)) {
                $clauses[] = $key === 'has_phone' ? ($filters[$key] === 'yes' ? "phone<>''" : "phone=''") : 'has_brochure=' . ($filters[$key] === 'yes' ? '1' : '0');
            }
        }
        foreach (array('date_from', 'date_to') as $key) {
            if (isset($filters[$key]) && self::scalar($filters[$key]) !== '') {
                $bound = self::day_bound($filters[$key], $key === 'date_to');
                $clauses[] = $bound === null ? '1=0' : $wpdb->prepare('created_at ' . ($key === 'date_from' ? '>=' : '<') . ' %s', $bound);
            }
        }
        if (isset($filters['max_id'])) {
            $clauses[] = $wpdb->prepare('id<=%d', max(0, (int) self::scalar($filters['max_id'])));
        }
        if (isset($filters['after_id'])) {
            $clauses[] = $wpdb->prepare('id>%d', max(0, (int) self::scalar($filters['after_id'])));
        }
        // Privacy hooks use exact email matching, never a contact-search substring.
        if (isset($filters['exact_email'])) {
            $clauses[] = $wpdb->prepare('email=%s', sanitize_email(self::scalar($filters['exact_email'])));
        }
        return implode(' AND ', $clauses);
    }

    public static function statuses() {
        return array('new', 'reviewed', 'contacted', 'archived');
    }

    public static function query($filters, $page = 1, $per_page = 25) {
        global $wpdb;
        self::$read_failed = false;
        $page = min(1000000, max(1, (int) $page));
        $per_page = min(200, max(1, (int) $per_page));
        $result = array('rows' => array(), 'total' => 0, 'page' => $page, 'per_page' => $per_page);
        $table = self::table('conversations');
        $where = self::where($filters);
        $order = is_array($filters) && isset($filters['export_order']) && $filters['export_order'] === 'id_asc' ? 'id ASC' : 'updated_at DESC,id DESC';
        $previous = $wpdb->suppress_errors(true);
        try {
            $total = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");
            if ($wpdb->last_error !== '') {
                self::$read_failed = true;
                $result['error'] = 'Conversations could not be loaded. Please retry.';
                return $result;
            }
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE $where ORDER BY $order LIMIT %d OFFSET %d", $per_page, ($page - 1) * $per_page), ARRAY_A);
            if ($wpdb->last_error !== '') {
                self::$read_failed = true;
                $result['error'] = 'Conversations could not be loaded. Please retry.';
                return $result;
            }
            $result['total'] = (int) $total;
            $result['rows'] = is_array($rows) ? $rows : array();
            return $result;
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    public static function get($id) {
        global $wpdb;
        self::$read_failed = false;
        if ((int) $id < 1) {
            return null;
        }
        $table = self::table('conversations');
        $previous = $wpdb->suppress_errors(true);
        try {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", (int) $id), ARRAY_A);
            self::$read_failed = $wpdb->last_error !== '';
            return is_array($row) ? $row : null;
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    /** Resolve a server-held chat token without exposing the token or trusting a submitted lead ID. */
    public static function for_session($token) {
        global $wpdb;
        self::$read_failed=false;
        if (!is_string($token) || !preg_match('/^[a-f0-9]{48}$/D', $token)) { return null; }
        $hash=hash_hmac('sha256', 'tlit-cb-session|' . $token, wp_salt('auth'));
        $previous=$wpdb->suppress_errors(true);
        try {
            $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('conversations').' WHERE session_hash=%s',$hash),ARRAY_A);
            self::$read_failed=$wpdb->last_error!=='';
            return self::$read_failed?null:$row;
        } finally { $wpdb->suppress_errors($previous); }
    }

    public static function snapshot_max_id($filters = array()) {
        global $wpdb;
        self::$read_failed = false;
        $table = self::table('conversations');
        $where = self::where($filters);
        $previous = $wpdb->suppress_errors(true);
        try {
            $id = $wpdb->get_var("SELECT MAX(id) FROM $table WHERE $where");
            self::$read_failed = $wpdb->last_error !== '';
            return self::$read_failed ? self::error('read') : (int) $id;
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    public static function messages($id) {
        global $wpdb;
        self::$read_failed = false;
        if ((int) $id < 1) {
            return array();
        }
        $table = self::table('messages');
        $previous = $wpdb->suppress_errors(true);
        try {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE conversation_id=%d ORDER BY created_at ASC,id ASC", (int) $id), ARRAY_A);
            self::$read_failed = $wpdb->last_error !== '';
            return is_array($rows) ? $rows : array();
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    public static function stats($filters = array()) {
        global $wpdb;
        self::$read_failed = false;
        $table = self::table('conversations');
        $where = self::where($filters);
        $result = array('total' => 0, 'with_phone' => 0, 'brochure' => 0, 'handoff' => 0);
        $previous = $wpdb->suppress_errors(true);
        try {
            $row = $wpdb->get_row("SELECT COUNT(*) AS total,COALESCE(SUM(phone<>''),0) AS with_phone,COALESCE(SUM(has_brochure=1),0) AS brochure,COALESCE(SUM(stage IN ('handoff','limit')),0) AS handoff FROM $table WHERE $where", ARRAY_A);
            if ($wpdb->last_error !== '') {
                self::$read_failed = true;
                $result['error'] = 'Conversation totals could not be loaded. Please retry.';
                return $result;
            }
            foreach ($result as $key => $value) {
                $result[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
            }
            return $result;
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    public static function distinct_values($column) {
        global $wpdb;
        self::$read_failed = false;
        if (!in_array($column, array('course', 'language'), true)) {
            return array();
        }
        $table = self::table('conversations');
        $previous = $wpdb->suppress_errors(true);
        try {
            $values = (array) $wpdb->get_col("SELECT DISTINCT $column FROM $table WHERE $column<>'' ORDER BY $column ASC LIMIT 100");
            self::$read_failed = $wpdb->last_error !== '';
            return $values;
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    public static function update_status($id, $status) {
        global $wpdb;
        if ((int) $id < 1 || !in_array($status, self::statuses(), true)) {
            return false;
        }
        $table = self::table('conversations');
        $previous = $wpdb->suppress_errors(true);
        try {
            if (!self::get($id)) {
                return false;
            }
            return $wpdb->query($wpdb->prepare("UPDATE $table SET status=%s,updated_at=%s WHERE id=%d", $status, gmdate('Y-m-d H:i:s'), (int) $id)) !== false;
        } finally {
            $wpdb->suppress_errors($previous);
        }
    }

    /** Internal linkage only; visitor events cannot supply qualification or CRM state. */
    public static function with_locked_conversation($id, $callback) {
        global $wpdb;
        $previous=$wpdb->suppress_errors(true);$started=false;
        try {
            if ($wpdb->query('START TRANSACTION')===false) { return self::error('qualification_lock'); }
            $started=true;
            $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table('conversations').' WHERE id=%d FOR UPDATE',(int)$id),ARRAY_A);
            if ($wpdb->last_error!=='' || !$row) { return self::error('qualification_read'); }
            $result=call_user_func($callback,$row);
            if (is_wp_error($result)) { return $result; }
            if ($wpdb->query('COMMIT')===false) { return self::error('qualification_commit'); }
            $started=false;
            return $result;
        } catch (Throwable $error) {
            return self::error('qualification_write');
        } finally {
            if ($started) { $wpdb->query('ROLLBACK'); }
            $wpdb->suppress_errors($previous);
        }
    }

    public static function update_qualification($id, $lead_id, $status, $note) {
        global $wpdb;
        $statuses=is_callable(array('TLIT_CB_Qualification','statuses'))?array_keys(TLIT_CB_Qualification::statuses()):array('new','junk','potential','high_potential','intended_to_join','enrolled');
        if ((int) $id < 1 || !in_array($status, $statuses, true)) { return false; }
        $table = self::table('conversations');
        $previous = $wpdb->suppress_errors(true);
        try {
            return $wpdb->query($wpdb->prepare("UPDATE $table SET lead_id=%d,qualification_status=%s,qualification_note=%s WHERE id=%d", max(0, (int) $lead_id), $status, self::clean_text($note, 4000, true), (int) $id)) !== false;
        } finally { $wpdb->suppress_errors($previous); }
    }

    public static function privacy_exporters($exporters) {
        $exporters['tlit-conversations'] = array('exporter_friendly_name' => 'TechLeads IT chatbot conversations', 'callback' => array(__CLASS__, 'privacy_export'));
        return $exporters;
    }

    public static function privacy_erasers($erasers) {
        $erasers['tlit-conversations'] = array('eraser_friendly_name' => 'TechLeads IT chatbot conversations', 'callback' => array(__CLASS__, 'privacy_erase'));
        return $erasers;
    }

    public static function privacy_export($email_address, $page = 1) {
        if (!is_email($email_address)) {
            return array('data' => array(), 'done' => true);
        }
        $result = self::query(array('exact_email' => $email_address), $page, self::PRIVACY_PAGE_SIZE);
        if (isset($result['error'])) {
            return self::error('privacy_export');
        }
        $data = array();
        foreach ($result['rows'] as $row) {
            $fields = array();
            if (class_exists('TLIT_CB_Qualification')) {
                $lead=TLIT_CB_Qualification::lead($row);
                if ($lead) { $row['qualification_status']=$lead->lead_status; $row['qualification_note']=$lead->advisor_note; }
            }
            foreach (array('name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'course' => 'Course', 'source_url' => 'Source page', 'language' => 'Language', 'goal' => 'Goal', 'stage' => 'Chat stage', 'status' => 'Review status', 'lead_id' => 'Lead reference', 'qualification_status' => 'Lead qualification', 'qualification_note' => 'Counsellor note', 'has_brochure' => 'Brochure downloaded', 'answer_count' => 'Answers', 'created_at' => 'Created (UTC)', 'updated_at' => 'Updated (UTC)') as $key => $label) {
                $fields[] = array('name' => $label, 'value' => isset($row[$key]) ? (string) $row[$key] : '');
            }
            $messages = self::messages($row['id']);
            if (self::read_error()) {
                return self::error('privacy_export');
            }
            foreach ($messages as $message) {
                $fields[] = array('name' => $message['created_at'] . ' UTC — ' . $message['role'], 'value' => $message['content']);
            }
            foreach (self::tracking(isset($row['tracking_json']) ? json_decode($row['tracking_json'], true) : array()) as $key => $value) {
                $fields[] = array('name' => 'Attribution: ' . $key, 'value' => $value);
            }
            $data[] = array('group_id' => 'tlit-chatbot-conversations', 'group_label' => 'Chatbot conversations', 'item_id' => 'tlit-conversation-' . $row['id'], 'data' => $fields);
        }
        return array('data' => $data, 'done' => $result['page'] * $result['per_page'] >= $result['total']);
    }

    public static function privacy_erase($email_address, $page = 1) {
        global $wpdb;
        $result = array('items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true);
        if (!is_email($email_address)) {
            return $result;
        }
        // Always take the first page: deletions shift remaining rows towards the start.
        $found = self::query(array('exact_email' => $email_address), 1, self::PRIVACY_PAGE_SIZE);
        if (isset($found['error'])) {
            return self::error('privacy_erase');
        }
        if (!$found['rows']) {
            return $result;
        }
        $ids = array_map('intval', array_column($found['rows'], 'id'));
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $conversations = self::table('conversations');
        $messages = self::table('messages');
        $events = self::table('events');
        $previous = $wpdb->suppress_errors(true);
        $started = false;
        try {
            if ($wpdb->query('START TRANSACTION') === false) {
                return self::error('privacy_erase');
            }
            $started = true;
            // Lock parent rows before deleting children, consistent with capture's write order.
            if ($wpdb->query($wpdb->prepare("SELECT id FROM $conversations WHERE id IN ($placeholders) FOR UPDATE", $ids)) === false) {
                return self::error('privacy_erase');
            }
            foreach ($ids as $id) {
                // Integration payload deletion must use this same database connection and
                // transaction. A failure rolls back the entire batch, including prior IDs.
                $ready = apply_filters('tlit_cb_before_conversation_erase', true, $id);
                if ($ready !== true) {
                    return self::error('privacy_erase');
                }
            }
            if ($wpdb->query($wpdb->prepare("DELETE FROM $messages WHERE conversation_id IN ($placeholders)", $ids)) === false || $wpdb->query($wpdb->prepare("DELETE FROM $events WHERE conversation_id IN ($placeholders)", $ids)) === false || $wpdb->query($wpdb->prepare("DELETE FROM $conversations WHERE id IN ($placeholders)", $ids)) === false || $wpdb->query('COMMIT') === false) {
                return self::error('privacy_erase');
            }
            $started = false;
            $result['items_removed'] = true;
            $result['done'] = $found['total'] <= self::PRIVACY_PAGE_SIZE;
            foreach ($ids as $id) {
                do_action('tlit_cb_conversation_erased', $id);
            }
            return $result;
        } catch (Throwable $error) {
            // Never expose integration exception details or treat a failed cleanup as done.
            return self::error('privacy_erase');
        } finally {
            if ($started) {
                $wpdb->query('ROLLBACK');
            }
            $wpdb->suppress_errors($previous);
        }
    }
}

add_filter('wp_privacy_personal_data_exporters', array('TLIT_CB_Store', 'privacy_exporters'));
add_filter('wp_privacy_personal_data_erasers', array('TLIT_CB_Store', 'privacy_erasers'));
