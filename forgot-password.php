<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="styles/style.css">
    <title>Forgot Password - Rainbow Forest Paradise</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #2b5142;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            background-color: #2b5142;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        
        .main-content {
            flex: 1;
            padding: 50px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .reset-container {
            width: 450px;
            background-color: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        h1 {
            font-size: 1.8rem;
            margin-bottom: 25px;
            text-align: center;
            color: #2b5142;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e2e2;
            border-radius: 5px;
            background-color: #f5f5f5;
        }
        
        input:focus {
            outline: none;
            border-color: #5b9a64;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            margin-top: 10px;
        }
        
        .btn-primary {
            background-color: #2b5142;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #1e3b2e;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9rem;
        }
        
        .back-link a {
            color: #5b9a64;
            text-decoration: none;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .instructions {
            text-align: center;
            margin-bottom: 25px;
            font-size: 0.95rem;
            color: #666;
        }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal {
            background-color: white;
            width: 400px;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            text-align: center;
            transform: translateY(-30px);
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active .modal {
            transform: translateY(0);
        }
        
        .modal-icon {
            font-size: 50px;
            color: #5b9a64;
            margin-bottom: 15px;
        }
        
        .modal-icon.error {
            color: #e74c3c;
        }
        
        .modal-title {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #2b5142;
        }
        
        .modal-message {
            font-size: 1rem;
            margin-bottom: 25px;
            color: #666;
            line-height: 1.5;
        }
        
        .modal-btn {
            padding: 10px 25px;
            background-color: #2b5142;
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .modal-btn:hover {
            background-color: #1e3b2e;
        }
        
        /* Loading indicator styles */
        .loader {
            display: none;
            margin: 20px auto;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #5b9a64;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .reset-container {
                width: 100%;
                padding: 30px 20px;
            }
            
            .modal {
                width: 90%;
                max-width: 400px;
            }
        }
    </style>
</head>
<body>
    <?php include 'headers/header_p2.php'; ?>
    
    <div class="main-content">
        <div class="reset-container">
            <h1>Reset Password</h1>
            
            <p class="instructions">Enter your email address below and we'll send you instructions to reset your password.</p>
            
            <form id="reset-form">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email address" required>
                </div>
                
                <button type="submit" class="btn btn-primary" id="submit-btn">Send Reset Link</button>
                
                <div class="loader" id="loader"></div>
                
                <div class="back-link">
                    <a href="login.php">Back to Login</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Success Modal -->
    <div class="modal-overlay" id="modal-overlay">
        <div class="modal">
            <div class="modal-icon" id="modal-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="modal-title" id="modal-title">Email Sent</h2>
            <p class="modal-message" id="modal-message">Password reset instructions have been sent to your email address. Please check your inbox and follow the instructions to reset your password.</p>
            <button class="modal-btn" id="close-modal">Close</button>
        </div>
    </div>
    
    <?php include 'headers/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const resetForm = document.getElementById('reset-form');
            const submitBtn = document.getElementById('submit-btn');
            const loader = document.getElementById('loader');
            const modalOverlay = document.getElementById('modal-overlay');
            const modalIcon = document.getElementById('modal-icon');
            const modalTitle = document.getElementById('modal-title');
            const modalMessage = document.getElementById('modal-message');
            const closeModal = document.getElementById('close-modal');
            
            resetForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Get email input
                const email = document.getElementById('email').value.trim();
                
                // Basic email validation
                if (!email || !isValidEmail(email)) {
                    showModal(false, 'Invalid Email', 'Please enter a valid email address.');
                    return;
                }
                
                // Show loading indicator
                submitBtn.disabled = true;
                loader.style.display = 'block';
                
                // Create FormData object to send to PHP
                const formData = new FormData();
                formData.append('email', email);
                
                // Send AJAX request to PHP script
                fetch('sendpassword-reset.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    // Hide loading indicator
                    submitBtn.disabled = false;
                    loader.style.display = 'none';
                    
                    // Check response from server
                    if (data.includes("Message sent")) {
                        showModal(true, 'Email Sent', 'Password reset instructions have been sent to your email address. Please check your inbox and follow the instructions to reset your password.');
                        resetForm.reset();
                    } else if (data.includes("No user found")) {
                        showModal(false, 'Email Not Found', 'We couldn\'t find an account associated with that email address. Please check your email and try again.');
                    } else {
                        showModal(false, 'Error', 'There was a problem sending the reset email. Please try again later.');
                    }
                })
                .catch(error => {
                    // Hide loading indicator
                    submitBtn.disabled = false;
                    loader.style.display = 'none';
                    
                    // Show error modal
                    showModal(false, 'Error', 'There was a problem connecting to the server. Please try again later.');
                    console.error('Error:', error);
                });
            });
            
            // Function to show modal with success or error
            function showModal(isSuccess, title, message) {
                if (isSuccess) {
                    modalIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
                    modalIcon.classList.remove('error');
                } else {
                    modalIcon.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
                    modalIcon.classList.add('error');
                }
                
                modalTitle.textContent = title;
                modalMessage.textContent = message;
                modalOverlay.classList.add('active');
            }
            
            // Close modal when button is clicked
            closeModal.addEventListener('click', function() {
                modalOverlay.classList.remove('active');
            });
            
            // Close modal if user clicks outside the modal content
            modalOverlay.addEventListener('click', function(e) {
                if (e.target === modalOverlay) {
                    modalOverlay.classList.remove('active');
                }
            });
            
            // Email validation helper function
            function isValidEmail(email) {
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailPattern.test(email);
            }
        });
    </script>
</body>
</html>