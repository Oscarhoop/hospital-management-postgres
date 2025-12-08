<?php
// Initialize test data for the patient management system

require_once __DIR__ . '/db.php';

function log_message($message) {
    echo "[TEST] $message\n";
}

function clear_tables($pdo) {
    $driver = get_db_driver();
    $tables = [
        'audit_trail',
        'billing',
        'medical_records',
        'appointments',
        'doctors',
        'patients',
        'users',
        'rooms'
    ];

    foreach ($tables as $table) {
        if ($driver === 'pgsql') {
            $pdo->exec("TRUNCATE TABLE $table RESTART IDENTITY CASCADE");
            continue;
        }

        try {
            $pdo->exec("DELETE FROM $table");
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name = '$table'");
        } catch (Exception $ex) {
            log_message("Warning: Could not clear table $table - " . $ex->getMessage());
        }
    }
}

try {
    $pdo = get_pdo();
    $driver = get_db_driver();

    // Clear all data first
    log_message("Clearing existing data...");
    if ($driver === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys = OFF');
        clear_tables($pdo);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        clear_tables($pdo);
    }

    // Start transaction
    $pdo->beginTransaction();

    // Create test users (including doctors as users for scheduling) with Kenyan names
    $users = [
        ['Admin User', 'admin@hospital.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin'],
        ['Dr. Mwangi Wambui', 'mwangi.wambui@hospital.com', password_hash('doctor123', PASSWORD_DEFAULT), 'doctor'],
        ['Dr. Wambui Kariuki', 'wambui.kariuki@hospital.com', password_hash('doctor123', PASSWORD_DEFAULT), 'doctor'],
        ['Dr. Kariuki Wanjiku', 'kariuki.wanjiku@hospital.com', password_hash('doctor123', PASSWORD_DEFAULT), 'doctor'],
        ['Dr. Wanjiku Omondi', 'wanjiku.omondi@hospital.com', password_hash('doctor123', PASSWORD_DEFAULT), 'doctor'],
        ['Nurse Wanjiru Nyambura', 'wanjiru.nyambura@hospital.com', password_hash('nurse123', PASSWORD_DEFAULT), 'nurse'],
        ['Nurse Kamau Mwangi', 'kamau.mwangi@hospital.com', password_hash('nurse123', PASSWORD_DEFAULT), 'nurse'],
        ['Receptionist Njeri Otieno', 'njeri.otieno@hospital.com', password_hash('staff123', PASSWORD_DEFAULT), 'receptionist'],
        ['Staff Achieng Kipchoge', 'achieng.kipchoge@hospital.com', password_hash('staff123', PASSWORD_DEFAULT), 'staff']
    ];
    
    $userIds = [];
    foreach ($users as $user) {
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
        $stmt->execute($user);
        $userIds[] = $pdo->lastInsertId();
    }
    
    // Create test patients with Kenyan names
    $patients = [
        ['Wanjiru', 'Ochieng', '1985-05-15', 'F', '123 Ngong Road, Nairobi', '+254 712 345 678', 'wanjiru.ochieng@gmail.com', 'Regular checkup needed'],
        ['Kamau', 'Wanjala', '1990-08-22', 'M', '456 Mombasa Road, Nairobi', '+254 713 456 789', 'kamau.wanjala@gmail.com', 'Allergic to penicillin'],
        ['Njeri', 'Kamau', '1975-11-30', 'F', '789 Thika Road, Nairobi', '+254 714 567 890', 'njeri.kamau@gmail.com', 'Hypertension'],
        ['Otieno', 'Njeri', '1992-03-10', 'M', '321 Waiyaki Way, Nairobi', '+254 715 678 901', 'otieno.njeri@gmail.com', 'Diabetes type 2'],
        ['Achieng', 'Otieno', '1988-07-25', 'F', '654 Jogoo Road, Nairobi', '+254 716 789 012', 'achieng.otieno@gmail.com', ''],
        ['Kipchoge', 'Achieng', '1995-12-05', 'M', '987 Langata Road, Nairobi', '+254 717 890 123', 'kipchoge.achieng@gmail.com', 'Asthma'],
        ['Muthoni', 'Kipchoge', '1980-01-18', 'F', '147 Limuru Road, Nairobi', '+254 718 901 234', 'muthoni.kipchoge@gmail.com', 'High cholesterol'],
        ['Ochieng', 'Muthoni', '1993-09-14', 'M', '258 Kiambu Road, Nairobi', '+254 719 012 345', 'ochieng.muthoni@gmail.com', ''],
        ['Wanjala', 'Ochieng', '1978-06-20', 'M', '369 Kasarani Road, Nairobi', '+254 720 123 456', 'wanjala.ochieng@gmail.com', 'Previous surgery - knee'],
        ['Nyambura', 'Wanjala', '1987-04-08', 'F', '741 Rongai Road, Nairobi', '+254 721 234 567', 'nyambura.wanjala@gmail.com', 'Pregnant - 2nd trimester']
    ];
    
    $patientIds = [];
    foreach ($patients as $patient) {
        $stmt = $pdo->prepare('INSERT INTO patients (first_name, last_name, dob, gender, address, phone, email, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute($patient);
        $patientIds[] = $pdo->lastInsertId();
    }
    
    // Create test doctors with Kenyan names
    $doctors = [
        ['Mwangi', 'Wambui', 'Cardiology', '+254 712 111 222', 'mwangi.wambui@hospital.com', 'Board certified cardiologist with 15 years experience'],
        ['Wambui', 'Kariuki', 'Pediatrics', '+254 713 222 333', 'wambui.kariuki@hospital.com', 'Pediatric specialist, expert in child development'],
        ['Kariuki', 'Wanjiku', 'Orthopedics', '+254 714 333 444', 'kariuki.wanjiku@hospital.com', 'Orthopedic surgeon specializing in sports medicine'],
        ['Wanjiku', 'Omondi', 'Dermatology', '+254 715 444 555', 'wanjiku.omondi@hospital.com', 'Dermatologist with focus on cosmetic and medical dermatology'],
        ['Omondi', 'Akinyi', 'Neurology', '+254 716 555 666', 'omondi.akinyi@hospital.com', 'Neurologist specializing in movement disorders'],
        ['Akinyi', 'Njuguna', 'Oncology', '+254 717 666 777', 'akinyi.njuguna@hospital.com', 'Oncologist with expertise in breast cancer treatment'],
        ['Njuguna', 'Wairimu', 'Emergency Medicine', '+254 718 777 888', 'njuguna.wairimu@hospital.com', 'Emergency medicine physician, trauma specialist'],
        ['Wairimu', 'Onyango', 'Psychiatry', '+254 719 888 999', 'wairimu.onyango@hospital.com', 'Psychiatrist specializing in anxiety and depression'],
        ['Onyango', 'Adhiambo', 'General Surgery', '+254 720 999 000', 'onyango.adhiambo@hospital.com', 'General surgeon with laparoscopic expertise'],
        ['Adhiambo', 'Mwangi', 'Obstetrics & Gynecology', '+254 721 000 111', 'adhiambo.mwangi@hospital.com', 'OB/GYN with focus on high-risk pregnancies']
    ];
    
    $doctorIds = [];
    foreach ($doctors as $doctor) {
        $stmt = $pdo->prepare('INSERT INTO doctors (first_name, last_name, specialty, phone, email, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute($doctor);
        $doctorIds[] = $pdo->lastInsertId();
    }
    
    // Create test rooms
    $rooms = [
        ['101', 'Examination Room 1', 'Examination', 2, 'Standard examination room', 1],
        ['201', 'Operation Room 1', 'Operation', 5, 'Main operation room', 1],
        ['102', 'Examination Room 2', 'Examination', 1, 'Pediatric examination room', 1]
    ];
    
    $roomIds = [];
    foreach ($rooms as $room) {
        $stmt = $pdo->prepare('INSERT INTO rooms (room_number, room_name, room_type, capacity, notes, is_available) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute($room);
        $roomIds[] = $pdo->lastInsertId();
    }
    
    // Create test appointments
    $appointments = [
        [$patientIds[0], $doctorIds[0], $roomIds[0], date('Y-m-d H:i:s', strtotime('+1 day 09:00')), date('Y-m-d H:i:s', strtotime('+1 day 10:00')), 'scheduled', 'Routine checkup', null, null, null, $userIds[0]],
        [$patientIds[1], $doctorIds[1], $roomIds[1], date('Y-m-d H:i:s', strtotime('+1 day 10:30')), date('Y-m-d H:i:s', strtotime('+1 day 11:30')), 'scheduled', 'Pediatric consultation', null, null, null, $userIds[0]],
        [$patientIds[2], $doctorIds[2], $roomIds[0], date('Y-m-d H:i:s', strtotime('+2 days 14:00')), date('Y-m-d H:i:s', strtotime('+2 days 15:00')), 'scheduled', 'Knee examination', null, null, null, $userIds[0]],
        [$patientIds[3], $doctorIds[0], $roomIds[1], date('Y-m-d H:i:s', strtotime('+3 days 09:00')), date('Y-m-d H:i:s', strtotime('+3 days 10:00')), 'scheduled', 'Cardiac evaluation', null, null, null, $userIds[0]],
        [$patientIds[4], $doctorIds[3], $roomIds[2], date('Y-m-d H:i:s', strtotime('+4 days 11:00')), date('Y-m-d H:i:s', strtotime('+4 days 12:00')), 'scheduled', 'Skin consultation', null, null, null, $userIds[0]]
    ];
    
    $appointmentIds = [];
    foreach ($appointments as $appt) {
        $stmt = $pdo->prepare('INSERT INTO appointments (patient_id, doctor_id, room_id, start_time, end_time, status, reason, diagnosis, treatment, prescription, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute($appt);
        $appointmentIds[] = $pdo->lastInsertId();
    }
    
    // Create test medical records
    $medicalRecords = [
        [$patientIds[0], $appointmentIds[0], 'consultation', 'Initial Consultation', 'Patient presents with mild symptoms. Blood pressure slightly elevated.', $userIds[1]]
    ];
    
    foreach ($medicalRecords as $record) {
        $stmt = $pdo->prepare('INSERT INTO medical_records (patient_id, appointment_id, record_type, title, content, created_by) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute($record);
    }
    
    // Create test billing records
    $billing = [
        [$patientIds[0], $appointmentIds[0], 150.00, 'paid', '2023-12-16', 'credit_card', '2023-12-15', 'Consultation fee'],
        [$patientIds[1], null, 75.50, 'pending', '2023-12-20', null, null, 'Lab test']
    ];
    
    foreach ($billing as $bill) {
        $stmt = $pdo->prepare('INSERT INTO billing (patient_id, appointment_id, amount, status, due_date, payment_method, payment_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute($bill);
    }
    
    // Create test audit trail entries
    $auditEntries = [
        [$userIds[0], 'system_init', 'system', 1, 'Test data initialization'],
        [$userIds[0], 'create_patient', 'patient', $patientIds[0], 'Initial test patient created'],
        [$userIds[1], 'create_appointment', 'appointment', $appointmentIds[0], 'Initial test appointment']
    ];
    
    foreach ($auditEntries as $entry) {
        $stmt = $pdo->prepare('INSERT INTO audit_trail (user_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute($entry);
    }
    
    $pdo->commit();
    
    log_message("Test data initialization completed successfully!");
    log_message("Test users created with IDs: " . implode(', ', $userIds));
    log_message("Test patients created with IDs: " . implode(', ', $patientIds));
    log_message("Test doctors created with IDs: " . implode(', ', $doctorIds));
    log_message("Test appointments created with IDs: " . implode(', ', $appointmentIds));
    
    // Output test credentials
    log_message("\nTest Credentials:");
    log_message("Admin: admin@hospital.com / admin123");
    log_message("Doctor: doctor1@hospital.com / doctor123");
    log_message("Nurse: nurse1@hospital.com / nurse123");
    log_message("Staff: staff1@hospital.com / staff123");
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_message("Error: " . $e->getMessage());
    log_message("Stack trace: " . $e->getTraceAsString());
}
