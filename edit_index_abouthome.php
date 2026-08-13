<?php
// Fetch current content from the database
$conn = new mysqli('localhost', 'u291458526_resort_user', 'r@inboWforest123!', 'u291458526_resort_db');
$result = $conn->query("SELECT section, content_value FROM page_content WHERE page='index'");

$content = [];
while ($row = $result->fetch_assoc()) {
    $content[$row['section']] = $row['content_value'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit About Home Section</title>
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

    .mb-3 {
      margin-bottom: 15px;
    }
  </style>
</head>
<body>

<div class="form-section">
  <h3>Edit About Home Section</h3>
  <form action="save_index_abouthome.php" method="post" enctype="multipart/form-data">
    <div class="mb-3">
      <label class="form-label">Section Title</label>
      <input type="text" name="abouthome_title" class="form-control" value="<?= htmlspecialchars($content['abouthome_title'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Heading</label>
      <input type="text" name="abouthome_heading" class="form-control" value="<?= htmlspecialchars($content['abouthome_heading'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">First Paragraph</label>
      <textarea name="abouthome_paragraph_1" class="form-control" rows="3"><?= htmlspecialchars($content['abouthome_paragraph_1'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Second Paragraph</label>
      <textarea name="abouthome_paragraph_2" class="form-control" rows="3"><?= htmlspecialchars($content['abouthome_paragraph_2'] ?? '') ?></textarea>
    </div>

    <h4>Images</h4>
    <div class="mb-3">
      <label class="form-label">Image 1</label>
      <input type="file" name="abouthome_image_1" class="form-control">
      <small>Current: <?= htmlspecialchars($content['abouthome_image_1'] ?? 'No image uploaded') ?></small>
    </div>

    <div class="mb-3">
      <label class="form-label">Image 2</label>
      <input type="file" name="abouthome_image_2" class="form-control">
      <small>Current: <?= htmlspecialchars($content['abouthome_image_2'] ?? 'No image uploaded') ?></small>
    </div>

    <div class="d-flex justify-content-between">
      <a href="content_management.php" class="btn btn-secondary">← Go Back</a>
      <button type="submit" name="save" class="btn btn-primary">Save About Home Section</button>
    </div>
  </form>
</div>

</body>
</html>
