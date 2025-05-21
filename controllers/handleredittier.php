<?php 
require_once "../utils/db_config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

$action = $_POST["action"];
$id = $_POST["tier_id"];
$listid = $_POST["list_id"];


switch($action) {
    case 1:
        updateTier($id);
        header("Location: ../views/edittierpage.php?id=$listid");
        break;
    case 2:
        deleteTier($id);
        header("Location: ../views/edittierpage.php?id=$listid");
        break;
}


    function updateTier($id) {
        $conn = get_db_connection();
        if ($conn->connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }

        $query = "update Price_Tiers
        set Tier_Name = ?,Price = ?, Description = ?
        where TierID = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            die("prepare failed " . $conn->error);
        }
        $desc = $_POST["tier_desc"];
        $price = $_POST["tier_price"];
        $name = $_POST["tier_name"];
        

        $stmt->bind_param("sdsi",$name,$price,$desc,$id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }

    function deleteTier($id) {
        $conn = get_db_connection();
        if ($conn->connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }

        $query = "update Price_Tiers
        set Deleted = 1
        where TierID = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            die("prepare failed " . $conn->error);
        }
        $desc = $_POST["tier_desc"];
        $price = $_POST["tier_price"];
        $name = $_POST["tier_name"];
        

        $stmt->bind_param("i",$id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }
?>