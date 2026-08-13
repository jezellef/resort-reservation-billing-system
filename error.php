<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Rainbow Forest Paradise Resort and Campsite</title>
    <link rel="stylesheet" href="mystyle.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Acme&family=Dancing+Script:wght@400..700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Lobster&family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <style>
        .error-container {
            max-width: 800px;
            margin: 100px auto;
            padding: 30px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 10px solid #f44336;
        }
        
        h1 {
            color: #03624c;
            margin-bottom: 20px;
        }
        
        .error-message {
            background-color: #fff8f8;
            border: 1px solid #ffcdd2;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            color: #b71c1c;
            font-size: 18px;
            line-height: 1.6;
            text-align: left;
        }
        
        .back-btn {
            display: inline-block;
            background-color: #4caf50;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 20px;
            transition: background-color 0.3s;
        }
        
        .back-btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <?php 
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

    // Get the current user if logged in
    $current_user = getUserStatus();
    ?>
    
    <!-- Navbar -->
    <nav class="home-navbar">
        <div class="logo">
            <img src="images/rainbow-logo.png" alt="Logo">
            <div>
                <h1>Rainbow Forest Paradise</h1>
                <h2>Resort and Campsite</h2>
            </div>
        </div>
        <div class="nav-right">
            <ul id="menu-img" class="home-nav-links">
                <li><a href="home_p1.php">HOME</a></li>
                <li><a href="aboutus_p1.php">ABOUT</a></li>
                <li><a href="accomodation_p1.php">ACCOMMODATIONS</a></li>
                <li><a href="activities_p1.php">ACTIVITIES</a></li>
                <li><a href="contact_p1.php">CONTACT US</a></li>
                <li><a href="#">BOOK NOW</a></li>
            </ul>
        </div>
        <div class="icon">
            <?php if($current_user): ?>
                <div class="user-info">
                    <span class="user-name">Hello, <?= htmlspecialchars($current_user["first_name"]) ?></span>
                    <div class="user-actions">
                        <a href="account.php" class="profile-btn">My Profile</a>
                        <form action="logout.php" method="post">
                            <button type="submit" class="logout-btn">Logout</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="user-icon">
                    <img src="images/logo.png" alt="User Icon">
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="error-container">
        <h1>Reservation Error</h1>
        
        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="error-message">
                <?php 
                $error_message = $_SESSION['error_message'];
                // Replace newlines with <br> tags for better display
                $error_message = nl2br(htmlspecialchars($error_message));
                echo $error_message; 
                ?>
            </div>
            <?php unset($_SESSION['error_message']); // Clear the error message ?>
        <?php else: ?>
            <div class="error-message">
                An unexpected error occurred during your reservation process. Please try again or contact our support team for assistance.
            </div>
        <?php endif; ?>
        
        <a href="home_p1.php" class="back-btn">Return to Home</a>
        <a href="javascript:history.back()" class="back-btn" style="background-color: #03624c; margin-left: 10px;">Go Back</a>
    </div>

    <footer>
        <div class="footer-container">
            <div class="footer-logo">
                <img src="images/rainbow-logo.png" alt="Rainbow Forest Logo">
            </div>
            <div class="footer-nav">
                <h3>Explore</h3>
                <ul>
                    <li><a href="#">Accommodations</a></li>
                    <li><a href="#">Activities</a></li>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-contact">
                <h3>Contact Us</h3>
                <p><strong>Address:</strong> Brgy. Cuyambay, Tanay, Rizal</p>
                <p><strong>Contact No.:</strong> 0960 587 7561</p>
            </div>
            <div class="footer-actions">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="#">Follow Us</a></li>
                    <li><a href="#">Book Now</a></li>
                    <li><a href="#">Cancel Reservation</a></li>
                </ul>
            </div>
        </div>
    </footer>
</body>
</html>