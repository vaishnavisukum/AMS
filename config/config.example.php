<?php
// Application Configuration
// INSTRUCTIONS: Copy this file to config.php and update the values with your actual settings

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// QR Code Settings
define('QR_ROTATION_INTERVAL', 30); // seconds
define('QR_EXPIRY_TIME', 30); // seconds

// Security
define('SECRET_KEY', 'YOUR_SECRET_KEY_HERE'); // Change this in production!
define('HMAC_ALGO', 'sha256');

// Application paths
define('BASE_URL', 'http://localhost/AMS-ai/');
define('API_URL', BASE_URL . 'api/');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once __DIR__ . '/database.php';
