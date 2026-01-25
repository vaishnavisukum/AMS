<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';

// Only admin can view statistics
Auth::requireRole('admin');

$db = Database::getInstance();

// Total users by role
$stmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$userStats = $stmt->fetch_all(MYSQLI_ASSOC);

// Total subjects
$stmt = $db->query("SELECT COUNT(*) as count FROM subjects");
$subjectCount = $stmt->fetch_assoc()['count'];

// Total sessions today
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) as count FROM attendance_sessions WHERE DATE(started_at) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$sessionsToday = $stmt->get_result()->fetch_assoc()['count'];

// Active sessions
$stmt = $db->query("SELECT COUNT(*) as count FROM attendance_sessions WHERE status = 'active'");
$activeSessions = $stmt->fetch_assoc()['count'];

// Campus attendance today
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM campus_attendance WHERE attendance_date = ? GROUP BY status");
$stmt->bind_param("s", $today);
$stmt->execute();
$campusToday = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Subject attendance today
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM subject_attendance WHERE attendance_date = ? GROUP BY status");
$stmt->bind_param("s", $today);
$stmt->execute();
$subjectToday = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'statistics' => [
        'users' => $userStats,
        'total_subjects' => $subjectCount,
        'sessions_today' => $sessionsToday,
        'active_sessions' => $activeSessions,
        'campus_attendance_today' => $campusToday,
        'subject_attendance_today' => $subjectToday
    ]
]);
?>

