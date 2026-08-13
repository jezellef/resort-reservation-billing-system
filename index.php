<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start(); // Start the session at the beginning

$mysqli = require __DIR__ . "/database.php";

$activities = [];
$reminders = [];
$food = [];
$content = []; // For other content


$results = $mysqli->query("SELECT section, content_value FROM page_content WHERE page = 'index'");

// Process each row
while ($row = $results->fetch_assoc()) {
    $section = $row['section'];
    $json = stripslashes($row['content_value']);
    $decoded = json_decode($json, true);
    
    // Check if json_decode was successful
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error for section {$section}: " . json_last_error_msg());
        continue; // Skip this item
    }
    
    // Sort into appropriate arrays
    if ($section === 'activities') {
        $activities[] = $decoded;
    } 
    else if ($section === 'reminders') {
        $reminders[] = $decoded;
    } 
    else if ($section === 'food') {
        $food[] = $decoded;
    } 
    else {
        // Handle other content types
        if (isset($decoded['image'])) {
            $content[$section] = $decoded['image'];
        } 
        else if (isset($decoded['text'])) {
            $content[$section] = $decoded['text'];
        } 
        else {
            $content[$section] = $decoded;
        }
    }
}

$stmt = $mysqli->prepare("SELECT name, message, rating FROM feedbacks WHERE status = 'approved'");
$stmt->execute();
$result = $stmt->get_result();
$approved_feedbacks = $result->fetch_all(MYSQLI_ASSOC);
if (isset($_GET['check_in']) && isset($_GET['check_out'])) {
    // Validate dates before storing in session
    $check_in = date('Y-m-d', strtotime($_GET['check_in']));
    $check_out = date('Y-m-d', strtotime($_GET['check_out']));
    
    if ($check_in && $check_out && $check_in < $check_out) {
        $_SESSION['check_in'] = $check_in;
        $_SESSION['check_out'] = $check_out;
    }
}
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rainbow Forest Paradise Resort and Campsite</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link rel="stylesheet" href="styles/mystyle.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Acme&family=Dancing+Script:wght@400..700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Lobster&family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400..700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="flatpickr.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
        .hero {
            position: relative;
            width: 100%;
            height: 100vh;
            background-image: url('images/img27.jpg');
            background-size: cover;
            background-position: center;
            transition: background-image 1s ease-in-out; 
        }
        .message-box {
            color: #03624c;
            border-radius: 5px;
            margin-top: 20px;
        }
        .loading {
            color: blue;
        }
        .error {
            color: #03624c;
        }
        #proceedToReservation {
            padding: 10px 18px;
            background: green;
            margin-top: 15px;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }
        .account-info {
            margin-top: 150px;
            margin-bottom: 150px;
            background-color: #f0f0f0;
        }

        .account-info p{
            line-height: 3;
            margin-top: 15px;
            margin-bottom: 15px;
        }
        
        h1{
            alignment: center;
        }

        .user-info{
            display: flex;
            flex-direction: column;
        }
        .profile-btn{
            color:white;
        }

        .accom-divider {
            width: 120px;
            height: 4px;
            background-color:#508E87;
            margin: 10px 0;
            border-radius: 10px;
        }
        .indent {
            margin-left: 2em;
        }
        .sections-container {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            gap: 20px;
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
          
        .content-section {
            flex: 1;
            min-width: 30%;
            padding: 15px;
            border-radius: 10px;
            background-color: #f9f9f9;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
        }
          
        .section-title {
            text-align: center;
            color: #03624c;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #afd757;
        }
          
        .grid-content {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
          
        .grid-item {
            position: relative;
            height: 180px;
            border-radius: 8px;
            overflow: hidden;
            background-size: cover;
            background-position: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }
          
        .grid-item:hover {
            transform: translateY(-5px);
        }
          
        .item-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px;
            background: rgba(3, 98, 76, 0.8);
            color: white;
            text-align: center;
            transition: all 0.3s ease;
        }
          
        .grid-item:hover .item-overlay {
            background: rgba(3, 98, 76, 0.9);
        }
          
        .item-overlay h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 500;
        }
          
        .item-overlay p {
            margin: 5px 0 0;
            font-size: 12px;
            opacity: 0.9;
        }

        @media (max-width: 1024px) {
            .sections-container {
              flex-wrap: wrap;
        }
            
        .content-section {
              min-width: 45%;
            }
        }
          
        @media (max-width: 768px) {
            .sections-container {
              flex-direction: column;
        }
            
        .content-section {
              width: 100%;
              margin-bottom: 20px;
            }
        }
         
        .booking-section {
            max-width: 1200px;
            margin: 20px auto;
            padding: 10px;
        }

        .section-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-header h2 {
            font-size: 36px;
            color: #fff;
            margin-bottom: 10px;
        }

        .section-header p {
            font-size: 18px;
            color: #fff;
            max-width: 700px;
            margin: 0 auto;
        }

        .booking-options {
            display: flex;
            justify-content: space-between;
            gap: 30px;
        }

        .booking-option {
            flex: 1;
            background-color: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .booking-option:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .option-image {
            height: 250px;
            overflow: hidden;
            position: relative;
        }

        .option-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .booking-option:hover .option-image img {
            transform: scale(1.1);
        }

        .option-content {
            padding: 25px;
        }

        .option-content h3 {
            font-size: 24px;
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .option-content p {
            margin-bottom: 20px;
            color: #7f8c8d;
        }

        .book-btn {
            display: inline-block;
            padding: 12px 30px;
            background-color: #9b59b6; 
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s ease;
            text-align: center;
        }

        .book-btn:hover {
            background-color: #03624c;
        }

        .private .book-btn {
            background-color: #3498db;
        }

        .private .book-btn:hover {
            background-color: #03624c;
        }

        .badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: rgba(155, 89, 182, 0.9);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: bold;
        }
        .private .badge {
                 background-color: rgba(52, 152, 219, 0.9);
        }

        @media screen and (max-width: 768px) {
            .booking-options {
                flex-direction: column;
            }
        }
        
        .containerflex {
            display: flex;
            justify-content: space-between;
            gap: 30px;
            padding: 20px;
            flex-wrap: wrap;
        }
        
        /* Section Box Styling */
        .info-box {
            flex: 1;
            min-width: 300px;
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }
        
        .info-box:hover {
            transform: translateY(-5px);
        }
        
        /* Titles */
        .section-title {
            font-size: 24px;
            color: #2f4f4f;
            border-bottom: 2px solid #6fb98f;
            padding-bottom: 8px;
            margin-bottom: 16px;
            font-weight: 600;
            text-align: center;
        }
        
        /* Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 20px;
        }
        
        /* Card Items */
        .info-card {
            position: relative;
            height: 180px;
            background-size: cover;
            background-position: center;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.12);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .info-card:hover {
            transform: scale(1.03);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        
        /* Overlay */
        .item-overlay {
            position: absolute;
            bottom: 0;
            width: 100%;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.7), transparent);
            color: #fff;
            padding: 10px;
            text-align: center;
            transition: background 0.3s ease;
        }
        
        .item-overlay h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 500;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .containerflex {
                flex-direction: column;
            }
        
            .info-grid {
                grid-template-columns: 1fr;
            }
        
            .info-box {
                margin-bottom: 20px;
            }
        }
