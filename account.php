<?php
session_start(); 
function getUserStatus() {
    if (isset($_SESSION["user_id"])) {
        $mysqli = require __DIR__ . "/database.php";
        $stmt = $mysqli->prepare("SELECT first_name, last_name FROM user WHERE id = ?");
        $stmt->bind_param("i", $_SESSION["user_id"]);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }
    return null;
}
$current_user = getUserStatus();
if (!$current_user) {
    session_destroy();
    header("Location: login.php"); 
    exit;
}
$mysqli = require __DIR__ . "/database.php";
if (!$mysqli) {
    die("Database connection failed: " . mysqli_connect_error());
}
$stmt = $mysqli->prepare("SELECT * FROM user WHERE id = ?");
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/mystyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <title>User Account</title>
    <style>
        body {
           background: linear-gradient(135deg, #3f704d 0%, #043927 100%);
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
        }

        .user-dashboard {
            max-width: 900px;
            margin: 60px auto;
            padding: 0;
            background: transparent;
            border-radius: 0;
            box-shadow: none;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .user-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .user-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }

        .profile-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 28px;
            color: white;
            font-weight: bold;
        }
        
        .user-title {
            font-size: 2rem;
            margin: 0;
            color: #333;
            font-weight: 700;
        }

        .user-subtitle {
            color: #666;
            margin: 5px 0 0 0;
            font-size: 0.9rem;
        }

        .info-grid {
            display: grid;
            gap: 15px;
        }

        .info-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 12px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .info-item:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(5px);
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.85rem;
            color: #666;
            font-weight: 500;
            margin-bottom: 3px;
        }

        .info-value {
            color: #333;
            font-weight: 600;
            font-size: 1rem;
        }

        .actions-card {
            grid-column: 1 / -1;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
        }

        .actions-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 25px;
            font-weight: 600;
        }

        .user-options {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-action {
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            min-width: 180px;
            justify-content: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        .btn-action::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .btn-action:hover::before {
            left: 100%;
        }
        
        .edit-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .edit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(102, 126, 234, 0.4);
        }
        
        .booking-btn {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: #fff;
            box-shadow: 0 8px 20px rgba(17, 153, 142, 0.3);
        }
        
        .booking-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(17, 153, 142, 0.4);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .user-dashboard {
                margin: 30px 20px;
            }

            .user-card, .actions-card {
                padding: 25px;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }

            .user-options {
                flex-direction: column;
                align-items: center;
            }

            .btn-action {
                min-width: 200px;
            }
        }

        /* Add subtle animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .user-card, .actions-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .actions-card {
            animation-delay: 0.2s;
        }
    </style>
</head>
<body>
<?php include 'headers/header.php'; ?>

<div class="user-dashboard">
    <div class="dashboard-grid">
        <!-- Profile Information Card -->
        <div class="user-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user["first_name"], 0, 1) . substr($user["last_name"], 0, 1)) ?>
                </div>
                <div>
                    <h1 class="user-title">Welcome Back</h1>
                    <p class="user-subtitle"><?= htmlspecialchars($user["first_name"] . " " . $user["last_name"]) ?></p>
                </div>
            </div>
        </div>

        <!-- Account Details Card -->
        <div class="user-card">
            <h2 style="margin-bottom: 25px; color: #333; font-size: 1.3rem;">Account Details</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?= htmlspecialchars($user["first_name"] . " " . $user["last_name"]) ?></div>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">
                        <i class="fa-solid fa-envelope"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?= htmlspecialchars($user["email"]) ?></div>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">
                        <i class="fa-solid fa-phone"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Contact Number</div>
                        <div class="info-value"><?= htmlspecialchars($user["contact_number"] ?? "Not provided") ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions Card -->
        <div class="actions-card">
            <h2 class="actions-title">Quick Actions</h2>
            <div class="user-options">
                <a href="edit_profile.php" class="btn-action edit-btn">
                    <i class="fa-solid fa-user-pen"></i> 
                    Edit Profile
                </a>
                <a href="bookings.php" class="btn-action booking-btn">
                    <i class="fa-solid fa-calendar-check"></i> 
                    View Bookings
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleMenu() {
        const menu = document.querySelector('.menu');
        const hamburger = document.querySelector('.hamburger');
        const header = document.querySelector('.page-header');
        menu.classList.toggle('active');
        header.classList.toggle('hidden');
        if (menu.classList.contains('active')) {
            hamburger.style.display = 'none';
        } else {
            hamburger.style.display = 'block';
        }
    }
</script>
</body>
</html>