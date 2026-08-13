<?php
$conn = new mysqli('localhost', 'u291458526_resort_user', 'r@inboWforest123!', 'u291458526_resort_db');
$results = $conn->query("SELECT * FROM page_content WHERE page='index'");
$content = [];
while ($row = $results->fetch_assoc()) {
    $content[$row['section']] = $row['content_value'];
}
?>
<header class="hero">
    <div class="overlay"></div>
    <nav class="home-navbar">
        <div class="logo">
            <img src="<?= $content['logo_image'] ?? 'images/rainbow-logo.png' ?>" alt="Logo">
            <div>
                <h1><?= $content['logo_title'] ?? 'Rainbow Forest Paradise' ?></h1>
                <h2><?= $content['logo_subtitle'] ?? 'Resort and Campsite' ?></h2>
            </div>
        </div>

        <div class="nav-right">
            <ul id="menu-img" class="home-nav-links">
                <li><a href="index.php"><?= $content['nav_home'] ?? 'HOME' ?></a></li>
                <li><a href="aboutus.php"><?= $content['nav_about'] ?? 'ABOUT' ?></a></li>
                <li><a href="accommodation.php"><?= $content['nav_accommodations'] ?? 'ACCOMMODATIONS' ?></a></li>
                <li><a href="contact.php"><?= $content['nav_contact'] ?? 'CONTACT US' ?></a></li>
                <li><a href="booking_form.php"><?= $content['nav_book'] ?? 'BOOK NOW' ?></a></li>
            </ul>
        </div>

        <!-- Desktop search and user section -->
        <div class="booking-search">
            <input type="text" id="reference-search" placeholder="Enter reservation code">
            <button onclick="searchBooking('reference-search', 'search-results')">Search</button>
            <div id="search-results" class="search-results-dropdown"></div>
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
    
    <!-- Mobile search and user section -->
    <div class="top-right-section">
        <div class="booking-search">
            <input type="text" id="reference-search-mobile" placeholder="Enter reservation code">
            <button onclick="searchBooking('reference-search-mobile', 'search-results-mobile')">Search</button>
            <div id="search-results-mobile" class="search-results-dropdown"></div>
        </div>
        <div class="icon">
            <?php if($current_user): ?>
                <div class="user-info">
                    <span class="user-name">Hello, <?= htmlspecialchars($current_user["first_name"]) ?></span>
                    <div class="user-actions">
                        <a href="account.php" class="profile-btn">My Profile</a>
                        <form action="logout.php" method="post" style="display: inline;">
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
    </div>

    <!-- Desktop hero text and menu -->
    <div class="hero-text">
        <h1><span><?= $content['hero_header'] ?? 'Welcome to <br> Rainbow Forest Paradise' ?></span><br><?= $content['hero_subtitle'] ?? 'Resort and Campsite' ?></h1>
        <p><?= $content['hero_paragraph'] ?? 'Nature, Comfort, and Activities – Your Ideal Escape!' ?></p>
    </div>

    <div class="menu-img">
        <img src="<?= $content['menu_image_1'] ?? 'images/IMG_4891.jpg' ?>" alt="Image 1" onclick="changeBackground('<?= $content['menu_image_1'] ?? 'images/IMG_4891.jpg' ?>', this)">
        <img src="<?= $content['menu_image_2'] ?? 'images/img27.jpg' ?>" alt="Image 2" onclick="changeBackground('<?= $content['menu_image_2'] ?? 'images/img27.jpg' ?>', this)">
        <img src="<?= $content['menu_image_3'] ?? 'images/IMG_4809.jpg' ?>" alt="Image 3" onclick="changeBackground('<?= $content['menu_image_3'] ?? 'images/IMG_4809.jpg' ?>', this)">
    </div>

    <!-- Mobile hero content -->
    <div class="mobile-hero-content">
        <div class="hero-text">
            <h1><span><?= $content['hero_header'] ?? 'Welcome to <br> Rainbow Forest Paradise' ?></span><br><?= $content['hero_subtitle'] ?? 'Resort and Campsite' ?></h1>
            <p><?= $content['hero_paragraph'] ?? 'Nature, Comfort, and Activities – Your Ideal Escape!' ?></p>
        </div>
        <div class="menu-img">
            <img src="<?= $content['menu_image_1'] ?? 'images/IMG_4891.jpg' ?>" alt="Image 1" onclick="changeBackground('<?= $content['menu_image_1'] ?? 'images/IMG_4891.jpg' ?>', this)">
            <img src="<?= $content['menu_image_2'] ?? 'images/img27.jpg' ?>" alt="Image 2" onclick="changeBackground('<?= $content['menu_image_2'] ?? 'images/img27.jpg' ?>', this)">
            <img src="<?= $content['menu_image_3'] ?? 'images/IMG_4809.jpg' ?>" alt="Image 3" onclick="changeBackground('<?= $content['menu_image_3'] ?? 'images/IMG_4809.jpg' ?>', this)">
        </div>
    </div>
