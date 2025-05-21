<?php 
require_once "../utils/db_config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);



    function getImagePath($id) {
            $data = [];
            $conn = get_db_connection();
            if ($conn->connect_error) {
                echo "<h1>Connection failed?</h1>";
                die("Connection failed: " . $conn->connect_error);
            }
    
            $query = "Select *
            from Listing_Pictures
            where ListingID = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                die("prepare failed " . $conn->error);
            }
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
            $conn->close();
            return $data;
        }

    function checkIfCover($Picid,$id) {
        $conn = get_db_connection();
            if ($conn->connect_error) {
                echo "<h1>Connection failed?</h1>";
                die("Connection failed: " . $conn->connect_error);
            }
    
            $query = "Select MIN(PictureID) As Minimum
            from Listing_Pictures
            where ListingID = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                die("prepare failed " . $conn->error);
            }
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if($row = $result->fetch_assoc()) {
                $minid = $row["Minimum"];
                if($minid == $Picid) {
                    return true;
                } else {
                    return false;
                }
            }
            $stmt->close();
            $conn->close();
    }

    function checkIfLimitReached($id) {
        $conn = get_db_connection();
            if ($conn->connect_error) {
                echo "<h1>Connection failed?</h1>";
                die("Connection failed: " . $conn->connect_error);
            }
    
            $query = "Select COUNT(PictureID) as counter
            from Listing_Pictures
            where ListingID = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                die("prepare failed " . $conn->error);
            }
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if($row = $result->fetch_assoc()) {
                if($row["counter"]< 10) {
                    return false;
                } else {
                    return true;
                }
            }
            return false;
    }
?>