<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: index.php");
    exit;
}

// Check if there's a password change message, if yes and it comes from a redirect after POST request, automatically show the modal
$showPasswordModal = false;
if (isset($_SESSION['password_message'])) {
    $showPasswordModal = true;
}

$page_title = 'User Profile';
//$page_specific_css = '<link href="css/pages/profile.css" rel="stylesheet">';
include 'utils/header.php';
?>

    <!-- Main Content -->
    <div class="container mb-5">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="sidebar">
                    <ul class="sidebar-nav">
                        <li><a href="profile.php" class="active">My Profile</a></li>
                        <?php if($_SESSION['user_type'] != 'Business'): ?>
                        <li><a href="history.php">History</a></li>
                        <?php endif; ?>
                        <li><a href="message.php">Messages</a></li>
                    </ul>
                </div>
            </div>
            
            <!-- Profile Info -->
            <div class="col-md-9">
                <div class="main-content">
                    <table class="table profile-table">
                        <tr>
                            <td width="30%" id="name-label"><?php echo $_SESSION['user_type'] == 'Business' ? 'Business Name' : 'User Name'; ?></td>
                            <td id="username-value"><?php echo $_SESSION['username'] ?? 'Not set'; ?></td>
                            <td width="10%"><a class="edit-link" data-field="username">Edit</a></td>
                        </tr>
                        <tr>
                            <td>Profile Picture</td>
                            <td>
                                <div class="profile-pic-container">
                                    <img id="profile-picture" src="api/icon_handler.php?action=get" alt="Profile Picture">
                                </div>
                            </td>
                            <td><a class="edit-link" data-field="profile_picture">Edit</a></td>
                        </tr>
                        <tr>
                            <td>User Type</td>
                            <td id="user-type"><?php echo $_SESSION['user_type'] ?? 'Not set'; ?></td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>Email Address</td>
                            <td id="email-value"><?php echo $_SESSION['email'] ?? 'Not set'; ?></td>
                            <td></td>
                            <!-- <td><a class="edit-link" data-field="email">Edit</a></td> -->
                        </tr>
                        <tr id="bio-row">
                            <td>Bio</td>
                            <td id="bio-value"><?php echo isset($_SESSION['bio']) ? $_SESSION['bio'] : ''; ?></td>
                            <td><a class="edit-link" data-field="bio">Edit</a></td>
                        </tr>
                        <tr id="password-row">
                            <td>Password</td>
                            <td>********</td>
                            <td><a href="#" data-bs-toggle="modal" data-bs-target="#passwordChangeModal">Change Password</a></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" id="field-name">
                        <!-- Text input -->
                        <div id="text-field-container">
                            <div class="mb-3">
                                <label for="field-value" class="form-label"></label>
                                <input type="text" class="form-control" id="field-value">
                            </div>
                        </div>
                        <!-- Bio textarea -->
                        <div id="textarea-field-container" style="display: none;">
                            <div class="mb-3">
                                <label for="field-textarea" class="form-label">Biography</label>
                                <textarea class="form-control" id="field-textarea" rows="4"></textarea>
                            </div>
                        </div>
                        <!-- File upload -->
                        <div id="file-field-container" style="display: none;">
                            <div class="mb-3">
                                <label for="field-file" class="form-label">Upload Image</label>
                                <input type="file" class="form-control" id="field-file" accept="image/*">
                            </div>
                            <div class="image-editor mt-3" style="display: none;">
                                <div class="cropper-container mb-3">
                                    <img id="cropper-image" src="" alt="Image Preview" style="max-width: 100%; display: block;">
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <button type="button" class="btn btn-sm btn-secondary" id="rotate-left">Rotate Left</button>
                                    <button type="button" class="btn btn-sm btn-secondary" id="rotate-right">Rotate Right</button>
                                </div>
                            </div>
                            <div class="preview-container mt-2" style="max-width: 200px; display: none;">
                                <img id="image-preview" src="" alt="Preview" class="img-fluid">
                            </div>
                        </div>
                        <!-- Password update -->
                        <div id="password-field-container" style="display: none;">
                            <div id="password-error-container"></div>
                            <div class="mb-3">
                                <label for="edit-current-password" class="form-label">Current Password</label>
                                <input type="text" class="form-control" id="edit-current-password" name="current_password">
                            </div>
                            <div class="mb-3">
                                <label for="edit-new-password" class="form-label">New Password</label>
                                <input type="text" class="form-control" id="edit-new-password" name="new_password">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveChanges">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Password change modal -->
<div class="modal fade" id="passwordChangeModal" tabindex="-1" aria-labelledby="passwordChangeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="passwordChangeModalLabel">Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="password-change-messages">
                <?php if(isset($_SESSION['password_message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['password_status'] ? 'success' : 'danger'; ?>">
                        <?php echo $_SESSION['password_message']; ?>
                    </div>
                    <?php unset($_SESSION['password_message'], $_SESSION['password_status']); ?>
                <?php endif; ?>
                </div>
                
                <form id="passwordChangeForm" method="post" action="api/change_password.php">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="text" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="text" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    <div class="modal-footer p-0 pt-3 border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- API Path Information -->
<script>
    // Define API path variables for JavaScript
    var apiPaths = {
        profile: "api/profile_api.php",
        icon: "api/icon_handler.php",
        password: "api/change_password.php"
    };
</script>

<!-- Load profile.js -->
<script src="js/pages/profile.js"></script>

<?php if($showPasswordModal): ?>
<script>
    // Automatically show password change modal when document is loaded
    document.addEventListener('DOMContentLoaded', function() {
        var passwordModal = new bootstrap.Modal(document.getElementById('passwordChangeModal'));
        passwordModal.show();
    });
</script>
<?php endif; ?>

<?php
include 'utils/footer.php';
?> 