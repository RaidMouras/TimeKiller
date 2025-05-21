<?php 
require_once "../utils/db_config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

//check for errors such as other user trying to use get stuff

    $listID = $_POST["id"];
    addTier($listID);
    header("Location: ../views/edittierpage.php?id=$listID");



    function addTier($listingID){
    $conn = get_db_connection();
    if($conn -> connect_error) {
        echo "<h1>Connection failed?</h1>";
        die("Connection failed: " . $conn->connect_error);
    }
    $query = "insert into Price_Tiers(ListingID,Tier_Name,Price,Description)
    VALUES(?,?,?,?)";
    $stmt = $conn ->prepare($query);
    if(!$stmt) {
        die("prepare failed " . $conn ->error);
    }
    $tierName = $_POST["tier_name"];
    $price = $_POST["price"];
    $longDesc = $_POST["long_desc"];
    $stmt->bind_param("isds",$listingID,$tierName,$price,$longDesc);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    }
?>