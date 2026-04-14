<?php
/**
 * Process 6: Organizing Schedule
 * HR Personnel (manages) & Intern (views own schedule)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';

if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(6);

$processModel = new ProcessModel($pdo);
$message = '';
$message_type = '';
$is_hr     = ($_SESSION['role_id'] == ROLE_HR_PERSONNEL);
$is_intern = ($_SESSION['role_id'] == ROLE_INTERN);

if ($is_hr) {
    $schedules = $processModel->getAllSchedules();
} else {
    $my_schedule = $processModel->getScheduleByIntern($_SESSION['user_id']);
}

// HR can update schedule status
if ($is_hr && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token';
        $message_type = 'error';
    } else {
        try {
            $schedule_id = intval($_POST['schedule_id'] ?? 0);
            $status      = sanitize($_POST['status'] ?? '');
            $notes       = sanitize($_POST['notes'] ?? '');
            if ($schedule_id <= 0 || empty($status)) throw new Exception('Schedule ID and status are required');
            $stmt = $pdo->prepare("UPDATE internship_schedules SET status=?, notes=? WHERE id=?");
            $stmt->execute([$status, $notes, $schedule_id]);
            $message = 'Schedule updated successfully';
            $message_type = 'success';
            $schedules = $processModel->getAllSchedules();
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organize Schedule | <?php echo APP_NAME; ?></title>
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
    <h1>Organizing Schedule <?php echo $is_hr ? '(HR Personnel)' : '(Intern View)'; ?></h1>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($is_intern): ?>
        <div class="content-section">
            <h2>My Internship Schedule</h2>
            <?php if (empty($my_schedule)): ?>
                <p style="color:var(--text3);">No schedule assigned yet. Please wait for HR to assign your schedule.</p>
            <?php else: ?>
                <div class="info-card">
                    <p><strong>Period:</strong> <?php echo date('M d, Y', strtotime($my_schedule['start_date'])); ?> – <?php echo date('M d, Y', strtotime($my_schedule['end_date'])); ?></p>
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($my_schedule['schedule_type']); ?> | <strong>Hours/Week:</strong> <?php echo $my_schedule['work_hours_per_week']; ?></p>
                    <?php if (!empty($my_schedule['department'])): ?><p><strong>Department:</strong> <?php echo htmlspecialchars($my_schedule['department']); ?></p><?php endif; ?>
                    <?php if (!empty($my_schedule['location'])): ?><p><strong>Location:</strong> <?php echo htmlspecialchars($my_schedule['location']); ?></p><?php endif; ?>
                    <?php if (!empty($my_schedule['notes'])): ?><p><strong>Schedule:</strong> <?php echo htmlspecialchars($my_schedule['notes']); ?></p><?php endif; ?>
                    <p><strong>Status:</strong> <span class="status-badge status-<?php echo strtolower($my_schedule['status']); ?>"><?php echo htmlspecialchars($my_schedule['status']); ?></span></p>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="content-section">
            <h2>All Intern Schedules</h2>
            <?php if (empty($schedules)): ?>
                <p style="color:var(--text3);">No schedules found. Create schedules in Process 5.</p>
            <?php else: ?>
                <?php foreach ($schedules as $sched): ?>
                    <div class="info-card">
                        <div style="display:flex; justify-content:space-between; align-items:start;">
                            <div>
                                <h3><?php echo htmlspecialchars($sched['first_name'] . ' ' . $sched['last_name']); ?></h3>
                                <p><strong>Period:</strong> <?php echo date('M d, Y', strtotime($sched['start_date'])); ?> – <?php echo date('M d, Y', strtotime($sched['end_date'])); ?></p>
                                <p><strong>Type:</strong> <?php echo htmlspecialchars($sched['schedule_type']); ?> | <?php echo $sched['work_hours_per_week']; ?> hrs/week</p>
                                <?php if (!empty($sched['department'])): ?>
                                    <p><strong>Dept:</strong> <?php echo htmlspecialchars($sched['department']); ?></p>
                                <?php endif; ?>
                                <p><strong>Status:</strong> <span class="status-badge"><?php echo htmlspecialchars($sched['status']); ?></span></p>
                            </div>
                            <button onclick="document.getElementById('update-<?php echo $sched['id']; ?>').style.display='block'" class="btn btn-sm">Update</button>
                        </div>
                        <div id="update-<?php echo $sched['id']; ?>" style="display:none; margin-top:15px; border-top:1px solid #2a2e3e; padding-top:15px;">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="schedule_id" value="<?php echo $sched['id']; ?>">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status">
                                        <option value="Scheduled" <?php echo $sched['status']=='Scheduled'?'selected':''; ?>>Scheduled</option>
                                        <option value="Ongoing" <?php echo $sched['status']=='Ongoing'?'selected':''; ?>>Ongoing</option>
                                        <option value="Completed" <?php echo $sched['status']=='Completed'?'selected':''; ?>>Completed</option>
                                        <option value="On Leave" <?php echo $sched['status']=='On Leave'?'selected':''; ?>>On Leave</option>
                                        <option value="Terminated" <?php echo $sched['status']=='Terminated'?'selected':''; ?>>Terminated</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" rows="2"><?php echo htmlspecialchars($sched['notes'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-sm">Save</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div style="margin-top:20px;">
        <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body>
</html>
