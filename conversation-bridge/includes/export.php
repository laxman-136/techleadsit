<?php
/** Private, bounded Office Open XML conversation exports. */
if (!defined('ABSPATH')) {
    exit;
}

final class TLIT_CB_Export
{
    const MAX_SELECTED = 200;
    const MAX_FILTERED = 2000;
    const MAX_ARCHIVE_BYTES = 134217728; // Includes uncompressed document XML (128 MiB).
    const MAX_DOCUMENT_BYTES = 4194304;

    private static $temporary = array();
    private static $document_bytes = array();
    private static $shutdown_registered = false;

    public static function register()
    {
        add_action('admin_post_tlit_cb_export', array(__CLASS__, 'download'));
    }

    public static function download()
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to export conversations.', 'Export denied', array('response' => 403));
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_die('Conversation exports require a POST request.', 'Export denied', array('response' => 405));
            return;
        }
        $nonce = isset($_POST['_wpnonce']) && is_string($_POST['_wpnonce']) ? wp_unslash($_POST['_wpnonce']) : '';
        if (!wp_verify_nonce($nonce, 'tlit_cb_export')) {
            wp_die('The export link expired. Reload the conversations page and try again.', 'Export denied', array('response' => 403));
            return;
        }

        try {
            $download = self::build_download(wp_unslash($_POST));
            if (headers_sent()) {
                throw new RuntimeException('The export could not start because output was already sent.');
            }
            $handle = fopen($download['path'], 'rb');
            if ($handle === false) {
                throw new RuntimeException('The temporary export could not be read.');
            }
            // A disconnected browser must not leave contact data on disk.
            ignore_user_abort(true);
            while (ob_get_level() > 0) {
                if (!@ob_end_clean()) {
                    break;
                }
            }
            nocache_headers();
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            header('Content-Type: ' . $download['mime']);
            header('Content-Disposition: attachment; filename="' . $download['filename'] . '"');
            header('Content-Length: ' . filesize($download['path']));
            try {
                while (!feof($handle)) {
                    $chunk = fread($handle, 65536);
                    if ($chunk === false) {
                        throw new RuntimeException('The export download was interrupted.');
                    }
                    echo $chunk;
                }
            } finally {
                fclose($handle);
            }
            self::cleanup();
            exit;
        } catch (Throwable $error) {
            self::cleanup();
            // Only our fixed, non-sensitive validation messages may reach the browser.
            $message = $error instanceof InvalidArgumentException ? $error->getMessage() : 'The export could not be generated. Confirm PHP ZIP support and private temporary storage are available, then retry with fewer conversations.';
            wp_die(esc_html($message), 'Conversation export failed', array('response' => 400));
        }
    }

    /** Internal builder. Authorization and nonce checks are performed by download(). */
    public static function build_download(array $request)
    {
        self::require_zip();
        $scope = isset($request['scope']) && is_string($request['scope']) ? $request['scope'] : '';
        if ($scope === 'single') {
            $id = self::positive_id($request['id'] ?? null);
            $row = TLIT_CB_Store::get($id);
            self::check_storage_read();
            if (!$row) {
                throw new InvalidArgumentException('The requested conversation no longer exists.');
            }
            return array(
                'path' => self::create_docx($row, self::stored_messages($id)),
                'filename' => self::document_filename($row),
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            );
        }
        if ($scope === 'selected') {
            if (empty($request['ids']) || !is_array($request['ids']) || count($request['ids']) > self::MAX_SELECTED) {
                throw new InvalidArgumentException('Select between 1 and 200 conversations to export.');
            }
            $ids = array_values(array_unique(array_map(array(__CLASS__, 'positive_id'), $request['ids'])));
            $rows = (static function () use ($ids) {
                foreach ($ids as $id) {
                    $row = TLIT_CB_Store::get($id);
                    self::check_storage_read();
                    if (!$row) {
                        throw new InvalidArgumentException('A selected conversation no longer exists. Refresh the list and try again.');
                    }
                    yield $row;
                }
            })();
        } elseif ($scope === 'filtered') {
            if (isset($request['filters']) && !is_array($request['filters'])) {
                throw new InvalidArgumentException('The export filters are invalid.');
            }
            $filters = array_intersect_key($request['filters'] ?? array(), array_fill_keys(array('date_from', 'date_to', 'status', 'search', 'language', 'has_phone', 'has_brochure', 'course', 'max_id'), true));
            foreach ($filters as $value) {
                if (!is_scalar($value)) {
                    throw new InvalidArgumentException('The export filters are invalid.');
                }
            }
            $rows = self::filtered_rows($filters);
        } else {
            throw new InvalidArgumentException('Choose a single, selected, or filtered conversation export.');
        }
        return array(
            'path' => self::create_archive($rows),
            'filename' => 'conversations-' . gmdate('Ymd-His') . '.zip',
            'mime' => 'application/zip',
        );
    }

    private static function filtered_rows(array $filters)
    {
        // Keyset pagination with a fixed upper ID prevents inserts, deletions,
        // or updated-at changes from shifting already-exported page boundaries.
        $snapshot = TLIT_CB_Store::snapshot_max_id($filters);
        if (!is_scalar($snapshot)) {
            throw new RuntimeException('Cannot read the export snapshot.');
        }
        if ((int) $snapshot < 1) {
            throw new InvalidArgumentException('No conversations match the selected filters.');
        }
        $filters['max_id'] = isset($filters['max_id']) && (int) $filters['max_id'] > 0 ? min((int) $filters['max_id'], (int) $snapshot) : (int) $snapshot;
        $filters['export_order'] = 'id_asc';
        $filters['after_id'] = 0;
        $result = TLIT_CB_Store::query($filters, 1, 100);
        if (!empty($result['error'])) {
            throw new RuntimeException('Cannot query conversations.');
        }
        if ((int) ($result['total'] ?? 0) > self::MAX_FILTERED) {
            throw new InvalidArgumentException('This filter matches more than 2,000 conversations. Narrow the date range or filters and export again.');
        }
        $first_rows = $result['rows'] ?? array();
        if (!$first_rows) {
            throw new InvalidArgumentException('No conversations match the selected filters.');
        }
        $count = 0;
        do {
            foreach ($result['rows'] ?? array() as $row) {
                $id = (int) self::field($row, 'id');
                if ($id <= $filters['after_id'] || $id > $filters['max_id']) {
                    throw new RuntimeException('Conversation export cursor is invalid.');
                }
                if (++$count > self::MAX_FILTERED) {
                    throw new InvalidArgumentException('Export up to 2,000 conversations at a time. Narrow the filters and try again.');
                }
                $filters['after_id'] = $id;
                yield $row;
            }
            if (count($result['rows'] ?? array()) < 100) {
                break;
            }
            $result = TLIT_CB_Store::query($filters, 1, 100);
            if (!empty($result['error'])) {
                throw new RuntimeException('Cannot query conversations.');
            }
        } while (true);
    }

    /** Returns a private .tmp path containing a complete DOCX. Caller must cleanup(). */
    public static function create_docx($row, $messages)
    {
        self::require_zip();
        $path = self::temporary_path();
        $zip = new ZipArchive();
        $opened = false;
        try {
            $parts = self::document_parts($row, $messages);
            $size = array_sum(array_map('strlen', $parts));
            if ($size > self::MAX_DOCUMENT_BYTES) {
                throw new InvalidArgumentException('A conversation is too large to export safely. Please contact the site administrator.');
            }
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Cannot open temporary DOCX.');
            }
            $opened = true;
            foreach ($parts as $name => $xml) {
                if (!$zip->addFromString($name, $xml)) {
                    throw new RuntimeException('Cannot write DOCX part.');
                }
            }
            $closed = $zip->close();
            $opened = false;
            if (!$closed || !@chmod($path, 0600)) {
                throw new RuntimeException('Cannot finish DOCX.');
            }
            self::$document_bytes[$path] = $size;
            return $path;
        } catch (Throwable $error) {
            if ($opened) {
                @$zip->close();
            }
            self::remove_temporary($path);
            throw $error;
        }
    }

    /** Rows may be a paginated generator; only one transcript is loaded at a time. */
    public static function create_archive(iterable $rows, $messages_callback = null)
    {
        self::require_zip();
        $archive_path = null;
        $manifest_path = null;
        $docx_paths = array();
        $zip = new ZipArchive();
        $opened = false;
        $manifest = null;
        try {
            $archive_path = self::temporary_path();
            $manifest_path = self::temporary_path();
            if ($zip->open($archive_path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Cannot open temporary archive.');
            }
            $opened = true;
            $manifest = fopen($manifest_path, 'wb');
            if ($manifest === false) {
                throw new RuntimeException('Cannot create archive manifest.');
            }
            if (fwrite($manifest, "\xEF\xBB\xBF") !== 3) {
                throw new RuntimeException('Cannot write archive manifest.');
            }
            self::write_csv($manifest, array('File', 'Conversation ID', 'Name', 'Email', 'Phone', 'Language', 'Goal', 'Status', 'Stage', 'Course', 'Source page', 'Brochure requested', 'Answer count', 'Created (site time)', 'Updated (site time)'));
            $count = 0;
            $bytes = 0;
            $seen = array();
            foreach ($rows as $row) {
                if (++$count > self::MAX_FILTERED) {
                    throw new InvalidArgumentException('Export up to 2,000 conversations at a time. Narrow the filters and try again.');
                }
                $id = self::positive_id(self::field($row, 'id'));
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $messages = $messages_callback ? call_user_func($messages_callback, $id) : self::stored_messages($id);
                $path = self::create_docx($row, $messages);
                $docx_paths[] = $path;
                $bytes += self::$document_bytes[$path] + filesize($path);
                if ($bytes > self::MAX_ARCHIVE_BYTES) {
                    throw new InvalidArgumentException('The export exceeds the 128 MiB safety limit. Narrow the date range or select fewer conversations.');
                }
                $filename = self::document_filename($row);
                if (!$zip->addFile($path, $filename)) {
                    throw new RuntimeException('Cannot add conversation to archive.');
                }
                $values = array($filename, (string) $id);
                foreach (array('name', 'email', 'phone', 'language', 'goal', 'status', 'stage', 'course') as $key) {
                    $values[] = self::field($row, $key);
                }
                $values[] = self::source_page(self::field($row, 'source_url'));
                $values[] = self::field($row, 'has_brochure') ? 'Yes' : 'No';
                $values[] = (string) (int) self::field($row, 'answer_count');
                $values[] = self::display_date(self::field($row, 'created_at'));
                $values[] = self::display_date(self::field($row, 'updated_at'));
                self::write_csv($manifest, array_map(array(__CLASS__, 'csv_value'), $values));
                if ($bytes + ftell($manifest) > self::MAX_ARCHIVE_BYTES) {
                    throw new InvalidArgumentException('The export exceeds the 128 MiB safety limit. Select fewer conversations.');
                }
            }
            if (!$count) {
                throw new InvalidArgumentException('There are no conversations to export.');
            }
            fclose($manifest);
            $manifest = null;
            if (!$zip->addFile($manifest_path, 'manifest.csv')) {
                throw new RuntimeException('Cannot finish archive.');
            }
            $closed = $zip->close();
            $opened = false;
            if (!$closed || !@chmod($archive_path, 0600)) {
                throw new RuntimeException('Cannot finish archive.');
            }
            foreach ($docx_paths as $path) {
                self::remove_temporary($path);
            }
            self::remove_temporary($manifest_path);
            return $archive_path;
        } catch (Throwable $error) {
            if (is_resource($manifest)) {
                fclose($manifest);
            }
            if ($opened) {
                @$zip->close();
            }
            foreach ($docx_paths as $path) {
                self::remove_temporary($path);
            }
            self::remove_temporary($manifest_path);
            self::remove_temporary($archive_path);
            throw $error;
        }
    }

    public static function cleanup()
    {
        foreach (array_keys(self::$temporary) as $path) {
            self::remove_temporary($path);
        }
    }

    private static function require_zip()
    {
        if (!class_exists('ZipArchive')) {
            throw new InvalidArgumentException('Conversation export requires the PHP ZIP extension. Ask the site administrator to enable ZipArchive.');
        }
    }

    private static function check_storage_read()
    {
        if (TLIT_CB_Store::read_error()) {
            throw new RuntimeException('The conversation could not be read.');
        }
    }

    private static function stored_messages($id)
    {
        $messages = TLIT_CB_Store::messages($id);
        self::check_storage_read();
        return $messages;
    }

    private static function temporary_path()
    {
        if (!function_exists('wp_tempnam')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $directory = null;
        foreach (array(get_temp_dir(), sys_get_temp_dir()) as $candidate) {
            $real = realpath($candidate);
            // Windows ACL checks can report is_writable() false even when a
            // process can create files; wp_tempnam below is the actual check.
            if ($real === false || !is_dir($real)) {
                continue;
            }
            $private = true;
            foreach (array(ABSPATH, defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : '', $_SERVER['DOCUMENT_ROOT'] ?? '') as $web_root) {
                $web_root = $web_root !== '' ? realpath($web_root) : false;
                if ($web_root !== false && self::inside_path($real, $web_root)) {
                    $private = false;
                }
            }
            if ($private) {
                $directory = $real;
                break;
            }
        }
        if ($directory === null) {
            throw new RuntimeException('A private temporary directory is required.');
        }
        $path = wp_tempnam('tlit-conversation-export', rtrim($directory, '/\\') . DIRECTORY_SEPARATOR);
        if (!$path || !is_file($path) || !self::inside_path(realpath($path), $directory)) {
            if ($path && is_file($path)) {
                @unlink($path);
            }
            throw new RuntimeException('Cannot create private temporary file.');
        }
        self::$temporary[$path] = true;
        if (!@chmod($path, 0600)) {
            self::remove_temporary($path);
            throw new RuntimeException('Cannot protect temporary export.');
        }
        if (!self::$shutdown_registered) {
            register_shutdown_function(array(__CLASS__, 'cleanup'));
            self::$shutdown_registered = true;
        }
        return $path;
    }

    private static function inside_path($path, $root)
    {
        $path = str_replace('\\', '/', (string) $path);
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
            $root = strtolower($root);
        }
        return $path === $root || strpos($path, $root . '/') === 0;
    }

    private static function remove_temporary($path)
    {
        if (isset(self::$temporary[$path])) {
            if (!is_file($path) || @unlink($path)) {
                unset(self::$temporary[$path], self::$document_bytes[$path]);
            }
        }
    }

    private static function positive_id($value)
    {
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]*$/D', (string) $value) || (float) $value > PHP_INT_MAX) {
            throw new InvalidArgumentException('The conversation ID is invalid.');
        }
        return (int) $value;
    }

    private static function field($row, $key)
    {
        $value = is_object($row) ? ($row->$key ?? '') : ($row[$key] ?? '');
        return is_scalar($value) ? (string) $value : '';
    }

    private static function document_filename($row)
    {
        $id = self::positive_id(self::field($row, 'id'));
        $timestamp = self::utc_timestamp(self::field($row, 'created_at'));
        return 'conversation-' . $id . '-' . ($timestamp !== false ? gmdate('Ymd', $timestamp) : 'undated') . '.docx';
    }

    private static function utc_timestamp($date)
    {
        if (!$date) {
            return false;
        }
        try {
            return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->getTimestamp();
        } catch (Exception $error) {
            return false;
        }
    }

    private static function display_date($date)
    {
        $timestamp = self::utc_timestamp($date);
        return $timestamp !== false ? wp_date('d M Y, H:i:s T', $timestamp) : 'Not recorded';
    }

    private static function source_page($url)
    {
        $parts = parse_url((string) $url);
        if (!$parts || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), array('https', 'http'), true)) {
            return '';
        }
        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . (int) $parts['port'] : '') . ($parts['path'] ?? '/');
    }

    private static function safe_text($value)
    {
        $text = (string) $value;
        if (!preg_match('//u', $text)) {
            $text = function_exists('iconv') ? (string) iconv('UTF-8', 'UTF-8//IGNORE', $text) : '';
        }
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);
        $text = preg_replace('/\b(?:sk-[A-Za-z0-9_-]{16,}|Bearer\s+[A-Za-z0-9._~+\/-]{12,})\b/i', '[redacted credential]', $text);
        $text = preg_replace('/\b((?:api[_ -]?key|api[_ -]?secret|session[_ -]?token|access[_ -]?token|client[_ -]?secret)\s*[=:]\s*)["\x27]?[^\s,;"\x27]+/i', '$1[redacted]', $text);
        $text = preg_replace('/\b((?:OTP|one[- ]time (?:password|code)|verification code)\s*(?:is\s*|[=:]\s*)?)[0-9]{4,8}\b/i', '$1[redacted]', $text);
        return $text;
    }

    private static function xml($text)
    {
        return htmlspecialchars(self::safe_text($text), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function csv_value($text)
    {
        $text = self::safe_text($text);
        // Excel evaluates +phone numbers too; prefix the entire original cell.
        // Also check after whitespace/control characters to block hidden formulas.
        if (preg_match('/^[\s\x00-\x20\x{FEFF}\x{200B}]*[=+@-]/u', $text) || preg_match('/^[\t\r\n]/', $text)) {
            $text = "'" . $text;
        }
        return $text;
    }

    private static function write_csv($handle, array $values)
    {
        if (fputcsv($handle, $values, ',', '"', '') === false) {
            throw new RuntimeException('Cannot write archive manifest.');
        }
    }

    private static function paragraph($text, $style = '', $language = 'en-IN')
    {
        $properties = $style !== '' ? '<w:pPr><w:pStyle w:val="' . self::xml($style) . '"/></w:pPr>' : '';
        return '<w:p>' . $properties . '<w:r><w:rPr><w:lang w:val="' . self::xml($language) . '"/></w:rPr><w:t xml:space="preserve">' . self::xml($text) . '</w:t></w:r></w:p>';
    }

    private static function document_parts($row, $messages)
    {
        $id = self::positive_id(self::field($row, 'id'));
        $language = array('telugu' => 'te-IN', 'hindi' => 'hi-IN', 'te' => 'te-IN', 'hi' => 'hi-IN')[strtolower(self::field($row, 'language'))] ?? 'en-IN';
        $body = self::paragraph('TECHLEADS IT', 'Brand');
        $body .= self::paragraph('Conversation ' . $id, 'Title');
        $body .= self::paragraph('Lead details', 'Heading1');
        $labels = array('name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'language' => 'Language', 'goal' => 'Goal', 'status' => 'Status', 'stage' => 'Stage', 'course' => 'Course');
        $details = array();
        foreach ($labels as $key => $label) {
            $details[$label] = self::field($row, $key) !== '' ? self::field($row, $key) : 'Not recorded';
        }
        $analysis = json_decode(self::field($row, 'goal_analysis_json'), true);
        if (is_array($analysis)) {
            $status = self::field($analysis, 'status');
            $statuses = array('stated' => 'Stated in chat', 'inferred' => 'Inferred from chat', 'interest' => 'Course interest', 'insufficient' => 'More context needed');
            if (isset($statuses[$status])) {
                $details['Goal analysis'] = $statuses[$status];
            }
            if (self::field($analysis, 'evidence') !== '') {
                $details['Goal evidence'] = self::field($analysis, 'evidence');
            }
        }
        $details['Source page'] = self::source_page(self::field($row, 'source_url')) ?: 'Not recorded';
        $details['Brochure requested'] = self::field($row, 'has_brochure') ? 'Yes' : 'No';
        $details['Answers provided'] = (string) (int) self::field($row, 'answer_count');
        $details['Created'] = self::display_date(self::field($row, 'created_at'));
        $details['Last updated'] = self::display_date(self::field($row, 'updated_at'));
        $body .= '<w:tbl><w:tblPr><w:tblW w:w="9000" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="70" w:type="dxa"/><w:left w:w="100" w:type="dxa"/><w:bottom w:w="70" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid><w:gridCol w:w="2400"/><w:gridCol w:w="6600"/></w:tblGrid>';
        foreach ($details as $label => $value) {
            $body .= '<w:tr><w:trPr><w:cantSplit/></w:trPr><w:tc><w:tcPr><w:tcW w:w="2400" w:type="dxa"/><w:shd w:val="clear" w:color="auto" w:fill="F0F4F8"/></w:tcPr>' . self::paragraph($label, 'MetadataLabel') . '</w:tc><w:tc><w:tcPr><w:tcW w:w="6600" w:type="dxa"/></w:tcPr>' . self::paragraph($value, 'Metadata', $language) . '</w:tc></w:tr>';
        }
        $body .= '</w:tbl>' . self::paragraph('Conversation transcript', 'Heading1');
        $count = 0;
        foreach ($messages as $message) {
            $role = strtolower(self::field($message, 'role'));
            $type = strtolower(self::field($message, 'type'));
            if (!in_array($role, array('user', 'visitor', 'assistant', 'system'), true) || preg_match('/(?:otp|auth|secret|token|credential)/i', $type)) {
                continue;
            }
            $content = self::field($message, 'content');
            if ($content === '') {
                continue;
            }
            if (strlen($content) > self::MAX_DOCUMENT_BYTES || strlen($body) > self::MAX_DOCUMENT_BYTES) {
                throw new InvalidArgumentException('A conversation is too large to export safely.');
            }
            $speaker = array('user' => 'Visitor', 'visitor' => 'Visitor', 'assistant' => 'Lakshmi / Assistant', 'system' => 'System')[$role];
            $body .= self::paragraph($speaker . ' | ' . self::display_date(self::field($message, 'created_at')), 'Speaker');
            foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
                $body .= self::paragraph($line, 'Transcript', $language);
            }
            ++$count;
        }
        if (!$count) {
            $body .= self::paragraph('No transcript messages have been recorded.', 'Transcript');
        }
        $body .= '<w:sectPr><w:footerReference w:type="default" r:id="rIdFooter"/><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1440" w:bottom="1134" w:left="1440" w:header="567" w:footer="567" w:gutter="0"/></w:sectPr>';
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $r = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $created = self::utc_timestamp(self::field($row, 'created_at'));
        $updated = self::utc_timestamp(self::field($row, 'updated_at'));
        $dates = ($created !== false ? '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z', $created) . '</dcterms:created>' : '') . ($updated !== false ? '<dcterms:modified xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z', $updated) . '</dcterms:modified>' : '');
        return array(
            '[Content_Types].xml' => $xml . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>',
            '_rels/.rels' => $xml . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="' . $r . '/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="' . $r . '/extended-properties" Target="docProps/app.xml"/></Relationships>',
            'word/document.xml' => $xml . '<w:document xmlns:w="' . $w . '" xmlns:r="' . $r . '"><w:body>' . $body . '</w:body></w:document>',
            'word/_rels/document.xml.rels' => $xml . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdStyles" Type="' . $r . '/styles" Target="styles.xml"/><Relationship Id="rIdSettings" Type="' . $r . '/settings" Target="settings.xml"/><Relationship Id="rIdFooter" Type="' . $r . '/footer" Target="footer1.xml"/></Relationships>',
            'word/styles.xml' => self::styles_xml($xml, $w),
            'word/settings.xml' => $xml . '<w:settings xmlns:w="' . $w . '"><w:defaultTabStop w:val="720"/><w:updateFields w:val="true"/></w:settings>',
            'word/footer1.xml' => $xml . '<w:ftr xmlns:w="' . $w . '"><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:color w:val="65748B"/><w:sz w:val="18"/></w:rPr><w:t xml:space="preserve">TechLeads IT | Conversation ' . $id . ' | Page </w:t></w:r><w:fldSimple w:instr="PAGE"><w:r><w:t>1</w:t></w:r></w:fldSimple></w:p></w:ftr>',
            'docProps/core.xml' => $xml . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Conversation ' . $id . '</dc:title><dc:creator>TechLeads IT</dc:creator><dc:subject>Lead conversation transcript</dc:subject>' . $dates . '</cp:coreProperties>',
            'docProps/app.xml' => $xml . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>TechLeads IT Conversation Bridge</Application></Properties>',
        );
    }

    private static function styles_xml($xml, $w)
    {
        return $xml . '<w:styles xmlns:w="' . $w . '"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:eastAsia="Nirmala UI" w:cs="Nirmala UI"/><w:color w:val="223044"/><w:sz w:val="22"/><w:szCs w:val="22"/><w:lang w:val="en-IN"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:widowControl/><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Brand"><w:name w:val="Brand"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:after="100"/></w:pPr><w:rPr><w:b/><w:color w:val="126DB5"/><w:sz w:val="24"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:after="280"/></w:pPr><w:rPr><w:b/><w:sz w:val="42"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="260" w:after="140"/><w:outlineLvl w:val="0"/></w:pPr><w:rPr><w:b/><w:color w:val="126DB5"/><w:sz w:val="28"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Metadata"><w:name w:val="Metadata"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="0"/></w:pPr><w:rPr><w:sz w:val="20"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="MetadataLabel"><w:name w:val="Metadata Label"/><w:basedOn w:val="Metadata"/><w:rPr><w:b/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Speaker"><w:name w:val="Speaker"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="220" w:after="60"/></w:pPr><w:rPr><w:b/><w:color w:val="126DB5"/><w:sz w:val="20"/></w:rPr></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Transcript"><w:name w:val="Transcript"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="100"/></w:pPr></w:style></w:styles>';
    }
}
