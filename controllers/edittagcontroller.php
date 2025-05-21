<?php 
require_once "../utils/db_config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);



    function getTags($id) {
        $data = [];
        $conn = get_db_connection();
        if ($conn->connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }

        $query = "Select DISTINCT(Tag_Name),Listing_Tag.TagID
        from Tags 
        join Listing_Tag on Tags.TagID = Listing_Tag.TagID
        where Listing_Tag.ListingID = ?";
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

    Function getUnusedTags($id) {
        $data = [];
        $conn = get_db_connection();
        if ($conn->connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }

        $query = "Select DISTINCT(Tag_Name),Listing_Tag.TagID
                from Tags 
                join Listing_Tag on Tags.TagID = Listing_Tag.TagID
                where Tag_Name not in (Select DISTINCT(Tag_Name)
                from Tags 
                join Listing_Tag on Tags.TagID = Listing_Tag.TagID
                      where ListingID = ?)";
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


?>