<?php
session_start(); 
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
$room_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mysqli = require __DIR__ . "/database.php";
// Get room details with content
$stmt = $mysqli->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc(); 
$stmt->close();
if (!$room) {
    header("Location: accommodation.php");
    exit;
}
// Get room images
$imageStmt = $mysqli->prepare("SELECT * FROM room_images WHERE room_id = ? ORDER BY image_type DESC, sort_order");
$imageStmt->bind_param("i", $room_id);
$imageStmt->execute();
$imageResult = $imageStmt->get_result();
$room_images = $imageResult->fetch_all(MYSQLI_ASSOC);
$imageStmt->close();
$main_image = $room['main_image'] ?? 'images/default_room.jpg';
if ($main_image && !file_exists($main_image)) {
    $main_image = 'images/default_room.jpg';
}
// Get all gallery images for this room
$gallery_images = [];
foreach ($room_images as $image) {
    if (file_exists($image['image_path'])) {
        $gallery_images[] = $image['image_path'];
    }
}
// Create display array: main image + up to 3 gallery images = 4 total
$display_images = [];
// Always add main image first
$display_images[] = $main_image;
// Add gallery images (up to 3 more to make 4 total)
$gallery_count = 0;
foreach ($gallery_images as $gallery_img) {
    if ($gallery_count < 3 && $gallery_img != $main_image) {
        $display_images[] = $gallery_img;
        $gallery_count++;
    }
}
// If we have less than 4 images, fill with main image
while (count($display_images) < 4) {
    $display_images[] = $main_image;
}
$current_user = getUserStatus(); 
// Get pricing info
$daytour_price = $room['day_tour_price'];
$overnight_price = $room['night_tour_price'];
$quantity = $room['quantity'];
$current_pricing = [
    "daytour" => $daytour_price,
    "overnight" => $overnight_price,
    "quantity" => $quantity
];

// Parse amenities if they exist
$amenities = [];
if ($room['amenities']) {
    $amenities = json_decode($room['amenities'], true) ?? [];
}

// Enhanced room descriptions based on database content or fallback to defaults
$room_descriptions = [
    1 => "Spacious family house perfect for large groups seeking comfort and privacy. Features multiple bedrooms, a fully equipped kitchen, and ample living space for memorable gatherings.",
    2 => "Comfortable kubo-style accommodation that brings you closer to nature while providing essential amenities. Perfect for families who want an authentic Filipino experience.",
    3 => "Large house ideal for extended families or groups of friends. Offers generous space, modern conveniences, and beautiful views of the surrounding forest.",
    4 => "Cozy cabin nestled among the trees, offering an intimate retreat with all necessary amenities. Perfect for couples or small families seeking tranquility.",
    5 => "Traditional kubo accommodation designed for guests who appreciate simplicity and natural beauty. Experience authentic Filipino hospitality in a serene setting.",
    6 => "Charming kubo that combines traditional architecture with modern comfort. Ideal for guests seeking a unique cultural experience.",
    7 => "Spacious house with premium amenities and stunning forest views. Perfect for special occasions and larger gatherings requiring extra comfort.",
    8 => "Open cottage design that maximizes your connection with nature. Perfect for adventurous guests who enjoy outdoor living.",
    9 => "Canvas tent accommodation for the ultimate camping experience. Ideal for nature enthusiasts and those seeking an authentic outdoor adventure.",
    14 => "Comfortable room with essential amenities and beautiful natural surroundings. Perfect for guests seeking quality accommodation at great value.",
    15 => "Well-appointed accommodation featuring modern amenities and scenic views. Ideal for guests who appreciate comfort and convenience.",
    16 => "Exclusive private accommodation offering complete privacy and luxury amenities. Perfect for special celebrations, corporate retreats, or large family gatherings."
];

