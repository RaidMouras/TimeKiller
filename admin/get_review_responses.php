<?php
// Prevent any output before JSON
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../error.log');

// Set JSON header
header('Content-Type: application/json');

function sendError($message, $code = 500) {
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

// Start session to check admin status
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    sendError('Unauthorized access', 401);
}

// Validate input parameters
if (!isset($_GET['reviewId']) || !is_numeric($_GET['reviewId'])) {
    sendError('Missing or invalid review ID', 400);
}

$reviewId = intval($_GET['reviewId']);
if ($reviewId <= 0) {
    sendError('Invalid review ID', 400);
}

try {
    require_once '../utils/db_config.php';
    $conn = get_db_connection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // First get the review details
    $reviewStmt = $conn->prepare("
        SELECT r.*, 
        CASE 
            WHEN c.UserID IS NOT NULL THEN c.Username
            WHEN b.UserID IS NOT NULL THEN b.Business_Name
            ELSE 'Unknown'
        END as ReviewerName
        FROM Review r
        LEFT JOIN Users u ON r.UserID = u.UserID
        LEFT JOIN Customers c ON r.UserID = c.UserID
        LEFT JOIN Business b ON r.UserID = b.UserID
        WHERE r.ReviewID = ?
    ");
    
    if (!$reviewStmt) {
        throw new Exception("Failed to prepare review statement: " . $conn->error);
    }
    
    $reviewStmt->bind_param("i", $reviewId);
    $reviewStmt->execute();
    $reviewResult = $reviewStmt->get_result();
    
    if ($reviewResult->num_rows === 0) {
        sendError('Review not found', 404);
    }
    
    $review = $reviewResult->fetch_assoc();
    
    // Now get all responses to this review
    $responseStmt = $conn->prepare("
        SELECT rr.*, 
        CASE 
            WHEN c.UserID IS NOT NULL THEN c.Username
            WHEN b.UserID IS NOT NULL THEN b.Business_Name
            ELSE 'Unknown'
        END as SenderName
        FROM Review_Response rr
        LEFT JOIN Users u ON rr.SenderID = u.UserID
        LEFT JOIN Customers c ON rr.SenderID = c.UserID
        LEFT JOIN Business b ON rr.SenderID = b.UserID
        WHERE rr.ReviewID = ?
        ORDER BY rr.Time_sent ASC
    ");
    
    if (!$responseStmt) {
        throw new Exception("Failed to prepare response statement: " . $conn->error);
    }
    
    $responseStmt->bind_param("i", $reviewId);
    $responseStmt->execute();
    $responseResult = $responseStmt->get_result();
    
    $responses = [];
    while ($row = $responseResult->fetch_assoc()) {
        $responses[] = $row;
    }
    
    // Return both the review and its responses
    echo json_encode([
        'success' => true,
        'review' => $review,
        'responses' => $responses
    ]);
    
} catch (Exception $e) {
    sendError($e->getMessage());
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?> 