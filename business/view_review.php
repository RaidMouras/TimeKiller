<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$_SERVER['PHP_SELF'] = '/business/view_review.php';  
require_once '../utils/db_config.php';

$page_title = "Business Reviews";
$page_specific_css = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">';
$disable_header_redirect = true;

// Process form submission for review responses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_id'], $_POST['content'])) {
    $review_id = intval($_POST['review_id']);
    $content = trim($_POST['content']);
    $current_user_id = intval($_SESSION['user_id']);
    
    // Validate input
    if (empty($content)) {
        $_SESSION['error'] = 'Response content cannot be empty';
    } elseif ($review_id <= 0) {
        $_SESSION['error'] = 'Invalid review ID';
    } else {
        // Connect to database
        $conn = get_db_connection();
        
        // Verify the review exists and belongs to the business
        $review_sql = "SELECT r.ReviewID, r.ListingID, l.UserID as BusinessID 
                    FROM Review r
                    JOIN Listing l ON r.ListingID = l.ListingID
                    WHERE r.ReviewID = ?";
        $review_stmt = $conn->prepare($review_sql);
        $review_stmt->bind_param("i", $review_id);
        $review_stmt->execute();
        $review_result = $review_stmt->get_result();
        
        if ($review_result->num_rows === 0) {
            $_SESSION['error'] = 'Review not found';
        } else {
            // Get business ID and review ID
            $review_data = $review_result->fetch_assoc();
            
            // Verify the current user is the owner of the business associated with the review
            if ($_SESSION['user_type'] === 'Business' && $review_data['BusinessID'] != $current_user_id) {
                $_SESSION['error'] = 'You can only respond to reviews for your own business';
            } else {
                // Insert response
                $insert_sql = "INSERT INTO Review_Response (ReviewID, SenderID, Content, Time_sent) 
                            VALUES (?, ?, ?, NOW())";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iis", $review_id, $current_user_id, $content);
                
                if ($insert_stmt->execute()) {
                    $_SESSION['success'] = 'Your response has been submitted successfully';
                } else {
                    $_SESSION['error'] = 'An error occurred while submitting your response: ' . $conn->error;
                }
                
                $insert_stmt->close();
            }
        }
        
        $review_stmt->close();
        $conn->close();
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

include_once '../utils/header.php';

// Get session messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success']);
unset($_SESSION['error']);

