<?php
require_once "../utils/db_config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit;
//checks if user came from addlisting page if not put them there
}
    if(empty($_POST)&& empty($_GET)){
        header("Location: /views/newlisting.php");
        exit;
    }
    

    if(!empty($_GET["action"])) {
        
        $list = $_GET["id"];
        $arr = getListUserID($list);
        if($_SESSION["user_id"]!= $arr["UserID"]) {//checks if its the user deleting his own entry and not just someone filling in get requests for another users listing
            echo "Error";
        } else {
        $tagid = $_GET["tagid"];
        deleteTags($list,$tagid);
        header("Location: ../views/edittagpage.php?id=$list");
        }
        
    }
    if(!empty($_POST)){
    $id  = $_POST["id"];
    addTags($id,$_POST["tags"]);
    header("Location: ../views/edittagpage.php?id=$id");
    }


    function addTags($listingID,$tags) {
        $conn = get_db_connection();
        if($conn -> connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }
        $query = "Insert into Listing_Tag(TagID,ListingID)
        VALUES(?,?)";
        $stmt = $conn->prepare($query);
        if(!$stmt) {
            die("prepare failed " . $conn ->error);
        }
            foreach($tags as $tag) {
            $stmt->bind_param("ii", $tag, $listingID);
            $stmt->execute();
            }
            $stmt->close();
            $conn->close();
    }

    function deleteTags($listingID,$tag) {
        $conn = get_db_connection();
        if($conn -> connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }
        $query = "Delete from Listing_Tag
        where ListingID = ? and TagID = ?";
        $stmt = $conn->prepare($query);
        if(!$stmt) {
            die("prepare failed " . $conn ->error);
        }
            
            $stmt->bind_param("ii", $listingID,$tag);
            $stmt->execute();
            
            $stmt->close();
            $conn->close();
    }

    function getListUserID($listID) {
        $conn = get_db_connection();
        if($conn -> connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }
        $query = "Select UserID
        from Listing
        where ListingID = ?";
        $stmt = $conn->prepare($query);
        if(!$stmt) {
            die("prepare failed " . $conn ->error);
        }

        $stmt->bind_param("i", $listID);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result -> fetch_assoc();
        return $row;
    }

?>