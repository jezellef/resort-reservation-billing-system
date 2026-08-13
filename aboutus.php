<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

// Fetch About Us content from the database
$mysqli = require __DIR__ . "/database.php";
$stmt = $mysqli->prepare("SELECT section, content, image_url FROM site_content WHERE section IN ('aboutus_p1', 'aboutus_p2', 'aboutus_p3')");
$stmt->execute();
$result = $stmt->get_result();
$contents = [];
while ($row = $result->fetch_assoc()) {
    $contents[$row['section']] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ABOUT US - Rainbow Forest Paradise Resort and Campsite</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link rel="stylesheet" href="styles/mystyle.css">
    <style>
        .account-info {
            margin-top: 150px;
            margin-bottom: 150px;
            background-color: #f0f0f0;
        }
        .account-info p {
            line-height: 3;
            margin-top: 15px;
            margin-bottom: 15px;
        }
        h1 {
            alignment: center;
        }
        .user-info {
            display: flex;
            flex-direction: column;
        }
        .profile-btn {
            color: white;
        }
        /* Desktop styles - show original position, hide mobile position */
        @media (min-width: 769px) {
            .mobile-user {
                display: none !important;
            }
            
            .desktop-user {
                display: flex;
            }
        }
        
        /* Mobile styles - show mobile position, hide original position */
        @media (max-width: 768px) {
            .page-header {
                padding: 10px 15px;
            }
        
            .navbar {
                flex-wrap: wrap;
                gap: 10px;
            }
        
            /* Show mobile user icon, hide desktop one */
            .mobile-user {
                display: flex;
                margin-left: 50px;
            }
            
            .desktop-user {
                display: none !important;
            }
        
            /* Make logo smaller on mobile */
            .logo img {
                width: 50px;
                height: 50px;
            }
        
            .logo-text h1 {
                font-size: 1.8em;
                line-height: 1.1;
            }
        
            .logo-text h2 {
                font-size: 1.1em;
                line-height: 1.1;
            }
        
            /* Adjust navigation */
            .nav-links {
                flex-wrap: wrap;
                gap: 20px;
                margin-left: 0;
                margin-right: 0;
                justify-content: center;
                width: 100%;
            }
        
            .nav-links li a {
                font-size: 14px;
                padding: 5px 10px;
                white-space: nowrap;
            }
        
            /* Adjust user info section */
            .user-info {
                flex-direction: column;
                align-items: flex-end;
                gap: 10px;
            }
        
            .user-name {
                font-size: 12px;
            }
        
            .profile-btn, .logout-btn {
                font-size: 12px;
                padding: 4px 8px;
            }
        }
        
        /* Extra small screens */
        @media (max-width: 480px) {
            .logo-text h1 {
                font-size: 1.5em;
            }
        
            .logo-text h2 {
                font-size: 1.0em;
            }
        
            .nav-links {
                gap: 10px;
            }
        
            .nav-links li a {
                font-size: 13px;
                padding: 4px 8px;
            }
        }
    </style>
</head>
<body>

<?php include 'headers/header.php'; ?>

<div class="about-container">
    <div class="about-text">
        <h2>About Us</h2>
        <p><?= nl2br(htmlspecialchars($contents['aboutus_p1']['content'] ?? '')) ?></p>
    </div>
    <div class="about-image">
        <img src="<?= htmlspecialchars($contents['aboutus_p1']['image_url'] ?? 'images/activity.png') ?>" alt="Rainbow Forest Paradise Resort">
    </div>
</div>

<div class="location-section">
    <h2>Location</h2>
    <p><?= nl2br(htmlspecialchars($contents['aboutus_p2']['content'] ?? '')) ?></p>
</div>

<div class="map-container">
    <h2>
        <img src="images/location-icon.png" alt="Location Icon" style="width: 30px; height: 30px; vertical-align: middle; margin-right: 5px;">
        BRGY. CUYAMBAY, TANAY, RIZAL
    </h2>
    <iframe 
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3861.62355114514!2d121.3419326751055!3d14.56350718591845!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397952b37b5010f%3A0x52edd4eea37586b8!2sRainbow%20Forest%20Paradise%20Resort%20and%20Campsite!5e0!3m2!1sen!2sph!4v1735636685930!5m2!1sen!2sph" 
        width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
    </iframe>
</div>

<div class="location-section">
    <h2>Landmark / Note</h2>
    <p><?= nl2br(htmlspecialchars($contents['aboutus_p3']['content'] ?? '')) ?></p>
</div>

<?php include 'headers/footer.php'; ?>
</body>
</html>
