<?php 
    require_once "../utils/db_config.php";
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }
    error_reporting(E_ALL);
    ini_set('display_errors', 1);


    $listid = $_POST["id"];
    deleteListing($listid);
    header("Location: ../views/mylistings.php");


        function deleteListing($id) {
            $conn = get_db_connection();
        if($conn -> connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }
        $query = "update Listing
        set Deleted = 1
        where ListingID = ?" ;
        $stmt = $conn->prepare($query);
        if(!$stmt) {
            die("prepare failed " . $conn ->error);
        }
        $stmt->bind_param("i",$id);
        $stmt->execute();
        
        $stmt->close();
        $conn->close();
        }
?>