<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="dashboard-container">
    <h2>HR Personnel Dashboard</h2>
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
            <h3>HR Administration</h3>
            <ul>
                <li>Employee recruitment and onboarding</li>
                <li>Performance management and evaluations</li>
                <li>Compensation and benefits administration</li>
                <li>Training and development coordination</li>
                <li>Compliance and regulatory reporting</li>
                <li>Employee relations and conflict resolution</li>
            </ul>
        </div>

        <div class="card">
            <h3>Pending Actions</h3>
            <ul>
                <li>Review 2 intern performance evaluations</li>
                <li>Process 3 new employee onboarding tasks</li>
                <li>Schedule recruitment interviews for Pharmacist position</li>
                <li>Update benefits documentation</li>
                <li>Complete compliance audit</li>
            </ul>
        </div>

        <div class="card">
            <h3>Quick Actions</h3>
            <button class="btn btn-secondary">Employee Directory</button>
            <button class="btn btn-secondary">Schedule Management</button>
            <button class="btn btn-secondary">Payroll System</button>
            <button class="btn btn-secondary">Reports & Analytics</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
