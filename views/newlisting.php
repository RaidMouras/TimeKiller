<!DOCTYPE html>
<?php 
require "../controllers/businesshub_controller.php";
include_once '../utils/header.php';?>
<html style="height: 100%;">
<head>
    <title>New Listing</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/pages/new_listing.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" integrity="sha512-nMNlpuaDPrqlEls3IX/Q56H36qvBASwb3ipuo3MxeWbsQB1881ox0cRv7UPTgBlriqoynt35KjEwgGUeUXIPnw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <Style>
        .select2-container--default .select2-selection--multiple {
    background-color: rgb(221, 239, 240);
}
.select2-container--default .select2-selection--multiple .select2-search__field {
    background-color: rgb(221, 239, 240);
}
    </Style>
</head>

<body style="background-color: rgb(221, 239, 240); height:90%">
        <div class="container-fluid" style="height:100%">
            <div class="row mx-auto mainbox_row" style="height: 65%;">
                <div class="col-12" style="background-color:white;">
                <form action="./businesshub.php">
                    <button type="submit" class="btn btn-outline-primary" style="margin-left: 5rem;margin-top:2rem">
                        <i class="bi bi-arrow-left"></i> Back
                    </button>
                </form>
                    <div class = "row justify-content-center">
                        <div class="col-md-6 col-sm-10">
                        <form action="../controllers/business_requests_controller.php" method="POST" enctype="multipart/form-data">
                            <b>Service name</b>
                            <input type="text" name="service_name" class="form-control" required style="background-color: rgb(221, 239, 240);">
                            <br>
                            <b>Selling Methods</b>
                            <input type="text" name="selling_methods" class="form-control" required style="background-color: rgb(221, 239, 240);">
                            <br>
                            <b>Price</b>
                            <input type="number" name="price" class="form-control" step="any" required style="background-color: rgb(221, 239, 240);"min = 0>
                            <br>
                            <b>Location</b>
                            <input type="text" name="location" class="form-control" required style="background-color: rgb(221, 239, 240);">
                            <br>
                            <b>Thumbnail Text</b>
                            <textarea name="thumbnail_text" rows="1" cols="25" class="form-control" required style="background-color: rgb(221, 239, 240);"></textarea >
                            <br>
                            <b>Long Descriptions</b>
                            <textarea name="long_desc" rows="3" cols="25" class="form-control" required style="background-color: rgb(221, 239, 240);"></textarea>
                            <br>
                            <b>Cover Image</b>
                            <input type="file" name="listing_image[]" class="form-control" required style="background-color: rgb(221, 239, 240);">
                            <br>
                            <select name="tags[]" class="select2" multiple required style="background-color: rgb(221, 239, 240);">
                                    <option value="">Choose a Tag</option>
                                    <?php 
                                    $tags = getTags($id);
                                    foreach($tags as $tag) {
                                    ?>
                                    <option value="<?php echo($tag['TagID'])?>">
                                        <?php echo $tag["Tag_Name"]?>
                                    </option>
                                    <?php
                                    }
                                    ?>
                                </select>
                            <div class="form-check mt-3">
                                <label class="form-check-label">Negotiable</label>
                                <input type="checkbox" class="form-check-input" name="negotiable" value="hi">
                            </div>
                            <br>
                            <input type="submit" value="Add Listing" class="form-control" name="submit_al">
                        </form>
                        </div>
                    </div>
                </div>
            </div>
            </div>
    
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js" integrity="sha512-2ImtlRlf2VVmiGZsjm9bEyhjGW4dU7B6TNwh/hx/iSByxNENtj3WVE6o/9Lj4TJeVXPi4bnOIMXFIJJAeufa0A==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
            <script>
                $(document).ready(function() {
                    $('.select2').select2({
                        placeholder:"Choose a Tag",
                        allowClear:true,
                        width:"100%"
                    });
                });
            </script>
</body>
</html>