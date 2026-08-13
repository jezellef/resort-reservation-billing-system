<?php
$conn = new mysqli('localhost', 'u291458526_resort_user', 'r@inboWforest123!', 'u291458526_resort_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch content by section
function fetchContent($conn, $section) {
    $stmt = $conn->prepare("SELECT id, content_value FROM page_content WHERE section = ? AND page = 'index'");
    $stmt->bind_param("s", $section);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $data = json_decode($row['content_value'], true);
        $items[] = [
            'id' => $row['id'],
            'image' => $data['image'] ?? '',
            'text' => $data['text'] ?? ''
        ];
    }
    return $items;
}

$activities = fetchContent($conn, 'activities');
$reminders = fetchContent($conn, 'reminders');
$food = fetchContent($conn, 'food');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Activities, Reminders, Foods</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f8f5;
            color: #2d4037;
            padding: 30px;
        }

        .success {
            color: green;
            font-weight: bold;
        }

        .flex-container {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .form-wrapper {
            flex: 1;
            background-color: #ecffdc;
            border: 1px solid #89baa9;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            min-width: 0;
        }

        h2 {
            background-color: #89baa9;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-top: 0;
        }

        .form-section {
            margin-bottom: 20px;
        }

        input[type="text"], input[type="file"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        img {
            display: block;
            margin-top: 5px;
            border: 2px solid #ccc;
            border-radius: 4px;
            width: 100%;
            max-height: 120px;
            object-fit: cover;
        }

        button {
            background-color: #4c7358;
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }

        button:hover {
            background-color: #385c46;
        }
    </style>
</head>
<body>

<?php if (isset($_GET['success'])): ?>
    <p class="success">Changes saved successfully!</p>
<?php endif; ?>

<div class="flex-container">
    <!-- Activities -->
    <div class="form-wrapper">
        <h2>Activities</h2>
        <form action="save_indexdetails.php" method="post" enctype="multipart/form-data">
            <?php foreach ($activities as $item): ?>
                <div class="form-section">
                    <input type="text" name="activity_name[<?= $item['id'] ?>]" value="<?= htmlspecialchars($item['text']) ?>" placeholder="Activity name">
                    <input type="file" name="activity_image[<?= $item['id'] ?>]">
                    <?php if ($item['image']): ?>
                        <img src="<?= $item['image'] ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit">Save Activities</button>
        </form>
    </div>

    <!-- Reminders -->
    <div class="form-wrapper">
        <h2>Reminders</h2>
        <form action="save_indexdetails.php" method="post" enctype="multipart/form-data">
            <?php foreach ($reminders as $item): ?>
                <div class="form-section">
                    <input type="text" name="reminder_name[<?= $item['id'] ?>]" value="<?= htmlspecialchars($item['text']) ?>" placeholder="Reminder">
                    <input type="file" name="reminder_image[<?= $item['id'] ?>]">
                    <?php if ($item['image']): ?>
                        <img src="<?= $item['image'] ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit">Save Reminders</button>
        </form>
    </div>

    <!-- Foods -->
    <div class="form-wrapper">
        <h2>Foods</h2>
        <form action="save_indexdetails.php" method="post" enctype="multipart/form-data">
            <?php foreach ($food as $item): ?>
                <div class="form-section">
                    <input type="text" name="food_name[<?= $item['id'] ?>]" value="<?= htmlspecialchars($item['text']) ?>" placeholder="Food name">
                    <input type="file" name="food_image[<?= $item['id'] ?>]">
                    <?php if ($item['image']): ?>
                        <img src="<?= $item['image'] ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit">Save Foods</button>
        </form>
    </div>
</div>

</body>
</html>
