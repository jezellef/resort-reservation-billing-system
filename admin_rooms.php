<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}
require_once 'db_connect.php';

$success_message = '';
$error_message = '';

// Handle image upload
if (isset($_POST['upload_image'])) {
    $room_id = $_POST['room_id'];
    $image_type = $_POST['image_type']; // 'main' or 'gallery'
    
    $target_dir = "images/";
    $file_extension = strtolower(pathinfo($_FILES["room_image"]["name"], PATHINFO_EXTENSION));
    $allowed_extensions = array("jpg", "jpeg", "png", "gif");
    
    if (in_array($file_extension, $allowed_extensions)) {
        $new_filename = "room_" . $room_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["room_image"]["tmp_name"], $target_file)) {
            if ($image_type == 'main') {
                $stmt = $conn->prepare("UPDATE rooms SET main_image = ? WHERE id = ?");
                $stmt->bind_param("si", $target_file, $room_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO room_images (room_id, image_path, image_type) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $room_id, $target_file, $image_type);
            }
            
            if ($stmt->execute()) {
                $success_message = "Image uploaded successfully!";
            } else {
                $error_message = "Error saving image to database.";
            }
        } else {
            $error_message = "Error uploading image.";
        }
    } else {
        $error_message = "Only JPG, JPEG, PNG & GIF files are allowed.";
    }
}

// Handle content update
if (isset($_POST['update_content'])) {
    $room_id = $_POST['room_id'];
    $detailed_description = $_POST['detailed_description'];
    $amenities = $_POST['amenities']; // JSON string
    
    $stmt = $conn->prepare("UPDATE rooms SET detailed_description = ?, amenities = ? WHERE id = ?");
    $stmt->bind_param("ssi", $detailed_description, $amenities, $room_id);
    
    if ($stmt->execute()) {
        $success_message = "Content updated successfully!";
    } else {
        $error_message = "Error updating content.";
    }
}

// Handle quantity update
if (isset($_POST['update_quantity'])) {
    $roomId = $_POST['room_id'];
    $newQuantity = intval($_POST['new_quantity']);
    if ($newQuantity < 0 || $newQuantity > 50) {
        $error_message = "Invalid quantity. Must be between 0 and 50.";
    } else {
        $updateQuery = "UPDATE rooms SET real_quantity = ?, quantity = ? WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("iii", $newQuantity, $newQuantity, $roomId);
        
        if ($stmt->execute()) {
            if ($newQuantity == 0) {
                $statusQuery = "UPDATE rooms SET status = 'Unavailable' WHERE id = ?";
            } else {
                $statusQuery = "UPDATE rooms SET status = 'Available' WHERE id = ?";
            }
            $statusStmt = $conn->prepare($statusQuery);
            $statusStmt->bind_param("i", $roomId);
            $statusStmt->execute();
            
            $success_message = "Room quantity updated successfully!";
        } else {
            $error_message = "Error updating room quantity.";
        }
    }
}

// Fetch rooms with images and content
$roomsQuery = "
    SELECT 
        r.*,
        COALESCE(SUM(CASE 
            WHEN res.status IN ('Approved', 'Checked In') 
            AND rr.check_in_date <= CURDATE() 
            AND rr.check_out_date > CURDATE() 
            THEN rr.quantity_booked 
            ELSE 0 
        END), 0) as currently_occupied
    FROM rooms r
    LEFT JOIN reservation_room rr ON r.id = rr.room_id
    LEFT JOIN reservations res ON rr.reservation_id = res.id
    GROUP BY r.id
    ORDER BY r.room_type, r.sort_order, r.name
";
$roomsResult = $conn->query($roomsQuery);

$publicRooms = [];
$privateRooms = [];

