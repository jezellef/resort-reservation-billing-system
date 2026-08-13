<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}
require_once 'db_connect.php';

if (isset($_GET['id'])) {
    $roomId = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
}

header("Location: rooms_public.php?deleted=1");
exit;
