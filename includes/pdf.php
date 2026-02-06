<?php
/**
 * PDF Helper Functions
 * This file contains only helper functions - NO HEADER() CALLS AT ALL
 */

/**
 * Generate monthly report PDF redirect URL
 * This function returns the URL for PDF generation - doesn't output anything
 */
function getMonthlyReportPDFUrl($house_id, $month, $year) {
    return "../includes/generate_monthly_report.php?" . http_build_query([
        'house_id' => $house_id,
        'month' => $month,
        'year' => $year
    ]);
}

/**
 * Generate monthly report HTML (for backward compatibility)
 * This function is DEPRECATED - use getMonthlyReportPDFUrl() instead
 */
function generateMonthlyReportPDF($house_info, $month, $year, $total_expenses, $total_meals, 
                                  $meal_rate, $monthly_report, $expense_categories) {
    // DEPRECATED FUNCTION - Does nothing now
    // Use getMonthlyReportPDFUrl() instead
    return true;
}

/**
 * Format currency for display
 */
function formatCurrencyPDF($amount) {
    return '৳ ' . number_format($amount, 2);
}

/**
 * Get status color based on balance
 */
function getStatusColorPDF($balance) {
    return $balance >= 0 ? 'success' : 'danger';
}

/**
 * Get status text based on balance
 */
function getStatusTextPDF($balance) {
    return $balance >= 0 ? 'In Credit' : 'Due';
}

/**
 * Calculate percentage
 */
function calculatePercentagePDF($value, $total) {
    if ($total == 0) return 0;
    return round(($value / $total) * 100, 2);
}

/**
 * Generate a clean filename for PDF
 */
function generatePDFFilename($house_code, $month, $year) {
    $month_names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                   'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $month_name = $month_names[$month - 1];
    return "Monthly_Report_{$house_code}_{$month_name}_{$year}.html";
}
?>