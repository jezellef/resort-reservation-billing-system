<?php
session_start();
include 'db_connect.php'; // Ensure database connection consistency

if (isset($_SESSION['admin_id'])) {
    header("Location: admin.php");
    exit;
}

$login_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, "email", FILTER_SANITIZE_EMAIL);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {
        $login_error = "All fields are required.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, email, password, role FROM admin_users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            if (password_verify($password, $admin["password"])) {
                    session_regenerate_id();
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_name'] = $admin['name'];
                    $_SESSION['role'] = $admin['role']; 
                    header("Location: admin.php");
                    exit;
            } else {
                $login_error = "Invalid login credentials.";
            }
        } else {
            $login_error = "Invalid login credentials.";
        }

        $stmt->close(); 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Rainbow Forest Paradise</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
          body {
            background-color: #eaf2e3; 
        }
       
        .login-container {
            max-width: 600px;
            margin: 90px auto;
            margin-top: 120px;
        }
        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 12px;
        }
        .card-header {
            background-color: #14532d; /* Dark green */
            color: white;
            text-align: center;
            padding: 1.5rem;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }
        .resort-name {
            font-size: 22px;
            font-weight: bold;
            line-height: 1.3;
        }
        .admin-login-text {
            font-size:18px;
            margin-top: 2px;
            opacity: 0.9;
        }
        .input-group-text {
            background-color: #3b6d3b;
            color: white;
            border: none;
        }
        .btn-primary {
            background-color: #14532d;
            border: none;
        }
        .btn-primary:hover {
            background-color: #295629; /* Darker green */
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="card">
            <div class="card-header">
                <h4 class="resort-name">Rainbow Forest Paradise<br>Resort & Campsite</h4>
                <p class="admin-login-text">Admin Login</p>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($login_error)): ?>
                    <div class="alert alert-danger"><?php echo $login_error; ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter email" required>

                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                        </div>
                        <input type="checkbox" id="showPassword"> Show Password
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Login</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        const passwordInput = document.getElementById('password');
        const showPasswordCheckbox = document.getElementById('showPassword');

        showPasswordCheckbox.addEventListener('change', function() {
            if (this.checked) {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
