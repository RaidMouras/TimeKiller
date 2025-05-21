<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../utils/db_config.php';

if (!isset($_GET['id'])) {
    die("Listing ID not specified.");
}

$listing_id = intval($_GET['id']);
$conn = get_db_connection();

// Get the main listing details
$sql = "SELECT l.*, b.Business_Name, b.Profile_Picture as Business_Profile 
        FROM Listing l
        JOIN Business b ON l.UserID = b.UserID
        WHERE l.ListingID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Listing not found.");
}

$listing = $result->fetch_assoc();

// Get price tiers for this listing
$price_tiers_sql = "SELECT * FROM Price_Tiers WHERE ListingID = ? ORDER BY Price";
$price_tiers_stmt = $conn->prepare($price_tiers_sql);
$price_tiers_stmt->bind_param("i", $listing_id);
$price_tiers_stmt->execute();
$price_tiers = $price_tiers_stmt->get_result();

// Get listing images
$images_sql = "SELECT Picture FROM Listing_Pictures WHERE ListingID = ?";
$images_stmt = $conn->prepare($images_sql);
$images_stmt->bind_param("i", $listing_id);
$images_stmt->execute();
$images = $images_stmt->get_result();

// Get average rating
$rating_sql = "SELECT AVG(Star_Rating) as avg_rating, COUNT(*) as review_count 
               FROM Review WHERE ListingID = ?";
$rating_stmt = $conn->prepare($rating_sql);
$rating_stmt->bind_param("i", $listing_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();
$rating_data = $rating_result->fetch_assoc();
$avg_rating = round($rating_data['avg_rating'] ?? 0, 1);
$review_count = $rating_data['review_count'] ?? 0;

// Get tags for this listing
$tags_sql = "SELECT t.Tag_Name 
             FROM Tags t
             JOIN Listing_Tag lt ON t.TagID = lt.TagID
             WHERE lt.ListingID = ?";
$tags_stmt = $conn->prepare($tags_sql);
$tags_stmt->bind_param("i", $listing_id);
$tags_stmt->execute();
$tags = $tags_stmt->get_result();

// Check verification status if logged in
$is_verified = false;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    $verified_check_sql = "SELECT Verified FROM Customers WHERE UserID = ?";
    $verified_stmt = $conn->prepare($verified_check_sql);
    $verified_stmt->bind_param("i", $user_id);
    $verified_stmt->execute();
    $verified_result = $verified_stmt->get_result();

    if ($verified_result->num_rows > 0) {
        $user_data = $verified_result->fetch_assoc();
        $is_verified = $user_data['Verified'] == 1;
    }

    // Check if user has booked this specific service
    $booking_check_sql = "SELECT ph.PurchaseID 
                         FROM Purchase_History_Tier ph
                         JOIN Price_Tiers pt ON ph.TierID = pt.TierID
                         WHERE ph.UserID = ? AND pt.ListingID = ?
                         UNION
                         SELECT ph.PurchaseID 
                         FROM Purchase_History_Negotiation ph
                         JOIN Negotiations n ON ph.NegotiationID = n.NegotiationID
                         WHERE n.UserID = ? AND n.ListingID = ?";
    $booking_stmt = $conn->prepare($booking_check_sql);
    $booking_stmt->bind_param("iiii", $user_id, $listing_id, $user_id, $listing_id);
    $booking_stmt->execute();
    $has_booked = $booking_stmt->get_result()->num_rows > 0;

    $can_review = $is_verified && $has_booked;
}

// Set page title
$page_title = htmlspecialchars($listing['Business_Name'] . ' - ' . $listing['Short_Desc']);

