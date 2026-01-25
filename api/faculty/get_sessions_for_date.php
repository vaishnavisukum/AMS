<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';

// Only faculty can access this
Auth::requireRole('faculty');

if (!isset($_GET['subject_id']) || !isset($_GET['date'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Subject ID and date are required']);
  exit;
}

$subjectId = intval($_GET['subject_id']);
$date = $_GET['date'];

try {
  $db = Database::getInstance();

  $stmt = $db->prepare("
        SELECT 
            sess.id,
            sess.started_at,
            sess.ended_at,
            sub.subject_name,
            sub.subject_code,
            u.full_name as faculty_name
        FROM attendance_sessions sess
        JOIN subjects sub ON sess.subject_id = sub.id
        JOIN users u ON sess.faculty_id = u.id
        WHERE sess.subject_id = ?
        AND DATE(sess.started_at) = ?
        AND sess.status = 'completed'
        ORDER BY sess.started_at ASC
    ");

  $stmt->bind_param("is", $subjectId, $date);
  $stmt->execute();
  $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

  echo json_encode([
    'success' => true,
    'sessions' => $sessions
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error fetching sessions: ' . $e->getMessage()
  ]);
}
