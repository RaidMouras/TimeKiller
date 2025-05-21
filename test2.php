<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'utils/db_config.php';



if (!isset($_SESSION['user_id'])) {
    die("Please log in to continue.");
}

$conn = get_db_connection();
$current_user_id = $_SESSION['user_id'];
$business_id = isset($_GET['business_id']) ? intval($_GET['business_id']) : 0;
$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;

// Handle negotiation actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $message_id = intval($_GET['message_id']);

    // Verify the message exists and belongs to this conversation
    $stmt = $conn->prepare("SELECT 1 FROM Messages 
                          WHERE MessageID = ? 
                          AND ((SenderID = ? AND ReceiverID = ?) OR (SenderID = ? AND ReceiverID = ?))
                          AND ListingID = ?");
    $stmt->bind_param("iiiiii", $message_id, $business_id, $current_user_id, $current_user_id, $business_id, $listing_id);
    $stmt->execute();
    if (!$stmt->get_result()->num_rows) {
        die("Invalid message specified.");
    }
    $stmt->close();

    // Update negotiation status
    $stmt = $conn->prepare("UPDATE Messages SET Negotiation_Status = ? 
                          WHERE MessageID = ? AND ReceiverID = ?");
    $status = ($action === 'accept') ? 'Accepted' : 'Declined';
    $stmt->bind_param("sii", $status, $message_id, $current_user_id);
    $stmt->execute();
    $stmt->close();

    if ($action === 'accept') {
        // Get the accepted offer details
        $stmt = $conn->prepare("SELECT Negotiation_Price, ListingID FROM Messages WHERE MessageID = ?");
        $stmt->bind_param("i", $message_id);
        $stmt->execute();
        $offer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Create a negotiation record in the Negotiations table
        $stmt = $conn->prepare("INSERT INTO Negotiations (ListingID, UserID, Price, Description, Current_Status, Time_Of_Event) 
                              VALUES (?, ?, ?, ?, 'Accepted', NOW())");
        $description = "Accepted offer from message ID: " . $message_id;
        $stmt->bind_param("iids", $offer['ListingID'], $current_user_id, $offer['Negotiation_Price'], $description);
        $stmt->execute();
        $negotiation_id = $conn->insert_id;
        $stmt->close();
        
        // Record the purchase in Purchase_History_Negotiation
        $stmt = $conn->prepare("INSERT INTO Purchase_History_Negotiation (UserID, NegotiationID) 
                              VALUES (?, ?)");
        $stmt->bind_param("ii", $current_user_id, $negotiation_id);
        $stmt->execute();
        $stmt->close();
        
        // Verify the user
        $stmt = $conn->prepare("UPDATE Customers SET Verified = 1 WHERE UserID = ?");
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success'] = "Thank you for your purchase! You are now a verified user.";
    } else if ($action === 'decline') {
        // Get the pending negotiation details from message
        $stmt = $conn->prepare("SELECT m.Negotiation_Price, m.ListingID, n.NegotiationID 
                               FROM Messages m
                               LEFT JOIN Negotiations n ON n.ListingID = m.ListingID 
                                                       AND n.UserID = m.SenderID 
                                                       AND n.Price = m.Negotiation_Price
                                                       AND n.Current_Status = 'Pending'
                               WHERE m.MessageID = ?");
        $stmt->bind_param("i", $message_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $negotiation = $result->fetch_assoc();
        $stmt->close();
        
        if ($negotiation && $negotiation['NegotiationID']) {
            // Update the negotiation status to Rejected
            $stmt = $conn->prepare("UPDATE Negotiations SET Current_Status = 'Rejected' WHERE NegotiationID = ?");
            $stmt->bind_param("i", $negotiation['NegotiationID']);
            $stmt->execute();
            $stmt->close();
        } else {
            // If no matching negotiation found, create a new rejected entry
            $stmt = $conn->prepare("SELECT Negotiation_Price, ListingID, SenderID FROM Messages WHERE MessageID = ?");
            $stmt->bind_param("i", $message_id);
            $stmt->execute();
            $message_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($message_data) {
                $stmt = $conn->prepare("INSERT INTO Negotiations (ListingID, UserID, Price, Description, Current_Status, Time_Of_Event) 
                                      VALUES (?, ?, ?, ?, 'Rejected', NOW())");
                $description = "Rejected offer from message ID: " . $message_id;
                $stmt->bind_param("iids", $message_data['ListingID'], $message_data['SenderID'], 
                                 $message_data['Negotiation_Price'], $description);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        $_SESSION['success'] = "Offer declined.";
    }
    header("Location: test2.php?business_id=$business_id&listing_id=$listing_id");
    exit();
}

// Validate business_id and listing_id
if ($business_id === 0 || $listing_id === 0) {
    die("Invalid business or listing specified.");
}

// Verify the listing belongs to the business
$stmt = $conn->prepare("SELECT 1 FROM Listing WHERE ListingID = ? AND UserID = ?");
$stmt->bind_param("ii", $listing_id, $business_id);
$stmt->execute();
if (!$stmt->get_result()->num_rows) {
    die("This listing doesn't belong to the specified business.");
}
$stmt->close();

// Handle message sending via GET
if (isset($_GET['message_content'])) {
    $message = trim($_GET['message_content']);
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO Messages (SenderID, ReceiverID, ListingID, Content) 
                              VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $current_user_id, $business_id, $listing_id, $message);
        $stmt->execute();
        $stmt->close();
        header("Location: test2.php?business_id=$business_id&listing_id=$listing_id");
        exit();
    }
}

// Handle negotiation via GET
if (isset($_GET['negotiation_price'])) {
    $price = floatval($_GET['negotiation_price']);
    $message = trim($_GET['negotiation_message'] ?? '');
    $negotiation_msg = "OFFER: €" . number_format($price, 2);
    if (!empty($message)) $negotiation_msg .= " - " . $message;

    // Insert negotiation record with Pending status
    $stmt = $conn->prepare("INSERT INTO Negotiations (ListingID, UserID, Price, Description, Current_Status) 
                          VALUES (?, ?, ?, ?, 'Pending')");
    $description = !empty($message) ? $message : "Offer from customer";
    $stmt->bind_param("iids", $listing_id, $current_user_id, $price, $description);
    $stmt->execute();
    $negotiation_id = $conn->insert_id;
    $stmt->close();

    // Insert message with reference to negotiation
    $stmt = $conn->prepare("INSERT INTO Messages (SenderID, ReceiverID, ListingID, Content, Negotiation_Status, Negotiation_Price) 
                          VALUES (?, ?, ?, ?, 'Pending', ?)");
    $stmt->bind_param("iiisd", $current_user_id, $business_id, $listing_id, $negotiation_msg, $price);
    $stmt->execute();
    $stmt->close();
    header("Location: test2.php?business_id=$business_id&listing_id=$listing_id");
    exit();
}

// Get business and listing info
$stmt = $conn->prepare("SELECT b.Business_Name, b.Profile_Picture as Business_Profile, 
                      l.Listing_Name, l.Short_Desc, l.Negotiable 
                      FROM Business b 
                      JOIN Listing l ON b.UserID = l.UserID
                      WHERE b.UserID = ? AND l.ListingID = ?");
$stmt->bind_param("ii", $business_id, $listing_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) {
    die("Business or listing not found.");
}

// Get conversation history for this specific listing
$stmt = $conn->prepare("SELECT m.*, 
                      CASE WHEN m.SenderID = ? THEN 'sent' ELSE 'received' END as message_type,
                      IFNULL(m.Negotiation_Status, '') as Negotiation_Status 
                      FROM Messages m
                      WHERE ((m.SenderID=? AND m.ReceiverID=?) OR (m.SenderID=? AND m.ReceiverID=?))
                      AND m.ListingID=?
                      ORDER BY m.Timesent ASC");
$stmt->bind_param("iiiiii", $current_user_id, $current_user_id, $business_id, $business_id, $current_user_id, $listing_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Set the page title before including header
$page_title = "Chat with " . htmlspecialchars($result['Business_Name']) . " about " . htmlspecialchars($result['Listing_Name']);

// Add custom styles for the chat page
$page_specific_css = '
<style>
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
            <img src="api/icon_handler.php?action=get&user_id=<?= $business_id ?>&nc=<?= time() ?>"
                alt="<?= htmlspecialchars($result['Business_Name']) ?>">
            <h5 class="mb-0">Chat with <?= htmlspecialchars($result['Business_Name']) ?></h5>
            <button onclick="history.back()" class="btn btn-sm btn-light ms-auto">
                <i class="fas fa-arrow-left"></i> Back
            </button>
        </div>

        <div class="chat-messages">
            <div class="listing-reference">
                <small>Regarding: <strong><?= htmlspecialchars($result['Listing_Name']) ?></strong></small>
                <p class="mb-0"><small><?= htmlspecialchars($result['Short_Desc']) ?></small></p>
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
                                <a href="test2.php?business_id=<?= $business_id ?>&listing_id=<?= $listing_id ?>&action=accept&message_id=<?= $msg['MessageID'] ?>"
                                    class="btn btn-sm btn-success me-2">Accept</a>
                                <a href="test2.php?business_id=<?= $business_id ?>&listing_id=<?= $listing_id ?>&action=decline&message_id=<?= $msg['MessageID'] ?>"
                                    class="btn btn-sm btn-danger">Decline</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="chat-input">
            <form method="GET" class="d-flex w-100">
                <input type="hidden" name="business_id" value="<?= $business_id ?>">
                <input type="hidden" name="listing_id" value="<?= $listing_id ?>">
                <textarea name="message_content" placeholder="Type your message here..." required></textarea>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send
                </button>
            </form>

            <?php if ($result['Negotiable']): ?>
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
            <form method="GET">
                <input type="hidden" name="business_id" value="<?= $business_id ?>">
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