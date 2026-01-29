<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

$page_title = "Monthly Report";

// Get month and year from query parameters
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = date('m');
}
if ($year < 2000 || $year > 2100) {
    $year = date('Y');
}

$month_name = date('F', mktime(0, 0, 0, $month, 1));
$month_start = "$year-$month-01";
$month_end = date('Y-m-t', strtotime($month_start));

// Check if month is closed
$is_closed = $functions->isMonthClosed($month, $year);

// Calculate monthly report
$report = $functions->calculateMonthlyReport($month, $year);

// Calculate totals
$total_meals = 0;
$total_expenses = 0;
$total_deposits = 0;
$total_cost = 0;
$total_balance = 0;
$credit_total = 0;
$due_total = 0;

foreach ($report as $member) {
    $total_meals += $member['total_meals'];
    $total_deposits += $member['total_deposits'];
    $total_cost += $member['member_cost'];
    $total_balance += $member['balance'];
    
    if ($member['balance'] >= 0) {
        $credit_total += $member['balance'];
    } else {
        $due_total += abs($member['balance']);
    }
}

// Get expense breakdown
$expense_breakdown = $functions->getExpenseBreakdown($month, $year);

// Get meal rate (use first member's calculation)
$meal_rate = !empty($report) ? $report[0]['meal_rate'] : 0;
$all_meals = !empty($report) ? $report[0]['all_meals'] : 0;
$total_expenses = !empty($report) ? $report[0]['total_expenses'] : 0;

// Handle month closing
if (isset($_POST['close_month']) && !$is_closed) {
    if ($functions->closeMonth($month, $year, $_SESSION['user_id'])) {
        $_SESSION['success'] = "Month closed successfully! Report has been finalized.";
        $is_closed = true;
    } else {
        $_SESSION['error'] = "Error closing month. It may already be closed.";
    }
}

// Check if print mode
$is_print = isset($_GET['print']) && $_GET['print'] == '1';
$is_pdf = isset($_GET['format']) && $_GET['format'] == 'pdf';

if ($is_print || $is_pdf) {
    // Simplified header for printing/PDF
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Monthly Report - <?php echo $month_name . ' ' . $year; ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .report-header { text-align: center; margin-bottom: 30px; }
            .summary-box { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f8f9fa; }
            .positive { color: green; }
            .negative { color: red; }
            .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #666; }
            @media print {
                .no-print { display: none; }
                body { margin: 0; }
            }
        </style>
    </head>
    <body>
    <?php
} else {
    // Normal header
    require_once '../includes/header.php';
}
?>

<?php if (!$is_print && !$is_pdf): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="page-title mb-0">Monthly Report: <?php echo $month_name . ' ' . $year; ?></h4>
                <p class="text-muted mb-0">
                    <?php echo $is_closed ? 
                        '<span class="badge bg-danger">CLOSED</span> - Finalized report' : 
                        '<span class="badge bg-success">OPEN</span> - Can be edited'; ?>
                </p>
            </div>
            <div class="btn-group">
                <button onclick="window.print()" class="btn btn-outline-primary">
                    <i class="fas fa-print me-2"></i>Print
                </button>
                <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&format=pdf" 
                   class="btn btn-outline-danger ms-2">
                    <i class="fas fa-file-pdf me-2"></i>PDF
                </a>
                <a href="export_monthly.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>&format=excel" 
                   class="btn btn-outline-success ms-2">
                    <i class="fas fa-file-excel me-2"></i>Excel
                </a>
                <?php if (!$is_closed): ?>
                <button type="button" class="btn btn-danger ms-2" data-bs-toggle="modal" data-bs-target="#closeMonthModal">
                    <i class="fas fa-lock me-2"></i>Close Month
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Month Selector -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <select name="month" class="form-select" required>
                            <option value="">Select Month</option>
                            <?php
                            $months = ['January', 'February', 'March', 'April', 'May', 'June', 
                                     'July', 'August', 'September', 'October', 'November', 'December'];
                            foreach ($months as $index => $month_name_option):
                                $month_num = $index + 1;
                            ?>
                            <option value="<?php echo $month_num; ?>" <?php echo $month_num == $month ? 'selected' : ''; ?>>
                                <?php echo $month_name_option; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select name="year" class="form-select" required>
                            <option value="">Select Year</option>
                            <?php
                            $current_year = date('Y');
                            for ($y = $current_year; $y >= $current_year - 5; $y--):
                            ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>View Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Report Header for Print/PDF -->
