<?php
/**
 * Mealing Online - Comprehensive Logging System
 * Logs all database queries, user actions, and system errors
 * Usage: require_once '/Applications/XAMPP/xamppfiles/htdocs/mealing_online/includes/logging.php';
 */

function init_logging(): void {
    global $logs_dir, $application_log, $current_user_id, $current_user_name;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $logs_dir = __DIR__ . '/../logs';
    if (!is_dir($logs_dir)) {
        if (!@mkdir($logs_dir, 0750, true) && !is_dir($logs_dir)) {
            @error_log('Unable to create application logs directory: ' . $logs_dir);
        }
    }

    $application_log = [];
    $current_user_id = $_SESSION['user_id'] ?? null;
    $current_user_name = $_SESSION['username'] ?? 'Guest';
    $GLOBALS['audit_request_id'] = bin2hex(random_bytes(16));
    $GLOBALS['audit_request_context'] = audit_current_context();

    audit_import_legacy_logs();

    if (empty($GLOBALS['audit_shutdown_registered'])) {
        register_shutdown_function(static function (): void {
            $last_error = error_get_last();
            $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            $fatal_error = $last_error !== null && in_array($last_error['type'], $fatal_types, true)
                ? [
                    'message' => $last_error['message'],
                    'file' => $last_error['file'],
                    'line' => $last_error['line'],
                ]
                : null;

            audit_log_event('request.completed', [
                'status' => http_response_code(),
                'fatal_error' => $fatal_error,
            ]);
        });
        $GLOBALS['audit_shutdown_registered'] = true;
    }
}

function audit_current_context(): array {
    $house_id = filter_var($_SESSION['house_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$house_id || $house_id < 1) {
        $house_id = null;
    }

    $house_name = null;
    if ($house_id !== null) {
        $house_name = audit_house_name($house_id);
        $_SESSION['house_name'] = $house_name;
    }

    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'house_id' => $house_id,
        'house_name' => $house_name,
    ];
}

function audit_house_name(int $house_id, ?string $preferred_name = null): string {
    if (is_string($preferred_name) && trim($preferred_name) !== '') {
        $GLOBALS['audit_house_names'][$house_id] = trim($preferred_name);
        return trim($preferred_name);
    }

    if (isset($GLOBALS['audit_house_names'][$house_id])) {
        return $GLOBALS['audit_house_names'][$house_id];
    }

    if (function_exists('getConnection')) {
        $conn = getConnection();
        $stmt = mysqli_prepare($conn, 'SELECT house_name FROM houses WHERE house_id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $house_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $house = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (is_string($house['house_name'] ?? null) && trim($house['house_name']) !== '') {
                $GLOBALS['audit_house_names'][$house_id] = trim($house['house_name']);
                return $GLOBALS['audit_house_names'][$house_id];
            }
        }
    }

    return 'House ' . $house_id;
}

function audit_house_directory(?string $house_name, ?int $house_id): string {
    if ($house_id === null) {
        return 'system';
    }

    $safe_name = preg_replace('~[\/\\\\\x00-\x1F\x7F<>:"|?*]~u', '_', trim($house_name ?? ''));
    $safe_name = trim(is_string($safe_name) ? $safe_name : '', " .\t\n\r\0\x0B");
    if ($safe_name === '' || $safe_name === '.' || $safe_name === '..') {
        $safe_name = 'House';
    }

    return $safe_name . ' (House ' . $house_id . ')';
}

function audit_clean_text(mixed $value): string {
    if ($value === null) {
        return 'none';
    }
    if (is_bool($value)) {
        return $value ? 'yes' : 'no';
    }
    if (is_array($value)) {
        return '[details below]';
    }

    $text = (string) $value;
    return trim((string) preg_replace('/[\r\n\t]+/', ' ', $text));
}

function audit_detail_lines(array $details, string $prefix = ''): array {
    $lines = [];
    foreach ($details as $key => $value) {
        $label = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            $lines = array_merge($lines, audit_detail_lines($value, $label));
        } else {
            $lines[] = '  ' . $label . ': ' . audit_clean_text($value);
        }
    }
    return $lines;
}

