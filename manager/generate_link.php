<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$auth = new Auth();
$functions = new Functions();

$auth->requireRole('manager');

$page_title = "Generate Join Link";

$conn = getConnection();

$error = '';
$success = '';
$join_url = '';

// Get all members without accounts
$sql = "SELECT m.*, u.user_id 
        FROM members m 
        LEFT JOIN users u ON m.member_id = u.member_id 
        WHERE m.status = 'active' AND u.user_id IS NULL 
        ORDER BY m.name ASC";
$result = mysqli_query($conn, $sql);
$members_without_accounts = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get members with expired tokens
$sql = "SELECT m.*, u.user_id 
        FROM members m 
        LEFT JOIN users u ON m.member_id = u.member_id 
        WHERE m.status = 'active' 
        AND m.join_token IS NOT NULL 
        AND m.token_expiry < NOW() 
        AND u.user_id IS NULL 
        ORDER BY m.name ASC";
$result = mysqli_query($conn, $sql);
$members_expired_tokens = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get members with active tokens
$sql = "SELECT m.*, u.user_id 
        FROM members m 
        LEFT JOIN users u ON m.member_id = u.member_id 
        WHERE m.status = 'active' 
        AND m.join_token IS NOT NULL 
        AND m.token_expiry >= NOW() 
        AND u.user_id IS NULL 
        ORDER BY m.token_expiry ASC";
$result = mysqli_query($conn, $sql);
$members_active_tokens = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['member_id'])) {
        $member_id = intval($_POST['member_id']);
        
        // Get member details
        $member_sql = "SELECT * FROM members WHERE member_id = ? AND status = 'active'";
        $member_stmt = mysqli_prepare($conn, $member_sql);
        mysqli_stmt_bind_param($member_stmt, "i", $member_id);
        mysqli_stmt_execute($member_stmt);
        $member_result = mysqli_stmt_get_result($member_stmt);
        $member = mysqli_fetch_assoc($member_result);
        
        if (!$member) {
            $error = "Member not found or inactive";
        } else {
            // Check if member already has account
            $check_sql = "SELECT user_id FROM users WHERE member_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "i", $member_id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $error = "This member already has an account";
            } else {
                // Generate new token
                $new_token = bin2hex(random_bytes(32));
                $token_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
                
                // Update member with new token
                $update_sql = "UPDATE members SET join_token = ?, token_expiry = ? WHERE member_id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "ssi", $new_token, $token_expiry, $member_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    // Create join URL
                    $base_url = str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', dirname(__DIR__)));
                    $join_url = 'http://' . $_SERVER['HTTP_HOST'] . $base_url . '/member/join.php?token=' . $new_token;
                    
                    $success = "Join link generated successfully for " . htmlspecialchars($member['name']) . "!";
                    
                    // Refresh member lists
                    $sql = "SELECT m.*, u.user_id 
                            FROM members m 
                            LEFT JOIN users u ON m.member_id = u.member_id 
                            WHERE m.status = 'active' AND u.user_id IS NULL 
                            ORDER BY m.name ASC";
                    $result = mysqli_query($conn, $sql);
                    $members_without_accounts = mysqli_fetch_all($result, MYSQLI_ASSOC);
                    
                    $sql = "SELECT m.*, u.user_id 
                            FROM members m 
                            LEFT JOIN users u ON m.member_id = u.member_id 
                            WHERE m.status = 'active' 
                            AND m.join_token IS NOT NULL 
                            AND m.token_expiry >= NOW() 
                            AND u.user_id IS NULL 
                            ORDER BY m.token_expiry ASC";
                    $result = mysqli_query($conn, $sql);
                    $members_active_tokens = mysqli_fetch_all($result, MYSQLI_ASSOC);
                } else {
                    $error = "Error generating join link: " . mysqli_error($conn);
                }
            }
        }
    } elseif (isset($_POST['generate_all'])) {
        // Generate links for all members without accounts
        $generated_count = 0;
        $error_messages = [];
        
        foreach ($members_without_accounts as $member) {
            // Check if already has active token
            if ($member['join_token'] && strtotime($member['token_expiry']) > time()) {
                continue; // Skip if already has active token
            }
            
            // Generate new token
            $new_token = bin2hex(random_bytes(32));
            $token_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
            
            // Update member
            $update_sql = "UPDATE members SET join_token = ?, token_expiry = ? WHERE member_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "ssi", $new_token, $token_expiry, $member['member_id']);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $generated_count++;
            } else {
                $error_messages[] = "Error for " . $member['name'] . ": " . mysqli_error($conn);
            }
        }
        
        if ($generated_count > 0) {
            $success = "Generated join links for $generated_count members successfully!";
            
            // Refresh lists
            $sql = "SELECT m.*, u.user_id 
                    FROM members m 
                    LEFT JOIN users u ON m.member_id = u.member_id 
                    WHERE m.status = 'active' AND u.user_id IS NULL 
                    ORDER BY m.name ASC";
            $result = mysqli_query($conn, $sql);
            $members_without_accounts = mysqli_fetch_all($result, MYSQLI_ASSOC);
            
            $sql = "SELECT m.*, u.user_id 
                    FROM members m 
                    LEFT JOIN users u ON m.member_id = u.member_id 
                    WHERE m.status = 'active' 
                    AND m.join_token IS NOT NULL 
                    AND m.token_expiry >= NOW() 
                    AND u.user_id IS NULL 
                    ORDER BY m.token_expiry ASC";
            $result = mysqli_query($conn, $sql);
            $members_active_tokens = mysqli_fetch_all($result, MYSQLI_ASSOC);
        } else {
            $error = "No new links were generated. " . implode(" ", $error_messages);
        }
    }
}

