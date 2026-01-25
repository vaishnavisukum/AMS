<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../utils/auth.php';

if (Auth::check()) {
    echo json_encode([
        'success' => true,
        'logged_in' => true,
        'user' => Auth::user(),
        'debug_session' => [
            'user_id' => $_SESSION['user_id'] ?? 'not set',
            'role' => $_SESSION['role'] ?? 'not set',
            'username' => $_SESSION['username'] ?? 'not set'
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'logged_in' => false,
        'debug_session' => [
            'session_started' => session_status() === PHP_SESSION_ACTIVE ? 'yes' : 'no',
            'session_id' => session_id()
        ]
    ]);
}