function audit_format_entry(array $entry): string {
    $user = $entry['username'] ?? 'Guest';
    $user_id = $entry['user_id'] ?? 'none';
    $house = $entry['house_name'] ?? 'system';
    if (isset($entry['house_id'])) {
        $house .= ' (ID ' . $entry['house_id'] . ')';
    }

    $lines = [
        '------------------------------------------------------------',
        'Time: ' . audit_clean_text($entry['timestamp'] ?? date(DATE_ATOM)),
        'Action: ' . audit_clean_text($entry['action'] ?? 'unknown'),
        'User: ' . audit_clean_text($user) . ' (ID ' . audit_clean_text($user_id) . ')',
        'Role: ' . audit_clean_text($entry['role'] ?? 'unknown'),
        'House: ' . audit_clean_text($house),
        'Request: ' . audit_clean_text($entry['method'] ?? 'CLI') . ' '
            . audit_clean_text($entry['path'] ?? '/'),
        'IP: ' . audit_clean_text($entry['ip_address'] ?? 'unknown'),
    ];

    if (!empty($entry['user_agent'])) {
        $lines[] = 'Browser: ' . audit_clean_text($entry['user_agent']);
    }
    if (!empty($entry['request_id'])) {
        $lines[] = 'Request ID: ' . audit_clean_text($entry['request_id']);
    }

    $details = $entry['details'] ?? [];
    if (is_array($details) && $details !== []) {
        $lines[] = 'Details:';
        $lines = array_merge($lines, audit_detail_lines($details));
    }

    $lines[] = '------------------------------------------------------------';
    return implode(PHP_EOL, $lines) . PHP_EOL . PHP_EOL;
}

function audit_append_file(string $file, string $text): bool {
    $handle = @fopen($file, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        return false;
    }

    rewind($handle);
    $contents = stream_get_contents($handle);
    if ($contents === false) {
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }

    $trimmed = ltrim($contents);
    if ($trimmed !== '' && $trimmed[0] === '{') {
        $converted = '';
        foreach (preg_split('/\r\n|\r|\n/', trim($contents)) as $old_line) {
            $old_entry = json_decode($old_line, true);
            if (!is_array($old_entry)) {
                flock($handle, LOCK_UN);
                fclose($handle);
                return false;
            }
            $converted .= audit_format_entry($old_entry);
        }
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $converted) !== strlen($converted)) {
            flock($handle, LOCK_UN);
            fclose($handle);
            return false;
        }
        fflush($handle);
    }

    fseek($handle, 0, SEEK_END);
    $written = fwrite($handle, $text);
    $success = $written === strlen($text) && fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    if ($success) {
        @chmod($file, 0640);
    }
    return $success;
}

