<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';

// Only admin can view this data
Auth::requireRole('admin');

$db = Database::getInstance();

// Step 1: Get students with overall attendance below 75%
// Use only completed attendance sessions (actually held lectures) as the basis for calculation
// NOT_STARTED lectures are excluded - students are only marked absent for lectures that were held
$overallQuery = "
    SELECT 
        u.id,
        u.student_id,
        u.full_name,
        COUNT(DISTINCT sess.id) as total_classes,
        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_count,
        ROUND((SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) / COUNT(DISTINCT sess.id)) * 100, 2) as overall_attendance
    FROM users u
    CROSS JOIN attendance_sessions sess
    LEFT JOIN subject_attendance sa ON sa.session_id = sess.id AND sa.student_id = u.id
    WHERE u.role = 'student' AND sess.status = 'completed'
    GROUP BY u.id, u.student_id, u.full_name
    HAVING overall_attendance < 75
    ORDER BY u.student_id
";

$overallResult = $db->query($overallQuery);

if (!$overallResult) {
  echo json_encode([
    'success' => false,
    'message' => 'Failed to fetch attendance data'
  ]);
  exit;
}

$students = [];
$studentIds = [];

// Collect all students with overall attendance below 75%
while ($row = $overallResult->fetch_assoc()) {
  $studentIds[] = $row['id'];
  $students[$row['id']] = [
    'id' => $row['id'],
    'full_name' => $row['full_name'],
    'student_id' => $row['student_id'],
    'overall_attendance' => $row['overall_attendance'],
    'total_classes' => $row['total_classes'],
    'present_count' => $row['present_count'],
    'subjects' => []
  ];
}

// If no students with low overall attendance, return empty result
if (empty($studentIds)) {
  echo json_encode([
    'success' => true,
    'students' => [],
    'total_count' => 0
  ]);
  exit;
}

// Step 2: For those students, get subjects where attendance is below 75%
// Use only completed attendance sessions (actually held lectures) grouped by subject
// NOT_STARTED lectures are excluded
$placeholders = implode(',', array_fill(0, count($studentIds), '?'));
$subjectQuery = "
    SELECT 
        u.id,
        u.student_id,
        u.full_name,
        s.id as subject_id,
        s.subject_code,
        s.subject_name,
        COUNT(DISTINCT sess.id) as total_classes,
        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_count,
        ROUND((SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) / COUNT(DISTINCT sess.id)) * 100, 2) as subject_attendance
    FROM users u
    CROSS JOIN subjects s
    INNER JOIN attendance_sessions sess ON s.id = sess.subject_id AND sess.status = 'completed'
    LEFT JOIN subject_attendance sa ON sa.session_id = sess.id AND sa.student_id = u.id
    WHERE u.id IN ($placeholders)
    GROUP BY u.id, u.student_id, u.full_name, s.id, s.subject_code, s.subject_name
    HAVING subject_attendance < 75
    ORDER BY u.student_id, subject_attendance ASC
";

$stmt = $db->prepare($subjectQuery);
$stmt->bind_param(str_repeat('i', count($studentIds)), ...$studentIds);
$stmt->execute();
$subjectResult = $stmt->get_result();

// Add subject data to students
while ($row = $subjectResult->fetch_assoc()) {
  $studentId = $row['id'];
  if (isset($students[$studentId])) {
    $students[$studentId]['subjects'][] = [
      'subject_code' => $row['subject_code'],
      'subject_name' => $row['subject_name'],
      'total_classes' => $row['total_classes'],
      'present_count' => $row['present_count'],
      'attendance_percentage' => $row['subject_attendance']
    ];
  }
}

// Convert to indexed array
$studentsList = array_values($students);

echo json_encode([
  'success' => true,
  'students' => $studentsList,
  'total_count' => count($studentsList)
]);
