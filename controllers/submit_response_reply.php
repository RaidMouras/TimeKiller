<?php
session_start();
require_once '../utils/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'You must be logged in to reply';
    header('Location: ../login.php');
    exit;
}

// Get input values
$review_id = isset($_POST['review_id']) ? intval($_POST['review_id']) : 0;
$reply_to_user_id = isset($_POST['sender_id']) ? intval($_POST['sender_id']) : 0;
$content = trim($_POST['reply_content']);
$current_user_id = intval($_SESSION['user_id']);

// Validate inputs
if (empty($content)) {
    $_SESSION['error'] = 'Reply content cannot be empty';
    header("Location: ../message.php");
    exit;
}

if ($review_id <= 0 || $reply_to_user_id <= 0) {
    $_SESSION['error'] = 'Invalid request';
    header("Location: ../message.php");
    exit;
}

// Verify the review exists
$conn = get_db_connection();
$review_sql = "SELECT * FROM Review WHERE ReviewID = ?";
$review_stmt = $conn->prepare($review_sql);
$review_stmt->bind_param("i", $review_id);
$review_stmt->execute();
$review_result = $review_stmt->get_result();

if ($review_result->num_rows === 0) {
    $_SESSION['error'] = 'Review not found';
    header("Location: ../message.php");
    $review_stmt->close();
    $conn->close();
    exit;
}

// Insert the reply
$insert_sql = "INSERT INTO Review_Response (ReviewID, SenderID, Content) VALUES (?, ?, ?)";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("iis", $review_id, $current_user_id, $content);

if ($insert_stmt->execute()) {
    $_SESSION['success'] = 'Your reply has been sent';
} else {
    $_SESSION['error'] = 'An error occurred while sending your reply';
}

$review_stmt->close();
$insert_stmt->close();
$conn->close();

// Redirect back to the messages page
header("Location: ../message.php");
exit; 