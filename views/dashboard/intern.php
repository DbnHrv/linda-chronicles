<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="dashboard-container">
    <h2>Intern Dashboard</h2>
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
            <h3>Learning Resources</h3>
            <ul>
                <li>Pharmacy procedures documentation</li>
                <li>Medication information database</li>
                <li>Training materials and guides</li>
                <li>Mentorship schedule</li>
                <li>Performance evaluations</li>
            </ul>
        </div>

        <div class="card">
            <h3>Current Tasks</h3>
            <ul>
                <li>Complete weekly training modules</li>
                <li>Shadow licensed pharmacist for 4 hours</li>
                <li>Study pharmaceutical calculations</li>
                <li>Assist with inventory management</li>
            </ul>
        </div>

        <div class="card">
            <h3>Quick Actions</h3>
            <button class="btn btn-secondary">View Training Material</button>
            <button class="btn btn-secondary">Log Hours</button>
            <button class="btn btn-secondary">Contact Mentor</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
