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

require "../controllers/edittagcontroller.php";

$page_title = "Edit Tags";
include_once '../utils/header.php';
?>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" integrity="sha512-nMNlpuaDPrqlEls3IX/Q56H36qvBASwb3ipuo3MxeWbsQB1881ox0cRv7UPTgBlriqoynt35KjEwgGUeUXIPnw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
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
            width: 400px;
            margin: 0 auto;
            margin-top:1rem;
            margin-left: 1rem;
        }

        .select2-container--default .select2-selection--multiple {
    background-color: white;
    margin-left: 2rem;
    margin-right: 2rem;
}
.select2-container--default .select2-selection--multiple .select2-search__field {
    background-color: white;
}

form {
    padding: 0px;
    margin: 0px;
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
                        <li><a href="../views/edittagpage.php?id=<?php echo $id;?>" class="active">Edit Tag</a></li>
                        <li><a href="../views/edittierpage.php?id=<?php echo $id;?>">Edit Tier</a></li>
                        <li><a href="../views/editlistingimagepage.php?id=<?php echo $id;?>">Edit Picture</a></li>
                        <li><a href="../views/deletelistingpage.php?id=<?php echo $id;?>">Delete Listing</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-md-9">
                <div style="background-color: white;">
                <form action="../controllers/addtaghandlercontroller.php" method="post">
                    <div style="display:flex;align-items:center">
                    <table>
                        <tr>
                            <th>Tags</th>
                        </tr>
                        <?php 
                        $tags = getTags($id);
                        foreach($tags as $tag) {
                            
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex justify-content-between align-items-center" >
                                <span><?php echo $tag["Tag_Name"];?></span>
                                <a href="../controllers/addtaghandlercontroller.php?id=<?php echo $id ?>&action=delete&tagid=<?php echo $tag["TagID"]?>" style="color: red;">X</a>
                                </div>
                            </td>
                        </tr>
                        <?php }?>
                    </table>
                    
                    <select name="tags[]" class="select2" multiple required style="background-color: rgb(221, 239, 240);">
                                    <option value="">Choose a Tag</option>
                                    <?php 
                                    $tags = getUnusedTags($id);
                                    foreach($tags as $tag) {
                                    ?>
                                    <option value="<?php echo($tag['TagID'])?>">
                                        <?php echo $tag["Tag_Name"]?>
                                    </option>
                                    <?php
                                    }
                                    ?>
                                </select>
                    </div>
                    <div class="text-center">
                    
                        <input type="hidden" name="id" value=<?php echo $id?>>
                        <button type="submit" class="btn btn-primary form-control" style="margin-top:1rem" name="sub">Submit</button>
                    </form>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
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