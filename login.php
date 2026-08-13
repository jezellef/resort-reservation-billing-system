<?php
session_start(); // Start the session at the beginning

// Check if there are session values for check-in and check-out
if (isset($_GET['check_in']) && isset($_GET['check_out'])) {
    $_SESSION['check_in'] = $_GET['check_in'];
    $_SESSION['check_out'] = $_GET['check_out'];
}

// Check where the user is coming from and store the appropriate redirection page
if (!isset($_SESSION['redirect_after_login'])) {
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    
    if (strpos($referrer, 'booking_form.php') !== false) {
        $_SESSION['redirect_after_login'] = $referrer;
        $_SESSION['from_booking_form'] = true;
    } elseif (strpos($referrer, 'guest_reservation.php') !== false) {
        $_SESSION['redirect_after_login'] = $referrer;
    } elseif (strpos($referrer, 'index.php') !== false) {
        $_SESSION['redirect_after_login'] = 'index.php';
    } else {
        // Default redirect if no specific referrer
        $_SESSION['redirect_after_login'] = 'index.php';
    }
}

// Initialize error message variable and invalid flag
$error_message = "";
$if_invalid = false;

// Handle the login process when the form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mysqli = require __DIR__ . "/database.php";
    $email = $_POST["email"] ?? '';
    $password = $_POST["password"] ?? '';
    $stmt = $mysqli->prepare("SELECT * FROM user WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        // First check if the account is activated
        if ($user["account_activation_hash"] !== null) {
            $if_invalid = true;
            $error_message = "Please activate your account";
        }
        // Then check if password is correct
        elseif (password_verify($password, $user["password_hash"])) {
            session_regenerate_id(true); // Regenerate session ID for security
            $_SESSION["user_id"] = $user["id"];

            // After successful login, check if the user is redirected from booking form step 3
            if (isset($_SESSION['reservation_data']) && isset($_GET['redirect']) && $_GET['redirect'] == 'booking_form_step_3') {
                $reservation_data = $_SESSION['reservation_data'];

                // Unset reservation data from session to avoid it being stored indefinitely
                unset($_SESSION['reservation_data']);

                // Redirect the user back to Step 3 of the booking form with prefilled data
                header("Location: booking_form.php?check_in=" . $reservation_data['check_in'] .
                       "&check_out=" . $reservation_data['check_out'] .
                       "&rooms=" . urlencode(serialize($reservation_data['rooms'])) . 
                       "&adults=" . $reservation_data['adults'] . 
                       "&children=" . $reservation_data['children']);
                exit();
            }

            // Regular redirect logic after login (if no reservation data)
            // Check if the user should be redirected back to booking form step 3
            if (isset($_SESSION['from_booking_form']) && $_SESSION['from_booking_form']) {
                // Ensure the booking data is passed to Step 3 with all session data
                header("Location: booking_form.php?step=3&check_in=" . $_SESSION['check_in'] .
                       "&check_out=" . $_SESSION['check_out'] .
                       "&rooms=" . urlencode(serialize($_SESSION['reservation_data']['rooms'])) .
                       "&adults=" . $_SESSION['reservation_data']['adults'] .
                       "&children=" . $_SESSION['reservation_data']['children']);
                exit();
            }

            // If no specific redirection from booking form, redirect to the saved referrer or homepage
            header("Location: " . $_SESSION['redirect_after_login']);
            exit();
        } else {
            // Password is incorrect
            $if_invalid = true;
            $error_message = "Email or password incorrect";
        }
    } else {
        // User not found
        $if_invalid = true;
        $error_message = "Email or password incorrect";
    }
}

