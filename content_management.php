<!DOCTYPE html>
<html lang="en">
<head>
  <title>Content Management</title>
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

    @media (max-width: 600px) {
      .main-content {
        padding: 20px;
      }
    }
  </style>
</head>
<body>
<?php include 'headers/adminheader.php'; ?>
  <div class="main-content px-5 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
         <h2 class="dashboard-title">Content Management</h2>
         <a href="admin_settings.php" class="btn btn-outline-primary">Go Back</a>
    </div>
        
    <div class="settings-container">
      <div class="setting-card">
        <img src="icons/header.png" alt="Header Icon" />
        <h3>Homepage Header</h3>
        <p>Edit hero title, subtext, and background image of homepage.</p>
        <a href="admin_index_header.php" class="btn">Edit Header</a>
      </div>

      <div class="setting-card">
        <img src="icons/booking.png" alt="Booking Icon" />
        <h3>Homepage Booking Section</h3>
        <p>Edit content for 'Choose Your Stay' including images and descriptions.</p>
        <a href="admin_index_booking.php" class="btn">Edit About Booking</a>
      </div>
      
        <div class="setting-card">
        <img src="icons/about.png" alt="About Icon" />
        <h3>Homepage About Us Section</h3>
        <p>Update the resort's about us in the homepage section.</p>
        <a href="edit_index_abouthome.php" class="btn">Edit About Home</a>
      </div>


      <div class="setting-card">
        <img src="icons/gallery.png" alt="Gallery Icon" />
        <h3>Homepage Gallery Section</h3>
        <p>Manage homepage/resort gallery images and captions.</p>
        <a href="edit_index_gallery.php" class="btn">Edit Gallery</a>
      </div>

      <div class="setting-card">
        <img src="icons/about.png" alt="About Icon" />
        <h3>Homepage Details</h3>
        <p>Update the resorts details such as Activities, Reminders, and Foods.</p>
        <a href="index_details.php" class="btn">Edit Details</a>
      </div>

      <div class="setting-card">
        <img src="icons/contact.png" alt="Contact Icon" />
        <h3>Contact Section</h3>
        <p>Manage resort contact info, map location, and inquiry form.</p>
        <a href="edit_contact.php" class="btn">Edit About Us</a>
      </div>

      <div class="setting-card">
        <img src="icons/contact.png" alt="Contact Icon" />
        <h3>About Us Page</h3>
        <p>Manage resort contact info, map location, and inquiry form.</p>
        <a href="edit_aboutus.php" class="btn">Edit About Us</a>
      </div>
    </div>
  </div>
</body>
</html>
