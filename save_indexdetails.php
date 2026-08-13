<?php
$conn = new mysqli('localhost', 'u291458526_resort_user', 'r@inboWforest123!', 'u291458526_resort_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function handleMixedUpdates($conn, $names, $images, $section) {
    foreach ($names as $id => $text) {
        $escapedText = $conn->real_escape_string($text);
        $imagePath = '';

        // Fetch existing content_value
        $stmt = $conn->prepare("SELECT content_value FROM page_content WHERE id=? AND section=? AND page='index'");
        $stmt->bind_param("is", $id, $section);
        $stmt->execute();
        $result = $stmt->get_result();
        $existingData = $result->fetch_assoc();
        $data = json_decode($existingData['content_value'], true);

        // Handle uploaded image
        if (isset($_FILES[$images]['name'][$id]) && $_FILES[$images]['name'][$id]) {
            $imageName = basename($_FILES[$images]['name'][$id]);
            $imageTmp = $_FILES[$images]['tmp_name'][$id];
            $targetDir = "images/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $imagePath = $targetDir . $imageName;
            move_uploaded_file($imageTmp, $imagePath);
        } else {
            $imagePath = $data['image'] ?? '';
        }

        $updatedData = json_encode(['image' => $imagePath, 'text' => $escapedText]);

        // Update database
        $update = $conn->prepare("UPDATE page_content SET content_value=? WHERE id=? AND section=? AND page='index'");
        $update->bind_param("sis", $updatedData, $id, $section);
        $update->execute();
        $update->close();
    }
}

// Save Activities
if (isset($_POST['activity_name'])) {
    handleMixedUpdates($conn, $_POST['activity_name'], 'activity_image', 'activities');
}

// Save Reminders
if (isset($_POST['reminder_name'])) {
    handleMixedUpdates($conn, $_POST['reminder_name'], 'reminder_image', 'reminders');
}

// Save Foods
if (isset($_POST['food_name'])) {
    handleMixedUpdates($conn, $_POST['food_name'], 'food_image', 'foods');
}

$conn->close();
header("Location: index_details.php?success=1");
exit;
