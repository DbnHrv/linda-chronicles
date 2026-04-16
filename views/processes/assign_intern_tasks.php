<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(8);

$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$is_hr     = ((int)$_SESSION['role_id'] === ROLE_HR_PERSONNEL);
$is_intern = ((int)$_SESSION['role_id'] === ROLE_INTERN);

/* ── HR: assign tasks ── */
if ($is_hr) {
    try { $all_tasks = $processModel->getAllTasks(); } catch(Exception $e){ $all_tasks = []; }
    try {
        $stmt = $pdo->prepare("SELECT u.id,u.first_name,u.last_name FROM users u
            JOIN internship_submissions s ON s.user_id=u.id
            WHERE u.role_id=? AND s.status='Approved' ORDER BY u.first_name");
        $stmt->execute([ROLE_INTERN]);
        $interns = $stmt->fetchAll();
    } catch(Exception $e){ $interns = []; }

    if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='assign_task') {
        if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
        else { try {
            $intern_id = intval($_POST['intern_id']??0);
            $title     = sanitize($_POST['task_title']??'');
            $desc      = sanitize($_POST['task_description']??'');
            $deadline  = sanitize($_POST['task_deadline']??'') ?: null;
            if (!$intern_id||!$title) throw new Exception('Intern and task title are required.');
            $processModel->assignTask($intern_id,$title,$desc,$deadline);
            $message='Task assigned successfully.'; $message_type='success';
            try { $all_tasks=$processModel->getAllTasks(); } catch(Exception $e){ $all_tasks=[]; }
        } catch(Exception $e){ $message=$e->getMessage(); $message_type='error'; } }
    }

    // Handle adding remarks to completed tasks
    if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_remarks') {
        if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
        else { try {
            $task_id = intval($_POST['task_id']??0);
            $remarks = sanitize($_POST['remarks']??'');
            if (!$task_id) throw new Exception('Task not found.');
            
            $stmt = $pdo->prepare("UPDATE intern_tasks SET remarks=? WHERE id=?");
            $stmt->execute([$remarks, $task_id]);
            
            $message='Remarks added successfully.'; $message_type='success';
            try { $all_tasks=$processModel->getAllTasks(); } catch(Exception $e){ $all_tasks=[]; }
        } catch(Exception $e){ $message=$e->getMessage(); $message_type='error'; } }
    }
}

/* ── Intern: view + update status ── */
if ($is_intern) {
    try { $my_tasks = $processModel->getTasksByIntern($_SESSION['user_id']); }
    catch(Exception $e){ $my_tasks = []; }

    if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update_task_status') {
        if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
        else { try {
            $task_id = intval($_POST['task_id']??0);
            $status  = sanitize($_POST['status']??'');
            $notes   = sanitize($_POST['completion_notes']??'');
            $allowed = ['Pending','In Progress','Completed'];
            if (!$task_id || !in_array($status,$allowed)) throw new Exception('Invalid task or status.');

            // Verify task belongs to this intern
            $stmt = $pdo->prepare("SELECT id, proof_file FROM intern_tasks WHERE id=? AND intern_id=?");
            $stmt->execute([$task_id, $_SESSION['user_id']]);
            $existing_task = $stmt->fetch();
            if (!$existing_task) throw new Exception('Task not found.');

            // Handle proof file upload
            $proof_file = $existing_task['proof_file'] ?? null;
            if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['proof_file'];
                if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('File upload error.');
                if ($file['size'] > 10 * 1024 * 1024) throw new Exception('File exceeds 10MB limit.');
                $allowed_ext = ['jpg','jpeg','png','gif','pdf','doc','docx'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext)) throw new Exception('Only JPG, PNG, PDF, DOC/DOCX allowed.');
                $uploads_dir = realpath(__DIR__ . '/../../uploads') ?: (__DIR__ . '/../../uploads');
                if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
                $filename = 'task_'.$task_id.'_'.$_SESSION['user_id'].'_'.time().'.'.$ext;
                if (!move_uploaded_file($file['tmp_name'], $uploads_dir.DIRECTORY_SEPARATOR.$filename)) {
                    throw new Exception('Could not save file. Check uploads folder permissions.');
                }
                $proof_file = $filename;
            }

            $stmt = $pdo->prepare("UPDATE intern_tasks
                SET status=?, completion_notes=?, proof_file=?,
                    completed_at=".($status==='Completed'?'NOW()':'NULL')."
                WHERE id=? AND intern_id=?");
            $stmt->execute([$status, $notes, $proof_file, $task_id, $_SESSION['user_id']]);

            $message = $status==='Completed'
                ? 'Task marked as completed! Proof uploaded successfully.'
                : 'Task status updated to "'.$status.'".';
            $message_type = 'success';
            try { $my_tasks = $processModel->getTasksByIntern($_SESSION['user_id']); }
            catch(Exception $e){ $my_tasks = []; }
        } catch(Exception $e){ $message=$e->getMessage(); $message_type='error'; } }
    }
}

