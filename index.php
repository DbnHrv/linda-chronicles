<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/controllers/AuthController.php';

$controller = new AuthController();
$action = isset($_GET['action']) ? $_GET['action'] : 'default';

switch ($action) {
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->handleRegister();
        } else {
            $controller->showRegister();
        }
        break;

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->handleLogin();
        } else {
            $controller->showLogin();
        }
        break;

    case 'logout':
        $controller->handleLogout();
        break;

    default:
        if (isAuthenticated()) {
            redirect(APP_URL . '/dashboard.php');
        } else {
            $controller->showLogin();
        }
}
?>
