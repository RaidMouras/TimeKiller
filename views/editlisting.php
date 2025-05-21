<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../controllers/editlistings_controller.php';

$page_title = "Edit Listing";
include_once '../utils/header.php';

$list = getListinginfo($_GET["id"]);
$negot = $list[0]["Negotiable"] == 1 ? "Yes" : "No";
?>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background-color: #DDEFF0;
        }

        .sidebar {
            background-color: white;
            height: 100vh;
        }

        .sidebar ul {
            padding-left: 0;
        }

        .sidebar ul li a {
            display: block;
            padding: 15px 30px;
            color: black;
            border-bottom: 1px solid #ccc;
            transition: 0.3s;
        }

        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background-color: grey;
            color: white;
        }

        .backbut {
            margin: 1rem 5rem 1rem 5rem;
        }

        .eldiv {
            background: white;
            border: 1px solid black;
            padding: 2rem;  
            margin: 2rem 0;
            border-radius: 5px;
        }

        .desc-row {
            margin-bottom: 1.5rem;
            max-width: 80rem;
        }

        .desc-label {
            font-weight: bold;
            color: #333;
        }

        .desc-value {
            overflow-y: auto;
        }

        .edit-link {
            text-decoration: underline;
            color: #007bff;
            cursor: pointer;
        }

        .popups {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5); /* semi-transparent background */
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1050;
        }

        .popups-content {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            max-width: 500px;
            min-width: 500px;
            width: 90%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
    </style>
</head>

<body>
    <form action="../views/mylistings.php">
        <button type="submit" class="btn btn-outline-primary backbut">
            <i class="bi bi-arrow-left"></i> Back
        </button>
    </form>

    <div class="container-fluid">
        <div class="row">
            <?php $id =  $_GET["id"];?>
            <div class="col-md-3">
                <div class="sidebar">
                    <ul class="list-unstyled">
                        <li><a href="../views/editlisting.php?id=<?php echo $id;?>" class="active">Edit Listing</a></li>
                        <li><a href="../views/edittagpage.php?id=<?php echo $id;?>">Edit Tag</a></li>
                        <li><a href="../views/edittierpage.php?id=<?php echo $id;?>">Edit Tier</a></li>
                        <li><a href="../views/editlistingimagepage.php?id=<?php echo $id;?>">Edit Picture</a></li>
                        <li><a href="../views/deletelistingpage.php?id=<?php echo $id;?>">Delete Listing</a></li>
                    </ul>
                </div>
            </div>

            <div class="col-md-9">
                <div class="eldiv">
                    <div class="row desc-row">
                        <div class="col-md-3 desc-label">Listing Name:</div>
                        <div class="col-md-7 desc-value"><?php echo htmlspecialchars($list[0]["Listing_Name"]); ?></div>
                        <div class="col-md-2"><a class="edit-link" href="#" data-field = "Listing_Name">Edit</a></div>
                    </div>
                    <hr>

                    <div class="row desc-row">
                        <div class="col-md-3 desc-label">Long Description:</div>
                        <div class="col-md-7 desc-value"><?php echo nl2br(htmlspecialchars($list[0]["Long_Desc"])); ?></div>
                        <div class="col-md-2"><a class="edit-linkLD" href="#">Edit</a></div>
                    </div>
                    <hr>

                    <div class="row desc-row">
                        <div class="col-md-3 desc-label">Short Description:</div>
                        <div class="col-md-7 desc-value"><?php echo htmlspecialchars($list[0]["Short_Desc"]); ?></div>
                        <div class="col-md-2"><a class="edit-linkSD" href="#">Edit</a></div>
                    </div>
                    <hr>

                    <div class="row desc-row">
                        <div class="col-md-3 desc-label">Location:</div>
                        <div class="col-md-7 desc-value"><?php echo htmlspecialchars($list[0]["Location"]); ?></div>
                        <div class="col-md-2"><a class="edit-linkLo" href="#">Edit</a></div>
                    </div>
                    <hr>

                    <div class="row desc-row">
                        <div class="col-md-3 desc-label">Negotiable:</div>
                        <div class="col-md-7 desc-value"><?php echo $negot; ?></div>
                        <div class="col-md-2"><a class="edit-linkNe" href="#">Edit</a></div>
                    </div>
                </div>
            </div>
            <!--start of popup-->

        </div>
    </div>
    
            <div id="textarea-field-container" style="display: none;" class="popups">
                
                    <div class="mb-3 popups-content">
                    <form action="../controllers/editlistings_controller.php" method="post">
                        <label for="field-textarea" class="form-label">Listing Name</label>
                        <textarea  id="field-textarea" rows="4" class="form-control" name="content"></textarea>
                        <div style="margin-top: 1rem;display:flex">
                        
                            <input type="hidden" name="action" value=1>
                            <input type="hidden" name="id" value=<?php echo $id ?>>
                            <button class="btn btn-priamry" id="submit-butt">Submit</button>
                        </form>
                        <button type="button" class="btn btn-secondary" id="cancel-butt">cancel</button>
                        </div>
                        
                    </div>
                    
                
            </div>
            <!--end of popup-->
            <div id="textarea-field-containerLD" style="display: none;" class="popups">
                
                <div class="mb-3 popups-content">
                    <form action="../controllers/editlistings_controller.php" method="post">
                    <label for="field-textarea" class="form-label">Long Description</label>
                    <textarea  id="field-textareaLD" rows="4" class="form-control" name="content"></textarea>
                    <div style="margin-top: 1rem;display:flex">
                    <input type="hidden" name="action" value=2>
                    <input type="hidden" name="id" value=<?php echo $id ?>>
                    <button class="btn btn-priamry" id="submit-buttLD">Submit</button>
                    </form>
                    <button type="button" class="btn btn-secondary" id="cancel-buttLD">cancel</button>
                    </div>
                    
                </div>
                
            
        </div>

        <div id="textarea-field-containerSD" style="display: none;" class="popups">
                
                <div class="mb-3 popups-content">
                    <form action="../controllers/editlistings_controller.php" method="post">
                    <label for="field-textarea" class="form-label">Short Description</label>
                    <textarea  id="field-textareaSD" rows="4" class="form-control" name="content"></textarea>
                    <div style="margin-top: 1rem; display:flex">
                    <input type="hidden" name="action" value=3>
                    <input type="hidden" name="id" value=<?php echo $id ?>>
                    <button class="btn btn-priamry" id="submit-buttSD">Submit</button>
                    </form>
                    <button type="button" class="btn btn-secondary" id="cancel-buttSD">cancel</button>
                    </div>
                    
                </div>
                
            
        </div>

        <div id="textarea-field-containerLo" style="display: none;" class="popups">
                
                <div class="mb-3 popups-content">
                <form action="../controllers/editlistings_controller.php" method="post">
                    <label for="field-textarea" class="form-label">Location</label>
                    <textarea  id="field-textareaLo" rows="4" class="form-control" name="content"></textarea>
                    <div style="margin-top: 1rem;display:flex">
                    <input type="hidden" name="action" value=4>
                    <input type="hidden" name="id" value=<?php echo $id ?>>
                    <button class="btn btn-priamry" id="submit-buttLo">Submit</button>
                    </form>
                    <button type="button" class="btn btn-secondary" id="cancel-buttLo">cancel</button>
                    </div>
                    
                </div>
                
            
        </div>

        <div id="textarea-field-containerNe" style="display: none;" class="popups">
                
                <div class="mb-3 popups-content">
                    <form action="../controllers/editlistings_controller.php" method="post">
                    <label for="field-textarea" class="form-label">Negotiable: </label>
                    
                    <input type="radio" value="Yes" name="Negotiable">
                    <label for="Yes">Yes</label>
                    <input type="radio" value="No" name="Negotiable">
                    <label for="No">No</label>
                    <div style="margin-top: 1rem; display:flex">
                    
                    <input type="hidden" name="action" value=5>
                    <input type="hidden" name="id" value=<?php echo $id ?>>
                    <button class="btn btn-priamry" id="submit-buttNe">Submit</button>
                    </form>
                    <button type="button" class="btn btn-secondary" id="cancel-buttNe">cancel</button>
                    </div>
                    
                </div>
                
            
        </div>
</body>
<script>

$(document).ready(function() {
 $('.edit-link').click(function (e){
    e.preventDefault();

    const field = $(this).data('field');
    const value = $(this).closest('.desc-row').find('.desc-value').text().trim();

    $('#textarea-field-container').fadeIn();


    $('#field-name').val(field); // store field name if needed
            $('#field-textarea').val(value);
 });


 $('.edit-linkLD').click(function (e){
    e.preventDefault();

    const field = $(this).data('field');
    const value = $(this).closest('.desc-row').find('.desc-value').text().trim();

    $('#textarea-field-containerLD').fadeIn();

    
    $('#field-nameLD').val(field); // store field name if needed
            $('#field-textareaLD').val(value);
 });

 $('.edit-linkSD').click(function (e){
    e.preventDefault();

    const field = $(this).data('field');
    const value = $(this).closest('.desc-row').find('.desc-value').text().trim();

    $('#textarea-field-containerSD').fadeIn();

   
    $('#field-nameSD').val(field); // store field name if needed
            $('#field-textareaSD').val(value);
 });

 $('.edit-linkLo').click(function (e){
    e.preventDefault();

    const field = $(this).data('field');
    const value = $(this).closest('.desc-row').find('.desc-value').text().trim();

    $('#textarea-field-containerLo').fadeIn();

    
    $('#field-nameLo').val(field); // store field name if needed
            $('#field-textareaLo').val(value);
 });

 $('.edit-linkNe').click(function (e){
    e.preventDefault();

    const field = $(this).data('field');
    const value = $(this).closest('.desc-row').find('.desc-value').text().trim();
    

    $('#textarea-field-containerNe').fadeIn();

    
    $(`input[name="Negotiable"][value="${value}"]`).prop('checked', true);
 });

 $('#cancel-butt').click(function (e){
    $('#textarea-field-container').fadeOut();


 });

 $('#cancel-buttLD').click(function (e){
    $('#textarea-field-containerLD').fadeOut();


 });

 $('#cancel-buttSD').click(function (e){
    $('#textarea-field-containerSD').fadeOut();


 });

 $('#cancel-buttLo').click(function (e){
    $('#textarea-field-containerLo').fadeOut();


 });

 $('#cancel-buttNe').click(function (e){
    $('#textarea-field-containerNe').fadeOut();


 });

 $


});




</script>