// Stats for intern
$pending_count   = 0; $inprogress_count = 0; $completed_count = 0;
if ($is_intern && !empty($my_tasks)) {
    foreach ($my_tasks as $t) {
        if ($t['status']==='Pending')     $pending_count++;
        if ($t['status']==='In Progress') $inprogress_count++;
        if ($t['status']==='Completed')   $completed_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Internship Tasks — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
/* task card */
.task-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 18px 20px;
    margin-bottom: 14px;
    transition: border-color .2s;
    position: relative;
    overflow: hidden;
}
.task-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; bottom: 0;
    width: 4px;
}
.task-card.status-pending::before    { background: var(--warn); }
.task-card.status-in-progress::before{ background: var(--accent2); }
.task-card.status-completed::before  { background: var(--accent); }
.task-card.status-overdue::before    { background: var(--danger); }
.task-card:hover { border-color: var(--border2); }

/* status toggle buttons */
.status-opts { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
.status-opt {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
    cursor: pointer; border: 2px solid transparent; transition: all .2s;
    background: var(--surface2);
}
.status-opt:hover { border-color: var(--border2); }
.status-opt.opt-pending     { color: var(--warn);    border-color: rgba(245,158,11,.3); }
.status-opt.opt-inprogress  { color: var(--accent2); border-color: rgba(56,189,248,.3); }
.status-opt.opt-completed   { color: var(--accent);  border-color: rgba(79,255,176,.3); }
.status-opt.opt-pending.active    { background: rgba(245,158,11,.15); border-color: var(--warn); }
.status-opt.opt-inprogress.active { background: rgba(56,189,248,.15); border-color: var(--accent2); }
.status-opt.opt-completed.active  { background: rgba(79,255,176,.15); border-color: var(--accent); }

/* notes expand */
.notes-box { display: none; margin-top: 12px; }
.notes-box textarea {
    width: 100%; background: var(--surface2); border: 1px solid var(--border2);
    border-radius: 8px; color: var(--text); font-size: 13px; font-family: inherit;
    padding: 10px 12px; resize: vertical; outline: none; min-height: 70px;
}
.notes-box textarea:focus { border-color: var(--accent); }

/* stat mini cards */
.task-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 24px; }
.task-stat { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; text-align: center; }
.task-stat-val { font-size: 26px; font-weight: 700; line-height: 1; }
.task-stat-lbl { font-size: 11px; color: var(--text3); margin-top: 4px; }

/* progress bar */
.task-progress { height: 6px; background: var(--surface3); border-radius: 3px; overflow: hidden; margin-top: 8px; }
.task-progress-fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg,var(--accent),var(--accent2)); transition: width .4s; }
</style>
</head>
<body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right">
<span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>

<div class="process-wrapper">
<h1><i class="fas fa-tasks" style="color:var(--accent);margin-right:10px"></i>
<?php echo $is_hr ? 'Assign Internship Tasks' : 'My Internship Tasks'; ?>
</h1>
<p class="subtitle"><?php echo $is_hr ? 'Assign tasks to approved interns and track their progress' : 'View your tasks and update their status as you work through them'; ?></p>

