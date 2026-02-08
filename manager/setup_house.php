<?php
// setup_house.php - ENHANCED VERSION
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = getConnection();

// Check if user already has a house
$sql = "SELECT house_id FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($result);

// If user already has a house, redirect to settings
if ($user_data && $user_data['house_id']) {
    $_SESSION['house_id'] = $user_data['house_id'];
    header("Location: settings.php");
    exit();
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_house'])) {
        $house_name = trim($_POST['house_name']);
        $description = trim($_POST['description']);
        
        if (empty($house_name)) {
            $error = "House name is required";
        } elseif (strlen($house_name) > 100) {
            $error = "House name must be less than 100 characters";
        } else {
            // Generate unique house code
            $house_code = '';
            $attempts = 0;
            do {
                $house_code = strtoupper(substr(md5(uniqid() . rand()), 0, 6));
                $check_sql = "SELECT house_id FROM houses WHERE house_code = ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "s", $house_code);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                $attempts++;
            } while (mysqli_stmt_num_rows($check_stmt) > 0 && $attempts < 10);
            
            if ($attempts >= 10) {
                $error = "Could not generate unique house code. Please try again.";
            } else {
                // Start transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // Insert house
                    $sql = "INSERT INTO houses (house_name, description, house_code, created_by, is_active) 
                            VALUES (?, ?, ?, ?, 1)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "sssi", $house_name, $description, $house_code, $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $new_house_id = mysqli_insert_id($conn);
                        
                        // Update user with house_id
                        $sql = "UPDATE users SET house_id = ? WHERE user_id = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "ii", $new_house_id, $user_id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            // Get user info for member creation
                            $sql = "SELECT username, email FROM users WHERE user_id = ?";
                            $stmt = mysqli_prepare($conn, $sql);
                            mysqli_stmt_bind_param($stmt, "i", $user_id);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            $current_user = mysqli_fetch_assoc($result);
                            
                            // Create user as a member of the house
                            // Create user as a member of the house
                            $sql = "INSERT INTO members (house_id, name, phone, email, join_date, status, created_by) 
                                    VALUES (?, ?, ?, ?, CURDATE(), 'active', ?)";
                            $stmt = mysqli_prepare($conn, $sql);

                            // Create variables for binding
                            $member_name = $current_user['username'];
                            $member_phone = '';
                            $member_email = isset($current_user['email']) ? $current_user['email'] : '';

                            mysqli_stmt_bind_param($stmt, "isssi", $new_house_id, $member_name, 
                            $member_phone, $member_email, $user_id);                          
                            mysqli_stmt_execute($stmt);
                            $member_id = mysqli_insert_id($conn);
                            
                            // Link user to member
                            $sql = "UPDATE users SET member_id = ? WHERE user_id = ?";
                            $stmt = mysqli_prepare($conn, $sql);
                            mysqli_stmt_bind_param($stmt, "ii", $member_id, $user_id);
                            mysqli_stmt_execute($stmt);
                            
                            // Commit transaction
                            mysqli_commit($conn);
                            
                            // Set session variables
                            $_SESSION['house_id'] = $new_house_id;
                            $_SESSION['member_id'] = $member_id;
                            $_SESSION['house_code'] = $house_code;
                            
                            $_SESSION['success'] = "House created successfully! Your House Code is: <strong>$house_code</strong>";
                            header("Location: settings.php");
                            exit();
                        } else {
                            throw new Exception("Error updating user with house: " . mysqli_error($conn));
                        }
                    } else {
                        throw new Exception("Error creating house: " . mysqli_error($conn));
                    }
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error = $e->getMessage();
                }
            }
        }
    }
    
    elseif (isset($_POST['join_house'])) {
        $house_code = strtoupper(trim($_POST['house_code']));
        
        if (empty($house_code)) {
            $error = "House code is required";
        } elseif (strlen($house_code) !== 6) {
            $error = "House code must be 6 characters";
        } else {
            // Check if house exists and is active
            $sql = "SELECT house_id, house_name, is_active FROM houses WHERE house_code = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $house_code);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $house = mysqli_fetch_assoc($result);
            
            if (!$house) {
                $error = "Invalid house code. Please check and try again.";
            } elseif (!$house['is_active']) {
                $error = "This house is currently inactive. Please contact the house manager.";
            } else {
                // Start transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // Update user with house_id
                    $sql = "UPDATE users SET house_id = ? WHERE user_id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ii", $house['house_id'], $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        // Get user info for member creation
                        $sql = "SELECT username, email FROM users WHERE user_id = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "i", $user_id);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        $current_user = mysqli_fetch_assoc($result);
                        
                        // Create user as a member of the house
                        $sql = "INSERT INTO members (house_id, name, phone, email, join_date, status, created_by) 
                                VALUES (?, ?, ?, ?, CURDATE(), 'active', ?)";
                        $stmt = mysqli_prepare($conn, $sql);
                        $phone = ''; // Empty phone for now
                        mysqli_stmt_bind_param($stmt, "isssi", $house['house_id'], $current_user['username'], 
                                              $phone, $current_user['email'], $user_id);
                        mysqli_stmt_execute($stmt);
                        $member_id = mysqli_insert_id($conn);
                        
                        // Link user to member
                        $sql = "UPDATE users SET member_id = ? WHERE user_id = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "ii", $member_id, $user_id);
                        mysqli_stmt_execute($stmt);
                        
                        // Commit transaction
                        mysqli_commit($conn);
                        
                        // Set session variables
                        $_SESSION['house_id'] = $house['house_id'];
                        $_SESSION['member_id'] = $member_id;
                        
                        $_SESSION['success'] = "Successfully joined house: <strong>" . htmlspecialchars($house['house_name']) . "</strong>";
                        header("Location: settings.php");
                        exit();
                    } else {
                        throw new Exception("Error joining house: " . mysqli_error($conn));
                    }
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error = $e->getMessage();
                }
            }
        }
    }
}

