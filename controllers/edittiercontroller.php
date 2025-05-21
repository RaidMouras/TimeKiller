<?php 
require_once "../utils/db_config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);


    function getTiers($id) {
        $data = [];
        $conn = get_db_connection();
        if ($conn->connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }

        $query = "Select TierID,Tier_Name,Price,Description
        from Price_Tiers
        where ListingID = ? and Deleted = 0";
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

    function getLowestID($id,$tierID) {
        $conn = get_db_connection();
        if ($conn->connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }

        $query = "Select MIN(TierID) as mini
        from Price_Tiers
        where ListingID = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            die("prepare failed " . $conn->error);
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if($row = $result->fetch_assoc()){
            return $row["mini"];
        } else {
            return $tierID;
        }
    }

?>