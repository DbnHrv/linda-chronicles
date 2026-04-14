<?php
require_once __DIR__ . '/config/config.php';

if (!isAuthenticated()) {
    redirect(APP_URL . '/?action=login');
}

// Session timeout
if (time() - (isset($_SESSION['login_time']) ? $_SESSION['login_time'] : 0) > SESSION_TIMEOUT) {
    $_SESSION['error'] = 'Your session has expired. Please login again.';
    session_destroy();
    redirect(APP_URL . '/?action=login');
}

$roleMap = array(
    ROLE_CUSTOMER             => 'customer',
    ROLE_INTERN               => 'intern',
    ROLE_PHARMACIST_ASSISTANT => 'pharmacist_assistant',
    ROLE_PHARMACY_TECHNICIAN  => 'pharmacy_technician',
    ROLE_PHARMACIST           => 'pharmacist',
    ROLE_HR_PERSONNEL         => 'hr_personnel',
);

$role = isset($roleMap[getCurrentUserRole()]) ? $roleMap[getCurrentUserRole()] : null;

if ($role) {
    $view = __DIR__ . '/views/dashboard/' . $role . '.php';
    if (file_exists($view)) {
        require $view;
        exit;
    }
}

// Fallback
redirect(APP_URL . '/?action=login');
?>
