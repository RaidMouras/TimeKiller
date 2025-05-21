<?php 
    require_once "../utils/db_config.php";
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

     $act = $_POST["action"] ?? null;
     $id = $_POST["id"] ?? null;
     $content = $_POST["content"] ?? null;

    switch($act) {
        case 1:
            updateListingName($_POST["id"],$_POST["content"]);
            header("Location: ../views/editlisting.php?id=$id");
            break;

        case 2:
            updateLongDesc($id,$content);
            header("Location: ../views/editlisting.php?id=$id");
            break;

        case 3:
            updateShortDesc($id,$content);
            header("Location: ../views/editlisting.php?id=$id");
            break;

        case 4:
            updateLocation($id,$content);
            header("Location: ../views/editlisting.php?id=$id");
            break;

        case 5:
            updateNegotiable($id,$_POST["Negotiable"]);
            header("Location: ../views/editlisting.php?id=$id");
            break;
        default:
            //echo "it broke";
            break;
    }





    function getListinginfo($listingID) {
        $conn = get_db_connection();
        if ($conn->connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }

        $query = "Select *
        from Listing
        where ListingID = ?";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            die("prepare failed " . $conn->error);
        }
        $stmt->bind_param("i", $listingID);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        if($row = $result->fetch_assoc()) {
            $data[] = $row;
        } else {
            $data [] = null;
        }
        $stmt->close();
        $conn->close();
        return $data;
    }

    function updateListingName($id,$content) {
        $conn = get_db_connection();
        if($conn->connect_error){
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
          }

        $query = "update Listing
        set Listing_Name = ?
        where ListingID = ?";
        $stmt = $conn -> prepare($query);
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt ->bind_param("si",$content,$id);
        $stmt-> execute();
        $stmt->close();
        $conn->close();
    }

    function updateLongDesc($id,$content) {
        $conn = get_db_connection();
        if($conn->connect_error){
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
          }

        $query = "update Listing
        set Long_Desc = ?
        where ListingID = ?";
        $stmt = $conn -> prepare($query);
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt ->bind_param("si",$content,$id);
        $stmt-> execute();
        $stmt->close();
        $conn->close();
    }

    function updateShortDesc($id,$content) {
        $conn = get_db_connection();
        if($conn->connect_error){
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
          }

        $query = "update Listing
        set Short_Desc = ?
        where ListingID = ?";
        $stmt = $conn -> prepare($query);
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt ->bind_param("si",$content,$id);
        $stmt-> execute();
        $stmt->close();
        $conn->close();
    }

    function updateLocation($id,$content) {
        $conn = get_db_connection();
        if($conn->connect_error){
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
          }

        $query = "update Listing
        set Location = ?
        where ListingID = ?";
        $stmt = $conn -> prepare($query);
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt ->bind_param("si",$content,$id);
        $stmt-> execute();
        $stmt->close();
        $conn->close();
    }

    function updateNegotiable($id,$content) {
        $conn = get_db_connection();
        if($conn->connect_error){
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
          }
        if($content == "Yes") {
            $negot = 1;
        } else {
            $negot = 0;
        }
        $query = "update Listing
        set Negotiable = ?
        where ListingID = ?";
        $stmt = $conn -> prepare($query);
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt ->bind_param("si",$negot,$id);
        $stmt-> execute();
        $stmt->close();
        $conn->close();
    }
?>