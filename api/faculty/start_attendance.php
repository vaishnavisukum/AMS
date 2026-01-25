<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';

// Only faculty can start attendance
Auth::requireRole('faculty');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['subject_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Subject ID required']);
    exit;
}

$subjectId = intval($input['subject_id']);
$timetableId = isset($input['timetable_id']) ? intval($input['timetable_id']) : null;
$facultyId = Auth::userId();

$db = Database::getInstance();

// Check if subject exists
$stmt = $db->prepare("SELECT subject_name, subject_code FROM subjects WHERE id = ?");
$stmt->bind_param("i", $subjectId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Subject not found']);
    exit;
}

$subject = $result->fetch_assoc();

// If a timetable slot is linked, validate current day/time is within the slot
if ($timetableId !== null) {
    $stmt = $db->prepare("SELECT day_of_week, start_time, end_time FROM timetable WHERE id = ? AND faculty_id = ?");
    $stmt->bind_param("ii", $timetableId, $facultyId);
    $stmt->execute();
    $ttRes = $stmt->get_result();

    if ($ttRes->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Timetable slot not found for this faculty']);
        exit;
    }

    $slot = $ttRes->fetch_assoc();
    $today = date('l'); // e.g., Monday
    $nowTime = date('H:i');

    // Validate day_of_week is not empty
    if (empty($slot['day_of_week'])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Timetable slot has invalid day configuration. Please contact administrator.']);
        exit;
    }

    if ($today !== $slot['day_of_week']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Cannot start QR outside scheduled day',
            'scheduled_day' => $slot['day_of_week'],
            'today' => $today
        ]);
        exit;
    }

    if ($nowTime < $slot['start_time'] || $nowTime > $slot['end_time']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Cannot start QR outside scheduled time window',
            'scheduled_window' => $slot['start_time'] . ' - ' . $slot['end_time'],
            'current_time' => $nowTime
        ]);
        exit;
    }
}

// Check if there's already an active session for this subject by this faculty
$stmt = $db->prepare("SELECT id FROM attendance_sessions WHERE subject_id = ? AND faculty_id = ? AND status = 'active'");
$stmt->bind_param("ii", $subjectId, $facultyId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'An active attendance session already exists for this subject']);
    exit;
}

// Generate unique session identifier and secret key
$sessionIdentifier = bin2hex(random_bytes(32));
$secretKey = bin2hex(random_bytes(64));

// Create attendance session
$stmt = $db->prepare("INSERT INTO attendance_sessions (subject_id, faculty_id, timetable_id, session_identifier, secret_key, status) VALUES (?, ?, ?, ?, ?, 'active')");
$stmt->bind_param("iiiss", $subjectId, $facultyId, $timetableId, $sessionIdentifier, $secretKey);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create attendance session']);
    exit;
}

$sessionId = $db->lastInsertId();

// Update timetable status if timetable_id is provided
if ($timetableId !== null) {
    $stmt = $db->prepare("UPDATE timetable SET attendance_status = 'active' WHERE id = ?");
    $stmt->bind_param("i", $timetableId);
    $stmt->execute();
}

// Get faculty name
$user = Auth::user();
$facultyName = $user['full_name'];

echo json_encode([
    'success' => true,
    'message' => 'Attendance session started successfully',
    'session' => [
        'id' => $sessionId,
        'session_identifier' => $sessionIdentifier,
        'subject_id' => $subjectId,
        'subject_name' => $subject['subject_name'],
        'subject_code' => $subject['subject_code'],
        'faculty_name' => $facultyName,
        'started_at' => date('Y-m-d H:i:s'),
        'secret_key' => $secretKey
    ]
]);
