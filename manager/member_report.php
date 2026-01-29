<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

$page_title = "Member Report";

$conn = getConnection();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!-- Starting Member Report -->";

// Simple check for files
$base_dir = __DIR__;
echo "<!-- Base directory: $base_dir -->";

// Check if user is logged in (simple check)
if (!isset($_SESSION['user_id'])) {
    die("Please login first. <a href='../login.php'>Login here</a>");
}

// Get parameters with defaults
$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

echo "<!-- Parameters: member_id=$member_id, month=$month, year=$year -->";

if ($member_id <= 0) {
    $_SESSION['error'] = "Invalid member ID. Please go back and select a member.";
    header("Location: reports.php");
    exit();
}

// Validate month/year
if ($month < 1 || $month > 12) $month = date('m');
if ($year < 2000 || $year > 2100) $year = date('Y');

$month_name = date('F', mktime(0, 0, 0, $month, 1));

// Try to get member
$member = null;
try {
    $member = $functions->getMember($member_id);
    echo "<!-- getMember() called -->";
} catch (Exception $e) {
    echo "<!-- Error in getMember: " . $e->getMessage() . " -->";
}

if (!$member) {
    $_SESSION['error'] = "Member not found with ID: $member_id";
    header("Location: reports.php");
    exit();
}

echo "<!-- Member found: " . $member['name'] . " -->";

// Try to get report data
$member_report = null;
$meals = [];
$deposits = [];

try {
    $monthly_report = $functions->calculateMonthlyReport($month, $year);
    echo "<!-- Monthly report generated -->";
    
    if (!empty($monthly_report)) {
        foreach ($monthly_report as $report) {
            if ($report['member_id'] == $member_id) {
                $member_report = $report;
                break;
            }
        }
    }
    
    // Get meals
    $meals = $functions->getMemberMeals($member_id, $month, $year);
    echo "<!-- Meals retrieved: " . count($meals) . " -->";
    
    // Get deposits
    $deposits = $functions->getMemberDeposits($member_id, $month, $year);
    echo "<!-- Deposits retrieved: " . count($deposits) . " -->";
    
} catch (Exception $e) {
    echo "<!-- Error getting data: " . $e->getMessage() . " -->";
}
?>
<div class="row">
    <div class="col-12">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">Member Report</h1>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($member['name']); ?> - <?php echo $month_name . ' ' . $year; ?></p>
                    </div>
                    <div>
                        <a href="reports.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </a>
                        <button onclick="window.print()" class="btn btn-primary ms-2">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Member Info -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Member Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Member ID:</strong> <?php echo $member['member_id']; ?></p>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($member['name']); ?></p>
                                <p><strong>Phone:</strong> <?php echo $member['phone'] ?? 'Not provided'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Email:</strong> <?php echo $member['email'] ?? 'Not provided'; ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo ($member['status'] ?? 'active') == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($member['status'] ?? 'active'); ?>
                                    </span>
                                </p>
                                <p><strong>Joined:</strong> <?php echo date('M d, Y', strtotime($member['join_date'] ?? date('Y-m-d'))); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Quick Stats</h5>
                    </div>
                    <div class="card-body text-center">
                        <?php if ($member_report): ?>
                        <div class="mb-3">
                            <h6 class="text-muted mb-1">Total Meals</h6>
                            <h2 class="text-success"><?php echo number_format($member_report['total_meals'], 2); ?></h2>
                        </div>
                        <div class="mb-3">
                            <h6 class="text-muted mb-1">Total Deposits</h6>
                            <h2 class="text-primary"><?php echo $functions->formatCurrency($member_report['total_deposits']); ?></h2>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Balance</h6>
                            <h2 class="<?php echo $member_report['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo $functions->formatCurrency(abs($member_report['balance'])); ?>
                            </h2>
                            <span class="badge bg-<?php echo $member_report['balance'] >= 0 ? 'success' : 'danger'; ?>">
                                <?php echo $member_report['balance'] >= 0 ? 'CREDIT' : 'DUE'; ?>
                            </span>
                        </div>
                        <?php else: ?>
                        <p class="text-muted">No data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($member_report): ?>
        <!-- Detailed Report -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Financial Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="alert alert-light">
                                    <h6>Meal Rate</h6>
                                    <h3 class="text-warning"><?php echo $functions->formatCurrency($member_report['meal_rate']); ?></h3>
                                    <small class="text-muted">Per meal</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-light">
                                    <h6>Total Cost</h6>
                                    <h3 class="text-warning"><?php echo $functions->formatCurrency($member_report['member_cost']); ?></h3>
                                    <small class="text-muted">For <?php echo number_format($member_report['total_meals'], 2); ?> meals</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-<?php echo $member_report['balance'] >= 0 ? 'success' : 'danger'; ?>">
                                    <h6>Final Balance</h6>
                                    <h3><?php echo $functions->formatCurrency($member_report['balance']); ?></h3>
                                    <small><?php echo $member_report['balance'] >= 0 ? 'To be returned' : 'To be paid'; ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Meal History -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-utensils me-2"></i>Meal History</h5>
                        <span class="badge bg-secondary"><?php echo count($meals); ?> days</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($meals)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-center">Meals</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($meals as $meal): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($meal['meal_date'])); ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary"><?php echo number_format($meal['meal_count'], 2); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted text-center py-3">No meals recorded</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Deposit History -->
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Deposit History</h5>
                        <span class="badge bg-success"><?php echo count($deposits); ?> deposits</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($deposits)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deposits as $deposit): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($deposit['deposit_date'])); ?></td>
                                        <td class="text-success"><?php echo $functions->formatCurrency($deposit['amount']); ?></td>
                                        <td><?php echo htmlspecialchars($deposit['description']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted text-center py-3">No deposits recorded</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <p class="text-muted mb-0">
                            Report generated on <?php echo date('F j, Y'); ?> at <?php echo date('h:i A'); ?>
                        </p>
                        <p class="text-muted">
                            <small>Meal Management System</small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Simple print function
function printReport() {
    window.print();
}

// Go back function
function goBack() {
    window.history.back();
}

$(document).ready(function() {
    // Add any custom JavaScript here if needed
});
</script>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>