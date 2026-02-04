<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../utils/auth.php';

if (Auth::check()) {
    echo json_encode([
        'success' => true,
        'logged_in' => true,
        'user' => Auth::user()
    ]);
} else {
    echo json_encode([
        'success' => true,
        'logged_in' => false
    ]);
}
