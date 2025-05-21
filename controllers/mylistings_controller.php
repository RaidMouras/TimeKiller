<?php 
    require_once "../utils/db_config.php";
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    function getBusinessListings($businessID) {
        $conn = get_db_connection();
        if ($conn->connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }

        $query = "Select * 
        from Listing
        where UserID = ? and Deleted = 0";

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            die("prepare failed " . $conn->error);
        }

        $stmt->bind_param("i", $businessID);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        $conn->close();
        return $data;

    }

    function getListingPFP($ListingID) {
        $defaultPic = "../assets/default_user.png";
        $conn = get_db_connection();
        if($conn->connect_error){
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
          }
  
        $query = "select Picture, MIN(PictureID)
                  from Listing_Pictures
                  where ListingID = ?";
        $stmt = $conn -> prepare($query);
        $stmt ->bind_param("i",$ListingID);
        $stmt-> execute();
        $result = $stmt->get_result();
        while($row = $result-> fetch_assoc()){
          $retPic = $row["Picture"];
          if(empty($retPic)) {
            return $defaultPic;
          } else if(!file_exists($retPic)) {
             return $defaultPic;
          } else {
            return $retPic;
          }
      }
    }
  
  
    function getDefaultPrice($ListingID) {
      $defaultName = "Default";
      $conn = get_db_connection();
        if($conn->connect_error){
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
          }
  
        $query = "select Price
                  from Price_Tiers
                  where ListingID = ? and Tier_Name = ?";
        $stmt = $conn -> prepare($query);
        $stmt ->bind_param("is",$ListingID,$defaultName);
        $stmt-> execute();
        $result = $stmt->get_result();
        $row = $result -> fetch_assoc();
        if(empty($row["Price"])) {
          return "No BP!";
        } else {
        return $row["Price"];
        }
    }



?>
