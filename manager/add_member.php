<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

$page_title = "Add New Member";

$conn = getConnection();

$error = '';
$success = '';
$join_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $join_date = mysqli_real_escape_string($conn, $_POST['join_date']);
    
    // Validation
    if (empty($name)) {
        $error = "Name is required";
    } elseif (empty($join_date)) {
        $error = "Join date is required";
    } else {
        // Check if member already exists with same phone or email
        if (!empty($phone) || !empty($email)) {
            $check_sql = "SELECT member_id FROM members WHERE ";
            $conditions = [];
            $params = [];
            $types = '';
            
            if (!empty($phone)) {
                $conditions[] = "phone = ?";
                $params[] = $phone;
                $types .= 's';
            }
            if (!empty($email)) {
                $conditions[] = "email = ?";
                $params[] = $email;
                $types .= 's';
            }
            
            $check_sql .= implode(" OR ", $conditions);
            $check_stmt = mysqli_prepare($conn, $check_sql);
            
            if ($check_stmt) {
                mysqli_stmt_bind_param($check_stmt, $types, ...$params);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $error = "Member with same phone or email already exists";
                }
            }
        }
        
        if (empty($error)) {
            // Generate unique join token
            $join_token = bin2hex(random_bytes(32));
            $token_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
            
            // Insert member
            $sql = "INSERT INTO members (name, phone, email, join_date, created_by, join_token, token_expiry, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssiss", 
                $name, $phone, $email, $join_date, $_SESSION['user_id'], $join_token, $token_expiry);
            
            if (mysqli_stmt_execute($stmt)) {
                $member_id = mysqli_insert_id($conn);
                
                // Create join URL
                $base_url = str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', dirname(__DIR__)));
                $join_url = 'http://' . $_SERVER['HTTP_HOST'] . $base_url . '/member/join.php?token=' . $join_token;
                
                $success = "Member added successfully!";
                
                // Clear form
                $_POST = array();
            } else {
                $error = "Error adding member: " . mysqli_error($conn);
            }
        }
    }
}
?>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Add New Member</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    
                    <?php if ($join_url): ?>
                    <div class="mt-3">
                        <h6><i class="fas fa-link me-2"></i>Member Join Link:</h6>
                        <div class="input-group">
                            <input type="text" class="form-control" id="joinLink" value="<?php echo $join_url; ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyJoinLink()">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <small class="text-muted">This link expires in 7 days. Share it with the member.</small>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                   required>
                            <div class="form-text">Enter member's full name</div>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            <div class="form-text">Optional contact number</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <div class="form-text">Optional email address</div>
                        </div>
                        <div class="col-md-6">
                            <label for="join_date" class="form-label">Join Date *</label>
                            <input type="date" class="form-control" id="join_date" name="join_date" 
                                   value="<?php echo isset($_POST['join_date']) ? $_POST['join_date'] : date('Y-m-d'); ?>" 
                                   required>
                            <div class="form-text">Date when member joined the meal system</div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Member
                        </button>
                        <a href="members.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card shadow mt-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Instructions</h6>
            </div>
            <div class="card-body">
                <ol>
                    <li>Fill in the member details above. Only name and join date are required.</li>
                    <li>After saving, a unique join link will be generated automatically.</li>
                    <li>Copy the join link and share it with the member.</li>
                    <li>The member must use the link within 7 days to create their account.</li>
                    <li>Once the member creates their account, they can view their meals and balances.</li>
                </ol>
                
                <div class="alert alert-info mt-3">
                    <h6><i class="fas fa-lightbulb me-2"></i>Tips:</h6>
                    <ul class="mb-0">
                        <li>Add phone number and email for better communication</li>
                        <li>Use the correct join date for accurate monthly calculations</li>
                        <li>Save the join link securely before sharing</li>
                        <li>You can regenerate join links from the member edit page</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyJoinLink() {
    const joinLink = document.getElementById('joinLink');
    joinLink.select();
    joinLink.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        document.execCommand('copy');
        alert('Join link copied to clipboard!');
    } catch (err) {
        console.error('Failed to copy: ', err);
    }
}

// Set default date to today
document.getElementById('join_date').value = '<?php echo date("Y-m-d"); ?>';
</script>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>