.clickable-card {
    cursor: pointer;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.clickable-card:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}

/* Removed click-hint styles since we're using a general instruction instead */

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    animation: fadeIn 0.3s ease;
}

.modal-content {
    position: relative;
    margin: 5% auto;
    padding: 0;
    width: 90%;
    max-width: 800px;
    background-color: white;
    border-radius: 10px;
    overflow: hidden;
    animation: slideIn 0.3s ease;
}

.modal-header {
    background-color: #03624c;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 20px;
}

.close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
    transition: color 0.3s ease;
}

.close:hover {
    color: #afd757;
}

.modal-body {
    padding: 0;
    text-align: center;
}

#modalImage {
    width: 100%;
    height: auto;
    max-height: 70vh;
    object-fit: contain;
    display: block;
}

.modal-text {
    padding: 20px;
    background-color: #f9f9f9;
}

.modal-text h4 {
    margin: 0;
    color: #03624c;
    font-size: 18px;
    line-height: 1.4;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { 
        transform: translateY(-50px);
        opacity: 0;
    }
    to { 
        transform: translateY(0);
        opacity: 1;
    }
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        margin: 10% auto;
    }
    
    .modal-header h3 {
        font-size: 18px;
    }
    
    .modal-text h4 {
        font-size: 16px;
    }
    
    #modalImage {
        max-height: 60vh;
    }
}
    </style>
