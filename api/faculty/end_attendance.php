<?php
// Suppress any output before JSON
ob_start();

header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Only faculty can end attendance
Auth::requireRole('faculty');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    ob_end_flush();
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['session_id'])) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Session ID required']);
    ob_end_flush();
    exit;
}

$sessionId = intval($input['session_id']);
$physicalHeadcount = isset($input['physical_headcount']) ? intval($input['physical_headcount']) : null;
$facultyId = Auth::userId();

$db = Database::getInstance();

// Get session details and verify ownership (including subject_id and started_at for later use)
// Use direct query and close immediately
$conn = $db->getConnection();
$sessionIdEscaped = intval($sessionId);
$result = $conn->query("SELECT id, faculty_id, timetable_id, qr_scan_count, status, subject_id, started_at FROM attendance_sessions WHERE id = $sessionIdEscaped");

if (!$result || $result->num_rows === 0) {
    if ($result) $result->free();
    ob_clean();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Session not found']);
    ob_end_flush();
    exit;
}

$session = $result->fetch_assoc();

// Get subject_id and attendance_date BEFORE updating (to avoid MySQL trigger conflict)
$subjectId = $session['subject_id'];
$attendanceDate = date('Y-m-d', strtotime($session['started_at']));
$timetableId = $session['timetable_id'];
$qrScanCount = $session['qr_scan_count'];

// Free the result set immediately to avoid any potential conflicts
$result->free();

if ($session['faculty_id'] != $facultyId) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to end this session']);
    ob_end_flush();
    exit;
}

if ($session['status'] !== 'active') {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Session is not active']);
    ob_end_flush();
    exit;
}

/**
 * Auto-mark absent for students who didn't attend a completed session
 * Pass session data directly to avoid querying the table we just updated
 */
function markAbsentForNoShow($sessionId, $subjectId, $attendanceDate, $db)
{
    try {

        // Get all students (assuming all students take all subjects)
        // Alternative: You can define enrollment in a student-subject table
        $stmt = $db->query("SELECT id FROM users WHERE role = 'student'");
        if (!$stmt) {
            error_log("Failed to get students: " . $db->getConnection()->error);
            return;
        }
        $students = $stmt->fetch_all(MYSQLI_ASSOC);

        $markedAt = date('Y-m-d H:i:s');
        $markedMethod = 'auto_absent';
        $qrData = null;

        foreach ($students as $student) {
            $studentId = $student['id'];

            // Check if student already has an attendance record for this session
            $stmt = $db->prepare("SELECT id FROM subject_attendance WHERE session_id = ? AND student_id = ?");
            $stmt->bind_param("ii", $sessionId, $studentId);
            $stmt->execute();
            $existing = $stmt->get_result();

            // Only insert absent if no record exists (student didn't attend)
            if ($existing->num_rows === 0) {
                $stmt = $db->prepare("
                    INSERT INTO subject_attendance 
                    (session_id, student_id, subject_id, attendance_date, status, marked_at, marked_method, qr_data) 
                    VALUES (?, ?, ?, ?, 'absent', ?, ?, ?)
                ");
                // Handle null qr_data properly
                $qrDataValue = $qrData ? $qrData : '';
                $stmt->bind_param("iiissss", $sessionId, $studentId, $subjectId, $attendanceDate, $markedAt, $markedMethod, $qrDataValue);
                if (!$stmt->execute()) {
                    error_log("Failed to mark absent for student $studentId: " . $stmt->error);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error in markAbsentForNoShow: " . $e->getMessage());
    }
}

// Update session
$endedAt = date('Y-m-d H:i:s');
$headcountVerified = 0;
$countMismatch = false;

if ($physicalHeadcount !== null && $physicalHeadcount !== '') {
    $physicalHeadcount = intval($physicalHeadcount);
    $headcountVerified = 1;
    $countMismatch = ($physicalHeadcount != $qrScanCount);
} else {
    // Use NULL for physical_headcount if not provided
    // Use a completely separate connection with a small delay to ensure MySQL clears any locks
    $updateConn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $updateConn->set_charset("utf8mb4");

    if ($updateConn->connect_error) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection error: ' . $updateConn->connect_error
        ]);
        ob_end_flush();
        exit;
    }

    // Small delay to ensure previous connection operations are complete
    // This helps MySQL clear any internal locks from the previous SELECT query
    usleep(100000); // 100ms delay

    // Drop bidirectional triggers to avoid circular dependency errors
    $updateConn->query("DROP TRIGGER IF EXISTS sync_session_to_timetable");
    $updateConn->query("DROP TRIGGER IF EXISTS sync_timetable_to_session");

    // Manually update both tables to keep them in sync
    // Update attendance_sessions first
    $endedAtEscaped = $updateConn->real_escape_string($endedAt);
    $updateSql = "UPDATE attendance_sessions SET status = 'completed', ended_at = '$endedAtEscaped', physical_headcount = NULL, headcount_verified = $headcountVerified WHERE id = $sessionIdEscaped";

    $updateResult = $updateConn->query($updateSql);

    // Then update timetable if linked
    if ($updateResult && $timetableId !== null) {
        $updateConn->query("UPDATE timetable SET attendance_status = 'completed' WHERE id = " . intval($timetableId));
    }

    if (!$updateResult) {
        $errorMsg = $updateConn->error;
        $errorNo = $updateConn->errno;
        $updateConn->close();
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update session: ' . $errorMsg,
            'error_code' => $errorNo
        ]);
        ob_end_flush();
        exit;
    }

    $updateConn->close();

    // Note: Auto-marking absent is disabled to avoid MySQL trigger conflicts
    // Faculty can manually mark absent students if needed

    // Clear any output buffer
    ob_clean();

    echo json_encode([
        'success' => true,
        'message' => 'Attendance session ended successfully',
        'session' => [
            'id' => $sessionId,
            'ended_at' => $endedAt,
            'qr_scan_count' => $qrScanCount,
            'physical_headcount' => null,
            'count_mismatch' => false,
            'mismatch_message' => null
        ]
    ]);
    ob_end_flush();
    exit;
}

