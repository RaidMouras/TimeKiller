<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Manage Messages';
require_once '../utils/db_config.php';

$conn = get_db_connection();

// Get all messages with sender and receiver information
$query = "SELECT m.*, 
          CASE 
              WHEN cs.UserID IS NOT NULL THEN cs.Username
              WHEN bs.UserID IS NOT NULL THEN bs.Business_Name
              ELSE 'Unknown'
          END as SenderName,
          CASE 
              WHEN cr.UserID IS NOT NULL THEN cr.Username
              WHEN br.UserID IS NOT NULL THEN br.Business_Name
              ELSE 'Unknown'
          END as ReceiverName
          FROM Messages m
          LEFT JOIN Users us ON m.SenderID = us.UserID
          LEFT JOIN Users ur ON m.ReceiverID = ur.UserID
          LEFT JOIN Customers cs ON m.SenderID = cs.UserID
          LEFT JOIN Customers cr ON m.ReceiverID = cr.UserID
          LEFT JOIN Business bs ON m.SenderID = bs.UserID
          LEFT JOIN Business br ON m.ReceiverID = br.UserID
          ORDER BY m.Timesent DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Time Killer Admin - Manage Messages">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($page_title); ?> - Time Killer</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="admin.css" rel="stylesheet">
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="admin.js" defer></script>
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
            <a href="admin.php?view=all" class="nav-link">All Users</a>
            <a href="admin.php?view=banned" class="nav-link">BanList</a>
            <a href="admin.php?view=tags" class="nav-link">Manage Tags</a>
            <a href="manage_reviews.php" class="nav-link">Manage Reviews</a>
            <a href="manage_messages.php" class="nav-link active">Manage Messages</a>
            <a href="admin.php?view=history" class="nav-link">Admin History</a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Manage Messages</h4>
                <input type="text" class="search-box" placeholder="Search messages" id="searchMessages">
            </div>
            
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr data-message-id="<?php echo htmlspecialchars($row['MessageID']); ?>">
                                    <td><?php echo htmlspecialchars($row['MessageID']); ?></td>
                                    <td><?php echo htmlspecialchars($row['SenderName']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ReceiverName']); ?></td>
                                    <td>
                                        <?php 
                                            $content = htmlspecialchars($row['Content']);
                                            echo strlen($content) > 50 ? substr($content, 0, 50) . '...' : $content;
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['Timesent']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info view-message-btn" 
                                                data-message-id="<?php echo $row['MessageID']; ?>"
                                                data-message-content="<?php echo htmlspecialchars($row['Content']); ?>"
                                                data-sender-name="<?php echo htmlspecialchars($row['SenderName']); ?>"
                                                data-receiver-name="<?php echo htmlspecialchars($row['ReceiverName']); ?>"
                                                data-date="<?php echo htmlspecialchars($row['Timesent']); ?>">
                                            View
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-message-btn" 
                                                data-message-id="<?php echo $row['MessageID']; ?>">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No messages found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Detail Modal -->
    <div class="modal fade" id="messageDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Message Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between">
                                <span>From: <strong id="messageSender"></strong></span>
                                <span>To: <strong id="messageReceiver"></strong></span>
                            </div>
                            <div class="text-muted mt-2 small" id="messageDate"></div>
                        </div>
                        <div class="card-body">
                            <p class="card-text" id="messageContent"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="deleteMessageFromModal">Delete Message</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteMessageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this message?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteMessage">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let currentMessageId = null;
            const messageDetailModal = new bootstrap.Modal(document.getElementById('messageDetailModal'));
            const deleteMessageModal = new bootstrap.Modal(document.getElementById('deleteMessageModal'));
            
            // Set up search functionality
            document.getElementById('searchMessages').addEventListener('input', function(e) {
                const searchText = e.target.value.toLowerCase().trim();
                const tableRows = document.querySelectorAll('tbody tr');
                
                tableRows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    let rowText = '';
                    cells.forEach((cell, index) => {
                        if (index < 5) { // Exclude the Actions column
                            rowText += cell.textContent.toLowerCase() + ' ';
                        }
                    });
                    
                    row.style.display = rowText.includes(searchText) ? '' : 'none';
                });
            });
            
            // View message details
            document.querySelectorAll('.view-message-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const messageId = this.dataset.messageId;
                    currentMessageId = messageId;
                    
                    document.getElementById('messageSender').textContent = this.dataset.senderName;
                    document.getElementById('messageReceiver').textContent = this.dataset.receiverName;
                    document.getElementById('messageDate').textContent = this.dataset.date;
                    document.getElementById('messageContent').textContent = this.dataset.messageContent;
                    
                    messageDetailModal.show();
                });
            });
            
            // Delete message button in detail modal
            document.getElementById('deleteMessageFromModal').addEventListener('click', function() {
                messageDetailModal.hide();
                deleteMessageModal.show();
            });
            
            // Delete message button in table
            document.querySelectorAll('.delete-message-btn').forEach(button => {
                button.addEventListener('click', function() {
                    currentMessageId = this.dataset.messageId;
                    deleteMessageModal.show();
                });
            });
            
            // Confirm delete message
            document.getElementById('confirmDeleteMessage').addEventListener('click', function() {
                if (currentMessageId) {
                    deleteMessage(currentMessageId);
                }
            });
            
            function deleteMessage(messageId) {
                // Show loading indicator
                document.getElementById('confirmDeleteMessage').disabled = true;
                document.getElementById('confirmDeleteMessage').innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';
                
                $.ajax({
                    url: 'delete_content.php',
                    method: 'POST',
                    data: { 
                        type: 'message',
                        id: messageId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const row = document.querySelector(`tr[data-message-id="${messageId}"]`);
                            if (row) {
                                row.remove();
                            }
                            deleteMessageModal.hide();
                            
                            // Add success notification
                            const notification = document.createElement('div');
                            notification.className = 'alert alert-success alert-dismissible fade show';
                            notification.innerHTML = `
                                ${response.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            `;
                            document.querySelector('.main-content').insertBefore(notification, document.querySelector('.table-container'));
                            
                            // Auto-dismiss notification after 5 seconds
                            setTimeout(() => {
                                notification.classList.remove('show');
                                setTimeout(() => notification.remove(), 150);
                            }, 5000);
                        } else {
                            alert('Error: ' + (response.error || 'Failed to delete message'));
                        }
                    },
                    error: function() {
                        alert('Error: Failed to delete message');
                    },
                    complete: function() {
                        document.getElementById('confirmDeleteMessage').disabled = false;
                        document.getElementById('confirmDeleteMessage').textContent = 'Delete';
                    }
                });
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?> 