<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';

// Only faculty can manually mark attendance
Auth::requireRole('faculty');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['session_id']) || !isset($input['student_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Session ID, student ID, and status required']);
    exit;
}

$sessionId = intval($input['session_id']);
$studentId = intval($input['student_id']);
$status = $input['status'];
$reason = $input['reason'] ?? '';
$facultyId = Auth::userId();

if (!in_array($status, ['present', 'absent'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

$db = Database::getInstance();

// Verify session ownership
$stmt = $db->prepare("SELECT faculty_id, subject_id FROM attendance_sessions WHERE id = ?");
$stmt->bind_param("i", $sessionId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Session not found']);
    exit;
}

$session = $result->fetch_assoc();
if ($session['faculty_id'] != $facultyId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$subjectId = $session['subject_id'];
$attendanceDate = date('Y-m-d');
$markedAt = date('Y-m-d H:i:s');

// Check if attendance record exists
$stmt = $db->prepare("SELECT id, status FROM subject_attendance WHERE session_id = ? AND student_id = ?");
$stmt->bind_param("ii", $sessionId, $studentId);
$stmt->execute();
$result = $stmt->get_result();

$oldStatus = null;
$attendanceId = null;

if ($result->num_rows > 0) {
    // Update existing record
    $existing = $result->fetch_assoc();
    $oldStatus = $existing['status'];
    $attendanceId = $existing['id'];
    
    $stmt = $db->prepare("UPDATE subject_attendance SET status = ?, marked_method = 'manual', updated_at = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status, $markedAt, $attendanceId);
    $stmt->execute();
} else {
    // Insert new record
    $stmt = $db->prepare("INSERT INTO subject_attendance (session_id, student_id, subject_id, attendance_date, status, marked_at, marked_method) VALUES (?, ?, ?, ?, ?, ?, 'manual')");
    $stmt->bind_param("iiisss", $sessionId, $studentId, $subjectId, $attendanceDate, $status, $markedAt);
    $stmt->execute();
    $attendanceId = $db->lastInsertId();
}

// Log the manual change
$stmt = $db->prepare("INSERT INTO attendance_logs (attendance_type, attendance_id, student_id, modified_by, old_status, new_status, reason) VALUES ('subject', ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iiisss", $attendanceId, $studentId, $facultyId, $oldStatus, $status, $reason);
$stmt->execute();

echo json_encode([
    'success' => true,
    'message' => 'Attendance marked manually'
]);
?>