// Add custom CSS
$page_specific_css = '
<link rel="stylesheet" href="../css/serviceDetails.css">
<style>
    body {
        background-color: #f8f9fa;
    }

    .rating-input {
        display: flex;
        flex-direction: row-reverse;
        justify-content: flex-end;
    }

    .rating-input input {
        display: none;
    }

    .rating-input label {
        font-size: 1.5rem;
        color: #ddd;
        cursor: pointer;
        padding: 0 0.1rem;
    }

    .rating-input input:checked~label,
    .rating-input label:hover,
    .rating-input label:hover~label {
        color: #ffc107;
    }

    .rating-input input:checked+label {
        color: #ffc107;
    }

    .review-form {
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
    }

    .replies {
        border-left: 3px solid #eee;
        padding-left: 15px;
        margin-left: 15px;
    }

    .reply {
        padding: 8px;
        background-color: #f8f9fa;
        border-radius: 5px;
        margin-bottom: 8px;
    }

    .carousel-container {
        max-width: 550px;
        margin: 0 auto;
    }

    .carousel-item img {
        max-height: 350px;
        object-fit: cover;
        border-radius: 5px;
    }

    .carousel-indicators button {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin: 0 5px;
    }

    .carousel-control-prev,
    .carousel-control-next {
        width: 5%;
    }

    /* Custom carousel controls */
    .carousel-control-prev,
    .carousel-control-next {
        width: 40px;
        height: 40px;
        top: 50%;
        transform: translateY(-50%);
        opacity: 1;
    }

    .carousel-control-prev:hover,
    .carousel-control-next:hover {
        background-color: rgb(131, 139, 148);
    }

    .carousel-control-prev-icon,
    .carousel-control-next-icon {
        filter: invert(100%);
        width: 1.5rem;
        height: 1.5rem;
    }

    /* Business profile image */
    .business-profile img {
        width: 100px;
        height: 100px;
    }

    .disabled-review {
        opacity: 0.6;
        pointer-events: none;
    }

    .disabled-review .form-control,
    .disabled-review .rating-input label {
        opacity: 0.6;
        pointer-events: none;
    }

    .disabled-review .btn {
        cursor: not-allowed;
    }

    .verification-badge {
        font-size: 0.9rem;
        padding: 3px 8px;
        border-radius: 10px;
        background-color: #28a745;
        color: white;
    }

    .active-thumbnail {
        border: 3px solid #0d6efd !important;
        transform: scale(1.05);
        box-shadow: 0 0 8px rgba(13, 110, 253, 0.5);
        transition: all 0.2s ease;
    }

    .thumbnail-wrapper {
        overflow: hidden;
    }

    .thumbnail-gallery {
        flex-wrap: nowrap;
        transition: transform 0.3s ease;
    }

    .thumbnail-nav:disabled {
        opacity: 0.5;
    }
</style>';

include '../utils/header.php';
?>

<!-- Success/Error Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>


