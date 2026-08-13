<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to get user information
function getUserStatus() {
    if (isset($_SESSION["user_id"])) {
        $mysqli = require __DIR__ . "/database.php";
        $stmt = $mysqli->prepare("SELECT first_name, last_name FROM user WHERE id = ?");
        $stmt->bind_param("i", $_SESSION["user_id"]);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    return null;
}

// Get base path similar to your header.php
function getRelativePathForUser() {
    $current_path = dirname($_SERVER['PHP_SELF']);
    $root_path = '';
    if ($current_path != '/') {
        $parts = explode('/', $current_path);
        $level = count($parts) - 1;
        if ($level > 0) {
            $root_path = str_repeat('../', $level);
        }
    }
    return $root_path;
}

$base_path = getRelativePathForUser();
$user = getUserStatus();
?>

<!-- User status display -->
<div class="user-status">
    <?php if($user): ?>
        <span class="user-name">Hello, <?= htmlspecialchars($user["first_name"]) ?></span>
        <a href="<?= $base_path ?>account.php" class="profile-link">My Profile</a>
        <a href="<?= $base_path ?>logout.php" class="logout-link">Logout</a>
    <?php else: ?>
        <a href="<?= $base_path ?>login.php" class="login-link">Login/Register</a>
    <?php endif; ?>
</div>