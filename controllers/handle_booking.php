<?php
session_start();
require_once '../utils/db_config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please log in to book services';
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$listing_id = intval($_POST['listing_id']);
$user_id = $_SESSION['user_id'];

// Check if booking already exists
$conn = get_db_connection();
$check_stmt = $conn->prepare("SELECT 1 FROM Bookings WHERE UserID = ? AND ListingID = ?");
$check_stmt->bind_param("ii", $user_id, $listing_id);
$check_stmt->execute();

if ($check_stmt->get_result()->num_rows > 0) {
    $_SESSION['error'] = "You've already booked this service";
} else {
    // Record the booking
    $insert_stmt = $conn->prepare("INSERT INTO Bookings (UserID, ListingID) VALUES (?, ?)");
    $insert_stmt->bind_param("ii", $user_id, $listing_id);
    
    if ($insert_stmt->execute()) {
        $_SESSION['success'] = "Booking confirmed! You can now leave a review.";
    } else {
        $_SESSION['error'] = "Failed to complete booking. Please try again.";
    }
}

header("Location: ../views/serviceDetails.php?id=$listing_id");
exit;
?>