<?php
session_start();
include 'db_connect.php';


if (!isset($_GET['id'])) {
    header("Location: admin_user.php");
    exit();
}

$id = $_GET['id'];
$error = "";
$success = "";

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Update user info
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = $_POST['password'];

    if (!empty($password)) {
        // Validate password
        if (strlen($password) < 8 || 
            !preg_match('/[A-Z]/', $password) || 
            !preg_match('/[0-9]/', $password) || 
            !preg_match('/[\W]/', $password)) {
            $error = "Password must be at least 8 characters long and include an uppercase letter, a number, and a special character.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE admin_users SET name = ?, email = ?, role = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $email, $role, $hashed_password, $id);
        }
    } else {
        $stmt = $conn->prepare("UPDATE admin_users SET name = ?, email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $email, $role, $id);
    }

    // Execute update statement and close properly
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "User updated successfully!";
        $stmt->close();
        header("Location: admin_user.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error updating user!";
        $stmt->close();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .edit-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            width: 380px;
            text-align: center;
        }
        h2 {
            margin-bottom: 20px;
            color: #333;
        }
        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
            text-align: left;
        }
        input, select {
            width: 95%;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .message-box {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            text-align: center;
        }
        .error {
            background-color: #ffdddd;
            color: red;
            border: 1px solid red;
        }
        .success {
            background-color: #ddffdd;
            color: green;
            border: 1px solid green;
        }
        .btn-container {
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            width: 48%;
        }
        button:hover {
            background-color: #45a049;
        }
        .cancel-btn {
            background-color: #f44336;
            text-decoration: none;
            text-align: center;
            color: white;
            padding: 10px;
            border-radius: 12px;
            width: 45%;
            display: inline-block;
        }
        .cancel-btn:hover {
            background-color: #d32f2f;
        }
    </style>
</head>
<body>

<div class="edit-container">
    <h2>Edit User</h2>

    <!-- Display messages -->
    <?php if (!empty($error)): ?>
        <div class="message-box error"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="message-box success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="message-box error"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Name:</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>

        <label>Email:</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

        <label>Role:</label>
        <select name="role">
            <option value="Super Admin" <?php if ($user['role'] == 'Super Admin') echo 'selected'; ?>>Super Admin</option>
            <option value="Admin" <?php if ($user['role'] == 'Admin') echo 'selected'; ?>>Admin</option>
        </select>

        <label>New Password (optional):</label>
        <input type="password" name="password">

        <div class="btn-container">
            <button type="submit" class="button">Update</button>
            <a href="admin_user.php" class="cancel-btn">Cancel</a>
        </div>
    </form>
</div>

</body>
</html>