<?php if ($is_print || $is_pdf): ?>
<div class="report-header">
    <h1>Meal Management System</h1>
    <h2>Monthly Report - <?php echo $month_name . ' ' . $year; ?></h2>
    <p>Generated on: <?php echo date('F j, Y h:i A'); ?></p>
    <?php if ($is_closed): ?>
    <p><strong>STATUS: FINALIZED (CLOSED)</strong></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Summary Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Total Expenses</h6>
                <h3 class="text-primary mb-0"><?php echo $functions->formatCurrency($total_expenses); ?></h3>
                <small class="text-muted">For the month</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Total Meals</h6>
                <h3 class="text-success mb-0"><?php echo number_format($all_meals, 2); ?></h3>
                <small class="text-muted">All members</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Meal Rate</h6>
                <h3 class="text-warning mb-0"><?php echo $functions->formatCurrency($meal_rate); ?></h3>
                <small class="text-muted">Per meal</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2">Net Balance</h6>
                <h3 class="text-info mb-0"><?php echo $functions->formatCurrency($total_balance); ?></h3>
                <small class="text-muted">System balance</small>
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-money-bill-wave me-2"></i>Financial Summary</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <p class="mb-2"><strong>Total Deposits:</strong></p>
                        <p class="mb-2"><strong>Total Cost:</strong></p>
                        <p class="mb-2"><strong>Net Balance:</strong></p>
                    </div>
                    <div class="col-6 text-end">
                        <p class="mb-2 text-success"><?php echo $functions->formatCurrency($total_deposits); ?></p>
                        <p class="mb-2 text-warning"><?php echo $functions->formatCurrency($total_cost); ?></p>
                        <p class="mb-0 <?php echo $total_balance >= 0 ? 'text-info' : 'text-danger'; ?>">
                            <?php echo $functions->formatCurrency($total_balance); ?>
                            <?php if ($total_balance >= 0): ?>
                            <small class="text-muted">(System has credit)</small>
                            <?php else: ?>
                            <small class="text-muted">(System has due)</small>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="mt-4">
                    <h6>Balance Distribution:</h6>
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <h3 class="text-success mb-0"><?php echo $functions->formatCurrency($credit_total); ?></h3>
                                <small class="text-muted">Total Credit</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <h3 class="text-danger mb-0"><?php echo $functions->formatCurrency($due_total); ?></h3>
                                <small class="text-muted">Total Due</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Expense Breakdown -->
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-pie me-2"></i>Expense Breakdown</h6>
            </div>
            <div class="card-body">
                <?php if (empty($expense_breakdown['breakdown'])): ?>
                <div class="text-center text-muted py-3">No expenses this month</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expense_breakdown['breakdown'] as $item): 
                                $percentage = $expense_breakdown['total'] > 0 ? ($item['total'] / $expense_breakdown['total'] * 100) : 0;
                            ?>
                            <tr>
                                <td><?php echo $item['category']; ?></td>
                                <td class="text-end"><?php echo $functions->formatCurrency($item['total']); ?></td>
                                <td class="text-end"><?php echo number_format($percentage, 1); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <th>Total</th>
                                <th class="text-end"><?php echo $functions->formatCurrency($expense_breakdown['total']); ?></th>
                                <th class="text-end">100%</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Member-wise Report -->
<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users me-2"></i>Member-wise Report</h6>
            </div>
            <div class="card-body">
                <?php if (empty($report)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5>No Data Found</h5>
                    <p class="text-muted">No meal or expense data for <?php echo $month_name . ' ' . $year; ?></p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover <?php echo ($is_print || $is_pdf) ? '' : 'datatable'; ?>">
                        <thead class="table-light">
                            <tr>
                                <th>Member</th>
                                <th class="text-center">Total Meals</th>
                                <th class="text-center">Total Deposits</th>
                                <th class="text-center">Total Cost</th>
                                <th class="text-center">Balance</th>
                                <th class="text-center">Status</th>
                                <?php if (!$is_print && !$is_pdf): ?>
                                <th class="text-center">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report as $member): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                    <?php if ($member['phone']): ?>
                                    <br><small class="text-muted"><?php echo $member['phone']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?php echo number_format($member['total_meals'], 2); ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?php echo $functions->formatCurrency($member['total_deposits']); ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-warning"><?php echo $functions->formatCurrency($member['member_cost']); ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($member['balance'] >= 0): ?>
                                    <span class="balance-positive">
                                        <?php echo $functions->formatCurrency($member['balance']); ?>
                                        <small class="text-muted">(Return)</small>
                                    </span>
                                    <?php else: ?>
                                    <span class="balance-negative">
                                        <?php echo $functions->formatCurrency(abs($member['balance'])); ?>
                                        <small class="text-muted">(Due)</small>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($member['balance'] >= 0): ?>
                                    <span class="badge bg-success">Credit</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Due</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (!$is_print && !$is_pdf): ?>
                                <td class="text-center">
                                    <a href="member_report.php?member_id=<?php echo $member['member_id']; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                                       class="btn btn-sm btn-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th>Totals</th>
                                <th class="text-center"><?php echo number_format($all_meals, 2); ?></th>
                                <th class="text-center"><?php echo $functions->formatCurrency($total_deposits); ?></th>
                                <th class="text-center"><?php echo $functions->formatCurrency($total_cost); ?></th>
                                <th class="text-center <?php echo $total_balance >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                                    <?php echo $functions->formatCurrency($total_balance); ?>
                                </th>
                                <th class="text-center">
                                    <?php if ($total_balance >= 0): ?>
                                    <span class="badge bg-success">System Credit</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">System Due</span>
                                    <?php endif; ?>
                                </th>
                                <?php if (!$is_print && !$is_pdf): ?>
                                <th></th>
                                <?php endif; ?>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Additional Information -->
