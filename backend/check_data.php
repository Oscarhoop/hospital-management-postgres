<?php
require_once 'backend/db.php';

$pdo = get_pdo();

echo "============================================\n";
echo "Database Summary Report\n";
echo "============================================\n\n";

$tables = [
    'users' => 'System Users',
    'patients' => 'Patients',
    'doctors' => 'Doctors',
    'rooms' => 'Rooms/Facilities',
    'appointments' => 'Appointments',
    'medical_records' => 'Medical Records',
    'billing' => 'Billing Records',
    'audit_trail' => 'Audit Trail Entries'
];

foreach ($tables as $table => $label) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo sprintf("%-25s %d\n", $label . ':', $count);
    } catch (Exception $e) {
        echo sprintf("%-25s Error\n", $label . ':');
    }
}

echo "\n============================================\n";
echo "Recent Users Added:\n";
echo "============================================\n";

try {
    $stmt = $pdo->query("SELECT name, email, role FROM users ORDER BY id DESC LIMIT 10");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("  %-30s %-35s [%s]\n", $row['name'], $row['email'], $row['role']);
    }
} catch (Exception $e) {
    echo "Error fetching users\n";
}

echo "\n";
?>
