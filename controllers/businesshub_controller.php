<?php
    require_once '../utils/db_config.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $id = isset($_SESSION["UserID"]) ? (int)$_SESSION["user_id"] : null;//if exist sets it to int else leaves it null



    function getBusinessInfo($userID){
        try{
            $conn = get_db_connection();

            if($conn->connect_error){
                echo "<h1>Connection failed?</h1>";
                die("Connection failed: " . $conn->connect_error);
              }

              $stmt = $conn->prepare("select * from Business WHERE UserID = ?");
              $stmt->bind_param("s", $userID);
              $stmt->execute();
              $result = $stmt->get_result();

              if($result -> num_rows>0){
                $row = $result ->fetch_assoc();

                $_SESSION["business_name"] = $row["Business_Name"];
                $_SESSION["location"] = isset($row["Location"]) ? $row["Location"] : null;
                $_SESSION["business_bio"] = isset($row["Bio"]) ? $row["Bio"] : null;
                $_SESSION["profile_picture"] = isset($row["Profile_Picture"]) ? $row["Profile_Picture"] : null;
                $_SESSION["business_type"] = isset($row["Business_Type"]) ? $row["Business_Type"] : null;
              }

        } catch(Exception $e) {
            error_log("Error in getBusinessInfo: " . $e->getMessage());
        }
    }

    function getTags() {
        $conn = get_db_connection();
        if($conn->connect_error){
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
          }

          $query = "Select *
          from Tags";
          $stmt = $conn ->prepare($query);
          $stmt-> execute();
          $result = $stmt->get_result();
          $data = [];
          while($row = $result->fetch_assoc()) {
            $data[] = $row;
          }
          return $data;
    }

    function getBusinessReviewScore($BusinessID) {
      $conn = get_db_connection();
      if($conn -> connect_error) {
      echo "<h1>Connection failed?</h1>";
      die("Connection failed: " . $conn->connect_error);
      }

      $query = "select AVG(Star_Rating) AS Average
      from Review
      where ListingID IN(Select ListingID from Listing where UserID = ?)";
      $stmt = $conn ->prepare($query);
      $stmt->bind_param("i", $BusinessID);
      $stmt ->execute();
      $result = $stmt -> get_result();
      if($row = $result->fetch_assoc()) {
        $Average = $row["Average"];
        if($Average === NULL) {
          return 0.00;
     } else {
          return round($Average,2);
      }
      }

  }

  Function getServicesSold($BusinessID) {
    $conn = get_db_connection();
      if($conn -> connect_error) {
      echo "<h1>Connection failed?</h1>";
      die("Connection failed: " . $conn->connect_error);
      }

      // Query for direct tier purchases
      $query1 = "SELECT COUNT(PurchaseID) as direct_counter
        FROM Purchase_History_Tier
        WHERE TierID IN(SELECT TierID
        FROM Listing
        JOIN Price_Tiers ON Listing.ListingID = Price_Tiers.ListingID
        WHERE UserID = ?)";
      
      // Query for negotiation purchases
      $query2 = "SELECT COUNT(phn.PurchaseID) as negotiation_counter
        FROM Purchase_History_Negotiation phn
        JOIN Negotiations n ON phn.NegotiationID = n.NegotiationID
        JOIN Listing l ON n.ListingID = l.ListingID
        WHERE l.UserID = ? AND n.Current_Status = 'Accepted'";
      
      // Execute first query (direct purchases)
      $stmt = $conn->prepare($query1);
      $stmt->bind_param("i", $BusinessID);
      $stmt->execute();
      $result = $stmt->get_result();
      $row1 = $result->fetch_assoc();
      $direct_count = $row1["direct_counter"] ?? 0;
      $stmt->close();
      
      // Execute second query (negotiation purchases)
      $stmt = $conn->prepare($query2);
      $stmt->bind_param("i", $BusinessID);
      $stmt->execute();
      $result = $stmt->get_result();
      $row2 = $result->fetch_assoc();
      $negotiation_count = $row2["negotiation_counter"] ?? 0;
      $stmt->close();
      
      // Return the sum of both types
      return $direct_count + $negotiation_count;
  }

  function getTotalReviews($BusinessID) {
    $conn = get_db_connection();
      if($conn -> connect_error) {
      echo "<h1>Connection failed?</h1>";
      die("Connection failed: " . $conn->connect_error);
      }

      $query = "Select COUNT(ReviewID) as counter
      from Review
      JOIN Listing ON Review.ListingID = Listing.ListingID
      where Listing.UserID = ?";
      $stmt = $conn ->prepare($query);
      $stmt->bind_param("i", $BusinessID);
      $stmt ->execute();
      $result = $stmt -> get_result();
      if($row = $result->fetch_assoc()) {
        $sum = $row["counter"];
      }
      if($sum === NULL) {
          return 0;
      } else {
        return $sum;
    }
  }

  function getProfit($BusinessID) {
    $conn = get_db_connection();
      if($conn -> connect_error) {
      echo "<h1>Connection failed?</h1>";
      die("Connection failed: " . $conn->connect_error);
      }

      // Query for profit from direct tier purchases
      $query1 = "SELECT SUM(Price) as tier_profit
      FROM Purchase_History_Tier
      JOIN Price_Tiers ON Purchase_History_Tier.TierID = Price_Tiers.TierID
      JOIN Listing ON Price_Tiers.ListingID = Listing.ListingID
      WHERE Listing.UserID = ?";
      
      // Query for profit from negotiation purchases
      $query2 = "SELECT SUM(n.Price) as negotiation_profit
      FROM Purchase_History_Negotiation phn
      JOIN Negotiations n ON phn.NegotiationID = n.NegotiationID
      JOIN Listing l ON n.ListingID = l.ListingID
      WHERE l.UserID = ? AND n.Current_Status = 'Accepted'";
      
      // Execute first query (profit from direct purchases)
      $stmt = $conn->prepare($query1);
      $stmt->bind_param("i", $BusinessID);
      $stmt->execute();
      $result = $stmt->get_result();
      $row1 = $result->fetch_assoc();
      $tier_profit = $row1["tier_profit"] ?? 0;
      $stmt->close();
      
      // Execute second query (profit from negotiation purchases)
      $stmt = $conn->prepare($query2);
      $stmt->bind_param("i", $BusinessID);
      $stmt->execute();
      $result = $stmt->get_result();
      $row2 = $result->fetch_assoc();
      $negotiation_profit = $row2["negotiation_profit"] ?? 0;
      $stmt->close();
      
      // Return the sum of profits from both types
      return $tier_profit + $negotiation_profit;
  }
?>