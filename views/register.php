<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="auth-container">
    <div class="auth-box">
        <h2>Register</h2>
        <form method="POST" action="<?php echo APP_URL; ?>/?action=register">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="form-group">
                <label for="first_name">First Name: <span class="required">*</span></label>
                <input type="text" id="first_name" name="first_name" required placeholder="Enter your first name">
            </div>

            <div class="form-group">
                <label for="middle_name">Middle Initial:</label>
                <input type="text" id="middle_name" name="middle_name" placeholder="Enter your middle name (optional)">
            </div>

            <div class="form-group">
                <label for="last_name">Last Name: <span class="required">*</span></label>
                <input type="text" id="last_name" name="last_name" required placeholder="Enter your last name">
            </div>

            <div class="form-group">
                <label for="email">Email: <span class="required">*</span></label>
                <input type="email" id="email" name="email" required placeholder="Enter your email">
            </div>

            <div class="form-group">
                <label for="role_id">Role: <span class="required">*</span></label>
                <select id="role_id" name="role_id" required>
                    <option value="">Select your role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>">
                            <?php echo htmlspecialchars($role['role_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="password">Password: <span class="required">*</span></label>
                <input type="password" id="password" name="password" required placeholder="Enter your password (min 6 characters)">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password: <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
            </div>

            <button type="submit" class="btn btn-primary">Register</button>
        </form>

        <div class="auth-link">
            <p>Already have an account? <a href="<?php echo APP_URL; ?>/?action=login">Login here</a></p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>
