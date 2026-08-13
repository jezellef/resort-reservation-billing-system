<?php
require_once('tcpdf/tcpdf.php');
function generatePDF($reservation) {
    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Rainbow Forest Paradise Resort and Campsite');
    $pdf->SetTitle('Reservation Confirmation');
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', '', 10);
    
    // FIXED: Determine room names and reservation type from actual booked rooms
    $roomNames = [];
    $isPrivateReservation = false;
    $hasSecondHouse = false;
    
    if (isset($reservation['rooms']) && is_array($reservation['rooms'])) {
        foreach ($reservation['rooms'] as $room) {
            // Check if private room (ID 28) is booked
            if ($room['room_id'] == 28) {
                $isPrivateReservation = true;
            }
        }
    }
    
    // Check for second house based on extras_price or explicit flag
    $extrasPrice = floatval($reservation['extras_price'] ?? 0);
    if ($extrasPrice >= 3000 || ($reservation['add_second_house'] ?? 0) || ($reservation['has_second_house'] ?? false)) {
        $hasSecondHouse = true;
    }
    
    // FIXED: Create simplified room display for Private vs Public
    if ($isPrivateReservation) {
        $roomsDisplay = $hasSecondHouse ? 'Private Area with 2nd House' : 'Private Area';
        $reservationType = 'Private';
    } else {
        // For public reservations, show room names
        if (isset($reservation['rooms']) && is_array($reservation['rooms'])) {
            foreach ($reservation['rooms'] as $room) {
                $roomNames[] = $room['room_name'] . ' (Qty: ' . $room['quantity'] . ')';
            }
            $roomsDisplay = !empty($roomNames) ? implode(', ', $roomNames) : 'Public Area';
        } else {
            $roomsDisplay = 'Public Area';
        }
        $reservationType = 'Public';
    }
    
    // FIXED: Get tour type from reservation data and map correctly
    $tourTypeString = isset($reservation['tour_type']) ? $reservation['tour_type'] : 
                     (isset($reservation['default_tour_type']) ? $reservation['default_tour_type'] : 'day_tour');
    
    // Updated tour type mapping with correct labels
    $tourTypes = [
        'day_tour' => 'Day Tour',
        'overnight_am' => 'Overnight AM',
        'overnight_pm' => 'Overnight PM', 
        'whole_day' => 'Whole Day',
        'night_tour' => 'Night Tour',
        'overnight_special' => 'Overnight Special'
    ];
    
    $tourTypeLabel = $tourTypes[$tourTypeString] ?? 'Day Tour';
    
    $totalAmount = $reservation['total_price'];
    $amountPaid = $reservation['amount_paid'];
    $remainingBalance = $totalAmount - $amountPaid;
    
    // FIXED: Correct check-in times based on reservation type and tour type
    $checkInTime = 'See resort for details';
    
    if ($isPrivateReservation) {
        // Private area schedules
        switch ($tourTypeString) {
            case 'day_tour':
                $checkInTime = '9:00 AM – 6:00 PM';
                break;
            case 'night_tour':
            case 'overnight_pm':
                $checkInTime = '8:00 PM – 7:00 AM (next day)';
                break;
            case 'overnight_am':
            case 'whole_day':
                $checkInTime = "9:00 AM – 7:00 AM (next day)\n8:00 PM – 6:00 PM (next day)";
                break;
            default:
                $checkInTime = '9:00 AM – 6:00 PM';
        }
    } else {
        // Public area schedules
        switch ($tourTypeString) {
            case 'day_tour':
                $checkInTime = '8:00 AM – 5:00 PM';
                break;
            case 'overnight_am':
                $checkInTime = '9:00 AM – 7:00 AM (next day)';
                break;
            case 'overnight_pm':
                $checkInTime = '6:00 PM – 4:00 PM (next day)';
                break;
            case 'overnight_special':
                $checkInTime = '2:00 PM – 12:00 NN (next day) — Campers/Tent Users Only';
                break;
            case 'whole_day':
                $checkInTime = "2:00 PM – 12:00 NN (next day) — Campers/Tent Users Only";
                break;
            default:
                $checkInTime = '8:00 AM – 5:00 PM';
        }
    }
    
    $logoPath = 'images/rainbow-logo.jpg';
    $html = '
    <div style="text-align:center;">
        <img src="' . $logoPath . '" width="80" height="80" />
        <h1 style="margin: 5px 0;">Rainbow Forest Paradise Resort and Campsite</h1>
        <p style="margin: 0;">Brgy. Cuyambay, Tanay, Rizal</p>
    </div>
    <h2 style="text-align:center; color:#31708f;">Reservation Approved</h2>
    <p style="text-align:center;">Thank you for your reservation! Your payment has been received and confirmed.</p>
    <h4 style="background-color:#d9edf7; padding:5px;">Reservation Details:</h4>
    <table cellpadding="6" cellspacing="0" border="1" style="width:100%; border-collapse:collapse;">
        <tr style="background-color:#f0f0f0;"><th><b>Reservation Code</b></th><td>' . htmlspecialchars($reservation['reservation_code']) . '</td></tr>
        <tr><th><b>Name</b></th><td>' . htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) . '</td></tr>
        <tr style="background-color:#f0f0f0;"><th><b>Email</b></th><td>' . htmlspecialchars($reservation['email']) . '</td></tr>
        <tr><th><b>Contact Number</b></th><td>' . htmlspecialchars($reservation['contact_number']) . '</td></tr>
        <tr style="background-color:#f0f0f0;"><th><b>Reservation Type</b></th><td>' . $reservationType . '</td></tr>
        <tr><th><b>Booked Rooms/Areas</b></th><td>' . htmlspecialchars($roomsDisplay) . '</td></tr>
        <tr style="background-color:#f0f0f0;"><th><b>Tour Package</b></th><td>' . $tourTypeLabel . '</td></tr>
        <tr><th><b>Check-in Date</b></th><td>' . htmlspecialchars($reservation['check_in']) . '</td></tr>
        <tr style="background-color:#f0f0f0;"><th><b>Check-out Date</b></th><td>' . htmlspecialchars($reservation['check_out']) . '</td></tr>
        <tr><th><b>Check-in Time</b></th><td>' . nl2br($checkInTime) . '</td></tr>
        <tr style="background-color:#f0f0f0;"><th><b>Adults</b></th><td>' . htmlspecialchars($reservation['adult_count']) . '</td></tr>
        <tr><th><b>Kids</b></th><td>' . htmlspecialchars($reservation['kid_count']) . '</td></tr>
        <tr style="background-color:#f0f0f0;"><th><b>Total Amount</b></th><td>₱' . htmlspecialchars($totalAmount) . '</td></tr>
        <tr><th><b>Amount Paid</b></th><td>₱' . htmlspecialchars($amountPaid) . '</td></tr>
        <tr style="background-color:#f0f0f0;"><th><b>Remaining Balance</b></th><td>₱' . htmlspecialchars($remainingBalance) . '</td></tr>
    </table>
    <br><h4 style="background-color:#dff0d8; padding:5px;">Next Steps</h4>
    <ul>
        <li>Save or print this confirmation for your records and present upon arrival.</li>
        <li>Pay the remaining balance on or before the day of your schedule.</li>
        <li>For inquiries, contact rainbowforestparadiseresort22@gmail.com or call 0960 587 7561.</li>
    </ul>
     <div style="page-break-before: always;"></div>
    <br><h4 style="background-color:#fcf8e3; padding:5px; color:#8a6d3b;">Important Notices</h4>
    <ul>
        <li>Free entrance for kids below 3 years old — please present a birth certificate or valid ID.</li>
        <li>Senior Citizens & PWDs — please bring your PWD ID and PWD Notebook</li>
    </ul>
   
    <br><h4 style="background-color:#f2dede; padding:5px; color:#a94442;">House Rules & Reminders</h4>
    <ul>
        <li>Proper swimming attire is required. No maong shorts allowed.</li>
        <li>Pets allowed (₱200/pet fee, up to medium size).</li>
        <li>Cooking inside rooms is not allowed. Use designated outdoor cooking areas.</li>
        <li>Bringing your own food and cookware is allowed.</li>
        <li>Do not bring electric appliances (e.g., rice cooker, kettle).</li>
        <li>Alcoholic drinks from outside are not allowed. Available on-site.</li>
        <li>No drinking beside pools.</li>
        <li>Speaker/videoke curfew at 11:00 PM.</li>
    </ul>
    <br><h4 style="background-color:#d9edf7; padding:5px;">Pool Hours</h4>
    <ul>
        <li>Big Pool: 6:00 AM – 10:00 PM</li>
        <li>Regular Pool: 6:00 AM – 11:00 PM</li>
    </ul>
    <p><i>Smart signal only. Available Wi-Fi service voucher.<br>
    Campers may charge devices at the information office.</i></p>
    <br><h4 style="background-color:#fcf8e3; padding:5px; color:#8a6d3b;">Rebooking & Cancellation</h4>
    <p><strong>Rebooking Policy:</strong> Rebooking is allowed on weekdays only and within 3 months of the original booking. Late notices will be forfeited. Rebook before your original schedule.</p>
    <p><strong>Cancellation/Refund Policy:</strong> No cancellations or refunds are allowed. For concerns, contact us via Facebook.</p>
    <br><h4 style="background-color:#dff0d8; padding:5px;">Extra / Additional On-site Fees</h4>
    <ul>
        <li>Extra Pillow: ₱50, Extra Blanket: ₱150, Extra Mattress: ₱300</li>
        <li>Pet Fee: ₱150 per pet</li>
        <li>Bonfire: ₱300 per set</li>
        <li>Zipline: ₱250 per head</li>
        <li>Spiderweb: ₱100 per head</li>
        <li>ATV: ₱550 / 30 mins</li>
        <li>Parking (Outside): ₱30–₱100</li>
        <li>Tent Pitching: ₱50–₱200</li>
    </ul>
    <p><i>These extras and activities are payable upon arrival and are not included in your reservation.</i></p>
    ';
    $pdf->writeHTML($html, true, false, true, false, '');
    $folderPath = __DIR__ . '/reservations';
    if (!is_dir($folderPath)) {
        mkdir($folderPath, 0777, true);
    }
    $pdfPath = $folderPath . "/confirmation_" . $reservation['reservation_code'] . ".pdf";
    $pdf->Output($pdfPath, 'F');
    return $pdfPath;
}
?>