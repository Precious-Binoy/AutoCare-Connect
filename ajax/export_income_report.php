<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

requireAdmin();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Fetch Income Data
$incomeQuery = "SELECT 
                    b.booking_number, 
                    b.service_type, 
                    u.name as customer_name, 
                    b.completion_date, 
                    b.mechanic_fee, 
                    (SELECT SUM(unit_price * quantity) FROM parts_used WHERE booking_id = b.id) as parts_cost,
                    (SELECT SUM(fee) FROM pickup_delivery WHERE booking_id = b.id AND status = 'completed') as delivery_fees,
                    b.final_cost
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                WHERE b.status IN ('completed', 'delivered') 
                AND DATE(b.completion_date) BETWEEN ? AND ?
                ORDER BY b.completion_date DESC";

$incomeResult = executeQuery($incomeQuery, [$start_date, $end_date], 'ss');

// CSV Headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=income_report_' . $start_date . '_to_' . $end_date . '.csv');

$output = fopen('php://output', 'w');

// Add Company Header Info
fputcsv($output, ['AutoCare Connect - Official Income Report']);
fputcsv($output, ['Generated On:', date('Y-m-d H:i:s')]);
fputcsv($output, ['Report Period:', $start_date . ' to ' . $end_date]);
fputcsv($output, []); // Empty row for spacing

// Column Headers
fputcsv($output, ['Date', 'Booking Number', 'Customer Name', 'Service Type', 'Labor Fee', 'Parts Cost', 'Logistics', 'Total Income']);

if ($incomeResult) {
    while ($row = $incomeResult->fetch_assoc()) {
        $parts_cost = $row['parts_cost'] ?? 0;
        $delivery_fees = $row['delivery_fees'] ?? 0;
        fputcsv($output, [
            date('Y-m-d', strtotime($row['completion_date'])),
            $row['booking_number'],
            $row['customer_name'],
            $row['service_type'],
            $row['mechanic_fee'],
            $parts_cost,
            $delivery_fees,
            $row['final_cost']
        ]);
    }
}

fclose($output);
exit();