<?php if($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
<i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i>
<?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php /* ══════════ HR VIEW ══════════ */ if($is_hr): ?>

<div class="content-section">
    <h2>Assign New Task</h2>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="assign_task">
        <div class="form-group">
            <label>Select Intern <span class="required">*</span></label>
            <select name="intern_id" required>
                <option value="">— Select —</option>
                <?php foreach($interns as $i): ?>
                <option value="<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['first_name'].' '.$i['last_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Task Title <span class="required">*</span></label>
            <input type="text" name="task_title" required placeholder="e.g. Assist in dispensing area">
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="task_description" rows="3" placeholder="Describe what the intern needs to do…"></textarea>
        </div>
        <div class="form-group">
            <label>Deadline</label>
            <input type="date" name="task_deadline" min="<?php echo date('Y-m-d'); ?>">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Assign Task</button>
    </form>
</div>

<div class="content-section">
    <h2>All Assigned Tasks</h2>
    <?php if(empty($all_tasks)): ?>
    <div class="empty-state"><i class="fas fa-tasks"></i><p>No tasks assigned yet.</p></div>
    <?php else: foreach($all_tasks as $t):
        $slug = strtolower(str_replace(' ','-',$t['status']));
        $is_overdue = $t['task_deadline'] && strtotime($t['task_deadline']) < time() && $t['status'] !== 'Completed';
        if ($is_overdue) $slug = 'overdue';
    ?>
    <div class="task-card status-<?php echo $slug; ?>">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div style="flex:1;min-width:0">
                <div style="font-size:14px;font-weight:700;color:var(--text)"><?php echo htmlspecialchars($t['task_title']); ?></div>
                <div style="font-size:12px;color:var(--text3);margin-top:3px">
                    <i class="fas fa-user-graduate" style="margin-right:4px"></i>
                    <?php echo htmlspecialchars($t['first_name'].' '.$t['last_name']); ?>
                </div>
                <?php if($t['task_description']): ?>
                <p style="font-size:13px;color:var(--text2);margin-top:8px;line-height:1.5"><?php echo htmlspecialchars($t['task_description']); ?></p>
                <?php endif; ?>
                <div style="display:flex;gap:16px;margin-top:8px;flex-wrap:wrap">
                    <?php if($t['task_deadline']): ?>
                    <span style="font-size:12px;color:<?php echo $is_overdue?'var(--danger)':'var(--warn)'; ?>">
                        <i class="fas fa-calendar" style="margin-right:4px"></i>
                        Due: <?php echo date('M d, Y',strtotime($t['task_deadline'])); ?>
                        <?php if($is_overdue): ?><strong> (Overdue)</strong><?php endif; ?>
                    </span>
                    <?php endif; ?>
                    <?php if($t['completed_at']): ?>
                    <span style="font-size:12px;color:var(--accent)">
                        <i class="fas fa-check-circle" style="margin-right:4px"></i>
                        Completed: <?php echo date('M d, Y',strtotime($t['completed_at'])); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if(!empty($t['completion_notes'])): ?>
                <div style="margin-top:8px;background:var(--surface2);border-radius:6px;padding:8px 12px;font-size:12px;color:var(--text2)">
                    <strong style="color:var(--text)">Intern notes:</strong> <?php echo htmlspecialchars($t['completion_notes']); ?>
                </div>
                <?php endif; ?>
                <?php if(!empty($t['proof_file'])): ?>
                <?php $ext_p=strtolower(pathinfo($t['proof_file'],PATHINFO_EXTENSION)); $is_img=in_array($ext_p,['jpg','jpeg','png','gif']); ?>
                <div style="margin-top:8px;background:var(--surface2);border:1px solid rgba(79,255,176,.2);border-radius:8px;padding:10px 14px">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--accent);margin-bottom:8px">
                        <i class="fas fa-paperclip" style="margin-right:4px"></i>Proof of Completion
                    </div>
                    <?php if($is_img): ?>
                    <img src="<?php echo APP_URL; ?>/uploads/<?php echo urlencode($t['proof_file']); ?>"
                         alt="Proof" style="max-width:100%;max-height:180px;border-radius:6px;display:block;margin-bottom:8px;object-fit:cover">
                    <?php endif; ?>
                    <a href="<?php echo APP_URL; ?>/uploads/<?php echo urlencode($t['proof_file']); ?>"
                       target="_blank" rel="noopener"
                       class="btn btn-sm" style="background:rgba(56,189,248,.1);color:var(--accent2);border:1px solid rgba(56,189,248,.3)">
                        <i class="fas fa-<?php echo $is_img?'image':'file-alt'; ?>"></i>
                        <?php echo $is_img ? 'View Image' : 'View Document'; ?>
                    </a>
                </div>
                <?php endif; ?>

                <!-- HR Remarks Section (for completed tasks) -->
                <?php if($t['status'] === 'Completed'): ?>
                <div style="margin-top:12px;background:rgba(167,139,250,.08);border:1px solid rgba(167,139,250,.2);border-radius:8px;padding:12px 14px">
                    <form method="POST" style="display:flex;gap:8px;align-items:flex-end">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="add_remarks">
                        <input type="hidden" name="task_id" value="<?php echo $t['id']; ?>">
                        <div style="flex:1">
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--accent3);margin-bottom:6px">
                                <i class="fas fa-comment-dots" style="margin-right:4px"></i>Add Remarks
                            </div>
                            <textarea name="remarks" placeholder="Add feedback or remarks for the intern..." style="width:100%;background:var(--surface);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:12px;font-family:inherit;padding:8px 10px;resize:vertical;outline:none;min-height:50px;max-height:100px"><?php echo htmlspecialchars($t['remarks']??''); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm" style="background:rgba(167,139,250,.1);color:var(--accent3);border:1px solid rgba(167,139,250,.3);flex-shrink:0">
                            <i class="fas fa-save"></i> Save
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <span class="status-badge status-<?php echo $slug; ?>" style="flex-shrink:0"><?php echo $is_overdue?'Overdue':$t['status']; ?></span>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<?php /* ══════════ INTERN VIEW ══════════ */ elseif($is_intern): ?>

