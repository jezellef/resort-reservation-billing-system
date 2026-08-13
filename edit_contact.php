<?php
session_start();

// Use database.php for consistency with contact.php
$mysqli = require __DIR__ . "/database.php";

// Initialize variables
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_contact_details'])) {
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $facebook = $_POST['facebook'] ?? '';
        $address = $_POST['address'] ?? '';
        
        // Update phone
        updateOrInsertContent('contact_phone', $phone, 'contact');
        
        // Update email
        updateOrInsertContent('contact_email', $email, 'contact');
        
        // Update Facebook
        updateOrInsertContent('contact_facebook', $facebook, 'contact');
        
        // Update address
        updateOrInsertContent('contact_address', $address, 'contact');
        
        $message = "Contact details updated successfully!";
        $messageType = "success";
    }
}

// Function to update or insert content
function updateOrInsertContent($section_name, $content, $section) {
    global $mysqli;
    
    // Check if record exists
    $checkSql = "SELECT id FROM site_content WHERE section_name = ?";
    $checkStmt = $mysqli->prepare($checkSql);
    $checkStmt->bind_param("s", $section_name);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Update existing record
        $row = $checkResult->fetch_assoc();
        $id = $row['id'];
        
        $updateSql = "UPDATE site_content SET content = ?, section = ? WHERE id = ?";
        $updateStmt = $mysqli->prepare($updateSql);
        $updateStmt->bind_param("ssi", $content, $section, $id);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        // Insert new record
        $insertSql = "INSERT INTO site_content (section_name, content, section) VALUES (?, ?, ?)";
        $insertStmt = $mysqli->prepare($insertSql);
        $insertStmt->bind_param("sss", $section_name, $content, $section);
        $insertStmt->execute();
        $insertStmt->close();
    }
    $checkStmt->close();
}

// Fetch current contact details
function getContentBySection($section_name) {
    global $mysqli;
    
    $sql = "SELECT content FROM site_content WHERE section_name = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $section_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['content'] ?? '';
    }
    
    $stmt->close();
    return '';
}

// Get current contact details
$phone = getContentBySection('contact_phone');
$email = getContentBySection('contact_email');
$facebook = getContentBySection('contact_facebook');
$address = getContentBySection('contact_address');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Edit Contact Page</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles/adminstyle.css">
    <style>
        .form-label {
            font-weight: bold;
        }
        .btn-primary {
            background-color: #2d6a4f;
            border-color: #2d6a4f;
        }
        .btn-primary:hover {
            background-color: #1b4332;
            border-color: #1b4332;
        }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
    </style>
</head>
<body>
<?php include 'headers/adminheader.php'; ?>

<div class="main-content">
    <div class="container mt-4">
        <h1>Edit Contact Page</h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <h3 class="card-title">Update Contact Information</h3>
                <p class="text-muted">Update the contact details that will appear on the Contact Us page.</p>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number:</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($phone ?? '') ?>" placeholder="e.g., 0960 587 7561">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address:</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" placeholder="e.g., rainbowforestparadise2020@gmail.com">
                    </div>
                    
                    <div class="mb-3">
                        <label for="facebook" class="form-label">Facebook Page URL:</label>
                        <input type="url" class="form-control" id="facebook" name="facebook" value="<?= htmlspecialchars($facebook ?? '') ?>" placeholder="e.g., https://www.facebook.com/yourpage">
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Resort Address:</label>
                        <textarea class="form-control" id="address" name="address" rows="3" placeholder="e.g., Brgy. Cuyambay, Tanay, Rizal"><?= htmlspecialchars($address ?? '') ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" name="update_contact_details" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Contact Details
                        </button>
                        
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>