<?php
/**
 * CSRF Protection Functions (Task 1.3)
 */

session_start(); // Ensure session active

/**
 * Generate CSRF token and store in session
 * @return string 32-byte hex token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token from POST
 * @param string $token Token from $_POST['csrf_token']
 * @return bool true if valid
 */
function verifyCSRFToken($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    
    // Optional: Regenerate token after use (double-submit cookie pattern alternative)
    // generateCSRFToken();
    
    return $valid;
}

/**
 * CSRF middleware - call at top of POST handlers
 * @return bool|void exits with error if invalid
 */
function requireCSRF() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    
    if (!verifyCSRFToken()) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']) ?: 'Invalid security token');
    }
    return true;
}
?>

