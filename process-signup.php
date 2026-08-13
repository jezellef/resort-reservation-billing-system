<?php
session_start();

// Initialize error messages array
$errorMessages = [];
$registrationSuccess = false;

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validate First Name
    if (empty($_POST["first_name"])) {
        $errorMessages[] = "First Name is required";
    }

    // Validate Last Name
    if (empty($_POST["last_name"])) {
        $errorMessages[] = "Last Name is required";
    }

    // Validate Email
    if (empty($_POST['email'])) {
        $errorMessages[] = "Email is required";
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errorMessages[] = "Valid email format is required";
    }

    // Validate Contact Number
    if (empty($_POST['contact_number'])) {
        $errorMessages[] = "Contact number is required";
    } else {
        // Remove any non-numeric characters
        $contact_number = preg_replace("/[^0-9]/", "", $_POST['contact_number']);
        
        // Check if the contact number is valid (length)
        if (strlen($contact_number) < 10 || strlen($contact_number) > 15) {
            $errorMessages[] = "Contact number must be between 10 and 15 digits";
        }
        
        // Only check format if we have a number with valid length
        elseif (!preg_match("/^\+?[0-9]\d{9,14}$/", $contact_number)) {
            $errorMessages[] = "Invalid contact number format";
        }
    }

    // Validate Password
    if (empty($_POST['password'])) {
        $errorMessages[] = "Password is required";
    } elseif (strlen($_POST['password']) < 8) {
        $errorMessages[] = "Password must be at least 8 characters long";
    } elseif (!preg_match('/[a-z]/i', $_POST['password'])) {
        $errorMessages[] = "Password must contain at least one letter";
    } elseif (!preg_match('/[0-9]/', $_POST['password'])) {
        $errorMessages[] = "Password must contain at least one number";
    }
    
    // Validate Password Confirmation
    if (empty($_POST['password_confirmation'])) {
        $errorMessages[] = "Password confirmation is required";
    } elseif ($_POST['password'] !== $_POST['password_confirmation']) {
        $errorMessages[] = "Passwords don't match";    
    }

    // If there are any error messages, display them and stop further execution
    if (count($errorMessages) > 0) {
        foreach ($errorMessages as $message) {
            echo $message . "<br>";
        }
        exit; // Stop further execution if there are validation errors
    }

    // If no validation errors, proceed with registration
    try {
        $mysqli = require __DIR__ . "/database.php";
        
        if (!$mysqli) {
            throw new Exception("Database connection failed");
        }

        // Prepare password hash and activation token
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $activation_token = bin2hex(random_bytes(16));
        $activation_token_hash = hash("sha256", $activation_token);   

        // Check if the email already exists
        $checkEmailQuery = $mysqli->prepare("SELECT * FROM user WHERE email = ?");
        
        if (!$checkEmailQuery) {
            throw new Exception("Email check preparation failed: " . $mysqli->error);
        }
        
        $checkEmailQuery->bind_param("s", $_POST['email']);
        $checkEmailQuery->execute();
        $result = $checkEmailQuery->get_result();

        if ($result->num_rows > 0) {
            echo "This email is already registered.";
            $checkEmailQuery->close();
            exit;
        }
        
        $checkEmailQuery->close();

        // Insert new user
        $sql = "INSERT INTO user (first_name, last_name, email, contact_number, password_hash, account_activation_hash) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $mysqli->prepare($sql);

        if (!$stmt) {
            throw new Exception("Preparation failed: " . $mysqli->error);
        }

        // Use the processed contact number for insertion
        $stmt->bind_param("ssssss", 
            $_POST['first_name'], 
            $_POST['last_name'], 
            $_POST['email'], 
            $contact_number, 
            $password_hash, 
            $activation_token_hash
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Execution failed: " . $stmt->error);
        }
        
        // Check if the user was inserted
        if ($mysqli->affected_rows <= 0) {
            throw new Exception("User registration failed - no rows affected");
        }
        
        $stmt->close();

        // Send activation email
        $mail = require __DIR__ . "/mailer.php";
        $mail->setFrom("rainbowforestparadiseresortandcampsite@gmail.com", "Rainbow Forest Paradise Resort and Campsite");
        $mail->addAddress($_POST["email"]);
        $mail->Subject = "Account Activation - Rainbow Forest Paradise Resort";
        $mail->isHTML(true); // Set email format to HTML
    
        // Correct activation link with the token parameter
        $activationLink = "https://rainbowforestparadiseresortandcampsite.com/activate-account.php?token=" . urlencode($activation_token);
        
        // Create a professional HTML email with header, logo, and styling
        $mail->Body = <<<END
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Account Activation</title>
            <style>
                body {
                    font-family: 'Arial', sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                }
                .email-container {
                    max-width: 600px;
                    margin: 0 auto;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    overflow: hidden;
                }
                .email-header {
                    background-color: #3a7734;
                    color: white;
                    padding: 20px;
                    text-align: center;
                }
                .logo {
                    max-width: 90px;
                    height: auto;
                    border-radius: 50px;
                }
                .email-content {
                    padding: 30px;
                    background-color: #fff;
                }
                .email-footer {
                    background-color: #f5f5f5;
                    padding: 15px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                }
                .button {
                    display: inline-block;
                    background-color: #4CAF50;
                    color: white;
                    text-decoration: none;
                    padding: 12px 25px;
                    margin: 20px 0;
                    border-radius: 4px;
                    font-weight: bold;
                }
                .contact-info {
                    margin-top: 25px;
                    padding-top: 15px;
                    border-top: 1px solid #eee;
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="email-header">
                 <img src="https://rainbowforestparadiseresortandcampsite.com/images/rainbow-logo.png" alt="Rainbow Forest Paradise Resort Logo" class="logo">
                    <h1>Rainbow Forest Paradise Resort & Campsite</h1>
                </div>
                
                <div class="email-content">
                    <h2>Welcome to Rainbow Forest Paradise Resort!</h2>
                    <p>Dear {$_POST['first_name']},</p>
                    <p>Thank you for registering with Rainbow Forest Paradise Resort & Campsite. We're excited to have you join our community!</p>
                    <p>To complete your registration and activate your account, please click the button below:</p>
                    
                    <div style="text-align: center;">
                        <a href="$activationLink" class="button">Activate My Account</a>
                    </div>
                    
                    <p>If the button above doesn't work, you can also copy and paste the following link into your browser:</p>
                    <p style="word-break: break-all;"><a href="$activationLink">$activationLink</a></p>
                    
                    <div class="contact-info">
                        <p><strong>Need assistance?</strong> Contact our customer support:</p>
                        <p>Email: support@rainbowforestparadiseresortandcampsite.com</p>
                        <p>Phone: (123) 456-7890</p>
                        <p>Address: Rainbow Forest Paradise Resort & Campsite, 123 Forest Drive, Paradise City</p>
                    </div>
                </div>
                
                <div class="email-footer">
                    <p>&copy; 2025 Rainbow Forest Paradise Resort & Campsite. All rights reserved.</p>
                    <p>This email was sent to you because you registered on our website. If you didn't register, please disregard this email.</p>
                </div>
            </div>
        </body>
        </html>
        END;
        
        // Add a plain text version for email clients that don't support HTML
        $mail->AltBody = "Dear {$_POST['first_name']},\n\n" .
                        "Thank you for registering with Rainbow Forest Paradise Resort and Campsite. " .
                        "To activate your account, please visit the following link:\n\n" .
                        "$activationLink\n\n" .
                        "If you need assistance, please contact our customer support at support@rainbowforestparadiseresortandcampsite.com or call (123) 456-7890.\n\n" .
                        "Best regards,\n" .
                        "Rainbow Forest Paradise Resort & Campsite Team";
    
        $mail->send();
        $registrationSuccess = true;
        
    } catch (Exception $e) {
        echo "Registration failed: " . $e->getMessage();
        exit;
    }
}

