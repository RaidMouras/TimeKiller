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
    error_log("Content deletion error: " . $message);
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Start session to get admin ID
session_start();
$admin_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

try {
    require_once '../utils/db_config.php';
    $conn = get_db_connection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        if (isset($_POST['userId']) && (isset($_POST['deleteReviews']) || isset($_POST['deleteMessages']))) {
            // Bulk deletion by user ID
            $userId = intval($_POST['userId']);
            $deleteReviews = isset($_POST['deleteReviews']) && $_POST['deleteReviews'] === 'true';
            $deleteMessages = isset($_POST['deleteMessages']) && $_POST['deleteMessages'] === 'true';
            
            if ($userId <= 0) {
                throw new Exception('Invalid user ID');
            }
            
            if (!$deleteReviews && !$deleteMessages) {
                throw new Exception('No content type selected for deletion');
            }
            
            $totalDeleted = 0;
            
            // Delete reviews if selected
            if ($deleteReviews) {
                // First delete review responses
                $stmt = $conn->prepare("DELETE FROM Review_Response WHERE ReviewID IN (SELECT ReviewID FROM Review WHERE UserID = ?)");
                if (!$stmt) {
                    throw new Exception("Prepare statement failed for review responses: " . $conn->error);
                }
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                
                // Then delete the reviews
                $stmt = $conn->prepare("DELETE FROM Review WHERE UserID = ?");
                if (!$stmt) {
                    throw new Exception("Prepare statement failed for reviews: " . $conn->error);
                }
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $reviewsDeleted = $stmt->affected_rows;
                $totalDeleted += $reviewsDeleted;
                
                // Log review deletion to admin history if reviews were deleted
                if ($reviewsDeleted > 0 && $admin_id > 0) {
                    logAdminAction($conn, $admin_id, 'Deleted Review', "Deleted {$reviewsDeleted} reviews from user ID: {$userId}");
                }
            }
            
            // Delete messages if selected
            if ($deleteMessages) {
                // Delete the messages
                $stmt = $conn->prepare("DELETE FROM Messages WHERE SenderID = ? OR ReceiverID = ?");
                if (!$stmt) {
                    throw new Exception("Prepare statement failed for messages: " . $conn->error);
                }
                $stmt->bind_param("ii", $userId, $userId);
                $stmt->execute();
                $messagesDeleted = $stmt->affected_rows;
                $totalDeleted += $messagesDeleted;
                
                // Log message deletion to admin history if messages were deleted
                if ($messagesDeleted > 0 && $admin_id > 0) {
                    logAdminAction($conn, $admin_id, 'Deleted Message', "Deleted {$messagesDeleted} messages from user ID: {$userId}");
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => "Deleted {$totalDeleted} items successfully"
            ]);
        } else if (isset($_POST['type']) && isset($_POST['id'])) {
            // Single item deletion
            $type = $_POST['type'];
            $id = intval($_POST['id']);
            
            if (!in_array($type, ['review', 'message', 'response'])) {
                throw new Exception('Invalid content type');
            }
            
            if ($id <= 0) {
                throw new Exception('Invalid ID');
            }
            
            if ($type === 'review') {
                // First delete review responses
                $stmt = $conn->prepare("DELETE FROM Review_Response WHERE ReviewID = ?");
                if (!$stmt) {
                    throw new Exception("Prepare statement failed for review responses: " . $conn->error);
                }
                $stmt->bind_param("i", $id);
                $stmt->execute();
                
                // Then delete the review
                $stmt = $conn->prepare("DELETE FROM Review WHERE ReviewID = ?");
            } else if ($type === 'message') {
                // Delete message
                $stmt = $conn->prepare("DELETE FROM Messages WHERE MessageID = ?");
                if (!$stmt) {
                    error_log("Failed to prepare message deletion statement: " . $conn->error);
                    throw new Exception("Failed to prepare message deletion statement");
                }
            } else if ($type === 'response') {
                // Delete review response
                $stmt = $conn->prepare("DELETE FROM Review_Response WHERE ReplyID = ?");
                if (!$stmt) {
                    error_log("Failed to prepare response deletion statement: " . $conn->error);
                    throw new Exception("Failed to prepare response deletion statement");
                }
            }
            
            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $conn->error);
            }
            
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                error_log("Failed to execute deletion statement: " . $stmt->error);
                throw new Exception("Failed to execute deletion statement: " . $stmt->error);
            }
            
            if ($stmt->affected_rows > 0) {
                // Log action to admin history
                if ($admin_id > 0) {
                    $action_type = $type === 'review' ? 'Deleted Review' : ($type === 'message' ? 'Deleted Message' : 'Deleted Review Response');
                    $action_description = ucfirst($type) . " with ID: $id was deleted";
                    logAdminAction($conn, $admin_id, $action_type, $action_description);
                }
                
                $conn->commit();
                echo json_encode([
                    'success' => true,
                    'message' => ucfirst($type) . ' deleted successfully'
                ]);
            } else {
                $conn->rollback();
                error_log("No rows affected for deletion of $type ID: $id");
                echo json_encode([
                    'success' => false,
                    'error' => ucfirst($type) . ' not found'
                ]);
            }
        } else {
            throw new Exception('Missing required parameters');
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Exception in delete_content.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendError($e->getMessage());
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

/**
 * Log admin action to Admin_History table
 * @param mysqli $conn Database connection
 * @param int $admin_id Admin ID
 * @param string $action_type Type of action
 * @param string $action_description Description of action
 */
function logAdminAction($conn, $admin_id, $action_type, $action_description) {
    try {
        $stmt = $conn->prepare("INSERT INTO Admin_History (AdminID, Action_Type, Action_Description) VALUES (?, ?, ?)");
        if (!$stmt) {
            error_log("Failed to prepare admin history log statement: " . $conn->error);
            return;
        }
        
        $stmt->bind_param("iss", $admin_id, $action_type, $action_description);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
    }
}
?> 