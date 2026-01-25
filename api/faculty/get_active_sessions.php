<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';

// Only faculty can view their sessions
Auth::requireRole('faculty');

$facultyId = Auth::userId();
$db = Database::getInstance();

// Get active sessions
$stmt = $db->prepare("
    SELECT 
        s.id, s.session_identifier, s.started_at, s.ended_at, s.status, s.qr_scan_count,
        sub.subject_name, sub.subject_code,
        COUNT(DISTINCT sa.student_id) as attendance_count
    FROM attendance_sessions s
    JOIN subjects sub ON s.subject_id = sub.id
    LEFT JOIN subject_attendance sa ON s.id = sa.session_id AND sa.status = 'present'
    WHERE s.faculty_id = ? AND s.status = 'active'
    GROUP BY s.id
    ORDER BY s.started_at DESC
");
$stmt->bind_param("i", $facultyId);
$stmt->execute();
$result = $stmt->get_result();

$sessions = [];
while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}

echo json_encode([
    'success' => true,
    'sessions' => $sessions
]);
?>

