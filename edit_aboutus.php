<?php
session_start();
$mysqli = require __DIR__ . "/database.php";

// Fetch current content
$stmt = $mysqli->prepare("SELECT * FROM site_content WHERE section IN ('aboutus_p1', 'aboutus_p2', 'aboutus_p3')");
$stmt->execute();
$result = $stmt->get_result();
$contents = [];
while ($row = $result->fetch_assoc()) {
    $contents[$row['section']] = $row['content'];
}
$stmt->close();

// Modal flag
$showModal = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aboutus_p1 = $_POST['section']['aboutus_p1'] ?? '';
    $aboutus_p2 = $_POST['section']['aboutus_p2'] ?? '';
    $aboutus_p3 = $_POST['section']['aboutus_p3'] ?? '';

    $stmt = $mysqli->prepare("UPDATE site_content SET content = ? WHERE section = 'aboutus_p1'");
    $stmt->bind_param('s', $aboutus_p1);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("UPDATE site_content SET content = ? WHERE section = 'aboutus_p2'");
    $stmt->bind_param('s', $aboutus_p2);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("UPDATE site_content SET content = ? WHERE section = 'aboutus_p3'");
    $stmt->bind_param('s', $aboutus_p3);
    $stmt->execute();
    $stmt->close();

    $showModal = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit ABOUT US</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9f9f9;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 60px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        textarea {
            width: 100%;
            padding: 10px;
            resize: vertical;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 16px;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover {
            background: #218838;
        }
        h2 {
            text-align: center;
            margin-bottom: 30px;
        }

        /* Modal Styles */
        .modal {
            display: <?= $showModal ? 'block' : 'none' ?>;
            position: fixed;
            z-index: 999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #888;
            width: 400px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }
        .button-group {
          display: flex;
          justify-content: space-between;
          gap: 10px;
          margin-top: 40px;
        }
    
        .btn {
          display: inline-block;
          padding: 12px 24px;
          font-size: 14px;
          border-radius: 6px;
          text-decoration: none;
          text-align: center;
          cursor: pointer;
          transition: background-color 0.3s ease;
          color: #fff;
          border: none;
        }
        .btn-secondary {
          background-color: #6c757d;
        }
    
        .btn-secondary:hover {
          background-color: #5a6268;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Edit About Us Content</h2>

    <form method="POST">
        <label><strong>About Us</strong></label><br>
        <textarea name="section[aboutus_p1]" rows="6"><?= htmlspecialchars($contents['aboutus_p1'] ?? '') ?></textarea><br><br>

        <label><strong>Location</strong></label><br>
        <textarea name="section[aboutus_p2]" rows="6"><?= htmlspecialchars($contents['aboutus_p2'] ?? '') ?></textarea><br><br>

        <label><strong>Landmark / Note</strong></label><br>
        <textarea name="section[aboutus_p3]" rows="6"><?= htmlspecialchars($contents['aboutus_p3'] ?? '') ?></textarea><br><br>

        <button type="submit">Update Content</button>
    </form>
    
    <div class="button-group">
      <a href="content_management.php" class="btn btn-secondary">← Go Back</a>
    </div>
</div>

<!-- Modal -->
<div id="successModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('successModal').style.display='none'">&times;</span>
        <p><strong>Content updated successfully!</strong></p>
    </div>
</div>

</body>
</html>
