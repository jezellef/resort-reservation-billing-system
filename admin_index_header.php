<?php
$conn = new mysqli('localhost', 'u291458526_resort_user', 'r@inboWforest123!', 'u291458526_resort_db');
$results = $conn->query("SELECT * FROM page_content WHERE page='index'");

$content = [];
while ($row = $results->fetch_assoc()) {
    $content[$row['section']] = $row['content_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Header Content</title>
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

    .form-section h2 {
      color: #14532d;
      margin-bottom: 30px;
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
  </style>
</head>
<body>

<div class="form-section">
  <h2>Edit Homepage Header</h2>
  <form action="save_index_header.php" method="post" enctype="multipart/form-data">
    <div class="mb-3">
      <label class="form-label">Hero Title</label>
      <input type="text" name="hero_header" class="form-control" value="<?= htmlspecialchars($content['hero_header']) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Hero Subtitle</label>
      <input type="text" name="hero_subtitle" class="form-control" value="<?= htmlspecialchars($content['hero_subtitle']) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Hero Paragraph</label>
      <textarea name="hero_paragraph" class="form-control" rows="4"><?= htmlspecialchars($content['hero_paragraph']) ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Image 1</label>
      <input type="file" name="menu_image_1" class="form-control">
    </div>
    <div class="mb-3">
      <label class="form-label">Image 2</label>
      <input type="file" name="menu_image_2" class="form-control">
    </div>
    <div class="mb-3">
      <label class="form-label">Image 3</label>
      <input type="file" name="menu_image_3" class="form-control">
    </div>

    <div class="d-flex justify-content-between">
      <a href="content_management.php" class="btn btn-secondary">← Go Back</a>
      <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
  </form>
</div>

</body>
</html>
