<?php
/**
 * Security Headers Utility
 * Sets comprehensive HTTP security headers
 */

/**
 * Set all security headers
 * Should be called at the very beginning of each PHP file
 */
function setSecurityHeaders() {
    // Prevent clickjacking attacks
    header("X-Frame-Options: DENY");

    // Prevent MIME type sniffing
    header("X-Content-Type-Options: nosniff");

    // Enable XSS protection in older browsers
    header("X-XSS-Protection: 1; mode=block");

    // Referrer policy for privacy
    header("Referrer-Policy: strict-origin-when-cross-origin");

    // Feature policy / Permissions policy
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

    // Content Security Policy (optional, basic)
    // Adjust based on your needs (CDN resources, inline scripts, etc.)
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com; " .
           "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com fonts.googleapis.com; " .
           "img-src 'self' data: https: ui-avatars.com; " .
           "font-src 'self' cdnjs.cloudflare.com fonts.gstatic.com; " .
           "connect-src 'self'; " .
           "frame-ancestors 'none'; " .
           "base-uri 'self'; " .
           "form-action 'self'";

    header("Content-Security-Policy: $csp");

    // HSTS (only on HTTPS in production)
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && ENVIRONMENT === 'production') {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
    }

    // Set secure cookie flags
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    } else {
        // Fallback for PHP < 7.3
        session_set_cookie_params(
            0,                                              // lifetime
            '/',                                            // path
            $_SERVER['HTTP_HOST'] ?? '',                   // domain
            !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',  // secure
            true                                            // httponly
        );
    }
}

/**
 * Check if connection is secure (HTTPS)
 * @return bool - True if HTTPS
 */
function isSecureConnection() {
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

/**
 * Ensure HTTPS in production
 */
function enforceHTTPS() {
    if (ENVIRONMENT === 'production' && !isSecureConnection()) {
        header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit();
    }
}

/**
 * Set no-cache headers
 * Prevents sensitive data from being cached by browsers
 */
function setNoCacheHeaders() {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
}

/**
 * Validate request origin for AJAX requests
 * @return bool - True if origin is valid
 */
function validateRequestOrigin() {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (empty($referer)) {
        return false;
    }

    $referer_host = parse_url($referer, PHP_URL_HOST);
    $request_host = $_SERVER['HTTP_HOST'] ?? '';

    return $referer_host === $request_host;
}

/**
 * Prevent open redirect vulnerability
 * @param string $url - URL to validate
 * @return bool - True if URL is safe
 */
function isSafeRedirectUrl($url) {
    // Only allow relative URLs or same-domain URLs
    if (strpos($url, '://') !== false) {
        return false;  // Absolute URL
    }

    if (strpos($url, '//') === 0) {
        return false;  // Protocol-relative URL
    }

    // Allow relative URLs only
    return true;
}

/**
 * Safely redirect to a URL
 * @param string $url - URL to redirect to
 */
function safeRedirect($url) {
    if (!isSafeRedirectUrl($url)) {
        $url = 'index.php';  // Fall back to home
    }

    header('Location: ' . $url);
    exit();
}
?>
