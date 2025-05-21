<?php
session_start();
require_once '../utils/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'You must be logged in to reply to a review';
    header('Location: ../login.php');
    exit;
}

$review_id = intval($_POST['review_id']);
$listing_id = intval($_POST['listing_id']);
$sender_id = intval($_SESSION['user_id']);
$content = trim($_POST['reply_content']);

// Validate inputs
if (empty($content)) {
    $_SESSION['error'] = 'Reply content cannot be empty';
    header("Location: ../views/serviceDetails.php?id=$listing_id");
    exit;
}

// Verify the review exists
$conn = get_db_connection();
$review_sql = "SELECT ListingID FROM Review WHERE ReviewID = ?";
$review_stmt = $conn->prepare($review_sql);
$review_stmt->bind_param("i", $review_id);
$review_stmt->execute();
$review_result = $review_stmt->get_result();

if ($review_result->num_rows === 0) {
    $_SESSION['error'] = 'Review not found';
    header("Location: ../views/serviceDetails.php?id=$listing_id");
    exit;
}

// Insert the reply (no permission check needed)
$insert_sql = "INSERT INTO Review_Response (ReviewID, SenderID, Content) VALUES (?, ?, ?)";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("iis", $review_id, $sender_id, $content);

if ($insert_stmt->execute()) {
    $_SESSION['success'] = 'Your reply has been posted';
} else {
    $_SESSION['error'] = 'There was an error submitting your reply';
}

header("Location: ../views/serviceDetails.php?id=$listing_id");
exit;
