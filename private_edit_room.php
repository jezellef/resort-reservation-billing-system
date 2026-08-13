<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

require_once 'db_connect.php';

if (isset($_GET['id'])) {
    $accommodation_id = intval($_GET['id']);
    $sql = "SELECT * FROM private_accommodations WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $accommodation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $accommodation = $result->fetch_assoc();

    if (!$accommodation) {
        echo "<script>alert('Accommodation not found.'); window.location.href='rooms_private.php';</script>";
        exit;
    }
} else {
    echo "<script>alert('Invalid request.'); window.location.href='rooms_private.php';</script>";
    exit;
}

// Handle form submission for editing the accommodation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];

    $update_sql = "UPDATE private_accommodations SET name = ?, description = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssi", $name, $description, $accommodation_id);

    if ($update_stmt->execute()) {
        echo "<script>alert('Accommodation updated successfully.'); window.location.href='rooms_private.php';</script>";
        exit;
    } else {
        echo "<script>alert('Error updating accommodation.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Private Accommodation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/adminstyle.css">
</head>
<body>
<?php include 'headers/adminheader.php'; ?>

<div class="container mt-4">
    <h2>Edit Private Accommodation</h2>
    <form method="POST">
        <div class="mb-3">
            <label for="name" class="form-label">Accommodation Name</label>
            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($accommodation['name']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($accommodation['description']); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
