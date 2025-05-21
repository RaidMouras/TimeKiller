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

require "../controllers/editlistingpicscontroller.php";

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

    .disimg {
        max-width: 150px;
        max-height: 150px;
        min-height: 150px;
        min-width: 150px;
        align-items: center;

    }

    .img-container {
        border: 2px solid black;
    }

    .imgform {
        padding-left: 0;
        padding-right: 0;
        margin-right:0;
        margin-left: 0;
        border: 1px solid grey;
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
            <?php $id =  $_GET["id"];
            $limit = checkIfLimitReached($id);
            if($limit == false) {
                $buttext = "Add Image";
                $dis = "";
                $butClass = "form-control btn-outline-primary";
            } else {
                $buttext = "Maximum number of pictures reached";
                $dis = "disabled";
                $butClass = "form-control btn-outline-Secondary";
            } ?>
            <div class="col-md-3">
                <div class="sidebar">
                    <ul class="list-unstyled">
                        <li><a href="../views/editlisting.php?id=<?php echo $id;?>">Edit Listing</a></li>
                        <li><a href="../views/edittagpage.php?id=<?php echo $id;?>">Edit Tag</a></li>
                        <li><a href="../views/edittierpage.php?id=<?php echo $id;?>">Edit Tier</a></li>
                        <li><a href="../views/editlistingimagepage.php?id=<?php echo $id;?>" class="active">Edit
                                Picture</a></li>
                        <li><a href="../views/deletelistingpage.php?id=<?php echo $id;?>">Delete Listing</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-md-5">
                <?php 
                    $pics = getImagePath($id);
                    foreach($pics as $pic) {
                        $currentimage = $pic["Picture"];
                        $encodedpath = urldecode($currentimage);
                ?>
                <div style="background-color: white; margin-bottom:1rem"
                    class="d-flex justify-content-start align-items-center img-container">
                    <div class="bg-white p-3 rounded" style="background-color: green;">
                        <img src= "<?php echo $encodedpath?>"
                            alt="" class="img-fluid disimg">
                    </div>
                    <div style="display: block;margin-left:3rem">
                        <?php if(checkIfCover($pic["PictureID"],$id)){
                            $coverID = $pic["PictureID"];
                            $coverpath = $pic["Picture"]?>
                            
                        <form action="#">
                            <input type="submit" name="Set as Cover" value="Current Cover"
                                class="btn btn-outline-secondary" style="padding-left:1.7rem;padding-right:1.7rem" disabled>
                        </form>
                        <?php } else {?>
                            <form action="../controllers/handlereditpiclisting.php" method="post">
                            <input type="hidden" name="pic_id" value="<?php echo $pic["PictureID"]?>">
                            <input type="hidden" name="listing_id" value=<?php echo $id?>>
                            <input type="hidden" name="action" value=3>
                            <input type="hidden" name="cover_id" value="<?php echo $coverID?>">
                            <input type="hidden" name="cover_path" value="<?php echo $coverpath?>">
                            <input type="hidden" name="pic_path" value="<?php $picpath = $pic["Picture"];
                             echo $picpath?>">
                            <input type="submit" name="Set as Cover" value="Set as Cover"
                                class="btn btn-outline-primary" style="padding-left:2rem;padding-right:2rem">
                        </form>
                        <?php }?>
                        <form action="../controllers/handlereditpiclisting.php" method="post">
                            <input type="hidden" name="pic_id" value="<?php echo $pic["PictureID"]?>">
                            <input type="hidden" name="listing_id" value=<?php echo $id?>>
                            <input type="hidden" name="action" value=2>
                            <input type="hidden" name="path" value="<?php echo $pic["Picture"]?>">
                            <input type="submit" name="delete"
                                style="margin-top: 1rem;padding-left:3.3rem;padding-right:3.3rem" value="Delete"
                                class="btn btn-outline-danger">
                        </form>
                    </div>
                </div>
                <?php }?>   
            </div>
            <div class="col-md-4 imgform" style="background: white;max-height:35vh">
                <h2 class="text-center" style="border: 1px solid black;">Add Image</h2>
                <form action="../controllers/handlereditpiclisting.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value=1>
                <input type="hidden" name="listing_id" value=<?php echo $id?>>
                <input type="file" name="listing_image[]" multiple class="form-control" required style="background-color: rgb(221, 239, 240);max-width:20rem;margin-left:3rem;margin-top: 2rem;">
                <input type="submit" value="<?php echo $buttext?>" class="<?php echo $butClass?>" name="submit_al" style="max-width:20rem;margin-left:3rem;margin-top: 2rem;" <?php echo $dis?> >
                </form>
            </div>
        </div>
    </div>

</body>