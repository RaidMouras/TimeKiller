<?php
// Ensure session has started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Include database configuration
require_once '../utils/db_config.php';

// Clear any previous password-related session messages
if (isset($_SESSION['password_message'])) {
    unset($_SESSION['password_message'], $_SESSION['password_status']);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form fields
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    // Basic validation
    if (empty($current_password) || empty($new_password)) {
        $_SESSION['password_message'] = 'All fields are required';
        $_SESSION['password_status'] = false;
        header("Location: ../profile.php");
        exit;
    }
    
    // Get user ID
    $user_id = (int)$_SESSION['user_id'];
    
    // Connect to database
    $conn = get_db_connection();
    
    // Validate current password
    $stmt = $conn->prepare("SELECT Password FROM Users WHERE UserID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['password_message'] = 'User not found';
        $_SESSION['password_status'] = false;
        header("Location: ../profile.php");
        exit;
    }
    
    $user_data = $result->fetch_assoc();
    
    // Verify if current password is correct
    if (!password_verify($current_password, $user_data['Password'])) {
        $_SESSION['password_message'] = 'Current password is incorrect';
        $_SESSION['password_status'] = false;
        header("Location: ../profile.php");
        exit;
    }
    
    // Update password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update_stmt = $conn->prepare("UPDATE Users SET Password = ? WHERE UserID = ?");
    $update_stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['password_message'] = 'Password changed successfully';
        $_SESSION['password_status'] = true;
    } else {
        $_SESSION['password_message'] = 'Failed to update password: ' . $conn->error;
        $_SESSION['password_status'] = false;
    }
    
    // Close database connection
    $conn->close();
    
    // Redirect back to profile page
    header("Location: ../profile.php");
    exit;
} else {
    // If not a POST request, redirect to profile page
    header("Location: ../profile.php");
    exit;
} 