// HTML starts here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>mealsa - Setup Your House</title>
    <link rel="icon" type="image/png" href="../image/icon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        .setup-container {
            max-width: 500px;
            margin: 0 auto;
        }
        .setup-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .setup-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .setup-body {
            padding: 30px;
        }
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            background-color: #e9ecef;
            border-color: #dee2e6 #dee2e6 #e9ecef;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
        }
        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
            border: none;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        .alert {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div class="setup-header">
                <h3><i class="fas fa-house-user me-2"></i>House Setup</h3>
                <p class="mb-0">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</p>
            </div>
            
            <div class="setup-body">
                <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); endif; ?>
                
                <!-- Tabs for Create/Join -->
                <ul class="nav nav-tabs nav-justified mb-4" id="houseTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="create-tab" data-bs-toggle="tab" 
                                data-bs-target="#create" type="button" role="tab">
                            <i class="fas fa-plus-circle me-2"></i>Create House
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="join-tab" data-bs-toggle="tab" 
                                data-bs-target="#join" type="button" role="tab">
                            <i class="fas fa-sign-in-alt me-2"></i>Join House
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="houseTabContent">
                    <!-- Create House Tab -->
                    <div class="tab-pane fade show active" id="create" role="tabpanel">
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="house_name" class="form-label">House Name *</label>
                                <input type="text" class="form-control" id="house_name" name="house_name" 
                                       value="<?php echo isset($_POST['house_name']) ? htmlspecialchars($_POST['house_name']) : ''; ?>" 
                                       required placeholder="e.g., Smith Family Home" maxlength="100">
                                <div class="invalid-feedback">Please enter a house name (max 100 characters)</div>
                                <small class="text-muted">This will be displayed throughout the system</small>
                            </div>
                            
                            <div class="mb-4">
                                <label for="description" class="form-label">Description (Optional)</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3" placeholder="Brief description of your household"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>As the creator</strong>, you'll be the <strong>Manager</strong> of this house with full administrative rights.
                            </div>
                            
                            <button type="submit" name="create_house" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-check-circle me-2"></i>Create House
                            </button>
                        </form>
                    </div>
                    
                    <!-- Join House Tab -->
                    <div class="tab-pane fade" id="join" role="tabpanel">
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <label for="house_code" class="form-label">House Code *</label>
                                <input type="text" class="form-control text-uppercase" id="house_code" 
                                       name="house_code" required maxlength="6" minlength="6"
                                       value="<?php echo isset($_POST['house_code']) ? htmlspecialchars($_POST['house_code']) : ''; ?>"
                                       placeholder="e.g., A1B2C3">
                                <div class="invalid-feedback">Please enter a 6-character house code</div>
                                <small class="text-muted">
                                    Get this 6-character code from your house manager
                                </small>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Note:</strong> You'll join as a <strong>Member</strong>. Only Managers can add expenses and manage house settings.
                            </div>
                            
                            <button type="submit" name="join_house" class="btn btn-success btn-lg w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Join House
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <small class="text-muted">
                        <i class="fas fa-question-circle me-1"></i>
                        Need help? <a href="#" data-bs-toggle="modal" data-bs-target="#helpModal">Click here for guidance</a>
                    </small>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4 text-white">
            <p>&copy; <?php echo date('Y'); ?> Meal Management System</p>
        </div>
    </div>
    
    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-question-circle me-2"></i>House Setup Help</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Create New House:</h6>
                    <ul>
                        <li>You become the <strong>Manager</strong> of the house</li>
                        <li>You can invite others using the generated House Code</li>
                        <li>You have full control over house settings and members</li>
                    </ul>
                    
                    <h6>Join Existing House:</h6>
                    <ul>
                        <li>You join as a <strong>Member</strong></li>
                        <li>You need the 6-character House Code from the manager</li>
                        <li>You can record meals and view house statistics</li>
                        <li>Only managers can add expenses and manage settings</li>
                    </ul>
                    
                    <h6>House Code:</h6>
                    <p>A unique 6-character code (letters and numbers) that identifies your house.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })();
        
        // Auto-switch to uppercase for house code
        document.getElementById('house_code')?.addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
        
        // Initialize tabs
        var triggerTabList = [].slice.call(document.querySelectorAll('#houseTab button'))
        triggerTabList.forEach(function (triggerEl) {
            var tabTrigger = new bootstrap.Tab(triggerEl)
            triggerEl.addEventListener('click', function (event) {
                event.preventDefault()
                tabTrigger.show()
            })
        });
        
        // Auto-focus on house name field
        document.getElementById('house_name')?.focus();
        
        // Show active tab based on previous submission
        <?php if (isset($_POST['join_house'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var joinTab = document.getElementById('join-tab');
            if (joinTab) {
                var tab = new bootstrap.Tab(joinTab);
                tab.show();
            }
        });
        <?php endif; ?>
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 8000);
    </script>
</body>
</html>