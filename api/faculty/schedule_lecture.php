<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
  require_once __DIR__ . '/../../config/database.php';
  require_once __DIR__ . '/../../utils/auth.php';

  Auth::requireRole('faculty');

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
  }

  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
  }

  if (!isset($input['subject_id']) || !isset($input['lecture_date']) || !isset($input['start_time']) || !isset($input['end_time'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
  }

  $subjectId = intval($input['subject_id']);
  $lectureDate = $input['lecture_date'];
  $startTime = $input['start_time'];
  $endTime = $input['end_time'];
  $roomNumber = isset($input['room_number']) ? $input['room_number'] : null;
  $facultyId = Auth::userId();

  // Validate lecture date (YYYY-MM-DD)
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lectureDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid lecture date']);
    exit;
  }

  // Derive day_of_week from lecture_date to keep data consistent
  $timestamp = strtotime($lectureDate);
  if ($timestamp === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid lecture date value']);
    exit;
  }
  $dayOfWeek = date('l', $timestamp);

  // Prevent scheduling in the past
  $today = date('Y-m-d');
  if ($lectureDate < $today) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Lecture date cannot be in the past']);
    exit;
  }

  if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid time format']);
    exit;
  }

  if ($startTime >= $endTime) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Start time must be before end time']);
    exit;
  }

  $db = Database::getInstance();

  $stmt = $db->prepare("SELECT id, subject_name FROM subjects WHERE id = ?");
  if (!$stmt) {
    throw new Exception("Prepare failed: " . $db->getConnection()->error);
  }

  $stmt->bind_param("i", $subjectId);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Subject not found']);
    exit;
  }

  $subject = $result->fetch_assoc();

  $checkStmt = $db->prepare("SELECT id FROM timetable WHERE faculty_id = ? AND lecture_date = ? AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?) OR (start_time >= ? AND end_time <= ?))");
  if (!$checkStmt) {
    throw new Exception("Prepare failed: " . $db->getConnection()->error);
  }

  // 1 int + 7 strings
  $checkStmt->bind_param("isssssss", $facultyId, $lectureDate, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime);
  $checkStmt->execute();
  $checkResult = $checkStmt->get_result();

  if ($checkResult->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'You already have a class scheduled during this time']);
    exit;
  }

  $insertStmt = $db->prepare("INSERT INTO timetable (subject_id, faculty_id, day_of_week, lecture_date, start_time, end_time, room_number, attendance_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'not_started')");
  if (!$insertStmt) {
    throw new Exception("Prepare failed: " . $db->getConnection()->error);
  }

  $insertStmt->bind_param("iisssss", $subjectId, $facultyId, $dayOfWeek, $lectureDate, $startTime, $endTime, $roomNumber);

  if (!$insertStmt->execute()) {
    throw new Exception("Execute failed: " . $insertStmt->error);
  }

  echo json_encode([
    'success' => true,
    'message' => 'Lecture scheduled successfully',
    'timetable' => [
      'id' => $db->lastInsertId(),
      'subject_name' => $subject['subject_name'],
      'day_of_week' => $dayOfWeek,
      'lecture_date' => $lectureDate,
      'start_time' => $startTime,
      'end_time' => $endTime,
      'room_number' => $roomNumber,
      'attendance_status' => 'not_started'
    ]
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Error: ' . $e->getMessage()
  ]);
}
