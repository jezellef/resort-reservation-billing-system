<?php
session_start();

$token = $_GET["token"] ?? null;

$token_hash = hash("sha256", $token);
$mysqli = require __DIR__ . "/database.php";

$sql = "SELECT * FROM user WHERE account_activation_hash = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$_SESSION["activation_message"] = "Account activated successfully! You can now log in.";

$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user === null) {
    die("Token not found.");
}

$sql = "UPDATE user SET account_activation_hash = NULL WHERE id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user["id"]);
$stmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to Paradise Resort</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: "Segoe UI", sans-serif;
            background: linear-gradient(to bottom right, #e0f7fa, #fff);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .activation-box {
            background: white;
            padding: 40px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .logo {
            width: 100px;
            margin-bottom: 15px;
            border-radius: 50px;
        }
        .resort-name {
            font-size: 30px;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 10px;
        }
        h1 {
            color: #4CAF50;
            margin-top: 20px;
        }
        p {
            font-size: 18px;
            color: #555;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            background: #00796B;
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            transition: background 0.3s ease;
        }
        a:hover {
            background: #004D40;
        }
    </style>
</head>
<body>
    <div class="activation-box">
        <img src="images/rainbow-logo.png" alt="Resort Logo" class="logo">
        <div class="resort-name">Rainbow Forest Paradise Resort and Campsite</div>
        <h1>Account Activated</h1>
        <p>Welcome to Rainbow Forest Paradise Resort and Campsite! Your account has been successfully activated.</p>
        <a href="login.php">Login to your account</a>
    </div>
</body>
</html>