<!-- Task progress summary -->
<?php if(!empty($my_tasks)): ?>
<?php $total = count($my_tasks); $pct = $total > 0 ? round(($completed_count/$total)*100) : 0; ?>
<div class="task-stats">
    <div class="task-stat">
        <div class="task-stat-val" style="color:var(--warn)"><?php echo $pending_count; ?></div>
        <div class="task-stat-lbl">Pending</div>
    </div>
    <div class="task-stat">
        <div class="task-stat-val" style="color:var(--accent2)"><?php echo $inprogress_count; ?></div>
        <div class="task-stat-lbl">In Progress</div>
    </div>
    <div class="task-stat">
        <div class="task-stat-val" style="color:var(--accent)"><?php echo $completed_count; ?></div>
        <div class="task-stat-lbl">Completed</div>
    </div>
</div>
<div style="margin-bottom:24px">
    <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text2);margin-bottom:6px">
        <span>Overall Progress</span>
        <span style="font-weight:600;color:var(--accent)"><?php echo $pct; ?>% complete</span>
    </div>
    <div class="task-progress"><div class="task-progress-fill" style="width:<?php echo $pct; ?>%"></div></div>
</div>
<?php endif; ?>

<div class="content-section">
    <h2>My Tasks</h2>
    <?php if(empty($my_tasks)): ?>
    <div class="empty-state">
        <i class="fas fa-tasks"></i>
        <p>No tasks assigned yet. HR will assign tasks to you soon.</p>
    </div>
    <?php else: foreach($my_tasks as $t):
        $slug = strtolower(str_replace(' ','-',$t['status']));
        $is_overdue = $t['task_deadline'] && strtotime($t['task_deadline']) < time() && $t['status'] !== 'Completed';
        $display_slug = $is_overdue ? 'overdue' : $slug;
        $is_done = ($t['status'] === 'Completed');
    ?>
    <div class="task-card status-<?php echo $display_slug; ?>" id="task-<?php echo $t['id']; ?>">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div style="flex:1;min-width:0">
                <!-- Title + assigned by -->
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <div style="font-size:15px;font-weight:700;color:var(--text)"><?php echo htmlspecialchars($t['task_title']); ?></div>
                    <?php if($is_done): ?>
                    <span style="font-size:11px;color:var(--accent)"><i class="fas fa-check-circle"></i> Done</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:12px;color:var(--text3);margin-top:3px">
                    <i class="fas fa-user-tie" style="margin-right:4px"></i>
                    Assigned by <?php echo htmlspecialchars($t['assigned_by_name']); ?>
                </div>

                <!-- Description -->
                <?php if($t['task_description']): ?>
                <p style="font-size:13px;color:var(--text2);margin-top:8px;line-height:1.5"><?php echo htmlspecialchars($t['task_description']); ?></p>
                <?php endif; ?>

                <!-- Deadline -->
                <?php if($t['task_deadline']): ?>
                <div style="margin-top:8px;font-size:12px;color:<?php echo $is_overdue?'var(--danger)':($is_done?'var(--accent)':'var(--warn)'); ?>">
                    <i class="fas fa-calendar" style="margin-right:4px"></i>
                    Deadline: <?php echo date('F d, Y',strtotime($t['task_deadline'])); ?>
                    <?php if($is_overdue): ?><strong> — Overdue!</strong><?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Completion date -->
                <?php if($t['completed_at']): ?>
                <div style="margin-top:4px;font-size:12px;color:var(--accent)">
                    <i class="fas fa-check-circle" style="margin-right:4px"></i>
                    Completed on <?php echo date('M d, Y H:i',strtotime($t['completed_at'])); ?>
                </div>
                <?php endif; ?>

                <!-- Existing completion notes -->
                <?php if(!empty($t['completion_notes'])): ?>
                <div style="margin-top:8px;background:var(--surface2);border-radius:6px;padding:8px 12px;font-size:12px;color:var(--text2)">
                    <strong style="color:var(--text)">Your notes:</strong> <?php echo htmlspecialchars($t['completion_notes']); ?>
                </div>
                <?php endif; ?>

                <!-- HR Remarks (shown after task is completed) -->
                <?php if($is_done && !empty($t['remarks'])): ?>
                <div style="margin-top:8px;background:rgba(167,139,250,.08);border:1px solid rgba(167,139,250,.2);border-radius:6px;padding:10px 12px;font-size:12px;color:var(--text2)">
                    <div style="font-size:11px;font-weight:700;color:var(--accent3);margin-bottom:4px;text-transform:uppercase;letter-spacing:.05em">
                        <i class="fas fa-comment-dots" style="margin-right:4px"></i>HR Remarks
                    </div>
                    <?php echo nl2br(htmlspecialchars($t['remarks'])); ?>
                </div>
                <?php endif; ?>

                <!-- Proof file -->
                <?php if(!empty($t['proof_file'])): ?>
                <?php
                    $ext_proof = strtolower(pathinfo($t['proof_file'], PATHINFO_EXTENSION));
                    $is_image  = in_array($ext_proof, ['jpg','jpeg','png','gif']);
                ?>
                <div style="margin-top:8px;background:var(--surface2);border:1px solid rgba(79,255,176,.2);border-radius:8px;padding:10px 14px">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--accent);margin-bottom:8px">
                        <i class="fas fa-paperclip" style="margin-right:4px"></i>Proof of Completion
                    </div>
                    <?php if($is_image): ?>
                    <img src="<?php echo APP_URL; ?>/uploads/<?php echo urlencode($t['proof_file']); ?>"
                         alt="Proof" style="max-width:100%;max-height:200px;border-radius:6px;display:block;margin-bottom:8px;object-fit:cover">
                    <?php endif; ?>
                    <a href="<?php echo APP_URL; ?>/uploads/<?php echo urlencode($t['proof_file']); ?>"
                       target="_blank" rel="noopener"
                       class="btn btn-sm" style="background:rgba(56,189,248,.1);color:var(--accent2);border:1px solid rgba(56,189,248,.3)">
                        <i class="fas fa-<?php echo $is_image?'image':'file-alt'; ?>"></i>
                        <?php echo $is_image ? 'View Image' : 'View Document'; ?>
                    </a>
                </div>
                <?php endif; ?>

                <!-- ── STATUS UPDATE FORM ── -->
                <?php if(!$is_done): ?>
                <form method="POST" id="form-<?php echo $t['id']; ?>" style="margin-top:14px" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_task_status">
                    <input type="hidden" name="task_id" value="<?php echo $t['id']; ?>">
                    <input type="hidden" name="status" id="status-val-<?php echo $t['id']; ?>" value="<?php echo $t['status']; ?>">

                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);margin-bottom:8px">Update Status</div>
                    <div class="status-opts">
                        <button type="button"
                            class="status-opt opt-pending <?php echo $t['status']==='Pending'?'active':''; ?>"
                            onclick="setStatus(<?php echo $t['id']; ?>,'Pending')">
                            <i class="fas fa-hourglass-half"></i> Pending
                        </button>
                        <button type="button"
                            class="status-opt opt-inprogress <?php echo $t['status']==='In Progress'?'active':''; ?>"
                            onclick="setStatus(<?php echo $t['id']; ?>,'In Progress')">
                            <i class="fas fa-spinner"></i> In Progress
                        </button>
                        <button type="button"
                            class="status-opt opt-completed"
                            onclick="setStatus(<?php echo $t['id']; ?>,'Completed')">
                            <i class="fas fa-check-circle"></i> Mark as Done
                        </button>
                    </div>

                    <!-- Notes box — shown when marking complete -->
                    <div class="notes-box" id="notes-box-<?php echo $t['id']; ?>">
                        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--accent);margin-bottom:6px;margin-top:10px">
                            <i class="fas fa-sticky-note" style="margin-right:4px"></i>Completion Notes (optional)
                        </div>
                        <textarea name="completion_notes" placeholder="Describe what you did, any issues encountered…"><?php echo htmlspecialchars($t['completion_notes']??''); ?></textarea>

                        <!-- Proof file upload -->
                        <div style="margin-top:12px">
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--accent);margin-bottom:6px">
                                <i class="fas fa-paperclip" style="margin-right:4px"></i>Proof of Completion
                                <span style="color:var(--text3);font-weight:400;text-transform:none;letter-spacing:0"> — attach a photo or document</span>
                            </div>
                            <?php if(!empty($t['proof_file'])): ?>
                            <div style="background:var(--surface2);border:1px solid rgba(79,255,176,.2);border-radius:6px;padding:8px 12px;font-size:12px;color:var(--accent);margin-bottom:8px;display:flex;align-items:center;gap:8px">
                                <i class="fas fa-check-circle"></i>
                                <span>Already uploaded: <strong><?php echo htmlspecialchars($t['proof_file']); ?></strong></span>
                                <a href="<?php echo APP_URL; ?>/uploads/<?php echo urlencode($t['proof_file']); ?>" target="_blank" style="margin-left:auto;color:var(--accent2);font-size:11px"><i class="fas fa-external-link-alt"></i> View</a>
                            </div>
                            <div style="font-size:11px;color:var(--text3);margin-bottom:6px">Upload a new file to replace (optional):</div>
                            <?php endif; ?>
                            <div class="file-upload" onclick="document.getElementById('proof-<?php echo $t['id']; ?>').click()" style="padding:14px">
                                <i class="fas fa-cloud-upload-alt" style="font-size:20px;color:var(--text3);display:block;margin-bottom:5px"></i>
                                <p id="proof-label-<?php echo $t['id']; ?>" style="font-size:12px;margin:0">Click to upload photo or document (JPG, PNG, PDF, DOC — max 10MB)</p>
                                <input type="file" id="proof-<?php echo $t['id']; ?>" name="proof_file"
                                    accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx"
                                    onchange="updateProofLabel(<?php echo $t['id']; ?>, this)">
                            </div>
                        </div>

                        <div style="display:flex;gap:8px;margin-top:10px">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-check"></i> Confirm Complete
                            </button>
                            <button type="button" onclick="cancelComplete(<?php echo $t['id']; ?>)" class="btn btn-secondary btn-sm">
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Save button for Pending / In Progress -->
                    <div id="save-btn-<?php echo $t['id']; ?>" style="margin-top:10px;<?php echo $t['status']==='Pending'?'display:none':''; ?>">
                        <button type="submit" class="btn btn-sm" style="background:rgba(56,189,248,.1);color:var(--accent2);border:1px solid rgba(56,189,248,.3)">
                            <i class="fas fa-save"></i> Save Status
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <!-- Already completed — show re-open option -->
                <form method="POST" style="margin-top:12px">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="update_task_status">
                    <input type="hidden" name="task_id" value="<?php echo $t['id']; ?>">
                    <input type="hidden" name="status" value="In Progress">
                    <input type="hidden" name="completion_notes" value="">
                    <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Re-open this task?')">
                        <i class="fas fa-undo"></i> Re-open Task
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Status badge -->
            <span class="status-badge status-<?php echo $display_slug; ?>" style="flex-shrink:0">
                <?php echo $is_overdue ? 'Overdue' : $t['status']; ?>
            </span>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<?php endif; /* end role views */ ?>

