<?php
session_start();
$mysqli = require __DIR__ . "/database.php";

// Update activity content
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_activity"])) {
    $id = $_POST["id"];
    $title = $_POST["title"];
    $description = $_POST["description"];
    $imageSection = "activity_{$id}_image";

    // Debugging: Check if the form data is correctly passed
    echo "<pre>";
    print_r($_POST);  // Show POST data
    print_r($_FILES);  // Show FILES data
    echo "</pre>";

    // Image upload
    if ($_FILES["image"]["error"] === UPLOAD_ERR_OK) {
        $imageName = basename($_FILES["image"]["name"]);
        $targetPath = "images/" . $imageName;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetPath)) {
            // Update the image in the database
            $stmt = $mysqli->prepare("UPDATE site_content SET content = ? WHERE section = ?");
            $stmt->bind_param("ss", $targetPath, $imageSection);
            if ($stmt->execute() === false) {
                echo "Error updating image: " . $stmt->error;  // Show SQL error
            }
            $stmt->close();
        } else {
            echo "Failed to upload the image.";  // Show image upload failure
        }
    }

    // Update title
    $stmt1 = $mysqli->prepare("UPDATE site_content SET content = ? WHERE section = ?");
    $titleSection = "activity_{$id}_title";
    $stmt1->bind_param("ss", $title, $titleSection);
    if ($stmt1->execute() === false) {
        echo "Error updating title: " . $stmt1->error;  // Show SQL error
    }
    $stmt1->close();

    // Update description
    $stmt2 = $mysqli->prepare("UPDATE site_content SET content = ? WHERE section = ?");
    $descSection = "activity_{$id}_desc";
    $stmt2->bind_param("ss", $description, $descSection);
    if ($stmt2->execute() === false) {
        echo "Error updating description: " . $stmt2->error;  // Show SQL error
    }
    $stmt2->close();

    // Redirect to success page
    header("Location: manage_activities.php?success=1");
    exit;
}

// Fetch all activity sections from site_content
$result = $mysqli->query("SELECT section, content FROM site_content WHERE section LIKE 'activity_%'");
$rawSections = [];
while ($row = $result->fetch_assoc()) {
    $rawSections[$row['section']] = $row['content'];
}

// Organize into activities
$activities = [];
foreach ($rawSections as $section => $content) {
    if (preg_match('/activity_(\d+)_(title|desc|image)/', $section, $matches)) {
        $id = $matches[1];
        $type = $matches[2];
        $activities[$id][$type] = $content;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Activities</title>
    
    <style>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 20px;
        }

        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px 20px;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .activity-form {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            background: #fafafa;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }

        .activity-form label {
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
            color: #555;
        }

        .activity-form input[type="text"],
        .activity-form textarea,
        .activity-form input[type="file"] {
            width: 100%;
            padding: 10px 15px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 15px;
            background: #fff;
        }

        .activity-form img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .activity-form button {
            background: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }

        .activity-form button:hover {
            background: #45a049;
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: 20px;
            }
        }
  
    </style>
</head>
<body>
<div class="admin-container">
    <h1>Edit Activities</h1>
    <?php if (isset($_GET["success"])): ?>
        <p class="success-message">Activity updated successfully!</p>
    <?php endif; ?>

    <?php foreach ($activities as $id => $activity): ?>
        <form class="activity-form" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $id ?>">

            <label>Current Image:</label><br>
            <img src="<?= htmlspecialchars($activity['image']) ?>" alt="Activity Image"><br>
            <label>New Image:</label>
            <input type="file" name="image"><br>

            <label>Title:</label>
            <input type="text" name="title" value="<?= htmlspecialchars($activity['title']) ?>" required>

            <label>Description:</label>
            <textarea name="description" rows="4" required><?= htmlspecialchars($activity['desc']) ?></textarea>

            <button type="submit" name="update_activity">Update Activity</button>
        </form>
    <?php endforeach; ?>
</div>
</body>
</html>
