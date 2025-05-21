<?php
require_once '../utils/db_config.php';
session_start();

$response = [
    'success' => false,
    'message' => '',
    'user' => null
];

// Validate user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not authenticated';
    returnJson($response);
    exit;
}

// Get user ID from session
$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'get_profile';
    
    if ($action === 'get_profile') {
        getProfileFromDatabase($user_id, $response);
    } else {
        $response['message'] = 'Invalid action for GET request';
        returnJson($response);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'update') {
        updateProfile($user_id, $response);
    } else {
        $response['message'] = 'Invalid action for POST request';
        returnJson($response);
    }
} else {
    $response['message'] = 'Unsupported request method';
    returnJson($response);
}

/**
 * Get user profile from database
 */
function getProfileFromDatabase($user_id, &$response) {
    $conn = get_db_connection();
    
    // Get basic user info
    $stmt = $conn->prepare("
        SELECT u.Email, u.User_Type
        FROM Users u
        WHERE u.UserID = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $response['message'] = 'User not found';
        returnJson($response);
        exit;
    }
    
    $basic_user = $result->fetch_assoc();
    $user_type = $basic_user['User_Type'];
    
    // Get specific user details based on user type
    if ($user_type === 'Customer') {
        $stmt = $conn->prepare("
            SELECT c.Username, c.Profile_Picture, c.Bio, c.Verified
            FROM Customers c
            WHERE c.UserID = ?
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT b.Business_Name as Username, b.Profile_Picture, b.Bio, b.Location, b.Business_Type
            FROM Business b
            WHERE b.UserID = ?
        ");
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $specific_user = $result->fetch_assoc();
    
    // Combine user data
    $user_data = array_merge($basic_user, $specific_user);
    
    // Format profile picture path
    if (!empty($user_data['Profile_Picture'])) {
        if (strpos($user_data['Profile_Picture'], 'uploads/') !== 0) {
            $user_data['Profile_Picture'] = 'uploads/' . $user_data['Profile_Picture'];
        }
    } else {
        $user_data['Profile_Picture'] = 'uploads/default/init_icon.png';
    }
    
    // Create response
    $response['success'] = true;
    $response['user'] = [
        'username' => $user_data['Username'],
        'email' => $user_data['Email'],
        'user_type' => $user_data['User_Type'],
        'profile_picture' => $user_data['Profile_Picture'],
        'bio' => $user_data['Bio'],
    ];
    
    // Add specific fields
    if ($user_type === 'Business') {
        $response['user']['location'] = $user_data['Location'];
        $response['user']['business_type'] = $user_data['Business_Type'];
    }
    
    if ($user_type === 'Customer') {
        $response['user']['verified'] = (bool)$user_data['Verified'];
    }
    
    $conn->close();
    returnJson($response);
}

/**
 * Update user profile
 */
function updateProfile($user_id, &$response) {
    $conn = get_db_connection();
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    
    // Get user type from database
    $stmt = $conn->prepare("SELECT User_Type FROM Users WHERE UserID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $response['message'] = 'User not found';
        returnJson($response);
        return;
    }
    
    $user = $result->fetch_assoc();
    $user_type = $user['User_Type'];
    
    // Handle profile picture upload - redirect to icon_handler.php
    if ($field === 'profile_picture' && isset($_FILES['file'])) {
        // Close db connection as we're redirecting to another script
        $conn->close();
        
        // Call icon_handler.php directly for processing
        require_once 'icon_handler.php';
        // The icon_handler.php script will handle the rest and exit
        
        // This point should never be reached as icon_handler.php will exit
        exit;
    } 
    // Handle password update
    else if ($field === 'password') {
        // Get passwords from POST or REQUEST
        $current_password = $_POST['current_password'] ?? $_REQUEST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? $_REQUEST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? $_REQUEST['confirm_password'] ?? '';
        
        // Validate all required fields
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $response['success'] = false;
            $response['message'] = 'All password fields are required';
            returnJson($response);
            return;
        }
        
        // Validate that new password and confirmation match
        if ($new_password !== $confirm_password) {
            $response['success'] = false;
            $response['message'] = 'New password and confirmation do not match';
            returnJson($response);
            return;
        }
        
        // Validate password length and complexity
        if (strlen($new_password) < 8) {
            $response['success'] = false;
            $response['message'] = 'New password must be at least 8 characters long';
            returnJson($response);
            return;
        }
        
        // Validate current password
        $stmt = $conn->prepare("SELECT Password FROM Users WHERE UserID = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        
        if (!password_verify($current_password, $user_data['Password'])) {
            $response['success'] = false;
            $response['message'] = 'Current password is incorrect';
            returnJson($response);
            return;
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE Users SET Password = ? WHERE UserID = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Password updated successfully';
        } else {
            $response['success'] = false;
            $response['message'] = 'Password update failed: ' . $conn->error;
        }
        
        returnJson($response);
        return;
    }
    // Handle text fields
    else if (isset($_POST['value'])) {
        $value = trim($_POST['value']);
        
        // Handle email update
        if ($field === 'email') {
            // Check if email is already used by another user
            $stmt = $conn->prepare("SELECT UserID FROM Users WHERE Email = ? AND UserID != ?");
            $stmt->bind_param("si", $value, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $response['success'] = false;
                $response['message'] = 'This email is already in use, please try another one';
                returnJson($response);
                return;
            }
            
            $stmt = $conn->prepare("UPDATE Users SET Email = ? WHERE UserID = ?");
            $stmt->bind_param("si", $value, $user_id);
            $stmt->execute();
            $_SESSION['email'] = $value;
        } 
        // Handle username update
        else if ($field === 'username') {
            // Check if username is already used by another user
            if ($user_type === 'Customer') {
                $stmt = $conn->prepare("SELECT UserID FROM Customers WHERE Username = ? AND UserID != ?");
            } else {
                $stmt = $conn->prepare("SELECT UserID FROM Business WHERE Business_Name = ? AND UserID != ?");
            }
            $stmt->bind_param("si", $value, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $response['success'] = false;
                $response['message'] = 'This username is already in use, please try another one';
                returnJson($response);
                return;
            }
            
            if ($user_type === 'Customer') {
                $stmt = $conn->prepare("UPDATE Customers SET Username = ? WHERE UserID = ?");
            } else {
                $stmt = $conn->prepare("UPDATE Business SET Business_Name = ? WHERE UserID = ?");
            }
            $stmt->bind_param("si", $value, $user_id);
            $stmt->execute();
            $_SESSION['username'] = $value;
        } 
        // Handle bio update
        else if ($field === 'bio') {
            $table = ($user_type === 'Customer') ? 'Customers' : 'Business';
            $stmt = $conn->prepare("UPDATE $table SET Bio = ? WHERE UserID = ?");
            $stmt->bind_param("si", $value, $user_id);
            $stmt->execute();
            $_SESSION['bio'] = $value;
        }
        
        $response['success'] = true;
        $response['message'] = 'Updated successfully';
        $response['updated_field'] = $field;
        $response['new_value'] = $value;
    } else {
        $response['message'] = 'Missing required data';
    }
    
    $conn->close();
    returnJson($response);
}

function returnJson($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?> 