<div class="container mt-2">
    <div class="col-4 d-flex ">
            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>back
            </a>
        </div>
    <!-- Business Profile Row -->
    <div class="row mb-4">
        <div class="col-8">
            <div class="business-profile">
                <div class="d-flex align-items-center">
                    <img src="../api/icon_handler.php?action=get&user_id=<?php echo $listing['UserID']; ?>"
                        class="rounded-circle me-3" alt="Business Profile" style="width: 60px; height: 60px;">
                    <h4 class="mb-0"><?php echo htmlspecialchars($listing['Business_Name']); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column (Pictures and buttons) -->
        <div class="col-md-4 text-center">
            <!-- Listing Images Carousel -->
            <div class="listing-images mb-4 carousel-container">
                <?php if ($images->num_rows > 0): ?>
                    <div id="listingCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000" data-bs-wrap="true">
                        <div class="carousel-indicators">
                            <?php for ($i = 0; $i < $images->num_rows; $i++): ?>
                                <button type="button" data-bs-target="#listingCarousel" data-bs-slide-to="<?php echo $i; ?>"
                                    <?php if ($i === 0) echo 'class="active" aria-current="true"'; ?>
                                    aria-label="Slide <?php echo $i + 1; ?>"></button>
                            <?php endfor; ?>
                        </div>
                        <div class="carousel-inner">
                            <?php $first = true; ?>
                            <?php while ($image = $images->fetch_assoc()): ?>
                                <div class="carousel-item <?php if ($first) {
                                                                echo 'active';
                                                                $first = false;
                                                            } ?>">
                                    <img src="<?php echo htmlspecialchars($image['Picture']); ?>"
                                        class="d-block w-100" alt="Listing Image">
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <?php if ($images->num_rows > 1): ?>
                            <button class="carousel-control-prev" type="button" data-bs-target="#listingCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Previous</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#listingCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Next</span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <img src="https://fakeimg.pl/550x350/?text=No+Image"
                        class="img-fluid rounded mb-2" alt="Service Image">
                <?php endif; ?>
            </div>

            <!-- Thumbnail Gallery with Navigation Controls -->
            <?php if ($images->num_rows > 0): ?>
                <div class="thumbnails-container mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <button class="btn btn-sm btn-primary thumbnail-nav" id="thumbPrev">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="small text-muted" id="imageCounter">Image 1 of <?php echo $images->num_rows; ?></span>
                        <button class="btn btn-sm btn-primary thumbnail-nav" id="thumbNext">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <div class="thumbnail-wrapper">
                        <div class="d-flex <?php if ($images->num_rows <= 5) echo 'justify-content-center'; ?> thumbnail-gallery" id="thumbnailGallery">
                            <?php 
                            $images->data_seek(0); // Reset the result pointer
                            $index = 0;
                            while ($image = $images->fetch_assoc()): 
                            ?>
                                <div class="thumbnail-item m-1 <?php if ($index >= 5) echo 'd-none'; ?>" onclick="jumpToSlide(<?php echo $index; ?>)" id="thumb-<?php echo $index; ?>" data-index="<?php echo $index; ?>">
                                    <img src="<?php echo htmlspecialchars($image['Picture']); ?>"
                                        class="img-thumbnail <?php if ($index === 0) echo 'active-thumbnail'; ?>" 
                                        alt="Thumbnail" style="width: 60px; height: 60px; object-fit: cover; cursor: pointer;">
                                </div>
                            <?php 
                            $index++;
                            endwhile; 
                            ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Middle Column (Listing details) -->
        <div class="col-md-4">
            <!-- Service Name -->
            <h2 class="mb-3"><?php echo htmlspecialchars($listing['Listing_Name']); ?></h2>
            
            <!-- Short Description -->
            <div class="short-description mb-3">
                <p class="lead"><?php echo htmlspecialchars($listing['Short_Desc']); ?></p>
            </div>

            <!-- Rating -->
            <div class="rating mb-3">
                <span class="stars">
                    <?php
                    $full_stars = floor($avg_rating);
                    $half_star = ($avg_rating - $full_stars) >= 0.5;

                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $full_stars) {
                            echo '<i class="fas fa-star text-warning"></i>';
                        } elseif ($half_star && $i == $full_stars + 1) {
                            echo '<i class="fas fa-star-half-alt text-warning"></i>';
                        } else {
                            echo '<i class="far fa-star text-warning"></i>';
                        }
                    }
                    ?>
                </span>
                <span class="ms-2"><?php echo $avg_rating; ?> (<?php echo $review_count; ?> reviews)</span>
            </div>

            <!-- Description -->
            <div class="description mb-4">
                <h4>Description</h4>
                <p><?php echo nl2br(htmlspecialchars($listing['Long_Desc'])); ?></p>
            </div>

            <!-- Location -->
            <div class="location mb-4">
                <h4>Location</h4>
                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($listing['Location']); ?></p>
            </div>

            <!-- Tags -->
            <?php if ($tags->num_rows > 0): ?>
                <div class="tags mb-4">
                    <h4>Tags</h4>
                    <div class="d-flex flex-wrap">
                        <?php while ($tag = $tags->fetch_assoc()): ?>
                            <span class="badge bg-secondary me-2 mb-2"><?php echo htmlspecialchars($tag['Tag_Name']); ?></span>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column (Pricing and reviews) -->
        <div class="col-md-4">
            <!-- Pricing -->
            <div class="pricing-card mb-4 bg-light rounded shadow-sm">
                <div class="pricing-header bg-primary text-white p-3 rounded-top">
                    <h3 class="m-0"><i class="fas fa-tag me-2"></i>Pricing</h3>
                </div>
                <div class="p-3">
                <?php if ($price_tiers->num_rows > 0): ?>
                    <?php 
                    $price_tiers->data_seek(0);
                    $tier_count = $price_tiers->num_rows;
                    // Get the first tier to display
                    $first_tier = $price_tiers->fetch_assoc();
                    ?>
                    <div id="initialTiers">
                        <div class="tier mb-3 p-3 border rounded bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-1"><?php echo htmlspecialchars($first_tier['Tier_Name']); ?></h5>
                                <span class="price fs-4 fw-bold text-primary">€<?php echo number_format($first_tier['Price'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($tier_count > 1): ?>
                        <!-- Hidden tiers that will be shown when "Show More" is clicked -->
                        <div id="hiddenTiers" style="display: none;">
                            <?php 
                            while ($tier = $price_tiers->fetch_assoc()): 
                            ?>
                                <div class="tier mb-3 p-3 border rounded bg-white">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($tier['Tier_Name']); ?></h5>
                                        <span class="price fs-4 fw-bold text-primary">€<?php echo number_format($tier['Price'], 2); ?></span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <!-- Show More/Less button -->
                        <div class="text-center mb-2">
                            <button id="showMoreBtn" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-chevron-down me-1"></i> Show More Pricing Options
                            </button>
                            <button id="showLessBtn" class="btn btn-sm btn-outline-primary" style="display: none;">
                                <i class="fas fa-chevron-up me-1"></i> Show Less
                            </button>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No pricing information available.
                    </div>
                <?php endif; ?>

                <?php if ($listing['Negotiable']): ?>
                    <div class="mt-2 p-2 bg-success bg-opacity-10 rounded text-success">
                        <i class="fas fa-handshake me-2"></i>Prices are negotiable
                    </div>
                <?php endif; ?>
                </div>
            </div>
            
            <!-- 商家视图没有按钮区域，这里移除了Book Now和Message Business按钮 -->
        </div>
    </div>
    
    <!-- Reviews Section -->
    <div class="row mt-5">
        <div class="col-12">
            <h2 class="mb-4">Customer Reviews</h2>
            
            <!-- Reviews List -->
            <div class="reviews">
                <?php
                // Get reviews with user info
                $reviews_sql = "SELECT r.*, c.Username, c.Profile_Picture, c.UserID as CustomerID
                               FROM Review r
                               JOIN Customers c ON r.UserID = c.UserID
                               WHERE r.ListingID = ?
                               ORDER BY r.Created_At DESC";
                $reviews_stmt = $conn->prepare($reviews_sql);
                $reviews_stmt->bind_param("i", $listing_id);
                $reviews_stmt->execute();
                $reviews = $reviews_stmt->get_result();

                if ($reviews->num_rows > 0): ?>
                    <div class="row">
                    <?php while ($review = $reviews->fetch_assoc()): ?>
                        <div class="col-12 mb-4">
                            <div class="review border p-3 rounded h-100">
                                <div class="d-flex align-items-center mb-2">
                                    <img src="../api/icon_handler.php?action=get&user_id=<?php echo $review['CustomerID']; ?>"
                                        class="rounded-circle me-2" width="40" height="40" alt="User">
                                    <a href="ptpChat.php?customer_id=<?php echo $review['CustomerID']; ?>&listing_id=<?php echo $listing_id; ?>" class="text-decoration-none">
                                        <strong><?php echo htmlspecialchars($review['Username']); ?></strong>
                                    </a>
                                    <div class="ms-auto">
                                        <?php echo str_repeat('<i class="fas fa-star text-warning"></i>', $review['Star_Rating']); ?>
                                        <?php echo str_repeat('<i class="far fa-star text-warning"></i>', 5 - $review['Star_Rating']); ?>
                                    </div>
                                </div>
                                <p><?php echo nl2br(htmlspecialchars($review['Body'])); ?></p>
                                <small class="text-muted">
                                    <?php echo date('M j, Y', strtotime($review['Created_At'])); ?>
                                </small>

                                <!-- Show existing replies -->
                                <?php
                                $replies_sql = "SELECT rr.*, u.User_Type, 
                                                IF(u.User_Type = 'Business', b.Business_Name, c.Username) as AuthorName,
                                                IF(u.User_Type = 'Business', b.Profile_Picture, c.Profile_Picture) as AuthorImage
                                                FROM Review_Response rr
                                                JOIN Users u ON rr.SenderID = u.UserID
                                                LEFT JOIN Business b ON u.User_Type = 'Business' AND u.UserID = b.UserID
                                                LEFT JOIN Customers c ON u.User_Type = 'Customer' AND u.UserID = c.UserID
                                                WHERE rr.ReviewID = ?
                                                ORDER BY rr.Time_sent ASC";
                                $replies_stmt = $conn->prepare($replies_sql);
                                $replies_stmt->bind_param("i", $review['ReviewID']);
                                $replies_stmt->execute();
                                $replies = $replies_stmt->get_result();

                                if ($replies->num_rows > 0): ?>
                                    <div class="replies mt-3">
                                        <?php while ($reply = $replies->fetch_assoc()): ?>
                                            <div class="reply">
                                                <div class="d-flex align-items-center">
                                                    <img src="../api/icon_handler.php?action=get&user_id=<?php echo $reply['SenderID']; ?>"
                                                        class="rounded-circle me-2" width="30" height="30" alt="Reply author">
                                                    <span class="reply-author"><?php echo htmlspecialchars($reply['AuthorName']); ?></span>
                                                    <span class="reply-time ms-2"><?php echo date('M j, Y g:i a', strtotime($reply['Time_sent'])); ?></span>
                                                </div>
                                                <p class="reply-content mb-0"><?php echo nl2br(htmlspecialchars($reply['Content'])); ?></p>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Reply form -->
                                <div class="reply-form mt-3">
                                    <form action="../controllers/submit_reply.php" method="post" class="row g-2">
                                        <input type="hidden" name="review_id" value="<?php echo $review['ReviewID']; ?>">
                                        <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">

                                        <div class="col-10">
                                            <input type="text" name="reply_content" class="form-control form-control-sm"
                                                placeholder="Write a reply..." required maxlength="500">
                                        </div>
                                        <div class="col-2">
                                            <button type="submit" class="btn btn-sm btn-primary w-100">Reply</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No reviews yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Add the toggle script for pricing tiers
    document.addEventListener('DOMContentLoaded', function() {
        const showMoreBtn = document.getElementById('showMoreBtn');
        const showLessBtn = document.getElementById('showLessBtn');
        const hiddenTiers = document.getElementById('hiddenTiers');
        
        if (showMoreBtn && showLessBtn && hiddenTiers) {
            showMoreBtn.addEventListener('click', function() {
                hiddenTiers.style.display = 'block';
                showMoreBtn.style.display = 'none';
                showLessBtn.style.display = 'inline-block';
            });
            
            showLessBtn.addEventListener('click', function() {
                hiddenTiers.style.display = 'none';
                showMoreBtn.style.display = 'inline-block';
                showLessBtn.style.display = 'none';
            });
        }
    });

    // Thumbnail navigation functionality
    function jumpToSlide(index) {
        const carousel = bootstrap.Carousel.getInstance(document.getElementById('listingCarousel'));
        carousel.to(index);
        
        // When jumping to a thumbnail not in the visible area, update the thumbnail display
        if (index >= currentPage * 5 || index < (currentPage - 1) * 5) {
            currentPage = Math.floor(index / 5) + 1;
            updateThumbnailsVisibility();
        }
    }
    
    let currentPage = 1;
    const itemsPerPage = 5;
    let totalThumbnails = 0;
    let currentSlideIndex = 0;
    
    // Add event listener to update active thumbnail
    document.addEventListener('DOMContentLoaded', function() {
        const carousel = document.getElementById('listingCarousel');
        const carouselInstance = bootstrap.Carousel.getInstance(carousel) || new bootstrap.Carousel(carousel);
        const thumbnailGallery = document.getElementById('thumbnailGallery');
        const thumbPrev = document.getElementById('thumbPrev');
        const thumbNext = document.getElementById('thumbNext');
        const thumbnails = document.querySelectorAll('.thumbnail-item');
        totalThumbnails = thumbnails.length;
        
        // Set up navigation buttons to control both carousel and thumbnails
        if(thumbPrev && thumbNext) {
            thumbPrev.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (currentSlideIndex > 0) {
                    currentSlideIndex--;
                    carouselInstance.to(currentSlideIndex);
                } else if (totalThumbnails > 0) {
                    currentSlideIndex = totalThumbnails - 1;
                    carouselInstance.to(currentSlideIndex);
                }
                
                // Update thumbnail visibility if needed
                const targetPage = Math.floor(currentSlideIndex / itemsPerPage) + 1;
                if (targetPage !== currentPage) {
                    currentPage = targetPage;
                    updateThumbnailsVisibility();
                }
            });
            
            thumbNext.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (currentSlideIndex < totalThumbnails - 1) {
                    currentSlideIndex++;
                    carouselInstance.to(currentSlideIndex);
                } else if (totalThumbnails > 0) {
                    currentSlideIndex = 0;
                    carouselInstance.to(currentSlideIndex);
                }
                
                // Update thumbnail visibility if needed
                const targetPage = Math.floor(currentSlideIndex / itemsPerPage) + 1;
                if (targetPage !== currentPage) {
                    currentPage = targetPage;
                    updateThumbnailsVisibility();
                }
            });
        }
        
        // Only setup additional thumbnail navigation if needed
        if (totalThumbnails > itemsPerPage) {
            // Initial setup
            updateThumbnailsVisibility();
        }
        
        // Track carousel changes
        if(carousel) {
            carousel.addEventListener('slid.bs.carousel', function(event) {
                // Update current slide index
                currentSlideIndex = event.to;
                
                // Update image counter
                const imageCounter = document.getElementById('imageCounter');
                if(imageCounter) {
                    imageCounter.textContent = `Image ${currentSlideIndex + 1} of ${totalThumbnails}`;
                }
                
                // Remove active class from all thumbnails
                document.querySelectorAll('.img-thumbnail').forEach(function(thumb) {
                    thumb.classList.remove('active-thumbnail');
                });
                
                // Add active class to current thumbnail
                const activeThumbnail = document.querySelector(`#thumb-${currentSlideIndex} .img-thumbnail`);
                if (activeThumbnail) {
                    activeThumbnail.classList.add('active-thumbnail');
                    
                    // Auto-navigate to the page containing the active thumbnail
                    const targetPage = Math.floor(currentSlideIndex / itemsPerPage) + 1;
                    if (targetPage !== currentPage) {
                        currentPage = targetPage;
                        updateThumbnailsVisibility();
                    }
                }
            });
        }
    });
    
    function updateThumbnailsVisibility() {
        const thumbnails = document.querySelectorAll('.thumbnail-item');
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = Math.min(startIndex + itemsPerPage - 1, totalThumbnails - 1);
        
        // Hide all thumbnails
        thumbnails.forEach(function(thumb) {
            thumb.classList.add('d-none');
        });
        
        // Show only the current page thumbnails
        for (let i = startIndex; i <= endIndex; i++) {
            const thumb = document.getElementById(`thumb-${i}`);
            if (thumb) {
                thumb.classList.remove('d-none');
            }
        }
    }
</script>

<?php include '../utils/footer.php'; ?>