<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="dashboard-container">
    <h2>Customer Dashboard</h2>
    <div class="welcome-message">
        <p>Welcome to your dashboard, <?php echo htmlspecialchars($_SESSION['user_full_name']); ?>!</p>
    </div>

    <div class="dashboard-content">
        <div class="card">
            <h3>Your Profile</h3>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['user_email']); ?></p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['user_role_name']); ?></p>
        </div>

        <div class="card">
            <h3>Available Services</h3>
            <ul>
                <li>Browse medications and products</li>
                <li>Place orders</li>
                <li>Track order history</li>
                <li>View prescriptions</li>
                <li>Customer support</li>
            </ul>
        </div>

        <div class="card">
            <h3>Quick Actions</h3>
            <button class="btn btn-secondary">Browse Products</button>
            <button class="btn btn-secondary">View Orders</button>
            <button class="btn btn-secondary">Contact Support</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
