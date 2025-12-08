<?php
// Appointments API endpoints
session_start();

require_once __DIR__ . '/../cors.php';

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/permissions.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo();
$driver = get_db_driver();

// Helper functions
function read_json() {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    return $data ?: [];
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function sql_date_cast(string $column): string {
    global $driver;
    return $driver === 'pgsql' ? "{$column}::date" : "DATE({$column})";
}

function sql_time_cast(string $column): string {
    global $driver;
    return $driver === 'pgsql' ? "{$column}::time" : "TIME({$column})";
}

/**
 * Update room availability status based on appointments
 * 
 * @param PDO $pdo
 * @param int|null $roomId
 * @param string|null $appointmentStartTime
 * @param string|null $appointmentStatus
 * @return void
 */
function updateRoomAvailability(PDO $pdo, ?int $roomId, ?string $appointmentStartTime = null, ?string $appointmentStatus = null): void {
    if (!$roomId) {
        return; // No room assigned
    }
    
    // If appointment is cancelled, make room available
    if ($appointmentStatus === 'cancelled') {
        $stmt = $pdo->prepare("UPDATE rooms SET is_available = 1 WHERE id = ?");
        $stmt->execute([$roomId]);
        return;
    }
    
    // If appointment start time is in the past, make room available
    if ($appointmentStartTime && strtotime($appointmentStartTime) < time()) {
        $stmt = $pdo->prepare("UPDATE rooms SET is_available = 1 WHERE id = ?");
        $stmt->execute([$roomId]);
        return;
    }
    
    // If appointment is scheduled and in the future, make room occupied
    if ($appointmentStartTime && strtotime($appointmentStartTime) >= time()) {
        $stmt = $pdo->prepare("UPDATE rooms SET is_available = 0 WHERE id = ?");
        $stmt->execute([$roomId]);
    }
}

/**
 * Check if a room should be freed (no active future appointments)
 * 
 * @param PDO $pdo
 * @param int $roomId
 * @return void
 */
function checkAndFreeRoom(PDO $pdo, int $roomId): void {
    global $driver;
    $nowExpr = $driver === 'pgsql' ? 'NOW()' : "datetime('now')";
    // Check if room has any active future appointments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE room_id = ? 
        AND status != 'cancelled'
        AND start_time >= " . $nowExpr . "
    ");
    $stmt->execute([$roomId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no active future appointments, make room available
    if ($result && $result['count'] == 0) {
        $stmt = $pdo->prepare("UPDATE rooms SET is_available = 1 WHERE id = ?");
        $stmt->execute([$roomId]);
    }
}

/**
 * Check if a doctor is available for a given time slot.
 *
 * @param PDO $pdo
 * @param int $doctorId
 * @param string $startTime
 * @param string $endTime
 * @return bool
 */
function isDoctorAvailable(PDO $pdo, int $doctorId, string $startTime, string $endTime): bool {
    $appointmentDate = date('Y-m-d', strtotime($startTime));
    $appointmentStartTime = date('H:i:s', strtotime($startTime));
    $appointmentEndTime = date('H:i:s', strtotime($endTime));

    // Try to find if this doctor is linked to a user account
    // Check by matching doctor email with user email, or by name
    $stmt = $pdo->prepare("SELECT email, first_name, last_name FROM doctors WHERE id = ?");
    $stmt->execute([$doctorId]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor) {
        return false; // Doctor doesn't exist
    }
    
    $userId = null;
    
    // Try to find matching user by email
    if (!empty($doctor['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$doctor['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $userId = $user['id'];
        }
    }
    
    // If no user found by email, try by name
    if (!$userId && !empty($doctor['first_name']) && !empty($doctor['last_name'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE name LIKE ?");
        $namePattern = '%' . $doctor['first_name'] . '%' . $doctor['last_name'] . '%';
        $stmt->execute([$namePattern]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $userId = $user['id'];
        }
    }
    
    // If doctor is linked to a user, check their schedule and leave
    if ($userId) {
        // 1. Check if the doctor has an approved leave request for that day
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM leave_requests 
            WHERE user_id = ? 
            AND status = \'approved\' 
            AND start_date <= ? 
            AND end_date >= ?
        ");
        $stmt->execute([$userId, $appointmentDate, $appointmentDate]);
        if ($stmt->fetchColumn() > 0) {
            return false; // Doctor is on leave
        }

        // 2. Check if the doctor is scheduled to work during the appointment time
        // If they have a schedule, verify it matches the appointment time
        // If no schedule exists, allow it (flexible scheduling - they might work without being scheduled)
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM staff_schedules
            WHERE user_id = ?
            AND schedule_date = ?
            AND status != 'cancelled'
            AND (
                (start_time <= ? AND end_time > ?) OR
                (start_time < ? AND end_time >= ?) OR
                (start_time >= ? AND end_time <= ?)
            )
        ");
        $stmt->execute([
            $userId, 
            $appointmentDate,
            $appointmentStartTime, $appointmentStartTime,
            $appointmentEndTime, $appointmentEndTime,
            $appointmentStartTime, $appointmentEndTime
        ]);
        
        $hasSchedule = $stmt->fetchColumn() > 0;
        
        // If doctor has a schedule for this time, they're available
        // If no schedule exists, still allow it (flexible scheduling)
        // The conflict check in the main code will prevent double-booking
        return true; // Doctor is not on leave, so they're available
    }
    
    // If doctor is not linked to a user account, we can't check schedules
    // Allow the appointment (doctor might work without being in scheduling system)
    // The conflict check in the main code will still prevent double-booking
    return true;
}

try {
    switch ($method) {
        case 'GET':
            // Check availability endpoint
            if (isset($_GET['action']) && $_GET['action'] === 'check_availability') {
                try {
                    $date = $_GET['date'] ?? date('Y-m-d');
                    $start_time = $_GET['start_time'] ?? '08:00';
                    $end_time = $_GET['end_time'] ?? '18:00';
                    
                    // Get all doctors first
                    try {
                        $stmt = $pdo->prepare("SELECT id, first_name, last_name, specialty FROM doctors ORDER BY last_name, first_name");
                        $stmt->execute();
                        $all_doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        // Doctors table might not exist, return empty results
                        echo json_encode([
                            'date' => $date,
                            'available_doctors' => [],
                            'available_rooms' => [],
                            'time_slots' => []
                        ]);
                        exit;
                    }

                    // Filter doctors based on their work schedule, leave status, and existing appointments
                    $available_doctors = [];
                    $appointmentStartTime = $date . ' ' . $start_time;
                    $appointmentEndTime = $date . ' ' . $end_time;

                    foreach ($all_doctors as $doctor) {
                        // Check if doctor is available (not on leave, schedule allows it)
                        if (!isDoctorAvailable($pdo, $doctor['id'], $appointmentStartTime, $appointmentEndTime)) {
                            continue; // Skip if doctor is on leave or not scheduled
                        }
                        
                        // Check if doctor has conflicting appointments
                        $dateExpr = sql_date_cast('start_time');
                        $startTimeExpr = sql_time_cast('start_time');
                        $endTimeExpr = sql_time_cast('end_time');
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) 
                            FROM appointments 
                            WHERE doctor_id = ? 
                            AND status != 'cancelled'
                            AND " . $dateExpr . " = ?
                            AND (
                                (" . $startTimeExpr . " >= ? AND " . $startTimeExpr . " < ?)
                                OR (" . $endTimeExpr . " > ? AND " . $endTimeExpr . " <= ?)
                                OR (" . $startTimeExpr . " <= ? AND " . $endTimeExpr . " >= ?)
                            )
                        ");
                        $stmt->execute([
                            $doctor['id'], 
                            $date, 
                            $start_time, $end_time,
                            $start_time, $end_time,
                            $start_time, $end_time
                        ]);
                        
                        $hasConflict = $stmt->fetchColumn() > 0;
                        
                        if (!$hasConflict) {
                            $available_doctors[] = $doctor;
                        }
                    }

                    // Get available rooms
                    $roomDateExpr = sql_date_cast('start_time');
                    $roomStartExpr = sql_time_cast('start_time');
                    $roomEndExpr = sql_time_cast('end_time');
                    try {
                        $stmt = $pdo->prepare("
                            SELECT r.id, r.room_number, r.room_name, r.room_type
                            FROM rooms r
                            WHERE r.is_available = 1
                            AND r.id NOT IN (
                                SELECT DISTINCT room_id 
                                FROM appointments 
                                WHERE room_id IS NOT NULL
                                AND status != 'cancelled'
                                AND " . $roomDateExpr . " = ?
                                AND (
                                    (" . $roomStartExpr . " >= ? AND " . $roomStartExpr . " < ?)
                                    OR (" . $roomEndExpr . " > ? AND " . $roomEndExpr . " <= ?)
                                    OR (" . $roomStartExpr . " <= ? AND " . $roomEndExpr . " >= ?)
                                )
                            )
                            ORDER BY r.room_number
                        ");
                        $stmt->execute([$date, $start_time, $end_time, $start_time, $end_time, $start_time, $end_time]);
                        $available_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        // Rooms table might not exist or other error
                        $available_rooms = [];
                    }
                    
                    // Generate time slots (every 30 minutes)
                    $time_slots = [];
                    $current = strtotime($date . ' ' . $start_time);
                    $endTime = strtotime($date . ' ' . $end_time);
                    
                    while ($current < $endTime) {
                        $slot_start = date('H:i', $current);
                        $slot_end = date('H:i', strtotime('+30 minutes', $current));
                        $time_slots[] = [
                            'start' => $slot_start,
                            'end' => $slot_end,
                            'label' => $slot_start . ' - ' . $slot_end
                        ];
                        $current = strtotime('+30 minutes', $current);
                    }
                    
                    echo json_encode([
                        'date' => $date,
                        'available_doctors' => $available_doctors,
                        'available_rooms' => $available_rooms,
                        'time_slots' => $time_slots
                    ]);
                    exit;
                } catch (Exception $e) {
                    http_response_code(500);
                    $errorMsg = 'Error checking availability: ' . $e->getMessage();
                    error_log('Availability check error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
                    echo json_encode(['error' => $errorMsg, 'trace' => $e->getTraceAsString()]);
                    exit;
                }
            }
            
            if (isset($_GET['id'])) {
                // Get single appointment with patient and doctor details
                $stmt = $pdo->prepare("
                    SELECT a.*, 
                           p.first_name as patient_first_name, p.last_name as patient_last_name,
                           d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialty,
                           r.room_number, r.room_name, r.room_type
                    FROM appointments a
                    LEFT JOIN patients p ON a.patient_id = p.id
                    LEFT JOIN doctors d ON a.doctor_id = d.id
                    LEFT JOIN rooms r ON a.room_id = r.id
                    WHERE a.id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $appointment = $stmt->fetch();
                echo json_encode($appointment ?: null);
                exit;
            }
            
            // Get all appointments with filtering
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if (isset($_GET['patient_id']) && !empty($_GET['patient_id'])) {
                $whereClause .= " AND a.patient_id = ?";
                $params[] = $_GET['patient_id'];
            }
            
            if (isset($_GET['doctor_id']) && !empty($_GET['doctor_id'])) {
                $whereClause .= " AND a.doctor_id = ?";
                $params[] = $_GET['doctor_id'];
            }
            
            if (isset($_GET['status']) && !empty($_GET['status'])) {
                $whereClause .= " AND a.status = ?";
                $params[] = $_GET['status'];
            }
            
            $startDateExpr = sql_date_cast('a.start_time');
            $endDateExpr = sql_date_cast('a.end_time');

            if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
                $whereClause .= " AND {$startDateExpr} >= ?";
                $params[] = $_GET['date_from'];
            }
            
            if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
                $whereClause .= " AND {$endDateExpr} <= ?";
                $params[] = $_GET['date_to'];
            }
            
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $whereClause .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR a.reason LIKE ?)";
                $searchTerm = '%' . $_GET['search'] . '%';
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            $orderBy = "ORDER BY a.start_time ASC";
            if (isset($_GET['sort'])) {
                $sortField = $_GET['sort'];
                $sortOrder = $_GET['order'] ?? 'ASC';
                if (in_array($sortField, ['start_time', 'status', 'created_at'])) {
                    $orderBy = "ORDER BY a.$sortField $sortOrder";
                }
            }
            
            $stmt = $pdo->prepare("
                SELECT a.*,
                       r.room_number, r.room_name, r.room_type, 
                       p.first_name as patient_first_name, p.last_name as patient_last_name,
                       d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialty
                FROM appointments a
                LEFT JOIN patients p ON a.patient_id = p.id
                LEFT JOIN doctors d ON a.doctor_id = d.id
                LEFT JOIN rooms r ON a.room_id = r.id
                $whereClause
                $orderBy
            ");
            $stmt->execute($params);
            $appointments = $stmt->fetchAll();
            
            echo json_encode($appointments);
            break;
            
        case 'POST':
            if (!is_logged_in()) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            }
            
            if (!can_create_appointments()) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied. You do not have permission to create appointments.']);
                exit;
            }
            
            $data = read_json();
            
            // Validate required fields
            if (empty($data['patient_id']) || empty($data['start_time'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Patient ID and start time are required']);
                exit;
            }
            
            $endTime = $data['end_time'] ?? date('Y-m-d H:i:s', strtotime($data['start_time'] . ' +1 hour'));

            // Check if doctor is scheduled and not on leave
            if (!empty($data['doctor_id'])) {
                if (!isDoctorAvailable($pdo, $data['doctor_id'], $data['start_time'], $endTime)) {
                    http_response_code(409);
                    echo json_encode(['error' => 'The selected doctor is not scheduled to work or is on leave at this time.']);
                    exit;
                }
            }
            
            // Check for conflicts with doctor
            if (!empty($data['doctor_id'])) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count FROM appointments 
                    WHERE doctor_id = ? 
                    AND status != 'cancelled'
                    AND (
                        (start_time < ? AND end_time > ?) OR
                        (start_time < ? AND end_time > ?) OR
                        (start_time >= ? AND end_time <= ?)
                    )
                ");
                $stmt->execute([
                    $data['doctor_id'],
                    $endTime, $data['start_time'],
                    $endTime, $data['start_time'],
                    $data['start_time'], $endTime
                ]);
                $conflict = $stmt->fetch();
                
                if ($conflict['count'] > 0) {
                    http_response_code(409);
                    echo json_encode(['error' => 'Doctor is not available at this time. Please choose a different time or doctor.']);
                    exit;
                }
            }
            
            // Check for conflicts with room
            if (!empty($data['room_id'])) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count FROM appointments 
                    WHERE room_id = ? 
                    AND status != 'cancelled'
                    AND (
                        (start_time < ? AND end_time > ?) OR
                        (start_time < ? AND end_time > ?) OR
                        (start_time >= ? AND end_time <= ?)
                    )
                ");
                $stmt->execute([
                    $data['room_id'],
                    $endTime, $data['start_time'],
                    $endTime, $data['start_time'],
                    $data['start_time'], $endTime
                ]);
                $conflict = $stmt->fetch();
                
                if ($conflict['count'] > 0) {
                    http_response_code(409);
                    echo json_encode(['error' => 'Room is not available at this time. Please choose a different time or room.']);
                    exit;
                }
            }
            
            // Check for conflicts with patient (prevent patient double-booking)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM appointments 
                WHERE patient_id = ? 
                AND status != 'cancelled'
                AND (
                    (start_time < ? AND end_time > ?) OR
                    (start_time < ? AND end_time > ?) OR
                    (start_time >= ? AND end_time <= ?)
                )
            ");
            $stmt->execute([
                $data['patient_id'],
                $endTime, $data['start_time'],
                $endTime, $data['start_time'],
                $data['start_time'], $endTime
            ]);
            $conflict = $stmt->fetch();
            
            if ($conflict['count'] > 0) {
                http_response_code(409);
                echo json_encode(['error' => 'Patient already has an appointment at this time.']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO appointments (
                    patient_id, doctor_id, room_id, start_time, end_time, status, reason, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['patient_id'],
                $data['doctor_id'] ?? null,
                $data['room_id'] ?? null,
                $data['start_time'],
                $endTime,
                $data['status'] ?? 'scheduled',
                $data['reason'] ?? null,
                $_SESSION['user_id']
            ]);
            
            $id = $pdo->lastInsertId();
            
            // Update room availability if room is assigned
            if (!empty($data['room_id'])) {
                updateRoomAvailability($pdo, $data['room_id'], $data['start_time'], $data['status'] ?? 'scheduled');
            }
            
            log_audit_trail('create_appointment', 'appointment', $id, $data);
            
            // Return appointment with patient and doctor details
            $stmt = $pdo->prepare("
                SELECT a.*, 
                       p.first_name as patient_first_name, p.last_name as patient_last_name,
                       d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialty,
                       r.room_number, r.room_name, r.room_type
                FROM appointments a
                LEFT JOIN patients p ON a.patient_id = p.id
                LEFT JOIN doctors d ON a.doctor_id = d.id
                LEFT JOIN rooms r ON a.room_id = r.id
                WHERE a.id = ?
            ");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch());
            break;
            
        case 'PUT':
            if (!is_logged_in()) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            }
            
            parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
            $id = $qs['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing id']);
                exit;
            }
            
            $data = read_json();
            
            // Get current appointment data
            $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch();
            
            if (!$current) {
                http_response_code(404);
                echo json_encode(['error' => 'Appointment not found']);
                exit;
            }
            
            // Prepare values for conflict checking
            $checkDoctorId = isset($data['doctor_id']) ? $data['doctor_id'] : $current['doctor_id'];
            $checkRoomId = isset($data['room_id']) ? $data['room_id'] : $current['room_id'];
            $checkPatientId = isset($data['patient_id']) ? $data['patient_id'] : $current['patient_id'];
            $checkStartTime = isset($data['start_time']) ? $data['start_time'] : $current['start_time'];
            $checkEndTime = isset($data['end_time']) ? $data['end_time'] : $current['end_time'];
            
            // Only check conflicts if time/doctor/room/patient is being changed
            $timeChanged = isset($data['start_time']) || isset($data['end_time']);
            $doctorChanged = isset($data['doctor_id']) && $data['doctor_id'] != $current['doctor_id'];
            $roomChanged = isset($data['room_id']) && $data['room_id'] != $current['room_id'];
            $patientChanged = isset($data['patient_id']) && $data['patient_id'] != $current['patient_id'];
            
            if ($timeChanged || $doctorChanged || $roomChanged || $patientChanged) {
                // Check if doctor is scheduled and not on leave
                if (!empty($checkDoctorId)) {
                    if (!isDoctorAvailable($pdo, $checkDoctorId, $checkStartTime, $checkEndTime)) {
                        http_response_code(409);
                        echo json_encode(['error' => 'The selected doctor is not scheduled to work or is on leave at this time.']);
                        exit;
                    }
                }

                // Check for conflicts with doctor
                if (!empty($checkDoctorId)) {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as count FROM appointments 
                        WHERE doctor_id = ? 
                        AND id != ?
                        AND status != 'cancelled'
                        AND (
                            (start_time < ? AND end_time > ?) OR
                            (start_time < ? AND end_time > ?) OR
                            (start_time >= ? AND end_time <= ?)
                        )
                    ");
                    $stmt->execute([
                        $checkDoctorId,
                        $id,
                        $checkEndTime, $checkStartTime,
                        $checkEndTime, $checkStartTime,
                        $checkStartTime, $checkEndTime
                    ]);
                    $conflict = $stmt->fetch();
                    
                    if ($conflict['count'] > 0) {
                        http_response_code(409);
                        echo json_encode(['error' => 'Doctor is not available at this time. Please choose a different time or doctor.']);
                        exit;
                    }
                }
                
                // Check for conflicts with room
                if (!empty($checkRoomId)) {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as count FROM appointments 
                        WHERE room_id = ? 
                        AND id != ?
                        AND status != 'cancelled'
                        AND (
                            (start_time < ? AND end_time > ?) OR
                            (start_time < ? AND end_time > ?) OR
                            (start_time >= ? AND end_time <= ?)
                        )
                    ");
                    $stmt->execute([
                        $checkRoomId,
                        $id,
                        $checkEndTime, $checkStartTime,
                        $checkEndTime, $checkStartTime,
                        $checkStartTime, $checkEndTime
                    ]);
                    $conflict = $stmt->fetch();
                    
                    if ($conflict['count'] > 0) {
                        http_response_code(409);
                        echo json_encode(['error' => 'Room is not available at this time. Please choose a different time or room.']);
                        exit;
                    }
                }
                
                // Check for conflicts with patient
                if (!empty($checkPatientId)) {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as count FROM appointments 
                        WHERE patient_id = ? 
                        AND id != ?
                        AND status != 'cancelled'
                        AND (
                            (start_time < ? AND end_time > ?) OR
                            (start_time < ? AND end_time > ?) OR
                            (start_time >= ? AND end_time <= ?)
                        )
                    ");
                    $stmt->execute([
                        $checkPatientId,
                        $id,
                        $checkEndTime, $checkStartTime,
                        $checkEndTime, $checkStartTime,
                        $checkStartTime, $checkEndTime
                    ]);
                    $conflict = $stmt->fetch();
                    
                    if ($conflict['count'] > 0) {
                        http_response_code(409);
                        echo json_encode(['error' => 'Patient already has an appointment at this time.']);
                        exit;
                    }
                }
            }
            
            // Build dynamic UPDATE query based on provided fields
            $updates = [];
            $params = [];
            
            if (isset($data['patient_id'])) {
                $updates[] = 'patient_id=?';
                $params[] = $data['patient_id'];
            }
            if (isset($data['doctor_id'])) {
                $updates[] = 'doctor_id=?';
                $params[] = $data['doctor_id'];
            }
            if (isset($data['room_id'])) {
                $updates[] = 'room_id=?';
                $params[] = $data['room_id'];
            }
            if (isset($data['start_time'])) {
                $updates[] = 'start_time=?';
                $params[] = $data['start_time'];
            }
            if (isset($data['end_time'])) {
                $updates[] = 'end_time=?';
                $params[] = $data['end_time'];
            }
            if (isset($data['status'])) {
                $updates[] = 'status=?';
                $params[] = $data['status'];
            }
            if (isset($data['reason'])) {
                $updates[] = 'reason=?';
                $params[] = $data['reason'];
            }
            if (isset($data['diagnosis'])) {
                $updates[] = 'diagnosis=?';
                $params[] = $data['diagnosis'];
            }
            if (isset($data['treatment'])) {
                $updates[] = 'treatment=?';
                $params[] = $data['treatment'];
            }
            if (isset($data['prescription'])) {
                $updates[] = 'prescription=?';
                $params[] = $data['prescription'];
            }
            
            $updates[] = 'updated_at=CURRENT_TIMESTAMP';
            $params[] = $id;
            
            $sql = 'UPDATE appointments SET ' . implode(', ', $updates) . ' WHERE id=?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Get new state for audit log
            $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
            $stmt->execute([$id]);
            $after = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Handle room availability updates
            $oldRoomId = $current['room_id'] ?? null;
            $newRoomId = $after['room_id'] ?? null;
            $newStatus = $after['status'] ?? $current['status'];
            $newStartTime = $after['start_time'] ?? $current['start_time'];
            
            // If room was changed, free the old room and occupy the new one
            if ($roomChanged && $oldRoomId) {
                checkAndFreeRoom($pdo, $oldRoomId);
            }
            
            // Update room availability for the new/current room
            if ($newRoomId) {
                updateRoomAvailability($pdo, $newRoomId, $newStartTime, $newStatus);
            } elseif ($oldRoomId && !$newRoomId) {
                // Room was removed from appointment, free the old room
                checkAndFreeRoom($pdo, $oldRoomId);
            } elseif (isset($data['status']) && $data['status'] === 'cancelled' && $oldRoomId) {
                // Appointment was cancelled, free the room
                checkAndFreeRoom($pdo, $oldRoomId);
            } elseif (isset($data['status']) && $oldRoomId && $newStatus !== 'cancelled' && $current['status'] === 'cancelled') {
                // Appointment was uncancelled, occupy the room if in future
                updateRoomAvailability($pdo, $oldRoomId, $newStartTime, $newStatus);
            }

            log_audit_trail('update_appointment', 'appointment', $id, ['before' => $current, 'after' => $after]);

            // Return updated appointment with details
            $stmt = $pdo->prepare("
                SELECT a.*, 
                       p.first_name as patient_first_name, p.last_name as patient_last_name,
                       d.first_name as doctor_first_name, d.last_name as doctor_last_name, d.specialty,
                       r.room_number, r.room_name, r.room_type
                FROM appointments a
                LEFT JOIN patients p ON a.patient_id = p.id
                LEFT JOIN doctors d ON a.doctor_id = d.id
                LEFT JOIN rooms r ON a.room_id = r.id
                WHERE a.id = ?
            ");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch());
            break;
            
        case 'DELETE':
            if (!is_logged_in()) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            }
            
            parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
            $id = $qs['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing id']);
                exit;
            }
            
            // Get appointment details before cancelling
            $stmt = $pdo->prepare("SELECT room_id FROM appointments WHERE id = ?");
            $stmt->execute([$id]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            log_audit_trail('cancel_appointment', 'appointment', $id);

            // Soft delete - mark as cancelled
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);
            
            // Free the room if one was assigned
            if ($appointment && !empty($appointment['room_id'])) {
                checkAndFreeRoom($pdo, $appointment['room_id']);
            }
            
            echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
