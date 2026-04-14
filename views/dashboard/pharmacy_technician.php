<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="dashboard-container">
    <h2>Pharmacy Technician Dashboard</h2>
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
            <h3>Key Duties</h3>
            <ul>
                <li>Compound medications and preparations</li>
                <li>Verify and fill prescriptions</li>
                <li>Maintain pharmacy equipment</li>
                <li>Handle dispensing and packaging</li>
                <li>Support clinical services</li>
            </ul>
        </div>

        <div class="card">
            <h3>Daily Assignments</h3>
            <ul>
                <li>Compound 8 medication preparations</li>
                <li>Verify and fill 12 prescriptions</li>
                <li>Conduct equipment maintenance check</li>
                <li>Process mail-order refills</li>
            </ul>
        </div>

        <div class="card">
            <h3>Quick Actions</h3>
            <button class="btn btn-secondary">View Assignments</button>
            <button class="btn btn-secondary">Log Activities</button>
            <button class="btn btn-secondary">Safety Report</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
