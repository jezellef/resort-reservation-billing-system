<?php
session_start();
$mysqli = require __DIR__ . "/database.php";
if (!isset($_POST['confirm_action'])) {
    header("Location: admin_feedback.php");
    exit;
}


if (isset($_POST['id'])) {
    $id = $_POST['id'];
    $stmt = $mysqli->prepare("DELETE FROM feedbacks WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Feedback deleted successfully!";
    } else {
        $_SESSION['message'] = "Error deleting feedback!";
    }
    $stmt->close();
}
header("Location: adminfeedback.php");
exit;
?>
