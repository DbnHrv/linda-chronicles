<?php require __DIR__ . '/layouts/header.php'; ?>

<div class="auth-container">
    <div class="auth-box">
        <h2>Login</h2>
        <form method="POST" action="<?php echo APP_URL; ?>/?action=login">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email">
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">
            </div>

            <button type="submit" class="btn btn-primary">Login</button>
        </form>

        <div class="auth-link">
            <p>Don't have an account? <a href="<?php echo APP_URL; ?>/?action=register">Register here</a></p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/layouts/footer.php'; ?>
