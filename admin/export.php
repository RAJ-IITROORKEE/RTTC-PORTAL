<?php
define('APP_INIT', true);
require_once __DIR__ . '/../config/init.php';
SecurityHelper::requireAdminAuth();

$db = db();

// Export submitted applications as CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="RTTC_2026_Applications_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');

$fh = fopen('php://output', 'w');

// Header row
fputcsv($fh, [
    'ID', 'Username', 'Email', 'Phone', 'Verified',
    'First Name', 'Middle Name', 'Last Name', 'DOB', 'Age', 'Gender', 'Blood Group',
    'Religion', 'Category', 'EWS', 'OBC-NCL', 'PWD',
    "Father's Name", "Mother's Name",
    'Permanent Address', 'Emergency Contact', 'Annual Income',
    'HSLC Board', 'HSLC Year', 'HSLC %', 'HSLC Division',
    'HSSLC Board', 'HSSLC Year', 'HSSLC %', 'HSSLC Division',
    "Bachelor's Board", "Bachelor's Year", "Bachelor's %", "Bachelor's Division",
    'GU Reg No', 'GU Reg Year',
    'GUBEDCET Roll', 'GUBEDCET Marks', 'GUBEDCET Rank',
    'Payment ID', 'Amount Paid', 'Payment Date',
    'Step', 'Submitted', 'Registration Date',
]);

$rows = $db->query("SELECT u.id, u.username, u.email, u.phone, u.is_verified, u.created_at,
    p.firstname, p.middlename, p.lastname, p.dob, p.age, p.gender, p.blood_group, p.religion, p.caste, p.ews, p.obc_ncl, p.pwd,
    p.fathersname, p.mothersname, p.permanent_address, p.emergency_contact, p.income,
    a.hslc_board, a.hslc_pass_year, a.hslc_percentage, a.hslc_division,
    a.hsslc_board, a.hsslc_pass_year, a.hsslc_percentage, a.hsslc_division,
    a.bachelor_board, a.bachelor_pass_year, a.bachelor_percentage, a.bachelor_division,
    a.gu_reg_no, a.gu_reg_year, a.gubedcet_rollno, a.gubedcet_marks, a.gubedcet_rank,
    pay.razorpay_payment_id, pay.amount, pay.created_at as payment_date,
    rp.current_step, rp.is_submitted
    FROM users u
    LEFT JOIN personal_details p ON p.user_id = u.id
    LEFT JOIN academic_details a ON a.user_id = u.id
    LEFT JOIN payment pay ON pay.user_id = u.id AND pay.status = 'success'
    LEFT JOIN registration_progress rp ON rp.user_id = u.id
    ORDER BY u.created_at ASC");

$steps = ['Not Started', 'Personal', 'Academic', 'Docs', 'Payment Done'];
while ($row = $rows->fetch_assoc()) {
    fputcsv($fh, [
        $row['id'], $row['username'], $row['email'], $row['phone'], $row['is_verified'] ? 'Yes' : 'No',
        $row['firstname'] ?? '', $row['middlename'] ?? '', $row['lastname'] ?? '',
        $row['dob'] ?? '', $row['age'] ?? '', $row['gender'] ?? '', $row['blood_group'] ?? '',
        $row['religion'] ?? '', $row['caste'] ?? '',
        ($row['ews'] ?? 0) ? 'Yes' : 'No',
        ($row['obc_ncl'] ?? 0) ? 'Yes' : 'No',
        ($row['pwd'] ?? 0) ? 'Yes' : 'No',
        $row['fathersname'] ?? '', $row['mothersname'] ?? '',
        $row['permanent_address'] ?? '', $row['emergency_contact'] ?? '', $row['income'] ?? '',
        $row['hslc_board'] ?? '', $row['hslc_pass_year'] ?? '', $row['hslc_percentage'] ?? '', $row['hslc_division'] ?? '',
        $row['hsslc_board'] ?? '', $row['hsslc_pass_year'] ?? '', $row['hsslc_percentage'] ?? '', $row['hsslc_division'] ?? '',
        $row['bachelor_board'] ?? '', $row['bachelor_pass_year'] ?? '', $row['bachelor_percentage'] ?? '', $row['bachelor_division'] ?? '',
        $row['gu_reg_no'] ?? '', $row['gu_reg_year'] ?? '',
        $row['gubedcet_rollno'] ?? '', $row['gubedcet_marks'] ?? '', $row['gubedcet_rank'] ?? '',
        $row['razorpay_payment_id'] ?? '', $row['amount'] ? '₹' . number_format($row['amount'] / 100, 2) : '',
        $row['payment_date'] ? date('d M Y', strtotime($row['payment_date'])) : '',
        $steps[$row['current_step'] ?? 0] ?? 'Unknown',
        ($row['is_submitted'] ?? 0) ? 'Yes' : 'No',
        date('d M Y', strtotime($row['created_at'])),
    ]);
}

fclose($fh);
exit;
