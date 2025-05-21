<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    
</head>

<body style="background-color: rgb(221, 239, 240)">
    <div class="container d-flex flex-column justify-content-center align-items-center vh-100">
        <div class="p-4">
            <div class="text-center">
                <h1 class="mb-1" style="font-family: 'Alacrity Sans', sans-serif; font-weight: bold; font-size: 5rem;">
                    Time Killer</h1>
                <img src="assets/mask_1.png" alt="Logo" class="navbar-logo mb-4" style="width: 150px; height: auto;">
            </div>
            <div class="card p-4 shadow mx-auto" style="width: 22rem;">
                <h2 class="text-center mb-3">Register</h2>
                <form action="register.php" method="POST">
                    <div class="mb-3">
                        <label for="register_type" class="form-label">Register as:</label>
                        <select class="form-select dropdown-toggle bg-white text-black" name='register_type'
                            style='text-align: center' ; id="register_type" required>
                            <option value="Customer">User</option>
                            <option value="Business">Business</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="username" class="form-label">Username:</label>
                        <input type="text" class="form-control" name="username" id="username" required>
                    </div>
                    <div class="mb-2">
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" class="form-control" name="email" id="email" required>
                    </div>
                    <div class="mb-2">
                        <label for="password" class="form-label">Password:</label> <i id="toggle-icon-password" class="fas fa-eye-slash" style="cursor: pointer;" onclick="togglePassword('password')"></i>
                        <input type="password" class="form-control" name="password" id="password" required>
                        
                    </div>
                    <div class="mb-2">
                        <label for="confirmPassword" class="form-label">Confirm Password:</label>
                        <input type="password" class="form-control" name="confirmPassword" id="confirmPassword"
                            required>
                    </div>

                    <div class="mb-2" id="business_info_field" style="display: none;">
                        <label for="business_description" class="form-label">Business Description:</label>
                        <textarea class="form-control" name="business_description" id="business_description" rows="3"></textarea>
                        <label for="BusinessKind" class="form-label">What Type of Business You Are:</label>
                        <input type="text" class="form-control" name="BusinessKind" id="BusinessKind">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Register</button>
                </form>
                <p class="mt-3 text-center">Already a member? <a href="index.php">Log in</a></p>
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

        function updateBusinessFieldsVisibility() {
            const registerType = document.getElementById('register_type');
            const businessStuff = document.getElementById('business_info_field');
            const businessDescription = document.getElementById('business_description');

            if (registerType.value === 'Business') {
                businessStuff.style.display = 'block';
                businessDescription.setAttribute('required', 'required');
            } else {
                businessStuff.style.display = 'none';
                businessDescription.removeAttribute('required');
            }
        }
        document.getElementById('register_type').addEventListener('change', function () {
            updateBusinessFieldsVisibility();
        });
        window.addEventListener('DOMContentLoaded', function () {
            updateBusinessFieldsVisibility();
        });
    </script>
</body>

</html>