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

//require "../controllers/edittagcontroller.php";

$page_title = "Edit Tags";
include_once '../utils/header.php';

$id = $_GET["id"];
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

        form {
            margin: 0px;
            border: 0px;
        }

        input[type = "submit"] {
            margin-left: 10px;
            padding-right: 2rem;
            padding-left: 2rem;
            max-width: 5rem;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
<div class="d-flex justify-content-center align-items-center">
        <div class="bg-white p-5 rounded shadow">
            <h4>Are you sure you want to delete this listing</h4>
            <div class="d-flex justify-content-center">
                <form action="../views/editlisting.php" method="get">
                    <input type="hidden" name="id" value="<?php echo $id;?>">
                    <input type="submit" class="btn btn-outline-danger" value="no">
                </form>
                <form action="../controllers/handlerdeletelisting.php" method="post">
                    <input type="submit" class="btn btn-outline-success" value="Yes">
                    <input type="hidden" name="id" value="<?php echo $id?>">
                </form>
            </div>
        </div>
</div>
</body>