// Use database description if available, otherwise use default
$description = $room['detailed_description'] ?: ($room_descriptions[$room_id] ?? "Experience comfort and natural beauty in this well-appointed accommodation. Each unit features quality amenities and scenic views for a memorable stay.");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($room['name']) ?> - Rainbow Forest Paradise Resort and Campsite</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link rel="stylesheet" href="mystyle.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Acme&family=Dancing+Script:wght@400..700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Lobster&family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <style>
        .room-details {
            display: flex;
            flex-direction: column;
            max-width: 1200px;
            margin: auto auto 50px auto;
            padding: 0;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
         .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #ffffff;
            text-decoration: none;
            font-weight: 500;
            padding: 10px 20px;
            border: 2px solid #ffffff;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            background: #ffffff;
            color: black;
            text-decoration: none;
        }
        .room-gallery {
            display: flex;
            flex-direction: row;
            gap: 15px;
            padding: 0 20px 20px 20px;
        }
        .main-image {
            flex: 2;
            height: 500px;
            overflow: hidden;
            border-radius: 8px;
        }
        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .main-image img:hover {
            transform: scale(1.02);
        }
        .thumbnail-grid {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: repeat(3, 1fr);
            gap: 10px;
            height: 500px;
        }
        .thumbnail {
            cursor: pointer;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
        }
        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .thumbnail:hover img {
            transform: scale(1.1);
        }
        .thumbnail::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.1);
            transition: background 0.3s ease;
        }
        .thumbnail:hover::after {
            background: rgba(0,0,0,0);
        }
        .room-info {
            display: flex;
            padding: 20px;
            gap: 40px;
        }
        .room-description {
            flex: 3;
            padding-right: 40px;
            color: black;
        }
        .room-description h1 {
            color: #228B22;
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .availability {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .room-description-text {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 20px;
            color: #444;
        }
        .features-highlight {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #228B22;
        }
        .features-highlight h3 {
            color: #228B22;
            margin-bottom: 15px;
        }
        .features-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }
        .feature-icon {
            color: #228B22;
            font-weight: bold;
        }
        .amenities-highlight {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #4CAF50;
        }
        .amenities-highlight h3 {
            color: #4CAF50;
            margin-bottom: 15px;
        }
        .amenities-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .amenity-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            color: #2e7d32;
        }
        .room-booking {
            flex: 2;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            height: fit-content;
        }
        .room-booking h2 {
            color: #333;
            margin-bottom: 25px;
            text-align: center;
            font-size: 1.6rem;
            font-weight: 600;
        }
        .price-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .price-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
        }
        .price-option:last-child {
            border-bottom: none;
        }
        .price-option h3 {
            margin: 0;
            font-size: 1.3rem;
            color: #333;
            font-weight: 600;
        }
        .price-option p {
            margin: 5px 0 0;
            color: #666;
            font-size: 0.9rem;
        }
        .price {
            font-size: 1.6rem;
            font-weight: bold;
            color: #228B22;
        }
        .book-now-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }
        .book-now-btn:hover {
            background: linear-gradient(135deg, #ff5252 0%, #ff4444 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
        }
        .book-now-btn:active {
            transform: translateY(0);
        }
        .private-gallery-section {
            margin: 20px 0;
        }
        
        .private-gallery-highlight {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            margin: 20px 0;
        }
        
        .private-gallery-highlight h3 {
            color: white;
            margin-bottom: 10px;
            font-size: 1.4rem;
        }
        
        .private-gallery-highlight p {
            margin-bottom: 20px;
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .gallery-button {
            display: inline-block;
            background: white;
            color: #667eea;
            padding: 12px 25px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .gallery-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            color: #667eea;
            text-decoration: none;
        }
        
        .gallery-icon {
            margin-right: 8px;
            font-size: 1.1rem;
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
        
        @media (max-width: 768px) {
            .private-gallery-highlight {
                padding: 20px;
            }
            
            .private-gallery-highlight h3 {
                font-size: 1.2rem;
            }
        }
        
        @media (max-width: 992px) {
            .room-gallery {
                flex-direction: column;
            }
            .main-image {
                height: 300px;
            }
            .thumbnail-grid {
                height: 200px;
                grid-template-columns: repeat(3, 1fr);
                grid-template-rows: repeat(2, 1fr);
            }
            .room-info {
                flex-direction: column;
            }
            .room-description {
                padding-right: 0;
            }
            .features-list, .amenities-list {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .features-list, .amenities-list {
                grid-template-columns: repeat(2, 1fr);
            }
            .thumbnail-grid {
                grid-template-columns: repeat(2, 1fr);
                grid-template-rows: repeat(3, 1fr);
            }
        }

        @media (max-width: 576px) {
            .features-list, .amenities-list {
                grid-template-columns: 1fr;
            }
            .thumbnail-grid {
                height: 150px;
                grid-template-columns: repeat(5, 1fr);
                grid-template-rows: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'headers/header.php'; ?>
    <main class="content-wrapper" role="main">
         <a href="javascript:history.back()" class="back-link">← Back to All Accommodations</a>
        <div class="room-details">
           
            
           <div class="room-gallery">
                <div class="main-image">
                    <img src="<?= htmlspecialchars($display_images[0]) ?>" 
                         alt="<?= htmlspecialchars($room['name']) ?>" 
                         id="mainImage">
                </div>
                <div class="thumbnail-grid">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="thumbnail <?= $i === 0 ? 'active' : '' ?>" 
                             onclick="changeImage('<?= htmlspecialchars($display_images[$i]) ?>', this)"
                             data-image="<?= htmlspecialchars($display_images[$i]) ?>">
                            <img src="<?= htmlspecialchars($display_images[$i]) ?>" 
                                 alt="<?= htmlspecialchars($room['name']) ?> view <?= $i + 1 ?>">
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="room-info">
                <div class="room-description">
                    <h1><?= htmlspecialchars($room['name']) ?></h1>
                    <span class="availability">
                        <?php if ($room['room_type'] == 'private'): ?>
                            <?= $room['real_quantity'] > 0 ? 'Available for Booking' : 'Currently Unavailable' ?>
                        <?php else: ?>
                            Up to <?= $room['real_quantity'] ?> Available
                        <?php endif; ?>
                    </span>
                    <?php if ($room['room_type'] == 'private' || $room_id == 28): ?>
                    <div class="private-gallery-section">
                        <div class="private-gallery-highlight">
                            <h3>Explore the Complete Private Area</h3>
                            <p>See all accommodations, facilities, and amenities included in your private paradise.</p>
                            <a href="private_gallery.php" class="gallery-button">
                           
                                View Complete Photo Gallery
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="room-description-text">
                        <p><?= nl2br(htmlspecialchars($description)) ?></p>
                    </div>
                    
                    <div class="features-highlight">
                        <h3>Key Features</h3>
                        <div class="features-list">
                            <div class="feature-item">
                                <span class="feature-icon">👥</span>
                                <span>Capacity: <?= $room['capacity'] ?> guests</span>
                            </div>
                            <?php if($room_id != 8 && $room_id != 9): ?>
                            <div class="feature-item">
                                <span class="feature-icon">🛏️</span>
                                <span>Comfortable bedding</span>
                            </div>
                            <?php endif; ?>
                            <?php if($room_id != 2 && $room_id != 5 && $room_id != 6 && $room_id != 8 && $room_id != 9): ?>
                            <div class="feature-item">
                                <span class="feature-icon">🚿</span>
                                <span>Private bathroom</span>
                            </div>
                            <?php else: ?>
                            <div class="feature-item">
                                <span class="feature-icon">🚿</span>
                                <span>Shared bathroom access</span>
                            </div>
                            <?php endif; ?>
                            <?php if($room_id == 1 || $room_id == 3 || $room_id == 7): ?>
                            <div class="feature-item">
                                <span class="feature-icon">🍳</span>
                                <span>Private kitchen</span>
                            </div>
                            <?php else: ?>
                            <div class="feature-item">
                                <span class="feature-icon">🍳</span>
                                <span>Shared kitchen access</span>
                            </div>
                            <?php endif; ?>
                            <div class="feature-item">
                                <span class="feature-icon">🌲</span>
                                <span>Natural surroundings</span>
                            </div>
                            <div class="feature-item">
                                <span class="feature-icon">🌅</span>
                                <span>Scenic forest views</span>
                            </div>
                        </div>
                    </div>
             
                    <?php if (!empty($amenities)): ?>
                    <div class="amenities-highlight">
                        <h3><?= $room['room_type'] == 'private' ? 'What\'s Included' : 'Amenities' ?></h3>
                        <div class="amenities-list">
                            <?php foreach ($amenities as $amenity): ?>
                            <div class="amenity-item">
                                <span class="feature-icon">✓</span>
                                <span><?= htmlspecialchars($amenity) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="room-booking">
                    <h2>Pricing & Booking</h2>
                    
                    <div class="price-section">
                        <div class="price-option">
                            <div>
                                <h3>Day Tour</h3>
                                <p>8:00 AM - 5:00 PM</p>
                                <p><?= $room['room_type'] == 'private' ? 'Full area access' : 'Perfect for day visitors' ?></p>
                            </div>
                            <div class="price">₱<?= number_format($current_pricing['daytour'], 0) ?></div>
                        </div>
                        <div class="price-option">
                            <div>
                                <h3>Overnight</h3>
                                <p>2:00 PM - 12:00 PM (next day)</p>
                                <p><?= $room['room_type'] == 'private' ? 'Complete private area experience' : 'Full accommodation experience' ?></p>
                            </div>
                            <div class="price">₱<?= number_format($current_pricing['overnight'], 0) ?></div>
                        </div>
                    </div>
                    <button type="button" class="book-now-btn" onclick="window.location.href='booking_form.php'">
                        Book <?= $room['room_type'] == 'private' ? 'This Area' : 'This Room' ?>
                    </button>
                </div>
            </div>
        </div>
    </main>
    <?php include 'headers/footer.php'; ?>
    
    <script>
        function changeImage(imageSrc, thumbnailElement) {
            const mainImage = document.getElementById('mainImage');
            if (mainImage) {
                mainImage.src = imageSrc;
                
                // Remove active class from all thumbnails
                document.querySelectorAll('.thumbnail').forEach(thumb => {
                    thumb.classList.remove('active');
                });
                
                // Add active class to clicked thumbnail
                if (thumbnailElement) {
                    thumbnailElement.classList.add('active');
                }
            }
        }

        // Initialize with first image as active
        document.addEventListener('DOMContentLoaded', function() {
            const firstThumbnail = document.querySelector('.thumbnail');
            if (firstThumbnail) {
                firstThumbnail.classList.add('active');
            }
        });
    </script>
</body>
</html>