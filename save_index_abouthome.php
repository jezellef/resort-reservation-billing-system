<?php
$conn = new mysqli('localhost', 'u291458526_resort_user', 'r@inboWforest123!', 'u291458526_resort_db');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to update or insert content
function updateContent($conn, $section, $value) {
    $stmt = $conn->prepare("SELECT * FROM page_content WHERE page='index' AND section=?");
    $stmt->bind_param("s", $section);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE page_content SET content_value=? WHERE page='index' AND section=?");
        $stmt->bind_param("ss", $value, $section);
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO page_content (page, section, content_value) VALUES ('index', ?, ?)");
        $stmt->bind_param("ss", $section, $value);
    }
    $stmt->execute();
}

// Text content
updateContent($conn, 'abouthome_title', $_POST['abouthome_title']);
updateContent($conn, 'abouthome_heading', $_POST['abouthome_heading']);
updateContent($conn, 'abouthome_paragraph_1', $_POST['abouthome_paragraph_1']);
updateContent($conn, 'abouthome_paragraph_2', $_POST['abouthome_paragraph_2']);

// Image upload helper
function handleImageUpload($inputName, $dbSection, $conn) {
    if (!empty($_FILES[$inputName]['name'])) {
        $uploadDir = 'images/';
        $fileName = basename($_FILES[$inputName]['name']);
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES[$inputName]['tmp_name'], $targetFile)) {
            updateContent($conn, $dbSection, $targetFile);
        }
    }
}

// Handle images
handleImageUpload('abouthome_image_1', 'abouthome_image_1', $conn);
handleImageUpload('abouthome_image_2', 'abouthome_image_2', $conn);

$conn->close();
header("Location: edit_index_abouthome.php?status=success");
exit;
