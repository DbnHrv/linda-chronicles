<?php
/**
 * Process 5: Presenting the Schedule and Other Requirements
 * HR Personnel Role - Creates and presents internship schedules to interns
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';

if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(5);

$processModel = new ProcessModel($pdo);
$message = '';
$message_type = '';

// Load all schedules
try { $schedules = $processModel->getAllSchedules(); } catch(Exception $e){ $schedules=[]; }

// Handle schedule creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create_schedule') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token';
        $message_type = 'error';
    } else {
        try {
            $intern_id  = intval($_POST['intern_id'] ?? 0);
            $start_date = sanitize($_POST['start_date'] ?? '');
            $end_date   = sanitize($_POST['end_date'] ?? '');
            if ($intern_id <= 0 || empty($start_date) || empty($end_date)) {
                throw new Exception('Intern, start date, and end date are required');
            }
            $data = [
                'schedule_type'       => sanitize($_POST['schedule_type'] ?? 'Full-Time'),
                'work_hours_per_week' => intval($_POST['work_hours_per_week'] ?? 40),
                'location'            => sanitize($_POST['location'] ?? ''),
                'department'          => sanitize($_POST['department'] ?? ''),
                'daily_schedule'      => sanitize($_POST['daily_schedule'] ?? ''),
            ];
            $processModel->createSchedule($intern_id, $start_date, $end_date, $data);
            $message = 'Schedule created and presented to intern successfully';
            $message_type = 'success';
            try { $schedules = $processModel->getAllSchedules(); } catch(Exception $e){ $schedules=[]; }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Load approved interns only
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, s.school_name, s.course
        FROM users u
        JOIN internship_submissions s ON s.user_id = u.id
        WHERE u.role_id = ? AND u.is_active = 1 AND s.status = 'Approved'
        ORDER BY u.first_name
    ");
    $stmt->execute([ROLE_INTERN]);
    $interns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $interns = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Present Schedule | <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
</head>
<body>
<nav class="navbar">
    <div class="navbar-content">
        <h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i>Linda Chronicles</h2>
        <div class="navbar-right">
            <span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']] ?? 'User'); ?></span>
            <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="process-wrapper">
    <h1>Presenting Schedule and Requirements</h1>
    <p class="subtitle">Create and present internship schedules to interns</p>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="content-section">
        <h2>Create New Schedule</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="create_schedule">

            <div class="form-group">
                <label for="intern_id">Select Intern *</label>
                <select id="intern_id" name="intern_id" required>
                    <option value="">-- Select Intern --</option>
                    <?php if (empty($interns)): ?>
                        <option value="" disabled>No approved interns yet</option>
                    <?php else: ?>
                        <?php foreach ($interns as $intern): ?>
                            <option value="<?php echo $intern['id']; ?>">
                                <?php echo htmlspecialchars($intern['first_name'] . ' ' . $intern['last_name']); ?>
                                — <?php echo htmlspecialchars($intern['school_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="start_date">Start Date *</label>
                    <input type="date" id="start_date" name="start_date" required>
                </div>
                <div class="form-group">
                    <label for="end_date">End Date *</label>
                    <input type="date" id="end_date" name="end_date" required>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="schedule_type">Schedule Type</label>
                    <select id="schedule_type" name="schedule_type">
                        <option value="Full-Time">Full-Time</option>
                        <option value="Part-Time">Part-Time</option>
                        <option value="Flexible">Flexible</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="work_hours_per_week">Hours per Week</label>
                    <input type="number" id="work_hours_per_week" name="work_hours_per_week" value="40" min="1" max="60">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="department">Department</label>
                    <input type="text" id="department" name="department" placeholder="e.g., Dispensing Area">
                </div>
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" placeholder="e.g., Main Branch">
                </div>
            </div>

            <div class="form-group">
                <label for="daily_schedule">Daily Schedule Details</label>
                <textarea id="daily_schedule" name="daily_schedule" rows="4" placeholder="e.g., Mon-Fri 8AM-5PM, 1hr lunch break..."></textarea>
            </div>

            <button type="submit" class="btn">Create & Present Schedule</button>
        </form>
    </div>

    <div class="content-section">
        <h2>Existing Schedules</h2>
        <?php if (empty($schedules)): ?>
            <p style="color:#999;">No schedules created yet.</p>
        <?php else: ?>
            <?php foreach ($schedules as $sched): ?>
                <div class="info-card">
                    <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:6px"><?php echo htmlspecialchars($sched['first_name'] . ' ' . $sched['last_name']); ?></div>
                    <p><strong>Period:</strong> <?php echo date('M d, Y', strtotime($sched['start_date'])); ?> – <?php echo date('M d, Y', strtotime($sched['end_date'])); ?></p>
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($sched['schedule_type']); ?> | <strong>Hours/Week:</strong> <?php echo $sched['work_hours_per_week']; ?></p>
                    <?php if (!empty($sched['department'])): ?>
                        <p><strong>Department:</strong> <?php echo htmlspecialchars($sched['department']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($sched['notes'])): ?>
                        <p><strong>Schedule:</strong> <?php echo htmlspecialchars($sched['notes']); ?></p>
                    <?php endif; ?>
                    <p><strong>Status:</strong> <span class="status-badge status-<?php echo strtolower($sched['status']); ?>"><?php echo htmlspecialchars($sched['status']); ?></span></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div style="margin-top:20px;">
        <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body>
</html>
