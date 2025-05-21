<?php
session_start();
require_once '../includes/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'You must be logged in to book a service';
    header('Location: ../login.php');
    exit;
}


$listing_id = intval($_POST['listing_id']);
$tier_id = isset($_POST['tier_id']) ? intval($_POST['tier_id']) : 0;
$user_id = intval($_SESSION['user_id']);

$conn = get_db_connection();

if (isset($_POST['negotiation_price'])) {
    $price = floatval($_POST['negotiation_price']);
    // Create a special tier for this negotiation
    $stmt = $conn->prepare("INSERT INTO Price_Tiers (ListingID, Tier_Name, Price, Description) 
                          VALUES (?, 'Negotiated Price', ?, 'Custom negotiated price')");
    $stmt->bind_param("id", $_POST['listing_id'], $price);
    $stmt->execute();
    $tier_id = $conn->insert_id;
    $stmt->close();
} else {
    $tier_id = intval($_POST['tier_id']);
}

// Verify the tier belongs to the listing
if ($tier_id > 0) {
    $verify_tier_sql = "SELECT TierID FROM Price_Tiers WHERE TierID = ? AND ListingID = ?";
    $verify_tier_stmt = $conn->prepare($verify_tier_sql);
    $verify_tier_stmt->bind_param("ii", $tier_id, $listing_id);
    $verify_tier_stmt->execute();
    
    if ($verify_tier_stmt->get_result()->num_rows === 0) {
        $_SESSION['error'] = 'Invalid pricing tier selected';
        header("Location: ../views/serviceDetails.php?id=$listing_id");
        exit;
    }
}

// Record the purchase
$purchase_sql = "INSERT INTO Purchase_History_Tier (UserID, TierID, Event_Time) VALUES (?, ?, NOW())";
$purchase_stmt = $conn->prepare($purchase_sql);
$purchase_stmt->bind_param("ii", $user_id, $tier_id);

// Mark user as verified
$verify_sql = "UPDATE Customers SET Verified = 1 WHERE UserID = ?";
$verify_stmt = $conn->prepare($verify_sql);
$verify_stmt->bind_param("i", $user_id);

try {
    $conn->begin_transaction();
    
    // Record the purchase
    if (!$purchase_stmt->execute()) {
        throw new Exception("Failed to record purchase");
    }
    
    // Mark user as verified
    if (!$verify_stmt->execute()) {
        throw new Exception("Failed to verify user");
    }
    
    $conn->commit();
    $_SESSION['success'] = 'Service booked successfully! You can now leave a review.';
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Error booking service: ' . $e->getMessage();
}

header("Location: ../views/serviceDetails.php?id=$listing_id");
exit;
?>