while ($room = $roomsResult->fetch_assoc()) {
    $room['currently_available'] = max(0, $room['real_quantity'] - $room['currently_occupied']);
    
    // Get gallery images
    $imageQuery = "SELECT * FROM room_images WHERE room_id = ? ORDER BY sort_order";
    $imageStmt = $conn->prepare($imageQuery);
    $imageStmt->bind_param("i", $room['id']);
    $imageStmt->execute();
    $imageResult = $imageStmt->get_result();
    $room['gallery_images'] = $imageResult->fetch_all(MYSQLI_ASSOC);
    
    if ($room['room_type'] == 'private') {
        $privateRooms[] = $room;
    } else {
        $publicRooms[] = $room;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Room Management</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles/adminstyle.css">
    <style>
        .main-content {
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #9dc183 0%, #043927 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .dashboard-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #2c3e50;
        }
        
        .room-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .room-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            border-radius: 10px 10px 0 0;
        }
        
        .room-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .room-body {
            padding: 20px;
        }
        
        .current-images {
            display: flex;
            gap: 10px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        
        .image-thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            border: 2px solid #ddd;
        }
        
        .main-image-thumb {
            border-color: #007bff;
        }
        
        .edit-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
        }
        
        .edit-form.show {
            display: block;
        }
        
        .btn-group-custom {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .quantity-section {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .modal-backdrop {
            z-index: 1040;
        }
        
        .modal {
            z-index: 1050;
        }
         .dashboard-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #2c3e50;
            position: relative;
            padding-bottom: 10px;
        }
        .dashboard-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            height: 4px;
            width: 460px;
            background: linear-gradient(to right, #3498db, #1abc9c);
        }
    </style>
</head>
<body>
<?php include 'headers/adminheader.php'; ?>
<div class="main-content">
<div class="d-flex justify-content-between align-items-center mb-4">
     <h2 class="dashboard-title">Room Management Dashboard</h2>
</div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="admin_add_room.php" class="btn btn-success btn-lg">
            <i class="bi bi-plus-circle"></i> Add New Room
        </a>
    </div>

    <!-- Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Public Rooms Section -->
    <h3 class="mb-3"><i class="bi bi-building"></i> Public Accommodations (<?= count($publicRooms) ?> rooms)</h3>
    
    <?php foreach ($publicRooms as $room): ?>
        <div class="room-card">
            <div class="room-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="room-title"><?= htmlspecialchars($room['name']) ?></div>
                        <div class="d-flex gap-2">
                            <span class="badge bg-<?= $room['status'] == 'Available' ? 'success' : 'secondary' ?>">
                                <?= $room['status'] ?>
                            </span>
                            <span class="badge bg-info">
                                <?= $room['currently_available'] ?>/<?= $room['real_quantity'] ?> Available
                            </span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div><strong>Day:</strong> ₱<?= number_format($room['day_tour_price'], 0) ?></div>
                        <div><strong>Night:</strong> ₱<?= number_format($room['night_tour_price'], 0) ?></div>
                    </div>
                </div>
            </div>
            
            <div class="room-body">
                <!-- Current Images -->
                <div>
                    <strong>Current Images:</strong>
                    <div class="current-images">
                        <?php if ($room['main_image']): ?>
                            <img src="<?= htmlspecialchars($room['main_image']) ?>" class="image-thumb main-image-thumb" alt="Main">
                        <?php endif; ?>
                        <?php foreach (array_slice($room['gallery_images'], 0, 3) as $image): ?>
                            <img src="<?= htmlspecialchars($image['image_path']) ?>" class="image-thumb" alt="Gallery">
                        <?php endforeach; ?>
                        <?php if (empty($room['main_image']) && empty($room['gallery_images'])): ?>
                            <span class="text-muted">No images uploaded</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quantity Update -->
                <div class="quantity-section">
                    <strong>Available Units:</strong>
                    <form method="POST" class="d-flex align-items-center gap-2 mt-2">
                        <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                        <input type="number" name="new_quantity" value="<?= $room['real_quantity'] ?>" 
                               min="0" max="50" class="form-control" style="width: 100px;">
                        <button type="submit" name="update_quantity" class="btn btn-success btn-sm">Update</button>
                    </form>
                </div>
                
                <!-- Action Buttons -->
                <div class="btn-group-custom">
                    <button class="btn btn-primary" onclick="toggleEdit(<?= $room['id'] ?>)">
                        <i class="bi bi-pencil"></i> Edit Content
                    </button>
                    <button class="btn btn-info" onclick="openImageModal(<?= $room['id'] ?>)">
                        <i class="bi bi-images"></i> Add Images
                    </button>
                </div>
                
                <!-- Edit Form -->
                <div id="editForm-<?= $room['id'] ?>" class="edit-form">
                    <form method="POST">
                        <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Room Description</label>
                            <textarea name="detailed_description" class="form-control" rows="4" 
                                      placeholder="Enter detailed room description..."><?= htmlspecialchars($room['detailed_description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Amenities (JSON format)</label>
                            <textarea name="amenities" class="form-control" rows="3" 
                                      placeholder='["WiFi", "Air Conditioning", "Private Bathroom"]'><?= htmlspecialchars($room['amenities'] ?? '') ?></textarea>
                            <small class="text-muted">Enter amenities as a JSON array</small>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="update_content" class="btn btn-success">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="toggleEdit(<?= $room['id'] ?>)">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Private Rooms Section -->
    <h3 class="mb-3 mt-5"><i class="bi bi-house-heart"></i> Private Areas (<?= count($privateRooms) ?> areas)</h3>
    
    <?php foreach ($privateRooms as $room): ?>
        <div class="room-card">
            <div class="room-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="room-title"><?= htmlspecialchars($room['name']) ?></div>
                        <div class="d-flex gap-2">
                            <span class="badge bg-<?= $room['status'] == 'Available' ? 'success' : 'secondary' ?>">
                                <?= $room['status'] ?>
                            </span>
                            <span class="badge bg-danger">
                                <?= $room['currently_occupied'] > 0 ? 'Booked' : 'Available' ?>
                            </span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div><strong>Day:</strong> ₱<?= number_format($room['day_tour_price'], 0) ?></div>
                        <div><strong>Night:</strong> ₱<?= number_format($room['night_tour_price'], 0) ?></div>
                    </div>
                </div>
            </div>
            
            <div class="room-body">
                <!-- Current Images -->
                <div>
                    <strong>Current Images:</strong>
                    <div class="current-images">
                        <?php if ($room['main_image']): ?>
                            <img src="<?= htmlspecialchars($room['main_image']) ?>" class="image-thumb main-image-thumb" alt="Main">
                        <?php endif; ?>
                        <?php foreach (array_slice($room['gallery_images'], 0, 3) as $image): ?>
                            <img src="<?= htmlspecialchars($image['image_path']) ?>" class="image-thumb" alt="Gallery">
                        <?php endforeach; ?>
                        <?php if (empty($room['main_image']) && empty($room['gallery_images'])): ?>
                            <span class="text-muted">No images uploaded</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="btn-group-custom">
                    <button class="btn btn-primary" onclick="toggleEdit(<?= $room['id'] ?>)">
                        <i class="bi bi-pencil"></i> Edit Content
                    </button>
                    <button class="btn btn-info" onclick="openImageModal(<?= $room['id'] ?>)">
                        <i class="bi bi-images"></i> Add Images
                    </button>
                    <a href="admin_private_gallery.php" class="btn btn-purple">
                        <i class="bi bi-camera"></i> Private Gallery
                    </a>
                </div>
                
                <!-- Edit Form -->
                <div id="editForm-<?= $room['id'] ?>" class="edit-form">
                    <form method="POST">
                        <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Area Description</label>
                            <textarea name="detailed_description" class="form-control" rows="4" 
                                      placeholder="Describe what's included in this private area..."><?= htmlspecialchars($room['detailed_description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Included Accommodations (JSON format)</label>
                            <textarea name="amenities" class="form-control" rows="3" 
                                      placeholder='["2 Houses", "1 Pavilion", "3 Kubo", "Shared Kitchen"]'><?= htmlspecialchars($room['amenities'] ?? '') ?></textarea>
                            <small class="text-muted">List what's included in this private area</small>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="update_content" class="btn btn-success">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="toggleEdit(<?= $room['id'] ?>)">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Single Image Upload Modal -->
<div class="modal fade" id="imageUploadModal" tabindex="-1" aria-labelledby="imageUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageUploadModalLabel">Add Room Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="imageUploadForm">
                <div class="modal-body">
                    <input type="hidden" name="room_id" id="modalRoomId">
                    
                    <div class="mb-3">
                        <label class="form-label">Image Type</label>
                        <select name="image_type" class="form-select" required>
                            <option value="main">Main Room Image</option>
                            <option value="gallery">Additional Gallery Image</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Select Image File</label>
                        <input type="file" name="room_image" class="form-control" accept="image/*" required>
                        <small class="text-muted">Supported formats: JPG, PNG, GIF</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="upload_image" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Upload Image
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let imageModal;

document.addEventListener('DOMContentLoaded', function() {
    imageModal = new bootstrap.Modal(document.getElementById('imageUploadModal'));
});

function toggleEdit(roomId) {
    const form = document.getElementById('editForm-' + roomId);
    if (form.classList.contains('show')) {
        form.classList.remove('show');
    } else {
        // Hide all other forms first
        document.querySelectorAll('.edit-form').forEach(f => f.classList.remove('show'));
        form.classList.add('show');
    }
}

function openImageModal(roomId) {
    document.getElementById('modalRoomId').value = roomId;
    imageModal.show();
}
</script>

<style>
.btn-purple {
    background-color: #6f42c1;
    border-color: #6f42c1;
    color: white;
}
.btn-purple:hover {
    background-color: #5a2a9a;
    border-color: #5a2a9a;
    color: white;
}
</style>

</body>
</html>