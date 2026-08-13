<?php
$conn = new mysqli("localhost", "root", "", "resort_db");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $contact_number = $_POST['contact_number'];
    $allowed_tour_types = ['Day Tour', 'Night Tour'];
    $tour_type = trim($_POST['tour_type']);

    if (!in_array($tour_type, $allowed_tour_types)) {
        die("Invalid tour type selected.");
    }

    $room_id = $_POST['room_id'];
    $quantity = $_POST['room_quantity'];
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $adult_count = $_POST['adult_count'];
    $kid_count = $_POST['kid_count'];
    $extra_mattress = $_POST['extra_mattress'] ?? 0;
    $extra_pillow = $_POST['extra_pillow'] ?? 0;
    $extra_blanket = $_POST['extra_blanket'] ?? 0;
    $special_requests = $_POST['special_requests'] ?? '';
    $payment_method = $_POST['payment_method'];
    $transaction_number = $_POST['transaction_number'] ?? '';
    $created_by = 'Admin';

    // NEW: entrance fee from POST
    $entrance_fee = isset($_POST['entrance_fee']) ? floatval($_POST['entrance_fee']) : 0;

    // Split name
    $name_parts = explode(' ', $full_name, 2);
    $first_name = $name_parts[0];
    $last_name = $name_parts[1] ?? '';

    // Fetch room price
    $room = $conn->query("SELECT day_tour_price, night_tour_price FROM rooms WHERE id = $room_id")->fetch_assoc();
    $room_price = ($tour_type === 'Day Tour') ? $room['day_tour_price'] : $room['night_tour_price'];
    $room_base_price = $room_price * $quantity;

    // NEW: Add entrance fee to base price
    $base_price = $room_base_price + $entrance_fee;

    // Calculate extras
    $extras_price = ($extra_mattress * 200) + ($extra_pillow * 50) + ($extra_blanket * 100);
    $total_price = $base_price + $extras_price;

    // Handle file upload
    $proof_path = null;
    if (!empty($_FILES['proof_of_payment']['name'])) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir);
        $filename = uniqid() . "_" . basename($_FILES['proof_of_payment']['name']);
        $target_file = $target_dir . $filename;

        if (move_uploaded_file($_FILES["proof_of_payment"]["tmp_name"], $target_file)) {
            $proof_path = $target_file;
        }
    }

    $reservation_code = strtoupper(uniqid("PUB"));
    $stmt = $conn->prepare("INSERT INTO publicguest_reservations (
        reservation_code, room_id, quantity, check_in, check_out,
        first_name, last_name, email, contact_number, adult_count,
        kid_count, tour_type, special_requests, extra_mattress, extra_pillow,
        extra_blanket, base_price, extras_price, total_price,
        transaction_number, proof_of_payment, payment_method, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("siisssssssssssiiiddssss",

        $reservation_code, $room_id, $quantity, $check_in, $check_out,
        $first_name, $last_name, $email, $contact_number, $adult_count,
        $kid_count, $tour_type, $special_requests, $extra_mattress, $extra_pillow,
        $extra_blanket, $base_price, $extras_price, $total_price,
        $transaction_number, $proof_path, $payment_method, $created_by
    );

    if ($stmt->execute()) {
        echo "<script>alert('Walk-in check-in successful!'); window.location.href='check-ins.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
