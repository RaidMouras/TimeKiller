<?php
    require_once "./utils/db_config.php";
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
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
          $stmt->close(); 
          $conn->close();
          return $data;
    }

    function getLocations() {
        $conn = get_db_connection();
        if($conn->connect_error){
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
          }

          $query = "Select distinct(location)
          from Listing";
          $stmt = $conn ->prepare($query);
          $stmt-> execute();
          $result = $stmt->get_result();
          $data = [];
          while($row = $result->fetch_assoc()) {
            $data[] = $row;
          }
          
          $stmt->close(); 
          $conn->close();
          return $data;
    }
    

   function getListings($search, $order, $location, $rating,$tagselected = [])
    {
        $conn = get_db_connection();
        if ($conn->connect_error) {
            echo "<h1>Connection failed?</h1>";
            die("Connection failed: " . $conn->connect_error);
        }

        $query = "Select Listing.*,
                        GROUP_CONCAT(CONCAT(Price_Tiers.Tier_Name, ':', Price_Tiers.Price)) 
                        AS Price_Tiers,
                        AVG(Review.Star_Rating) AS avg_rating,
                        MAX(CASE 
                             WHEN Listing_Name LIKE ? THEN 2
                            WHEN Tags.Tag_Name LIKE ? THEN 1
                            ELSE 0
                        END) AS relevance
                        From Listing 
                        Left join Price_Tiers on Listing.ListingID = Price_Tiers.ListingID 
                        Left join Review on Listing.ListingID = Review.ListingID
                        Left join Listing_Tag on Listing.ListingID = Listing_Tag.ListingID
                        Left join Tags ON Listing_Tag.TagID = Tags.TagID
                        where (Listing_Name like ? OR Tags.Tag_Name LIKE ?)";

        if($location != ""){
            $query = $query . " AND Location = ?";
        }
        if (!empty($tagselected)) {
        // Create a condition for matching tags, using IN for multiple tags
        $query .= " AND Listing.ListingID IN (
                        SELECT DISTINCT Listing_Tag.ListingID 
                        FROM Listing_Tag 
                        WHERE Listing_Tag.TagID IN (" . implode(",", array_fill(0, count($tagselected), "?")) . ")
                    )";
         }   
        

        $query = $query . " Group By Listing.ListingID";
        
        if(intval($rating ) <= 1){
            $query = $query . " HAVING (avg_rating >= ? OR avg_rating IS NULL)";
        }else{
            $query = $query . " HAVING (avg_rating >= ?)";
        }
        //$query = $query . " HAVING (avg_rating >= ?)";

        if ($order === 'Ascending') {
            $query = $query . " ORDER BY relevance DESC, Price_Tiers.Price ASC, Listing_Name ASC";
        } else if ($order === 'Descending') {
            $query = $query . " ORDER BY relevance DESC, Price_Tiers.Price DESC, Listing_Name ASC";
        }else{
            $query .= " ORDER BY relevance DESC, avg_rating DESC, Listing_Name ASC";
        }
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            die("prepare failed " . $conn->error);
        }
           $params = [];
        $types = 'ssss'; 
        $params[] = "%" . $search . "%"; 
        $params[] = "%" . $search . "%";
        $params[] = "%" . $search . "%";
        $params[] = "%" . $search . "%";


    if ($location != "") {
        $types .= 's'; 
        $params[] = $location;
    }

    foreach ($tagselected as $tagId) {
        $types .= 'i'; 
        $params[] = $tagId;
    }

    $types .= 'i'; 
    $params[] = $rating;

    $stmt->bind_param($types, ...$params);

        
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
      $defaultPic = "./assets/default_user.png";
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
        } else if(file_exists($retPic)) {
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