function audit_import_legacy_logs(): void {
    $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($document_root === '') {
        return;
    }

    $server_logs_root = dirname(rtrim($document_root, DIRECTORY_SEPARATOR)) . DIRECTORY_SEPARATOR . 'logs';
    $logs_root = isset($GLOBALS['logs_dir']) && is_writable($GLOBALS['logs_dir'])
        ? $GLOBALS['logs_dir']
        : $server_logs_root;
    foreach (glob($server_logs_root . '/mealing_online_audit_*.jsonl') ?: [] as $legacy_file) {
        $handle = @fopen($legacy_file, 'r');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            continue;
        }

        $entries = [];
        $valid = true;
        foreach (file($legacy_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $entry = json_decode($line, true);
            if (!is_array($entry) || empty($entry['timestamp']) || empty($entry['action'])) {
                $valid = false;
                break;
            }
            $entries[] = $entry;
        }

        foreach ($entries as $entry) {
            $house_id = filter_var($entry['house_id'] ?? null, FILTER_VALIDATE_INT);
            $house_id = $house_id && $house_id > 0 ? $house_id : null;
            $house_name = $house_id === null ? null : audit_house_name($house_id);
            $day = substr((string) $entry['timestamp'], 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                $valid = false;
                break;
            }

            $house_dir = $logs_root . DIRECTORY_SEPARATOR
                . audit_house_directory($house_name, $house_id);
            if (!is_dir($house_dir) && !@mkdir($house_dir, 0750, true)) {
                $valid = false;
                break;
            }
            @chmod($house_dir, 0750);
            $target = $house_dir . DIRECTORY_SEPARATOR . $day . '.log';

            if (!empty($entry['request_id'])) {
                $existing = @file_get_contents($target);
                $request_marker = 'Request ID: ' . audit_clean_text($entry['request_id']);
                $action_marker = 'Action: ' . audit_clean_text($entry['action']);
                if ($existing !== false && str_contains($existing, $request_marker)
                    && str_contains($existing, $action_marker)) {
                    continue;
                }
            }

            $entry['house_id'] = $house_id;
            $entry['house_name'] = $house_name;
            if (!audit_append_file($target, audit_format_entry($entry))) {
                $valid = false;
                break;
            }
        }

        if ($valid && !empty($entries)) {
            @unlink($legacy_file);
        }
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function audit_log_event(string $action, array $details = []): void {
    global $logs_dir;

    $request_context = $GLOBALS['audit_request_context'] ?? [];
    $house_id = filter_var($_SESSION['house_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$house_id || $house_id < 1) {
        $house_id = $request_context['house_id'] ?? null;
    }
    $house_name = $house_id !== null
        ? audit_house_name((int) $house_id)
        : null;

    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($request_uri, PHP_URL_PATH);
    $entry = [
        'timestamp' => date(DATE_ATOM),
        'request_id' => $GLOBALS['audit_request_id'] ?? null,
        'action' => $action,
        'user_id' => $_SESSION['user_id'] ?? $request_context['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? $request_context['username'] ?? null,
        'role' => $_SESSION['role'] ?? $request_context['role'] ?? null,
        'house_id' => $house_id,
        'house_name' => $house_name,
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'path' => is_string($path) ? $path : '/',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        'details' => $details,
    ];

    $GLOBALS['application_log'][] = $entry;
    $text = audit_format_entry($entry);
    $house_directory = audit_house_directory(
        $house_name,
        $house_id === null ? null : (int) $house_id
    );
    $roots = [];
    if (isset($logs_dir) && is_dir($logs_dir) && is_writable($logs_dir)) {
        $roots[] = $logs_dir;
    }

    $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $server_logs_dir = $document_root !== ''
        ? dirname(rtrim($document_root, DIRECTORY_SEPARATOR)) . DIRECTORY_SEPARATOR . 'logs'
        : '';
    if ($server_logs_dir !== '' && is_dir($server_logs_dir) && is_writable($server_logs_dir)
        && !in_array($server_logs_dir, $roots, true)) {
        $roots[] = $server_logs_dir;
    }

    $filename = date('Y-m-d') . '.log';
    foreach ($roots as $root) {
        $house_log_dir = $root . DIRECTORY_SEPARATOR . $house_directory;
        if (!is_dir($house_log_dir) && !@mkdir($house_log_dir, 0750, true)) {
            continue;
        }
        @chmod($house_log_dir, 0750);
        $house_log_file = $house_log_dir . DIRECTORY_SEPARATOR . $filename;
        if (is_writable($house_log_dir) && audit_append_file($house_log_file, $text)) {
            return;
        }
    }

    @error_log('Unable to write application audit log: ' . $action);
}

function log_action($action, $details = '', $user_id = null, $user_name = null): void {
    audit_log_event('action.' . (string) $action, [
        'details' => $details,
        'target_user_id' => $user_id,
        'target_user_name' => $user_name,
    ]);
}

function log_query($sql, $params = null, $execution_time = null): void {
    $query = trim((string) $sql);
    preg_match('/^([a-z]+)/i', $query, $matches);
    $log_entry = [
        'timestamp' => date(DATE_ATOM),
        'query_type' => strtoupper($matches[1] ?? 'UNKNOWN'),
        'query_fingerprint' => hash('sha256', $query),
        'parameter_count' => is_array($params) ? count($params) : null,
        'execution_time' => $execution_time,
    ];
    audit_log_event('database.query', $log_entry);
    $last_key = array_key_last($GLOBALS['application_log']);
    if ($last_key !== null) {
        $GLOBALS['application_log'][$last_key]['query_type'] = $log_entry['query_type'];
    }
}

function log_db_error($sql, $error): void {
    $query = trim((string) $sql);
    preg_match('/^([a-z]+)/i', $query, $matches);
    audit_log_event('database.error', [
        'query_type' => strtoupper($matches[1] ?? 'UNKNOWN'),
        'query_fingerprint' => hash('sha256', $query),
        'error' => (string) $error,
    ]);
}

function get_recent_queries($limit = 20) {
    global $application_log;
    
    $queries = array_filter($application_log, function($entry) {
        return isset($entry['query_type']) || isset($entry['query']);
    });
    
    return array_slice($queries, -$limit);
}

function get_user_actions($user_id = null, $limit = 50) {
    global $application_log, $current_user_id;
    
    if ($user_id === null) {
        $user_id = $current_user_id;
    }
    
    return array_slice(
        array_filter($application_log, function($entry) use ($user_id) {
            return $entry['user_id'] == $user_id;
        }),
        -$limit
    );
}
