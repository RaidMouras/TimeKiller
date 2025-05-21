<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../utils/db_config.php';

// Handle form submission before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_content'])) {
    // Get required IDs from GET parameters
    $other_user_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
    $listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
    
    $conn = get_db_connection();
    $current_user_id = $_SESSION['user_id'];
    $message_content = trim($_POST['message_content']);
    
    if (!empty($message_content) && $other_user_id > 0) {
        $insert_sql = "INSERT INTO Messages (SenderID, ReceiverID, ListingID, Content, Timesent) 
                      VALUES (?, ?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iiis", $current_user_id, $other_user_id, $listing_id, $message_content);
        
        if ($insert_stmt->execute()) {
            $_SESSION['success'] = "Message sent successfully!";
        } else {
            $_SESSION['error'] = "Failed to send message.";
        }
    } else {
        $_SESSION['error'] = "Message cannot be empty.";
    }
    
    // Redirect back to prevent form resubmission
    header("Location: ptpChat.php?customer_id=$other_user_id&listing_id=$listing_id");
    exit();
}

// Now continue with normal page rendering
if (!isset($_SESSION['user_id'])) {
    die("Please log in to continue.");
}

$conn = get_db_connection();
$current_user_id = $_SESSION['user_id'];
$current_user_type = '';
$current_user_name = '';
$current_user_picture = '';

// Get current user info
$user_sql = "SELECT User_Type FROM Users WHERE UserID = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $current_user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 0) {
    die("User not found.");
}

$user_data = $user_result->fetch_assoc();
$current_user_type = $user_data['User_Type'];

// Get current user's profile
if ($current_user_type === 'Business') {
    $profile_sql = "SELECT Business_Name as name, Profile_Picture FROM Business WHERE UserID = ?";
} else {
    $profile_sql = "SELECT Username as name, Profile_Picture FROM Customers WHERE UserID = ?";
}

