<?php
include 'db_connect.php'; // Database connection
session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] != 'Super Admin') {
    header("Location: access_denied.php");
    exit();
}
// Fetch all users from the database
$query = "SELECT * FROM admin_users";
$result = $conn->query($query); // Execute the query

if (!$result) {
    die("Database query failed: " . $conn->error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = $_POST['password'];

    // Backend Password Validation (PHP)
    if (strlen($password) < 8 || 
        !preg_match('/[A-Z]/', $password) || 
        !preg_match('/[0-9]/', $password) || 
        !preg_match('/[\W]/', $password)) {
        $_SESSION['error_message'] = "Password must be at least 8 characters long and include an uppercase letter, a number, and a special character.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO admin_users (name, email, role, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $role, $hashed_password);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "User added successfully!";
        } else {
            $_SESSION['error_message'] = "Error adding user!";
        }
        $stmt->close();
    }

    header("Location: admin_user.php"); // Reload the same page to show messages
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - User Management</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/adminstyle.css">
    <style>
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }
        .btn-edit {
            background-color: #007bff;
            color: white;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .btn:hover {
            opacity: 0.8;
        }
             .dashboard-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #2c3e50;
            position: relative;
            padding-bottom: 10px;
        }
        .dashboard-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            height: 4px;
            width: 330px;
            background: linear-gradient(to right, #3498db, #1abc9c);
        }
            .btn {
      padding: 10px 16px;
      background: #14532d;
      color: white;
      border-radius: 6px;
      text-decoration: none;
      font-size: 14px;
      transition: 0.3s;
    }

    .btn:hover {
      background: #1b6a3b;
      color: #FFEA00;
    }
    </style>
</head>
<body>
<?php include 'headers/adminheader.php'; ?>

<div class="main-content px-5 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="dashboard-title">Admin Dashboard</h2>
        <a href="admin_settings.php" class="btn btn-outline-primary">Go Back</a>
    </div>
    
    <div class="mb-3">
        <a href="admin_add_user.php" class="btn btn-success">+ Add Admin</a>
    </div>
    
    <?php
        if (isset($_SESSION['success_message'])) {
            echo "<div class='alert alert-success'>" . $_SESSION['success_message'] . "</div>";
            unset($_SESSION['success_message']);
        }

        if (isset($_SESSION['error_message'])) {
            echo "<div class='alert alert-danger'>" . $_SESSION['error_message'] . "</div>";
            unset($_SESSION['error_message']);
        }
    ?>

    <table class="table table-striped">
   
        <thead style="background-color: #caf0f8; color: #333;">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
        </thead>

        <tbody id="userTableBody">
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?php echo $row['name']; ?></td>
            <td><?php echo $row['email']; ?></td>
            <td><?php echo $row['role']; ?></td>
            <td>
                <a href="admin_edit_user.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                <button class="btn btn-danger btn-sm" onclick="openDeleteModal(<?php echo $row['id']; ?>)">Delete</button>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <p>Are you sure you want to delete this user?</p>
            <div class="modal-buttons">
                <button class="modal-confirm btn btn-danger" onclick="confirmDelete()">Yes, Delete</button>
                <button class="modal-cancel btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
    let deleteUserId = null; // Store user ID to delete

    function openDeleteModal(userId) {
        deleteUserId = userId;
        document.getElementById("deleteModal").style.display = "block";
    }

    function closeDeleteModal() {
        document.getElementById("deleteModal").style.display = "none";
    }

    function confirmDelete() {
        if (deleteUserId) {
            window.location.href = "admin_delete_user.php?id=" + deleteUserId;
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        let messageBox = document.querySelector(".message-box");
        if (messageBox) {
            messageBox.style.display = "block";
            setTimeout(() => {
                messageBox.style.display = "none";
            }, 3000); // Hide after 3 seconds
        }
    });
</script>


<script>
    let deleteUserId = null; // Store user ID to delete

    function openDeleteModal(userId) {
        deleteUserId = userId;
        document.getElementById("deleteModal").style.display = "block";
    }

    function closeDeleteModal() {
        document.getElementById("deleteModal").style.display = "none";
    }

    function confirmDelete() {
        if (deleteUserId) {
            window.location.href = "admin_delete_user.php?id=" + deleteUserId;
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        let messageBox = document.querySelector(".message-box");
        if (messageBox) {
            messageBox.style.display = "block";
            setTimeout(() => {
                messageBox.style.display = "none";
            }, 3000); // Hide after 3 seconds
        }
    });
</script>
</body>
</html>
