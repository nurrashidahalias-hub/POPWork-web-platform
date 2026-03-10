<?php
session_start();
include("db_connect.php");

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized access');
}

// Get filters from URL parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$payment_method_filter = isset($_GET['payment_method']) ? $_GET['payment_method'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT pr.payment_id,
               j.title as job_title,
               j.category as job_category,
               wp.name as worker_name,
               wu.email as worker_email,
               ep.name as employer_name,
               eu.email as employer_email,
               pr.total_hours,
               pr.hourly_rate,
               pr.total_amount,
               pr.bonus_amount,
               pr.deduction_amount,
               pr.final_amount,
               pr.payment_status,
               pr.payment_method,
               pr.payment_reference,
               pr.payment_date,
               pr.created_at,
               pr.notes
        FROM payment_records pr
        JOIN jobs j ON pr.job_id = j.job_id
        JOIN users wu ON pr.user_id = wu.user_id
        LEFT JOIN profiles wp ON wu.user_id = wp.user_id
        JOIN users eu ON pr.employer_id = eu.user_id
        LEFT JOIN profiles ep ON eu.user_id = ep.user_id
        WHERE 1=1";

if ($status_filter != 'all') {
    $sql .= " AND pr.payment_status = '" . $conn->real_escape_string($status_filter) . "'";
}

if ($payment_method_filter != 'all') {
    $sql .= " AND pr.payment_method = '" . $conn->real_escape_string($payment_method_filter) . "'";
}

if ($date_from != '') {
    $sql .= " AND DATE(pr.created_at) >= '" . $conn->real_escape_string($date_from) . "'";
}

if ($date_to != '') {
    $sql .= " AND DATE(pr.created_at) <= '" . $conn->real_escape_string($date_to) . "'";
}

if ($search != '') {
    $search_term = $conn->real_escape_string($search);
    $sql .= " AND (j.title LIKE '%$search_term%' OR wp.name LIKE '%$search_term%' OR ep.name LIKE '%$search_term%')";
}

$sql .= " ORDER BY pr.created_at DESC";

$result = $conn->query($sql);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=payments_report_' . date('Y-m-d_His') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, [
    'Payment ID',
    'Job Title',
    'Job Category',
    'Worker Name',
    'Worker Email',
    'Employer Name',
    'Employer Email',
    'Total Hours',
    'Hourly Rate',
    'Base Amount',
    'Bonus',
    'Deduction',
    'Final Amount',
    'Payment Status',
    'Payment Method',
    'Payment Reference',
    'Payment Date',
    'Created Date',
    'Notes'
]);

// Add data rows
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['payment_id'],
        $row['job_title'],
        $row['job_category'],
        $row['worker_name'] ?? 'N/A',
        $row['worker_email'],
        $row['employer_name'] ?? 'N/A',
        $row['employer_email'],
        number_format($row['total_hours'], 2),
        number_format($row['hourly_rate'], 2),
        number_format($row['total_amount'], 2),
        number_format($row['bonus_amount'], 2),
        number_format($row['deduction_amount'], 2),
        number_format($row['final_amount'], 2),
        ucfirst($row['payment_status']),
        $row['payment_method'] ? ucfirst(str_replace('_', ' ', $row['payment_method'])) : 'N/A',
        $row['payment_reference'] ?? 'N/A',
        $row['payment_date'] ? date('Y-m-d H:i:s', strtotime($row['payment_date'])) : 'N/A',
        date('Y-m-d H:i:s', strtotime($row['created_at'])),
        $row['notes'] ?? ''
    ]);
}

fclose($output);
exit();
?>