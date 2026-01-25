<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';

// Only faculty can access this
Auth::requireRole('faculty');

$db = Database::getInstance();
$facultyId = Auth::userId();

try {
  // Get all subjects for this faculty with lecture count
  $stmt = $db->prepare("
        SELECT 
            s.id,
            s.subject_code,
            s.subject_name,
            COUNT(CASE WHEN tt.attendance_status = 'completed' THEN 1 END) as completed_lectures,
            COUNT(CASE WHEN tt.attendance_status = 'active' THEN 1 END) as active_lectures,
            COUNT(CASE WHEN tt.attendance_status = 'not_started' THEN 1 END) as not_started_lectures
        FROM subjects s
        LEFT JOIN timetable tt ON s.id = tt.subject_id AND tt.faculty_id = ?
        GROUP BY s.id, s.subject_code, s.subject_name
        HAVING COUNT(tt.id) > 0
        ORDER BY s.subject_code
    ");

  $stmt->bind_param("i", $facultyId);
  $stmt->execute();
  $subjectsResult = $stmt->get_result();
  $subjects = $subjectsResult->fetch_all(MYSQLI_ASSOC);

  // For each subject, get detailed lecture list
  $subjectsWithLectures = [];

  foreach ($subjects as $subject) {
    $lectureStmt = $db->prepare("
            SELECT 
                tt.id,
                tt.lecture_date,
                tt.start_time,
                tt.end_time,
                tt.room_number,
                tt.attendance_status,
                s.subject_code,
                s.subject_name
            FROM timetable tt
            INNER JOIN subjects s ON tt.subject_id = s.id
            WHERE tt.subject_id = ? AND tt.faculty_id = ?
            ORDER BY tt.lecture_date DESC, tt.start_time DESC
        ");

    $lectureStmt->bind_param("ii", $subject['id'], $facultyId);
    $lectureStmt->execute();
    $lectures = $lectureStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $subject['lectures'] = $lectures;
    $subjectsWithLectures[] = $subject;
  }

  echo json_encode([
    'success' => true,
    'subjects' => $subjectsWithLectures
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error fetching lectures: ' . $e->getMessage()
  ]);
}
