<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Basic meta tags for character set, viewport, and cache control -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <!-- Dynamic page title, falls back to 'Time Killer' if not set -->
    <title><?php echo $page_title ?? 'Time Killer'; ?></title>
    <!-- Bootstrap CSS from CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Header styles -->
<style>
    /* CSS variables for consistent styling and easy maintenance */
    :root {
        --nav-padding: 1rem 1.2rem;
        --nav-margin-bottom: 1.5rem;
        --nav-brand-size: 1.5rem;
        --nav-icon-size: 40px;
        --nav-icon-border: 2px solid #f8f9fa;
        --nav-username-size: 1.1rem;
        --nav-usertype-size: 0.9rem;
        --nav-logo-height: 40px;
        --nav-logo-max-width: 120px;
    }

    /* Navigation bar styles */
    .navbar {
        box-shadow: 0 3px 6px rgba(0,0,0,0.12);
        padding: var(--nav-padding);
        margin-bottom: var(--nav-margin-bottom);
    }

    /* Brand/site name styling */
    .navbar-brand {
        font-weight: bold;
        color: #212529;
        font-size: var(--nav-brand-size);
        letter-spacing: 0.5px;
    }

    /* Navigation bar user icon styles */
    #nav-profile-pic {
        width: var(--nav-icon-size);
        height: var(--nav-icon-size);
        object-fit: cover;
        border: var(--nav-icon-border);
    }

    /* User profile picture hover and focus states */
    #nav-profile-pic:hover,
    #nav-profile-pic:focus,
    #nav-profile-pic:active {
        border-color: #f8f9fa;
        outline: none;
        box-shadow: none;
    }

    /* User information container adjustment */
    .navbar .container {
        position: relative;
        max-width: 1200px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 15px;
    }

    /* Center logo positioning */
    .navbar-center {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Logo image sizing and display */
    .navbar-logo {
        height: var(--nav-logo-height);
        max-width: var(--nav-logo-max-width);
        object-fit: contain;
        transition: all 0.3s ease;
    }

    /* User profile link positioning */
    .navbar a[href="profile.php"] {
        position: relative;
    }

    /* User information text size */
    .navbar .username {
        font-size: var(--nav-username-size);
        font-weight: 500;
        margin-bottom: 0.1rem;
    }

    /* User type label styling */
    .navbar .user-type {
        font-size: var(--nav-usertype-size);
        opacity: 0.8;
    }
    
    /* Remove focus outline */
    #userDropdown:focus {
        box-shadow: none !important;
        outline: none !important;
    }
    
    /* Remove dropdown arrow */
    .dropdown-toggle::after {
        display: none !important;
    }
    
    /* Remove blue outline on focus */
    .dropdown-item:focus, 
    .dropdown-item:active {
        background-color: #f8f9fa !important;
        color: #212529 !important;
        outline: none !important;
        box-shadow: none !important;
    }

    /* Responsive styles */
    @media (max-width: 992px) {
        :root {
            --nav-brand-size: 1.3rem;
            --nav-icon-size: 35px;
            --nav-username-size: 1rem;
            --nav-usertype-size: 0.8rem;
            --nav-logo-height: 35px;
            --nav-logo-max-width: 100px;
        }
        
        .navbar .container {
            padding: 0 10px;
        }
    }

    @media (max-width: 768px) {
        :root {
            --nav-brand-size: 1.1rem;
            --nav-icon-size: 30px;
            --nav-username-size: 0.9rem;
            --nav-usertype-size: 0.7rem;
            --nav-logo-height: 30px;
            --nav-logo-max-width: 80px;
        }
        
        .navbar .container {
            padding: 0 5px;
        }
    }

    @media (max-width: 576px) {
        :root {
            --nav-brand-size: 1rem;
            --nav-icon-size: 25px;
            --nav-username-size: 0.8rem;
            --nav-usertype-size: 0.6rem;
            --nav-logo-height: 25px;
            --nav-logo-max-width: 60px;
        }
        
        .navbar .container {
            padding: 0 5px;
        }
    }
</style>
    <!-- Page Specific CSS - dynamically loads CSS based on current page -->
    <?php
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
    if (file_exists("css/pages/{$current_page}.css")) {
        echo "<link href=\"css/pages/{$current_page}.css?v=" . time() . "\" rel=\"stylesheet\">";
    }
    
    // Include any additional page-specific CSS defined elsewhere
    echo $page_specific_css ?? '';
    ?>