// Check if there's a signup success message
$signup_success = isset($_GET['signup']) && $_GET['signup'] === 'success';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="styles/style.css">
    <script src="https://cdn.jsdelivr.net/npm/just-validate@3.0.1/dist/js/just-validate.min.js"></script>
    <style>
        /* Container size adjustments */
        .container {
            max-width: 1000px; /* Increased from default */
            min-height: 600px; /* Increased height */
            width: 90%; /* Use percentage for responsiveness */
            margin: 20px auto; /* Center the container */
        }
        
        /* Form container adjustments */
        .form-container {
            width: 50%; /* Ensure forms use half the container space */
            padding: 30px 40px; /* More padding for better spacing */
        }
        
        .toggle-container {
            width: 50%; /* Ensure toggle side uses half the container */
        }
        
        /* Add these styles to position the eye icon inside the input */
        .password-container {
            position: relative;
            width: 100%;
        }
        
        .password-container input {
            width: 100%;
            padding-right: 40px; /* Make room for the icon */
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
        }
        
        .toggle-password:hover {
            color: #333;
        }
        
        /* Fix any conflicts with other styles */
        .form-container input {
            box-sizing: border-box;
        }

        /* Success modal styles */
        .success-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .success-modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 50%;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .success-modal h2 {
            color: #4CAF50;
            margin-bottom: 15px;
        }

        .success-modal p {
            margin-bottom: 20px;
        }

        .success-modal button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .success-modal button:hover {
            background-color: #45a049;
        }
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            max-width: 400px;
            text-align: center;
        }
        
        /* Logo styling */
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
            padding-top: 20px;
        }
        
        .logo-container img {
            max-width: 100px;
            max-height: 100px;
            height: auto;
            border-radius: 100px; 
            
        }
        .logo-container h1{
            color:  white;
        }
        
        /* Go back button styling - Changed to yellow */
        .back-button-container {
            text-align: center;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        .back-button {
            background-color: #FFD700; /* Changed from #555 to yellow (#FFD700) */
            color: black; /* Changed text color to black for better contrast */
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .back-button:hover {
            background-color: #F4C430; /* Darker yellow on hover */
        }
        
        .back-button i {
            margin-right: 5px;
        }
        
        /* Error message styling */
        .error-message {
            color: red;
            font-size: 14px;
            margin-top: 5px;
            margin-bottom: 10px;
        }
        .mobile-form-toggle {
            display: none;
        }
        @media screen and (max-width: 768px) {
        .mobile-form-toggle {
            display: block !important;
            text-align: center;
            margin: 20px auto;
            max-width: 400px;
            width: 90%;
        }
    
        .mobile-toggle-btn {
            background: linear-gradient(45deg, #2196F3, #1976D2);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            max-width: 300px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }
    
        .mobile-toggle-btn:hover {
            background: linear-gradient(45deg, #1976D2, #1565C0);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
        }
    
        .mobile-toggle-btn i {
            margin-right: 8px;
        }
    
        /* Logo stays the same */
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
            padding: 20px;
        }
        
        .logo-container img {
            max-width: 80px;
            max-height: 80px;
        }
        
        .logo-container h1 {
            color: white;
            font-size: 18px;
            margin: 10px 0;
            line-height: 1.2;
        }
    
        /* Container - simple centered box */
         .container {
            width: 90% !important;
            max-width: 400px !important;
            min-height: auto !important;
            height: auto !important; /* Add this */
            margin: 20px auto !important;
            padding: 0 !important;
            display: block !important;
            background: white !important;
            border-radius: 15px !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
            overflow: visible !important; /* Make sure this is visible */
            position: relative !important;
        }
    
        /* Hide toggle container on mobile */
        .toggle-container {
            display: none !important;
        }
    
        .form-container {
        width: 100% !important;
        padding: 20px 30px 30px 30px !important; /* Add top padding */
        position: relative !important;
        transform: none !important;
        transition: all 0.3s ease !important;
        background: transparent !important;
        top: auto !important;
        left: auto !important;
        height: auto !important;
        min-height: auto !important; /* Add this */
        display: block !important;
        opacity: 1 !important;
        z-index: 1 !important;
        margin-bottom: 0 !important;
        box-sizing: border-box !important; /* Add this */
    }
    .form-container h1 {
        text-align: center;
        margin: 0 0 25px 0 !important; /* Reset margins */
        padding: 0 !important;
        color: #333;
        font-size: 24px;
    }
    
        /* Initially show only login form */
        .form-container.sign-in {
            border-bottom: none;
        }
    
        /* Hide signup form initially, will show below login when button is clicked */
        .form-container.sign-up {
            display: none !important;
            border-top: 2px solid #f0f0f0;
            margin-top: 0;
        }
    
         /* Signup form specific adjustments when visible */
    .form-container.sign-up.mobile-visible {
        display: block !important;
        border-top: 2px solid #f0f0f0;
        margin-top: 0 !important;
        padding-top: 30px !important;
    }

    /* Ensure form doesn't get cut off */
    .form-container form {
        width: 100% !important;
        height: auto !important;
        overflow: visible !important;
        display: block !important;
    }
       .form-container input {
        width: 100% !important;
        padding: 15px !important;
        margin: 10px 0 !important;
        border: 1px solid #ddd !important;
        border-radius: 8px !important;
        font-size: 16px !important;
        box-sizing: border-box !important;
        display: block !important; /* Ensure inputs are block level */
        visibility: visible !important; /* Force visibility */
        opacity: 1 !important; /* Force opacity */
    }

    
        .form-container input:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 8px rgba(76, 175, 80, 0.3);
        }
    
        .form-container button {
            width: 100% !important;
            padding: 15px !important;
            margin: 20px 0 15px !important;
            background: #4CAF50 !important;
            color: white !important;
            border: none !important;
            border-radius: 8px !important;
            font-size: 16px !important;
            font-weight: bold !important;
            cursor: pointer !important;
        }
    
        .form-container button:hover {
            background: #45a049 !important;
        }
    
          /* Password container adjustments */
    .password-container {
        position: relative;
        margin: 10px 0;
        width: 100% !important;
        display: block !important;
    }

    .password-container input {
        padding-right: 50px !important;
        margin: 0 !important;
        width: 100% !important;
    }
    
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 18px;
            padding: 5px;
        }
    
        /* Links and text */
        .form-container p {
            text-align: center;
            margin: 15px 0;
            font-size: 14px;
        }
    
        .form-container a {
            color: #4CAF50;
            text-decoration: none;
        }
    
         .error-message {
        color: #e74c3c;
        font-size: 14px;
        margin: 5px 0 10px 0 !important;
        padding: 0 !important;
        display: block !important;
    }
     .form-container.sign-up input:first-of-type,
    .form-container.sign-up #first_name {
        margin-top: 0 !important;
        padding: 15px !important;
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        position: relative !important;
        z-index: 10 !important;
    }

        /* Back button adjustments */
        .back-button-container {
            margin: 30px 0 20px;
            text-align: center;
        }
    
        .back-button {
            padding: 12px 25px;
            font-size: 16px;
            border-radius: 20px;
        }
    
        /* Success modal */
        .success-modal-content {
            width: 90%;
            max-width: 350px;
            margin: 20% auto;
        }
    }
    </style>
</head>
<body>
<!-- Logo at the top -->
<div class="logo-container">
    <img src="images/rainbow-logo.png" alt="Company Logo">
    <h1>Rainbow Forest Paradise Resort and Campsite</h1>
</div>

<div id="successModal" class="success-modal" <?php if($signup_success): ?>style="display: block;"<?php endif; ?>>
    <div class="success-modal-content">
        <h2><i class="fas fa-check-circle"></i> Registration Successful!</h2>
        <p>Thank you for signing up! A confirmation email has been sent to your email address. Please verify your account to complete the registration process.</p>
        <button onclick="closeSuccessModal()">Continue</button>
    </div>
</div>

<div class="container" id="container">
    <div class="form-container sign-up">
        <form action="process-signup.php" method="post" id="signup-form" novalidate>
            <h1>Create Account</h1>
            
            <input type="text" id="first_name" name="first_name" placeholder="First Name">
            <div id="first_name-error" class="error-message"></div>

            <input type="text" id="last_name" name="last_name" placeholder="Last Name">
            <div id="last_name-error" class="error-message"></div>
            
            <input type="email" id="email" name="email" placeholder="Email" required>
            <div id="email-error" class="error-message"></div>
            
            <input type="text" id="contact_number" name="contact_number" placeholder="Contact Number" required>
            <div id="contact_number-error" class="error-message"></div>
            
            <div class="password-container">
                <input type="password" id="password" name="password" placeholder="Password" required>
                <i class="fa-solid fa-eye toggle-password" data-toggle="password"></i>
            </div>
            <div id="password-error" class="error-message"></div>
            
            <div class="password-container">
                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirm Password" required>
                <i class="fa-solid fa-eye toggle-password" data-toggle="password_confirmation"></i>
            </div>
            <div id="password_confirmation-error" class="error-message"></div>
            
            <button type="submit">Sign up</button>
        </form>
    </div>

    <!-- Sign In form -->
    <div class="form-container sign-in">
        <form method="post" id="login-form" novalidate>
            <h1>Log In</h1>
            <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST["email"] ?? "") ?>">
            
            <div class="password-container">
                <input type="password" class="form-control" name="password" id="login-password" placeholder="Password" required>
                <i class="fa-solid fa-eye toggle-password" data-toggle="login-password"></i>
            </div>
            
            <button type="submit">Log in</button>
            <?php if ($if_invalid): ?>
                <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
            <?php endif; ?>
            <p><a href="forgot-password.php">Forgot your password?</a></p>
        </form>
    </div>

    <div class="toggle-container">
        <div class="toggle">
            <div class="toggle-panel toggle-left">
                <h1>Welcome Back!</h1>
                <p>Enter your personal details to use all of site features</p>
                <button class="hidden" id="login">Log in</button>
            </div>
            <div class="toggle-panel toggle-right">
                <h1>Create your Account!</h1>
                <p>Register with your personal details to use all of site features</p>
                <button class="hidden" id="register">Sign up</button>
            </div>
        </div>
    </div>
