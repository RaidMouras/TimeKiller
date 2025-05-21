<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'utils/db_config.php';
?>
<html lang=en>

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged in</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>

<body style="background-color: rgb(221, 239, 240)">
<div class="container d-flex flex-column justify-content-center align-items-center vh-100">
<div class="p-4">
 <div class="text-center">
    <h1 class="mb-2" style="font-family: 'Alacrity Sans', sans-serif; font-weight: bold; font-size: 5rem;">Time Killer</h1>
    <img src="assets/mask_1.png" alt="Logo" class="navbar-logo mb-4" style="width: 150px; height: auto;">
    </div>
<div class="card p-4 shadow mx-auto" style="width: 22rem;">
<?php
     $conn = get_db_connection();

    $email = $_POST["email"];
    $stmt = $conn->prepare("SELECT * FROM Users WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0){
      $row = $result->fetch_assoc();
      if(((int)$row["Banned"])!=0){
        echo("<p class='mt-3 text-center'>You have been banned from using this service.</p>");
        $stmt->close();
        $conn->close();
        exit;
      }

      if(password_verify($_POST["password"], $row["Password"])){
        echo "<h1>Login successful!</h1>";
        $_SESSION["user_id"] = $row["UserID"];
        $_SESSION["email"] = $email;
        $_SESSION["is_admin"] = (int)$row["Is_Admin"];
        $_SESSION["user_type"] = $row["User_Type"];
        $_SESSION["username"] = $row["Username"];
        
        if((int)$row["Is_Admin"] === 1){
            header("Location: admin/admin.php");
            $stmt->close();
            $conn->close();
            exit;
        }

        if($_SESSION["user_type"] == "Customer"){
            header("Location: home.php");
            $stmt->close();
            $conn->close();
            exit;
        } else {
            header("Location: ../views/businesshub.php");
            $stmt->close();
            $conn->close();
            exit;  
        }
      } else {
        echo "<p class='mt-3 text-center'>Incorrect password!</p>";
        echo "<p class='mt-3 text-center'>Return to Log in page:</p><a href = 'index.php' class='text-center'>Log In</a>";
      }
    }else{
      echo "<p class='mt-3 text-center'>User not found</p>";
      echo "<p class='mt-3 text-center'>Try Registering first:</p><a href = 'registerForm.php' class='text-center'>Register</a>";
      echo "<p class='mt-3 text-center'>Or return to Log in page:</p><a href = 'index.php' class='text-center'>Log In</a>";
    }
  $stmt->close();
  
?>
</div>
</div>
</div>
</body>
</html>