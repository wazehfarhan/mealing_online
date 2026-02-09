<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

// Get current user's house_id
$user_id = $_SESSION['user_id'];
$house_id = $_SESSION['house_id'] ?? null;

// Check if user has a house
if (!$house_id) {
    $_SESSION['error'] = "You need to set up a house first";
    header("Location: setup_house.php");
    exit();
}

$page_title = "Add New Member";

$conn = getConnection();

// Get house name for display
$house_sql = "SELECT house_name FROM houses WHERE house_id = ?";
$house_stmt = mysqli_prepare($conn, $house_sql);
mysqli_stmt_bind_param($house_stmt, "i", $house_id);
mysqli_stmt_execute($house_stmt);
$house_result = mysqli_stmt_get_result($house_stmt);
$house = mysqli_fetch_assoc($house_result);
$house_name = $house ? $house['house_name'] : 'Unknown House';

$error = '';
$success = '';
$join_url = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $join_date = trim($_POST['join_date']);
    
    // Validation
    if (empty($name)) {
        $error = "Name is required";
    } elseif (empty($join_date)) {
        $error = "Join date is required";
    } elseif (strlen($name) > 100) {
        $error = "Name must be less than 100 characters";
    } elseif (!empty($phone) && strlen($phone) > 20) {
        $error = "Phone number must be less than 20 characters";
    } elseif (!empty($email) && (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        $error = "Invalid email address";
    } else {
        // Check for duplicate phone/email in the same house
        if (!empty($phone) || !empty($email)) {
            $check_sql = "SELECT member_id FROM members WHERE house_id = ? AND (phone = ? OR email = ?)";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            
            // Handle empty values
            $check_phone = !empty($phone) ? $phone : '';
            $check_email = !empty($email) ? $email : '';
            
            mysqli_stmt_bind_param($check_stmt, "iss", $house_id, $check_phone, $check_email);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $error = "Another member with same phone or email already exists in this house";
            }
        }
        
        if (empty($error)) {
            // Generate unique join token
            $join_token = bin2hex(random_bytes(32));
            $token_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
            
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Insert member
                $sql = "INSERT INTO members (house_id, name, phone, email, join_date, created_by, join_token, token_expiry, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')";
                $stmt = mysqli_prepare($conn, $sql);
                
                // Handle empty values for binding
                $insert_phone = !empty($phone) ? $phone : '';
                $insert_email = !empty($email) ? $email : '';
                
                mysqli_stmt_bind_param($stmt, "issssiss", $house_id, $name, $insert_phone, $insert_email, 
                                      $join_date, $user_id, $join_token, $token_expiry);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Error adding member: " . mysqli_error($conn));
                }
                
                $member_id = mysqli_insert_id($conn);
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Create join URL
                $base_url = str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', dirname(__DIR__)));
                $join_url = 'http://' . $_SERVER['HTTP_HOST'] . $base_url . '/member/join.php?token=' . $join_token;
                
                $success = "Member added successfully! Member ID: #" . $member_id;
                
                // Clear form
                $_POST = array();
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = $e->getMessage();
            }
        }
    }
}

