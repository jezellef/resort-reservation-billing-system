<?php
session_start();
$mysqli = require __DIR__ . "/database.php";

if (!isset($_POST['confirm_action'])) {
    header("Location: adminfeedback.php");
    exit;
}

if (isset($_POST['id']) && isset($_POST['action'])) {
    $id = $_POST['id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $status = 'approved';
        $message = "Feedback approved successfully!";
    } else if ($action === 'reject') {
        $status = 'rejected';  
        $message = "Feedback rejected successfully!";
    } else if ($action === 'delete') {
        // Handle delete action
        $stmt = $mysqli->prepare("DELETE FROM feedbacks WHERE id = ?");
        $stmt->bind_param("i", $id);
        $_SESSION['message'] = $stmt->execute() 
            ? "Feedback deleted successfully!" 
            : "Error deleting feedback!";
        $stmt->close();
        header("Location: adminfeedback.php");
        exit;
    }
    
    if (isset($status)) {
        $stmt = $mysqli->prepare("UPDATE feedbacks SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $_SESSION['message'] = $stmt->execute() ? $message : "Error processing feedback!";
        $stmt->close();
    }
}

header("Location: adminfeedback.php");
exit;
?>