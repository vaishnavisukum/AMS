<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate payload
$required = ['username', 'password', 'full_name', 'email', 'student_id'];
foreach ($required as $field) {
  if (empty($input[$field])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
    exit;
  }
}

$username = trim($input['username']);
$password = $input['password'];
$fullName = trim($input['full_name']);
$email = trim($input['email']);
$studentId = trim($input['student_id']);

if (strlen($password) < 6) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid email address']);
  exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Check uniqueness
$check = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ? OR student_id = ? LIMIT 1');
if (!$check) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error preparing uniqueness check']);
  exit;
}
$check->bind_param('sss', $username, $email, $studentId);
$check->execute();
$res = $check->get_result();
if ($res && $res->num_rows > 0) {
  http_response_code(409);
  echo json_encode(['success' => false, 'message' => 'Username, email, or student ID already exists']);
  exit;
}

$hashed = password_hash($password, PASSWORD_BCRYPT);

$stmt = $db->prepare('INSERT INTO users (username, password, full_name, email, role, student_id, status) VALUES (?, ?, ?, ?, "student", ?, "active")');
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error preparing insert']);
  exit;
}
$stmt->bind_param('sssss', $username, $hashed, $fullName, $email, $studentId);

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Failed to register student']);
  exit;
}

echo json_encode([
  'success' => true,
  'message' => 'Account created. You can now log in as a student.',
]);
