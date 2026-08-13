<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}
require_once 'db_connect.php';

// Fetch private accommodations
$result = $conn->query("SELECT * FROM private_accommodations ORDER BY id DESC");
$accommodations = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $accommodations[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Private Accommodations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/adminstyle.css">
</head>
<body>
<?php include 'headers/adminheader.php'; ?>

<div class="main-content">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Private Accommodations</h2>
            <div>
                <a href="admin_rooms.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to Rooms Dashboard
                </a>
                <a href="private_add.php" class="btn btn-success">+ Add Accommodation</a>
            </div>
        </div>
        
        <?php if (!empty($accommodations)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accommodations as $a): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['name']); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($a['description'])); ?></td>
                            <td>
                                <a href="private_edit_room.php?id=<?php echo $a['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <button class="btn btn-danger btn-sm" onclick="openDeleteModal(<?php echo $a['id']; ?>)">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No private accommodations found.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for Delete Confirmation -->
<div id="deleteModal" class="modal" style="display:none;">
    <div class="modal-content">
        <p>Are you sure you want to delete this accommodation?</p>
        <div class="modal-buttons">
            <button class="modal-confirm btn btn-danger" onclick="confirmDelete()">Yes, Delete</button>
            <button class="modal-cancel btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
    let deleteAccommodationId = null; // Store accommodation ID to delete

    // Open the delete confirmation modal
    function openDeleteModal(accommodationId) {
        deleteAccommodationId = accommodationId;
        document.getElementById("deleteModal").style.display = "block";
    }

    // Close the delete confirmation modal
    function closeDeleteModal() {
        document.getElementById("deleteModal").style.display = "none";
    }

    // Confirm and delete the accommodation
    function confirmDelete() {
        if (deleteAccommodationId) {
            window.location.href = "delete_private_accommodation.php?id=" + deleteAccommodationId;
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</body>
</html>
