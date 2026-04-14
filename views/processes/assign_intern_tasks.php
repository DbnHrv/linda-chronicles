<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(8);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$is_hr     = ((int)$_SESSION['role_id'] === ROLE_HR_PERSONNEL);
$is_intern = ((int)$_SESSION['role_id'] === ROLE_INTERN);
if ($is_hr) {
    $all_tasks = $processModel->getAllTasks();
    try { $stmt=$pdo->prepare("SELECT u.id,u.first_name,u.last_name FROM users u JOIN internship_submissions s ON s.user_id=u.id WHERE u.role_id=? AND s.status='Approved' ORDER BY u.first_name"); $stmt->execute([ROLE_INTERN]); $interns=$stmt->fetchAll(); } catch(Exception $e){$interns=[];}
    if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='assign_task') {
        if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
        else { try {
            $intern_id=intval($_POST['intern_id']??0); $title=sanitize($_POST['task_title']??''); $desc=sanitize($_POST['task_description']??''); $deadline=sanitize($_POST['task_deadline']??'')?:null;
            if (!$intern_id||!$title) throw new Exception('Intern and task title are required.');
            $processModel->assignTask($intern_id,$title,$desc,$deadline);
            $message='Task assigned.'; $message_type='success'; $all_tasks=$processModel->getAllTasks();
        } catch(Exception $e){$message=$e->getMessage();$message_type='error';} }
    }
} else {
    $my_tasks = $processModel->getTasksByIntern($_SESSION['user_id']);
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Internship Tasks — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>
<div class="process-wrapper">
<h1><i class="fas fa-tasks" style="color:var(--accent);margin-right:10px"></i>Internship Tasks <?php echo $is_hr?'(HR)':'(My Tasks)'; ?></h1>
<p class="subtitle"><?php echo $is_hr?'Assign tasks to approved interns':'Tasks assigned to you by HR'; ?></p>
<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if($is_hr): ?>
<div class="content-section"><h2>Assign New Task</h2>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="assign_task">
<div class="form-group"><label>Select Intern <span class="required">*</span></label><select name="intern_id" required><option value="">— Select —</option><?php foreach($interns as $i): ?><option value="<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['first_name'].' '.$i['last_name']); ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>Task Title <span class="required">*</span></label><input type="text" name="task_title" required placeholder="e.g. Assist in dispensing area"></div>
<div class="form-group"><label>Description</label><textarea name="task_description" rows="3" placeholder="Describe the task…"></textarea></div>
<div class="form-group"><label>Deadline</label><input type="date" name="task_deadline"></div>
<button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Assign Task</button>
</form></div>
<div class="content-section"><h2>All Assigned Tasks</h2>
<?php if(empty($all_tasks)): ?><div class="empty-state"><p>No tasks assigned yet.</p></div>
<?php else: foreach($all_tasks as $t): $slug=strtolower(str_replace(' ','-',$t['status'])); ?>
<div class="info-card">
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
<div style="flex:1"><div style="font-size:14px;font-weight:700;color:var(--text)"><?php echo htmlspecialchars($t['task_title']); ?></div>
<div style="font-size:12px;color:var(--text3);margin-top:2px"><?php echo htmlspecialchars($t['first_name'].' '.$t['last_name']); ?></div>
<?php if($t['task_description']): ?><p style="font-size:13px;color:var(--text2);margin-top:6px"><?php echo htmlspecialchars($t['task_description']); ?></p><?php endif; ?>
<?php if($t['task_deadline']): ?><p style="font-size:12px;color:var(--warn);margin-top:4px"><i class="fas fa-clock" style="margin-right:4px"></i>Due: <?php echo date('M d, Y',strtotime($t['task_deadline'])); ?></p><?php endif; ?>
</div><span class="status-badge status-<?php echo $slug; ?>"><?php echo $t['status']; ?></span>
</div></div>
<?php endforeach; endif; ?>
</div>
<?php else: ?>
<div class="content-section"><h2>My Tasks</h2>
<?php if(empty($my_tasks)): ?><div class="empty-state"><p>No tasks assigned yet.</p></div>
<?php else: foreach($my_tasks as $t): $slug=strtolower(str_replace(' ','-',$t['status'])); ?>
<div class="info-card">
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
<div style="flex:1"><div style="font-size:14px;font-weight:700;color:var(--text)"><?php echo htmlspecialchars($t['task_title']); ?></div>
<div style="font-size:12px;color:var(--text3);margin-top:2px">Assigned by <?php echo htmlspecialchars($t['assigned_by_name']); ?></div>
<?php if($t['task_description']): ?><p style="font-size:13px;color:var(--text2);margin-top:6px"><?php echo htmlspecialchars($t['task_description']); ?></p><?php endif; ?>
<?php if($t['task_deadline']): ?><p style="font-size:12px;color:var(--warn);margin-top:4px"><i class="fas fa-clock" style="margin-right:4px"></i>Due: <?php echo date('M d, Y',strtotime($t['task_deadline'])); ?></p><?php endif; ?>
</div><span class="status-badge status-<?php echo $slug; ?>"><?php echo $t['status']; ?></span>
</div></div>
<?php endforeach; endif; ?>
</div>
<?php endif; ?>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