</head>
<body>
<?php include 'headers/homeheader.php'; ?>
    <section class="booking-section">
        <div class="section-header">
            <h2><?= htmlspecialchars($content['booking_header'] ?? '') ?></h2>
            <p><?= htmlspecialchars($content['booking_subtext'] ?? '') ?></p>
        </div>
        <div class="booking-options">
            <!-- Public Option -->
            <div class="booking-option public">
                <div class="option-image">
                    <img src="<?= htmlspecialchars($content['public_image'] ?? 'images/placeholder.jpg') ?>" alt="Public accommodation">
                    <div class="badge">PUBLIC</div>
                </div>
                <div class="option-content">
                    <h3><?= htmlspecialchars($content['public_title'] ?? '') ?></h3>
                    <p><?= htmlspecialchars($content['public_description'] ?? '') ?></p>
                    <a href="accommodation.php" class="book-btn">Browse Public</a>
                </div>
            </div>
            <div class="booking-option private">
                <div class="option-image">
                    <img src="<?= htmlspecialchars($content['private_image'] ?? 'images/placeholder.jpg') ?>" alt="Private accommodation">
                    <div class="badge">PRIVATE</div>
                </div>
                <div class="option-content">
                    <h3><?= htmlspecialchars($content['private_title'] ?? '') ?></h3>
                    <p><?= htmlspecialchars($content['private_description'] ?? '') ?></p>
                    <a href="accommodation.php" class="book-btn">Browse Private</a>
                </div>
            </div>
        </div>
    </section>
    
       <section class="gallery-section" id="amenities">
        <h1 class="gallery-title">Our Gallery</h1>
        <div class="gallery-grid">
            <?php
            $conn = new mysqli('localhost', 'u291458526_resort_user', 'r@inboWforest123!', 'u291458526_resort_db');
            $result = $conn->query("SELECT * FROM gallery_images");
            $count = 0;
            $groupOpen = false;
    
            while ($row = $result->fetch_assoc()) {
                if ($count < 5) {
                    echo '<img src="' . htmlspecialchars($row['image_url']) . '" alt="Gallery Image">';
                } else {
                    if (($count - 5) % 5 === 0) {
                        if ($groupOpen) echo '</div>'; 
                        echo '<div class="extra-images hidden">';
                        $groupOpen = true;
                    }
                    echo '<img src="' . htmlspecialchars($row['image_url']) . '" alt="Gallery Image">';
                }
                $count++;
            }
    
            if ($groupOpen) echo '</div>';
            ?>
        </div>
        <?php if ($count > 5): ?>
            <button id="toggleGallery" class="gallery-toggle-btn">See More</button>
        <?php endif; ?>
    </section>

    
    <section class="abouthome" id="abouthome">
        <div class="containerflex">
            <div class="left">
                <div class="img">
                    <img src="<?= htmlspecialchars($content['abouthome_image_1'] ?? 'images/IMG_4859.jpg') ?>" alt="" class="image1">
                    <img src="<?= htmlspecialchars($content['abouthome_image_2'] ?? 'images/img23.jpg') ?>" alt="" class="image2">
                </div>
            </div>
            <div class="right">
                <div class="heading">
                    <h5><?= htmlspecialchars($content['abouthome_title'] ?? '') ?></h5>
                    <div class="accom-divider"></div>
                    <h2><?= htmlspecialchars($content['abouthome_heading'] ?? '') ?></h2>
                    <p class="indent"><?= nl2br(htmlspecialchars($content['abouthome_paragraph_1'] ?? '')) ?></p>
                    <p class="indent"><?= nl2br(htmlspecialchars($content['abouthome_paragraph_2'] ?? '')) ?></p>
                    <a href="accommodation.php"><button class="btn1" style="cursor: pointer;">READ MORE</button></a>
                </div>
            </div>
        </div>
    </section>
     
    <section class="info-sections">
        <div class="containerflex three-columns">
            <div class="info-box">
                <h2 class="section-title">Activities</h2>
                <div class="info-grid">
                    <?php foreach ($activities as $index => $item): ?>
                        <div class="info-card clickable-card" 
                             style="background-image: url('<?= htmlspecialchars($item['image'] ?? '') ?>');"
                             onclick="openModal('<?= htmlspecialchars($item['image'] ?? '') ?>', '<?= htmlspecialchars($item['text'] ?? '') ?>', 'Activities')">
                            <div class="item-overlay">
                                <h3><?= htmlspecialchars($item['text'] ?? '') ?></h3>
    
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
    
            <div class="info-box">
                <h2 class="section-title">Reminders</h2>
                <div class="info-grid">
                    <?php foreach ($reminders as $index => $item): ?>
                        <div class="info-card clickable-card" 
                             style="background-image: url('<?= htmlspecialchars($item['image'] ?? '') ?>');"
                             onclick="openModal('<?= htmlspecialchars($item['image'] ?? '') ?>', '<?= htmlspecialchars($item['text'] ?? '') ?>', 'Reminders')">
                            <div class="item-overlay">
                                <h3><?= htmlspecialchars($item['text'] ?? '') ?></h3>
                              
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
    
            <div class="info-box">
                <h2 class="section-title">Food</h2>
                <div class="info-grid">
                    <?php foreach ($food as $index => $item): ?>
                        <div class="info-card clickable-card" 
                             style="background-image: url('<?= htmlspecialchars($item['image'] ?? '') ?>');"
                             onclick="openModal('<?= htmlspecialchars($item['image'] ?? '') ?>', '<?= htmlspecialchars($item['text'] ?? '') ?>', 'Food')">
                            <div class="item-overlay">
                                <h3><?= htmlspecialchars($item['text'] ?? '') ?></h3>
                               
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
    <!-- Modal for full image view -->
    <div id="imageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalCategory"></h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <img id="modalImage" src="" alt="Full Image">
                <div class="modal-text">
                    <h4 id="modalTitle"></h4>
                </div>
            </div>
        </div>
    </div>
    <p style="text-align: center; font-style: italic; color: #fff; margin: 1em auto;">
        Click on any image above to view it in full size.
    </p>
    <p style="text-align: center; font-style: italic; color: #fff; margin: 1em auto;">
        Please note that the activities and food items shown are for display and informational purposes only and are not available for reservation.
    </p>

        <section class="feedback-section" id="feedback-home">
            <div class="feedback-header">
                <h2>What Our Guests Say</h2>
                <p>Your experience matters. Read reviews or share your own!</p>
            </div>
            <div class="feedback-content">
                <?php foreach ($approved_feedbacks as $feedback): ?>
                    <div class="feedback-box">
                        <div class="feedback-review">
                            <h3><?php echo htmlspecialchars($feedback['name']); ?></h3>
                            <p class="stars"><?php echo str_repeat('★', $feedback['rating']); ?></p>
                            <p>"<?php echo htmlspecialchars($feedback['message']); ?>"</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="feedback-button">
                <button class="btn-review" onclick="window.location.href='contact.php'">Write a Review</button>
            </div>
        </section>

