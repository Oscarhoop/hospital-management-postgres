<?php
/**
 * Staff Scheduling API
 * Handles shift management, leave requests, and schedule operations
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

session_start();

require_once __DIR__ . '/../db.php';

try {
    $db = get_pdo();
    $driver = get_db_driver();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
$method = $_SERVER['REQUEST_METHOD'];

// Helper function to check if user is logged in
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

// Helper function to check conflicts
function checkScheduleConflict($db, $userId, $scheduleDate, $startTime, $endTime, $excludeId = null) {
    $sql = "SELECT COUNT(*) as count FROM staff_schedules 
            WHERE user_id = :user_id 
            AND schedule_date = :schedule_date 
            AND status != 'cancelled'
            AND (
                (start_time <= :start_time AND end_time > :start_time)
                OR (start_time < :end_time AND end_time >= :end_time)
                OR (start_time >= :start_time AND end_time <= :end_time)
            )";
    
    if ($excludeId) {
        $sql .= " AND id != :exclude_id";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':schedule_date', $scheduleDate);
    $stmt->bindParam(':start_time', $startTime);
    $stmt->bindParam(':end_time', $endTime);
    if ($excludeId) {
        $stmt->bindParam(':exclude_id', $excludeId);
    }
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
}

// Use the audit trail function from audit.php
// Import it if not already loaded
if (!function_exists('log_audit_trail')) {
    require_once __DIR__ . '/audit.php';
}

// Wrapper to maintain backward compatibility with existing calls in this file
// The old signature was: log_audit_trail($db, $action, $type, $id, $data)
// The new signature is: log_audit_trail($action, $target_type, $target_id, $details, $pdo)
// This wrapper allows us to keep existing calls working
if (!function_exists('log_audit_trail_old')) {
    function log_audit_trail_old($db, $action, $type, $id, $data = null) {
        // Call the standard function with correct parameter order
        log_audit_trail($action, $type, $id, $data, $db);
    }
}

// Check if required tables exist
function checkTablesExist($db, $driver) {
    $tables = ['staff_schedules', 'users', 'shift_templates'];
    foreach ($tables as $table) {
        if ($driver === 'sqlite') {
            $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :table");
            $stmt->execute([':table' => $table]);
            if (!$stmt->fetch()) {
                return false;
            }
        } else {
            $stmt = $db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = :table");
            $stmt->execute([':table' => $table]);
            if (!$stmt->fetchColumn()) {
                return false;
            }
        }
    }
    return true;
}

try {
    // Check if scheduling tables exist
    if (!checkTablesExist($db, $driver)) {
        http_response_code(503);
        echo json_encode(['error' => 'Scheduling tables not found. Please run the database setup script: backend/setup_scheduling_tables.php']);
        exit;
    }
    
    switch ($method) {
        case 'GET':
            requireAuth();
            $action = $_GET['action'] ?? 'list';
            
            if (isset($_GET['view']) && $_GET['view'] === 'calendar') {
                // Fetch all schedules for the calendar view
                $stmt = $db->query("
                    SELECT ss.schedule_date, ss.start_time, ss.end_time, u.name as user_name, st.name as shift_name
                    FROM staff_schedules ss
                    JOIN users u ON ss.user_id = u.id
                    LEFT JOIN shift_templates st ON ss.shift_template_id = st.id
                    WHERE ss.status != 'cancelled'
                ");
                $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $events = [];
                foreach ($schedules as $schedule) {
                    $events[] = [
                        'title' => $schedule['user_name'] . ' - ' . ($schedule['shift_name'] ?? 'Shift'),
                        'start' => $schedule['schedule_date'] . 'T' . $schedule['start_time'],
                        'end' => $schedule['schedule_date'] . 'T' . $schedule['end_time'],
                        'description' => $schedule['user_name'] . ' is scheduled for ' . ($schedule['shift_name'] ?? 'a shift') . ' from ' . $schedule['start_time'] . ' to ' . $schedule['end_time'],
                    ];
                }
                echo json_encode($events);
                exit;
            }
            
            if ($action === 'list' && isset($_GET['id'])) {
                // Get single schedule with detailed information
                log_audit_trail_old($db, 'view_schedule', 'schedule', $_GET['id'], ['filters' => $_GET]);
                $stmt = $db->prepare("
                    SELECT ss.*, 
                           u.name as user_name, u.role, u.email as user_email, u.phone as user_phone,
                           st.name as shift_name, st.color,
                           c.name as created_by_name
                    FROM staff_schedules ss
                    JOIN users u ON ss.user_id = u.id
                    LEFT JOIN shift_templates st ON ss.shift_template_id = st.id
                    LEFT JOIN users c ON ss.created_by = c.id
                    WHERE ss.id = :id
                ");
                $stmt->bindParam(':id', $_GET['id']);
                $stmt->execute();
                $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($schedule) {
                    // Get related appointments - try to match by user email/name with doctor email/name
                    // First, get the user's email and name
                    $userStmt = $db->prepare("SELECT email, name FROM users WHERE id = ?");
                    $userStmt->execute([$schedule['user_id']]);
                    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Get appointments for this schedule time period
                    // Match doctors by email or name if available
                    $apptStmt = $db->prepare("
                        SELECT a.*, 
                               p.first_name as patient_first_name, p.last_name as patient_last_name,
                               p.phone as patient_phone, p.email as patient_email,
                               d.first_name as doctor_first_name, d.last_name as doctor_last_name,
                               d.specialty as doctor_specialty, d.email as doctor_email,
                               r.room_number, r.room_name
                        FROM appointments a
                        LEFT JOIN patients p ON a.patient_id = p.id
                        LEFT JOIN doctors d ON a.doctor_id = d.id
                        LEFT JOIN rooms r ON a.room_id = r.id
                        WHERE a.start_time::date = :schedule_date
                        AND a.start_time::time >= :start_time 
                        AND a.start_time::time < :end_time
                        AND a.status != 'cancelled'
                        AND (
                            -- Match by doctor email if user email matches
                            (d.email IS NOT NULL AND d.email = :user_email)
                            OR
                            -- Match by doctor name if user name matches
                            (d.first_name || ' ' || d.last_name LIKE :user_name_pattern)
                            OR
                            -- If no doctor assigned, show all appointments in this time slot
                            (a.doctor_id IS NULL)
                        )
                        ORDER BY a.start_time
                    ");
                    $userNamePattern = $user ? '%' . $user['name'] . '%' : '';
                    $apptStmt->bindValue(':schedule_date', $schedule['schedule_date']);
                    $apptStmt->bindValue(':start_time', $schedule['start_time']);
                    $apptStmt->bindValue(':end_time', $schedule['end_time']);
                    $apptStmt->bindValue(':user_email', $user['email'] ?? '');
                    $apptStmt->bindValue(':user_name_pattern', $userNamePattern);
                    $apptStmt->execute();
                    $schedule['appointments'] = $apptStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get related billing for these appointments
                    if (!empty($schedule['appointments'])) {
                        $appointmentIds = array_column($schedule['appointments'], 'id');
                        $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
                        $billingStmt = $db->prepare("
                            SELECT b.*, 
                                   p.first_name as patient_first_name, p.last_name as patient_last_name
                            FROM billing b
                            LEFT JOIN patients p ON b.patient_id = p.id
                            WHERE b.appointment_id IN ($placeholders)
                            ORDER BY b.created_at DESC
                        ");
                        $billingStmt->execute($appointmentIds);
                        $schedule['billing'] = $billingStmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $schedule['billing'] = [];
                    }
                }
                
                echo json_encode($schedule);
                
            } elseif ($action === 'list') {
                // List schedules with filters and related information
                log_audit_trail_old($db, 'list_schedules', 'schedule', null, ['filters' => $_GET]);

                $patientNamesSubquery = $driver === 'pgsql'
                    ? "(SELECT STRING_AGG(p.first_name || ' ' || p.last_name, ', ' ORDER BY a.start_time)
                           FROM appointments a
                           JOIN patients p ON a.patient_id = p.id
                           LEFT JOIN doctors d ON a.doctor_id = d.id
                           LEFT JOIN users u2 ON (d.email = u2.email OR (d.first_name || ' ' || d.last_name) LIKE '%' || u2.name || '%')
                           WHERE u2.id = ss.user_id
                           AND a.start_time::date = ss.schedule_date
                           AND a.start_time::time >= ss.start_time
                           AND a.start_time::time < ss.end_time
                           AND a.status != 'cancelled') as patient_names"
                    : "(SELECT GROUP_CONCAT(p.first_name || ' ' || p.last_name)
                           FROM appointments a
                           JOIN patients p ON a.patient_id = p.id
                           LEFT JOIN doctors d ON a.doctor_id = d.id
                           LEFT JOIN users u2 ON (d.email = u2.email OR (d.first_name || ' ' || d.last_name) LIKE '%' || u2.name || '%')
                           WHERE u2.id = ss.user_id
                           AND DATE(a.start_time) = ss.schedule_date
                           AND TIME(a.start_time) >= ss.start_time
                           AND TIME(a.start_time) < ss.end_time
                           AND a.status != 'cancelled'
                           LIMIT 3) as patient_names";

                $sql = "SELECT ss.*, 
                               u.name as user_name, u.role, u.email as user_email, u.phone as user_phone,
                               st.name as shift_name, st.color,
                               c.name as created_by_name,
                               (SELECT COUNT(*) FROM appointments a 
                                LEFT JOIN doctors d ON a.doctor_id = d.id
                                LEFT JOIN users u2 ON (d.email = u2.email OR (d.first_name || ' ' || d.last_name) LIKE '%' || u2.name || '%')
                                WHERE u2.id = ss.user_id
                                AND a.start_time::date = ss.schedule_date
                                AND a.start_time::time >= ss.start_time 
                                AND a.start_time::time < ss.end_time
                                AND a.status != 'cancelled') as appointment_count,
                               {$patientNamesSubquery}
                        FROM staff_schedules ss
                        JOIN users u ON ss.user_id = u.id
                        LEFT JOIN shift_templates st ON ss.shift_template_id = st.id
                        LEFT JOIN users c ON ss.created_by = c.id
                        WHERE 1=1";
                
                $params = [];
                
                if (isset($_GET['user_id'])) {
                    $sql .= " AND ss.user_id = :user_id";
                    $params[':user_id'] = $_GET['user_id'];
                }
                
                if (isset($_GET['date_from'])) {
                    $sql .= " AND ss.schedule_date >= :date_from";
                    $params[':date_from'] = $_GET['date_from'];
                }
                
                if (isset($_GET['date_to'])) {
                    $sql .= " AND ss.schedule_date <= :date_to";
                    $params[':date_to'] = $_GET['date_to'];
                }
                
                if (isset($_GET['status'])) {
                    $sql .= " AND ss.status = :status";
                    $params[':status'] = $_GET['status'];
                }
                
                $sql .= " ORDER BY ss.schedule_date ASC, ss.start_time ASC";
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                
            } elseif ($action === 'templates') {
                // Get shift templates
                try {
                    $stmt = $db->query("SELECT * FROM shift_templates WHERE is_active = 1 ORDER BY start_time");
                    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                } catch (Exception $e) {
                    // Table might not exist, return empty array
                    echo json_encode([]);
                }
                
            } elseif ($action === 'leave_requests') {
                // Get leave requests
                log_audit_trail_old($db, 'list_leave_requests', 'leave_request', null, ['filters' => $_GET]);
                $sql = "SELECT lr.*, u.name as user_name, u.role, a.name as approved_by_name
                        FROM leave_requests lr
                        JOIN users u ON lr.user_id = u.id
                        LEFT JOIN users a ON lr.approved_by = a.id
                        WHERE 1=1";
                
                $params = [];
                
                if (isset($_GET['user_id'])) {
                    $sql .= " AND lr.user_id = :user_id";
                    $params[':user_id'] = $_GET['user_id'];
                }
                
                if (isset($_GET['status'])) {
                    $sql .= " AND lr.status = :status";
                    $params[':status'] = $_GET['status'];
                }
                
                $sql .= " ORDER BY lr.created_at DESC";
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                
            } elseif ($action === 'shift_swaps') {
                // Get shift swap requests
                log_audit_trail_old($db, 'list_shift_swaps', 'shift_swap', null, ['filters' => $_GET]);
                $stmt = $db->query("
                    SELECT ss.*, 
                           ru.name as requesting_user_name, 
                           tu.name as target_user_name,
                           au.name as approved_by_name
                    FROM shift_swaps ss
                    JOIN users ru ON ss.requesting_user_id = ru.id
                    JOIN users tu ON ss.target_user_id = tu.id
                    LEFT JOIN users au ON ss.approved_by = au.id
                    ORDER BY ss.created_at DESC
                ");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            break;
            
        case 'POST':
            requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? 'create';
            
            if ($action === 'create') {
                // Create new schedule
                // Validate required fields
                if (!isset($data['user_id']) || !isset($data['schedule_date']) || !isset($data['start_time']) || !isset($data['end_time'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing required fields: user_id, schedule_date, start_time, end_time']);
                    exit;
                }
                
                // Check for conflicts
                if (checkScheduleConflict($db, $data['user_id'], $data['schedule_date'], $data['start_time'], $data['end_time'])) {
                    http_response_code(409);
                    echo json_encode(['error' => 'Schedule conflict detected for this staff member']);
                    exit;
                }
                
                $stmt = $db->prepare("
                    INSERT INTO staff_schedules (user_id, shift_template_id, schedule_date, start_time, end_time, status, notes, created_by)
                    VALUES (:user_id, :shift_template_id, :schedule_date, :start_time, :end_time, :status, :notes, :created_by)
                ");
                
                $status = $data['status'] ?? 'scheduled';
                $shiftTemplateId = !empty($data['shift_template_id']) ? $data['shift_template_id'] : null;
                $notes = !empty($data['notes']) ? $data['notes'] : null;
                
                $stmt->bindValue(':user_id', $data['user_id'], PDO::PARAM_INT);
                $stmt->bindValue(':shift_template_id', $shiftTemplateId, $shiftTemplateId ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmt->bindValue(':schedule_date', $data['schedule_date']);
                $stmt->bindValue(':start_time', $data['start_time']);
                $stmt->bindValue(':end_time', $data['end_time']);
                $stmt->bindValue(':status', $status);
                $stmt->bindValue(':notes', $notes, $notes ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':created_by', $_SESSION['user_id'], PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $newId = $db->lastInsertId();
                    echo json_encode(['id' => $newId, 'message' => 'Schedule created successfully']);
                    log_audit_trail_old($db, 'create_schedule', 'schedule', $newId, $data);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to create schedule']);
                }
                
            } elseif ($action === 'leave_request') {
                // Create leave request
                // Validate required fields
                if (!isset($data['user_id']) || !isset($data['leave_type']) || !isset($data['start_date']) || !isset($data['end_date'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing required fields: user_id, leave_type, start_date, end_date']);
                    exit;
                }
                
                $stmt = $db->prepare("
                    INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason, status)
                    VALUES (:user_id, :leave_type, :start_date, :end_date, :reason, 'pending')
                ");
                
                $reason = !empty($data['reason']) ? $data['reason'] : null;
                
                $stmt->bindValue(':user_id', $data['user_id'], PDO::PARAM_INT);
                $stmt->bindValue(':leave_type', $data['leave_type']);
                $stmt->bindValue(':start_date', $data['start_date']);
                $stmt->bindValue(':end_date', $data['end_date']);
                $stmt->bindValue(':reason', $reason, $reason ? PDO::PARAM_STR : PDO::PARAM_NULL);
                
                if ($stmt->execute()) {
                    $newId = $db->lastInsertId();
                    echo json_encode(['id' => $newId, 'message' => 'Leave request submitted successfully']);
                    log_audit_trail_old($db, 'create_leave_request', 'leave_request', $newId, $data);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to submit leave request']);
                }
            }
            break;
            
        case 'PUT':
            requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID required']);
                exit;
            }
            
            $action = $data['action'] ?? 'update';
            
            if ($action === 'update') {
                // Get existing schedule to check user_id if not provided
                $stmt = $db->prepare("SELECT user_id FROM staff_schedules WHERE id = :id");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$existing) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Schedule not found']);
                    exit;
                }
                
                $userId = $data['user_id'] ?? $existing['user_id'];
                
                // Check for conflicts if time/date is being updated
                if (isset($data['schedule_date']) && isset($data['start_time']) && isset($data['end_time'])) {
                    if (checkScheduleConflict($db, $userId, $data['schedule_date'], $data['start_time'], $data['end_time'], $id)) {
                        http_response_code(409);
                        echo json_encode(['error' => 'Schedule conflict detected']);
                        exit;
                    }
                }
                
                // Build dynamic UPDATE query based on provided fields
                $updateFields = [];
                $params = [':id' => $id];
                
                if (isset($data['user_id'])) {
                    $updateFields[] = 'user_id = :user_id';
                    $params[':user_id'] = $data['user_id'];
                }
                if (isset($data['shift_template_id'])) {
                    $updateFields[] = 'shift_template_id = :shift_template_id';
                    $params[':shift_template_id'] = !empty($data['shift_template_id']) ? $data['shift_template_id'] : null;
                }
                if (isset($data['schedule_date'])) {
                    $updateFields[] = 'schedule_date = :schedule_date';
                    $params[':schedule_date'] = $data['schedule_date'];
                }
                if (isset($data['start_time'])) {
                    $updateFields[] = 'start_time = :start_time';
                    $params[':start_time'] = $data['start_time'];
                }
                if (isset($data['end_time'])) {
                    $updateFields[] = 'end_time = :end_time';
                    $params[':end_time'] = $data['end_time'];
                }
                if (isset($data['status'])) {
                    $updateFields[] = 'status = :status';
                    $params[':status'] = $data['status'];
                }
                if (isset($data['notes'])) {
                    $updateFields[] = 'notes = :notes';
                    $params[':notes'] = !empty($data['notes']) ? $data['notes'] : null;
                }
                
                if (empty($updateFields)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'No fields to update']);
                    exit;
                }
                
                $updateFields[] = 'updated_at = CURRENT_TIMESTAMP';
                
                $sql = "UPDATE staff_schedules SET " . implode(', ', $updateFields) . " WHERE id = :id";
                $stmt = $db->prepare($sql);
                
                foreach ($params as $key => $value) {
                    if ($key === ':id') {
                        $stmt->bindValue($key, $value, PDO::PARAM_INT);
                    } elseif ($key === ':user_id' || $key === ':shift_template_id') {
                        $stmt->bindValue($key, $value, $value !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                    } elseif ($key === ':notes') {
                        $stmt->bindValue($key, $value, $value !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue($key, $value);
                    }
                }
                
                if ($stmt->execute()) {
                    echo json_encode(['message' => 'Schedule updated successfully']);
                    log_audit_trail_old($db, 'update_schedule', 'schedule', $id, $data);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to update schedule']);
                }
                
            } elseif ($action === 'approve_leave') {
                // Approve/reject leave request
                if (!isset($data['status'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Status is required']);
                    exit;
                }
                
                // Check if leave request exists
                $checkStmt = $db->prepare("SELECT id FROM leave_requests WHERE id = :id");
                $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
                $checkStmt->execute();
                if (!$checkStmt->fetch()) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Leave request not found']);
                    exit;
                }
                
                $stmt = $db->prepare("
                    UPDATE leave_requests 
                    SET status = :status,
                        approved_by = :approved_by,
                        approved_at = CURRENT_TIMESTAMP,
                        rejection_reason = :rejection_reason
                    WHERE id = :id
                ");
                
                $rejectionReason = !empty($data['rejection_reason']) ? $data['rejection_reason'] : null;
                
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->bindValue(':status', $data['status']);
                $stmt->bindValue(':approved_by', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt->bindValue(':rejection_reason', $rejectionReason, $rejectionReason ? PDO::PARAM_STR : PDO::PARAM_NULL);
                
                if ($stmt->execute()) {
                    if ($stmt->rowCount() > 0) {
                        echo json_encode(['message' => 'Leave request ' . $data['status']]);
                        log_audit_trail_old($db, 'update_leave_request', 'leave_request', $id, $data);
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Failed to update leave request']);
                    }
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to update leave request']);
                }
            }
            break;
            
        case 'DELETE':
            requireAuth();
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID required']);
                exit;
            }
            
            // Soft delete - set status to cancelled
            $stmt = $db->prepare("UPDATE staff_schedules SET status = 'cancelled' WHERE id = :id");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['message' => 'Schedule cancelled successfully']);
                    log_audit_trail_old($db, 'cancel_schedule', 'schedule', $id);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Schedule not found']);
                }
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to cancel schedule']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log('PDO Exception in schedules.php: ' . $e->getMessage()); // Log the error
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
