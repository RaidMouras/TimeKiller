<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: index.php");
    exit;
}

$page_title = 'Purchase History';
// $page_specific_css = '<link href="css/pages/history.css" rel="stylesheet">';
//$page_specific_css = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">';
include 'utils/header.php';
require_once 'utils/db_config.php';
$conn = get_db_connection();

// Get user's purchase history
$user_id = $_SESSION['user_id'];

// Get purchase history from Purchase_History_Tier
$tier_query = "SELECT pht.PurchaseID, pht.Time_Of_Purchase, pht.Event_Time, 
                pt.Tier_Name, pt.Price, 
                l.ListingID, l.Listing_Name, l.Short_Desc,
                b.Business_Name, b.UserID as BusinessID
                FROM Purchase_History_Tier pht
                JOIN Price_Tiers pt ON pht.TierID = pt.TierID
                JOIN Listing l ON pt.ListingID = l.ListingID
                JOIN Business b ON l.UserID = b.UserID
                WHERE pht.UserID = ?
                ORDER BY pht.Time_Of_Purchase DESC";

$tier_stmt = $conn->prepare($tier_query);
$tier_stmt->bind_param("i", $user_id);
$tier_stmt->execute();
$tier_result = $tier_stmt->get_result();

// Get purchase history from Purchase_History_Negotiation (if exists)
$nego_query = "SELECT phn.PurchaseID, phn.Time_Of_Purchase, 
                n.Price, n.Time_Of_Event, n.Description,
                l.ListingID, l.Listing_Name, l.Short_Desc,
                b.Business_Name, b.UserID as BusinessID
                FROM Purchase_History_Negotiation phn
                JOIN Negotiations n ON phn.NegotiationID = n.NegotiationID
                JOIN Listing l ON n.ListingID = l.ListingID
                JOIN Business b ON l.UserID = b.UserID
                WHERE n.UserID = ?
                ORDER BY phn.Time_Of_Purchase DESC";

$nego_stmt = $conn->prepare($nego_query);
$nego_stmt->bind_param("i", $user_id);
$nego_stmt->execute();
$nego_result = $nego_stmt->get_result();

// Get business images for each listing
function getBusinessImage($conn, $business_id) {
    // Get the business profile image
    return "api/icon_handler.php?action=get&user_id=" . $business_id;
}

// Function to check if user has already reviewed a listing
function hasUserReviewedListing($conn, $user_id, $listing_id) {
    $check_sql = "SELECT ReviewID FROM Review WHERE ListingID = ? AND UserID = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $listing_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $has_reviewed = ($check_result->num_rows > 0);
    $check_stmt->close();
    return $has_reviewed;
}

// Function to get user review for a listing
function getUserReview($conn, $user_id, $listing_id) {
    $review_sql = "SELECT r.ReviewID, r.Star_Rating, r.Body, r.Created_At 
                   FROM Review r
                   WHERE r.ListingID = ? AND r.UserID = ?";
    $review_stmt = $conn->prepare($review_sql);
    $review_stmt->bind_param("ii", $listing_id, $user_id);
    $review_stmt->execute();
    $review_result = $review_stmt->get_result();
    $review = $review_result->fetch_assoc();
    $review_stmt->close();
    return $review;
}
?>

