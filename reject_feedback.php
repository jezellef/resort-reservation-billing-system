<?php
session_start();
$mysqli = require __DIR__ . "/database.php";
if (!isset($_POST['confirm_action'])) {
    header("Location: admin_feedback.php");
    exit;
}

if (isset($_POST['id'])) {
    $id = $_POST['id'];
    $stmt = $mysqli->prepare("UPDATE feedbacks SET status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $id);

    $_SESSION['message'] = $stmt->execute() 
        ? "Feedback rejected successfully!" 
        : "Error rejecting feedback!";

    $stmt->close();
}
header("Location: adminfeedback.php");
exit;
?>
