<?php
require_once 'utils/db_config.php';
?>
<html lang=en>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body style="background-color: rgb(221, 239, 240)">
    <div class="container d-flex flex-column justify-content-center align-items-center vh-100">
        <div class="p-4">
            <div class="text-center">
                <h1 class="mb-2" style="font-family: 'Alacrity Sans', sans-serif; font-weight: bold; font-size: 5rem;">
                    Time Killer</h1>
                <img src="assets/mask_1.png" alt="Logo" class="navbar-logo mb-4" style="width: 150px; height: auto;">
            </div>
            <div class="card p-4 shadow mx-auto" style="width: 22rem;">
                <?php
                $conn = get_db_connection();

                $email = htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8');

                $getStmt = $conn->prepare("SELECT * FROM Users WHERE Email = ?");
                $getStmt->bind_param("s", $email);
                $getStmt->execute();
                $result = $getStmt->get_result();

                $password = htmlspecialchars($_POST['password'], ENT_QUOTES, 'UTF-8');
                $password_confirm = htmlspecialchars($_POST['confirmPassword'], ENT_QUOTES, 'UTF-8');
                if ($password != $password_confirm) {
                    echo "<p class='mt-3 text-center'>Passwords did not match!</p><p class='mt-3 text-center'>Try Registering again:</p><a href = 'registerForm.php' class='mt-3 text-center'>Register</a>";
                } else {
                    if ($result->num_rows > 0) {
                        echo "<p class='mt-3 text-center'>This email has already been registered for our service! Please log in here:</p><a href = 'index.php' class='mt-3 text-center'>Log in</a>";
                        $getStmt->close();
                        $conn->close();
                        exit;
                    } else {
                        $getStmt->close();

                        $password = password_hash($password, PASSWORD_DEFAULT);
                        $user_type = htmlspecialchars($_POST['register_type'], ENT_QUOTES, 'UTF-8');
                        $user_name = htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8');

                        $insertStmt = $conn->prepare("INSERT INTO Users (Email, Password, User_Type, Banned, Is_Admin) VALUES (?, ?, ?, 0, 0)");
                        $insertStmt->bind_param("sss", $email, $password, $user_type);
                        $insertStmt->execute();

                        $registeredId = $conn->insert_id;

                        if ($user_type == "Customer") {
                            $profileStmt = $conn->prepare("INSERT INTO Customers (UserId, Username, Verified) VALUES (?, ?, 0)");
                            $profileStmt->bind_param("is", $registeredId, $user_name);
                            $profileStmt->execute();
                        } else {
                            $buisness_desc = htmlspecialchars($_POST['business_description'], ENT_QUOTES, 'UTF-8');
                            $services = htmlspecialchars($_POST['BusinessKind'], ENT_QUOTES, 'UTF-8');
                            $profileStmt = $conn->prepare("INSERT INTO Business (UserId, Business_Name, Business_Type, Bio) VALUES (?, ?, ?, ?)");
                            $profileStmt->bind_param("isss", $registeredId, $user_name, $services, $buisness_desc);
                            $profileStmt->execute();
                        }

                        $insertStmt->close();
                        echo "<p class='mt-3 text-center'>Registration successful! Please Log in: </p><a href = 'index.php' class='mt-3 text-center'>Log In</a>";

                        $profileStmt->close();
                        $conn->close();
                        exit;
                    }
                }
                ?>
            </div>
        </div>
    </div>
</body>

</html>