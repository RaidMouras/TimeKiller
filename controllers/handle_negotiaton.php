<?php
session_start();
require_once '../utils/db_config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit();
}

$message_id = intval($_POST['message_id']);
$action = $_POST['action'];

$conn = get_db_connection();

// Verify the message belongs to this business
$stmt = $conn->prepare("SELECT m.* FROM Messages m
                      JOIN Listing l ON m.ListingID = l.ListingID
                      WHERE m.MessageID = ? AND l.UserID = ?");
$stmt->bind_param("ii", $message_id, $_SESSION['user_id']);
$stmt->execute();
$message = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$message) {
    $_SESSION['error'] = "Invalid negotiation";
    header("Location: message.php");
    exit();
}

// Update negotiation status
$stmt = $conn->prepare("UPDATE Messages SET Negotiation_Status = ? WHERE MessageID = ?");
$status = ($action === 'accept') ? 'Accepted' : 'Declined';
$stmt->bind_param("si", $status, $message_id);
$stmt->execute();
$stmt->close();

header("Location: businessChat.php?customer_id=".$message['SenderID']."&listing_id=".$message['ListingID']);
exit();
?>