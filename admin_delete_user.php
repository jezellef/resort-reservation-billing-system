<?php
include 'db_connect.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Delete user from database
    $stmt = $conn->prepare("DELETE FROM admin_users WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: admin_user.php?message=User deleted successfully&type=success");
        exit();
    } else {
        header("Location: admin_user.php?message=Error deleting user&type=error");
        exit();
    }
}
?>