</head>
<body data-current-user-id="<?php echo isset($_SESSION['user_id']) ? htmlspecialchars($_SESSION['user_id']) : ''; ?>">
    <?php
    // Check session status and initialize default values if needed
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    // Check if user is logged in, if not redirect to login page
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }
    
    // Ensure we have a username for display
    $username = $_SESSION['username'] ?? '';
    $user_type = $_SESSION['user_type'] ?? '';
    
    // If username is not set but we have a user_id, fetch it from the database
    if (empty($username) && isset($_SESSION['user_id'])) {
        $db_config_path = (strpos($_SERVER['PHP_SELF'], '/views/') !== false) ? '../utils/db_config.php' : 'utils/db_config.php';
        require_once $db_config_path;
        $conn = get_db_connection();
        
        if ($user_type === 'Business') {
            // Get business name
            $stmt = $conn->prepare("SELECT Business_Name FROM Business WHERE UserID = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $username = $row['Business_Name'];
                $_SESSION['username'] = $username; // Save it in session for future use
                $_SESSION['business_name'] = $username;
            }
            $stmt->close();
        } else {
            // Get customer username
            $stmt = $conn->prepare("SELECT Username FROM Customers WHERE UserID = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $username = $row['Username'];
                $_SESSION['username'] = $username; // Save it in session for future use
            }
            $stmt->close();
        }
        $conn->close();
    }
    ?>
    
    <!-- Main navigation bar -->
    <nav class="navbar navbar-light bg-white">
        <div class="container">
            <!-- Site logo/name link -->
            <a class="navbar-brand" href="<?php 
                $base_path = (strpos($_SERVER['PHP_SELF'], '/views/') !== false) ? '../' : '';
                echo $base_path . ($user_type === 'Business' ? 'views/businesshub.php' : 'home.php'); 
            ?>">Time Killer</a>
            <!-- Center logo -->
            <div class="navbar-center">
                <a href="<?php 
                    $base_path = (strpos($_SERVER['PHP_SELF'], '/views/') !== false) ? '../' : '';
                    echo $base_path . ($user_type === 'Business' ? 'views/businesshub.php' : 'home.php'); 
                ?>">
                    <img src="<?php echo (strpos($_SERVER['PHP_SELF'], '/views/') !== false) ? '../assets/mask_1.png' : 'assets/mask_1.png'; ?>" alt="Logo" class="navbar-logo" id="nav-center-logo">
                </a>
            </div>
            
            <!-- User profile section with dropdown menu -->
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="color: inherit; outline: none;">
                        <!-- User profile picture -->
                        <img src="<?php echo (strpos($_SERVER['PHP_SELF'], '/views/') !== false) ? '../api/icon_handler.php?action=get&nc=' . time() : 'api/icon_handler.php?action=get&nc=' . time(); ?>" 
                             alt="User" 
                             class="rounded-circle me-2" 
                             id="nav-profile-pic">
                        <!-- User information container -->
                        <div>
                            <span class="username d-block"><?php echo htmlspecialchars($username); ?></span>
                            <small class="text-muted user-type"><?php echo htmlspecialchars($user_type); ?></small>
                        </div>
                    </a>
                    <!-- Dropdown menu -->
                    <ul class="dropdown-menu" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/views/') !== false) ? '../profile.php' : 'profile.php'; ?>">Profile</a></li>
                        <?php if($user_type != 'Business'): ?>
                        <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/views/') !== false) ? '../history.php' : 'history.php'; ?>">History</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/views/') !== false) ? '../message.php' : 'message.php'; ?>">Messages</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/views/') !== false) ? '../api/logout.php' : 'api/logout.php'; ?>">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

<!-- JavaScript function to force refresh the navigation bar profile icon -->
<script>
function forceRefreshNavIcon() {
    var iconPath = (window.location.pathname.indexOf('/views/') !== -1) ? 
        '../api/icon_handler.php?action=get&session_only=1&nc=' : 
        'api/icon_handler.php?action=get&session_only=1&nc=';
    document.getElementById('nav-profile-pic').src = iconPath + new Date().getTime();
}

// Check if we're on the home page
var isHomePage = window.location.pathname.endsWith('/home.php') || 
                 window.location.pathname.endsWith('/');

// Ensure dropdown-toggle class is present on home page
if (isHomePage) {
    document.addEventListener('DOMContentLoaded', function() {
        var userDropdown = document.getElementById('userDropdown');
        if (userDropdown && !userDropdown.classList.contains('dropdown-toggle')) {
            userDropdown.classList.add('dropdown-toggle');
        }
    });
}
</script>