$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("i", $current_user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();

if ($profile_result->num_rows > 0) {
    $profile_data = $profile_result->fetch_assoc();
    $current_user_name = $profile_data['name'];
    $current_user_picture = $profile_data['Profile_Picture'] ?? 
        ($current_user_type === 'Business' ? 'https://fakeimg.pl/50x50/?text=B' : 'https://fakeimg.pl/50x50/?text=U');
}

// Get other user ID from URL
if (!isset($_GET['customer_id'])) {
    die("Customer ID not specified.");
}

$other_user_id = intval($_GET['customer_id']);
$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;

// Get other user info
$other_user_sql = "SELECT User_Type FROM Users WHERE UserID = ?";
$other_user_stmt = $conn->prepare($other_user_sql);
$other_user_stmt->bind_param("i", $other_user_id);
$other_user_stmt->execute();
$other_user_result = $other_user_stmt->get_result();

if ($other_user_result->num_rows === 0) {
    die("User not found.");
}

$other_user_data = $other_user_result->fetch_assoc();
$other_user_type = $other_user_data['User_Type'];

// Get other user's profile
if ($other_user_type === 'Business') {
    $other_profile_sql = "SELECT Business_Name as name, Profile_Picture FROM Business WHERE UserID = ?";
} else {
    $other_profile_sql = "SELECT Username as name, Profile_Picture FROM Customers WHERE UserID = ?";
}

$other_profile_stmt = $conn->prepare($other_profile_sql);
$other_profile_stmt->bind_param("i", $other_user_id);
$other_profile_stmt->execute();
$other_profile_result = $other_profile_stmt->get_result();

if ($other_profile_result->num_rows > 0) {
    $other_profile_data = $other_profile_result->fetch_assoc();
    $other_user_name = $other_profile_data['name'];
    $other_user_picture = $other_profile_data['Profile_Picture'] ?? 
        ($other_user_type === 'Business' ? 'https://fakeimg.pl/50x50/?text=B' : 'https://fakeimg.pl/50x50/?text=U');
}

// Get messages between users
$messages_sql = "SELECT m.*, 
                CASE 
                    WHEN m.SenderID = ? THEN 'sent'
                    ELSE 'received'
                END as message_type
                FROM Messages m
                WHERE (m.SenderID = ? AND m.ReceiverID = ?)
                OR (m.SenderID = ? AND m.ReceiverID = ?)
                ORDER BY m.Timesent ASC";
$messages_stmt = $conn->prepare($messages_sql);
$messages_stmt->bind_param("iiiii", $current_user_id, $current_user_id, $other_user_id, $other_user_id, $current_user_id);
$messages_stmt->execute();
$messages = $messages_stmt->get_result();

// Set page title
$page_title = "Chat with " . htmlspecialchars($other_user_name);

// Add custom styles for the chat page
$page_specific_css = '
<style>
    body { background-color: #f8f9fa; }
    .chat-container { max-width: 800px; margin: 30px auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
    .chat-header { background-color: #007bff; color: white; padding: 15px; display: flex; align-items: center; }
    .chat-header img { width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; object-fit: cover; }
    .chat-messages { height: 400px; overflow-y: auto; padding: 15px; background-color: #f9f9f9; }
    .message { margin-bottom: 15px; display: flex; }
    .message.sent { justify-content: flex-end; }
    .message.received { justify-content: flex-start; }
    .message-content { max-width: 70%; padding: 10px 15px; border-radius: 18px; position: relative; }
    .message.sent .message-content { background-color: #007bff; color: white; border-top-right-radius: 0; }
    .message.received .message-content { background-color: #e9ecef; color: #333; border-top-left-radius: 0; }
    .message-time { font-size: 0.75rem; color: #6c757d; margin-top: 5px; text-align: right; }
    .chat-input { display: flex; padding: 15px; background-color: white; border-top: 1px solid #ddd; }
    .chat-input textarea { flex-grow: 1; border-radius: 20px; padding: 10px 15px; resize: none; border: 1px solid #ddd; }
    .chat-input button { margin-left: 10px; border-radius: 20px; padding: 10px 20px; }
    .listing-reference { background-color: #e9f5ff; padding: 10px; border-radius: 5px; margin-bottom: 15px; border-left: 3px solid #007bff; }
</style>';

include '../utils/header.php';
?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="container mt-4">
    <div class="chat-container">
        <div class="chat-header">
            <img src="../api/icon_handler.php?action=get&user_id=<?= $other_user_id ?>&nc=<?= time() ?>" 
                alt="<?php echo htmlspecialchars($other_user_name); ?>">
            <h5 class="mb-0">Chat with <?php echo htmlspecialchars($other_user_name); ?></h5>
            <a href="<?php echo $listing_id > 0 ? "serviceDetails.php?id=$listing_id" : "../index.php"; ?>" class="btn btn-sm btn-light ms-auto">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <div class="chat-messages">
            <?php if ($listing_id > 0): ?>
                <?php
                $listing_sql = "SELECT Listing_Name FROM Listing WHERE ListingID = ?";
                $listing_stmt = $conn->prepare($listing_sql);
                $listing_stmt->bind_param("i", $listing_id);
                $listing_stmt->execute();
                $listing_result = $listing_stmt->get_result();
                
                if ($listing_result->num_rows > 0): 
                    $listing_data = $listing_result->fetch_assoc();
                ?>
                    <div class="listing-reference">
                        <small>Regarding: <strong><?php echo htmlspecialchars($listing_data['Listing_Name']); ?></strong></small>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($messages->num_rows > 0): ?>
                <?php while ($message = $messages->fetch_assoc()): ?>
                    <div class="message <?php echo $message['message_type']; ?>">
                        <div class="message-content">
                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($message['Content'])); ?></p>
                            <div class="message-time">
                                <?php echo date('M j, g:i a', strtotime($message['Timesent'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center text-muted py-4">
                    No messages yet. Start the conversation!
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" class="chat-input">
            <textarea name="message_content" placeholder="Type your message here..." required></textarea>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Send
            </button>
        </form>
    </div>
</div>

<script>
    // Auto-scroll to bottom and focus input
    window.onload = function() {
        const chatMessages = document.querySelector('.chat-messages');
        chatMessages.scrollTop = chatMessages.scrollHeight;
        document.querySelector('textarea[name="message_content"]').focus();
    };
</script>

<?php
include '../utils/footer.php';
?>