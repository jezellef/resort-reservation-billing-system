<?php
session_start();

$mysqli = require __DIR__ . "/database.php";

// Get all active gallery images
$stmt = $mysqli->prepare("SELECT * FROM private_gallery WHERE is_active = 1 ORDER BY sort_order, id");
$stmt->execute();
$result = $stmt->get_result();
$gallery_images = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get private room details for pricing
$room_stmt = $mysqli->prepare("SELECT * FROM rooms WHERE room_type = 'private' OR id = 16 LIMIT 1");
$room_stmt->execute();
$private_room = $room_stmt->get_result()->fetch_assoc();
$room_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Private Area Gallery - Rainbow Forest Paradise Resort</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link rel="stylesheet" href="styles/mystyle.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
        .gallery-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .gallery-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px 20px;
            background: linear-gradient(135deg, #043927 0%, #0b6623 100%);
            color: white;
            border-radius: 15px;
        }
        
        .gallery-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .gallery-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
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
        
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .image-card {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            aspect-ratio: 4/3;
        }
        
        .image-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        
        .image-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .image-card:hover img {
            transform: scale(1.05);
        }
        
        .image-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            color: white;
            padding: 20px;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }
        
        .image-card:hover .image-overlay {
            transform: translateY(0);
        }
        
        .booking-section {
            background: linear-gradient(135deg, #043927 0%, #0b6623 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            margin-top: 40px;
        }
        
        .booking-section h2 {
            margin-bottom: 15px;
            font-size: 2rem;
        }
        
        .booking-section p {
            margin-bottom: 25px;
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .price-info {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .price-item {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            min-width: 150px;
        }
        
        .price-item h3 {
            margin-bottom: 5px;
            font-size: 1.2rem;
        }
        
        .price-item .price {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .book-btn {
            background: white;
            color: #f5576c;
            padding: 15px 40px;
            border: none;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .book-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            color: #f5576c;
            text-decoration: none;
        }
        
        /* Lightbox styles */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .lightbox.active {
            display: flex;
        }
        
        .lightbox-content {
            position: relative;
            max-width: 90%;
            max-height: 90%;
        }
        
        .lightbox img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 8px;
        }
        
        .lightbox-close {
            position: absolute;
            top: -40px;
            right: 0;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            background: none;
            border: none;
        }
        
        .lightbox-caption {
            position: absolute;
            bottom: -50px;
            left: 0;
            right: 0;
            color: white;
            text-align: center;
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
        
        @media (max-width: 768px) {
            .gallery-header h1 {
                font-size: 2rem;
            }
            
            .image-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 15px;
            }
            
            .price-info {
                flex-direction: column;
                gap: 20px;
            }
            
            .booking-section {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'headers/header.php'; ?>
    
    <main class="content-wrapper" role="main">
        <div class="gallery-container">
            <a href="javascript:history.back()" class="back-link">← Back to Room Details</a>
            
            <div class="gallery-header">
                <h1>Private Area Gallery</h1>
                <p>Explore every corner of your exclusive private paradise. Perfect for your family and friends special gateway. </p>
            </div>
            <h3>Click on any image above to view it in full size. </h3>
            <br>
            <div class="image-grid">
                <?php foreach ($gallery_images as $index => $image): ?>
                    <div class="image-card" onclick="openLightbox(<?= $index ?>)">
                        <img src="<?= htmlspecialchars($image['image_path']) ?>" 
                             alt="<?= htmlspecialchars($image['caption'] ?? 'Private area') ?>"
                             onerror="this.src='images/default_room.jpg'">
                        <div class="image-overlay">
                            <h3><?= htmlspecialchars($image['caption'] ?? 'Private Area Feature') ?></h3>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($gallery_images)): ?>
                <div style="text-align: center; padding: 60px 20px; color: #666;">
                    <h3>Gallery images are being updated</h3>
                    <p>Please check back soon for amazing photos of our private area!</p>
                </div>
            <?php endif; ?>
            
            <div class="booking-section">
                <h2>Ready to Book Your Private Paradise?</h2>     
                <?php if ($private_room): ?>
                <div class="price-info">
                    <div class="price-item">
                        <h3>Day Tour</h3>
                        <div class="price">₱<?= number_format($private_room['day_tour_price'], 0) ?></div>
                        <small>8:00 AM - 5:00 PM</small>
                    </div>
                    <div class="price-item">
                        <h3>Overnight</h3>
                        <div class="price">₱<?= number_format($private_room['night_tour_price'], 0) ?></div>
                        <small>2:00 PM - 12:00 PM</small>
                    </div>
                </div>
                <?php endif; ?>
                
                <a href="booking_form.php" class="book-btn">Book Private Area Now</a>
            </div>
        </div>
    </main>
    
    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <div class="lightbox-content" onclick="event.stopPropagation()">
            <button class="lightbox-close" onclick="closeLightbox()">&times;</button>
            <img id="lightbox-image" src="" alt="">
            <div class="lightbox-caption" id="lightbox-caption"></div>
        </div>
    </div>
    
    <?php include 'headers/footer.php'; ?>
    
    <script>
        const galleryData = <?= json_encode($gallery_images) ?>;
        
        function openLightbox(index) {
            const lightbox = document.getElementById('lightbox');
            const image = document.getElementById('lightbox-image');
            const caption = document.getElementById('lightbox-caption');
            
            image.src = galleryData[index].image_path;
            caption.textContent = galleryData[index].caption || 'Private Area Feature';
            
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeLightbox() {
            const lightbox = document.getElementById('lightbox');
            lightbox.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Close lightbox with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });
    </script>
</body>
</html>