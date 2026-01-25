<?php
require_once __DIR__ . '/../config/config.php';

class Auth
{

    /**
     * Check if user is logged in
     */
    public static function check()
    {
        return isset($_SESSION['user_id']) && isset($_SESSION['role']);
    }

    /**
     * Get current user ID
     */
    public static function userId()
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current user role
     */
    public static function role()
    {
        return $_SESSION['role'] ?? null;
    }

    /**
     * Get current user data
     */
    public static function user()
    {
        if (!self::check()) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id, username, full_name, email, role, student_id, faculty_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Check if user has specific role
     */
    public static function hasRole($role)
    {
        return self::role() === $role;
    }

    /**
     * Require authentication
     */
    public static function require()
    {
        if (!self::check()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
    }

    /**
     * Require specific role
     */
    public static function requireRole($role)
    {
        self::require();
        if (!self::hasRole($role)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Forbidden: Insufficient permissions',
                'current_role' => self::role(),
                'required_role' => $role
            ]);
            exit;
        }
    }

    /**
     * Login user
     */
    public static function login($username, $password)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id, username, password, full_name, email, role, student_id, faculty_id, status FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        $user = $result->fetch_assoc();

        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account is inactive'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['student_id'] = $user['student_id'];
        $_SESSION['faculty_id'] = $user['faculty_id'];

        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'student_id' => $user['student_id'],
                'faculty_id' => $user['faculty_id']
            ]
        ];
    }

    /**
     * Logout user
     */
    public static function logout()
    {
        // Clear all session variables
        $_SESSION = [];

        // Destroy the session
        session_destroy();

        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    /**
     * Hash password
     */
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}
