<?php
session_start();

// Store the referring page before destroying the session
$referrer = $_SERVER['HTTP_REFERER'] ?? '';

// Determine which page to redirect to after logout
if (strpos($referrer, 'index.php') !== false) {
    $redirect_page = 'index.php';
} elseif (strpos($referrer, 'index.php') !== false) {
    $redirect_page = 'index.php';
} else {
    // Default redirect if not coming from a specific page
    $redirect_page = 'index.php';
}

// Clear all session variables
$_SESSION = [];

// If session cookie is used, clear it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to appropriate page based on where user came from
header("Location: " . $redirect_page);
exit();
?>