<?php include 'headers/footer.php'; ?>
    
    <script>
        function img(anything) {
        document.querySelector('.slide').src = anything;
        }
        function change(change) {
        const line = document.querySelector('.image');
        line.style.background = change;
        }
    </script>
    <script>
        // Function to change the background image of the hero section
        function changeBackground(image, imgElement) {
            document.querySelector('.hero').style.backgroundImage = `url('${image}')`;
            const images = document.querySelectorAll('.menu img');
            images.forEach(img => img.classList.remove('clicked'));
            if (imgElement) imgElement.classList.add('clicked');
        }
    </script>
    <script>
        const toggleBtn = document.getElementById('toggleGallery');
            const imageGroups = document.querySelectorAll('.extra-images');
            let currentIndex = 0;

            toggleBtn.addEventListener('click', () => {
                if (currentIndex < imageGroups.length) {
                    imageGroups[currentIndex].classList.add('show'); 
                    currentIndex++;

                    if (currentIndex === imageGroups.length) {
                toggleBtn.textContent = 'No More Photos';
                toggleBtn.style.display = 'none';  
            }
                }
            });
    </script>
    
<script>
function openModal(imageSrc, title, category) {
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const modalTitle = document.getElementById('modalTitle');
    const modalCategory = document.getElementById('modalCategory');
    
    modalImage.src = imageSrc;
    modalTitle.textContent = title;
    modalCategory.textContent = category;
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeModal() {
    const modal = document.getElementById('imageModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
}

// Close modal when clicking outside the content
document.getElementById('imageModal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});
</script>
</body>
</html>