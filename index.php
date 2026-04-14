<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/controllers/AuthController.php';

$controller = new AuthController();
$action = $_GET['action'] ?? 'login';

// Clear messages after displaying
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
} else {
    $success = null;
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
} else {
    $error = null;
}

switch ($action) {
    case 'register':
        $controller->handleRegister();
        break;

    case 'login':
        $controller->handleLogin();
        break;

    case 'logout':
        $controller->handleLogout();
        break;

    case 'dashboard':
        $role = $_GET['role'] ?? 'customer';
        $controller->showDashboard($role);
        break;

    default:
        // Default redirect to login if not authenticated
        if (isAuthenticated()) {
            $controller->redirectToDashboard();
        } else {
            $controller->showLogin();
        }
}
?>