</div>
<div class="mobile-form-toggle">
    <button class="mobile-toggle-btn" id="mobile-toggle-btn" onclick="toggleMobileForms()">
        <i class="fas fa-user-plus"></i> Create your Account
    </button>
</div>

<!-- Go back button at the bottom -->
<div class="back-button-container">
    <button class="back-button" onclick="goBack()">
        <i class="fas fa-arrow-left"></i> Go Back
    </button>
</div>

<script>
function closeSuccessModal() {
    document.getElementById('successModal').style.display = 'none';
}

function closeActivationModal() {
    document.getElementById('activationModal').style.display = 'none';
}

function goBack() {
    window.history.back();
}

// Mobile form toggle function - now stacks forms instead of switching
function toggleMobileForms() {
    const signUpForm = document.querySelector('.form-container.sign-up');
    const toggleBtn = document.getElementById('mobile-toggle-btn');
    
    // Check if we're on mobile
    if (window.innerWidth <= 768) {
        if (signUpForm.classList.contains('mobile-visible')) {
            // Hide signup form
            signUpForm.classList.remove('mobile-visible');
            signUpForm.style.display = 'none';
            toggleBtn.innerHTML = '<i class="fas fa-user-plus"></i> Create your Account';
            toggleBtn.style.background = 'linear-gradient(45deg, #2196F3, #1976D2)';
        } else {
            // Show signup form below login form
            signUpForm.classList.add('mobile-visible');
            signUpForm.style.display = 'block';
            toggleBtn.innerHTML = '<i class="fas fa-times"></i> Hide Sign Up Form';
            toggleBtn.style.background = 'linear-gradient(45deg, #ff6b6b, #ee5a52)';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to all toggle-password icons
    const togglePassword = document.querySelectorAll('.toggle-password');
    togglePassword.forEach(function(element) {
        element.addEventListener('click', function() {
            // Get the target input field
            const targetId = this.getAttribute('data-toggle');
            const passwordInput = document.getElementById(targetId);
            
            // Toggle password visibility
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            }
        });
    });
    
    // Desktop toggle forms functionality
    const container = document.getElementById('container');
    const registerBtn = document.getElementById('register');
    const loginBtn = document.getElementById('login');

    if (registerBtn) {
        registerBtn.addEventListener('click', () => {
            container.classList.add("active");
        });
    }

    if (loginBtn) {
        loginBtn.addEventListener('click', () => {
            container.classList.remove("active");
        });
    }
    
    // Initialize mobile view - ensure correct initial state
    if (window.innerWidth <= 768) {
        const signInForm = document.querySelector('.form-container.sign-in');
        const signUpForm = document.querySelector('.form-container.sign-up');
        const toggleBtn = document.getElementById('mobile-toggle-btn');
        
        if (signInForm && signUpForm && toggleBtn) {
            // Login form always visible
            signInForm.style.display = 'block';
            // Signup form hidden initially
            signUpForm.style.display = 'none';
            signUpForm.classList.remove('mobile-visible');
            // Button shows "Create Account"
            toggleBtn.innerHTML = '<i class="fas fa-user-plus"></i> Create your Account';
            toggleBtn.style.background = 'linear-gradient(45deg, #2196F3, #1976D2)';
        }
    }
    
    // Form validation for signup
    const signupForm = document.getElementById('signup-form');
    if (signupForm) {
        signupForm.addEventListener("submit", function(event) {
            let errors = {};

            // Validate First Name
            const first_name = document.getElementById("first_name").value.trim();
            if (first_name === "") {
                errors.first_name = "First Name is required";
            }
            
            // Validate Last Name
            const last_name = document.getElementById("last_name").value.trim();
            if (last_name === "") {
                errors.last_name = "Last Name is required";
            }

            // Validate Email
            const email = document.getElementById("email").value.trim();
            const emailRegex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
            if (email === "" || !emailRegex.test(email)) {
                errors.email = "A valid email is required";
            }

            // Validate Contact Number
            const contactNumber = document.getElementById("contact_number").value.trim();
            const contactNumberRegex = /^\+?[0-9]{10,15}$/;
            if (contactNumber === "") {
                errors.contact_number = "Contact number is required";
            } else if (!contactNumberRegex.test(contactNumber)) {
                errors.contact_number = "Invalid contact number format";
            }

            // Validate Password
            const password = document.getElementById("password").value.trim();
            if (password === "") {
                errors.password = "Password is required";
            } else if (password.length < 8) {
                errors.password = "Password must be at least 8 characters";
            } else if (!/[a-z]/i.test(password)) {
                errors.password = "Password must contain at least one letter";
            } else if (!/[0-9]/.test(password)) {
                errors.password = "Password must contain at least one number";
            }

            // Validate Password Confirmation
            const passwordConfirmation = document.getElementById("password_confirmation").value.trim();
            if (password !== passwordConfirmation) {
                errors.password_confirmation = "Passwords don't match";
            }

            // If there are any errors, show them and prevent form submission
            if (Object.keys(errors).length > 0) {
                event.preventDefault();

                // Clear previous error messages
                const errorFields = document.querySelectorAll('.error-message');
                errorFields.forEach(field => field.textContent = '');

                // Display new error messages
                for (const [field, message] of Object.entries(errors)) {
                    const errorElement = document.getElementById(`${field}-error`);
                    if (errorElement) {
                        errorElement.textContent = message;
                    }
                }
                
                // If signup form is not visible on mobile, make it visible so user can see errors
                if (window.innerWidth <= 768) {
                    const signUpForm = document.querySelector('.form-container.sign-up');
                    const toggleBtn = document.getElementById('mobile-toggle-btn');
                    if (signUpForm && !signUpForm.classList.contains('mobile-visible')) {
                        signUpForm.classList.add('mobile-visible');
                        signUpForm.style.display = 'block';
                        toggleBtn.innerHTML = '<i class="fas fa-times"></i> Hide Sign Up Form';
                        toggleBtn.style.background = 'linear-gradient(45deg, #ff6b6b, #ee5a52)';
                    }
                }
            }
        });
    }

    // Sign-In Form Validation (using JustValidate)
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        const signInValidation = new JustValidate('#login-form');
        signInValidation
            .addField('input[name="email"]', [
                { rule: 'required', errorMessage: 'Email is required' },
                { rule: 'email', errorMessage: 'Email is not valid' }
            ])
            .addField('input[name="password"]', [
                { rule: 'required', errorMessage: 'Password is required' }
            ])
            .onSuccess((event) => {
                // Allow form submission if validation passes
                event.target.submit();
            });
    }
    
    // Handle window resize to maintain proper mobile/desktop view
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            // Reset to desktop view - remove inline styles and classes
            const signInForm = document.querySelector('.form-container.sign-in');
            const signUpForm = document.querySelector('.form-container.sign-up');
            if (signInForm && signUpForm) {
                signInForm.style.display = '';
                signUpForm.style.display = '';
                signUpForm.classList.remove('mobile-visible');
            }
        } else {
            // Ensure mobile view is correct
            const signInForm = document.querySelector('.form-container.sign-in');
            const signUpForm = document.querySelector('.form-container.sign-up');
            const toggleBtn = document.getElementById('mobile-toggle-btn');
            
            if (signInForm && signUpForm && toggleBtn) {
                // Login form always visible on mobile
                signInForm.style.display = 'block';
                
                // Check if signup should be visible
                if (signUpForm.classList.contains('mobile-visible')) {
                    signUpForm.style.display = 'block';
                    toggleBtn.innerHTML = '<i class="fas fa-times"></i> Hide Sign Up Form';
                    toggleBtn.style.background = 'linear-gradient(45deg, #ff6b6b, #ee5a52)';
                } else {
                    signUpForm.style.display = 'none';
                    toggleBtn.innerHTML = '<i class="fas fa-user-plus"></i> Create your Account';
                    toggleBtn.style.background = 'linear-gradient(45deg, #2196F3, #1976D2)';
                }
            }
        }
    });
});
</script>
</body>
</html>