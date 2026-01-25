<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../utils/auth.php';

Auth::require();

$db = Database::getInstance();
$userId = Auth::userId();
$role = Auth::role();

// Build query based on role
if ($role === 'faculty') {
    // Faculty sees only their timetable
    $stmt = $db->prepare("
        SELECT 
            t.id, t.day_of_week, t.lecture_date, t.start_time, t.end_time, t.room_number, t.attendance_status,
            s.subject_code, s.subject_name
        FROM timetable t
        JOIN subjects s ON t.subject_id = s.id
        WHERE t.faculty_id = ?
        ORDER BY 
            t.lecture_date ASC,
            t.start_time
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $timetable = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    // Admin and students see all timetable
    $stmt = $db->prepare("
        SELECT 
            t.id, t.day_of_week, t.lecture_date, t.start_time, t.end_time, t.room_number, t.attendance_status,
            s.subject_code, s.subject_name,
            u.full_name as faculty_name
        FROM timetable t
        JOIN subjects s ON t.subject_id = s.id
        JOIN users u ON t.faculty_id = u.id
        ORDER BY 
            t.lecture_date ASC,
            t.start_time
    ");
    $stmt->execute();
    $timetable = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

echo json_encode([
    'success' => true,
    'timetable' => $timetable
]);