// Count statistics
$total_without_accounts = count($members_without_accounts);
$total_expired_tokens = count($members_expired_tokens);
$total_active_tokens = count($members_active_tokens);
?>
<div class="row">
    <div class="col-lg-10 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="fas fa-link me-2"></i>Generate Member Join Links</h5>
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
                        <h6><i class="fas fa-external-link-alt me-2"></i>Generated Join Link:</h6>
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
                
                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-2">Without Accounts</h6>
                                <h3 class="text-primary mb-0"><?php echo $total_without_accounts; ?></h3>
                                <small class="text-muted">Need join links</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-2">Active Links</h6>
                                <h3 class="text-warning mb-0"><?php echo $total_active_tokens; ?></h3>
                                <small class="text-muted">Valid for 7 days</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card border-danger">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-2">Expired Links</h6>
                                <h3 class="text-danger mb-0"><?php echo $total_expired_tokens; ?></h3>
                                <small class="text-muted">Need regeneration</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Generate for Specific Member -->
                <div class="card border-primary mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-user-plus me-2"></i>Generate for Specific Member</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="row g-3">
                            <div class="col-md-8">
                                <select class="form-select" name="member_id" required>
                                    <option value="">Select Member</option>
                                    <?php foreach ($members_without_accounts as $member): ?>
                                    <option value="<?php echo $member['member_id']; ?>">
                                        <?php echo htmlspecialchars($member['name']); ?>
                                        <?php if ($member['phone']): ?> (<?php echo $member['phone']; ?>)<?php endif; ?>
                                        <?php if ($member['join_token']): ?>
                                        <?php if (strtotime($member['token_expiry']) > time()): ?>
                                        <span class="text-warning"> - Has active link</span>
                                        <?php else: ?>
                                        <span class="text-danger"> - Link expired</span>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select member who needs join link</div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-link me-2"></i>Generate Link
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Generate for All Members -->
                <?php if ($total_without_accounts > 0): ?>
                <div class="card border-success mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-users me-2"></i>Generate for All Members</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This will generate join links for all <?php echo $total_without_accounts; ?> members who don't have accounts.
                            Members with existing active links will be skipped.
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="generate_all" value="1">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-bolt me-2"></i>Generate Links for All Members
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Members with Active Links -->
                <?php if ($total_active_tokens > 0): ?>
                <div class="card border-warning mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Members with Active Join Links</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Contact</th>
                                        <th>Link Generated</th>
                                        <th>Expires In</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members_active_tokens as $member): 
                                        $expires_in = strtotime($member['token_expiry']) - time();
                                        $days_left = floor($expires_in / (60 * 60 * 24));
                                        $hours_left = floor(($expires_in % (60 * 60 * 24)) / (60 * 60));
                                        
                                        // Create join URL
                                        $base_url = str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', dirname(__DIR__)));
                                        $member_join_url = 'http://' . $_SERVER['HTTP_HOST'] . $base_url . '/member/join.php?token=' . $member['join_token'];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($member['phone']): ?>
                                            <div><i class="fas fa-phone text-muted me-2"></i><?php echo $member['phone']; ?></div>
                                            <?php endif; ?>
                                            <?php if ($member['email']): ?>
                                            <div><i class="fas fa-envelope text-muted me-2"></i><?php echo $member['email']; ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($member['token_expiry'] . ' -7 days')); ?>
                                        </td>
                                        <td>
                                            <?php if ($days_left > 0): ?>
                                            <span class="badge bg-success"><?php echo $days_left; ?> days</span>
                                            <?php elseif ($hours_left > 0): ?>
                                            <span class="badge bg-warning"><?php echo $hours_left; ?> hours</span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">Expired</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-info" 
                                                        onclick="copyMemberLink('<?php echo $member_join_url; ?>', '<?php echo addslashes($member['name']); ?>')"
                                                        title="Copy Link">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="member_id" value="<?php echo $member['member_id']; ?>">
                                                    <button type="submit" class="btn btn-warning" title="Regenerate">
                                                        <i class="fas fa-sync"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Instructions -->
                <div class="card border-info">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Instructions</h6>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li>Select a member from the dropdown and click "Generate Link" to create a unique join link.</li>
                            <li>Use the "Generate for All Members" button to create links for all members without accounts.</li>
                            <li>Copy the generated link and share it with the member via email, WhatsApp, or SMS.</li>
                            <li>The member must use the link within 7 days to create their account.</li>
                            <li>Active links are shown in the table above with expiration information.</li>
                            <li>Regenerate links if they expire before the member creates an account.</li>
                        </ol>
                        
                        <div class="alert alert-warning mt-3">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notes:</h6>
                            <ul class="mb-0">
                                <li>Each join link is unique and can only be used once</li>
                                <li>Links expire after 7 days for security</li>
                                <li>Once a member creates an account, their link becomes invalid</li>
                                <li>Keep the join links secure - they provide access to create accounts</li>
                                <li>Monitor the "Active Links" section to track pending account creations</li>
                            </ul>
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
            alert('Join link copied to clipboard!');
        } catch (err) {
            console.error('Failed to copy: ', err);
        }
    }
}

function copyMemberLink(link, memberName) {
    // Create temporary input
    const tempInput = document.createElement('input');
    tempInput.value = link;
    document.body.appendChild(tempInput);
    tempInput.select();
    tempInput.setSelectionRange(0, 99999);
    
    try {
        document.execCommand('copy');
        alert('Join link for ' + memberName + ' copied to clipboard!');
    } catch (err) {
        console.error('Failed to copy: ', err);
    }
    
    document.body.removeChild(tempInput);
}
</script>

<?php 
mysqli_close($conn);
require_once '../includes/footer.php'; 
?>