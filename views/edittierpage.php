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

require "../controllers/edittiercontroller.php";

$page_title = "Edit Tags";
include_once '../utils/header.php';
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

        th {
            text-align: center;
        }

        tr {
            text-align: center;
            border: 1px solid black;
        }

        table {
            border: 1px solid black;
            width: 4000px;
            margin: 0 auto;
            margin-top:1rem;
            margin-left: 1rem;
            margin-right: 1rem;
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

        .edit-link {
            text-decoration: underline;
            color: #007bff;
            cursor: pointer;
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
                        <li><a href="../views/editlisting.php?id=<?php echo $id;?>">Edit Listing</a></li>
                        <li><a href="../views/edittagpage.php?id=<?php echo $id;?>">Edit Tag</a></li>
                        <li><a href="../views/edittierpage.php?id=<?php echo $id;?>" class="active">Edit Tier</a></li>
                        <li><a href="../views/editlistingimagepage.php?id=<?php echo $id;?>">Edit Picture</a></li>
                        <li><a href="../views/deletelistingpage.php?id=<?php echo $id;?>">Delete Listing</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-md-9">
                <div style="background-color: white;padding-top:1rem">
                <form action="../controllers/addtaghandlercontroller.php" method="post">
                <h1 style="border: 2px solid black; margin-top:1rem;margin-right:1rem;margin-left:1rem;" class="text-center">Current Tiers</h1>
                    <div style="display:flex;align-items:center">
                    <table>
                        <tr>
                            <th>Tiers</th>
                        </tr>
                        <?php 
                        $tags = getTiers($id);
                        foreach($tags as $tag) {
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex justify-content-between align-items-center" style="width: 100%;">
                                <div class="text-start" style="width: 33%;overflow:auto;height:3rem">
                                <span><?php echo $tag["Tier_Name"];?></span>
                                </div>
                                <?php if($tag["TierID"]!= getLowestID($id,$tag["TierID"])) {//this handles placement for the edit if you cant delete it ?>
                                <div class="text-center" style="width:66%">
                                <a href="" class="ml-3  edit-link"
                                data-name = "<?php echo $tag["Tier_Name"] ?>"
                                data-price = "<?php echo $tag["Price"]?>"
                                data-desc = "<?php echo $tag["Description"]?>"
                                data-id = "<?php echo $tag["TierID"]?>">edit</a>
                                </div>
                                <?php } else {?>
                                <div class="text-center" style="width:33%">
                                <a href="" class="ml-3  edit-link"
                                data-name = "<?php echo $tag["Tier_Name"] ?>"
                                data-price = "<?php echo $tag["Price"]?>"
                                data-desc = "<?php echo $tag["Description"]?>"
                                data-id = "<?php echo $tag["TierID"]?>">edit</a>
                                </div>
                                <div class="text-center" style="width:33%">
                                    &nbsp;
                                </div>
                                <?php }?>
                                <?php if($tag["TierID"]!= getLowestID($id,$tag["TierID"])) {//we want every business to at least have one tier so this removes the delete from the default tier?>
                                <div style="width: 33%;" class="text-end">
                                <form action="../controllers/handleredittier.php" method="post">
                                    <input type="hidden" name="tier_id" value=<?php echo $tag["TierID"]?>>
                                    <input type="hidden" name="list_id" value=<?php echo $id?>>
                                    <input type="hidden" name="action" value=2>
                                    <button type="submit" style="color: red;background:none;border:none;">X</button>
                                </form>
                                </div>
                                <?php }?>
                                </div>
                            </td>
                        </tr>
                        <?php }?>
                    </table>
                    
                    </div>
                    <!-- test code !-->
                     <h1 style="border: 2px solid black; margin-top:3rem;margin-right:1rem;margin-left:1rem" class="text-center">Add new Tier</h1>
                    </form>
                    <div class = "row justify-content-center">
                        <div class="" style="padding: 2rem;" >
                        <form action="../controllers/randatierhandler.php" method="POST" enctype="multipart/form-data">
                            <b>Tier Name</b>
                            <input type="text" name="tier_name" class="form-control" required style="background-color: rgb(221, 239, 240);">
                            <br>
                            <b>Price</b>
                            <input type="number" name="price" class="form-control" step="any" required style="background-color: rgb(221, 239, 240);" min = 0>
                            <br>
                            <b>Description</b>
                            <textarea name="long_desc" rows="3" cols="25" class="form-control" required style="background-color: rgb(221, 239, 240);"></textarea>
                            <br>
                            <input type="submit" value="Add Listing" class="form-control" name="submit_al">
                            <input type="hidden" name="id" value=<?php echo $id?>>
                     <!-- test code !-->
                </form>
        </div>
        
    </div>

    
    <div id="textarea-field-container" style="display: none;" class="popups">
                
                <div class="mb-3 popups-content">
                <form action="../controllers/handleredittier.php" method="post">
                    <input type="hidden" name="tier_id" id="popup_tier_id">
                    <input type="hidden" name="list_id" value=<?php echo $id ?>>
                    <input type="hidden" name="action" value=1>
                    <label for="field-textarea-name" class="form-label">Tier Name</label>
                    <input type="text" id="field-textarea-name" class="form-control" name="tier_name">
                    <label for="field-textarea-price" class="form-label">Price</label>
                    <input type="number" id="field-textarea-price" class="form-control" name="tier_price">
                    <label for="field-textarea" class="form-label">Desc</label>
                    <textarea  id="field-textarea" rows="4" class="form-control" name="tier_desc"></textarea>
                    <div style="margin-top: 1rem;display:flex">
                    <button class="btn btn-priamry" id="submit-buttLD">Submit</button>
                    </form>
                    <button type="button" class="btn btn-secondary" id="cancel-butt">cancel</button>
                    </div>
                    
                </div>
                
            
        </div>


</body>

<script>

$(document).ready(function() {
 $('.edit-link').click(function (e){
    e.preventDefault();
    const name = $(this).data("name");
    const price = $(this).data("price");
    const desc = $(this).data("desc");
    const id = $(this).data("id");


    $('#field-textarea-name').val(name);
    $('#field-textarea-price').val(price);
    $('#field-textarea').val(desc);
    $('#popup_tier_id').val(id);


    $('#textarea-field-container').fadeIn();


    
 });


 $('#cancel-butt').click(function (e){
    $('#textarea-field-container').fadeOut();


 });

});




</script>