<!-- Main Content -->
<div class="container mb-5">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="sidebar">
                <ul class="sidebar-nav">
                    <li><a href="profile.php">My Profile</a></li>
                    <?php if($_SESSION['user_type'] != 'Business'): ?>
                    <li><a href="history.php" class="active">History</a></li>
                    <?php endif; ?>
                    <li><a href="message.php">Messages</a></li>
                </ul>
            </div>
        </div>
        
        <!-- History Content -->
        <div class="col-md-9">
            <div class="main-content">
                <h2>Purchase History</h2>
                
                <?php if ($tier_result->num_rows == 0 && $nego_result->num_rows == 0): ?>
                    <div class="alert alert-info">
                        You haven't made any purchases yet.
                    </div>
                <?php else: ?>
                    
                    <!-- Purchase Cards -->
                    <div class="purchase-cards">
                        <?php 
                        // Combine both result sets
                        $all_purchases = array();
                        
                        // Add standard purchases
                        while ($purchase = $tier_result->fetch_assoc()) {
                            $purchase['type'] = 'standard';
                            $purchase['date'] = $purchase['Event_Time'];
                            $purchase['purchase_id'] = $purchase['PurchaseID'];
                            $all_purchases[] = $purchase;
                        }
                        
                        // Add negotiated purchases
                        while ($purchase = $nego_result->fetch_assoc()) {
                            $purchase['type'] = 'negotiated';
                            $purchase['date'] = $purchase['Time_Of_Event'];
                            $purchase['purchase_id'] = $purchase['PurchaseID'];
                            $all_purchases[] = $purchase;
                        }
                        
                        // Sort by date (newest first)
                        usort($all_purchases, function($a, $b) {
                            return strtotime($b['date']) - strtotime($a['date']);
                        });
                        
                        // Display all purchases as cards
                        $total_records = count($all_purchases);
                        $visible_count = 0;
                        
                        foreach ($all_purchases as $purchase):
                            $purchase_date = date('d/m/Y', strtotime($purchase['date']));
                            $purchase_id = str_pad($purchase['purchase_id'], 7, '0', STR_PAD_LEFT);
                            $business_image = getBusinessImage($conn, $purchase['BusinessID']);
                            
                            // Determine status (for this example, we'll set all to "Complete")
                            $status = "Complete";
                            
                            // Add a class to hide records after the first two
                            $visible_count++;
                            $hidden_class = ($visible_count > 2) ? 'hidden-purchase' : '';
                        ?>
                            <div class="purchase-card <?php echo $hidden_class; ?>">
                                <div class="purchase-header">
                                    <div class="image-section">
                                        <div class="date-container" style="margin-bottom: 5px;">
                                            <div class="purchase-date"><?php echo $purchase_date; ?></div>
                                        </div>
                                        <div class="business-image">
                                            <img src="<?php echo $business_image; ?>" alt="<?php echo htmlspecialchars($purchase['Business_Name']); ?>">
                                        </div>
                                    </div>
                                    <div class="purchase-details">
                                        <h3><?php echo htmlspecialchars($purchase['Business_Name']); ?></h3>
                                        <p class="service-name"><?php echo htmlspecialchars($purchase['Listing_Name']); ?></p>
                                    </div>
                                    <div class="purchase-status">
                                        <div class="purchase-id">#<?php echo $purchase_id; ?></div>
                                        <div class="purchase-price">€<?php echo number_format($purchase['Price'], 2); ?></div>
                                        <div class="status-label">Status: <span class="<?php echo strtolower($status); ?>"><?php echo $status; ?></span></div>
                                    </div>
                                </div>
                                <div class="purchase-actions">
                                    <a href="#" class="action-btn comment-btn" data-bs-toggle="modal" data-bs-target="#commentModal<?php echo $purchase['purchase_id']; ?>">
                                        <?php 
                                        $has_reviewed = hasUserReviewedListing($conn, $user_id, $purchase['ListingID']);
                                        echo $has_reviewed ? 'View Review' : 'Comment'; 
                                        ?>
                                    </a>
                                    <a href="views/serviceDetails.php?id=<?php echo $purchase['ListingID']; ?>" class="action-btn book-again-btn">Book Again</a>
                                </div>
                            </div>

                            <!-- Comment Modal for each purchase -->
                            <div class="modal fade" id="commentModal<?php echo $purchase['purchase_id']; ?>" tabindex="-1" aria-labelledby="commentModalLabel<?php echo $purchase['purchase_id']; ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="commentModalLabel<?php echo $purchase['purchase_id']; ?>">Write a Review for <?php echo htmlspecialchars($purchase['Listing_Name']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <?php 
                                        $has_reviewed = hasUserReviewedListing($conn, $user_id, $purchase['ListingID']);
                                        if ($has_reviewed): 
                                            $review = getUserReview($conn, $user_id, $purchase['ListingID']);
                                        ?>
                                            <div class="modal-body">
                                                <h5>Your Review</h5>
                                                <div class="mb-3">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <div class="stars me-2">
                                                            <?php
                                                            for ($i = 1; $i <= 5; $i++) {
                                                                if ($i <= $review['Star_Rating']) {
                                                                    echo '<i class="fas fa-star text-warning"></i>';
                                                                } else {
                                                                    echo '<i class="far fa-star text-warning"></i>';
                                                                }
                                                            }
                                                            ?>
                                                        </div>
                                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($review['Created_At'])); ?></small>
                                                    </div>
                                                    <p><?php echo nl2br(htmlspecialchars($review['Body'])); ?></p>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <a href="views/serviceDetails.php?id=<?php echo $purchase['ListingID']; ?>" class="btn btn-primary">View Service</a>
                                            </div>
                                        <?php else: ?>
                                            <form action="controllers/submit_review.php" method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="listing_id" value="<?php echo $purchase['ListingID']; ?>">
                                                    <input type="hidden" name="purchase_id" value="<?php echo $purchase['purchase_id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Your Rating</label>
                                                        <div class="rating-input">
                                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                                                <input type="radio" id="star<?php echo $i; ?>_<?php echo $purchase['purchase_id']; ?>" name="rating" value="<?php echo $i; ?>" required>
                                                                <label for="star<?php echo $i; ?>_<?php echo $purchase['purchase_id']; ?>"><i class="far fa-star"></i></label>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="reviewText<?php echo $purchase['purchase_id']; ?>" class="form-label">Your Review</label>
                                                        <textarea class="form-control" id="reviewText<?php echo $purchase['purchase_id']; ?>" name="review_text" rows="3" required maxlength="500"></textarea>
                                                        <small class="text-muted">Maximum 500 characters</small>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Submit Review</button>
                                                </div>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ($total_records > 2): ?>
                            <div class="text-center mt-4 mb-2" id="toggle-buttons">
                                <button id="show-more-btn" class="btn btn-outline-primary">Show More</button>
                                <button id="show-less-btn" class="btn btn-outline-secondary" style="display: none;">Show Less</button>
                            </div>
                        <?php endif; ?> 
                    </div>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for handling star rating -->
