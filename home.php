<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$page_title = 'Home';
$page_specific_css = '<link href="css/pages/home.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css"
      integrity="sha512-nMNlpuaDPrqlEls3IX/Q56H36qvBASwb3ipuo3MxeWbsQB1881ox0cRv7UPTgBlriqoynt35KjEwgGUeUXIPnw=="
      crossorigin="anonymous" referrerpolicy="no-referrer" />';
include 'utils/header.php';
require_once "./controllers/home_controller.php";

$search = isset($_GET['Search']) ? $_GET['Search'] : '';
$locationFilter = isset($_GET['location']) ? $_GET['location'] : '';
$starVal = isset($_GET['rating']) ? $_GET['rating'] : '0';
$order = isset($_GET['priceSort'])?$_GET['priceSort']:'Ratings';
$tagselected = isset($_GET['tags'])?$_GET['tags']:[];
?>

<form action="" method="get">
    <div class="container-fluid">
        <div class="row min-vh-100">
            <div class="col-md-4 filter-sidebar" style= "background-color: rgb(214, 215, 215);" >
                <h1>Search</h1>
                <input type="text" placeholder="Search" style="height: 2rem;" name="Search"
                    value="<?php echo htmlspecialchars($search); ?>">
                <hr>
                <H1>Filters</H1>
                <div class="mb-3">
                    <div style="display: flex;align-items:center;justify-content:center">
                        <select name="priceSort" class="form-select" id="priceSort">
                            <option value="Ratings" <?php echo ($order == 'Ratings') ? 'selected' : ''; ?>>Sort By Ratings</option>
                            <option value="Descending" <?php echo ($order == 'Descending') ? 'selected' : ''; ?>>Sort By Price Descending</option>
                            <option value="Ascending" <?php echo ($order == 'Ascending') ? 'selected' : ''; ?>>Sort By Price Ascending</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <select name="location" style="height: 2rem;" class="form-control select2"
                        id="locationDropdown">
                        <option value="">Choose a Location</option>
                        <?php
                        $locations = getLocations();
                        foreach ($locations as $location) {
                            ?>
                            <option value="<?php echo htmlspecialchars($location['location']) ?>" <?php echo ($locationFilter == $location['location']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($location["location"]) ?>
                            </option>
                            <?php
                        }
                        ?>
                    </select>
                </div>
                <div class="rating-filter mb-3">
                    <header style="padding-top:0">Rating</header>
                    <div id="star-rating">
                        <input type="hidden" name="rating" id="rating-value"
                            value="<?php echo htmlspecialchars($starVal); ?>">
                        <i class="fa fa-star rating-stars" data-value="1"></i>
                        <i class="fa fa-star rating-stars" data-value="2"></i>
                        <i class="fa fa-star rating-stars" data-value="3"></i>
                        <i class="fa fa-star rating-stars" data-value="4"></i>
                        <i class="fa fa-star rating-stars" data-value="5"></i>
                    </div>
                </div>
                <div class="mb-3">
                <select name="tags[]" class="form-select mt-3" multiple id="tagDropdown">
                    <option value="">Choose a Tag</option>
                    <?php
                    $tags = getTags();
                    foreach ($tags as $tag) {
                        ?>
                        <option value="<?php echo htmlspecialchars($tag['TagID']) ?>" <?php echo (in_array($tag['TagID'], $tagselected)) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tag["Tag_Name"]) ?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
                </div>
                <div class="d-flex gap-3 w-100">
                    <a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>" class="btn btn-secondary w-50 text-center">Clear Filters</a>
                    <input type="submit" class="submit-btn btn w-100" value="Search">
                </div>
            </div>


            <div class="col-md-8" style="padding-left: 3rem; width:65%;">
                <?php
                $listing = getListings($search, $order, $locationFilter, $starVal, $tagselected);
                foreach ($listing as $list) {
                    if ($list['Deleted'] == 1) {
                        continue;
                    }
                    $listingImage = getListingPFP($list["ListingID"]);
                    $imageSrc = !empty($listingImage) ? htmlspecialchars($listingImage) : 'https://fakeimg.pl/350x200/?text=No Image';
                    ?>
                    <div class="listing-container">
                        <img src="<?php echo $imageSrc; ?>" alt="Listing Image">
                        <div style="padding-top: 2rem;width:50%" class="listing-details">
                            <h1><?php echo htmlspecialchars($list["Listing_Name"]) ?></h1>
                            <p>
                                <?php echo htmlspecialchars($list["Short_Desc"]) ?>
                            </p>
                        </div>
                        <div class="listing-right-side">
                            <h1 class="listing-price">
                                <?php echo htmlspecialchars(getDefaultPrice($list["ListingID"])) ?></h1>
                            <h2><?php echo htmlspecialchars($list["Location"]) ?></h2>
                            <a href="test2.php?business_id=<?php echo $list['UserID']; ?>&listing_id=<?php echo $list['ListingID']; ?>" class="btn btn-outline-secondary">Message Business</a>
                            <a href="all_listings.php?id=<?php echo $list['UserID']; ?>" class="btn btn-outline-secondary">View Business Page</a>
                            <a href="./views/serviceDetails.php?id=<?php echo $list['ListingID']; ?>"
                                class="btn btn-primary">View Listing</a>
                        </div>
                    </div>
                    <br>
                    <?php
                }
                ?>
            </div>
        </div>
    </div>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"
    integrity="sha512-2ImtlRlf2VVmiGZsjm9bEyhjGW4dU7B6TNwh/hx/iSByxNENtj3WVE6o/9Lj4TJeVXPi4bnOIMXFIJJAeufa0A=="
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    $(document).ready(function () {
        $('#locationDropdown').select2({
            placeholder: "Choose a Location",
            allowClear: true,
            width: "100%"
        });
        $('#tagDropdown').select2({
            placeholder: "Choose a Tag",
            allowClear: true,
            width: "100%"
        });
    });

    const stars = document.querySelectorAll('.rating-stars');
    const ratingInput = document.getElementById('rating-value');

    stars.forEach(star => {
        star.addEventListener('click', () => {
            const clickedValue = parseInt(star.getAttribute('data-value'));
            const currentValue = parseInt(ratingInput.value);

            if (clickedValue === currentValue) {
                // If clicked star is already selected, reset to 0
                ratingInput.value = 0;
                stars.forEach(s => s.classList.remove('filled'));
            } else {
                // Set new rating
                ratingInput.value = clickedValue;

                // Update star colors
                stars.forEach(s => {
                    const starVal = parseInt(s.getAttribute('data-value'));
                    s.classList.toggle('filled', starVal <= clickedValue);
                });
            }
        });
    });


    //set stars to 0 if re-clicked
    document.addEventListener('DOMContentLoaded', () => {
        const currentRating = parseInt(ratingInput.value);
        if (!isNaN(currentRating)) {
            stars.forEach(s => {
                const val = parseInt(s.getAttribute('data-value'));
                if (val <= currentRating) {
                    s.classList.add('filled');
                } else {
                    s.classList.remove('filled');
                }
            });
        }
    });
</script>
<?php
include 'utils/footer.php';
?>