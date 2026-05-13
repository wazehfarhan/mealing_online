<?php
/**
 * Security Utilities
 * Provides cryptographically secure random generation and sanitization
 */

/**
 * Generate cryptographically secure random string
 * @param int $length - Length of the string to generate
 * @return string - Random alphanumeric string
 */
function generateSecureRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    $max = strlen($characters) - 1;

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $max)];
    }

    return $randomString;
}

/**
 * Generate cryptographically secure house code (6 characters)
 * Uses random_bytes() for maximum security
 * @return string - 6-character hex string (e.g., "A1B2C3")
 */
function generateSecureHouseCode() {
    // Generate 3 bytes (24 bits) and convert to hex = 6 characters
    return strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Safely sanitize output for HTML context
 * Prevents XSS attacks
 * @param string $text - Text to sanitize
 * @param string $encoding - Character encoding (default: UTF-8)
 * @return string - Sanitized text safe for HTML output
 */
function sanitizeOutput($text, $encoding = 'UTF-8') {
    if (is_array($text)) {
        $output = [];
        foreach ($text as $key => $value) {
            $output[$key] = sanitizeOutput($value, $encoding);
        }
        return $output;
    }

    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_HTML5, $encoding);
}

/**
 * Securely sanitize input data
 * Handles both single values and arrays
 * @param mixed $input - Input data to sanitize
 * @return mixed - Sanitized input
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        $sanitized = [];
        foreach ($input as $key => $value) {
            $sanitized[$key] = sanitizeInput($value);
        }
        return $sanitized;
    }

    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate secure CSRF token
 * NOTE: Primary CSRF implementation is in csrf.php
 * These are guarded wrappers for backward compatibility
 */
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token = null) {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? '';
        }
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token ?? '');
    }
}

/**
 * Regenerate CSRF token (call after processing form)
 * @return string - New CSRF token
 */
if (!function_exists('regenerateCSRFToken')) {
    function regenerateCSRFToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}

/**
 * Get CSRF token for use in forms
 * @return string - Current CSRF token
 */
if (!function_exists('getCSRFToken')) {
    function getCSRFToken() {
        return generateCSRFToken();
    }
}

/**
 * Validate email address securely
 * @param string $email - Email to validate
 * @return bool - True if valid
 */
function validateEmailSecurely($email) {
    $email = sanitizeInput($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number format
 * @param string $phone - Phone number to validate
 * @param int $minLength - Minimum length (default: 10)
 * @param int $maxLength - Maximum length (default: 15)
 * @return bool - True if valid
 */
function validatePhoneSecurely($phone, $minLength = 10, $maxLength = 15) {
    $phone = preg_replace('/[^0-9+\-\s]/', '', $phone);
    $digits = preg_replace('/[^0-9]/', '', $phone);

    return strlen($digits) >= $minLength && strlen($digits) <= $maxLength;
}
?>
