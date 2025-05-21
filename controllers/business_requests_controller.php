<!DOCTYPE html>
<HTMl>
    <head>
        <title>Loading</title>
    </head>
</HTMl>
<?php

use Dom\Document;

require_once '../utils/db_config.php';
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
//checks if user logged in if so continue
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit;
//checks if user came from addlisting page if not put them there
}
    if(empty($_POST)){
        header("Location: /views/newlisting.php");
        exit;
    }
    $check = $_POST["submit_al"];
    
   
    //addListingPicMultible();
    //addListing();
    //moveListingTagsToDB($_POST["tags"],1);
    //addTierForListingDefault(1);

    $id = addListing();
    $arr1 = addListingPicMultible();
    moveImagesToDB($arr1,$id);
    moveListingTagsToDB($_POST["tags"],$id);
    addTierForListingDefault($id);
    header("Location: ../views/newlisting.php");


    function addListing() {
        $conn = get_db_connection();

        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        $maxid = getMaxRow();

        $userid = $_SESSION['user_id'];
        $listingName = $_POST["service_name"];
        $longDesc = $_POST["long_desc"];
        $shortDesc = $_POST["thumbnail_text"];
        $location = $_POST["location"];

        if(isset($_POST["negotiable"])) {
            $negotiable = 1;
        } else {
            $negotiable = 0;
        }

        $query = "insert into Listing(ListingID,UserID,Listing_Name,Long_Desc,Short_Desc,Location,Negotiable)
        Values(?,?,?,?,?,?,?)";

        $stmt = $conn -> prepare($query);
        $stmt ->bind_param("iissssi",$maxid,$userid,$listingName,$longDesc,$shortDesc,$location,$negotiable);
        $stmt ->execute();
        $stmt->close();
        $conn->close();
        return $maxid;
    }
    


    function addListingPicMultible(){
                if(!isset($_FILES["listing_image"])) {
            echo "having trouble";
        } else{

        $filepaths = [];
        $target_dir = "../uploads/Listing_pics/";
        
    
        foreach($_FILES["listing_image"]["tmp_name"] as $index => $tmp_name){
            $uploadOk = 1;
            $fileName =basename($_FILES["listing_image"]["name"][$index]);
            $target_file = $target_dir . $fileName;
            $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
        if(isset($_POST["submit_al"])) {
            $check = getimagesize($tmp_name);
            if($check !== false) {
                echo "File is an image - " . $check["mime"] . ".";
                $uploadOk = 1;
            } else {
                echo "File is not an image.";
                $uploadOk = 0;
            }


        }

        if(file_exists($target_file)) {
            $target_file = changeNameIfExists($target_file);
        }

        if($_FILES["listing_image"]["size"][$index]>5000000){
            echo "Sorry your file is too large";
            $uploadOk = 0;
        }

        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != 'jpeg') {
            echo "Sorry, the only formats excepted are jpg,png,jpeg";
            $uploadOk = 0;
        }

        if($uploadOk ==0) {
            echo "Sorry your file was not uploaded";
        } else {
            if(move_uploaded_file($tmp_name, $target_file)) {
                $filepaths[] = $target_file;
                echo "The file " . htmlspecialchars($fileName) . " has been uploaded.";
            } else {
                echo "Sorry there was an error uploading your file.";
            }
        }

        }
    }
    return $filepaths;
    }

    function moveImagesToDB($images,$listingID){
        $conn = get_db_connection();
        if($conn -> connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }
        $query = "Insert into Listing_Pictures(ListingID,Picture)
        Values(?, ?)" ;
        $stmt = $conn->prepare($query);
        if(!$stmt) {
            die("prepare failed " . $conn ->error);
        }
        foreach($images as $index){
            $stmt->bind_param("is", $listingID, $index);
            $stmt->execute();
        }
        $stmt->close();
        $conn->close();
    }

    function getMaxRow() {
        $conn = get_db_connection();
        if($conn -> connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }
        $query = "select MAX(ListingID)+1 as nextID
        from Listing";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
       if($row = $result->fetch_assoc()) {
            $nextID = $row["nextID"];
        }
        if($nextID === NULL) {
            return 1;
       } else {
            return $nextID;
        }
    }

    function moveListingTagsToDB($tags,$listingID) {
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
            //work on preventing error from repeat tags
    }

    function addTierForListingDefault($listingID){
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
        $tierName = "Default";
        $price = $_POST["price"];
        $longDesc = $_POST["long_desc"];
        $stmt->bind_param("isds",$listingID,$tierName,$price,$longDesc);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }

    function checkImageNameExists($path) {
        $conn = get_db_connection();
        if($conn -> connect_error) {
        echo "<h1>Connection failed?</h1>";
        die("Connection failed: " . $conn->connect_error);
        }
        $query = "select *
        from Listing_Pictures
        where Picture = ?";
        $stmt = $conn ->prepare($query);
        if(!$stmt) {
            die("prepare failed " . $conn ->error);
        }
        $stmt->bind_param("s",$path);
        $stmt ->execute();
        $result = $stmt -> get_result();
        if($result ->num_rows != 0) {
            $stmt->close();
            $conn->close();
            return true;
        } else {
            $stmt->close();
            $conn->close();
            return false;
        }
    }

    function changeNameIfExists($path) {
        if(!str_contains($path,".")){
            return $path;
        }
        $arr2 = explode(".",$path);
        $extension = array_pop($arr2);
        $filePathName = implode(".",$arr2);
        $counter = 1;
        $result = $path;
        while (file_exists($result)) {
            $result = $filePathName . "_" . $counter . "." . $extension;
            $counter++;
        }

        return $result;
    }


?>