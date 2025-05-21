<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../utils/db_config.php';

header('Content-Type: application/json');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', '../error.log');

function sendError($message, $code = 500) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    error_log("Ban toggle error: " . $message);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Validate input parameters
if (!isset($_POST['userId']) || !isset($_POST['newStatus'])) {
    sendError('Missing required parameters', 400);
}

// Get user ID from POST data
$userId = intval($_POST['userId']);
$newStatus = intval($_POST['newStatus']);

if ($userId <= 0) {
    sendError('Invalid user ID', 400);
}

if ($newStatus !== 0 && $newStatus !== 1) {
    sendError('Invalid status value', 400);
}

// Start session to get admin ID
session_start();
$admin_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

try {
    $conn = get_db_connection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Check if user exists
        $check_stmt = $conn->prepare("SELECT UserID FROM Users WHERE UserID = ?");
        if (!$check_stmt) {
            throw new Exception("Prepare check statement failed: " . $conn->error);
        }
        
        $check_stmt->bind_param("i", $userId);
        if (!$check_stmt->execute()) {
            throw new Exception("Execute check failed: " . $check_stmt->error);
        }
        
        $result = $check_stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("User not found");
        }
        
        // Update user's banned status
        $stmt = $conn->prepare("UPDATE Users SET Banned = ? WHERE UserID = ?");
        if (!$stmt) {
            throw new Exception("Prepare update failed: " . $conn->error);
        }
        
        $stmt->bind_param("ii", $newStatus, $userId);
        if (!$stmt->execute()) {
            throw new Exception("Execute update failed: " . $stmt->error);
        }
        
        if ($stmt->affected_rows > 0) {
            // Add to admin history if admin_id is valid
            if ($admin_id > 0) {
                // First verify admin exists in Users table
                $admin_check = $conn->prepare("SELECT UserID FROM Users WHERE UserID = ? AND Is_Admin = 1");
                if ($admin_check) {
                    $admin_check->bind_param("i", $admin_id);
                    $admin_check->execute();
                    $admin_result = $admin_check->get_result();
                    
                    if ($admin_result->num_rows > 0) {
                        $action_type = $newStatus ? 'Banned User' : 'Unban User';
                        $action_description = $newStatus ? 
                            "Banned user with ID: $userId" : 
                            "Unbanned user with ID: $userId";
                            
                        $hist_stmt = $conn->prepare("INSERT INTO Admin_History (AdminID, Action_Type, Action_Description) VALUES (?, ?, ?)");
                        if ($hist_stmt) {
                            $hist_stmt->bind_param("iss", $admin_id, $action_type, $action_description);
                            // Try to execute but continue even if it fails
                            try {
                                $hist_stmt->execute();
                                error_log("Admin action logged successfully: $action_type for user $userId by admin $admin_id");
                            } catch (Exception $e) {
                                error_log("Failed to log admin action: " . $e->getMessage());
                                // Continue even if logging fails
                            }
                        }
                    } else {
                        error_log("Admin ID $admin_id is not a valid admin in the Users table");
                    }
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => $newStatus ? 'User has been banned' : 'User has been unbanned'
            ]);
        } else {
            // Rollback transaction
            $conn->rollback();
            
            echo json_encode([
                'success' => true,
                'message' => 'No changes were made (user status was already set)'
            ]);
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    sendError($e->getMessage());
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?> 