<script>
    // Make star rating interactive
    document.addEventListener('DOMContentLoaded', function() {
        // Star rating functionality
        document.querySelectorAll('.rating-input input').forEach(input => {
            input.addEventListener('change', function() {
                const stars = this.parentElement.querySelectorAll('label');
                const rating = parseInt(this.value);

                stars.forEach((star, index) => {
                    if (index >= (5 - rating)) {
                        star.querySelector('i').classList.add('fas');
                        star.querySelector('i').classList.remove('far');
                    } else {
                        star.querySelector('i').classList.add('far');
                        star.querySelector('i').classList.remove('fas');
                    }
                });
            });
        });
        
        // Show More/Less functionality
        const showMoreBtn = document.getElementById('show-more-btn');
        const showLessBtn = document.getElementById('show-less-btn');
        const hiddenCards = document.querySelectorAll('.hidden-purchase');
        
        if (showMoreBtn && showLessBtn) {
            // Show More button click
            showMoreBtn.addEventListener('click', function() {
                // Show each hidden card with a slight delay for animation effect
                hiddenCards.forEach((card, index) => {
                    setTimeout(() => {
                        card.classList.add('show');
                    }, index * 100); // 100ms delay between each card
                });
                
                // Toggle buttons
                showMoreBtn.style.display = 'none';
                showLessBtn.style.display = 'inline-block';
            });
            
            // Show Less button click
            showLessBtn.addEventListener('click', function() {
                // Hide each card
                hiddenCards.forEach((card) => {
                    card.classList.remove('show');
                });
                
                // Scroll back to top of the purchase cards if needed
                const purchaseCards = document.querySelector('.purchase-cards');
                if (purchaseCards) {
                    window.scrollTo({
                        top: purchaseCards.offsetTop - 50,
                        behavior: 'smooth'
                    });
                }
                
                // Toggle buttons
                showLessBtn.style.display = 'none';
                showMoreBtn.style.display = 'inline-block';
            });
        }
    });
</script>

<?php
$tier_stmt->close();
$nego_stmt->close();
$conn->close();
include 'utils/footer.php';
?> 