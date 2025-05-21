<?php 
    require_once "../utils/db_config.php";
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $action = $_POST["action"] ?? null;
    $listID = $_POST["listing_id"] ?? null;

    switch($action) {
        case 1:
            $arr1 = addListing();
            moveImagesToDB($arr1,$listID);
            header("Location: ../views/editlistingimagepage.php?id=$listID");
            break;
        case 2:
            $picID = $_POST["pic_id"];
            $pathFP = $_POST["path"];
            deleteImage($picID);
            removeFromFP($pathFP);
            header("Location: ../views/editlistingimagepage.php?id=$listID");
            break;
        case 3:
            $picID = $_POST["pic_id"];
            $currCoverID = $_POST["cover_id"];
            $picPath = $_POST["pic_path"];
            $coverPath = $_POST["cover_path"];
            swapCoverPath($currCoverID,$picPath);
            swapCoverPath($picID,$coverPath);
            header("Location: ../views/editlistingimagepage.php?id=$listID");
            break;
    }


    function addListing() {
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
            echo $target_file;
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

    function deleteImage($imgID) {
        $conn = get_db_connection();
        if($conn -> connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }
        $query = "Delete from Listing_Pictures
        where PictureID = ?" ;
        $stmt = $conn->prepare($query);
        if(!$stmt) {
            die("prepare failed " . $conn ->error);
        }
        $stmt->bind_param("i",$imgID);
        $stmt->execute();

        $stmt->close();
        $conn->close();
    }

    function swapCoverPath($picID,$picPath) {
        $conn = get_db_connection();
        if($conn -> connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }
        $query = "Update Listing_Pictures
        set Picture = ?
        where PictureID = ?";
        $stmt = $conn->prepare($query);
        if(!$stmt) {
            die("prepare failed " . $conn ->error);
        }
        $stmt->bind_param("si",$picPath,$picID);
        $stmt->execute();

        $stmt->close();
        $conn->close();
    }

    function removeFromFP($path) {
        if(file_exists($path)) {
            echo "found it";
            if(unlink($path)) {
                error_log("file has been deleted");;
            } else {
                echo "not found";
                error_log("file not found");
            }
        }
    }
?>