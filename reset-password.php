<?php
$token = $_GET["token"] ?? null; 
if ($token === null) {
    die("Token is required.");
}
$token_hash = hash("sha256", $token);
$mysqli = require __DIR__ . "/database.php";
// Check if the database connection was successful
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}
$sql = "SELECT * FROM user WHERE reset_token_hash = ?";
$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    die("Failed to prepare statement: " . $mysqli->error);
}
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if ($user === null) {
    die("Token not found.");
}
if (strtotime($user["reset_token_expires_at"]) <= time()) {
    die("Token has expired.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rainbow Forest Paradise Resort - Reset Password</title>
    <style>
        :root {
            --primary-color: #2e8b57;
            --secondary-color: #98fb98;
            --accent-color: #006400;
            --text-color: #333;
            --background-color: #f0fff0;
            --form-bg-color: #ffffff;
            --button-color: #2e8b57;
            --button-hover-color: #3cb371;
            --error-color: #ff6b6b;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px 0;
            text-align: center;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
        
        .logo {
            width: 50px;
            height: 50px;
            margin-right: 10px;
            border-radius: 50px;
        }
        
        h1 {
            margin: 0;
            color: white;
            font-size: 24px;
        }
        
        .content {
            background-color: var(--form-bg-color);
            padding: 30px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .form-title {
            color: var(--primary-color);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: var(--accent-color);
            font-weight: bold;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 16px;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .toggle-password:focus {
            outline: none;
        }
        
        .password-requirements {
            margin-top: 8px;
            font-size: 13px;
            color: #666;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 3px;
        }
        
        .requirement-icon {
            margin-right: 5px;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 11px;
            color: white;
        }
        
        .requirement-icon.valid {
            background-color: #4CAF50;
        }
        
        .requirement-icon.invalid {
            background-color: #ccc;
        }
        
        button {
            background-color: var(--button-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            transition: background-color 0.3s;
        }
        
        button:hover {
            background-color: var(--button-hover-color);
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            color: var(--accent-color);
            font-size: 14px;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: var(--form-bg-color);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 400px;
            width: 90%;
            position: relative;
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from {transform: translateY(-50px); opacity: 0;}
            to {transform: translateY(0); opacity: 1;}
        }
        
        .modal-title {
            color: var(--primary-color);
            margin-top: 0;
        }
        
        .modal-button {
            background-color: var(--button-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
            transition: background-color 0.3s;
        }
        
        .modal-button:hover {
            background-color: var(--button-hover-color);
        }
        
        .error-message {
            color: var(--error-color);
            font-weight: bold;
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid var(--error-color);
            border-radius: 5px;
            background-color: rgba(255, 107, 107, 0.1);
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-container">
                <img src="images/rainbow-logo.png" alt="Rainbow Forest Paradise Resort Logo" class="logo">
                <h1>Rainbow Forest Paradise Resort</h1>
            </div>
        </div>
        
        <div class="content">
            <h2 class="form-title">Reset Your Password</h2>
            <form method="post" action="process-reset-password.php">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" required>
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password')">👁️</button>
                    </div>
                    <div class="password-requirements">
                        <div class="requirement" id="req-length">
                            <span class="requirement-icon invalid">✓</span>
                            <span>At least 8 characters</span>
                        </div>
                        <div class="requirement" id="req-letter">
                            <span class="requirement-icon invalid">✓</span>
                            <span>At least 1 letter</span>
                        </div>
                        <div class="requirement" id="req-number">
                            <span class="requirement-icon invalid">✓</span>
                            <span>At least 1 number</span>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <div class="password-container">
                        <input type="password" id="password_confirmation" name="password_confirmation" required>
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password_confirmation')">👁️</button>
                    </div>
                    <div class="password-requirements">
                        <div class="requirement" id="req-match">
                            <span class="requirement-icon invalid">✓</span>
                            <span>Passwords match</span>
                        </div>
                    </div>
                </div>
                
                <button type="submit">Reset Password</button>
            </form>
        </div>
        
        <div class="footer">
            <p>© 2025 Rainbow Forest Paradise Resort. All rights reserved.</p>
        </div>
    </div>
    
    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Success!</h3>
            <p>Your password has been successfully updated.</p>
            <a href="login.php" class="modal-button">Go to Login</a>
        </div>
    </div>
    
    <!-- Error Modal -->
    <div id="errorModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Error</h3>
            <p id="errorMessage"></p>
            <button class="modal-button" onclick="closeErrorModal()">Try Again</button>
        </div>
    </div>
    
    <div id="formErrorMessage" class="error-message"></div>
    
    <script>
        // Function to show the success modal
        function showSuccessModal() {
            document.getElementById('successModal').style.display = 'flex';
        }
        
        // Function to show error modal
        function showErrorModal(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('errorModal').style.display = 'flex';
        }
        
        // Function to close error modal
        function closeErrorModal() {
            document.getElementById('errorModal').style.display = 'none';
        }
        
        // Function to show form error
        function showFormError(message) {
            const errorElement = document.getElementById('formErrorMessage');
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                errorElement.style.display = 'none';
            }, 5000);
        }
        
        // Toggle password visibility
        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
            } else {
                input.type = "password";
            }
        }
        
        // Live password validation
        function validatePassword() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirmation').value;
            
            // Check length
            const reqLength = document.getElementById('req-length');
            if (password.length >= 8) {
                reqLength.querySelector('.requirement-icon').classList.replace('invalid', 'valid');
            } else {
                reqLength.querySelector('.requirement-icon').classList.replace('valid', 'invalid');
            }
            
            // Check for letter
            const reqLetter = document.getElementById('req-letter');
            if (/[a-z]/i.test(password)) {
                reqLetter.querySelector('.requirement-icon').classList.replace('invalid', 'valid');
            } else {
                reqLetter.querySelector('.requirement-icon').classList.replace('valid', 'invalid');
            }
            
            // Check for number
            const reqNumber = document.getElementById('req-number');
            if (/[0-9]/.test(password)) {
                reqNumber.querySelector('.requirement-icon').classList.replace('invalid', 'valid');
            } else {
                reqNumber.querySelector('.requirement-icon').classList.replace('valid', 'invalid');
            }
            
            // Check passwords match
            const reqMatch = document.getElementById('req-match');
            if (password === confirmPassword && password !== '') {
                reqMatch.querySelector('.requirement-icon').classList.replace('invalid', 'valid');
            } else {
                reqMatch.querySelector('.requirement-icon').classList.replace('valid', 'invalid');
            }
        }
        
        // Add event listeners for password validation
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('password_confirmation');
            
            passwordInput.addEventListener('input', validatePassword);
            confirmInput.addEventListener('input', validatePassword);
        });
        
        // Form submission handling
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Clear any existing error messages
            document.getElementById('formErrorMessage').style.display = 'none';
            
            // Validate passwords
            const password = document.getElementById('password').value;
            const passwordConfirmation = document.getElementById('password_confirmation').value;
            
            // Client-side validation
            if (password === '') {
                showFormError('Please input password');
                return;
            } else if (password.length < 8) {
                showFormError('Password too short (minimum 8 characters)');
                return;
            } else if (!/[a-z]/i.test(password)) {
                showFormError('Password must contain at least one letter');
                return;
            } else if (!/[0-9]/.test(password)) {
                showFormError('Password must contain at least one number');
                return;
            } else if (password !== passwordConfirmation) {
                showFormError('Passwords don\'t match');
                return;
            }
            
            // If validation passes, submit the form via AJAX
            const formData = new FormData(this);
            
            fetch('process-reset-password-ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessModal();
                } else {
                    showErrorModal(data.message);
                }
            })
            .catch(error => {
                showErrorModal('An error occurred. Please try again later.');
                console.error('Error:', error);
            });
        });
    </script>
</body>
</html>