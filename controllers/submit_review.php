<?php
session_start();
require_once '../includes/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'You must be logged in to submit a review';
    header('Location: ../login.php');
    exit;
}

$listing_id = intval($_POST['listing_id']);
$user_id = intval($_SESSION['user_id']);
$rating = intval($_POST['rating']);
$review_text = trim($_POST['review_text']);

// Validate inputs
if ($rating < 1 || $rating > 5) {
    $_SESSION['error'] = 'Please select a valid rating (1-5 stars)';
    header("Location: ../views/serviceDetails.php?id=$listing_id");
    exit;
}

if (empty($review_text)) {
    $_SESSION['error'] = 'Review text cannot be empty';
    header("Location: ../views/serviceDetails.php?id=$listing_id");
    exit;
}

// Check if user is a verified customer
$conn = get_db_connection();

$verified_check_sql = "SELECT Verified FROM Customers WHERE UserID = ?";
$verified_stmt = $conn->prepare($verified_check_sql);
$verified_stmt->bind_param("i", $user_id);
$verified_stmt->execute();
$verified_result = $verified_stmt->get_result();

if ($verified_result->num_rows === 0 || $verified_result->fetch_assoc()['Verified'] != 1) {
    $_SESSION['error'] = 'You need to be a verified user to leave reviews';
    header("Location: ../views/serviceDetails.php?id=$listing_id");
    exit;
}

// Check if user already reviewed this listing
$check_sql = "SELECT ReviewID FROM Review WHERE ListingID = ? AND UserID = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $listing_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $_SESSION['error'] = 'You have already reviewed this listing';
    header("Location: ../views/serviceDetails.php?id=$listing_id");
    exit;
}

// Insert the review
$insert_sql = "INSERT INTO Review (ListingID, UserID, Star_Rating, Body) VALUES (?, ?, ?, ?)";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("iiis", $listing_id, $user_id, $rating, $review_text);

if ($insert_stmt->execute()) {
    $_SESSION['success'] = 'Thank you for your review!';
} else {
    $_SESSION['error'] = 'There was an error submitting your review';
}

header("Location: ../views/serviceDetails.php?id=$listing_id");
exit;
?>