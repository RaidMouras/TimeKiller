<?php
session_start();
require_once '../utils/db_config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

$conn = get_db_connection();
$admin_id = $_SESSION['user_id'];

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_REQUEST;
}

$action = isset($data['action']) ? $data['action'] : '';

header('Content-Type: application/json');

switch ($action) {
    case 'add':
        addTag($conn, $data, $admin_id);
        break;
    case 'update':
        updateTag($conn, $data, $admin_id);
        break;
    case 'delete':
        deleteTag($conn, $data, $admin_id);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

$conn->close();

function addTag($conn, $data, $admin_id) {
    if (!isset($data['tag_name']) || empty(trim($data['tag_name']))) {
        echo json_encode(['success' => false, 'error' => 'Tag name is required']);
        return;
    }

    $tag_name = trim($data['tag_name']);

    $check_stmt = $conn->prepare("SELECT TagID FROM Tags WHERE Tag_Name = ?");
    $check_stmt->bind_param("s", $tag_name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Tag already exists']);
        $check_stmt->close();
        return;
    }
    $check_stmt->close();

    $stmt = $conn->prepare("INSERT INTO Tags (Tag_Name) VALUES (?)");
    $stmt->bind_param("s", $tag_name);
    
    if ($stmt->execute()) {
        $tag_id = $conn->insert_id;
        
        logAdminAction($conn, $admin_id, 'Added Tag', "Added new tag: $tag_name (ID: $tag_id)");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Tag added successfully', 
            'tag' => [
                'TagID' => $tag_id,
                'Tag_Name' => $tag_name
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to add tag: ' . $stmt->error]);
    }
    
    $stmt->close();
}

function updateTag($conn, $data, $admin_id) {
    if (!isset($data['tag_id']) || !is_numeric($data['tag_id'])) {
        echo json_encode(['success' => false, 'error' => 'Valid tag ID is required']);
        return;
    }
    
    if (!isset($data['tag_name']) || empty(trim($data['tag_name']))) {
        echo json_encode(['success' => false, 'error' => 'Tag name is required']);
        return;
    }

    $tag_id = intval($data['tag_id']);
    $tag_name = trim($data['tag_name']);

    $check_stmt = $conn->prepare("SELECT Tag_Name FROM Tags WHERE TagID = ?");
    $check_stmt->bind_param("i", $tag_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Tag not found']);
        $check_stmt->close();
        return;
    }
    
    $old_tag = $check_result->fetch_assoc();
    $old_tag_name = $old_tag['Tag_Name'];
    $check_stmt->close();

    $check_duplicate_stmt = $conn->prepare("SELECT TagID FROM Tags WHERE Tag_Name = ? AND TagID != ?");
    $check_duplicate_stmt->bind_param("si", $tag_name, $tag_id);
    $check_duplicate_stmt->execute();
    $check_duplicate_result = $check_duplicate_stmt->get_result();
    
    if ($check_duplicate_result->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Another tag with this name already exists']);
        $check_duplicate_stmt->close();
        return;
    }
    $check_duplicate_stmt->close();

    $stmt = $conn->prepare("UPDATE Tags SET Tag_Name = ? WHERE TagID = ?");
    $stmt->bind_param("si", $tag_name, $tag_id);
    
    if ($stmt->execute()) {
        logAdminAction($conn, $admin_id, 'Edited Tag', "Changed tag from \"$old_tag_name\" to \"$tag_name\" (ID: $tag_id)");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Tag updated successfully',
            'tag' => [
                'TagID' => $tag_id,
                'Tag_Name' => $tag_name
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update tag: ' . $stmt->error]);
    }
    
    $stmt->close();
}

function deleteTag($conn, $data, $admin_id) {
    if (!isset($data['tag_id']) || !is_numeric($data['tag_id'])) {
        echo json_encode(['success' => false, 'error' => 'Valid tag ID is required']);
        return;
    }

    $tag_id = intval($data['tag_id']);

    $conn->begin_transaction();
    
    try {
        $get_stmt = $conn->prepare("SELECT Tag_Name FROM Tags WHERE TagID = ?");
        $get_stmt->bind_param("i", $tag_id);
        $get_stmt->execute();
        $result = $get_stmt->get_result();
        
        if ($result->num_rows === 0) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => 'Tag not found']);
            $get_stmt->close();
            return;
        }
        
        $tag = $result->fetch_assoc();
        $tag_name = $tag['Tag_Name'];
        $get_stmt->close();
        
        $delete_assoc_stmt = $conn->prepare("DELETE FROM Listing_Tag WHERE TagID = ?");
        $delete_assoc_stmt->bind_param("i", $tag_id);
        $delete_assoc_stmt->execute();
        $delete_assoc_stmt->close();
        
        $delete_stmt = $conn->prepare("DELETE FROM Tags WHERE TagID = ?");
        $delete_stmt->bind_param("i", $tag_id);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        logAdminAction($conn, $admin_id, 'Deleted Tag', "Deleted tag: $tag_name (ID: $tag_id)");
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Tag deleted successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Failed to delete tag: ' . $e->getMessage()]);
    }
}

function logAdminAction($conn, $admin_id, $action_type, $description) {
    $stmt = $conn->prepare("INSERT INTO Admin_History (AdminID, Action_Type, Action_Description) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $admin_id, $action_type, $description);
    $stmt->execute();
    $stmt->close();
} 