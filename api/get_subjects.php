<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../utils/auth.php';

Auth::require();

$db = Database::getInstance();

$stmt = $db->query("SELECT id, subject_code, subject_name, department, semester FROM subjects ORDER BY subject_code");
$subjects = $stmt->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'subjects' => $subjects
]);
?>