</header>

<style>
    .home-navbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 20px;
        position: relative;
        z-index: 10;
    }

    .booking-search {
        display: flex;
        align-items: center;
        position: relative;
    }

    .booking-search input {
        padding: 8px 12px;
        border: 1px solid #ccc;
        border-radius: 4px 0 0 4px;
        width: 220px;
        font-size: 14px;
    }

    .booking-search button {
        padding: 8px 15px;
        background-color: #4CAF50;
        color: white;
        border: none;
        border-radius: 0 4px 4px 0;
        cursor: pointer;
        font-size: 14px;
    }

    .booking-search button:hover {
        background-color: #45a049;
    }

    .search-results-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        background-color: white;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        border-radius: 4px;
        max-height: 300px;
        overflow-y: auto;
        z-index: 100;
    }

    .search-result-item {
        padding: 10px 15px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
        color: black;
    }

    .search-result-item:hover {
        background-color: #f5f5f5;
    }

    .pending-payment {
        background-color: #fff3cd;
    }

    .continue-payment-btn {
        background-color: #dc3545;
        color: white;
        padding: 5px 10px;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        margin-top: 5px;
        display: inline-block;
    }

    .continue-payment-btn:hover {
        background-color: #c82333;
    }

    .expired-booking {
        background-color: #f8d7da;
        border-left: 4px solid #dc3545;
    }

    .expired-label {
        background-color: #dc3545;
        color: white;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: bold;
        margin-left: 5px;
    }

    /* Ensure user icon is clickable on all devices */
    .user-icon {
        display: block;
        cursor: pointer;
        pointer-events: auto;
    }

    .user-icon img {
        pointer-events: none; /* Prevent image from intercepting clicks */
    }

    /* Hide mobile section on desktop */
    .top-right-section {
        display: none;
    }
    
    .mobile-hero-content {
        display: none;
    }
    
    /* Replace your existing mobile CSS section with this updated version */
    @media screen and (max-width: 1024px) {
        .hero {
            display: flex;
            flex-direction: column;
            height: auto;
            min-height: 100vh;
        }
    
        /* Hide desktop elements */
        .hero-text { display: none !important; }
        .menu-img { display: none !important; }
        
        /* Hide desktop booking search and user icon, but NOT the mobile ones */
        .home-navbar .booking-search { display: none !important; }
        .home-navbar .icon { display: none !important; }
    
        /* Mobile Navigation Container */
        .home-navbar {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 15px;
            padding: 15px;
        }
    
        .logo {
            justify-content: center;
            order: 1;
        }
    
        .nav-right {
            order: 2;
            justify-content: center;
            width: 100%;
        }
    
        .home-nav-links {
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
        }
    
        .home-nav-links li a {
            font-size: 12px;
            padding: 8px 12px !important;
            background: rgba(76, 175, 80, 0.9) !important;
            border-radius: 5px !important;
        }
    
        /* Search section - now outside navbar, appears right after it */
        .top-right-section {
            display: flex !important;
            align-items: center;
            gap: 15px;
            justify-content: center;
            padding: 15px;
            position: relative;
            z-index: 10;
        }
    
        .top-right-section .booking-search { 
            display: flex !important; 
            position: relative;
        }
        
        .top-right-section .icon { 
            display: flex !important; 
            align-items: center;
        }
    
        /* Ensure user icon and info are properly clickable on mobile */
        .top-right-section .user-icon {
            display: block !important;
            cursor: pointer;
            pointer-events: auto !important;
            z-index: 15;
            position: relative;
        }
    
        .top-right-section .user-icon img {
            pointer-events: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
    
        /* Style user info for mobile */
        .top-right-section .user-info {
            display: flex !important;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            position: relative;
            z-index: 15;
        }
    
        .top-right-section .user-info .user-name {
            font-size: 12px;
            color: white;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
        }
    
        .top-right-section .user-info .user-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }
    
        .top-right-section .user-info .profile-btn,
        .top-right-section .user-info .logout-btn {
            font-size: 10px !important;
            padding: 4px 8px !important;
            border-radius: 3px !important;
            text-decoration: none;
            cursor: pointer;
            pointer-events: auto !important;
            z-index: 20;
            position: relative;
        }
    
        .top-right-section .user-info .profile-btn {
            background-color: #4CAF50;
            color: white;
        }
    
        .top-right-section .user-info .logout-btn {
            background-color: #dc3545;
            color: white;
            border: none;
        }
    
        .top-right-section .booking-search input {
            width: 140px;
            font-size: 10px;
        }
    
        /* Mobile hero content appears after search */
        .mobile-hero-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
            text-align: center;
            padding: 40px 20px;
        }
    
        .mobile-hero-content .hero-text {
            display: block !important;
            position: relative !important;
            top: auto !important;
            left: auto !important;
            transform: none !important;
            max-width: 90% !important;
            margin-bottom: 30px;
        }
    
        .mobile-hero-content .hero-text h1 {
            font-size: 28px;
            line-height: 1.2;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
        }
    
        .mobile-hero-content .menu-img {
            display: flex !important;
            position: relative !important;
            flex-direction: row !important;
            justify-content: center !important;
            gap: 10px;
            margin-top: 20px;
        }
    
        .mobile-hero-content .menu-img img {
            width: 70px;
            height: 55px;
            border-radius: 5px;
        }
    }
