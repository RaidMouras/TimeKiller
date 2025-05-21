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
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;

// Verify business account
$stmt = $conn->prepare("SELECT User_Type FROM Users WHERE UserID = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$user_type = $stmt->get_result()->fetch_assoc()['User_Type'];
$stmt->close();

if ($user_type !== 'Business') {
    die("Business account required");
}

// Handle negotiation actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $message_id = intval($_GET['message_id']);
    
    $stmt = $conn->prepare("UPDATE Messages SET Negotiation_Status = ? 
                          WHERE MessageID = ? AND ReceiverID = ?");
    $status = ($action === 'accept') ? 'Accepted' : 'Declined';
    $stmt->bind_param("sii", $status, $message_id, $current_user_id);
    $stmt->execute();
    $stmt->close();
    
    // If accepted, verify the customer
    if ($action === 'accept') {
        // Get customer ID and negotiation price from the message
        $stmt = $conn->prepare("SELECT SenderID, Negotiation_Price, ListingID FROM Messages WHERE MessageID = ?");
        $stmt->bind_param("i", $message_id);
        $stmt->execute();
        $messageData = $stmt->get_result()->fetch_assoc();
        $customer_id = $messageData['SenderID'];
        $price = $messageData['Negotiation_Price'];
        $listing_id_from_msg = $messageData['ListingID'];
        $stmt->close();
        
        // Create a negotiation record in the Negotiations table
        $stmt = $conn->prepare("INSERT INTO Negotiations (ListingID, UserID, Price, Description, Current_Status, Time_Of_Event) 
                              VALUES (?, ?, ?, ?, 'Accepted', NOW())");
        $description = "Accepted offer from message ID: " . $message_id;
        $stmt->bind_param("iids", $listing_id_from_msg, $customer_id, $price, $description);
        $stmt->execute();
        $negotiation_id = $conn->insert_id;
        $stmt->close();
        
        // Record the purchase in Purchase_History_Negotiation
        $stmt = $conn->prepare("INSERT INTO Purchase_History_Negotiation (UserID, NegotiationID) 
                              VALUES (?, ?)");
        $stmt->bind_param("ii", $customer_id, $negotiation_id);
        $stmt->execute();
        $stmt->close();
        
        // Verify the customer
        $stmt = $conn->prepare("UPDATE Customers SET Verified = 1 WHERE UserID = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success'] = "Offer accepted and customer verified!";
    } else if ($action === 'decline') {
        // Get the message and associated negotiation information
        $stmt = $conn->prepare("SELECT m.SenderID, m.Negotiation_Price, m.ListingID, n.NegotiationID 
                              FROM Messages m
                              LEFT JOIN Negotiations n ON n.ListingID = m.ListingID 
                                                     AND n.UserID = m.SenderID 
                                                     AND n.Price = m.Negotiation_Price
                                                     AND n.Current_Status = 'Pending'
                              WHERE m.MessageID = ?");
        $stmt->bind_param("i", $message_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        if ($data && isset($data['NegotiationID'])) {
            // Update existing negotiation record to Rejected
            $stmt = $conn->prepare("UPDATE Negotiations SET Current_Status = 'Rejected' WHERE NegotiationID = ?");
            $stmt->bind_param("i", $data['NegotiationID']);
            $stmt->execute();
            $stmt->close();
        } else {
            // Create a new rejected negotiation record if none exists
            $stmt = $conn->prepare("INSERT INTO Negotiations (ListingID, UserID, Price, Description, Current_Status, Time_Of_Event) 
                                 VALUES (?, ?, ?, ?, 'Rejected', NOW())");
            $description = "Rejected offer from message ID: " . $message_id;
            $stmt->bind_param("iids", $data['ListingID'], $data['SenderID'], $data['Negotiation_Price'], $description);
            $stmt->execute();
            $stmt->close();
        }
        
        $_SESSION['success'] = "Offer declined.";
    }
    
    header("Location: businessChat.php?customer_id=$customer_id&listing_id=$listing_id");
    exit();
}

// Handle message sending via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_content'])) {
    if ($customer_id === 0 || $listing_id === 0) {
        die("Customer or listing not specified.");
    }
    
    $message = trim($_POST['message_content']);
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO Messages (SenderID, ReceiverID, ListingID, Content) 
                              VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $current_user_id, $customer_id, $listing_id, $message);
        $stmt->execute();
        $stmt->close();
        header("Location: businessChat.php?customer_id=$customer_id&listing_id=$listing_id");
        exit();
    }
}

// Handle negotiation via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['negotiation_price'])) {
    if ($customer_id === 0 || $listing_id === 0) {
        die("Customer or listing not specified.");
    }
    
    $price = floatval($_POST['negotiation_price']);
    $message = trim($_POST['negotiation_message'] ?? '');
    $negotiation_msg = "BUSINESS OFFER: €" . number_format($price, 2);
    if (!empty($message)) $negotiation_msg .= " - " . $message;

    // Insert negotiation record with Pending status
    $stmt = $conn->prepare("INSERT INTO Negotiations (ListingID, UserID, Price, Description, Current_Status) 
                          VALUES (?, ?, ?, ?, 'Pending')");
    $description = !empty($message) ? $message : "Offer from business";
    $stmt->bind_param("iids", $listing_id, $current_user_id, $price, $description);
    $stmt->execute();
    $negotiation_id = $conn->insert_id;
    $stmt->close();

    // Insert into Messages
    $stmt = $conn->prepare("INSERT INTO Messages (SenderID, ReceiverID, ListingID, Content, Negotiation_Status, Negotiation_Price) 
                          VALUES (?, ?, ?, ?, 'Pending', ?)");
    $stmt->bind_param("iiisd", $current_user_id, $customer_id, $listing_id, $negotiation_msg, $price);
    $stmt->execute();
    $stmt->close();
    header("Location: businessChat.php?customer_id=$customer_id&listing_id=$listing_id");
    exit();
}

// Get customer and listing info
$customer_info = [];
$listing_info = [];
if ($customer_id > 0 && $listing_id > 0) {
    // Get customer info
    $stmt = $conn->prepare("SELECT u.UserID, c.Username, c.Profile_Picture, c.Verified 
                          FROM Users u JOIN Customers c ON u.UserID = c.UserID 
                          WHERE u.UserID = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $customer_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$customer_info) {
        die("Customer not found.");
    }
    
    // Get listing info with negotiable status
    $stmt = $conn->prepare("SELECT Listing_Name, Short_Desc, Negotiable FROM Listing WHERE ListingID = ? AND UserID = ?");
    $stmt->bind_param("ii", $listing_id, $current_user_id);
    $stmt->execute();
    $listing_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$listing_info) {
        die("Listing not found or doesn't belong to you.");
    }
    
    // Verify this customer has messaged the business about this listing before
    $stmt = $conn->prepare("SELECT 1 FROM Messages 
                          WHERE SenderID=? AND ReceiverID=? AND ListingID=?
                          LIMIT 1");
    $stmt->bind_param("iii", $customer_id, $current_user_id, $listing_id);
    $stmt->execute();
    $has_contact = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    
    if (!$has_contact) {
        die("No previous conversation found with this customer about this listing.");
    }
}

// Get conversation history for this specific listing
$messages = [];
if ($customer_id > 0 && $listing_id > 0) {
    $stmt = $conn->prepare("SELECT m.*, 
                          CASE WHEN m.SenderID = ? THEN 'sent' ELSE 'received' END as message_type,
                          IFNULL(m.Negotiation_Status, '') as Negotiation_Status
                          FROM Messages m
                          WHERE ((m.SenderID=? AND m.ReceiverID=?) OR (m.SenderID=? AND m.ReceiverID=?))
                          AND m.ListingID=?
                          ORDER BY m.Timesent ASC");
    $stmt->bind_param("iiiiii", $current_user_id, $customer_id, $current_user_id, $current_user_id, $customer_id, $listing_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Set page title
$page_title = "Chat with " . htmlspecialchars($customer_info['Username'] ?? 'Customer') . " about " . htmlspecialchars($listing_info['Listing_Name'] ?? 'Listing');

// Add custom styles for the chat page
$page_specific_css = '
<style>
    body {
        background-color: #f8f9fa;
    }
    .chat-container {
        max-width: 800px;
        margin: 30px auto;
        border: 1px solid #ddd;
        border-radius: 8px;
        overflow: hidden;
    }
    .chat-header {
        background-color: #007bff;
        color: white;
        padding: 15px;
        display: flex;
        align-items: center;
    }
    .chat-header img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 10px;
        object-fit: cover;
    }
    .chat-messages {
        height: 400px;
        overflow-y: auto;
        padding: 15px;
        background-color: #f9f9f9;
    }
    .message {
        margin-bottom: 15px;
        display: flex;
    }
    .message.sent {
        justify-content: flex-end;
    }
    .message.received {
        justify-content: flex-start;
    }
    .message-content {
        max-width: 70%;
        padding: 10px 15px;
        border-radius: 18px;
        position: relative;
    }
    .message.sent .message-content {
        background-color: #007bff;
        color: white;
        border-top-right-radius: 0;
    }
    .message.received .message-content {
        background-color: #e9ecef;
        color: #333;
        border-top-left-radius: 0;
    }
    .message-time {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 5px;
        text-align: right;
    }
    .chat-input {
        display: flex;
        padding: 15px;
        background-color: white;
        border-top: 1px solid #ddd;
    }
    .chat-input textarea {
        flex-grow: 1;
        border-radius: 20px;
        padding: 10px 15px;
        resize: none;
        border: 1px solid #ddd;
    }
    .chat-input button {
        margin-left: 10px;
        border-radius: 20px;
        padding: 10px 20px;
    }
    .listing-reference {
        background-color: #e9f5ff;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
        border-left: 3px solid #007bff;
    }
    .negotiation-actions {
        margin-top: 8px;
    }
    .text-decoration-line-through {
        text-decoration: line-through;
        opacity: 0.7;
    }
    .badge {
        font-size: 0.65rem;
        vertical-align: middle;
    }
    .verified-badge {
        font-size: 0.8rem;
        margin-left: 5px;
    }
</style>';

include 'utils/header.php';
?>

<!-- Success/Error Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<div class="container mt-4">
    <div class="chat-container">
        <div class="chat-header">
            <img src="api/icon_handler.php?action=get&user_id=<?= $customer_id ?>&nc=<?= time() ?>" 
                 alt="<?= htmlspecialchars($customer_info['Username']) ?>">
            <div>
                <h5 class="mb-0">Chat with <?= htmlspecialchars($customer_info['Username']) ?></h5>
                <small>
                    <?= $customer_info['Verified'] ? '<span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>' : '<span class="verified-badge text-warning"><i class="fas fa-exclamation-circle"></i> Not Verified</span>' ?>
                </small>
            </div>
            <a href="message.php" class="btn btn-sm btn-light ms-auto">
                <i class="fas fa-arrow-left"></i> Back to Messages
            </a>
        </div>

        <div class="chat-messages">
            <div class="listing-reference">
                <small>Regarding: <strong><?= htmlspecialchars($listing_info['Listing_Name']) ?></strong></small>
                <p class="mb-0"><small><?= htmlspecialchars($listing_info['Short_Desc']) ?></small></p>
            </div>

            <?php foreach ($messages as $msg): ?>
                <div class="message <?= $msg['message_type'] ?>">
                    <div class="message-content <?= $msg['Negotiation_Status'] === 'Declined' ? 'text-decoration-line-through' : '' ?>">
                        <p class="mb-1"><?= nl2br(htmlspecialchars($msg['Content'])) ?></p>
                        <div class="message-time">
                            <?= date('M j, g:i a', strtotime($msg['Timesent'])) ?>
                            <?php if ($msg['Negotiation_Status'] === 'Accepted'): ?>
                                <span class="badge bg-success ms-2">Accepted</span>
                            <?php elseif ($msg['Negotiation_Status'] === 'Declined'): ?>
                                <span class="badge bg-danger ms-2">Declined</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($msg['Negotiation_Status'] === 'Pending' && $msg['message_type'] === 'received'): ?>
                            <div class="negotiation-actions">
                                <a href="businessChat.php?customer_id=<?= $customer_id ?>&listing_id=<?= $listing_id ?>&action=accept&message_id=<?= $msg['MessageID'] ?>"
                                    class="btn btn-sm btn-success me-2">Accept</a>
                                <a href="businessChat.php?customer_id=<?= $customer_id ?>&listing_id=<?= $listing_id ?>&action=decline&message_id=<?= $msg['MessageID'] ?>"
                                    class="btn btn-sm btn-danger">Decline</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="chat-input">
            <form method="POST" class="d-flex w-100">
                <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                <input type="hidden" name="listing_id" value="<?= $listing_id ?>">
                <textarea name="message_content" placeholder="Type your message here..." required></textarea>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send
                </button>
            </form>

            <?php if ($listing_info['Negotiable']): ?>
                <div class="mt-2 text-center">
                    <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#negotiationModal">
                        <i class="fas fa-handshake"></i> Make an Offer
                    </button>
                </div>
            <?php else: ?>
                <div class="mt-2 text-center text-muted">
                    <i class="fas fa-info-circle"></i> This listing is not negotiable
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Negotiation Modal -->
<div class="modal fade" id="negotiationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Make an Offer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="customer_id" value="<?= $customer_id ?>">
                <input type="hidden" name="listing_id" value="<?= $listing_id ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Your Offer Price (€)</label>
                        <input type="number" name="negotiation_price" class="form-control"
                            step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message (Optional)</label>
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

<script>
    // Auto-scroll to bottom of chat
    window.onload = function() {
        const chatMessages = document.querySelector('.chat-messages');
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        // Focus on textarea
        document.querySelector('textarea[name="message_content"]').focus();
    };

    // Auto-refresh chat every 10 seconds
    setInterval(function() {
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newMessages = doc.querySelector('.chat-messages');
                if (newMessages) {
                    document.querySelector('.chat-messages').innerHTML = newMessages.innerHTML;
                    document.querySelector('.chat-messages').scrollTop = document.querySelector('.chat-messages').scrollHeight;
                }
            })
            .catch(error => console.error('Error refreshing chat:', error));
    }, 10000);
</script>

<?php
include 'utils/footer.php';
?>