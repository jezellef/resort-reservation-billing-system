<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_POST['backup_now'])) {
    // Database connection info
    $servername = 'localhost';
    $username = 'u291458526_resort_user'; 
    $password = 'r@inboWforest123!';   
    $database = 'u291458526_resort_db';      

    // Create database connection
    $conn = new mysqli($servername, $username, $password, $database);

    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }

    // Fetch all tables
    $tables = array();
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    $backup = "-- Database Backup\n";
    $backup .= "-- Created: " . date('Y-m-d H:i:s') . "\n\n";

    foreach ($tables as $table) {
        // Get table structure
        $result = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch_row();
        $backup .= "\n\n" . $row[1] . ";\n\n";

        // Get table data
        $result = $conn->query("SELECT * FROM `$table`");
        while ($row = $result->fetch_assoc()) {
            $columns = array_keys($row);
            $values = array_map(function($value) use ($conn) {
                if (is_null($value)) {
                    return "NULL";
                }
                return "'" . $conn->real_escape_string($value) . "'";
            }, array_values($row));


            $backup .= "INSERT INTO `$table` (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', $values) . ");\n";
        }
    }

    $conn->close();

    // Set headers to download the backup
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="backup_' . date('Ymd_His') . '.sql"');
    echo $backup;
    exit;
}
?>

<!-- HTML PART -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Data Backup</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        h1 {
            color: #333;
            margin-bottom: 5px;
        }
        p {
            color: #666;
            margin-bottom: 30px;
        }
        form {
            text-align: center;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #45a049;
        }
        .go-back {
            margin-top:80px;
        }
        .go-back a {
            text-decoration: none;
            color: #007bff;
            font-size: 16px;
            font-weight: bold;
            transition: color 0.3s;
        }
        .go-back a:hover {
            color: #0056b3;
        }
    </style>
</head>
<body>
    <h1>Data Backup</h1>
    <p>Backup and restore system data securely.</p>
    
    <form method="post" action="admin_backup.php">
        <button type="submit" name="backup_now">Download Backup</button>
    </form>
    
    <div class="go-back">
        <a href="admin_settings.php">← Go Back</a>
    </div>
</body>
</html>
