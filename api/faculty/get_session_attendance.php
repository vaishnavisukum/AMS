<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';

// Only faculty can view session attendance
Auth::requireRole('faculty');

if (!isset($_GET['session_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Session ID required']);
    exit;
}

$sessionId = intval($_GET['session_id']);
$facultyId = Auth::userId();
$db = Database::getInstance();

// Verify session ownership
$stmt = $db->prepare("SELECT faculty_id FROM attendance_sessions WHERE id = ?");
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

// Get attendance records
$stmt = $db->prepare("
    SELECT 
        sa.id, sa.student_id, sa.status, sa.marked_at, sa.marked_method,
        u.full_name, u.student_id as student_number
    FROM subject_attendance sa
    JOIN users u ON sa.student_id = u.id
    WHERE sa.session_id = ?
    ORDER BY sa.marked_at DESC
");
$stmt->bind_param("i", $sessionId);
$stmt->execute();
$result = $stmt->get_result();

$attendance = [];
while ($row = $result->fetch_assoc()) {
    $attendance[] = $row;
}

echo json_encode([
    'success' => true,
    'attendance' => $attendance
]);
?>

