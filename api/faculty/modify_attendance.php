<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';

// Only faculty can modify attendance
Auth::requireRole('faculty');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['student_id']) || !isset($input['session_id']) || !isset($input['new_status'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Student ID, session ID, and new status are required']);
  exit;
}

$studentNumber = trim($input['student_id']); // This is the student_id field (like STU001)
$sessionId = intval($input['session_id']);
$newStatus = $input['new_status'];
$reason = $input['reason'] ?? '';
$facultyId = Auth::userId();

if (!in_array($newStatus, ['present', 'absent'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid status']);
  exit;
}

try {
  $db = Database::getInstance();

  // Get the student's database ID from student_id field
  $stmt = $db->prepare("SELECT id FROM users WHERE student_id = ? AND role = 'student'");
  $stmt->bind_param("s", $studentNumber);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit;
  }

  $student = $result->fetch_assoc();
  $studentDbId = $student['id'];

  // Get the session details
  $stmt = $db->prepare("
        SELECT id, faculty_id, subject_id, DATE(started_at) as attendance_date
        FROM attendance_sessions 
        WHERE id = ? 
        AND status = 'completed'
    ");
  $stmt->bind_param("i", $sessionId);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Session not found or not completed']);
    exit;
  }

  $session = $result->fetch_assoc();
  $subjectId = $session['subject_id'];
  $attendanceDate = $session['attendance_date'];

  // Check if attendance record exists
  $stmt = $db->prepare("SELECT id, status FROM subject_attendance WHERE session_id = ? AND student_id = ?");
  $stmt->bind_param("ii", $sessionId, $studentDbId);
  $stmt->execute();
  $result = $stmt->get_result();

  $oldStatus = 'absent'; // Default to absent if no record exists
  $attendanceId = null;

  if ($result->num_rows > 0) {
    $attendance = $result->fetch_assoc();
    $attendanceId = $attendance['id'];
    $oldStatus = $attendance['status'];

    // Update existing record
    $stmt = $db->prepare("
            UPDATE subject_attendance 
            SET status = ?, marked_at = NOW(), marked_method = 'manual'
            WHERE id = ?
        ");
    $stmt->bind_param("si", $newStatus, $attendanceId);
    $stmt->execute();
  } else {
    // Insert new record (student was previously absent with no record)
    $stmt = $db->prepare("
            INSERT INTO subject_attendance (session_id, student_id, subject_id, attendance_date, status, marked_at, marked_method)
            VALUES (?, ?, ?, ?, ?, NOW(), 'manual')
        ");
    $stmt->bind_param("iiiss", $sessionId, $studentDbId, $subjectId, $attendanceDate, $newStatus);
    $stmt->execute();
    $attendanceId = $db->insert_id;
  }

  // Log the modification
  $stmt = $db->prepare("
        INSERT INTO attendance_logs (attendance_type, attendance_id, student_id, modified_by, old_status, new_status, reason)
        VALUES ('subject', ?, ?, ?, ?, ?, ?)
    ");
  $stmt->bind_param("iiisss", $attendanceId, $studentDbId, $facultyId, $oldStatus, $newStatus, $reason);
  $stmt->execute();

  // Update campus attendance if needed
  if ($newStatus === 'present') {
    // Mark campus attendance as present if not already
    $stmt = $db->prepare("
            INSERT INTO campus_attendance (student_id, attendance_date, status, marked_at, is_derived)
            VALUES (?, ?, 'present', NOW(), 1)
            ON DUPLICATE KEY UPDATE status = 'present', is_derived = 1
        ");
    $stmt->bind_param("is", $studentDbId, $attendanceDate);
    $stmt->execute();
  }

  echo json_encode([
    'success' => true,
    'message' => 'Attendance modified successfully',
    'old_status' => $oldStatus,
    'new_status' => $newStatus
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error modifying attendance: ' . $e->getMessage()
  ]);
}
