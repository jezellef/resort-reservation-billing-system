<?php
// Connect to database and fetch gallery images
$conn = new mysqli('localhost', 'u291458526_resort_user', 'r@inboWforest123!', 'u291458526_resort_db');
$result = $conn->query("SELECT * FROM gallery_images");
$gallery_images = [];
while ($row = $result->fetch_assoc()) {
    $gallery_images[] = $row['image_url'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Gallery Section</title>
  <link rel="icon" type="image/png" href="images/rlogo.png">
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f0f4f3;
      margin: 0;
      padding: 0;
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

    .gallery-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }

    .gallery-image {
      display: flex;
      flex-direction: column;
      align-items: center;
      background-color: #f8f9fa;
      padding: 12px;
      border-radius: 8px;
      box-shadow: 0 0 6px rgba(0, 0, 0, 0.05);
    }

    .gallery-image img {
      max-width: 100%;
      height: auto;
      max-height: 120px;
      object-fit: cover;
      margin-bottom: 10px;
      border-radius: 6px;
    }

    .gallery-image input[type="file"] {
      font-size: 12px;
      width: 100%;
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

    .btn-primary {
      background-color: #14532d;
    }

    .btn-primary:hover {
      background-color: #1b6a3b;
      color: #ffe600;
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

<div class="form-section">
  <h3>Edit Gallery Section</h3>
  <form action="save_index_gallery.php" method="post" enctype="multipart/form-data">
    <h4>Current Gallery Images</h4>
    <div class="gallery-grid">
      <?php foreach ($gallery_images as $image): ?>
        <div class="gallery-image">
          <img src="<?= htmlspecialchars($image) ?>" alt="Gallery Image">
          <input type="file" name="gallery_images[]" class="form-control" value="<?= htmlspecialchars($image) ?>">
        </div>
      <?php endforeach; ?>
    </div>

    <div class="button-group">
      <a href="content_management.php" class="btn btn-secondary">← Go Back</a>
      <button type="submit" name="save" class="btn btn-primary">Save Gallery Images</button>
    </div>
  </form>
</div>

</body>
</html>
