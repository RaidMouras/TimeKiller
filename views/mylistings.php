<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit;
}

require '../controllers/mylistings_controller.php';


$page_title = "My Listings";

include_once '../utils/header.php';

?>

<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css">
</head>

<body style="background-color: rgb(221, 239, 240);">
<form action="./businesshub.php">
                    <button type="submit" class="btn btn-outline-primary" style="margin-left: 5rem;margin-top:1rem;margin-bottom:2rem">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                </form>
    <div class="container">
        <div class=row>
            <div class="col-1">
            </div>
        </div>
        <div class="row">
            <div class="col-12 text-center">
                <?php 
                $listing = getBusinessListings($_SESSION["user_id"]);
                if (empty($listing)) {
                    // No listings found, display "add first listing" button
                    ?>
                    <div class="text-center mt-5 mb-5">
                        <p>don't have any listings yet</p>
                        <a href="./newlisting.php" class="btn btn-primary btn-lg">Add First Listing</a>
                    </div>
                    <?php
                } else {
                    // Display existing listings
                    foreach($listing as $list) {

                
                
                
                ?>
                <div style="background-color: white;border:5px solid black">
                    <div class="d-flex">
                        <img src="<?php echo (getListingPFP($list["ListingID"])) ?>" alt=""
                            style="max-width: 150px; max-height:150px;min-width: 150px;min-height:150px">
                        <div style="padding-top: 2.6rem;width:50%;margin-left:3rem" class="text-start">
                            <h1 style="overflow-y: auto; max-height:5rem"><?php echo ($list["Listing_Name"]) ?></h1>
                        </div>
                        <div class="vr"></div>
                        <div style="margin-top: 1rem;margin-bottom:1rem" class="mx-auto">
                                <form action="../views/businessServiceDetails.php?id=<?php echo $list['ListingID']; ?>" method="get">
                                <input type="hidden" name="id" value="<?php echo $list["ListingID"];?>">
                                <button style="margin-right: 1rem;" class="btn btn-outline-primary form-control">view listing</button>
                                </form>
                                <hr>
                                <form action="../views/editlisting.php?=1" method="get">
                                <button class="btn btn-outline-primary form-control">edit listing</button>
                                <input type="hidden" name="id" value="<?php echo $list["ListingID"];?>">
                                </form>
                        </div>
                    </div>
                </div>
                <br>
                <?php
                    }
                }
                ?>
            </div>
        </div>
    </div>
    </div>
    </div>
</body>