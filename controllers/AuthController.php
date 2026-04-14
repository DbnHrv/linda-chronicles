<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/user.php';

class AuthController {
    private $user;

    public function __construct() {
        $this->user = new User();
    }

    /**
     * Show login page
     */
    public function showLogin() {
        if (isAuthenticated()) {
            $this->redirectToDashboard();
        }
        require __DIR__ . '/../views/login.php';
    }

    /**
     * Handle login form submission
     */
    public function handleLogin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showLogin();
            return;
        }

        // Verify CSRF token
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid request. Please try again.';
            $_SESSION['error'] = $error;
            require __DIR__ . '/../views/login.php';
            return;
        }

        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = $this->user->login($email, $password);

        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            $this->redirectToDashboard();
        } else {
            $_SESSION['error'] = $result['message'];
            require __DIR__ . '/../views/login.php';
        }
    }

    /**
     * Show register page
     */
    public function showRegister() {
        if (isAuthenticated()) {
            $this->redirectToDashboard();
        }

        $roles = $this->user->getAllRoles();
        require __DIR__ . '/../views/register.php';
    }

    /**
     * Handle register form submission
     */
    public function handleRegister() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showRegister();
            return;
        }

        // Verify CSRF token
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid request. Please try again.';
            $_SESSION['error'] = $error;
            $roles = $this->user->getAllRoles();
            require __DIR__ . '/../views/register.php';
            return;
        }

        $firstName = sanitize($_POST['first_name'] ?? '');
        $middleName = sanitize($_POST['middle_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $roleId = sanitize($_POST['role_id'] ?? '');

        // Validate
        if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($roleId)) {
            $_SESSION['error'] = 'First name, last name, email, password, and role are required';
            $roles = $this->user->getAllRoles();
            require __DIR__ . '/../views/register.php';
            return;
        }

        if ($password !== $confirmPassword) {
            $_SESSION['error'] = 'Passwords do not match';
            $roles = $this->user->getAllRoles();
            require __DIR__ . '/../views/register.php';
            return;
        }

        $result = $this->user->register($firstName, $middleName, $lastName, $email, $password, $roleId);

        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            redirect(APP_URL . '/?action=login');
        } else {
            $_SESSION['error'] = $result['message'];
            $roles = $this->user->getAllRoles();
            require __DIR__ . '/../views/register.php';
        }
    }

    /**
     * Handle logout
     */
    public function handleLogout() {
        $this->user->logout();
        $_SESSION['success'] = 'You have been logged out successfully.';
        redirect(APP_URL . '/?action=login');
    }

    /**
     * Redirect to role-specific dashboard
     */
    public function redirectToDashboard() {
        if (!isAuthenticated()) {
            redirect(APP_URL . '/?action=login');
        }

        $roleId = getCurrentUserRole();

        switch ($roleId) {
            case ROLE_CUSTOMER:
                redirect(APP_URL . '/?action=dashboard&role=customer');
                break;
            case ROLE_INTERN:
                redirect(APP_URL . '/?action=dashboard&role=intern');
                break;
            case ROLE_PHARMACIST_ASSISTANT:
                redirect(APP_URL . '/?action=dashboard&role=pharmacist_assistant');
                break;
            case ROLE_PHARMACY_TECHNICIAN:
                redirect(APP_URL . '/?action=dashboard&role=pharmacy_technician');
                break;
            case ROLE_PHARMACIST:
                redirect(APP_URL . '/?action=dashboard&role=pharmacist');
                break;
            case ROLE_HR_PERSONNEL:
                redirect(APP_URL . '/?action=dashboard&role=hr_personnel');
                break;
            default:
                redirect(APP_URL . '/?action=login');
        }
    }

    /**
     * Show dashboard
     */
    public function showDashboard($role) {
        if (!isAuthenticated()) {
            redirect(APP_URL . '/?action=login');
        }

        // Check session timeout
        if (!$this->user->checkSessionTimeout()) {
            $_SESSION['error'] = 'Your session has expired. Please login again.';
            redirect(APP_URL . '/?action=login');
        }

        // Check if user role matches the requested dashboard
        $roleId = getCurrentUserRole();
        $validRoles = [
            'customer' => ROLE_CUSTOMER,
            'intern' => ROLE_INTERN,
            'pharmacist_assistant' => ROLE_PHARMACIST_ASSISTANT,
            'pharmacy_technician' => ROLE_PHARMACY_TECHNICIAN,
            'pharmacist' => ROLE_PHARMACIST,
            'hr_personnel' => ROLE_HR_PERSONNEL
        ];

        if (!isset($validRoles[$role]) || $validRoles[$role] !== $roleId) {
            redirect(APP_URL . '/?action=dashboard&role=' . $this->getRoleUrlFromId($roleId));
        }

        $viewPath = __DIR__ . '/../views/dashboard/' . $role . '.php';
        if (!file_exists($viewPath)) {
            redirect(APP_URL . '/?action=login');
        }

        require $viewPath;
    }

    /**
     * Get role URL from role ID
     */
    private function getRoleUrlFromId($roleId) {
        $roleMapping = [
            ROLE_CUSTOMER => 'customer',
            ROLE_INTERN => 'intern',
            ROLE_PHARMACIST_ASSISTANT => 'pharmacist_assistant',
            ROLE_PHARMACY_TECHNICIAN => 'pharmacy_technician',
            ROLE_PHARMACIST => 'pharmacist',
            ROLE_HR_PERSONNEL => 'hr_personnel'
        ];
        return $roleMapping[$roleId] ?? 'login';
    }
}
?>
