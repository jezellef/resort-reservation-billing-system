<!DOCTYPE html>
<html lang="en">
<head>
  <title>Admin Settings</title>
  <link rel="icon" type="image/png" href="images/rlogo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles/adminstyle.css">
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f4f6f8;
      color: #333;
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
            width: 400px;
            background: linear-gradient(to right, #3498db, #1abc9c);
    }

    .settings-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 24px;
    }

    .setting-card {
      background: #ffffff;
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      transition: 0.3s ease;
    }

    .setting-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 28px rgba(0, 0, 0, 0.1);
    }

    .setting-card img {
      width: 56px;
      height: 56px;
      margin-bottom: 16px;
    }

    .setting-card h3 {
      font-size: 20px;
      margin: 10px 0;
      color: #14532d;
    }

    .setting-card p {
      font-size: 14px;
      color: #666;
      margin-bottom: 18px;
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

    @media (max-width: 600px) {
      .main-content {
        padding: 20px;
      }
    }
  </style>
</head>
<body>
<?php include 'headers/adminheader.php'; ?>
<div class="main-content">
<div class="d-flex justify-content-between align-items-center mb-4">
     <h2 class="dashboard-title">Admin Settings Dashboard</h2>
</div>
    <div class="settings-container">
      <div class="setting-card">
        <img src="icons/users.png" alt="Users Icon" />
        <h3>Admin User Management</h3>
        <p>Manage user accounts, roles, and permissions.</p>
        <a href="admin_user.php" class="btn">Manage Users</a>
      </div>
      <div class="setting-card">
        <img src="icons/backup.png" alt="Backup Icon" />
        <h3>Data Backup</h3>
        <p>Backup system data securely.</p>
        <a href="admin_backup.php" class="btn">Manage Backups</a>
      </div>
      <div class="setting-card">
        <img src="icons/rooms.png" alt="Rooms Icon" />
        <h3>Room Management</h3>
        <p>Update room descriptions, images, and pricing.</p>
        <a href="admin_rooms.php" class="btn">Manage Rooms</a>
      </div>
     
      <div class="setting-card">
        <img src="icons/edit.png" alt="Content Icon" />
        <h3>Content Management</h3>
        <p>Edit landing pages, gallery, and resort info.</p>
        <a href="content_management.php" class="btn">Manage Content</a>
      </div>
    </div>
  </div>
</body>
</html>
