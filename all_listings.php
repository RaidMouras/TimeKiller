<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'utils/db_config.php';

$page_title = 'All Listings';
$page_specific_css = '<link rel="stylesheet" href="css/pages/all_listings.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">';

include 'utils/header.php';

// Check if business ID is provided
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$business_id = intval($_GET['id']);
$conn = get_db_connection();

// Get business information
$business_sql = "SELECT b.*, u.Email 
                FROM Business b 
                JOIN Users u ON b.UserID = u.UserID 
                WHERE b.UserID = ?";
$business_stmt = $conn->prepare($business_sql);
$business_stmt->bind_param("i", $business_id);
$business_stmt->execute();
$business_result = $business_stmt->get_result();

if ($business_result->num_rows === 0) {
    echo '<div class="container mt-5"><div class="alert alert-danger">Business not found.</div></div>';
    include 'utils/footer.php';
    exit;
}

$business = $business_result->fetch_assoc();

// Get all listings for this business
$listings_sql = "SELECT l.*, 
                (SELECT COUNT(*) FROM Review r WHERE r.ListingID = l.ListingID) as review_count,
                (SELECT AVG(Star_Rating) FROM Review r WHERE r.ListingID = l.ListingID) as avg_rating
                FROM Listing l 
                WHERE l.UserID = ? AND l.Deleted = 0
                ORDER BY l.ListingID DESC";
$listings_stmt = $conn->prepare($listings_sql);
$listings_stmt->bind_param("i", $business_id);
$listings_stmt->execute();
$listings_result = $listings_stmt->get_result();
?>

<div class="container mt-5">
    <!-- Business Profile Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="business-profile-header bg-light p-4 rounded">
                <div class="d-flex align-items-center">
                    <img src="api/icon_handler.php?action=get&user_id=<?php echo $business_id; ?>" 
                         class="rounded-circle me-4" style="width: 100px; height: 100px;" alt="Business Profile">
                    <div>
                        <h1 class="mb-2"><?php echo htmlspecialchars($business['Business_Name'] ?? ''); ?></h1>
                        <p class="text-muted mb-0">
                            <i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($business['Location'] ?? ''); ?>
                        </p>
                        <?php if (!empty($business['Contact_Info'])): ?>
                        <p class="text-muted mb-0">
                            <i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($business['Contact_Info']); ?>
                        </p>
                        <?php endif; ?>
                        <p class="text-muted mb-0">
                            <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($business['Email'] ?? ''); ?>
                        </p>
                        <p class="mt-2">
                            <?php echo !empty($business['Bio']) ? nl2br(htmlspecialchars($business['Bio'])) : 'No business description available.'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Listings Section -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h2>All Listings (<?php echo $listings_result->num_rows; ?>)</h2>
            <hr>
        </div>
    </div>
    
    <?php if ($listings_result->num_rows > 0): ?>
        <div class="row">
            <?php while ($listing = $listings_result->fetch_assoc()): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 listing-card">
                        <?php
                        // Get the first image for this listing
                        $image_sql = "SELECT Picture FROM Listing_Pictures WHERE ListingID = ? LIMIT 1";
                        $image_stmt = $conn->prepare($image_sql);
                        $image_stmt->bind_param("i", $listing['ListingID']);
                        $image_stmt->execute();
                        $image_result = $image_stmt->get_result();
                        $image_url = $image_result->num_rows > 0 
                            ? $image_result->fetch_assoc()['Picture'] 
                            : 'https://fakeimg.pl/400x300/?text=No+Image';
                        ?>
                        <img src="<?php echo htmlspecialchars($image_url ?? ''); ?>" class="card-img-top listing-image" alt="<?php echo htmlspecialchars($listing['Listing_Name'] ?? ''); ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($listing['Listing_Name'] ?? ''); ?></h5>
                            <p class="card-text text-truncate"><?php echo htmlspecialchars($listing['Short_Desc'] ?? ''); ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="rating">
                                    <?php
                                    $rating = round($listing['avg_rating'] ?? 0, 1);
                                    $full_stars = floor($rating);
                                    $half_star = ($rating - $full_stars) >= 0.5;
                                    
                                    // Star rating display
                                    echo '<div class="stars me-1">';
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $full_stars) {
                                            echo '<i class="fas fa-star text-warning"></i>';
                                        } elseif ($half_star && $i == $full_stars + 1) {
                                            echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                        } else {
                                            echo '<i class="far fa-star text-warning"></i>';
                                        }
                                    }
                                    echo '</div>';
                                    ?>
                                    <div>
                                        <span class="rating-number"><?php echo number_format($rating, 1); ?></span>
                                        <span class="text-muted">(<?php echo $listing['review_count']; ?>)</span>
                                    </div>
                                </div>
                                
                                <?php
                                // Get the lowest price for this listing
                                $price_sql = "SELECT MIN(Price) as min_price FROM Price_Tiers WHERE ListingID = ?";
                                $price_stmt = $conn->prepare($price_sql);
                                $price_stmt->bind_param("i", $listing['ListingID']);
                                $price_stmt->execute();
                                $price_result = $price_stmt->get_result();
                                $min_price = $price_result->fetch_assoc()['min_price'] ?? 0;
                                ?>
                                
                                <div class="price">
                                    <?php if ($min_price > 0): ?>
                                        <span class="text-primary fw-bold">€<?php echo number_format($min_price, 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Price on request</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0">
                            <a href="views/serviceDetails.php?id=<?php echo $listing['ListingID']; ?>" class="btn btn-primary w-100">View Details</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            This business has not published any services yet.
        </div>
    <?php endif; ?>
</div>

<?php include 'utils/footer.php'; ?> 