<?php
// Include the Composer autoloader (ensure the path is correct)
require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

include 'db_connect.php'; // Include your database connection

$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$type = $_POST['type'] ?? '';


// Check if the parameters are set correctly for debugging purposes
if (empty($start_date) || empty($end_date)) {
    die('Error: Start date or End date not provided.');
}

// Debugging: Print the received parameters
echo 'Start Date: ' . $start_date . '<br>';
echo 'End Date: ' . $end_date . '<br>';
echo 'Type: ' . $type . '<br>';

// Query to fetch reservation data based on the date range
$query = "
    SELECT * 
    FROM reservations
    WHERE (check_in BETWEEN '$start_date' AND '$end_date') 
    AND reservation_type = '$type'
";

echo 'Query: ' . $query . '<br>';  // Print the query for debugging

$result = mysqli_query($conn, $query);

// Check if data is fetched correctly
if ($result) {
    $reservations = mysqli_fetch_all($result, MYSQLI_ASSOC);
    // Debugging: Check how many rows are returned
    echo 'Number of reservations fetched: ' . count($reservations) . '<br>';
} else {
    echo 'Error: ' . mysqli_error($conn) . '<br>';
    $reservations = [];
}

// If there are no reservations, show a message
if (empty($reservations)) {
    die('No reservations found for the given date range and type.');
}

// Create a new Dompdf instance
$dompdf = new Dompdf();

// Define HTML content (you can fetch this from your data or dynamically generate it)
$html = '
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>Reservation Report</h2>
    <p>Date Range: ' . htmlspecialchars($start_date) . ' to ' . htmlspecialchars($end_date) . '</p>
    <table>
        <thead>
            <tr>
                <th>Reservation Code</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Status</th>
                <th>Base Price</th>
                <th>Extras Price</th>
                <th>Total Price</th>
            </tr>
        </thead>
        <tbody>';

// Loop through the fetched reservation data
$total_base_price = 0;
$total_extras_price = 0;
$total_price = 0;

foreach ($reservations as $row) {
    $base_price = $row['base_price'];
    $extras_price = $row['extras_price'];
    $total = $base_price + $extras_price;

    $total_base_price += $base_price;
    $total_extras_price += $extras_price;
    $total_price += $total;

    $html .= '
    <tr>
        <td>' . htmlspecialchars($row['reservation_code']) . '</td>
        <td>' . htmlspecialchars($row['check_in']) . '</td>
        <td>' . htmlspecialchars($row['check_out']) . '</td>
        <td>' . htmlspecialchars($row['status']) . '</td>
        <td>' . number_format($base_price, 2) . '</td>
        <td>' . number_format($extras_price, 2) . '</td>
        <td>' . number_format($total, 2) . '</td>
    </tr>';
}

// End of the table
$html .= '
        </tbody>
    </table>
    <h3>Total Earnings: ₱' . number_format($total_price, 2) . '</h3>
    <h3>Base Price: ₱' . number_format($total_base_price, 2) . '</h3>
    <h3>Extras Price: ₱' . number_format($total_extras_price, 2) . '</h3>
</body>
</html>';

// Load the HTML content into Dompdf
$dompdf->loadHtml($html);

// Set paper size (A4, portrait)
$dompdf->setPaper('A4', 'portrait');

// Render PDF (first pass)
$dompdf->render();

// Output the generated PDF (view in browser)
$dompdf->stream('reservation_report.pdf', ['Attachment' => 0]); // 0 to view in browser, 1 to force download
?>