</style>

<script>
function searchBooking(inputId, resultsId) {
    const referenceNumber = document.getElementById(inputId).value.trim();
    const resultsContainer = document.getElementById(resultsId);
    
    if (!referenceNumber) {
        resultsContainer.innerHTML = '<div class="search-result-item">Please enter a reference number.</div>';
        resultsContainer.style.display = 'block';
        return;
    }
    
    resultsContainer.innerHTML = '<div class="search-result-item">Searching...</div>';
    resultsContainer.style.display = 'block';
    
    fetch('searchbookingp2.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'reference=' + encodeURIComponent(referenceNumber)
    })
    .then(response => {
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse response as JSON:', text);
                throw new Error('Invalid response from server: ' + e.message);
            }
        });
    })
    .then(data => {
        resultsContainer.innerHTML = ''; // Clear previous content

        if (data.error) {
            resultsContainer.innerHTML = `<div class="search-result-item">${data.error}</div>`;
            resultsContainer.style.display = 'block';
            return;
        }
        
        if (!data.results || data.results.length === 0) {
            resultsContainer.innerHTML = '<div class="search-result-item">No bookings found with this reference number</div>';
            resultsContainer.style.display = 'block';
            return;
        }
        
        // Filter valid and expired matches
        const validMatches = data.results.filter(booking => 
            booking.reference_number.toLowerCase() === referenceNumber.toLowerCase() && 
            !booking.is_expired);
        const expiredMatches = data.results.filter(booking => 
            booking.reference_number.toLowerCase() === referenceNumber.toLowerCase() && 
            booking.is_expired);
            
        // If no valid or expired matches, show no match found
        if (validMatches.length === 0 && expiredMatches.length === 0) {
            resultsContainer.innerHTML = '<div class="search-result-item">No exact match found for reference number: ' + referenceNumber + '</div>';
            resultsContainer.style.display = 'block';
            return;
        }
        
        let html = '';
        
        // Display valid matches first (if any)
        if (validMatches.length > 0) {
            validMatches.forEach(booking => {
                html += `
                <div class="search-result-item">
                    <strong>Booking #${booking.reference_number}</strong>
                    <p>Name: ${booking.name}</p>
                    <p>Date: ${booking.booking_date}</p>
                    <p>Check-in: ${booking.check_in}</p>
                    <p>Check-out: ${booking.check_out}</p>
                    <p>Status: ${booking.status}</p>
                    <p>Amount: ${booking.total_amount}</p>
                    ${booking.status.toLowerCase() !== 'approved' ? 
                      `<a href="saved_billing.php?code=${booking.reservation_code}&type=${booking.reservation_type}" class="continue-payment-btn">Continue Payment</a>` : 
                      ''}
                </div>`;
            });
        }
        
        // Then add expired matches (if any)
        if (expiredMatches.length > 0) {
            expiredMatches.forEach(booking => {
                html += `
                <div class="search-result-item expired-booking">
                    <strong>Booking #${booking.reference_number}</strong> <span class="expired-label">EXPIRED</span>
                    <p>Name: ${booking.name}</p>
                    <p>Date: ${booking.booking_date}</p>
                    <p>Check-in: ${booking.check_in}</p>
                    <p>Check-out: ${booking.check_out}</p>
                    <p>Status: ${booking.status}</p>
                    <p>Amount: ${booking.total_amount}</p>
                </div>`;
            });
        }
        
        resultsContainer.innerHTML = html;
        resultsContainer.style.display = 'block';
    })
    .catch(error => {
        console.error('Error details:', error);
        resultsContainer.innerHTML = '<div class="search-result-item">An error occurred while searching. Please try again. Details: ' + error.message + '</div>';
        resultsContainer.style.display = 'block';
    });
}

// Close search results when clicking outside
document.addEventListener('click', function(event) {
    const searchContainers = document.querySelectorAll('.booking-search');
    searchContainers.forEach(container => {
        if (!container.contains(event.target)) {
            const dropdown = container.querySelector('.search-results-dropdown');
            if (dropdown) {
                dropdown.style.display = 'none';
            }
        }
    });
});

// Add Enter key support for search
document.addEventListener('DOMContentLoaded', function() {
    const desktopInput = document.getElementById('reference-search');
    const mobileInput = document.getElementById('reference-search-mobile');
    
    if (desktopInput) {
        desktopInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchBooking('reference-search', 'search-results');
            }
        });
    }
    
    if (mobileInput) {
        mobileInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchBooking('reference-search-mobile', 'search-results-mobile');
            }
        });
    }
});
</script>