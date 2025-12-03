<?php
// Sample Kenyan data script with RBAC roles and .gmail.com emails
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';

$pdo = get_pdo();

try {
    // Clear existing sample data (optional)
    $pdo->exec('DELETE FROM appointments');
    $pdo->exec('DELETE FROM billing');
    $pdo->exec('DELETE FROM medical_records');
    $pdo->exec('DELETE FROM rooms');
    $pdo->exec('DELETE FROM doctors');
    $pdo->exec('DELETE FROM patients');
    $pdo->exec('DELETE FROM users WHERE email != "admin@hospital.com"');

    // Users with different RBAC roles
    $users = [
        ['name' => 'Grace Wanjiru', 'email' => 'grace.wanjiru@gmail.com', 'password' => 'admin123', 'role' => 'admin'],
        ['name' => 'John Kamau', 'email' => 'john.kamau@gmail.com', 'password' => 'recept123', 'role' => 'receptionist'],
        ['name' => 'Dr. Mary Njeri', 'email' => 'mary.njeri@gmail.com', 'password' => 'doctor123', 'role' => 'doctor'],
        ['name' => 'Susan Achieng', 'email' => 'susan.achieng@gmail.com', 'password' => 'nurse123', 'role' => 'nurse'],
        ['name' => 'David Mutua', 'email' => 'david.mutua@gmail.com', 'password' => 'billing123', 'role' => 'billing'],
        ['name' => 'Faith Chebet', 'email' => 'faith.chebet@gmail.com', 'password' => 'pharm123', 'role' => 'pharmacist'],
        ['name' => 'Peter Kiprop', 'email' => 'peter.kiprop@gmail.com', 'password' => 'lab123', 'role' => 'lab_tech'],
    ];

    foreach ($users as $u) {
        $hashed = password_hash($u['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, phone, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$u['name'], $u['email'], $hashed, $u['role'], '+2547' . rand(10000000, 99999999), 'Sample user']);
    }

    // Patients
    $patients = [
        ['first_name' => 'James', 'last_name' => 'Mwangi', 'phone' => '+254712345678', 'email' => 'james.mwangi@gmail.com'],
        ['first_name' => 'Esther', 'last_name' => 'Wanjala', 'phone' => '+254723456789', 'email' => 'esther.wanjala@gmail.com'],
        ['first_name' => 'Samuel', 'last_name' => 'Kipchumba', 'phone' => '+254734567890', 'email' => 'samuel.kipchumba@gmail.com'],
        ['first_name' => 'Lucy', 'last_name' => 'Auma', 'phone' => '+254745678901', 'email' => 'lucy.auma@gmail.com'],
        ['first_name' => 'Joseph', 'last_name' => 'Mutiso', 'phone' => '+254756789012', 'email' => 'joseph.mutiso@gmail.com'],
        ['first_name' => 'Grace', 'last_name' => 'Njeri', 'phone' => '+254767890123', 'email' => 'grace.njeri@gmail.com'],
        ['first_name' => 'Michael', 'last_name' => 'Odhiambo', 'phone' => '+254778901234', 'email' => 'michael.odhiambo@gmail.com'],
        ['first_name' => 'Sarah', 'last_name' => 'Chebet', 'phone' => '+254789012345', 'email' => 'sarah.chebet@gmail.com'],
    ];

    foreach ($patients as $p) {
        $stmt = $pdo->prepare('INSERT INTO patients (first_name, last_name, phone, email, notes) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$p['first_name'], $p['last_name'], $p['phone'], $p['email'], 'Sample patient']);
    }

    // Doctors
    $doctors = [
        ['first_name' => 'Mary', 'last_name' => 'Njeri', 'specialty' => 'General Practice', 'phone' => '+254711223344', 'email' => 'mary.njeri@gmail.com'],
        ['first_name' => 'Samuel', 'last_name' => 'Kariuki', 'specialty' => 'Pediatrics', 'phone' => '+254722334455', 'email' => 'samuel.kariuki@gmail.com'],
        ['first_name' => 'Hellen', 'last_name' => 'Owino', 'specialty' => 'Obstetrics', 'phone' => '+254733445566', 'email' => 'hellen.owino@gmail.com'],
        ['first_name' => 'Peter', 'last_name' => 'Karanja', 'specialty' => 'Surgery', 'phone' => '+254744556677', 'email' => 'peter.karanja@gmail.com'],
    ];

    foreach ($doctors as $d) {
        $stmt = $pdo->prepare('INSERT INTO doctors (first_name, last_name, specialty, phone, email, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$d['first_name'], $d['last_name'], $d['specialty'], $d['phone'], $d['email'], 'Sample doctor']);
    }

    // Rooms
    $rooms = [
        ['room_number' => '101', 'room_name' => 'Consultation Room A', 'room_type' => 'Consultation', 'capacity' => 2],
        ['room_number' => '102', 'room_name' => 'Consultation Room B', 'room_type' => 'Consultation', 'capacity' => 2],
        ['room_number' => '201', 'room_name' => 'Pediatrics Ward', 'room_type' => 'Ward', 'capacity' => 10],
        ['room_number' => '202', 'room_name' => 'Maternity Ward', 'room_type' => 'Ward', 'capacity' => 8],
        ['room_number' => '301', 'room_name' => 'Operating Theatre 1', 'room_type' => 'Theatre', 'capacity' => 4],
        ['room_number' => '302', 'room_name' => 'Operating Theatre 2', 'room_type' => 'Theatre', 'capacity' => 4],
        ['room_number' => '401', 'room_name' => 'Laboratory', 'room_type' => 'Lab', 'capacity' => 3],
        ['room_number' => '402', 'room_name' => 'Radiology', 'room_type' => 'Radiology', 'capacity' => 2],
    ];

    foreach ($rooms as $r) {
        $stmt = $pdo->prepare('INSERT INTO rooms (room_number, room_name, room_type, capacity, notes) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$r['room_number'], $r['room_name'], $r['room_type'], $r['capacity'], 'Sample room']);
    }

    // Appointments (for the next few days)
    $appointments = [
        ['patient_id' => 1, 'doctor_id' => 1, 'room_id' => 1, 'start_time' => '2025-12-04 09:00:00', 'reason' => 'Routine checkup'],
        ['patient_id' => 2, 'doctor_id' => 2, 'room_id' => 2, 'start_time' => '2025-12-04 10:30:00', 'reason' => 'Child vaccination'],
        ['patient_id' => 3, 'doctor_id' => 3, 'room_id' => 4, 'start_time' => '2025-12-04 14:00:00', 'reason' => 'Prenatal check'],
        ['patient_id' => 4, 'doctor_id' => 1, 'room_id' => 1, 'start_time' => '2025-12-05 08:30:00', 'reason' => 'Follow-up'],
        ['patient_id' => 5, 'doctor_id' => 4, 'room_id' => 5, 'start_time' => '2025-12-05 11:00:00', 'reason' => 'Pre-op assessment'],
        ['patient_id' => 6, 'doctor_id' => 2, 'room_id' => 2, 'start_time' => '2025-12-05 13:00:00', 'reason' => 'Fever evaluation'],
        ['patient_id' => 7, 'doctor_id' => 3, 'room_id' => 4, 'start_time' => '2025-12-06 09:30:00', 'reason' => 'Pregnancy test'],
        ['patient_id' => 8, 'doctor_id' => 1, 'room_id' => 1, 'start_time' => '2025-12-06 15:00:00', 'reason' => 'Blood pressure check'],
    ];

    foreach ($appointments as $a) {
        $stmt = $pdo->prepare('INSERT INTO appointments (patient_id, doctor_id, room_id, start_time, end_time, status, reason, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $end_time = date('Y-m-d H:i:s', strtotime($a['start_time']) + 3600); // +1 hour
        $stmt->execute([$a['patient_id'], $a['doctor_id'], $a['room_id'], $a['start_time'], $end_time, 'scheduled', $a['reason'], 1]);
    }

    // Create some sample billing records
    $billing = [
        ['patient_id' => 1, 'amount' => 1500, 'notes' => 'Consultation fee'],
        ['patient_id' => 2, 'amount' => 800, 'notes' => 'Vaccination'],
        ['patient_id' => 3, 'amount' => 2000, 'notes' => 'Prenatal care'],
        ['patient_id' => 4, 'amount' => 1200, 'notes' => 'Follow-up visit'],
        ['patient_id' => 5, 'amount' => 3500, 'notes' => 'Pre-op assessment'],
    ];

    foreach ($billing as $b) {
        $stmt = $pdo->prepare('INSERT INTO billing (patient_id, amount, status, notes, created_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$b['patient_id'], $b['amount'], 'pending', $b['notes'], date('Y-m-d H:i:s')]);
    }

    echo "Sample Kenyan data created successfully!\n";
    echo "Users created with passwords:\n";
    foreach ($users as $u) {
        echo "- {$u['email']} ({$u['role']}) : {$u['password']}\n";
    }
    echo "\nPatients, doctors, rooms, appointments, and billing records added.\n";

} catch (Exception $e) {
    die("Sample data creation failed: " . $e->getMessage());
}
?>
