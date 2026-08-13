<?php
$conn = new mysqli('localhost', 'u291458526_resort_user', 'r@inboWforest123!', 'u291458526_resort_db');

function saveContent($conn, $section, $value) {
    $stmt = $conn->prepare("REPLACE INTO page_content (page, section, content_type, content_value) VALUES ('index', ?, 'text', ?)");
    $stmt->bind_param("ss", $section, $value);
    $stmt->execute();
}

// Text content
$fields = ['booking_header', 'booking_subtext', 'public_title', 'public_description', 'private_title', 'private_description'];
foreach ($fields as $field) {
    if (isset($_POST[$field])) {
        saveContent($conn, $field, $_POST[$field]);
    }
}

// Image uploads
function saveImage($conn, $section, $fileField) {
    if (isset($_FILES[$fileField]) && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
        $filename = basename($_FILES[$fileField]['name']);
        $targetPath = 'images/' . $filename;
        move_uploaded_file($_FILES[$fileField]['tmp_name'], $targetPath);
        saveContent($conn, $section, $targetPath);
    }
}

saveImage($conn, 'public_image', 'public_image');
saveImage($conn, 'private_image', 'private_image');

header("Location: admin_index_booking.php");
exit;