// Get business reviews
function getBusinessReviews($businessID) {
    $conn = get_db_connection();
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Query to get all reviews for business listings
    $query = "SELECT r.ReviewID, r.Star_Rating, r.Body, r.Created_At, r.Likes, 
                    c.Username as CustomerName, l.Listing_Name, l.Deleted
              FROM Review r
              JOIN Customers c ON r.UserID = c.UserID
              JOIN Listing l ON r.ListingID = l.ListingID
              WHERE l.UserID = ? AND l.Deleted = 0
              ORDER BY r.Created_At DESC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $businessID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Get responses for each review
        $row['responses'] = getReviewResponses($conn, $row['ReviewID']);
        $data[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    return $data;
}

// Get responses for a specific review
function getReviewResponses($conn, $reviewID) {
    $query = "SELECT rr.ReplyID, rr.Content, rr.Time_sent, rr.SenderID, 
                    CASE 
                        WHEN u.User_Type = 'Business' THEN b.Business_Name
                        ELSE c.Username
                    END as ResponderName,
                    u.User_Type
              FROM Review_Response rr
              JOIN Users u ON rr.SenderID = u.UserID
              LEFT JOIN Business b ON u.UserID = b.UserID AND u.User_Type = 'Business'
              LEFT JOIN Customers c ON u.UserID = c.UserID AND u.User_Type = 'Customer'
              WHERE rr.ReviewID = ?
              ORDER BY rr.Time_sent ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $reviewID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $responses = [];
    while ($row = $result->fetch_assoc()) {
        $responses[] = $row;
    }
    
    $stmt->close();
    return $responses;
}

$reviews = getBusinessReviews($_SESSION["user_id"]);
?>

<script>
    document.body.style.backgroundColor = "rgb(221, 239, 240)";
    
    document.addEventListener("DOMContentLoaded", function() {
        var profilePic = document.getElementById("nav-profile-pic");
        if (profilePic) {
            profilePic.setAttribute("src", "../api/icon_handler.php?action=get");
        }
        
        var logo = document.getElementById("nav-center-logo");
        if (logo) {
            logo.setAttribute("src", "../assets/mask_1.png");
        }
        
        // Fix relative paths
        var navLinks = document.querySelectorAll(".navbar a:not([href='#'])");
        navLinks.forEach(function(link) {
            var href = link.getAttribute("href");
            if (href && !href.startsWith("../") && !href.startsWith("http") && !href.startsWith("#")) {
                link.setAttribute("href", "../" + href);
            }
        });
        
        var dropdownItems = document.querySelectorAll(".dropdown-item");
        dropdownItems.forEach(function(item) {
            var href = item.getAttribute("href");
            if (href && !href.startsWith("../") && !href.startsWith("http")) {
                item.setAttribute("href", "../" + href);
            }
        });
        
        // Show More / Show Less functionality
        function initializeShowMoreLess() {
            const reviewsContainer = document.querySelector('.reviews-container');
            if (!reviewsContainer) return;
            
            const reviewCards = reviewsContainer.querySelectorAll('.review-card');
            const initialDisplay = 5; 
            
            if (reviewCards.length > initialDisplay) {
                for (let i = initialDisplay; i < reviewCards.length; i++) {
                    reviewCards[i].classList.add('d-none');
                }
                
                const buttonContainer = document.createElement('div');
                buttonContainer.className = 'text-center mt-3';
                
                const showMoreBtn = document.createElement('button');
                showMoreBtn.className = 'btn btn-outline-primary';
                showMoreBtn.textContent = 'Show More (' + (reviewCards.length - initialDisplay) + ' more)';
                showMoreBtn.id = 'show-more-reviews';
                buttonContainer.appendChild(showMoreBtn);
                
                reviewsContainer.appendChild(buttonContainer);
                
                showMoreBtn.addEventListener('click', function() {
                    if (showMoreBtn.textContent.includes('Show More')) {
                        reviewCards.forEach(card => card.classList.remove('d-none'));
                        showMoreBtn.textContent = 'Show Less';
                    } else {
                        for (let i = initialDisplay; i < reviewCards.length; i++) {
                            reviewCards[i].classList.add('d-none');
                        }
                        showMoreBtn.textContent = 'Show More (' + (reviewCards.length - initialDisplay) + ' more)';
                    }
                });
            }
        }
        
        // Fix buttons display
        function fixButtonDisplay() {
            document.querySelectorAll('.btn').forEach(button => {
                if (!button.classList.contains('btn-close')) {
                    button.style.whiteSpace = 'normal';
                    button.style.display = 'inline-flex';
                    button.style.alignItems = 'center';
                    button.style.justifyContent = 'center';
                    button.style.minHeight = '38px';
                }
            });
            
            document.querySelectorAll('.btn-sm').forEach(button => {
                button.style.minHeight = '31px';
            });
        }
        
        initializeShowMoreLess();
        setTimeout(fixButtonDisplay, 100);
    });
</script>

<style>
    .review-card {
        margin-bottom: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .review-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .star-rating {
        color: #ffc107;
    }
    .review-metadata {
        color: #6c757d;
        font-size: 0.9rem;
    }
    .review-body {
        margin-top: 10px;
    }
    .response-card {
        margin-left: 30px;
        margin-top: 10px;
        border-left: 3px solid #e0e0e0;
        padding-left: 15px;
    }
    .business-response {
        background-color: #f8f9fa;
    }
    .your-response {
        border-left: 3px solid #0d6efd;
        background-color: #f0f7ff;
    }
    #show-more-reviews {
        transition: all 0.3s ease;
        white-space: normal;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    #show-more-reviews:hover {
        background-color: #0d6efd;
        color: white;
    }
    /* Ensure buttons display properly */
    .btn {
        white-space: normal;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
    }
    .btn-sm {
        min-height: 31px;
    }
</style>

<div class="container mt-4">
    <form action="../views/businesshub.php">
        <button type="submit" class="btn btn-outline-primary mb-4">
            <i class="bi bi-arrow-left"></i> Back
        </button>
    </form>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2>Business Reviews</h2>
        </div>
        <div class="card-body">
            <?php if (empty($reviews)): ?>
                <div class="alert alert-info">
                    No reviews have been received yet.
                </div>
            <?php else: ?>
                <div class="reviews-container">
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-card card">
                            <div class="card-body">
                                <div class="review-header">
                                    <h5 class="card-title">
                                        <?php echo htmlspecialchars($review['Listing_Name'] ?? ''); ?>
                                    </h5>
                                    <div class="star-rating">
                                        <?php
                                        for ($i = 0; $i < intval($review['Star_Rating']); $i++) {
                                            echo '<i class="bi bi-star-fill"></i>';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="review-metadata">
                                    <span>By <?php echo htmlspecialchars($review['CustomerName'] ?? ''); ?></span>
                                    <span> • <?php echo date('F j, Y', strtotime($review['Created_At'] ?? 'now')); ?></span>
                                    <?php if (isset($review['Likes']) && $review['Likes'] > 0): ?>
                                        <span> • <i class="bi bi-hand-thumbs-up"></i> <?php echo $review['Likes']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="review-body">
                                    <p><?php echo htmlspecialchars($review['Body'] ?? ''); ?></p>
                                </div>
                                
                                <?php if (!empty($review['responses'])): ?>
                                    <?php 
                                    // Separate business owner responses from other responses
                                    $businessResponses = array_filter($review['responses'], function($resp) {
                                        return $resp['SenderID'] == $_SESSION['user_id'];
                                    });
                                    
                                    $otherResponses = array_filter($review['responses'], function($resp) {
                                        return $resp['SenderID'] != $_SESSION['user_id'];
                                    });
                                    ?>
                                    
                                    <!-- Display business owner responses automatically -->
                                    <?php if (!empty($businessResponses)): ?>
                                        <div class="mt-3">
                                            <div class="responses-container">
                                                <h6>Responses:</h6>
                                                <?php foreach ($businessResponses as $response): ?>
                                                    <div class="response-card your-response">
                                                        <div class="response-metadata">
                                                            <strong><?php echo htmlspecialchars($response['ResponderName'] ?? ''); ?> (owner)</strong>
                                                            <span class="text-muted"> • <?php echo date('F j, Y', strtotime($response['Time_sent'] ?? 'now')); ?></span>
                                                        </div>
                                                        <div class="response-content mt-1">
                                                            <?php echo htmlspecialchars($response['Content'] ?? ''); ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Other users' responses (hidden behind Show button) -->
                                    <?php if (!empty($otherResponses)): ?>
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" 
                                                    data-bs-toggle="collapse" 
                                                    data-bs-target="#responses-<?php echo $review['ReviewID']; ?>" 
                                                    aria-expanded="false">
                                                Show Responses (<?php echo count($otherResponses); ?>)
                                            </button>
                                            <div class="collapse mt-2" id="responses-<?php echo $review['ReviewID']; ?>">
                                                <div class="responses-container">
                                                    
                                                    <?php foreach ($otherResponses as $response): ?>
                                                        <div class="response-card <?php echo $response['User_Type'] === 'Business' ? 'business-response' : ''; ?>">
                                                            <div class="response-metadata">
                                                                <strong><?php echo htmlspecialchars($response['ResponderName'] ?? ''); ?></strong>
                                                                <span class="text-muted"> • <?php echo date('F j, Y', strtotime($response['Time_sent'] ?? 'now')); ?></span>
                                                            </div>
                                                            <div class="response-content mt-1">
                                                                <?php echo htmlspecialchars($response['Content'] ?? ''); ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-primary" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#reply-form-<?php echo $review['ReviewID']; ?>"
                                            aria-expanded="false">
                                        Reply
                                    </button>
                                </div>
                                
                                <!-- Reply Form -->
                                <div class="collapse mt-3" id="reply-form-<?php echo $review['ReviewID']; ?>">
                                    <div class="card card-body bg-light">
                                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                                            <input type="hidden" name="review_id" value="<?php echo $review['ReviewID']; ?>">
                                            <div class="mb-3">
                                                <label for="response-<?php echo $review['ReviewID']; ?>" class="form-label">Your Response:</label>
                                                <textarea class="form-control" id="response-<?php echo $review['ReviewID']; ?>" 
                                                          name="content" rows="3" required></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Submit Response</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include_once '../utils/footer.php';
?> 