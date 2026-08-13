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
    $caption = $_POST['caption'];
    $sort_order = intval($_POST['sort_order']);
    
    $target_dir = "images/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    if (isset($_FILES["gallery_image"]) && $_FILES["gallery_image"]["error"] == UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES["gallery_image"]["name"], PATHINFO_EXTENSION));
        $allowed_extensions = array("jpg", "jpeg", "png", "gif");
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = "private_gallery_" . time() . "_" . rand(1000, 9999) . "." . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES["gallery_image"]["tmp_name"], $target_file)) {
                $stmt = $conn->prepare("INSERT INTO private_gallery (image_path, caption, sort_order) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $target_file, $caption, $sort_order);
                
                if ($stmt->execute()) {
                    $success_message = "Image uploaded successfully!";
                } else {
                    $error_message = "Error saving image to database: " . $conn->error;
                    if (file_exists($target_file)) {
                        unlink($target_file);
                    }
                }
            } else {
                $error_message = "Error uploading image. Check file permissions.";
            }
        } else {
            $error_message = "Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    } else {
        $error_message = "No file uploaded or upload error occurred.";
    }
}

// Handle image deletion
if (isset($_GET['delete'])) {
    $image_id = intval($_GET['delete']);
    
    // Get image path first
    $stmt = $conn->prepare("SELECT image_path FROM private_gallery WHERE id = ?");
    $stmt->bind_param("i", $image_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $image = $result->fetch_assoc();
    
    if ($image) {
        // Delete from database
        $delete_stmt = $conn->prepare("DELETE FROM private_gallery WHERE id = ?");
        $delete_stmt->bind_param("i", $image_id);
        
        if ($delete_stmt->execute()) {
            // Delete physical file
            if (file_exists($image['image_path'])) {
                unlink($image['image_path']);
            }
            $success_message = "Image deleted successfully!";
        } else {
            $error_message = "Error deleting image.";
        }
    }
}

// Handle caption update
if (isset($_POST['update_caption'])) {
    $image_id = intval($_POST['image_id']);
    $new_caption = $_POST['new_caption'];
    $new_sort_order = intval($_POST['new_sort_order']);
    
    $stmt = $conn->prepare("UPDATE private_gallery SET caption = ?, sort_order = ? WHERE id = ?");
    $stmt->bind_param("sii", $new_caption, $new_sort_order, $image_id);
    
    if ($stmt->execute()) {
        $success_message = "Caption updated successfully!";
    } else {
        $error_message = "Error updating caption.";
    }
}

// Handle toggle active status
if (isset($_GET['toggle'])) {
    $image_id = intval($_GET['toggle']);
    $stmt = $conn->prepare("UPDATE private_gallery SET is_active = NOT is_active WHERE id = ?");
    $stmt->bind_param("i", $image_id);
    
    if ($stmt->execute()) {
        header("Location: admin_private_gallery.php");
        exit;
    }
}

// Get all gallery images
$gallery_query = "SELECT * FROM private_gallery ORDER BY sort_order, id";
$gallery_result = $conn->query($gallery_query);
$gallery_images = $gallery_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Private Area Gallery Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles/adminstyle.css">
    <style>
        .main-content {
            padding: 20px;
        }
        
        .upload-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border: 1px solid #dee2e6;
        }
        
        .gallery-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .gallery-item.inactive {
            opacity: 0.6;
            background: #f8f9fa;
        }
        
        .gallery-image {
            width: 100%;
            max-width: 200px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #ddd;
        }
        
        .gallery-info {
            flex: 1;
            padding-left: 20px;
        }
        
        .gallery-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .badge-custom {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .edit-form {
            background: #e8f4fd;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
        }
        
        .edit-form.show {
            display: block;
        }
    </style>
</head>
<body>
    <?php include 'headers/adminheader.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Private Area Gallery Management</h2>
            <div>
                <a href="private_gallery.php" target="_blank" class="btn btn-info me-2">
                    <i class="bi bi-eye"></i> View Gallery
                </a>
                <a href="admin_rooms.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Rooms
                </a>
            </div>
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
        
        <!-- Upload Section -->
        <div class="upload-section">
            <h4><i class="bi bi-upload"></i> Add New Gallery Image</h4>
            <form method="POST" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Select Image File</label>
                        <input type="file" name="gallery_image" class="form-control" accept="image/*" required>
                        <small class="text-muted">Supported: JPG, PNG, GIF (max 10MB)</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Caption/Description</label>
                        <input type="text" name="caption" class="form-control" 
                               placeholder="e.g., Main House with Pool View" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="sort_order" class="form-control" 
                               value="<?= count($gallery_images) + 1 ?>" min="1">
                    </div>
                    <div class="col-12">
                        <button type="submit" name="upload_image" class="btn btn-success">
                            <i class="bi bi-upload"></i> Upload Image
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Current Images -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4>Current Gallery Images</h4>
            <span class="badge bg-primary"><?= count($gallery_images) ?> total images</span>
        </div>
        
        <?php if (empty($gallery_images)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No gallery images yet. Upload your first image above!
            </div>
        <?php else: ?>
            <?php foreach ($gallery_images as $image): ?>
                <div class="gallery-item <?= $image['is_active'] ? '' : 'inactive' ?>">
                    <div class="d-flex align-items-start">
                        <img src="<?= htmlspecialchars($image['image_path']) ?>" 
                             class="gallery-image" alt="<?= htmlspecialchars($image['caption']) ?>"
                             onerror="this.src='images/default_room.jpg'">
                        
                        <div class="gallery-info">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-1"><?= htmlspecialchars($image['caption'] ?: 'No caption') ?></h5>
                                <div class="d-flex gap-2">
                                    <span class="badge badge-custom bg-secondary">Order: <?= $image['sort_order'] ?></span>
                                    <span class="badge badge-custom bg-<?= $image['is_active'] ? 'success' : 'warning' ?>">
                                        <?= $image['is_active'] ? 'Active' : 'Hidden' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <p class="text-muted mb-2">
                                <small>Uploaded: <?= date('M j, Y g:i A', strtotime($image['created_at'] ?? 'now')) ?></small>
                            </p>
                            
                            <div class="gallery-actions">
                                <button class="btn btn-sm btn-primary" onclick="toggleEdit(<?= $image['id'] ?>)">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                
                                <a href="?toggle=<?= $image['id'] ?>" 
                                   class="btn btn-sm btn-<?= $image['is_active'] ? 'warning' : 'success' ?>">
                                    <i class="bi bi-<?= $image['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                    <?= $image['is_active'] ? 'Hide' : 'Show' ?>
                                </a>
                                
                                <a href="?delete=<?= $image['id'] ?>" 
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this image permanently?')">
                                    <i class="bi bi-trash"></i> Delete
                                </a>
                            </div>
                            
                            <!-- Edit Form -->
                            <div id="editForm-<?= $image['id'] ?>" class="edit-form">
                                <form method="POST">
                                    <input type="hidden" name="image_id" value="<?= $image['id'] ?>">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <label class="form-label">Caption/Description</label>
                                            <input type="text" name="new_caption" class="form-control" 
                                                   value="<?= htmlspecialchars($image['caption']) ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Display Order</label>
                                            <input type="number" name="new_sort_order" class="form-control" 
                                                   value="<?= $image['sort_order'] ?>" min="1">
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" name="update_caption" class="btn btn-success btn-sm">
                                                <i class="bi bi-save"></i> Save Changes
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEdit(<?= $image['id'] ?>)">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleEdit(imageId) {
            const form = document.getElementById('editForm-' + imageId);
            if (form.classList.contains('show')) {
                form.classList.remove('show');
            } else {
                // Hide all other forms first
                document.querySelectorAll('.edit-form').forEach(f => f.classList.remove('show'));
                form.classList.add('show');
            }
        }
    </script>
</body>
</html>