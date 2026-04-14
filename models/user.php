<?php
require_once __DIR__ . '/../config/config.php';

class User {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Register a new user
     */
    public function register($firstName, $middleName, $lastName, $email, $password, $roleId) {
        // Validate input
        if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($roleId)) {
            return ['success' => false, 'message' => 'First name, last name, email, password, and role are required'];
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }

        // Check if email already exists
        if ($this->emailExists($email)) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        // Validate password strength (minimum 6 characters)
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters'];
        }

        try {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $sql = "INSERT INTO users (first_name, middle_name, last_name, email, password, role_id)
                    VALUES (:first_name, :middle_name, :last_name, :email, :password, :role_id)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':first_name' => $firstName,
                ':middle_name' => $middleName,
                ':last_name' => $lastName,
                ':email' => $email,
                ':password' => $hashedPassword,
                ':role_id' => $roleId
            ]);

            return ['success' => true, 'message' => 'Registration successful! Please login.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }

    /**
     * Login user
     */
    public function login($email, $password) {
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Email and password are required'];
        }

        try {
            $sql = "SELECT u.id, u.first_name, u.middle_name, u.last_name, u.email, u.password, u.role_id, r.role_name
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE u.email = :email AND u.is_active = 1";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Construct full name
                $fullName = trim($user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name']);

                // Create session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_first_name'] = $user['first_name'];
                $_SESSION['user_middle_name'] = $user['middle_name'];
                $_SESSION['user_last_name'] = $user['last_name'];
                $_SESSION['user_full_name'] = $fullName;
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role_id'];
                $_SESSION['user_role_name'] = $user['role_name'];
                $_SESSION['login_time'] = time();

                return ['success' => true, 'message' => 'Login successful', 'role_id' => $user['role_id']];
            } else {
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }

    /**
     * Check if email exists
     */
    public function emailExists($email) {
        try {
            $sql = "SELECT id FROM users WHERE email = :email";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':email' => $email]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        try {
            $sql = "SELECT u.*, r.role_name FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE u.id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Get role by ID
     */
    public function getRoleById($roleId) {
        try {
            $sql = "SELECT * FROM roles WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $roleId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Get all roles
     */
    public function getAllRoles() {
        try {
            $sql = "SELECT * FROM roles ORDER BY id ASC";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Logout user
     */
    public function logout() {
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }

    /**
     * Check session timeout
     */
    public function checkSessionTimeout() {
        if (isset($_SESSION['login_time'])) {
            if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
                $this->logout();
                return false;
            }
        }
        return true;
    }
}
?>
