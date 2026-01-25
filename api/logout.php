<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../utils/auth.php';

Auth::require();
$result = Auth::logout();

echo json_encode($result);
?>

