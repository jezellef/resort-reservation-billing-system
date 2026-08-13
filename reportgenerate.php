<?php
session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] != 'Super Admin') {
    header("Location: access_denied.php");
    exit();
}
require_once 'db_connect.php';
require_once('tcpdf/tcpdf.php');

// Get parameters - Enhanced to support new report types
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : 'all';
$format = isset($_GET['format']) ? $_GET['format'] : '';

// Backward compatibility for old parameters
$earnings_type = isset($_GET['earnings_type']) ? $_GET['earnings_type'] : 'both';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Set today's date for today report
if ($report_type == 'today') {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
}

// Validate dates for reports that need them
if ($report_type != 'today') {
    if (empty($start_date) || empty($end_date)) {
        header("Location: generate_report.php");
        exit();
    }
    if (strtotime($end_date) < strtotime($start_date)) {
        $_SESSION['error'] = "End date cannot be before start date.";
        header("Location: generate_report.php");
        exit();
    }
}

$results = [];
$total_earnings = 0;
$public_earnings = 0;
$private_earnings = 0;
$title = "Report";

// Build SQL query based on report type
$sql = "SELECT * FROM reservations WHERE 1=1";
$params = [];

// Add date filter for applicable reports
if ($report_type != 'today' || $report_type == 'today') {
    $sql .= " AND DATE(check_in) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

// Handle backward compatibility first
if (!empty($action)) {
    if ($action == 'generate_report') {
        $report_type = 'reservation';
    } elseif ($action == 'generate_earnings') {
        $report_type = 'earnings';
        $type = $earnings_type == 'public' ? 'public_all' : 
               ($earnings_type == 'private' ? 'private_all' : 'all');
    }
}

// Add filters based on report type
switch ($report_type) {
    case 'reservation':
        $title = "Reservation Report";
        if ($type == 'public_all') {
            $sql .= " AND reservation_type = 'public'";
            $title = "Public Reservation Report";
        } elseif ($type == 'private_all') {
            $sql .= " AND reservation_type = 'private'";
            $title = "Private Reservation Report";
        } else {
            $title = "Combined Reservation Report";
        }
        break;
        
    case 'earnings':
        $title = "Earnings Report";
        if ($type == 'public_all') {
            $sql .= " AND reservation_type = 'public'";
            $title = "Public Earnings Report";
        } elseif ($type == 'private_all') {
            $sql .= " AND reservation_type = 'private'";
            $title = "Private Earnings Report";
        } else {
            $title = "Combined Earnings Report";
        }
        break;
        
    case 'checkin':
        $title = "Check-in Report";
        $sql .= " AND checkin_status IS NOT NULL";
        if ($type == 'public_all') {
            $sql .= " AND reservation_type = 'public'";
            $title = "Public Check-in Report";
        } elseif ($type == 'private_all') {
            $sql .= " AND reservation_type = 'private'";
            $title = "Private Check-in Report";
        }
        break;
        
    case 'checkout':
        $title = "Check-out Report";
        $sql .= " AND checkout_status IS NOT NULL";
        if ($type == 'public_all') {
            $sql .= " AND reservation_type = 'public'";
            $title = "Public Check-out Report";
        } elseif ($type == 'private_all') {
            $sql .= " AND reservation_type = 'private'";
            $title = "Private Check-out Report";
        }
        break;
        
    case 'sales':
        $title = "Sales Report";
        if ($type == 'public_all') {
            $sql .= " AND reservation_type = 'public'";
            $title = "Public Sales Report";
        } elseif ($type == 'private_all') {
            $sql .= " AND reservation_type = 'private'";
            $title = "Private Sales Report";
        }
        
        // Add payment status filter
        if ($payment_status == 'paid') {
            $sql .= " AND payment_status = 'paid'";
        } elseif ($payment_status == 'partial') {
            $sql .= " AND payment_status = 'partial'";
        } elseif ($payment_status == 'unpaid') {
            $sql .= " AND (payment_status = 'unpaid' OR payment_status IS NULL)";
        }
        break;
        
    case 'today':
        $title = "Today's Reservations";
        if ($type == 'public_all') {
            $sql .= " AND reservation_type = 'public'";
            $title = "Today's Public Reservations";
        } elseif ($type == 'private_all') {
            $sql .= " AND reservation_type = 'private'";
            $title = "Today's Private Reservations";
        }
        break;
        
    default:
        // Fallback for backward compatibility
        $title = "Reservation Report";
        break;
}

// Add status filter for applicable reports
if (($report_type == 'checkin' || $report_type == 'checkout' || $report_type == 'today') && $status_filter != 'all') {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY check_in DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$results = [];

while ($row = $result->fetch_assoc()) {
    $results[] = $row;
}

// Calculate earnings and statistics
foreach ($results as $reservation) {
    if (strtolower($reservation['reservation_type']) == 'public') {
        $public_earnings += floatval($reservation['total_price']);
    } elseif (strtolower($reservation['reservation_type']) == 'private') {
        $private_earnings += floatval($reservation['total_price']);
    }
    $total_earnings += floatval($reservation['total_price']);
}

$formatted_start_date = date('F d, Y', strtotime($start_date));
$formatted_end_date = date('F d, Y', strtotime($end_date));

// TCPDF class remains the same
class MYPDF extends TCPDF {
    protected $logoPath;
    protected $reportTitle;
    protected $reportPeriod;
    
    public function setLogoPath($path) {
        $this->logoPath = $path;
    }
    
    public function setReportTitle($title) {
        $this->reportTitle = $title;
    }
    
    public function setReportPeriod($period) {
        $this->reportPeriod = $period;
    }
    
    public function Header() {
        $this->SetFillColor(232, 245, 233);
        $this->Rect(0, 0, $this->getPageWidth(), 40, 'F');
        
        if (file_exists($this->logoPath)) {
            $x = 15;
            $y = 10;
            $width = 20;
            $this->StartTransform();
            $this->RoundedRect($x, $y, $width, $width, $width/2, '1111', 'CNZ');
            $this->Image($this->logoPath, $x, $y, $width, $width, 'PNG', '', 'T', false, 300);
            $this->StopTransform();
        }
        
        $this->SetTextColor(46, 139, 87);
        $this->SetFont('dejavusans', 'B', 15);
        $this->SetXY(40, 10);
        $this->Cell(0, 10, 'Rainbow Forest Paradise Resort and Campsite', 0, 1, 'L');
        $this->SetFont('dejavusans', '', 10);
        $this->SetXY(40, 22);
        $this->Cell(0, 5, 'Brgy. Cuyambay, Tanay, Rizal', 0, 1, 'L');
        $this->SetFont('dejavusans', 'B', 10);
        $this->SetXY($this->getPageWidth() - 100, 30);
        $this->Cell(85, 5, $this->reportTitle, 0, 1, 'R');
        $this->SetTextColor(0, 0, 0);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetDrawColor(76, 175, 80);
        $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
        $this->SetY($this->GetY() + 1);
        $this->SetFont('dejavusans', 'I', 8);
        $this->SetX(15);
        $this->Cell(60, 10, 'Rainbow Forest Paradise Resort and Campsite', 0, 0, 'L');
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, 0, 'C');
        $this->Cell(-60, 10, date('Y-m-d'), 0, 0, 'R');
    }
}

$logoPath = 'images/rainbow-logo.png';
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Rainbow Forest Paradise Resort');
$pdf->SetAuthor('Admin System');
$pdf->SetTitle($title);
$pdf->SetSubject('Resort Report');
$pdf->setLogoPath($logoPath);
$pdf->setReportTitle($title);
$pdf->SetMargins(15, 45, 15);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->SetFont('dejavusans', '', 10);
$pdf->setCellHeightRatio(1.25);
$pdf->AddPage();
$pdf->deletePage(1);
$pdf->AddPage();

$pdf->SetFillColor(248, 253, 248);
$pdf->Rect(15, 45, $pdf->getPageWidth() - 30, $pdf->getPageHeight() - 60, 'F');

$pdf->SetFont('dejavusans', 'B', 14);
$pdf->Cell(0, 10, 'Report Details', 0, 1, 'L');
$pdf->SetFont('dejavusans', '', 10);
$pdf->SetFillColor(232, 245, 233);
$pdf->RoundedRect(15, $pdf->GetY(), $pdf->getPageWidth() - 30, 25, 3.50, '1111', 'F');
$pdf->SetXY(20, $pdf->GetY() + 5);
$pdf->Cell(0, 6, 'Period: ' . $formatted_start_date . ' to ' . $formatted_end_date, 0, 1, 'L');
$pdf->SetX(20);
$pdf->Cell(0, 6, 'Total Records: ' . count($results), 0, 1, 'L');
$pdf->SetX(20);
$pdf->Cell(0, 6, 'Generated: ' . date('M d, Y h:i A'), 0, 1, 'L');
$pdf->Ln(8);

// Handle different report types
if ($report_type == 'earnings' || $report_type == 'sales') {
    // Earnings/Sales Summary
    $pdf->SetFont('dejavusans', 'B', 14);
    $pdf->Cell(0, 10, ($report_type == 'sales' ? 'Sales' : 'Earnings') . ' Summary', 0, 1, 'L');
    $pdf->SetFont('dejavusans', 'B', 10);
    
    $w = array(120, 60);
    $pdf->SetFillColor(76, 175, 80);
    $pdf->SetTextColor(255);
    $pdf->Cell($w[0], 7, 'Category', 1, 0, 'L', true);
    $pdf->Cell($w[1], 7, 'Amount (₱)', 1, 1, 'R', true);
    $pdf->SetTextColor(0);
    $pdf->SetFont('dejavusans', '', 10);
    
    $row_color = true;
    if ($type == 'all' || $type == 'public_all') {
        $pdf->SetFillColor(232, 245, 233);
        $pdf->Cell($w[0], 6, 'Public Reservations', 1, 0, 'L', $row_color);
        $pdf->Cell($w[1], 6, '₱' . number_format($public_earnings, 2), 1, 1, 'R', $row_color);
        $row_color = !$row_color;
    }
    
    if ($type == 'all' || $type == 'private_all') {
        $pdf->SetFillColor(241, 248, 242);
        $pdf->Cell($w[0], 6, 'Private Reservations', 1, 0, 'L', $row_color);
        $pdf->Cell($w[1], 6, '₱' . number_format($private_earnings, 2), 1, 1, 'R', $row_color);
        $row_color = !$row_color;
    }
    
    // Show payment analysis for sales reports
    if ($report_type == 'sales' && count($results) > 0) {
        $pdf->Ln(5);
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 10, 'Payment Analysis', 0, 1, 'L');
        
        $paid_count = 0;
        $partial_count = 0;
        $unpaid_count = 0;
        $total_paid = 0;
        $total_balance = 0;
        
        foreach ($results as $res) {
            if ($res['payment_status'] == 'paid') $paid_count++;
            elseif ($res['payment_status'] == 'partial') $partial_count++;
            else $unpaid_count++;
            
            $total_paid += floatval($res['amount_paid'] ?: 0);
            $total_balance += floatval($res['balance_due'] ?: 0);
        }
        
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetFillColor(232, 245, 233);
        $pdf->RoundedRect(15, $pdf->GetY(), $pdf->getPageWidth() - 30, 30, 3.50, '1111', 'F');
        $pdf->SetXY(20, $pdf->GetY() + 5);
        $pdf->Cell(0, 6, 'Fully Paid: ' . $paid_count . ' reservations', 0, 1, 'L');
        $pdf->SetX(20);
        $pdf->Cell(0, 6, 'Partially Paid: ' . $partial_count . ' reservations', 0, 1, 'L');
        $pdf->SetX(20);
        $pdf->Cell(0, 6, 'Unpaid: ' . $unpaid_count . ' reservations', 0, 1, 'L');
        $pdf->SetX(20);
        $pdf->Cell(0, 6, 'Total Outstanding: ₱' . number_format($total_balance, 2), 0, 1, 'L');
    }
    
    $pdf->Ln(10);
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->Cell(0, 10, 'Statistics', 0, 1, 'L');
    $pdf->SetFillColor(232, 245, 233);
    $pdf->RoundedRect(15, $pdf->GetY(), $pdf->getPageWidth() - 30, 20, 3.50, '1111', 'F');
    $pdf->SetXY(20, $pdf->GetY() + 5);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->Cell(0, 6, 'Total Records: ' . count($results), 0, 1, 'L');
    $pdf->SetX(20);
    $pdf->Cell(0, 6, 'Average Booking Value: ₱' . 
        (count($results) > 0 ? number_format($total_earnings / count($results), 2) : '0.00'), 0, 1, 'L');
        
} else {
    // Detailed table for other report types
    if (count($results) > 0) {
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->Cell(0, 10, 'Detailed ' . ucfirst($report_type) . ' Records', 0, 1, 'L');
        $pdf->SetFont('dejavusans', 'B', 9);
        
        // Adjust columns based on report type
        if ($report_type == 'checkin') {
            $w = array(30, 25, 20, 35, 30, 40);
            $headers = array('Code', 'Date', 'Type', 'Guest Name', 'Status', 'Check-in Time');
        } elseif ($report_type == 'checkout') {
            $w = array(28, 22, 18, 32, 28, 35, 25);
            $headers = array('Code', 'Date', 'Type', 'Guest Name', 'Status', 'Check-out Time', 'Damage Fee');
        } else {
            $w = array(40, 30, 30, 40, 40);
            $headers = array('Code', 'Date', 'Type', 'Guest', 'Amount (₱)');
        }
        
        $pdf->SetFillColor(46, 139, 87);
        $pdf->SetTextColor(255);
        
        foreach ($headers as $i => $header) {
            $pdf->Cell($w[$i], 7, $header, 1, 0, 'L', true);
        }
        $pdf->Ln();
        
        $pdf->SetTextColor(0);
        $pdf->SetFont('dejavusans', '', 8);
        $row_color = true;
        
        foreach ($results as $row) {
            if ($pdf->getY() > $pdf->getPageHeight() - 30) {
                $pdf->AddPage();
            }
            
            $pdf->SetFillColor(241, 248, 242);
            
            if ($report_type == 'checkin') {
                $pdf->Cell($w[0], 6, $row['reservation_code'], 1, 0, 'L', $row_color);
                $pdf->Cell($w[1], 6, date('M d, Y', strtotime($row['check_in'])), 1, 0, 'L', $row_color);
                $pdf->Cell($w[2], 6, ucfirst($row['reservation_type']), 1, 0, 'L', $row_color);
                $pdf->Cell($w[3], 6, $row['first_name'] . ' ' . $row['last_name'], 1, 0, 'L', $row_color);
                $pdf->Cell($w[4], 6, ucfirst($row['status']), 1, 0, 'L', $row_color);
                $pdf->Cell($w[5], 6, $row['checkin_time'] ? date('M d, h:i A', strtotime($row['checkin_time'])) : 'N/A', 1, 1, 'L', $row_color);
            } elseif ($report_type == 'checkout') {
                $pdf->Cell($w[0], 6, $row['reservation_code'], 1, 0, 'L', $row_color);
                $pdf->Cell($w[1], 6, date('M d, Y', strtotime($row['check_in'])), 1, 0, 'L', $row_color);
                $pdf->Cell($w[2], 6, ucfirst($row['reservation_type']), 1, 0, 'L', $row_color);
                $pdf->Cell($w[3], 6, $row['first_name'] . ' ' . $row['last_name'], 1, 0, 'L', $row_color);
                $pdf->Cell($w[4], 6, ucfirst($row['status']), 1, 0, 'L', $row_color);
                $pdf->Cell($w[5], 6, $row['checkout_time'] ? date('M d, h:i A', strtotime($row['checkout_time'])) : 'N/A', 1, 0, 'L', $row_color);
                $pdf->Cell($w[6], 6, '₱' . number_format($row['damage_fee'] ?: 0, 2), 1, 1, 'R', $row_color);
            } else {
                $pdf->Cell($w[0], 6, $row['reservation_code'], 1, 0, 'L', $row_color);
                $pdf->Cell($w[1], 6, date('M d, Y', strtotime($row['check_in'])), 1, 0, 'L', $row_color);
                $pdf->Cell($w[2], 6, ucfirst($row['reservation_type']), 1, 0, 'L', $row_color);
                $pdf->Cell($w[3], 6, ($row['guest_type'] == 'user' ? 'Registered User' : 'Guest'), 1, 0, 'L', $row_color);
                $pdf->Cell($w[4], 6, '₱' . number_format($row['total_price'], 2), 1, 1, 'R', $row_color);
            }
            
            $row_color = !$row_color;
        }
        
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->SetFillColor(165, 214, 167);
        $pdf->Cell(array_sum($w) - end($w), 6, 'Total:', 1, 0, 'R', true);
        $pdf->Cell(end($w), 6, '₱' . number_format($total_earnings, 2), 1, 1, 'R', true);
        
        $pdf->Ln(10);
        $pdf->SetFont('dejavusans', 'B', 14);
        $pdf->Cell(0, 10, 'Summary', 0, 1, 'L');
        $pdf->SetFillColor(232, 245, 233);
        $pdf->RoundedRect(15, $pdf->GetY(), $pdf->getPageWidth() - 30, 20, 3.50, '1111', 'F');
        $pdf->SetXY(20, $pdf->GetY() + 5);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(0, 6, 'Total Records: ' . count($results), 0, 1, 'L');
        $pdf->SetX(20);
        $pdf->Cell(0, 6, 'Total Revenue: ₱' . number_format($total_earnings, 2), 0, 1, 'L');
        
    } else {
        $pdf->SetFillColor(232, 245, 233);
        $pdf->RoundedRect(15, $pdf->GetY(), $pdf->getPageWidth() - 30, 20, 3.50, '1111', 'F');
        $pdf->SetXY(20, $pdf->GetY() + 7);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->Cell(0, 6, 'No records found for the selected criteria.', 0, 1, 'C');
    }
}

$pdf->SetY(-35);
$pdf->SetFont('dejavusans', 'I', 8);
$pdf->Cell(0, 5, 'Report generated on ' . date('F d, Y h:i A'), 0, 1, 'C');

$filename = 'RainbowForestParadise_' . str_replace(' ', '_', $title) . '_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D');
?>