<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>

<script>
function setStatus(taskId, status) {
    // Update hidden input
    document.getElementById('status-val-' + taskId).value = status;

    // Update button active states
    var form = document.getElementById('form-' + taskId);
    form.querySelectorAll('.status-opt').forEach(function(btn) {
        btn.classList.remove('active');
    });
    if (status === 'Pending')     form.querySelector('.opt-pending').classList.add('active');
    if (status === 'In Progress') form.querySelector('.opt-inprogress').classList.add('active');
    if (status === 'Completed')   form.querySelector('.opt-completed').classList.add('active');

    var notesBox = document.getElementById('notes-box-' + taskId);
    var saveBtn  = document.getElementById('save-btn-' + taskId);

    if (status === 'Completed') {
        // Show notes box, hide save btn
        notesBox.style.display = 'block';
        saveBtn.style.display  = 'none';
    } else {
        // Hide notes box, show save btn
        notesBox.style.display = 'none';
        saveBtn.style.display  = 'block';
        // Auto-submit for Pending/In Progress
        if (status === 'Pending' || status === 'In Progress') {
            document.getElementById('form-' + taskId).submit();
        }
    }
}

function cancelComplete(taskId) {
    // Reset to previous status
    var prev = '<?php echo "Pending"; ?>'; // fallback
    document.getElementById('notes-box-' + taskId).style.display = 'none';
    document.getElementById('save-btn-' + taskId).style.display  = 'none';
    document.querySelectorAll('#form-' + taskId + ' .status-opt').forEach(function(b){ b.classList.remove('active'); });
}

function updateProofLabel(taskId, input) {
    var label = document.getElementById('proof-label-' + taskId);
    if (!label) return;
    if (input.files && input.files[0]) {
        var f    = input.files[0];
        var size = (f.size / 1024).toFixed(0);
        label.innerHTML = '<i class="fas fa-check-circle" style="color:var(--accent);margin-right:4px"></i>'
            + '<span style="color:var(--accent)">' + f.name + ' (' + size + ' KB) — ready to upload</span>';
    }
}
</script>
</body>
</html>
