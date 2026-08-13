<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");  // Redirect to login page if not logged in
    exit;
}
$mysqli = require __DIR__ . "/database.php";
$success = $error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get form data
    $first_name = $_POST["first_name"];
    $last_name = $_POST["last_name"];
    $email = $_POST["email"];
    $contact_number = $_POST["contact_number"];
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Valid email is required";
    } else {
        // Check if email already exists but belongs to someone else
        $stmt = $mysqli->prepare("SELECT id FROM user WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $_SESSION["user_id"]);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email already taken";
        } else {
            // Update user information
            $stmt = $mysqli->prepare("UPDATE user SET first_name = ?, last_name = ?, email = ?, contact_number = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $first_name, $last_name, $email, $contact_number, $_SESSION["user_id"]);
            
            if ($stmt->execute()) {
                $success = "Profile updated successfully!";
            } else {
                $error = "Error updating profile: " . $mysqli->error;
            }
        }
    }
}
function getUserStatus() {
    if (isset($_SESSION["user_id"])) {
        $mysqli = require __DIR__ . "/database.php";
        $stmt = $mysqli->prepare("SELECT first_name, last_name FROM user WHERE id = ?");
        $stmt->bind_param("i", $_SESSION["user_id"]);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }
    return null;
}
$current_user = getUserStatus();

// Get current user info
$stmt = $mysqli->prepare("SELECT * FROM user WHERE id = ?");
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Acme&family=Dancing+Script:wght@400..700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Lobster&family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400..700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/mystyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
   
    <title>Edit Profile</title>
    <style>
        body {
            background: linear-gradient(135deg, #3f704d 0%, #043927 100%);
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 800px;
            margin: 60px auto;
            padding: 0;
            background: transparent;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            animation: slideInUp 0.6s ease-out;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .form-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .form-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
            color: white;
        }

        h1 {
            font-size: 2.2rem;
            margin: 0;
            color: #333;
            font-weight: 700;
        }

        .form-subtitle {
            color: #666;
            margin-top: 8px;
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .label-icon {
            color: #667eea;
            font-size: 16px;
        }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-group input:focus {
            border-color: #667eea;
            outline: none;
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .form-group input:hover {
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 35px;
            flex-wrap: wrap;
        }

        .form-buttons a,
        .form-buttons button {
            padding: 15px 30px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            text-decoration: none;
            color: #fff;
            min-width: 140px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .form-buttons a::before,
        .form-buttons button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .form-buttons a:hover::before,
        .form-buttons button:hover::before {
            left: 100%;
        }

        .cancel-btn {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            box-shadow: 0 8px 20px rgba(240, 147, 251, 0.3);
        }

        .cancel-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(240, 147, 251, 0.4);
        }

        .submit-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(102, 126, 234, 0.4);
        }

        .alert {
            padding: 16px 20px;
            margin-bottom: 25px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInDown 0.4s ease-out;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 2px solid #28a745;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 2px solid #dc3545;
        }

        .alert i {
            font-size: 18px;
        }

        .error-message {
            color: #dc3545 !important;
            font-size: 12px !important;
            display: block !important;
            margin-top: 5px !important;
            font-weight: 500 !important;
            animation: shake 0.5s ease-in-out;
        }

        /* Animations */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                margin: 30px 20px;
            }

            .form-card {
                padding: 30px 25px;
            }

            h1 {
                font-size: 1.8rem;
            }

            .form-buttons {
                flex-direction: column;
                align-items: center;
            }

            .form-buttons a,
            .form-buttons button {
                min-width: 200px;
            }
        }

        /* Loading state */
        .form-card.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .form-card.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 30px;
            height: 30px;
            border: 3px solid #667eea;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            transform: translate(-50%, -50%);
        }

        @keyframes spin {
            to {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }
    </style>
</head>
<body>
<?php include 'headers/header.php'; ?>
    <div class="container">
        <div class="form-card">
            <div class="form-header">
            
                <h1>Edit Profile</h1>
                <p class="form-subtitle">Update your account information</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="post" action="edit_profile.php" id="edit-form">
                <div class="form-group">
                    <label for="first_name">
                        <i class="fas fa-user label-icon"></i>
                        First Name
                    </label>
                    <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user["first_name"]) ?>" required>
                </div>

                <div class="form-group">
                    <label for="last_name">
                        <i class="fas fa-user label-icon"></i>
                        Last Name
                    </label>
                    <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user["last_name"]) ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope label-icon"></i>
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user["email"]) ?>" required>
                </div>

                <div class="form-group">
                    <label for="contact_number">
                        <i class="fas fa-phone label-icon"></i>
                        Contact Number
                    </label>
                    <input type="text" id="contact_number" name="contact_number" value="<?= htmlspecialchars($user["contact_number"] ?? "") ?>" required>
                </div>

                <div class="form-buttons">
                    <a href="account.php" class="cancel-btn">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleMenu() {
            const menu = document.querySelector('.menu');
            const hamburger = document.querySelector('.hamburger');
            const header = document.querySelector('.page-header');
            menu.classList.toggle('active');
            header.classList.toggle('hidden');
            if (menu.classList.contains('active')) {
                hamburger.style.display = 'none';
            } else {
                hamburger.style.display = 'block';
            }
        }
    </script>

    <script>
    // Wait for the DOM to be fully loaded
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById("edit-form");
        const formCard = document.querySelector('.form-card');

        form.addEventListener("submit", function(event) {
            let errors = {};
            let hasErrors = false;

            // Validate Name
            const first_name = document.getElementById("first_name").value.trim();
            if (first_name === "") {
                errors.first_name = "First Name is required";
                hasErrors = true;
            }
            
            const last_name = document.getElementById("last_name").value.trim();
            if (last_name === ""){
                errors.last_name = "Last Name is required";
                hasErrors = true;
            }

            // Validate Email
            const email = document.getElementById("email").value.trim();
            const emailRegex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
            if (email === "" || !emailRegex.test(email)) {
                errors.email = "A valid email is required";
                hasErrors = true;
            }

            // Validate Contact Number
            const contactNumber = document.getElementById("contact_number").value.trim();
            const contactNumberRegex = /^\+?[0-9]{10,15}$/; // Allow optional country code and 10-15 digits
            if (contactNumber === "") {
                errors.contact_number = "Contact number is required";
                hasErrors = true;
            } else if (!contactNumberRegex.test(contactNumber)) {
                errors.contact_number = "Invalid contact number format";
                hasErrors = true;
            }
            
            // If there are errors, prevent form submission and display errors
            if (hasErrors) {
                event.preventDefault();
                
                // Remove any existing error messages
                document.querySelectorAll('.error-message').forEach(el => el.remove());
                
                // Display new error messages
                for (const field in errors) {
                    const input = document.getElementById(field);
                    const errorSpan = document.createElement('span');
                    errorSpan.className = 'error-message';
                    errorSpan.textContent = errors[field];
                    input.parentNode.appendChild(errorSpan);
                    
                    // Add shake animation to input
                    input.style.animation = 'shake 0.5s ease-in-out';
                    setTimeout(() => {
                        input.style.animation = '';
                    }, 500);
                }
            } else {
                // Add loading state
                formCard.classList.add('loading');
            }
        });

        // Remove error messages on input focus
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                const errorMsg = this.parentNode.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.remove();
                }
            });
        });
    });
    </script>
</body>
</html>