<?php
// Connect to database
$conn = new mysqli('localhost', 'u291458526_resort_user', 'r@inboWforest123!', 'u291458526_resort_db');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch current IDs
$result = $conn->query("SELECT id FROM gallery_images ORDER BY id ASC");
$image_ids = [];
while ($row = $result->fetch_assoc()) {
    $image_ids[] = $row['id'];
}

// Handle file uploads
if (isset($_FILES['gallery_images'])) {
    $uploaded_files = $_FILES['gallery_images'];

    foreach ($uploaded_files['name'] as $index => $file_name) {
        if (!empty($file_name)) {
            $target_path = 'images/' . basename($file_name);
            $tmp_path = $uploaded_files['tmp_name'][$index];

            // Ensure file is uploaded
            if (is_uploaded_file($tmp_path)) {
                if (move_uploaded_file($tmp_path, $target_path)) {
                    // Update the DB using correct ID
                    if (isset($image_ids[$index])) {
                        $stmt = $conn->prepare("UPDATE gallery_images SET image_url = ? WHERE id = ?");
                        $stmt->bind_param("si", $target_path, $image_ids[$index]);
                        $stmt->execute();
                    }
                }
            }
        }
    }
}

header('Location: edit_index_gallery.php');
exit();
?>
