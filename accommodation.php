<?php
session_start(); // Start the session at the beginning
function getUserStatus() {
    if (isset($_SESSION["user_id"])) {
        $mysqli = require __DIR__ . "/database.php";
        $stmt = $mysqli->prepare("SELECT first_name, last_name FROM user WHERE id = ?");
        if (!$stmt) {
            error_log("Error preparing SQL statement: " . $mysqli->error);
            return null;
        }
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
$mysqli = require __DIR__ . "/database.php";
$stmt = $mysqli->prepare("SELECT * FROM rooms");
if (!$stmt) {
    error_log("Error preparing SQL statement to fetch rooms: " . $mysqli->error);
    die("Error fetching rooms.");
}
$stmt->execute();
$result = $stmt->get_result();
$privateAccommodations = [];
$publicAccommodations = [];
while ($row = $result->fetch_assoc()) {
    $accommodation = [
        "id" => $row['id'],
        "name" => $row['name'],
        "description" => $row['description'],
        "daytour" => $row['day_tour_price'],
        "overnight" => $row['night_tour_price'],
        "image" => $row['image'],
    ];
    if (stripos($row['name'], 'Private') !== false) {
        $privateAccommodations[] = $accommodation;
    } else {
        $publicAccommodations[] = $accommodation;
    }
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACCOMMODATIONS - Rainbow Forest Paradise Resort and Campsite</title>
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
        .unavailable {
            opacity: 0.5;
            pointer-events: none;
        }
        .accom-item.unavailable {
            opacity: 0.6;
            position: relative;
        }
        .accom-item.unavailable:after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.2);
            pointer-events: none;
        }
        .accom-item.unavailable .accom-details .availability {
            font-weight: bold;
            font-size: 1.1em;
        }
        .accom-button[disabled] {
            background-color: gray;
            cursor: not-allowed;
        }
        .private-hero {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            background-image: url("<?php echo !empty($privateAccommodations) ? $privateAccommodations[0]['image'] : ''; ?>");
            background-size: cover;
            background-position: center;
            height: 400px;
            padding: 30px;
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.7);
            border-radius: 25px;
        }
        .private-hero .details {
            flex: 1;
            max-width: 40%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px;
        }
        .private-hero .details h2 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .private-hero .details h3 {
            margin-bottom: 15px;
        }
        .private-hero .details p {
            margin-bottom: 20px;
        }
        .private-hero .details h4 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
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
                margin-left: 50px; /* Adjusted spacing */
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
                gap: 15px;
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
                gap: 5px;
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
        
            /* Make accommodation content more mobile-friendly */
            .content-wrapper h1 {
                font-size: 2em;
            }
        
            .private-hero {
                height: 300px;
                padding: 20px;
            }
        
            .private-hero .details {
                max-width: 100%;
                padding: 10px;
            }
        
            .private-hero .details h2 {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
<?php include 'headers/header.php'; ?>
<main class="content-wrapper" role="main">
    <h1>Private Villa</h1>
        <?php if (!empty($privateAccommodations)): ?>
            <section class="private-hero">
                <div class="details">
                    <h2><?php echo $privateAccommodations[0]['name']; ?></h2>
                    <a href="accommodation_details.php?id=<?php echo $privateAccommodations[0]['id']; ?>" class="accom-button">See Details</a>
                </div>
            </section>
        <?php endif; ?>
    <br>
    <h1>Public Accommodations</h1>
    <section class="accom-container" aria-label="Public Accommodation Listings">
        <?php
        foreach ($publicAccommodations as $accommodation) {
            $stmt = $mysqli->prepare("SELECT real_quantity, status FROM rooms WHERE id = ?");
            $stmt->bind_param("i", $accommodation['id']);
            $stmt->execute();
            $roomData = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $isUnavailable = !$roomData || $roomData['status'] !== 'Available' || $roomData['real_quantity'] <= 0;
            echo '<article class="accom-item' . ($isUnavailable ? ' unavailable' : '') . '" aria-labelledby="' . strtolower(str_replace(' ', '-', $accommodation['name'])) . '">';
            echo '<div class="accom-image"><img src="' . $accommodation['image'] . '" alt="' . $accommodation['name'] . '" /></div>';
            echo '<div class="accom-details">';
            echo '<h2 id="' . strtolower(str_replace(' ', '-', $accommodation['name'])) . '" class="accom-title">' . $accommodation['name'] . '</h2>';
            if ($isUnavailable) {
                echo '<span class="availability" style="color:red;">UNAVAILABLE</span>'; 
            } else {
                echo '<span class="availability">Up to ' . $roomData['real_quantity'] . ' Available - Select Dates</span>';
            }
            echo '<div class="accom-divider"></div>';
            echo '<h3 class="accom-description">' . $accommodation['description'] . '</h3>';
            echo '<h4>Daytour: ₱' . number_format($accommodation['daytour'], 2) . '<br> Overnight: ₱' . number_format($accommodation['overnight'], 2) . '</h4>';
            if ($isUnavailable) {
                echo '<button class="accom-button" disabled>Unavailable</button>';
            } else {
                echo '<a href="accommodation_details.php?id=' . $accommodation['id'] . '" class="accom-button">See Details</a>';
            }
            echo '</div>';
            echo '</article>';
        }
        ?>
    </section>
</main>
<?php include 'headers/footer.php'; ?>
</body>
</html>