<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'utils/db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Please log in to continue.");
}

$conn = get_db_connection();
$current_user_id = $_SESSION['user_id'];
$receiver_id = isset($_GET['receiver_id']) ? intval($_GET['receiver_id']) : 0;

// Validate receiver_id
if ($receiver_id === 0) {
    die("Invalid business specified.");
}

// Handle message sending via GET
if (isset($_GET['message_content'])) {
    $message = trim($_GET['message_content']);
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO Messages (SenderID, ReceiverID, Content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $current_user_id, $receiver_id, $message);
        $stmt->execute();
        // Redirect to prevent form resubmission
        header("Location: test2.php?receiver_id=" . $receiver_id);
        exit();
    }
}

// Handle negotiation via GET
if (isset($_GET['negotiation_price'])) {
    $price = floatval($_GET['negotiation_price']);
    $message = trim($_GET['negotiation_message'] ?? '');
    $negotiation_msg = "CUSTOMER OFFER: €" . number_format($price, 2);
    if (!empty($message)) $negotiation_msg .= " - " . $message;

    $stmt = $conn->prepare("INSERT INTO Messages (SenderID, ReceiverID, Content) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $current_user_id, $receiver_id, $negotiation_msg);
    $stmt->execute();
    // Redirect to prevent form resubmission
    header("Location: test2.php?receiver_id=" . $receiver_id);
    exit();
}

// Get business info
$stmt = $conn->prepare("SELECT u.UserID, b.Business_Name 
                      FROM Users u JOIN Business b ON u.UserID = b.UserID 
                      WHERE u.UserID = ?");
$stmt->bind_param("i", $receiver_id);
$stmt->execute();
$receiver_info = $stmt->get_result()->fetch_assoc();

if (!$receiver_info) {
    die("Business not found.");
}

// Get conversation history
$stmt = $conn->prepare("SELECT * FROM Messages 
                      WHERE (SenderID=? AND ReceiverID=?) 
                      OR (SenderID=? AND ReceiverID=?) 
                      ORDER BY Timesent ASC");
$stmt->bind_param("iiii", $current_user_id, $receiver_id, $receiver_id, $current_user_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?= htmlspecialchars($receiver_info['Business_Name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .chat-box {
            height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .message {
            padding: 8px 12px;
            margin-bottom: 10px;
            border-radius: 5px;
            max-width: 70%;
            word-wrap: break-word;
        }
        .message.sent {
            background-color: #007bff;
            color: white;
            margin-left: auto;
        }
        .message.received {
            background-color: #e9ecef;
            margin-right: auto;
        }
        .message-time {
            font-size: 0.8em;
            opacity: 0.7;
            margin-top: 5px;
        }
        .sent .message-time {
            color: #e0e0e0;
        }
        .received .message-time {
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2 class="mb-4">Chat with <?= htmlspecialchars($receiver_info['Business_Name']) ?></h2>
        
        <!-- Messages Display -->
        <div class="chat-box" id="chatMessages">
            <?php foreach ($messages as $msg): ?>
                <div class="message <?= $msg['SenderID'] == $current_user_id ? 'sent' : 'received' ?>">
                    <?= htmlspecialchars($msg['Content']) ?>
                    <div class="message-time">
                        <?= date("M j, g:i a", strtotime($msg['Timesent'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Negotiation Button -->
        <div class="action-buttons mt-3">
            <button class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#negotiationModal">
                Send Negotiation
            </button>
        </div>
        
        <!-- Regular Message Form -->
        <form method="GET" class="mt-3">
            <input type="hidden" name="receiver_id" value="<?= $receiver_id ?>">
            <div class="input-group">
                <input type="text" name="message_content" class="form-control" placeholder="Type message..." required>
                <button type="submit" class="btn btn-primary">Send</button>
            </div>
        </form>
    </div>

    <!-- Negotiation Modal -->
    <div class="modal fade" id="negotiationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Your Offer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="GET">
                    <input type="hidden" name="receiver_id" value="<?= $receiver_id ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Price (€)</label>
                            <input type="number" name="negotiation_price" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label>Message (Optional)</label>
                            <textarea name="negotiation_message" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Send Offer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll to bottom of chat
        window.onload = function() {
            const chatBox = document.getElementById('chatMessages');
            chatBox.scrollTop = chatBox.scrollHeight;
        };
        
        // Auto-focus message input
        document.querySelector('input[name="message_content"]').focus();
    </script>
</body>
</html>