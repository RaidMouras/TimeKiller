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

// Include database configuration
require_once 'utils/db_config.php';

// Handle negotiation actions
if (isset($_GET['action']) && isset($_GET['message_id'])) {
    $conn = get_db_connection();
    $action = $_GET['action'];
    $message_id = intval($_GET['message_id']);
    $current_user_id = $_SESSION['user_id'];

    // Verify the message exists and belongs to this user
    $stmt = $conn->prepare("SELECT m.*, l.UserID as BusinessID 
                           FROM Messages m 
                           JOIN Listing l ON m.ListingID = l.ListingID
                           WHERE m.MessageID = ? 
                           AND (m.SenderID = ? OR m.ReceiverID = ?)");
    $stmt->bind_param("iii", $message_id, $current_user_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows) {
        $message_data = $result->fetch_assoc();
        $business_id = $message_data['BusinessID'];
        $listing_id = $message_data['ListingID'];
        
        // Update negotiation status
        $stmt = $conn->prepare("UPDATE Messages SET Negotiation_Status = ? 
                              WHERE MessageID = ?");
        $status = ($action === 'accept') ? 'Accepted' : 'Declined';
        $stmt->bind_param("si", $status, $message_id);
        $stmt->execute();
        
        if ($action === 'accept') {
            // Get the accepted offer details
            $stmt = $conn->prepare("SELECT Negotiation_Price, ListingID, SenderID FROM Messages WHERE MessageID = ?");
            $stmt->bind_param("i", $message_id);
            $stmt->execute();
            $offer = $stmt->get_result()->fetch_assoc();
            
            // Create a negotiation record in the Negotiations table
            $stmt = $conn->prepare("INSERT INTO Negotiations (ListingID, UserID, Price, Description, Current_Status, Time_Of_Event) 
                                  VALUES (?, ?, ?, ?, 'Accepted', NOW())");
            $description = "Accepted offer from message ID: " . $message_id;
            $stmt->bind_param("iids", $offer['ListingID'], $current_user_id, $offer['Negotiation_Price'], $description);
            $stmt->execute();
            $negotiation_id = $conn->insert_id;
            
            // Record the purchase in Purchase_History_Negotiation
            $stmt = $conn->prepare("INSERT INTO Purchase_History_Negotiation (UserID, NegotiationID) 
                                  VALUES (?, ?)");
            $stmt->bind_param("ii", $current_user_id, $negotiation_id);
            $stmt->execute();
            
            // Verify the user if they're a customer
            if ($_SESSION['user_type'] === 'Customer') {
                $stmt = $conn->prepare("UPDATE Customers SET Verified = 1 WHERE UserID = ?");
                $stmt->bind_param("i", $current_user_id);
                $stmt->execute();
            }
            
            $_SESSION['success'] = "Offer accepted successfully!";
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
            $negotiation = $stmt->get_result()->fetch_assoc();
            
            if ($negotiation && isset($negotiation['NegotiationID'])) {
                // Update existing negotiation record to Rejected
                $stmt = $conn->prepare("UPDATE Negotiations SET Current_Status = 'Rejected' WHERE NegotiationID = ?");
                $stmt->bind_param("i", $negotiation['NegotiationID']);
                $stmt->execute();
            } else {
                // Create a new rejected negotiation record if none exists
                $stmt = $conn->prepare("SELECT Negotiation_Price, ListingID, SenderID FROM Messages WHERE MessageID = ?");
                $stmt->bind_param("i", $message_id);
                $stmt->execute();
                $message_data = $stmt->get_result()->fetch_assoc();
                
                if ($message_data) {
                    $stmt = $conn->prepare("INSERT INTO Negotiations (ListingID, UserID, Price, Description, Current_Status, Time_Of_Event) 
                                          VALUES (?, ?, ?, ?, 'Rejected', NOW())");
                    $description = "Rejected offer from message ID: " . $message_id;
                    $stmt->bind_param("iids", $message_data['ListingID'], $message_data['SenderID'], 
                                     $message_data['Negotiation_Price'], $description);
                    $stmt->execute();
                }
            }
            
            $_SESSION['success'] = "Offer declined.";
        }
        
        // Redirect back to the same page
        header("Location: event.php");
        exit();
    }
    $conn->close();
}

// Get all negotiations for the current user
function getNegotiationOffers($userId) {
    $conn = get_db_connection();
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Query to get all negotiation messages with status Pending or Declined
    $query = "SELECT 
                m.MessageID,
                m.ListingID,
                m.Timesent,
                m.Negotiation_Status,
                m.Negotiation_Price,
                l.Listing_Name,
                l.UserID as BusinessID,
                CASE 
                    WHEN m.SenderID = ? THEN 'sent'
                    ELSE 'received'
                END as message_type,
                CASE 
                    WHEN m.SenderID = ? THEN b.UserID
                    ELSE c.UserID
                END as other_party_id,
                CASE 
                    WHEN m.SenderID = ? THEN b.Business_Name
                    ELSE c.Username
                END as other_party_name,
                CASE 
                    WHEN m.SenderID = ? THEN c.Username
                    ELSE b.Business_Name
                END as sender_name
              FROM Messages m
              JOIN Listing l ON m.ListingID = l.ListingID
              LEFT JOIN Business b ON (m.ReceiverID = b.UserID OR m.SenderID = b.UserID)
              LEFT JOIN Customers c ON (m.ReceiverID = c.UserID OR m.SenderID = c.UserID)
              WHERE (m.SenderID = ? OR m.ReceiverID = ?)
              AND m.Negotiation_Status IN ('Pending', 'Declined')
              ORDER BY m.Timesent DESC";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiiii", $userId, $userId, $userId, $userId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    return $messages;
}

// Get user's negotiation data
$negotiations = getNegotiationOffers($_SESSION['user_id']);

$page_title = 'Events';
$page_specific_css = '<link href="css/pages/event.css" rel="stylesheet">';
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
                    <li><a href="event.php" class="active">My Events</a></li>
                    <li><a href="history.php">History</a></li>
                    <li><a href="message.php">Messages</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Event Content -->
        <div class="col-md-9">
            <div class="main-content">
                <h2>My Events</h2>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($negotiations)): ?>
                    <div class="alert alert-info mt-3">
                        You don't have any pending or declined negotiation offers.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Listing</th>
                                    <th>From</th>
                                    <th>Price</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($negotiations as $offer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($offer['Listing_Name']); ?></td>
                                    <td>
                                        <?php if ($offer['message_type'] === 'sent'): ?>
                                            You
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($offer['sender_name']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>€<?php echo number_format($offer['Negotiation_Price'], 2); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($offer['Timesent'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $offer['Negotiation_Status'] === 'Pending' ? 'warning' : 'danger'; ?>">
                                            <?php echo htmlspecialchars($offer['Negotiation_Status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($offer['Negotiation_Status'] === 'Pending' && $offer['message_type'] === 'received'): ?>
                                            <a href="event.php?action=accept&message_id=<?php echo $offer['MessageID']; ?>" class="btn btn-sm btn-success action-btn" onclick="disableButtons(this)">Accept</a>
                                            <a href="event.php?action=decline&message_id=<?php echo $offer['MessageID']; ?>" class="btn btn-sm btn-danger action-btn" onclick="disableButtons(this)">Decline</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Function to disable all action buttons in the same row after one is clicked
function disableButtons(clickedButton) {
    // Display processing message
    clickedButton.innerHTML = clickedButton.innerHTML + ' <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    
    // Find all action buttons in the same row and disable them
    const parentRow = clickedButton.closest('tr');
    const buttons = parentRow.querySelectorAll('.action-btn');
    buttons.forEach(button => {
        button.disabled = true;
        button.classList.add('disabled');
        if (button !== clickedButton) {
            button.style.opacity = '0.5';
        }
    });
    
    // Store the processed message ID in localStorage to prevent re-processing
    const messageId = new URLSearchParams(clickedButton.href.split('?')[1]).get('message_id');
    localStorage.setItem('processed_message_' + messageId, 'true');
    
    // Submit the form after a short delay to show the spinner
    return true;
}

// On page load, check for previously processed messages
document.addEventListener('DOMContentLoaded', function() {
    const actionButtons = document.querySelectorAll('.action-btn');
    
    actionButtons.forEach(button => {
        const messageId = new URLSearchParams(button.href.split('?')[1]).get('message_id');
        if (localStorage.getItem('processed_message_' + messageId) === 'true') {
            // Disable buttons for already processed messages
            const parentRow = button.closest('tr');
            const buttons = parentRow.querySelectorAll('.action-btn');
            buttons.forEach(btn => {
                btn.disabled = true;
                btn.classList.add('disabled');
                btn.style.opacity = '0.5';
            });
            
            // Update the row to indicate it's being processed
            const statusCell = parentRow.querySelector('td:nth-child(4)');
            if (statusCell) {
                const statusBadge = statusCell.querySelector('.badge');
                if (statusBadge && statusBadge.textContent.trim() === 'Pending') {
                    statusBadge.textContent = 'Processing...';
                    statusBadge.classList.remove('bg-warning');
                    statusBadge.classList.add('bg-secondary');
                }
            }
        }
    });
});
</script>

<?php
include 'utils/footer.php';
?> 