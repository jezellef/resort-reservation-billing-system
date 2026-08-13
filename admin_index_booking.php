<?php
// Connect and fetch current content
$conn = new mysqli('localhost', 'u291458526_resort_user', 'r@inboWforest123!', 'u291458526_resort_db');
$result = $conn->query("SELECT section, content_value FROM page_content WHERE page='index'");
$content = [];
while ($row = $result->fetch_assoc()) {
    $content[$row['section']] = $row['content_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Booking Section</title>
  <link rel="icon" type="image/png" href="images/rlogo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8fafc;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .form-section {
      max-width: 800px;
      margin: 50px auto;
      padding: 40px;
      background: white;
      border-radius: 16px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
    }

    .form-section h3,
    .form-section h4 {
      color: #14532d;
      margin-bottom: 20px;
    }

    .form-label {
      font-weight: 600;
      color: #14532d;
    }

    .btn-primary {
      background-color: #14532d;
      border-color: #14532d;
    }

    .btn-primary:hover {
      background-color: #1b6a3b;
      border-color: #1b6a3b;
      color: #ffe600;
    }

    .btn-secondary {
      background-color: #6c757d;
      border-color: #6c757d;
    }

    .btn-secondary:hover {
      background-color: #5a6268;
      border-color: #545b62;
    }

    small {
      color: #555;
      font-size: 0.875rem;
    }
  </style>
</head>
<body>

<div class="form-section">
  <h3>Booking Section</h3>
  <form action="save_index_booking.php" method="post" enctype="multipart/form-data">
    <div class="mb-3">
      <label class="form-label">Section Title</label>
      <input type="text" name="booking_header" class="form-control" value="<?= htmlspecialchars($content['booking_header'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Subtext</label>
      <textarea name="booking_subtext" class="form-control" rows="3"><?= htmlspecialchars($content['booking_subtext'] ?? '') ?></textarea>
    </div>

    <h4>Public Area</h4>
    <div class="mb-3">
      <label class="form-label">Title</label>
      <input type="text" name="public_title" class="form-control" value="<?= htmlspecialchars($content['public_title'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Description</label>
      <textarea name="public_description" class="form-control" rows="3"><?= htmlspecialchars($content['public_description'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Image (Public Area)</label>
      <input type="file" name="public_image" class="form-control">
      <small>Current: <?= htmlspecialchars($content['public_image'] ?? '') ?></small>
    </div>

    <h4>Private Area</h4>
    <div class="mb-3">
      <label class="form-label">Title</label>
      <input type="text" name="private_title" class="form-control" value="<?= htmlspecialchars($content['private_title'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Description</label>
      <textarea name="private_description" class="form-control" rows="3"><?= htmlspecialchars($content['private_description'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Image (Private Area)</label>
      <input type="file" name="private_image" class="form-control">
      <small>Current: <?= htmlspecialchars($content['private_image'] ?? '') ?></small>
    </div>

    <div class="d-flex justify-content-between">
      <a href="content_management.php" class="btn btn-secondary">← Go Back</a>
      <button type="submit" name="save" class="btn btn-primary">Save Booking Section</button>
    </div>
  </form>
</div>

</body>
</html>