// If we have a successful registration, show the modal
if ($registrationSuccess) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: #fefefe;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            text-align: center;
            animation: modalopen 0.5s;
        }
        
        @keyframes modalopen {
            from {opacity: 0; transform: scale(0.8);}
            to {opacity: 1; transform: scale(1);}
        }
        
        .modal-header {
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            color: #3a7734;
            margin: 0;
            font-size: 28px;
        }
        
        .modal-body {
            margin-bottom: 25px;
        }
        
        .modal-body p {
            font-size: 18px;
            line-height: 1.6;
            color: #444;
        }
        
        .success-icon {
            color: #4CAF50;
            font-size: 60px;
            margin-bottom: 20px;
        }
        
        .modal-footer button {
            padding: 12px 24px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .modal-footer button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="success-icon">✓</div>
            <div class="modal-header">
                <h2>Registration Successful!</h2>
            </div>
            <div class="modal-body">
                <p>Please check your email to activate your account.</p>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="redirectToLogin()">Continue to Login</button>
            </div>
        </div>
    </div>

    <script>
        function redirectToLogin() {
            window.location.href = 'login.php';
        }
        
        // If user clicks outside of the modal, redirect to login
        window.onclick = function(event) {
            var modal = document.getElementById('successModal');
            if (event.target == modal) {
                redirectToLogin();
            }
        }
        
        // Automatically redirect after 5 seconds
        setTimeout(function() {
            redirectToLogin();
        }, 5000);
    </script>
</body>
</html>
<?php
    exit; // Stop further execution
}
?>