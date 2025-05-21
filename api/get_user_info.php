<?php
// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../utils/db_config.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user_id parameter exists
if (!isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID is required']);
    exit;
}

$user_id = intval($_GET['user_id']);

// Input validation
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid User ID']);
    exit;
}

try {
    // Get database connection
    $conn = get_db_connection();
    
    // Fetch user information - first check if user is a customer or business
    $user_type_sql = "SELECT User_Type FROM Users WHERE UserID = ?";
    $user_type_stmt = $conn->prepare($user_type_sql);
    $user_type_stmt->bind_param("i", $user_id);
    $user_type_stmt->execute();
    $user_type_result = $user_type_stmt->get_result();
    
    if ($user_type_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    $user_type_data = $user_type_result->fetch_assoc();
    $user_type = $user_type_data['User_Type'];
    
    // Based on user type, fetch the appropriate data
    if ($user_type == 'Business') {
        $query = "SELECT u.Email, b.Description as bio 
                 FROM Users u
                 JOIN Business b ON u.UserID = b.UserID
                 WHERE u.UserID = ?";
    } else {
        $query = "SELECT u.Email, c.Bio as bio
                 FROM Users u
                 JOIN Customers c ON u.UserID = c.UserID
                 WHERE u.UserID = ?";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // This shouldn't happen if user exists in Users table
        http_response_code(404);
        echo json_encode(['error' => 'User profile information not found']);
        exit;
    }
    
    // Get user data
    $user_data = $result->fetch_assoc();
    
    // Return user info
    echo json_encode([
        'email' => $user_data['Email'],
        'bio' => $user_data['bio']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
} 