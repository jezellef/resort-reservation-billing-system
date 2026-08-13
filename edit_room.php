<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}
require_once 'db_connect.php';


// Check if a room ID is provided for editing
if (isset($_GET['id'])) {
    $roomId = $_GET['id'];
    
    // Fetch room details based on the provided room ID
    $sql = "SELECT * FROM rooms WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $result = $stmt->get_result();
    $room = $result->fetch_assoc();
    $stmt->close();

    if (!$room) {
        echo "Room not found.";
        exit;
    }
} else {
    echo "Invalid room ID.";
    exit;
}

// Define upload directory
$uploadDir = 'uploads/rooms/';
// Create directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Initialize variables for error/success messages
$message = '';
$messageType = '';

// Update room details when form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'] ?? '';
    $day_tour_price = $_POST['day_tour_price'] ?? 0;
    $night_tour_price = $_POST['night_tour_price'] ?? 0;
    $quantity = $_POST['quantity'] ?? 0;
    $status = $_POST['status'] ?? 'Available';
    $capacity = $_POST['capacity'] ?? 0;
    
    // Validate non-zero values
    $validationErrors = [];
    
    if (empty($name)) {
        $validationErrors[] = "Room name cannot be empty.";
    }
    
    if (empty($day_tour_price) || $day_tour_price <= 0) {
        $validationErrors[] = "Day tour price must be greater than zero.";
    }
    
    if (empty($night_tour_price) || $night_tour_price <= 0) {
        $validationErrors[] = "Night tour price must be greater than zero.";
    }
    
    if (empty($quantity) || $quantity <= 0) {
        $validationErrors[] = "Quantity must be greater than zero.";
    }
    
    if (empty($capacity) || $capacity <= 0) {
        $validationErrors[] = "Capacity must be greater than zero.";
    }
    
    // If validation errors exist, show them and stop processing
    if (!empty($validationErrors)) {
        $message = "<ul><li>" . implode("</li><li>", $validationErrors) . "</li></ul>";
        $messageType = "danger";
    } else {
        // Initialize variable to store new image path
        $imagePath = $room['image'] ?? ''; // Keep existing image by default
        
        // Handle image upload
        if (isset($_FILES['room_image']) && $_FILES['room_image']['size'] > 0) {
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            $fileName = $_FILES['room_image']['name'];
            $fileTmpName = $_FILES['room_image']['tmp_name'];
            $fileSize = $_FILES['room_image']['size'];
            $fileError = $_FILES['room_image']['error'];
            
            // Get file extension
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Check if file extension is allowed
            if (in_array($fileExt, $allowedExtensions)) {
                // Check for upload errors
                if ($fileError === 0) {
                    // Check file size (limit to 5MB)
                    if ($fileSize < 5000000) {
                        // Create unique file name to prevent overwriting
                        $newFileName = 'room_' . $roomId . '_' . uniqid('', true) . '.' . $fileExt;
                        $fileDestination = $uploadDir . $newFileName;
                        
                        // Move uploaded file
                        if (move_uploaded_file($fileTmpName, $fileDestination)) {
                            // Store new image path in database
                            $imagePath = $fileDestination;
                            
                            // Delete old image if it exists and is not the default
                            if (!empty($room['image']) && file_exists($room['image']) && $room['image'] != 'uploads/rooms/default_room.jpg') {
                                unlink($room['image']);
                            }
                        } else {
                            $message = "Failed to upload image.";
                            $messageType = "danger";
                        }
                    } else {
                        $message = "File size is too large (max 5MB).";
                        $messageType = "danger";
                    }
                } else {
                    $message = "Error uploading file: " . $fileError;
                    $messageType = "danger";
                }
            } else {
                $message = "Invalid file type. Allowed types: jpg, jpeg, png, gif.";
                $messageType = "danger";
            }
        }
        
        // If no errors with the image, proceed with updating the room
        if (empty($messageType) || $messageType !== "danger") {
            $updateSql = "UPDATE rooms SET name = ?, day_tour_price = ?, night_tour_price = ?, quantity = ?, status = ?, capacity = ?, image = ? WHERE id = ?";
            $stmt = $conn->prepare($updateSql);
            $stmt->bind_param("sssdsiss", $name, $day_tour_price, $night_tour_price, $quantity, $status, $capacity, $imagePath, $roomId);
            
            if ($stmt->execute()) {
                $message = "Room details updated successfully.";
                $messageType = "success";
                
                // Refresh room data after update
                $sql = "SELECT * FROM rooms WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $roomId);
                $stmt->execute();
                $result = $stmt->get_result();
                $room = $result->fetch_assoc();
            } else {
                $message = "Error updating room details: " . $conn->error;
                $messageType = "danger";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Room</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/adminstyle.css">
    <style>
        .img-preview {
            max-width: 300px;
            max-height: 200px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
        }
        .custom-file-upload {
            border: 1px solid #ccc;
            display: inline-block;
            padding: 6px 12px;
            cursor: pointer;
            background-color: #f8f9fa;
            border-radius: 4px;
            margin-top: 5px;
        }
        .preview-container {
            margin-bottom: 20px;
        }
        .error-feedback {
            color: #dc3545;
            font-size: 80%;
            margin-top: .25rem;
        }
    </style>
</head>
<body>
<?php include 'headers/adminheader.php'; ?>
<div class="main-content">
    <div class="container mt-4">
        <h2>Edit Room</h2>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?>" role="alert">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="edit_room.php?id=<?= $room['id'] ?>" enctype="multipart/form-data" id="roomEditForm">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="name" class="form-label">Room Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($room['name'] ?? '') ?>" required>
                        <div class="error-feedback" id="name-error"></div>
                    </div>

                    <div class="mb-3">
                        <label for="day_tour_price" class="form-label">Day Tour Price (₱)</label>
                        <input type="number" class="form-control" id="day_tour_price" name="day_tour_price" value="<?= htmlspecialchars($room['day_tour_price'] ?? '') ?>" min="1" step="0.01" required>
                        <div class="error-feedback" id="day-price-error"></div>
                    </div>

                    <div class="mb-3">
                        <label for="night_tour_price" class="form-label">Night Tour Price (₱)</label>
                        <input type="number" class="form-control" id="night_tour_price" name="night_tour_price" value="<?= htmlspecialchars($room['night_tour_price'] ?? '') ?>" min="1" step="0.01" required>
                        <div class="error-feedback" id="night-price-error"></div>
                    </div>

                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" value="<?= htmlspecialchars($room['quantity'] ?? '') ?>" min="1" required>
                        <div class="error-feedback" id="quantity-error"></div>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="Available" <?= ($room['status'] ?? '') === 'Available' ? 'selected' : '' ?>>Available</option>
                            <option value="Unavailable" <?= ($room['status'] ?? '') === 'Unavailable' ? 'selected' : '' ?>>Unavailable</option>
                            <option value="Under Maintenance" <?= ($room['status'] ?? '') === 'Under Maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="capacity" class="form-label">Capacity</label>
                        <input type="number" class="form-control" id="capacity" name="capacity" value="<?= htmlspecialchars($room['capacity'] ?? '') ?>" min="1" required>
                        <div class="error-feedback" id="capacity-error"></div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="room_image" class="form-label">Room Image</label>
                        <div class="preview-container">
                            <?php if (!empty($room['image'])): ?>
                                <p>Current Image:</p>
                                <img src="<?= htmlspecialchars($room['image'] ?? 'uploads/rooms/default_room.jpg') ?>" alt="<?= htmlspecialchars($room['name'] ?? 'Room') ?>" class="img-preview">
                            <?php else: ?>
                                <p>No image available</p>
                            <?php endif; ?>
                        </div>
                        <label class="custom-file-upload">
                            <input type="file" name="room_image" id="room_image" onchange="previewImage(this);">
                            <i class="fas fa-upload"></i> Choose New Image
                        </label>
                        <div id="image-preview-new" class="preview-container" style="display: none;">
                            <p>New Image Preview:</p>
                            <img id="preview-img" class="img-preview">
                        </div>
                        <small class="form-text text-muted">
                            Upload a new image to change the current one. Accepted formats: JPG, JPEG, PNG, GIF. Maximum file size: 5MB.
                        </small>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary" id="submitBtn">Update Room</button>
                <a href="rooms_public.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Function to preview image before upload
    function previewImage(input) {
        var preview = document.getElementById('preview-img');
        var previewContainer = document.getElementById('image-preview-new');
        
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                previewContainer.style.display = 'block';
            }
            
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.src = '';
            previewContainer.style.display = 'none';
        }
    }
    
    // Client-side validation for non-zero values
    document.getElementById('roomEditForm').addEventListener('submit', function(event) {
        let hasErrors = false;
        
        // Reset error messages
        document.querySelectorAll('.error-feedback').forEach(el => el.textContent = '');
        
        // Validate room name
        const name = document.getElementById('name').value.trim();
        if (name === '') {
            document.getElementById('name-error').textContent = 'Room name cannot be empty.';
            hasErrors = true;
        }
        
        // Validate day tour price
        const dayPrice = parseFloat(document.getElementById('day_tour_price').value);
        if (isNaN(dayPrice) || dayPrice <= 0) {
            document.getElementById('day-price-error').textContent = 'Day tour price must be greater than zero.';
            hasErrors = true;
        }
        
        // Validate night tour price
        const nightPrice = parseFloat(document.getElementById('night_tour_price').value);
        if (isNaN(nightPrice) || nightPrice <= 0) {
            document.getElementById('night-price-error').textContent = 'Night tour price must be greater than zero.';
            hasErrors = true;
        }
        
        // Validate quantity
        const quantity = parseInt(document.getElementById('quantity').value);
        if (isNaN(quantity) || quantity <= 0) {
            document.getElementById('quantity-error').textContent = 'Quantity must be greater than zero.';
            hasErrors = true;
        }
        
        // Validate capacity
        const capacity = parseInt(document.getElementById('capacity').value);
        if (isNaN(capacity) || capacity <= 0) {
            document.getElementById('capacity-error').textContent = 'Capacity must be greater than zero.';
            hasErrors = true;
        }
        
        // Prevent form submission if there are errors
        if (hasErrors) {
            event.preventDefault();
        }
    });
</script>
</body>
</html>