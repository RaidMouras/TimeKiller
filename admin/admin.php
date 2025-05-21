<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Admin Dashboard';
$current_view = isset($_GET['view']) ? $_GET['view'] : 'all';
require_once '../utils/db_config.php';

$conn = get_db_connection();

// If viewing admin history
if ($current_view === 'history') {
    $query = "SELECT ah.*, u.Email as AdminEmail,
             CASE 
                WHEN c.UserID IS NOT NULL THEN c.Username
                WHEN b.UserID IS NOT NULL THEN b.Business_Name
                ELSE 'Admin'
             END as AdminName
             FROM Admin_History ah
             LEFT JOIN Users u ON ah.AdminID = u.UserID
             LEFT JOIN Customers c ON ah.AdminID = c.UserID
             LEFT JOIN Business b ON ah.AdminID = b.UserID
             ORDER BY ah.Action_Time DESC";
    $result = $conn->query($query);
} 
// If viewing tags management
else if ($current_view === 'tags') {
    $query = "SELECT * FROM Tags ORDER BY TagID ASC";
    $result = $conn->query($query);
} 
// Default view - users
else {
    $banned_condition = ($current_view === 'banned') ? "u.Banned = 1" : "u.Banned = 0";
    
    $query = "SELECT u.*, 
            CASE 
                WHEN c.UserID IS NOT NULL THEN c.Username
                WHEN b.UserID IS NOT NULL THEN b.Business_Name
                ELSE 'Unknown'
            END as DisplayName
            FROM Users u
            LEFT JOIN Customers c ON u.UserID = c.UserID
            LEFT JOIN Business b ON u.UserID = b.UserID
            WHERE $banned_condition AND u.Is_Admin = 0
            ORDER BY u.UserID ASC";
    $result = $conn->query($query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Time Killer Admin Dashboard">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($page_title); ?> - Time Killer</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="admin.css" rel="stylesheet">
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="admin.js?v=<?php echo time(); ?>" defer></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-light bg-white">
        <div class="container">
            <span class="navbar-brand">Time Killer</span>
            <div class="navbar-center">
                <img src="../assets/mask_1.png" alt="Logo" style="height: 40px;">
            </div>
            <div class="d-flex align-items-center">
                <div class="text-end">
                    <span class="d-block" style="font-size: 1.1rem;"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                    <small class="text-muted">Administrator</small>
                </div>
                <a href="../api/logout.php" class="btn btn-outline-danger ms-3">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <a href="?view=all" class="nav-link <?php echo $current_view === 'all' ? 'active' : ''; ?>">All Users</a>
            <a href="?view=banned" class="nav-link <?php echo $current_view === 'banned' ? 'active' : ''; ?>">BanList</a>
            <a href="?view=tags" class="nav-link <?php echo $current_view === 'tags' ? 'active' : ''; ?>">Manage Tags</a>
            <a href="manage_reviews.php" class="nav-link">Manage Reviews</a>
            <a href="manage_messages.php" class="nav-link">Manage Messages</a>
            <a href="?view=history" class="nav-link <?php echo $current_view === 'history' ? 'active' : ''; ?>">Admin History</a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <?php if ($current_view === 'tags'): ?>
            <!-- Tags Management View -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Manage Tags</h4>
                <button class="btn btn-primary" onclick="showAddTagModal()">
                    <i class="fas fa-plus"></i> Add New Tag
                </button>
            </div>
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tag Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr data-tag-id="<?php echo htmlspecialchars($row['TagID']); ?>">
                                    <td><?php echo htmlspecialchars($row['TagID']); ?></td>
                                    <td><?php echo htmlspecialchars($row['Tag_Name']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="showEditTagModal(<?php echo $row['TagID']; ?>, '<?php echo htmlspecialchars($row['Tag_Name']); ?>')">
                                            Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="confirmDeleteTag(<?php echo $row['TagID']; ?>, '<?php echo htmlspecialchars($row['Tag_Name']); ?>')">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No tags found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php elseif ($current_view !== 'history'): ?>
            <input type="text" class="search-box" placeholder="Search user" id="searchUser">
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr data-user-id="<?php echo htmlspecialchars($row['UserID']); ?>">
                                <td><?php echo htmlspecialchars($row['UserID']); ?></td>
                                <td title="<?php echo htmlspecialchars($row['DisplayName']); ?>"><?php 
                                    $displayName = htmlspecialchars($row['DisplayName']);
                                    echo strlen($displayName) > 20 ? substr($displayName, 0, 20) . '...' : $displayName;
                                ?></td>
                                <td><?php echo htmlspecialchars($row['Email']); ?></td>
                                <td><?php echo htmlspecialchars($row['User_Type']); ?></td>
                                <td>
                                    <?php if($row['Banned'] == 1): ?>
                                        <span class="badge bg-danger">Banned</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-danger" onclick="toggleBan(<?php echo $row['UserID']; ?>, <?php echo $row['Banned']; ?>)">
                                        <?php echo $row['Banned'] == 1 ? 'Unban' : 'Ban'; ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <!-- Admin History View -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Admin</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['HistoryID']); ?></td>
                                    <td><?php echo htmlspecialchars($row['AdminName'] ?? $row['AdminEmail'] ?? 'Admin'); ?></td>
                                    <td>
                                        <?php 
                                            $actionClass = '';
                                            switch($row['Action_Type']) {
                                                case 'Banned User':
                                                    $actionClass = 'bg-danger';
                                                    break;
                                                case 'Unban User':
                                                    $actionClass = 'bg-success';
                                                    break;
                                                case 'Deleted Message':
                                                case 'Deleted Review':
                                                case 'Deleted Review Response':
                                                case 'Deleted Response':
                                                    $actionClass = 'bg-warning';
                                                    break;
                                                case 'Added Tag':
                                                case 'Edited Tag':
                                                case 'Changed Tag':
                                                case 'Changed tag':
                                                case 'Deleted Tag':
                                                case 'Deleted tag':
                                                    $actionClass = 'bg-info';
                                                    break;
                                                default:
                                                    $actionClass = 'bg-secondary';
                                            }
                                        ?>
                                        <span class="badge <?php echo $actionClass; ?>"><?php echo htmlspecialchars($row['Action_Type']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['Action_Description']); ?></td>
                                    <td><?php echo htmlspecialchars($row['Action_Time']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No history records found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modals -->
    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Content</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>confirm to delete</p>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="deleteReviews">
                        <label class="form-check-label" for="deleteReviews">Delete Reviews</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="deleteMessages">
                        <label class="form-check-label" for="deleteMessages">Delete Messages</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="deleteContent()">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tag Modals -->
    <!-- Add Tag Modal -->
    <div class="modal fade" id="addTagModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Tag</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="newTagName" class="form-label">Tag Name</label>
                        <input type="text" class="form-control" id="newTagName" placeholder="Enter tag name">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="addTag()">Add Tag</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Tag Modal -->
    <div class="modal fade" id="editTagModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Tag</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editTagId">
                    <div class="mb-3">
                        <label for="editTagName" class="form-label">Tag Name</label>
                        <input type="text" class="form-control" id="editTagName" placeholder="Enter tag name">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateTag()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

</body>
</html>

<?php $conn->close(); ?> 