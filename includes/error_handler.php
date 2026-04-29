<?php
/**
 * Error Handler Utility
 * Centralizes error handling with environment-aware responses
 */

/**
 * Handle database errors securely
 * Shows detailed errors in development, user-friendly messages in production
 * @param string $error - Error message from mysqli
 * @param string $context - Context where error occurred (for logging)
 * @param string $userMessage - User-friendly message to display (optional)
 * @return string - Message to display
 */
function handleDatabaseError($error, $context = '', $userMessage = null) {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    // Log the error securely
    $logMessage = "[$timestamp] Database Error in $context | IP: $ip | Error: $error";
    error_log($logMessage);

    // Return environment-appropriate message
    if (ENVIRONMENT === 'production') {
        return $userMessage ?? 'An error occurred. Please try again later.';
    } else {
        return "Error in $context: " . htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Handle query execution errors
 * @param mysqli $conn - Database connection
 * @param string $context - Context where error occurred
 * @param bool $die - Whether to die on error (default: false)
 * @return string|bool - Error message or true if no error
 */
function checkQueryError($conn, $context = 'Unknown', $die = false) {
    if ($conn->error) {
        $errorMsg = handleDatabaseError($conn->error, $context);
        if ($die) {
            die($errorMsg);
        }
        return $errorMsg;
    }
    return true;
}

/**
 * Validate mysqli statement execution result
 * @param mysqli_result|bool $result - Result from mysqli_stmt_execute()
 * @param mysqli_stmt $stmt - Statement object
 * @param string $context - Context description
 * @return bool|string - True on success, error message on failure
 */
function validateStatementExecution($result, $stmt, $context = 'Database operation') {
    if ($result === false) {
        return handleDatabaseError(mysqli_stmt_error($stmt), $context);
    }
    return true;
}

/**
 * Format validation error message
 * @param string $field - Field name
 * @param string $rule - Validation rule that failed
 * @return string - Formatted error message
 */
function formatValidationError($field, $rule) {
    $messages = [
        'required' => "The $field field is required.",
        'email' => "Please enter a valid email address.",
        'phone' => "Please enter a valid phone number.",
        'length_min' => "The $field must have at least the minimum required length.",
        'length_max' => "The $field is too long.",
        'unique' => "This $field is already registered.",
        'password_mismatch' => "The passwords do not match.",
        'invalid_format' => "The $field has an invalid format.",
        'numeric' => "The $field must be a number.",
        'date_invalid' => "Please enter a valid date.",
    ];

    return $messages[$rule] ?? "Invalid $field.";
}

/**
 * Check if result is error and handle appropriately
 * @param bool|string|array $result - Result to check
 * @param string $errorPrefix - Prefix for error (default: "Error: ")
 * @return array - ['success' => bool, 'message' => string, 'data' => mixed]
 */
function handleResult($result, $errorPrefix = 'Error: ') {
    if ($result === true) {
        return ['success' => true, 'message' => 'Operation completed successfully', 'data' => null];
    }

    if (is_string($result)) {
        return ['success' => false, 'message' => $errorPrefix . $result, 'data' => null];
    }

    if (is_array($result)) {
        return ['success' => false, 'message' => $result['error'] ?? 'Operation failed', 'data' => null];
    }

    return ['success' => false, 'message' => $errorPrefix . 'Unknown error occurred', 'data' => null];
}

/**
 * Set error session and redirect
 * @param string $message - Error message
 * @param string $redirectUrl - URL to redirect to
 */
function setErrorAndRedirect($message, $redirectUrl) {
    $_SESSION['error'] = $message;
    header('Location: ' . $redirectUrl);
    exit();
}

/**
 * Set success session and redirect
 * @param string $message - Success message
 * @param string $redirectUrl - URL to redirect to
 */
function setSuccessAndRedirect($message, $redirectUrl) {
    $_SESSION['success'] = $message;
    header('Location: ' . $redirectUrl);
    exit();
}

/**
 * Display alert HTML (for AJAX responses)
 * @param string $type - Alert type (success, danger, warning, info)
 * @param string $message - Alert message
 * @return string - HTML alert div
 */
function getAlertHTML($type, $message) {
    $type = in_array($type, ['success', 'danger', 'warning', 'info']) ? $type : 'info';
    $icons = [
        'success' => '✓',
        'danger' => '✗',
        'warning' => '⚠',
        'info' => 'ⓘ'
    ];

    $icon = $icons[$type] ?? '•';
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    return "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
        <strong>$icon</strong> $message
        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
    </div>";
}
?>
