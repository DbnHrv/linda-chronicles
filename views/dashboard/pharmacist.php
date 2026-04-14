<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="dashboard-container">
    <h2>Pharmacist Dashboard</h2>
    <div class="welcome-message">
        <p>Welcome to your dashboard, <?php echo htmlspecialchars($_SESSION['user_full_name']); ?>!</p>
    </div>

    <div class="dashboard-content">
        <div class="card">
            <h3>Your Profile</h3>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['user_email']); ?></p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['user_role_name']); ?></p>
            <p><strong>License Status:</strong> <span class="status-active">Active</span></p>
        </div>

        <div class="card">
            <h3>Professional Responsibilities</h3>
            <ul>
                <li>Clinical consultations and medication therapy management</li>
                <li>Prescription verification and review</li>
                <li>Drug interaction screening</li>
                <li>Supervise pharmacy staff</li>
                <li>Maintain patient medication records</li>
                <li>Continuing education requirements</li>
            </ul>
        </div>

        <div class="card">
            <h3>Active Cases & Reviews</h3>
            <ul>
                <li>Review 3 patient medication profiles</li>
                <li>Process 15 pending prescriptions</li>
                <li>Conduct medication counseling sessions (2 scheduled)</li>
                <li>Supervise technician certifications</li>
            </ul>
        </div>

        <div class="card">
            <h3>Quick Actions</h3>
            <button class="btn btn-secondary">Patient Records</button>
            <button class="btn btn-secondary">Clinical Notes</button>
            <button class="btn btn-secondary">Team Management</button>
            <button class="btn btn-secondary">CE Requirements</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
