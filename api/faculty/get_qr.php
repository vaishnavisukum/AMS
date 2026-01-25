<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/qr_generator.php';

// Only faculty can get QR codes
Auth::requireRole('faculty');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_GET['session_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Session ID required']);
    exit;
}

$sessionId = intval($_GET['session_id']);
$facultyId = Auth::userId();

$db = Database::getInstance();

// Get session details and verify ownership
$stmt = $db->prepare("
    SELECT 
        s.id, s.faculty_id, s.session_identifier, s.secret_key, s.status,
        sub.subject_name, sub.subject_code,
        u.full_name as faculty_name
    FROM attendance_sessions s
    JOIN subjects sub ON s.subject_id = sub.id
    JOIN users u ON s.faculty_id = u.id
    WHERE s.id = ?
");
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
    echo json_encode(['success' => false, 'message' => 'You do not have permission to access this session']);
    exit;
}

if ($session['status'] !== 'active') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Session is not active']);
    exit;
}

// Generate QR data
$qrData = QRGenerator::generateSessionQR(
    $session['session_identifier'],
    $session['subject_name'],
    $session['faculty_name'],
    $session['secret_key']
);

// Generate QR image URL
$qrImageUrl = QRGenerator::generateQRImage($qrData);

echo json_encode([
    'success' => true,
    'qr_data' => $qrData,
    'qr_image_url' => $qrImageUrl,
    'expiry_seconds' => QR_EXPIRY_TIME,
    'rotation_interval' => QR_ROTATION_INTERVAL
]);
?>

