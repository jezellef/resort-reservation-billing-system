<?php
$conn = new mysqli('localhost', 'u291458526_resort_user', 'r@inboWforest123!', 'u291458526_resort_db');

function update_content($section, $type, $value) {
    global $conn;
    $stmt = $conn->prepare("UPDATE page_content SET content_value=? WHERE page='index' AND section=?");
    $stmt->bind_param("ss", $value, $section);
    $stmt->execute();
}

// Update text content
update_content('hero_header', 'text', $_POST['hero_header']);
update_content('hero_subtitle', 'text', $_POST['hero_subtitle']);
update_content('hero_paragraph', 'text', $_POST['hero_paragraph']);

// Handle images
foreach (['menu_image_1', 'menu_image_2', 'menu_image_3'] as $key) {
    if ($_FILES[$key]['error'] === 0) {
        $filename = 'uploads/' . basename($_FILES[$key]['name']);
        move_uploaded_file($_FILES[$key]['tmp_name'], $filename);
        update_content($key, 'image', $filename);
    }
}

header("Location: admin_index_header.php");
