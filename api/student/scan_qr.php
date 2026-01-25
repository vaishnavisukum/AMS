<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/qr_generator.php';

// Only students can scan QR
Auth::requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['qr_data'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'QR data required']);
    exit;
}

$qrData = json_decode($input['qr_data'], true);

if (!isset($qrData['data']) || !isset($qrData['signature'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid QR format']);
    exit;
}

$data = $qrData['data'];
$signature = $qrData['signature'];

// Determine QR type
if (!isset($data['type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'QR type not specified']);
    exit;
}

$studentId = Auth::userId();
$db = Database::getInstance();

// Handle different QR types
if ($data['type'] === 'subject_attendance') {
    // Subject attendance QR
    
    if (!isset($data['session_id']) || !isset($data['expiry'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid subject attendance QR']);
        exit;
    }
    
    // Get session details
    $sessionIdentifier = $data['session_id'];
    $stmt = $db->prepare("SELECT id, subject_id, secret_key, status FROM attendance_sessions WHERE session_identifier = ?");
    $stmt->bind_param("s", $sessionIdentifier);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Session not found']);
        exit;
    }
    
    $session = $result->fetch_assoc();
    
    // Validate signature
    if (!QRGenerator::validateSignature($data, $signature, $session['secret_key'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid QR signature']);
        exit;
    }
    
    // Check if expired (with small grace period to avoid boundary issues)
    $expiryTs = isset($data['expiry']) ? (int)$data['expiry'] : 0;
    if (QRGenerator::isExpired($expiryTs)) {
        http_response_code(410);
        echo json_encode([
            'success' => false,
            'message' => 'QR code has expired. Please ask faculty to show the latest QR and try again.'
        ]);
        exit;
    }
    
    // Check if session is active
    if ($session['status'] !== 'active') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Attendance session is not active']);
        exit;
    }
    
    // Check if student has already marked attendance
    $stmt = $db->prepare("SELECT id FROM subject_attendance WHERE session_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $session['id'], $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Attendance already marked']);
        exit;
    }
    
    // Mark attendance
    $attendanceDate = date('Y-m-d');
    $markedAt = date('Y-m-d H:i:s');
    $qrDataString = json_encode($qrData);
    
    $stmt = $db->prepare("INSERT INTO subject_attendance (session_id, student_id, subject_id, attendance_date, status, marked_at, marked_method, qr_data) VALUES (?, ?, ?, ?, 'present', ?, 'qr_scan', ?)");
    $stmt->bind_param("iiisss", $session['id'], $studentId, $session['subject_id'], $attendanceDate, $markedAt, $qrDataString);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save subject attendance',
            'error' => $stmt->error
        ]);
        exit;
    }
    
    // Increment QR scan count
    $stmt = $db->prepare("UPDATE attendance_sessions SET qr_scan_count = qr_scan_count + 1 WHERE id = ?");
    $stmt->bind_param("i", $session['id']);
    $stmt->execute();
    
    // Auto-mark campus attendance as present (derived)
    $stmt = $db->prepare("INSERT INTO campus_attendance (student_id, attendance_date, status, marked_at, is_derived) VALUES (?, ?, 'present', ?, TRUE) ON DUPLICATE KEY UPDATE status = 'present', is_derived = TRUE, updated_at = ?");
    $stmt->bind_param("isss", $studentId, $attendanceDate, $markedAt, $markedAt);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Subject attendance marked successfully',
        'attendance_type' => 'subject',
        'subject' => $data['subject'],
        'marked_at' => $markedAt
    ]);
    
} elseif ($data['type'] === 'campus_attendance') {
    // Campus attendance QR
    
    // Validate signature using SECRET_KEY
    if (!QRGenerator::validateSignature($data, $signature, SECRET_KEY)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid QR signature']);
        exit;
    }
    
    $attendanceDate = $data['date'];
    $markedAt = date('Y-m-d H:i:s');
    
    // Check if already marked
    $stmt = $db->prepare("SELECT id, status FROM campus_attendance WHERE student_id = ? AND attendance_date = ?");
    $stmt->bind_param("is", $studentId, $attendanceDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Campus attendance already marked for today']);
        exit;
    }
    
    // Mark campus attendance
    $stmt = $db->prepare("INSERT INTO campus_attendance (student_id, attendance_date, status, marked_at, is_derived) VALUES (?, ?, 'present', ?, FALSE)");
    $stmt->bind_param("iss", $studentId, $attendanceDate, $markedAt);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save campus attendance',
            'error' => $stmt->error
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Campus attendance marked successfully',
        'attendance_type' => 'campus',
        'date' => $attendanceDate,
        'marked_at' => $markedAt
    ]);
    
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown QR type']);
}
?>

