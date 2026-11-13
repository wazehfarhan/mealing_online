<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth.php';
require_once '../includes/csrf.php';

$auth = new Auth();
$auth->requireRole('manager');

$user_id = (int) $_SESSION['user_id'];
$house_id = $_SESSION['house_id'] ?? null;
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

// Generate CSRF token
$csrf_token = generateCSRFToken();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    audit_log_event('manager.member.create.view');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token invalid. Please try again.";
    } else {
        $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
        $phone = is_string($_POST['phone'] ?? null) ? trim($_POST['phone']) : '';
        $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $join_date = is_string($_POST['join_date'] ?? null) ? trim($_POST['join_date']) : '';

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

                    mysqli_stmt_bind_param(
                        $stmt,
                        "issssiss",
                        $house_id,
                        $name,
                        $insert_phone,
                        $insert_email,
                        $join_date,
                        $user_id,
                        $join_token,
                        $token_expiry
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Error adding member: " . mysqli_error($conn));
                    }

                    $member_id = mysqli_insert_id($conn);

                    // Commit transaction
                    mysqli_commit($conn);
                    audit_log_event('manager.member.create.success', [
                        'member_id' => $member_id,
                    ]);

                    // Create join URL
                    $base_url = str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', dirname(__DIR__)));
                    $join_url = 'http://' . $_SERVER['HTTP_HOST'] . $base_url . '/member/join.php?token=' . $join_token;

                    // Store success message in session and redirect to prevent resubmission
                    $_SESSION['success'] = "Member added successfully! Member ID: #" . $member_id;
                    $_SESSION['join_url'] = $join_url;

                    // Redirect to prevent form resubmission (PRG pattern)
                    header("Location: members.php");
                    exit();
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    audit_log_event('manager.member.create.error', [
                        'error' => $e->getMessage(),
                    ]);
                    $error = "Unable to add member. Please try again.";
                }
            }
        }
    }

    if ($error !== '') {
        audit_log_event('manager.member.create.rejected', [
            'reason' => $error,
        ]);
    }
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    $join_url = $_SESSION['join_url'] ?? '';
    unset($_SESSION['success']);
    unset($_SESSION['join_url']);
}

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
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php unset($_SESSION['error']);
                    endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>

                            <?php if ($join_url): ?>
                                <div class="mt-3">
                                    <h6><i class="fas fa-link me-2"></i>Member Join Link:</h6>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="joinLink" value="<?php echo htmlspecialchars($join_url, ENT_QUOTES, 'UTF-8'); ?>" readonly>
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
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
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
                                    value="<?php echo htmlspecialchars($_POST['join_date'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"
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



<?php
    mysqli_close($conn);
    require_once '../includes/footer.php';
?>