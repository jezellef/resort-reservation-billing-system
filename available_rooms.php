<?php
include 'db.php';

$check_in = $_POST['check_in'] ?? null;
$check_out = $_POST['check_out'] ?? null;

if (!$check_in || !$check_out) {
    echo "<p style='color: red;'>Please select both check-in and check-out dates.</p>";
    exit;
}

$roomsQuery = $conn->query("SELECT * FROM rooms WHERE status = 'available'");

echo "<style>
    .container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px;
    }

    .reservation-container {
        margin-bottom: 30px;
    }

    .steps {
        display: flex;
        justify-content: space-between;
        position: relative;
        margin-bottom: 40px;
    }

    .steps::before {
        content: '';
        position: absolute;
        top: 24px;
        left: 0;
        right: 0;
        height: 2px;
        background: #e0e0e0;
        z-index: 1;
    }

    .step {
        position: relative;
        z-index: 2;
        text-align: center;
        width: 25%;
    }

    .step-number {
        width: 50px;
        height: 50px;
        background-color: #e0e0e0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        color: #777;
        font-weight: bold;
        font-size: 20px;
        transition: all 0.3s ease;
    }

    .step-title {
        font-size: 14px;
        color: #777;
        transition: all 0.3s ease;
    }

    .step.active .step-number,
    .step.completed .step-number {
        background-color: #325f51;
        color: white;
    }

    .step.active .step-title,
    .step.completed .step-title {
        color: #325f51;
        font-weight: bold;
    }

    .rooms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .room-card {
        border: 1px solid #ccc;
        border-radius: 10px;
        overflow: hidden;
        background-color: #f9f9f9;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
    }

    .room-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }

    .room-info {
        padding: 15px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        flex-grow: 1;
    }

    .room-info h3 {
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 1.2em;
        color: #2c3e50;
    }

    .room-info p {
        margin: 5px 0;
    }

    .reserve-btn {
        margin-top: 10px;
        padding: 10px;
        background-color: #2ecc71;
        color: white;
        text-align: center;
        text-decoration: none;
        border-radius: 5px;
        display: inline-block;
        border: none;
        cursor: pointer;
    }

    .reserve-btn:hover {
        background-color: #27ae60;
    }

    form.date-form h2 {
        margin-bottom: 20px;
        color: #2c3e50;
    }
</style>";

echo "<div class='container'>";

echo "<div class='reservation-container'>
        <form class='date-form' action='available_rooms.php' method='post'>
            <h2>Select Your Stay</h2>
            <div class='steps'>
                <div class='step' onclick='goToStep(1)'>
                    <div class='step-number'>1</div>
                    <div class='step-title'>Dates & Guests</div>
                </div>
                <div class='step active' onclick='goToStep(2)'>
                    <div class='step-number'>2</div>
                    <div class='step-title'>Room Selection</div>
                </div>
                <div class='step' onclick='goToStep(3)'>
                    <div class='step-number'>3</div>
                    <div class='step-title'>Guest Details</div>
                </div>
                <div class='step' onclick='goToStep(4)'>
                    <div class='step-number'>4</div>
                    <div class='step-title'>Payment & Confirmation</div>
                </div>
            </div>
        </form>
    </div>";

echo "<h2>Available Rooms from <strong>$check_in</strong> to <strong>$check_out</strong></h2>";
echo "<div class='rooms-grid'>";

while ($room = $roomsQuery->fetch_assoc()) {
    $room_id = $room['id'];
    $room_quantity = $room['quantity'];

    $reservedQuery = $conn->prepare("
        SELECT SUM(quantity) as total_reserved
        FROM public_reservations
        WHERE room_id = ? 
        AND (
            (check_in <= ? AND check_out > ?) OR
            (check_in < ? AND check_out >= ?) OR
            (check_in >= ? AND check_out <= ?)
        )
    ");
    $reservedQuery->bind_param("issssss", $room_id, $check_out, $check_in, $check_out, $check_in, $check_in, $check_out);
    $reservedQuery->execute();
    $reservedResult = $reservedQuery->get_result()->fetch_assoc();

    $total_reserved = $reservedResult['total_reserved'] ?? 0;
    $available_quantity = $room_quantity - $total_reserved;

    if ($available_quantity > 0) {
        echo "<div class='room-card'>
            <img src='{$room['image']}' alt='{$room['name']}' class='room-image'>
            <div class='room-info'>
                <h3>{$room['name']}</h3>
                <p>{$room['description']}</p>
                <p><strong>₱{$room['night_tour_price']}</strong> per night</p>
                <p>Available: <strong>{$available_quantity}</strong></p>
                <form action='public-reservation-form.php' method='post'>
                        <input type='hidden' name='room_id' value='<?php echo $room_id; ?>'>
                        <input type='hidden' name='check_in' value='<?php echo $check_in; ?>'>
                        <input type='hidden' name='check_out' value='<?php echo $check_out; ?>'>
                        <input type='hidden' name='available_quantity' value='<?php echo $available_quantity; ?>'>

                        <label>Quantity:
                            <input type='number' name='quantity' min='1' max='<?php echo $available_quantity; ?>' value='1'>
                        </label>
                        <br>

                        <button type='submit' class='reserve-btn'>Reserve</button>
                </form>


            </div>
        </div>";
    }
}

echo "</div>"; // close rooms grid
echo "</div>"; // close main container
?>

<script>
function goToStep(stepNumber) {
    document.querySelectorAll('.step').forEach((step, index) => {
        step.classList.remove('active', 'completed');
        if (index + 1 < stepNumber) {
            step.classList.add('completed');
        } else if (index + 1 === stepNumber) {
            step.classList.add('active');
        }
    });
}
</script>