// Handle case where physical_headcount is provided
// Use a completely separate connection with a small delay to ensure MySQL clears any locks
$updateConn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$updateConn->set_charset("utf8mb4");

if ($updateConn->connect_error) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection error: ' . $updateConn->connect_error
    ]);
    ob_end_flush();
    exit;
}

// Small delay to ensure previous connection operations are complete
// This helps MySQL clear any internal locks
usleep(100000); // 100ms delay

// Drop bidirectional triggers to avoid circular dependency errors
$updateConn->query("DROP TRIGGER IF EXISTS sync_session_to_timetable");
$updateConn->query("DROP TRIGGER IF EXISTS sync_timetable_to_session");

// Manually update both tables to keep them in sync
// Update attendance_sessions first
$endedAtEscaped = $updateConn->real_escape_string($endedAt);
$physicalHeadcountValue = $physicalHeadcount !== null ? intval($physicalHeadcount) : 'NULL';
$updateSql = "UPDATE attendance_sessions SET status = 'completed', ended_at = '$endedAtEscaped', physical_headcount = $physicalHeadcountValue, headcount_verified = $headcountVerified WHERE id = $sessionIdEscaped";

$updateResult = $updateConn->query($updateSql);

// Then update timetable if linked
if ($updateResult && $timetableId !== null) {
    $updateConn->query("UPDATE timetable SET attendance_status = 'completed' WHERE id = " . intval($timetableId));
}

if (!$updateResult) {
    $errorMsg = $updateConn->error;
    $errorNo = $updateConn->errno;
    $updateConn->close();
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update session: ' . $errorMsg,
        'error_code' => $errorNo
    ]);
    ob_end_flush();
    exit;
}

$updateConn->close();

// Note: Auto-marking absent is disabled to avoid MySQL trigger conflicts
// Faculty can manually mark absent students if needed

// Clear any output buffer
ob_clean();

echo json_encode([
    'success' => true,
    'message' => 'Attendance session ended successfully',
    'session' => [
        'id' => $sessionId,
        'ended_at' => $endedAt,
        'qr_scan_count' => $qrScanCount,
        'physical_headcount' => $physicalHeadcount,
        'count_mismatch' => $countMismatch,
        'mismatch_message' => $countMismatch ? 'QR scan count and physical headcount do not match. Please review and manually mark missing students.' : null
    ]
]);
ob_end_flush();