<?php if (!$is_print && !$is_pdf): ?>
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-calculator me-2"></i>Calculation Method</h6>
            </div>
            <div class="card-body">
                <h6>Meal Rate Formula:</h6>
                <div class="alert alert-light">
                    <p class="mb-2"><strong>Meal Rate = Total Expenses ÷ Total Meals</strong></p>
                    <p class="mb-2"><?php echo $functions->formatCurrency($total_expenses); ?> ÷ <?php echo number_format($all_meals, 2); ?> = <?php echo $functions->formatCurrency($meal_rate); ?></p>
                </div>
                
                <h6 class="mt-3">Member Cost Formula:</h6>
                <div class="alert alert-light">
                    <p class="mb-0"><strong>Member Cost = Member's Meals × Meal Rate</strong></p>
                </div>
                
                <h6 class="mt-3">Balance Formula:</h6>
                <div class="alert alert-light">
                    <p class="mb-0"><strong>Balance = Total Deposits - Member Cost</strong></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle me-2"></i>Report Information</h6>
            </div>
            <div class="card-body">
                <p><strong>Report Period:</strong> <?php echo date('F j, Y', strtotime($month_start)); ?> to <?php echo date('F j, Y', strtotime($month_end)); ?></p>
                <p><strong>Generated On:</strong> <?php echo date('F j, Y h:i A'); ?></p>
                <p><strong>Generated By:</strong> <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)</p>
                <p><strong>Report Status:</strong> 
                    <?php if ($is_closed): ?>
                    <span class="badge bg-danger">FINALIZED (CLOSED)</span>
                    <?php else: ?>
                    <span class="badge bg-success">OPEN (CAN BE EDITED)</span>
                    <?php endif; ?>
                </p>
                
                <?php if ($is_closed): ?>
                <div class="alert alert-danger mt-3">
                    <h6><i class="fas fa-lock me-2"></i>Month Closed</h6>
                    <p class="mb-0">This month has been finalized. No further edits to meals, expenses, or deposits are allowed for this period.</p>
                </div>
                <?php else: ?>
                <div class="alert alert-warning mt-3">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Month Open</h6>
                    <p class="mb-0">This month is still open for edits. Close the month to finalize the report and prevent further changes.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Footer for Print/PDF -->
<?php if ($is_print || $is_pdf): ?>
<div class="footer">
    <hr>
    <p>Meal Management System - <?php echo date('Y'); ?></p>
    <p>This is a computer-generated report. No signature required.</p>
</div>
<?php endif; ?>

<!-- Close Month Modal -->
<?php if (!$is_closed && !$is_print && !$is_pdf): ?>
<div class="modal fade" id="closeMonthModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Close Month</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to close <strong><?php echo $month_name . ' ' . $year; ?></strong>?</p>
                <div class="alert alert-danger">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Warning: This action cannot be undone!</h6>
                    <p class="mb-0">Once closed, you cannot:</p>
                    <ul class="mb-0">
                        <li>Add or edit meals for this month</li>
                        <li>Add or edit expenses for this month</li>
                        <li>Add or edit deposits for this month</li>
                        <li>Reopen the month for editing</li>
                    </ul>
                </div>
                <p>Make sure all data for <?php echo $month_name . ' ' . $year; ?> is correct before closing.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="">
                    <input type="hidden" name="close_month" value="1">
                    <button type="submit" class="btn btn-danger">Close Month Permanently</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($is_print || $is_pdf): ?>
<script>
// Auto-print when page loads
window.onload = function() {
    <?php if ($is_print): ?>
    window.print();
    <?php endif; ?>
};
</script>
</body>
</html>
<?php else: ?>
<?php require_once '../includes/footer.php'; ?>
<?php endif; ?>