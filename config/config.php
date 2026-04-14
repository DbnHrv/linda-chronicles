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
    return $_SESSION['role_id'] ?? $_SESSION['user_role'] ?? null;
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

// ── RBAC ──────────────────────────────────────────────────────

$PROCESS_ACCESS = [
    ROLE_INTERN               => [1, 4, 6, 8, 9],
    ROLE_HR_PERSONNEL         => [2, 3, 4, 5, 6, 7, 8],
    ROLE_PHARMACY_TECHNICIAN  => [10, 11, 12],
    ROLE_PHARMACIST           => [13, 14],
    ROLE_PHARMACIST_ASSISTANT => [16, 17],
    ROLE_CUSTOMER             => [15, 18],
];

$PROCESS_METADATA = [
    1  => ['name'=>'Submit Internship Requirements',  'role'=>'Intern',               'category'=>'Internship'],
    2  => ['name'=>'Organize Pharmacy Policies',      'role'=>'HR Personnel',          'category'=>'HR'],
    3  => ['name'=>'Check Internship Requirements',   'role'=>'HR Personnel',          'category'=>'HR'],
    4  => ['name'=>'Conduct Job Interview',           'role'=>'HR Personnel / Intern', 'category'=>'HR'],
    5  => ['name'=>'Present Schedule & Requirements', 'role'=>'HR Personnel',          'category'=>'HR'],
    6  => ['name'=>'Organize Schedule',               'role'=>'HR Personnel / Intern', 'category'=>'HR'],
    7  => ['name'=>'Conduct Company Orientation',     'role'=>'HR Personnel',          'category'=>'HR'],
    8  => ['name'=>'Present Internship Tasks',        'role'=>'HR Personnel / Intern', 'category'=>'HR'],
    9  => ['name'=>'Conduct Product Inventory',       'role'=>'Intern',               'category'=>'Inventory'],
    10 => ['name'=>'Create Inventory Report',         'role'=>'Pharmacy Technician',  'category'=>'Inventory'],
    11 => ['name'=>'Check Inventory Report',          'role'=>'Pharmacy Technician',  'category'=>'Inventory'],
    12 => ['name'=>'Request Additional Stocks',       'role'=>'Pharmacy Technician',  'category'=>'Inventory'],
    13 => ['name'=>'Check Stock Requisition Report',  'role'=>'Pharmacist',           'category'=>'Pharmacy'],
    14 => ['name'=>'Generate Purchase Order',         'role'=>'Pharmacist',           'category'=>'Pharmacy'],
    15 => ['name'=>'Upload Doctor Prescription',      'role'=>'Customer',             'category'=>'Customer'],
    16 => ['name'=>'Check Product Availability',      'role'=>'Pharmacist Assistant', 'category'=>'Pharmacy'],
    17 => ['name'=>'Dispense Product',                'role'=>'Pharmacist Assistant', 'category'=>'Pharmacy'],
    18 => ['name'=>'Process Payment',                 'role'=>'Customer',             'category'=>'Customer'],
];

function canAccessProcess($processId, $roleId = null) {
    global $PROCESS_ACCESS;
    if ($roleId === null) $roleId = getCurrentUserRole();
    return isset($PROCESS_ACCESS[$roleId]) && in_array($processId, $PROCESS_ACCESS[$roleId]);
}

function getAccessibleProcesses($roleId = null) {
    global $PROCESS_ACCESS;
    if ($roleId === null) $roleId = getCurrentUserRole();
    return $PROCESS_ACCESS[$roleId] ?? [];
}

function getProcessMetadata($processId) {
    global $PROCESS_METADATA;
    return $PROCESS_METADATA[$processId] ?? null;
}

function getRoleNameById($roleId) {
    global $ROLE_NAMES;
    return $ROLE_NAMES[$roleId] ?? 'Unknown';
}

function requireProcessAccess($processId) {
    if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
    if (!canAccessProcess($processId)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;background:#0a0c10;color:#f87171;min-height:100vh">
             <h2>Access Denied</h2><p>You do not have permission to access this process.</p>
             <a href="' . APP_URL . '/dashboard.php" style="color:#38bdf8">← Back to Dashboard</a></div>');
    }
}
?>
