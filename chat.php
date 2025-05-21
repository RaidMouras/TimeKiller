<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'utils/db_config.php';

$conn = get_db_connection();

if (isset($_SESSION['user_id'])) {
    

$current_user_id = $_SESSION['user_id'];
$receiver_id = isset($_GET['receiver_id']) ? intval($_GET['receiver_id']) : 0;

// Handle message sending
if (isset($_POST['message_content']) && $receiver_id > 0) {
    $message = trim($_POST['message_content']);
    
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO Messages (SenderID, ReceiverID, Content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $current_user_id, $receiver_id, $message);
        $stmt->execute();
        $stmt->close();
        
        header("Location: chat.php?receiver_id=".$receiver_id);
        exit();
    }
}

// Handle negotiation
if (isset($_POST['negotiation_price']) && $receiver_id > 0) {
    $price = floatval($_POST['negotiation_price']);
    $message = trim($_POST['negotiation_message'] ?? '');
    
    if ($price > 0) {
        $negotiation_msg = "Negotiation offer: €".number_format($price, 2);
        if (!empty($message)) {
            $negotiation_msg .= " - ".$message;
        }
        
        $stmt = $conn->prepare("INSERT INTO Messages (SenderID, ReceiverID, Content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $current_user_id, $receiver_id, $negotiation_msg);
        $stmt->execute();
        $stmt->close();
        
        header("Location: chat.php?receiver_id=".$receiver_id);
        exit();
    }
}

$receiver_info = [];
if ($receiver_id > 0) {
    $stmt = $conn->prepare("SELECT u.UserID, 
                           IF(u.User_Type='Business', b.Business_Name, c.Username) as DisplayName,
                           IF(u.User_Type='Business', b.Profile_Picture, c.Profile_Picture) as Profile_Picture
                           FROM Users u
                           LEFT JOIN Business b ON u.UserID = b.UserID AND u.User_Type='Business'
                           LEFT JOIN Customers c ON u.UserID = c.UserID AND u.User_Type='Customer'
                           WHERE u.UserID = ?");
    $stmt->bind_param("i", $receiver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $receiver_info = $result->fetch_assoc();
    $stmt->close();
    
    if (!$receiver_info) {
        die("Invalid user selected");
    }
}

// Get message history
$messages = [];
if ($receiver_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM Messages 
                          WHERE (SenderID = ? AND ReceiverID = ?)
                          OR (SenderID = ? AND ReceiverID = ?)
                          ORDER BY Timesent ASC");
    $stmt->bind_param("iiii", $current_user_id, $receiver_id, $receiver_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
} else {
    die("Invalid request. Please log in to continue.");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?php echo htmlspecialchars($receiver_info['DisplayName'] ?? 'Business'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/pages/chat.css">
   
</head>

<body>
    <div class="container mt-4">
        <h2 class="text-center">Chat with <?php echo htmlspecialchars($receiver_info['DisplayName'] ?? 'Business'); ?></h2>
        
        <!-- Chat Messages -->
        <div class="chat-box" id="chatMessages">
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $row): ?>
                    <div class="<?php echo ($row['SenderID'] == $current_user_id) ? 'user-message' : 'business-message'; ?>">
                        <?php echo htmlspecialchars($row['Content']); ?>
                        <div class="message-time">
                            <?php echo date("M j, g:i a", strtotime($row['Timesent'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php elseif ($receiver_id > 0): ?>
                <div class="text-center text-muted mt-5">No messages yet. Start the conversation!</div>
            <?php else: ?>
                <div class="text-center text-muted mt-5">Select a user to start chatting</div>
            <?php endif; ?>
        </div>
        
        
        <div class="action-buttons d-flex justify-content-center gap-2">
            <button type="button" id="showNegotiationForm" class="btn btn-warning">Send Negotiation</button>
        </div>
        
        <!-- Message Form -->
        <form method="POST" id="messageForm">
            <div class="input-group mb-3">
                <input type="text" name="message_content" class="form-control chat-input" placeholder="Type your message..." required>
                <button type="submit" class="btn btn-success">Send</button>
            </div>
        </form>
        
        <!-- Negotiation Form -->
        <form method="POST" id="negotiationForm" style="display: none;">
            <div class="input-group mb-2">
                <input type="number" name="negotiation_price" class="form-control chat-input" placeholder="Price" step="0.01" min="0" required>
            </div>
            <div class="input-group mb-3">
                <input type="text" name="negotiation_message" class="form-control chat-input" placeholder="Negotiation message (optional)">
                <button type="submit" class="btn btn-success">Send Offer</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle between message and negotiation forms
        document.getElementById('showMessageForm').addEventListener('click', function() {
            document.getElementById('negotiationForm').style.display = 'none';
            document.getElementById('messageForm').style.display = 'block';
            document.querySelector('#messageForm input').focus();
        });
        
        document.getElementById('showNegotiationForm').addEventListener('click', function() {
            document.getElementById('messageForm').style.display = 'none';
            document.getElementById('negotiationForm').style.display = 'block';
            document.querySelector('#negotiationForm input').focus();
        });
        
        // Auto-scroll to bottom of chat
        window.onload = function() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            
            // Show message form by default if receiver is selected
            <?php if ($receiver_id > 0): ?>
            document.getElementById('messageForm').style.display = 'block';
            <?php endif; ?>
        };
    </script>
</body>
</html>