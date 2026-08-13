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
$current_user = getUserStatus();
$feedback_success = "";

// Fetch content from database
$mysqli = require __DIR__ . "/database.php";

// Function to get content by section name
function getContentBySection($mysqli, $section_name) {
    $stmt = $mysqli->prepare("SELECT content FROM site_content WHERE section_name = ?");
    $stmt->bind_param("s", $section_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['content'] ?? '';
    }
    
    $stmt->close();
    return '';
}

// Fetch all contact page content
$contact_heading = getContentBySection($mysqli, 'contact_heading');
$get_in_touch = getContentBySection($mysqli, 'get_in_touch');
$follow_us = getContentBySection($mysqli, 'follow_us');
$visit_us = getContentBySection($mysqli, 'visit_us');
$contact_form = getContentBySection($mysqli, 'contact_form');

// Fetch contact details
$phone = getContentBySection($mysqli, 'contact_phone');
$email = getContentBySection($mysqli, 'contact_email');
$facebook = getContentBySection($mysqli, 'contact_facebook');
$address = getContentBySection($mysqli, 'contact_address');

// Handle feedback form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email_input = $_POST['email'];
    $message = $_POST['message'];
    $rating = $_POST['rating'];
    $section = $_POST['section'];

    $stmt = $mysqli->prepare("INSERT INTO feedbacks (name, email, message, rating, section, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("sssis", $name, $email_input, $message, $rating, $section);

    if ($stmt->execute()) {
        $feedback_success = "Thank you so much for your feedback!";
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CONTACT US - Rainbow Forest Paradise Resort</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link rel="stylesheet" href="styles/mystyle.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Acme&family=Dancing+Script:wght@400..700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Lobster&family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400..700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        .content-wrapper {
            width: 90%;
            max-width: 1400px;
            margin: 50px auto;
            padding: 10px;
        }
        
        .contact-flex {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
        }
        .content-wrapper h1 {
            font-size: 2.8em;
            margin: 10px;
            text-align: center;
            color: #ffd700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
       
        .contact-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            margin: 30px;
            padding: 30px;
            text-align: center;
            transition: transform 0.2s ease-in-out;
        }
        .contact-item:hover {
            transform: translateY(-5px);
        }
        .contact-item h2 {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #ffd700;
        }
        .contact-item p {
            font-size: 1em;
            margin-bottom: 15px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-all;
        }
        .contact-item a {
            color: #ffffff;
            font-weight: bold;
            text-decoration: none;
            position: relative;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-all;
        }
        .contact-item a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            background: #ffd700;
            bottom: -2px;
            left: 0;
            transition: width 0.3s ease-in-out;
        }
        .contact-item a:hover::after {
            width: 100%;
        }
        .contact-form {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 30px;
            margin-top: 30px;
            margin-left: auto;
            margin-right: auto;
        }
        .contact-form h2 {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #ffd700;
            text-align: center;
        }
        .contact-form label {
            display: block;
            text-align: left;
            margin: 15px 0 5px;
            font-weight: bold;
        }
        .contact-form input,
        .contact-form textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: none;
            border-radius: 5px;
            background-color: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            font-family: inherit;
        }
        .contact-form input::placeholder,
        .contact-form textarea::placeholder {
            color: #ffffff;
            opacity: 0.7;
        }
        .contact-form textarea {
            resize: vertical;
            min-height: 120px;
        }
        .contact-form button {
            background: #ffd700;
            color: #2d6a4f;
            padding: 12px 25px;
            font-size: 1em;
            font-weight: bold;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out;
        }
        .contact-form button:hover {
            background: #ffffff;
            color: #2d6a4f;
        }
        .star-rating {
            display: inline-flex;
            flex-direction: row;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .star-rating input:checked ~ label {
            color: #ddd;
        }
        .star-rating input:checked + label,
        .star-rating input:checked ~ label {
            color: #ffd700;
        }
        .feedback-success {
            background-color: rgba(255, 215, 0, 0.1);
            border: 1px solid #ffd700;
            color: #ffd700;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
        }
        .submit-wrapper {
            text-align: center;
            margin-top: 20px;
        }

        button[type="submit"] {
            padding: 10px 20px;
            font-size: 16px;
        }
        .cancellation-notice {
            background-color: #fff;
            color: #000;
            padding: 15px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
        }

        .cancellation-notice a {
            color: #000;
            text-decoration: underline;
        }

        .cancellation-notice a:hover {
            color: red;
        }
        .contact-left {
            flex: 1;
            max-width: 40%; 
        }
        
        .contact-right {
            flex: 1;
            max-width: 55%;
        }
        
        @media (min-width: 769px) {
            .mobile-user {
                display: none !important;
            }
            
            .desktop-user {
                display: flex;
            }
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 10px 15px;
            }
        
            .navbar {
                flex-wrap: wrap;
                gap: 10px;
            }
        
            .mobile-user {
                display: flex;
                margin-left: 50px;
            }
            
            .desktop-user {
                display: none !important;
            }
        
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
        
        @media (max-width: 480px) {
            .contact-item {
                margin: 10px 0;
                padding: 15px;
            }
            
            .contact-item p,
            .contact-item a {
                font-size: 0.9em;
                word-break: break-word;
            }
        }
        
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
        
        @media (max-width: 900px) {
            .contact-flex {
                flex-direction: column;
            }
            .contact-left, .contact-right {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
<?php include 'headers/header.php'; ?>
<main class="content-wrapper">
    <!-- Display heading from database or default -->
    <h1><?= !empty($contact_heading) ? htmlspecialchars($contact_heading) : 'Contact Us' ?></h1>

    <div class="cancellation-notice">
        <p><strong>Important Notice:</strong> Our resort typically does not offer cancellations or refunds. However, if there are exceptional circumstances (e.g., weather conditions), please contact us immediately. For cancellation inquiries or urgent issues, feel free to reach out to us via 
        <a href="<?= htmlspecialchars($facebook ?: 'https://www.facebook.com/profile.php?id=100050508021940') ?>" target="_blank">Facebook Messenger</a> or call us at 
        <a href="tel:<?= htmlspecialchars($phone ?: '09605877561') ?>"><?= htmlspecialchars($phone ?: '0960 587 7561') ?></a>. We will do our best to assist you.</p>
    </div>

    <div class="contact-flex">
        <!-- LEFT SIDE -->
        <div class="contact-left">
            <section class="contact-item">
                <?php if (!empty($get_in_touch)): ?>
                    <?= $get_in_touch ?>
                <?php else: ?>
                    <h2>Get in Touch</h2>
                    <p>Have questions? We're here to help.</p>
                    <p><strong>Phone:</strong> <a href="tel:<?= htmlspecialchars($phone ?: '09605877561') ?>"><?= htmlspecialchars($phone ?: '0960 587 7561') ?></a></p>
                    <p><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($email ?: 'rainbowforestparadise2020@gmail.com') ?>"><?= htmlspecialchars($email ?: 'rainbowforestparadise2020@gmail.com') ?></a></p>
                <?php endif; ?>
            </section>
            
            <section class="contact-item">
                <?php if (!empty($follow_us)): ?>
                    <?= $follow_us ?>
                <?php else: ?>
                    <h2>Follow Us</h2>
                    <p>Stay updated with our latest news and promotions.</p>
                    <p><a href="<?= htmlspecialchars($facebook ?: 'https://www.facebook.com/profile.php?id=100050508021940') ?>" target="_blank">Facebook Page</a></p>
                <?php endif; ?>
            </section>

            <section class="contact-item">
                <?php if (!empty($visit_us)): ?>
                    <?= $visit_us ?>
                <?php else: ?>
                    <h2>Visit Us</h2>
                    <p>Relax in nature at Rainbow Forest Paradise Resort.</p>
                    <p><?= nl2br(htmlspecialchars($address ?: 'Brgy. Cuyambay, Tanay, Rizal')) ?></p>
                <?php endif; ?>
            </section>
        </div>

        <!-- RIGHT SIDE -->
        <div class="contact-right">
            <section class="contact-form">
                <?php if (!empty($contact_form)): ?>
                    <?= $contact_form ?>
                <?php else: ?>
                    <h2>Send Us a Feedback</h2>

                    <?php if (!empty($feedback_success)): ?>
                        <div class="feedback-success">
                            <?= htmlspecialchars($feedback_success) ?>
                        </div>
                    <?php endif; ?>

                    <form action="contact.php" method="post">
                        <input type="hidden" name="section" value="private">

                        <label for="name">Your Name:</label>
                        <input type="text" id="name" name="name" placeholder="Enter your name" required>
                        
                        <label for="email">Your Email:</label>
                        <input type="email" id="email" name="email" placeholder="Enter your email" required>
                        
                        <label for="message">Your Message:</label>
                        <textarea id="message" name="message" rows="5" placeholder="Write your message here..." required></textarea>
                        
                        <label for="rating">Rating:</label>
                        <div class="star-rating">
                            <input type="radio" id="star1" name="rating" value="1">
                            <label for="star1">&#9733;</label>

                            <input type="radio" id="star2" name="rating" value="2">
                            <label for="star2">&#9733;</label>

                            <input type="radio" id="star3" name="rating" value="3">
                            <label for="star3">&#9733;</label>

                            <input type="radio" id="star4" name="rating" value="4">
                            <label for="star4">&#9733;</label>

                            <input type="radio" id="star5" name="rating" value="5" checked>
                            <label for="star5">&#9733;</label>
                        </div>

                        <div class="submit-wrapper">
                            <button type="submit">Send Message</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </div>

</main>
<?php include 'headers/footer.php'; ?>
<script>
    const stars = document.querySelectorAll('.star-rating input');
    const labels = document.querySelectorAll('.star-rating label');

    function updateStars() {
        let selected = -1;
        stars.forEach((star, i) => {
            if (star.checked) selected = i;
        });

        labels.forEach((label, index) => {
            label.style.color = index <= selected ? '#ffd700' : '#ddd';
        });
    }

    stars.forEach((star, index) => {
        star.addEventListener('change', updateStars);
    });

    labels.forEach((label, index) => {
        label.addEventListener('click', () => {
            stars[index].checked = true;
            updateStars();
        });
    });

    updateStars();
</script>
</body>
</html>