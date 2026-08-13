<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $daytour_price = $_POST['daytour_price'];
    $overnight_price = $_POST['overnight_price'];
    $image = $_FILES['image']['name'];

    if (move_uploaded_file($_FILES['image']['tmp_name'], "images/" . $image)) {
        $stmt = $conn->prepare("INSERT INTO rooms (name, description, day_tour_price, night_tour_price, image) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdds", $name, $description, $daytour_price, $overnight_price, $image);

        if ($stmt->execute()) {
            header("Location: rooms_public.php?added=true");
            exit;
        } else {
            echo "Error adding room: " . $stmt->error;
        }
    } else {
        echo "Failed to upload image.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Room</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .form-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            width: 400px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-top: 10px;
            font-weight: 500;
        }

        input[type="text"],
        input[type="number"],
        input[type="file"],
        textarea {
            width: 95%;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-family: inherit;
        }

        textarea {
            resize: vertical;
        }

        button {
            margin-top: 15px;
            width: 100%;
            padding: 10px;
            border: none;
            background-color: #4CAF50;
            color: white;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
        }

        button:hover {
            background-color: #45a049;
        }

    </style>
</head>
<body>

<div class="form-container">
    <h2>Add New Room</h2>
    <form action="admin_add_room.php" method="POST" enctype="multipart/form-data">
        <label for="name">Room Name:</label>
        <input type="text" id="name" name="name" required>

        <label for="description">Description:</label>
        <textarea id="description" name="description" required></textarea>

        <label for="daytour_price">Daytour Price:</label>
        <input type="number" id="daytour_price" name="daytour_price" required>

        <label for="overnight_price">Overnight Price:</label>
        <input type="number" id="overnight_price" name="overnight_price" required>

        <label for="image">Room Image:</label>
        <input type="file" id="image" name="image" accept="image/*" required>

        <button type="submit">Add Room</button>
    </form><div style="margin-top: 15px;">
    <button onclick="history.back()" style="background-color: #555;">Go Back</button>
</div>

</div>

</body>
</html>
