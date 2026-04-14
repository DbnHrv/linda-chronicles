<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(7);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
try { $orientations = $processModel->getAllOrientations(); } catch(Exception $e){ $orientations = []; }
try { $stmt=$pdo->prepare("SELECT u.id,u.first_name,u.last_name FROM users u JOIN internship_submissions s ON s.user_id=u.id WHERE u.role_id=? AND s.status='Approved' ORDER BY u.first_name"); $stmt->execute([ROLE_INTERN]); $interns=$stmt->fetchAll(); } catch(Exception $e){$interns=[];}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='create_orientation') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
    else { try {
        $intern_id=intval($_POST['intern_id']??0); $date=sanitize($_POST['orientation_date']??''); $venue=sanitize($_POST['venue']??''); $content=sanitize($_POST['content']??'');
        if (!$intern_id||!$date) throw new Exception('Intern and date are required.');
        $processModel->createOrientationSession($intern_id,$date,$venue,$content);
        $message='Orientation scheduled.'; $message_type='success';
        try { $orientations=$processModel->getAllOrientations(); } catch(Exception $e){ $orientations=[]; }
    } catch(Exception $e){$message=$e->getMessage();$message_type='error';} }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Company Orientation — <?php echo APP_NAME; ?></title>
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
<h1><i class="fas fa-chalkboard-teacher" style="color:var(--accent);margin-right:10px"></i>Conduct Company Orientation</h1>
<p class="subtitle">Schedule orientation sessions for approved interns</p>
<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<div class="content-section"><h2>Schedule New Orientation</h2>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="create_orientation">
<div class="form-group"><label>Select Intern <span class="required">*</span></label>
<select name="intern_id" required><option value="">— Select —</option><?php foreach($interns as $i): ?><option value="<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['first_name'].' '.$i['last_name']); ?></option><?php endforeach; ?></select></div>
<div class="grid-2">
<div class="form-group"><label>Date & Time <span class="required">*</span></label><input type="datetime-local" name="orientation_date" required></div>
<div class="form-group"><label>Venue</label><input type="text" name="venue" placeholder="e.g. Conference Room, Main Office"></div>
</div>
<div class="form-group"><label>Agenda / Content</label><textarea name="content" rows="4" placeholder="Topics to be covered…"></textarea></div>
<button type="submit" class="btn btn-primary"><i class="fas fa-calendar-plus"></i> Schedule Orientation</button>
</form></div>
<div class="content-section"><h2>Orientation Sessions</h2>
<?php if(empty($orientations)): ?><div class="empty-state"><p>No sessions scheduled yet.</p></div>
<?php else: foreach($orientations as $o): $slug=strtolower($o['status']); ?>
<div class="info-card">
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
<div><div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:4px"><?php echo htmlspecialchars($o['first_name'].' '.$o['last_name']); ?></div>
<p><i class="fas fa-calendar" style="color:var(--text3);margin-right:6px"></i><?php echo date('F d, Y H:i',strtotime($o['orientation_date'])); ?></p>
<?php if($o['venue']): ?><p><i class="fas fa-map-marker-alt" style="color:var(--text3);margin-right:6px"></i><?php echo htmlspecialchars($o['venue']); ?></p><?php endif; ?>
<?php if($o['content']): ?><p style="margin-top:6px;color:var(--text2);font-size:12px"><?php echo nl2br(htmlspecialchars($o['content'])); ?></p><?php endif; ?>
</div><span class="status-badge status-<?php echo $slug; ?>"><?php echo $o['status']; ?></span>
</div></div>
<?php endforeach; endif; ?>
</div>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
