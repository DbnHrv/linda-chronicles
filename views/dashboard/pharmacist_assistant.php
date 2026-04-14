<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="dashboard-container">
    <h2>Pharmacist Assistant Dashboard</h2>
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
            <h3>Responsibilities</h3>
            <ul>
                <li>Assist pharmacists with prescription processing</li>
                <li>Manage inventory and stock levels</li>
                <li>Customer assistance and consultations</li>
                <li>Pharmacy housekeeping and organization</li>
                <li>Insurance verification support</li>
            </ul>
        </div>

        <div class="card">
            <h3>Pending Tasks</h3>
            <ul>
                <li>Process 5 pending prescriptions</li>
                <li>Verify insurance for 3 customers</li>
                <li>Update inventory records</li>
                <li>Stock shelves in aisle B and C</li>
            </ul>
        </div>

        <div class="card">
            <h3>Quick Actions</h3>
            <button class="btn btn-secondary">View Prescriptions</button>
            <button class="btn btn-secondary">Check Inventory</button>
            <button class="btn btn-secondary">File Report</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
