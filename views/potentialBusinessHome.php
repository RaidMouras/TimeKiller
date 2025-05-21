<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit;
}

$page_specific_css = '
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">';

require '../controllers/businesshub_controller.php';
getBusinessInfo($_SESSION['user_id']);

$page_title = "Business Hub";
include_once '../utils/header.php';
?>
<body style="background-color: rgb(221, 239, 240)">
<!-- Header -->
<header class="card p-4 shadow mx-auto">
  <div class="container text-center">
    <h1 class="display-4 fw-bold"><?php echo "Welcome " . $_SESSION["business_name"]?></h1>
    <p class="lead">To The Time Killer Business Hub</p>
    <img src="../assets/mask_1.png" alt="Logo" class="my-4" style="width: 150px; height: auto;">
  </div>
</header>

<section class="py-5">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-8">
        <h1>Have a look at your ratings!</h1>
        <?php $score = getBusinessReviewScore($_SESSION["user_id"]); ?>
        <p class="lead"><?php echo $score ?> out of 5</p>
        <p class="text-muted">We'll update this when you get reviews on your listings!</p>
      </div>
      <div class="col-lg-3 text-center">
        <?php
            for ($i = 1; $i <= 5; $i++) {
                $color = ($i <= $score) ? '#ffc107' : '#6c757d';
                echo "<i class='fas fa-star fa-5x' style='color: $color'></i>";
            }
        ?>
      </div>
    </div>
  </div>
</section>

<section class="py-5">
  <div class="container">
    <div class="row justify-content-center">
      <!-- Total Reviews -->
      <div class="col-md-5 mb-4">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body text-center">
            <h5 class="card-title">
              <a href="../business/view_review.php" class="text-decoration-none">Total Reviews</a>
            </h5>
            <p class="card-text mt-3"><strong>Total Reviews: <?php echo(getTotalReviews($_SESSION["user_id"])) ?></strong></p>
            <i class="fas fa-thumbs-up fa-3x text-secondary mt-3"></i>
          </div>
        </div>
      </div>

      <!-- Services Sold -->
      <div class="col-md-5 mb-4">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body text-center">
            <h5 class="card-title">
              <a href="../business/service_history.php" class="text-decoration-none">Services Sold</a>
            </h5>
            <p class="card-text mt-3"><strong>Sold: <?php echo(getServicesSold($_SESSION["user_id"])) ?></strong></p>
            <i class="fas fa-shopping-cart fa-3x text-secondary mt-3"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="bg-light py-5">
  <div class="container">
    <div class="row align-items-start">
      <div class="col-lg-4 text-center mb-4 mb-lg-0">
        <i class="fas fa-coins fa-5x" style="color: #6c757d"></i>
      </div>

      <div class="col-lg-8">
        <div class="d-flex justify-content-between flex-wrap">
          <div class="pe-2">
            <h1>Business Profits</h1>
            <p class="lead"><?php echo(getProfit($_SESSION["user_id"])) ?></p>
            <p class="text-muted">The total amount of money you've made here on Time Killer</p>
            <p class="text-muted">Add more listings to get this number up!</p>
          </div>

          <div class="d-flex flex-column align-items-start">
            <form action="../views/newlisting.php" method="post" class="mb-2 w-100">
              <input type="submit" class="btn btn-primary w-100" value="Add New Listing">
            </form>
            <a href="../views/mylistings.php" class="btn btn-outline-secondary w-100">View Listings</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>







<?php
include '../utils/footer.php';
?>
