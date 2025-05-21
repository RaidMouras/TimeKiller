<html lang="en">
  
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

  </head>
  
  <body style="background-color: rgb(221, 239, 240)">
  
  <div class="container d-flex flex-column justify-content-center align-items-center vh-100">
  <div class="p-4">
  <div class="text-center">
    <h1 class="mb-2" style="font-family: 'Alacrity Sans', sans-serif; font-weight: bold; font-size: 5rem;">Time Killer</h1>
    <img src="assets/mask_1.png" alt="Logo" class="navbar-logo mb-4" style="width: 150px; height: auto;">
    </div>
  <div class="card p-4 shadow mx-auto" style="width: 22rem;">
    <h2 class="text-center mb-3">Login</h2>
    <form action="login.php" method="POST">
        <div class="mb-3">
          <label for="email" class="form-label">Email:</label>
          <input type="text" class="form-control" name="email" id="email" required><br><br>
        </div>
        <div class="mb-3">
          <label for="password" class="form-label">Password:</label> <i id="toggle-icon-password" class="fas fa-eye-slash" style="cursor: pointer;" onclick="togglePassword('password')"></i>
          <input type="password" class="form-control" name="password" id="password" required><br><br>
        </div>
          <button type="submit" class="btn btn-primary w-100">Login</button>
      </form>

      <p class="mt-3 text-center">Not a member? <a href = "registerForm.php">Sign Up</a></p>
    </div>
   </div>
   </div>

   <script>
   function togglePassword(id) {
            var passwordField = document.getElementById(id);
            var icon = document.getElementById('toggle-icon-' + id);
            if (passwordField.type === "password") {
                passwordField.type = "text";
                
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                passwordField.type = "password";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }
   </script>
  </body>
</html>