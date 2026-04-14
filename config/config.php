<?php
session_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'linda_chronicles');

// Role Constants
define('ROLE_CUSTOMER', 1);
define('ROLE_INTERN', 2);
define('ROLE_PHARMACIST_ASSISTANT', 3);
define('ROLE_PHARMACY_TECHNICIAN', 4);
define('ROLE_PHARMACIST', 5);
define('ROLE_HR_PERSONNEL', 6);

// Role Names Mapping
$ROLE_NAMES = [
    1 => 'Customer',
    2 => 'Intern',
    3 => 'Pharmacist Assistant',
    4 => 'Pharmacy Technician',
    5 => 'Pharmacist',
    6 => 'HR Personnel'
];

// Application Settings
define('APP_NAME', 'Linda Chronicles - Pharmacy Management');
define('APP_URL', 'http://localhost/linda-chronicles');
define('SESSION_TIMEOUT', 3600); // 1 hour

// Database Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper function to check if user is authenticated
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Helper function to get current user role
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

// Helper function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Helper function to generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Helper function to verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Helper function to sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>
