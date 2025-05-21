<?php
require_once '../utils/db_config.php';
session_start();

// Validate user is logged in
if (!isset($_SESSION['user_id'])) {
    sendErrorResponse('User not authenticated');
    exit;
}

// Get user ID from session or from request parameter
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)$_SESSION['user_id'];

// Handle different actions
$action = $_GET['action'] ?? 'get';

switch ($action) {
    case 'get':
        getProfileIcon($user_id);
        break;
    case 'update':
        updateProfileIcon((int)$_SESSION['user_id']);
        break;
    case 'clean':
        cleanOldIcons((int)$_SESSION['user_id']);
        break;
    case 'clear_cache':
        unset($_SESSION['profile_picture']);
        $referer = $_SERVER['HTTP_REFERER'] ?? 'profile.php';
        header("Location: $referer");
        exit;
    default:
        sendErrorResponse('Invalid action');
}

/**
 * Get the user profile icon.
 */
function getProfileIcon($user_id) {
    try {
        // Check if we have a valid profile picture in session first
        // Only use session cache for current user, not for other users
        if (isset($_SESSION['profile_picture']) && $user_id == $_SESSION['user_id']) {
            $iconPath = $_SESSION['profile_picture'];
            if (file_exists('../' . $iconPath)) {
                deliverImage('../' . $iconPath);
                exit;
            }
        }
        
        $conn = get_db_connection();
        
        // Get user info including picture path
        $userInfo = getUserInfo($conn, $user_id);
        
        if (!$userInfo) {
            deliverDefaultIcon();
            $conn->close();
            exit;
        }
        
        $iconPath = $userInfo['profile_picture'];
        
        // Check if path exists and deliver
        if (!empty($iconPath)) {
            // Remove 'uploads/' prefix if present to normalize path
            $iconPath = str_replace('uploads/', '', $iconPath);
            
            // Web URL path (for session)
            $webPath = 'uploads/' . $iconPath;
            
            // File system path (for file_exists and readfile)
            $fsPath = '../' . $webPath;
            
            if (file_exists($fsPath)) {
                // Store web path in session ONLY for current user, not for other users
                if ($user_id == $_SESSION['user_id']) {
                    $_SESSION['profile_picture'] = $webPath;
                }
                $conn->close();
                deliverImage($fsPath);
                exit;
            }
        }
        
        $conn->close();
        // If all else fails, deliver default icon
        deliverDefaultIcon();
    } catch (Exception $e) {
        deliverDefaultIcon();
    }
}

/**
 * Update the user profile icon
 */
function updateProfileIcon($user_id) {
    $response = [
        'success' => false,
        'message' => '',
        'file_path' => ''
    ];
    
    $conn = null;
    
    try {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] != UPLOAD_ERR_OK) {
            $response['message'] = 'No file uploaded or upload error';
            returnJson($response);
            exit;
        }
        
        // Get file and file type
        $file = $_FILES['file'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $contentType = $file['type'];
        
        // Support for Blob data received from Cropper.js
        if (empty($fileExt) || $fileExt == 'blob') {
            // Set extension based on content type
            if (strpos($contentType, 'jpeg') !== false || strpos($contentType, 'jpg') !== false) {
                $fileExt = 'jpg';
            } elseif (strpos($contentType, 'png') !== false) {
                $fileExt = 'png';
            } elseif (strpos($contentType, 'gif') !== false) {
                $fileExt = 'gif';
            } elseif (strpos($contentType, 'webp') !== false) {
                $fileExt = 'webp';
            } else {
                // Default to jpg
                $fileExt = 'jpg';
            }
        }
        
        // File type validation
        if (!in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $response['message'] = 'Invalid file type. Only JPG, PNG, GIF and WEBP are allowed.';
            returnJson($response);
            exit;
        }
        
        $conn = get_db_connection();
        
        // Get user info
        $userInfo = getUserInfo($conn, $user_id);
        
        if (!$userInfo) {
            $response['message'] = 'User not found';
            if ($conn) $conn->close();
            returnJson($response);
            exit;
        }
        
        $user_type = $userInfo['user_type'];
        
        // Process file upload
        $timestamp = time();
        $fileName = $timestamp . '.' . $fileExt;
        
        // Ensure user directory exists
        $userDir = '../uploads/icon/user_' . $user_id;
        if (!file_exists($userDir)) {
            mkdir($userDir, 0777, true);
        }
        
        $targetPath = $userDir . '/' . $fileName;
        $dbFilePath = 'icon/user_' . $user_id . '/' . $fileName;
        
        // Save file
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Update database
            $table = ($user_type === 'Customer') ? 'Customers' : 'Business';
            $stmt = $conn->prepare("UPDATE $table SET Profile_Picture = ? WHERE UserID = ?");
            $stmt->bind_param("si", $dbFilePath, $user_id);
            $result = $stmt->execute();
            
            if ($result) {
                // Update session
                $_SESSION['profile_picture'] = 'uploads/' . $dbFilePath;
                
                // Clean up old files
                cleanOldFiles($userDir, $fileName);
                
                // Return success information
                $response['success'] = true;
                $response['message'] = 'Profile picture updated successfully';
                $response['file_path'] = 'uploads/' . $dbFilePath;
                $response['updated_field'] = 'profile_picture';
                $response['new_value'] = 'uploads/' . $dbFilePath;
                $response['is_cropped'] = !empty($_POST['is_cropped']);
            } else {
                $response['message'] = 'Database update failed: ' . $conn->error;
            }
        } else {
            $response['message'] = 'Failed to save uploaded file';
        }
    } catch (Exception $e) {
        $response['message'] = 'Error occurred: ' . $e->getMessage();
    } finally {
        if ($conn) $conn->close();
    }
    
    returnJson($response);
}

