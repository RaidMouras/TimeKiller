<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit;
}

require '../controllers/businesshub_controller.php';
getBusinessInfo($_SESSION['user_id']);

$page_title = "Business Hub";

include_once '../utils/header.php';
?>

<style>
    :root {
        --primary: #2c83c3;
        --primary-light: #e1f0fa;
        --secondary: #6c757d;
        --success: #28a745;
        --light: #f8f9fa;
        --dark: #343a40;
        --border-radius: 0.375rem;
        --box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
    }
    
    body {
        background-color: rgb(221, 239, 240);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        color: #495057;
        line-height: 1.6;
    }
    
    .dashboard-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 0 1.5rem;
    }
    
    .welcome-header {
        color: var(--dark);
        font-weight: 600;
        margin-bottom: 2rem;
        font-size: 1.8rem;
        position: relative;
        padding-bottom: 0.5rem;
    }
    
    .welcome-header:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 60px;
        height: 4px;
        background: var(--primary);
        border-radius: 2px;
    }
    
    .card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        transition: all 0.3s ease;
        overflow: hidden;
        background: white;
        margin-bottom: 1.5rem;
    }
    
    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.1);
    }
    
    .card-header {
        background: white;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        padding: 1.25rem 1.5rem;
        font-weight: 600;
        color: var(--dark);
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    .stat-card {
        height: 100%;
        border-left: 4px solid var(--primary);
    }
    
    .stat-card.clickable {
        transition: all 0.2s ease;
    }
    
    .stat-card.clickable:hover {
        background-color: var(--primary-light);
        border-left-color: var(--primary);
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin: 0.5rem 0;
    }
    
    .stat-label {
        color: var(--secondary);
        font-weight: 500;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .star-rating {
        font-size: 1.5rem;
        letter-spacing: 2px;
        margin: 0.5rem 0;
    }
    
    .star-filled {
        color: #ffc107;
    }
    
    .star-empty {
        color: #e9ecef;
    }
    
    .btn-primary {
        background-color: var(--primary);
        border-color: var(--primary);
        padding: 0.5rem 1.25rem;
        font-weight: 500;
        border-radius: var(--border-radius);
    }
    
    .btn-outline-primary {
        color: var(--primary);
        border-color: var(--primary);
    }
    
    .btn-outline-primary:hover {
        background-color: var(--primary);
        color: white;
    }
    
    .section-title {
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 1.5rem;
        font-size: 1.25rem;
    }
    
    .action-card {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 120px;
        background: white;
        border-radius: var(--border-radius);
        transition: all 0.3s ease;
    }
    
    .action-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }
    
    a.card-link {
        text-decoration: none;
        color: inherit;
    }
    
    a.card-link:hover {
        text-decoration: none;
    }
    
    .divider {
        height: 1px;
        background: rgba(0, 0, 0, 0.1);
        margin: 2rem 0;
    }
</style>

<body>
<div class="dashboard-container">
    <div class="welcome-header">
        <?php echo "Welcome back, " . $_SESSION["business_name"] ?>
    </div>
    
    <!-- Overview Section -->
    <div class="card">
        <div class="card-header">
            Business Overview
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Rating Card -->
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="stat-card">
                        <div class="card-body text-center">
                            <div class="stat-label">Business Rating</div>
                            <div class="star-rating">
                                <?php 
                                $rating = getBusinessReviewScore($_SESSION["user_id"]);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $rating ? '<span class="star-filled">★</span>' : '<span class="star-empty">★</span>';
                                }
                                ?>
                            </div>
                            <div class="stat-value"><?php echo $rating ?>/5</div>
                        </div>
                    </div>
                </div>
                
                <!-- Services Sold Card -->
                <div class="col-md-6 col-lg-3 mb-4">
                    <a href="../business/service_history.php" class="card-link">
                        <div class="stat-card clickable">
                            <div class="card-body text-center">
                                <div class="stat-label">Services Sold</div>
                                <div class="stat-value"><?php echo getServicesSold($_SESSION["user_id"]) ?></div>
                                <small class="text-muted">View history</small>
                            </div>
                        </div>
                    </a>
                </div>
                
                <!-- Total Reviews Card -->
                <div class="col-md-6 col-lg-3 mb-4">
                    <a href="../business/view_review.php" class="card-link">
                        <div class="stat-card clickable">
                            <div class="card-body text-center">
                                <div class="stat-label">Total Reviews</div>
                                <div class="stat-value"><?php echo getTotalReviews($_SESSION["user_id"]) ?></div>
                                <small class="text-muted">View all reviews</small>
                            </div>
                        </div>
                    </a>
                </div>
                
                <!-- Profit Card -->
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="stat-card">
                        <div class="card-body text-center">
                            <div class="stat-label">Total Profit</div>
                            <div class="stat-value"><?php echo getProfit($_SESSION["user_id"]) ?></div>
                            <small class="text-muted">Lifetime earnings</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="divider"></div>
    
    <!-- Listings Section -->
    <div class="section-title">Manage Your Listings</div>
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="action-card">
                <a href="../views/mylistings.php" class="btn btn-primary btn-lg">View All Listings</a>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="action-card">
                <form action="./newlisting.php">
                    <input type="submit" class="btn btn-outline-primary btn-lg" value="Add New Listing">
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include_once '../utils/footer.php';
?>