// Now include the header AFTER all processing
require_once '../includes/header.php';
?>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Add New Member</h5>
                <span class="badge bg-primary">House: <?php echo htmlspecialchars($house_name); ?></span>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                </div>
                <?php unset($_SESSION['error']); endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    
                    <?php if ($join_url): ?>
                    <div class="mt-3">
                        <h6><i class="fas fa-link me-2"></i>Member Join Link:</h6>
                        <div class="input-group">
                            <input type="text" class="form-control" id="joinLink" value="<?php echo $join_url; ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyJoinLink()">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>This link expires in 7 days
                            </small>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="shareJoinLink()">
                            <i class="fas fa-share me-1"></i> Share via...
                        </button>
                        <a href="members.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-users me-1"></i> View All Members
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetForm()">
                            <i class="fas fa-plus me-1"></i> Add Another
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="addMemberForm" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                   required maxlength="100" placeholder="Enter member's full name">
                            <div class="invalid-feedback">Please enter member's name (max 100 characters)</div>
                            <div class="form-text">Member's full name as it should appear in reports</div>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                   maxlength="20" placeholder="Optional phone number">
                            <div class="form-text">For contact purposes only</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   maxlength="100" placeholder="Optional email address">
                            <div class="invalid-feedback">Please enter a valid email address</div>
                            <div class="form-text">Will be used for account creation and notifications</div>
                        </div>
                        <div class="col-md-6">
                            <label for="join_date" class="form-label">Join Date *</label>
                            <input type="date" class="form-control" id="join_date" name="join_date" 
                                   value="<?php echo isset($_POST['join_date']) ? $_POST['join_date'] : date('Y-m-d'); ?>" 
                                   required max="<?php echo date('Y-m-d'); ?>">
                            <div class="invalid-feedback">Please select a join date</div>
                            <div class="form-text">Date when member joined this house</div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Save Member
                        </button>
                        <a href="members.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="reset" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-redo me-2"></i>Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card shadow mt-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Instructions & Guidelines</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="alert-su alert-info">
                            <h6><i class="fas fa-lightbulb me-2"></i>Required Fields:</h6>
                            <ul class="mb-0">
                                <li><strong>Full Name:</strong> Member's complete name</li>
                                <li><strong>Join Date:</strong> When they started in this house</li>
                            </ul>
                        </div>
                        
                        <div class="alert-su alert-success">
                            <h6><i class="fas fa-check-circle me-2"></i>Optional Fields:</h6>
                            <ul class="mb-0">
                                <li><strong>Phone:</strong> For contact and reminders</li>
                                <li><strong>Email:</strong> For account creation and notifications</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="alert-su alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notes:</h6>
                            <ul class="mb-0">
                                <li>Join link is valid for <strong>7 days only</strong></li>
                                <li>Member must use link to create account</li>
                                <li>No duplicate phone/email in same house</li>
                                <li>Join date cannot be in the future</li>
                            </ul>
                        </div>
                        
                        <div class="alert-su alert-primary">
                            <h6><i class="fas fa-question-circle me-2"></i>Need Help?</h6>
                            <p class="mb-0">If member doesn't have email, you can add them without it and share the join link manually.</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <h6><i class="fas fa-share-alt me-2"></i>Sharing Options:</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center p-3 border rounded">
                                <i class="fas fa-sms fa-2x text-primary mb-2"></i>
                                <h6>SMS</h6>
                                <small>Send link via text message</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3 border rounded">
                                <i class="fas fa-envelope fa-2x text-success mb-2"></i>
                                <h6>Email</h6>
                                <small>Send link via email</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3 border rounded">
                                <i class="fas fa-whatsapp fa-2x text-info mb-2"></i>
                                <h6>WhatsApp</h6>
                                <small>Share via messaging app</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyJoinLink() {
    const joinLink = document.getElementById('joinLink');
    if (joinLink) {
        joinLink.select();
        joinLink.setSelectionRange(0, 99999);
        
        try {
            document.execCommand('copy');
            showToast('Join link copied to clipboard!', 'success');
        } catch (err) {
            console.error('Failed to copy: ', err);
            showToast('Failed to copy link', 'error');
        }
    }
}

function shareJoinLink() {
    const joinLink = document.getElementById('joinLink')?.value;
    if (joinLink) {
        if (navigator.share) {
            navigator.share({
                title: 'Join <?php echo htmlspecialchars($house_name); ?>',
                text: 'Click the link to join our meal management system',
                url: joinLink
            }).catch(err => {
                console.log('Error sharing:', err);
                fallbackShare(joinLink);
            });
        } else {
            fallbackShare(joinLink);
        }
    }
}

function fallbackShare(link) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(link).then(() => {
            showToast('Link copied to clipboard! Share it manually.', 'info');
        }).catch(err => {
            alert('Share this link:\n\n' + link);
        });
    } else {
        alert('Share this link:\n\n' + link);
    }
}

function resetForm() {
    document.getElementById('addMemberForm').reset();
    document.getElementById('join_date').value = '<?php echo date("Y-m-d"); ?>';
    document.getElementById('name').focus();
    
    // Scroll to form
    document.getElementById('addMemberForm').scrollIntoView({ behavior: 'smooth' });
}

function showToast(message, type = 'info') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    // Add to container
    const container = document.getElementById('toastContainer') || createToastContainer();
    container.appendChild(toast);
    
    // Show toast
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();
    
    // Remove after hide
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(container);
    return container;
}

// Form validation
(function () {
    'use strict'
    const forms = document.querySelectorAll('.needs-validation')
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }
            form.classList.add('was-validated')
        }, false)
    })
})()

// Set max date to today
document.getElementById('join_date').max = '<?php echo date("Y-m-d"); ?>';

// Auto-focus on name field
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('name').focus();
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});
</script>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>