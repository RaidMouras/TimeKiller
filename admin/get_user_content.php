<?php
// Prevent any output before JSON
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly
ini_set('log_errors', 1);
ini_set('error_log', '../error.log');

// Set JSON header
header('Content-Type: application/json');

// Log the start of the script
error_log("Starting get_user_content.php script");

try {
    require_once '../utils/db_config.php';
    error_log("Database config file loaded successfully");
} catch (Exception $e) {
    error_log("Error loading db_config.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database configuration error'
    ]);
    exit;
}

function sendError($message, $code = 500) {
    error_log("Sending error response: " . $message);
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

// Check if request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Log request parameters
error_log("Request parameters: " . print_r($_GET, true));

// Validate input parameters
if (!isset($_GET['userId']) || !isset($_GET['type'])) {
    sendError('Missing required parameters', 400);
}

// Get parameters from GET data
$userId = intval($_GET['userId']);
$type = $_GET['type'];

if ($userId <= 0) {
    sendError('Invalid user ID', 400);
}

if (!in_array($type, ['reviews', 'messages'])) {
    sendError('Invalid content type', 400);
}

try {
    error_log("Attempting database connection");
    $conn = get_db_connection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    error_log("Database connection successful");
    
    if ($type === 'reviews') {
        // First check if user exists
        $checkUser = $conn->prepare("SELECT UserID FROM Users WHERE UserID = ?");
        if (!$checkUser) {
            throw new Exception("Prepare statement failed for user check: " . $conn->error);
        }
        $checkUser->bind_param("i", $userId);
        $checkUser->execute();
        $userResult = $checkUser->get_result();
        
        if ($userResult->num_rows === 0) {
            throw new Exception("User not found");
        }
        
        $query = "SELECT r.*, l.Listing_Name, 
                 (SELECT COUNT(*) FROM Review_Response WHERE ReviewID = r.ReviewID) as ResponseCount
                 FROM Review r 
                 LEFT JOIN Listing l ON r.ListingID = l.ListingID 
                 WHERE r.UserID = ? 
                 ORDER BY r.Created_At DESC";
    } else {
        // First check if user exists
        $checkUser = $conn->prepare("SELECT UserID FROM Users WHERE UserID = ?");
        if (!$checkUser) {
            throw new Exception("Prepare statement failed for user check: " . $conn->error);
        }
        $checkUser->bind_param("i", $userId);
        $checkUser->execute();
        $userResult = $checkUser->get_result();
        
        if ($userResult->num_rows === 0) {
            throw new Exception("User not found");
        }
        
        $query = "SELECT m.*, 
                 CASE 
                     WHEN m.SenderID = ? THEN 'Sent'
                     ELSE 'Received'
                 END as MessageType,
                 CASE 
                     WHEN c_sender.UserID IS NOT NULL THEN c_sender.Username
                     ELSE b_sender.Business_Name
                 END as SenderName,
                 CASE 
                     WHEN c_receiver.UserID IS NOT NULL THEN c_receiver.Username
                     ELSE b_receiver.Business_Name
                 END as ReceiverName
                 FROM Messages m 
                 LEFT JOIN Customers c_sender ON m.SenderID = c_sender.UserID
                 LEFT JOIN Business b_sender ON m.SenderID = b_sender.UserID
                 LEFT JOIN Customers c_receiver ON m.ReceiverID = c_receiver.UserID
                 LEFT JOIN Business b_receiver ON m.ReceiverID = b_receiver.UserID
                 WHERE m.SenderID = ? OR m.ReceiverID = ?
                 ORDER BY m.Timesent DESC";
    }
    
    error_log("Preparing query: " . $query);
    error_log("Parameters: userId=$userId, type=$type");
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    if ($type === 'reviews') {
        $stmt->bind_param("i", $userId);
        error_log("Binding review params: userId=$userId");
    } else {
        $stmt->bind_param("iii", $userId, $userId, $userId);
        error_log("Binding message params: userId=$userId (3 times)");
    }
    
    error_log("Executing query");
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $items = [];
    
    while ($row = $result->fetch_assoc()) {
        if ($type === 'reviews') {
            $items[] = [
                'ReviewID' => $row['ReviewID'],
                'Review_Content' => $row['Body'],
                'Rating' => $row['Star_Rating'],
                'Created_At' => $row['Created_At'],
                'Listing_Name' => $row['Listing_Name'],
                'ResponseCount' => $row['ResponseCount']
            ];
        } else {
            error_log("Processing message row: " . print_r($row, true));
            $items[] = [
                'MessageID' => $row['MessageID'],
                'Message_Content' => $row['Content'],
                'Created_At' => $row['Timesent'],
                'MessageType' => $row['MessageType'],
                'SenderName' => $row['SenderName'],
                'ReceiverName' => $row['ReceiverName']
            ];
        }
    }
    
    error_log("Found " . count($items) . " items");
    
    // Clear any output buffers
    ob_clean();
    
    echo json_encode([
        'success' => true,
        $type => $items
    ]);
    
} catch (Exception $e) {
    error_log("Exception in get_user_content.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Clear any output buffers
    ob_clean();
    
    sendError($e->getMessage());
} finally {
    if (isset($conn)) {
        $conn->close();
        error_log("Database connection closed");
    }
}
?> 