<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$page_title = 'Messages';

// Get database connection
require_once 'utils/db_config.php';
$conn = get_db_connection();

// Initialize accepted chats array in session if not exists
if (!isset($_SESSION['accepted_chats'])) {
    $_SESSION['accepted_chats'] = [];
}

// Pagination settings
$messages_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $messages_per_page;

// Get user's messages grouped by sender and listing
$query = "SELECT 
          m.SenderID as sender_id,
          m.ReceiverID as receiver_id,
          m.ListingID,
          IF(sender.User_Type = 'Business', b.Business_Name, c.Username) as sender_name,
          IF(sender.User_Type = 'Business', b.Profile_Picture, c.Profile_Picture) as sender_pic,
          sender.User_Type as sender_type,
          receiver.User_Type as receiver_type,
          l.Listing_Name,
          l.Short_Desc,
          l.UserID as listing_owner_id,
          MAX(m.Timesent) as last_message_time,
          COUNT(m.MessageID) as message_count,
          MAX(CASE WHEN m.Negotiation_Status = 'Accepted' THEN 1 ELSE 0 END) as is_accepted
          FROM Messages m
          JOIN Users sender ON m.SenderID = sender.UserID
          JOIN Users receiver ON m.ReceiverID = receiver.UserID
          LEFT JOIN Business b ON (sender.User_Type = 'Business' AND m.SenderID = b.UserID)
          LEFT JOIN Customers c ON (sender.User_Type = 'Customer' AND m.SenderID = c.UserID)
          JOIN Listing l ON m.ListingID = l.ListingID
          WHERE m.ReceiverID = ? OR m.SenderID = ?
          GROUP BY m.SenderID, m.ListingID
          ORDER BY last_message_time DESC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $_SESSION['user_id'], $_SESSION['user_id'], $messages_per_page, $offset);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT CONCAT(SenderID, '-', ListingID)) as total 
               FROM Messages 
               WHERE ReceiverID = ? OR SenderID = ?";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$count_stmt->execute();
$total_conversations = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_conversations / $messages_per_page);

// Handle accept action
if (isset($_GET['accept_request'])) {
    $sender_id = intval($_GET['sender_id']);
    $listing_id = intval($_GET['listing_id']);
    
    // Update the database to mark as accepted
    $update_query = "UPDATE Messages SET Negotiation_Status = 'Accepted' 
                     WHERE SenderID = ? AND ListingID = ? AND ReceiverID = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("iii", $sender_id, $listing_id, $_SESSION['user_id']);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Store accepted status in session
    $_SESSION['accepted_chats'][$sender_id.'_'.$listing_id] = true;
    
    $_SESSION['success'] = "Chat request accepted! You can now chat with this user.";
    header("Location: message.php");
    exit();
}

include 'utils/header.php';
?>

<!-- Main Content -->
<div class="container mb-5">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="sidebar">
                <ul class="sidebar-nav">
                    <li><a href="profile.php">My Profile</a></li>
                    <?php if($_SESSION['user_type'] != 'Business'): ?>
                    <li><a href="history.php">History</a></li>
                    <?php endif; ?>
                    <li><a href="message.php" class="active">Messages</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Messages Content -->
        <div class="col-md-9">
            <div class="main-content">
                <h2>Messages</h2>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (empty($conversations)): ?>
                    <div class="alert alert-info mt-3">
                        No messages found.
                    </div>
                <?php else: ?>
                    <table class="table messages-table">
                        <thead>
                            <tr>
                                <th width="30%">Sender</th>
                                <th>Listing</th>
                                <th width="15%">Last Message</th>
                                <th width="10%">Messages</th>
                                <th width="20%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($conversations as $conv): 
                                $chat_key = $conv['sender_id'].'_'.$conv['ListingID'];
                                $is_accepted = $conv['is_accepted'] || isset($_SESSION['accepted_chats'][$chat_key]);
                                
                                // Determine if current user is sender or receiver
                                $is_sender = ($conv['sender_id'] == $_SESSION['user_id']);
                                $other_user_id = $is_sender ? $conv['receiver_id'] : $conv['sender_id'];
                                $other_user_type = $is_sender ? $conv['receiver_type'] : $conv['sender_type'];
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="api/icon_handler.php?action=get&user_id=<?= $conv['sender_id'] ?>" 
                                                 class="rounded-circle me-2" 
                                                 width="40" 
                                                 height="40" 
                                                 alt="<?= htmlspecialchars($conv['sender_name']) ?>">
                                            <span><?= htmlspecialchars($conv['sender_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="listing-info">
                                            <strong><?= htmlspecialchars($conv['Listing_Name']) ?></strong>
                                            <div class="text-muted small"><?= htmlspecialchars($conv['Short_Desc']) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?= date('M j, g:i a', strtotime($conv['last_message_time'])) ?>
                                    </td>
                                    <td>
                                        <?= $conv['message_count'] ?>
                                    </td>
                                    <td>
                                        <?php if (!$is_accepted && !$is_sender): ?>
                                            <a href="message.php?accept_request=1&sender_id=<?= $conv['sender_id'] ?>&listing_id=<?= $conv['ListingID'] ?>" 
                                               class="btn btn-sm btn-success me-2">Accept</a>
                                        <?php endif; ?>
                                        
                                        <?php
                                        // Determine which chat page to use based on user types
                                        if ($_SESSION['user_type'] == 'Business') {
                                            // Business user chatting with someone
                                            if ($other_user_type == 'Business') {
                                                // Business-to-Business chat
                                                $chat_page = 'businessChat.php';
                                                $param_name = 'business_id';
                                            } else {
                                                // Business-to-Customer chat
                                                $chat_page = 'businessChat.php';
                                                $param_name = 'customer_id';
                                            }
                                        } else {
                                            // Customer user chatting with someone
                                            if ($other_user_type == 'Business') {
                                                // Customer-to-Business chat
                                                $chat_page = 'test2.php';
                                                $param_name = 'business_id';
                                            } else {
                                                // Customer-to-Customer chat
                                                $chat_page = 'ptpChat.php';
                                                $param_name = 'customer_id';
                                            }
                                        }
                                        ?>
                                        <a href="<?= $chat_page ?>?<?= $param_name ?>=<?= $other_user_id ?>&listing_id=<?= $conv['ListingID'] ?>" 
                                           class="btn btn-sm btn-primary <?= (!$is_accepted && !$is_sender) ? 'disabled' : '' ?>">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
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
include 'utils/footer.php';
?>