/**
 * Clean old icons for the user (keeps only the most recent)
 */
function cleanOldIcons($user_id) {
    $response = [
        'success' => true,
        'message' => 'No cleanup needed',
        'deleted_count' => 0
    ];
    
    $conn = null;
    
    try {
        $userDir = '../uploads/icon/user_' . $user_id;
        
        if (!file_exists($userDir)) {
            returnJson($response);
            exit;
        }
        
        $conn = get_db_connection();
        
        // Get user info
        $userInfo = getUserInfo($conn, $user_id);
        
        if (!$userInfo) {
            $response['message'] = 'User not found';
            if ($conn) $conn->close();
            returnJson($response);
            exit;
        }
        
        $currentIcon = !empty($userInfo['profile_picture']) ? $userInfo['profile_picture'] : '';
        $currentFile = basename($currentIcon);
        
        // Use new cleanOldFiles function for cleanup
        $deleted = cleanOldFiles($userDir, $currentFile);
        
        $response['success'] = true;
        $response['message'] = "Cleanup completed, deleted " . count($deleted) . " files";
        $response['deleted_count'] = count($deleted);
    } catch (Exception $e) {
        $response['message'] = 'Error occurred: ' . $e->getMessage();
        $response['success'] = false;
    } finally {
        if ($conn) $conn->close();
    }
    
    returnJson($response);
}

/**
 * Clean old files in a directory except the one to keep
 */
function cleanOldFiles($directory, $keepFile = null) {
    $deletedFiles = [];
    
    if (file_exists($directory) && is_dir($directory)) {
        $files = scandir($directory);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === $keepFile || $file === '.DS_Store') {
                continue;
            }
            
            $filePath = $directory . '/' . $file;
            
            if (is_file($filePath)) {
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    if (unlink($filePath)) {
                        $deletedFiles[] = $file;
                    }
                }
            }
        }
    }
    
    return $deletedFiles;
}

/**
 * Get user information including profile picture
 */
function getUserInfo($conn, $user_id) {
    // Get user type
    $stmt = $conn->prepare("SELECT User_Type FROM Users WHERE UserID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $user = $result->fetch_assoc();
    $user_type = $user['User_Type'];
    
    // Get profile picture based on user type
    $table = ($user_type === 'Customer') ? 'Customers' : 'Business';
    $stmt = $conn->prepare("SELECT Profile_Picture FROM $table WHERE UserID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['user_type' => $user_type, 'profile_picture' => ''];
    }
    
    $row = $result->fetch_assoc();
    
    return [
        'user_type' => $user_type,
        'profile_picture' => $row['Profile_Picture']
    ];
}

/**
 * Deliver the default icon
 */
function deliverDefaultIcon() {
    $defaultIcon = '../uploads/default/init_icon.png';
    $mimeType = getMimeType($defaultIcon);
    header("Content-Type: $mimeType");
    header("Content-Length: " . filesize($defaultIcon));
    header("Cache-Control: max-age=3600, public");
    
    readfile($defaultIcon);
    exit;
}

/**
 * Deliver an image file
 */
function deliverImage($path) {
    if (!file_exists($path)) {
        deliverDefaultIcon();
        return;
    }
    
    $mimeType = getMimeType($path);
    header("Content-Type: $mimeType");
    header("Content-Length: " . filesize($path));
    header("Cache-Control: max-age=3600, public");
    
    readfile($path);
    exit;
}

/**
 * Get the MIME type of a file
 */
function getMimeType($path) {
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    switch (strtolower($ext)) {
        case 'jpg':
        case 'jpeg':
            return 'image/jpeg';
        case 'png':
            return 'image/png';
        case 'gif':
            return 'image/gif';
        case 'webp':
            return 'image/webp';
        default:
            return 'application/octet-stream';
    }
}

/**
 * Return a JSON response
 */
function returnJson($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Send an error response
 */
function sendErrorResponse($message) {
    $response = [
        'success' => false,
        'message' => $message
    ];
    returnJson($response);
}
?> 