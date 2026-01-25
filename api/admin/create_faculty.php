<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../utils/auth.php';

// Only admins can add faculty
Auth::requireRole('admin');

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

$fullName = trim($input['full_name'] ?? '');
$username = trim($input['username'] ?? '');
$email = trim($input['email'] ?? '');
$facultyId = trim($input['faculty_id'] ?? '');
$password = trim($input['password'] ?? '');

if ($fullName === '' || $username === '' || $email === '' || $facultyId === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

$db = Database::getInstance();

// Check duplicates
$checks = [
    ['sql' => 'SELECT id FROM users WHERE username = ?', 'val' => $username, 'msg' => 'Username already exists'],
    ['sql' => 'SELECT id FROM users WHERE email = ?', 'val' => $email, 'msg' => 'Email already exists'],
    ['sql' => 'SELECT id FROM users WHERE faculty_id = ?', 'val' => $facultyId, 'msg' => 'Faculty ID already exists']
];

foreach ($checks as $check) {
    $stmt = $db->prepare($check['sql']);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare query']);
        exit;
    }
    $stmt->bind_param('s', $check['val']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => $check['msg']]);
        exit;
    }
}

$hashedPassword = Auth::hashPassword($password);

// Use placeholders for all values to ensure proper binding
$role = 'faculty';
$status = 'active';

$stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role, faculty_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare insert']);
    exit;
}

$stmt->bind_param('sssssss', $username, $hashedPassword, $fullName, $email, $role, $facultyId, $status);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create faculty: ' . $stmt->error]);
    exit;
}

$facultyDbId = $db->lastInsertId();

echo json_encode([
    'success' => true,
    'message' => 'Faculty account created successfully',
    'faculty' => [
        'id' => $facultyDbId,
        'username' => $username,
        'full_name' => $fullName,
        'email' => $email,
        'faculty_id' => $facultyId
    ]
]);
