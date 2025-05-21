<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Customer') {
    header("Location: ../index.php");
    exit;
}

$page_title = 'User Messages';

// Get database connection
require_once 'utils/db_config.php';
$conn = get_db_connection();

// Pagination settings
$messages_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $messages_per_page;

// Get user's messages grouped by sender (other users only)
$query = "SELECT 
          m.SenderID as sender_id,
          c.Username as sender_name,
          c.Profile_Picture as sender_pic,
          c.Verified as sender_verified,
          MAX(m.Timesent) as last_message_time,
          COUNT(m.MessageID) as message_count,
          SUM(CASE WHEN m.Negotiation_Status IS NOT NULL THEN 1 ELSE 0 END) as negotiation_count
          FROM Messages m
          JOIN Customers c ON m.SenderID = c.UserID
          JOIN Users u ON m.SenderID = u.UserID
          WHERE m.ReceiverID = ? 
          AND u.User_Type = 'Customer'  -- Only show messages from other customers
          AND m.SenderID != ?  -- Don't show messages from self
          GROUP BY m.SenderID
          ORDER BY last_message_time DESC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $_SESSION['user_id'], $_SESSION['user_id'], $messages_per_page, $offset);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT SenderID) as total 
               FROM Messages 
               WHERE ReceiverID = ? 
               AND SenderID IN (SELECT UserID FROM Users WHERE User_Type = 'Customer')
               AND SenderID != ?";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$count_stmt->execute();
$total_conversations = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_conversations / $messages_per_page);

// Handle message deletion
if (isset($_GET['delete_conversation'])) {
    $sender_id = intval($_GET['sender_id']);
    
    // Delete all messages between these users
    $delete_stmt = $conn->prepare("DELETE FROM Messages 
                                 WHERE (SenderID = ? AND ReceiverID = ?)
                                 OR (SenderID = ? AND ReceiverID = ?)");
    $delete_stmt->bind_param("iiii", $sender_id, $_SESSION['user_id'], $_SESSION['user_id'], $sender_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['success'] = "Conversation deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete conversation.";
    }
    
    $delete_stmt->close();
    header("Location: ptpMessage.php");
    exit();
}

include '../utils/header.php';
?>

<!-- Main Content -->
<div class="container mb-5">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="sidebar">
                <ul class="sidebar-nav">
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="history.php">History</a></li>
                    <li><a href="message.php">Business Messages</a></li>
                    <li><a href="ptpMessage.php" class="active">User Messages</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Messages Content -->
        <div class="col-md-9">
            <div class="main-content">
                <h2>User Messages</h2>
                <p class="text-muted">Messages from other users</p>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <?php if (empty($conversations)): ?>
                    <div class="alert alert-info mt-3">
                        No messages found from other users.
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($conversations as $conv): ?>
                            <div class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <?php if ($conv['sender_pic']): ?>
                                            <img src="<?= htmlspecialchars($conv['sender_pic']) ?>" 
                                                 class="rounded-circle me-3" 
                                                 width="50" 
                                                 height="50" 
                                                 alt="<?= htmlspecialchars($conv['sender_name']) ?>">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 50px; height: 50px;">
                                                <?= substr(htmlspecialchars($conv['sender_name']), 0, 1) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div>
                                            <h5 class="mb-1">
                                                <?= htmlspecialchars($conv['sender_name']) ?>
                                                <?php if ($conv['sender_verified']): ?>
                                                    <span class="badge bg-primary ms-1" title="Verified User">
                                                        <i class="fas fa-check"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </h5>
                                            <small class="text-muted">
                                                <?= $conv['message_count'] ?> message<?= $conv['message_count'] > 1 ? 's' : '' ?>
                                                <?php if ($conv['negotiation_count'] > 0): ?>
                                                    | <?= $conv['negotiation_count'] ?> negotiation<?= $conv['negotiation_count'] > 1 ? 's' : '' ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted"><?= date('M j, g:i a', strtotime($conv['last_message_time'])) ?></small>
                                        <div class="mt-2">
                                            <a href="ptpChat.php?customer_id=<?= $conv['sender_id'] ?>" 
                                               class="btn btn-sm btn-primary me-2">View Chat</a>
                                            <a href="ptpMessage.php?delete_conversation=1&sender_id=<?= $conv['sender_id'] ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete this conversation?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$stmt->close();
$count_stmt->close();
$conn->close();
include '../utils/footer.php';
?>