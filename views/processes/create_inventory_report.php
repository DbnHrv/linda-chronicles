<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(10);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
try { $stmt=$pdo->prepare("SELECT ic.*,u.first_name,u.last_name,(SELECT COUNT(*) FROM inventory_items ii WHERE ii.inventory_id=ic.id) AS item_count FROM inventory_counts ic JOIN users u ON ic.conducted_by=u.id WHERE ic.status='Completed' AND ic.id NOT IN (SELECT inventory_id FROM inventory_reports) ORDER BY ic.completed_at DESC"); $stmt->execute(); $pending_counts=$stmt->fetchAll(); } catch(Exception $e){$pending_counts=[];}
$existing_reports = $processModel->getInventoryReports();
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='create_report') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
    else { try {
        $inv_id=intval($_POST['inventory_id']??0); $details=sanitize($_POST['report_details']??'');
        if (!$inv_id) throw new Exception('Select a count.');
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM inventory_items WHERE inventory_id=?"); $stmt->execute([$inv_id]); $total=$stmt->fetchColumn();
        $processModel->createInventoryReport($inv_id,$total,$details);
        $message='Report created.'; $message_type='success';
        $stmt=$pdo->prepare("SELECT ic.*,u.first_name,u.last_name,(SELECT COUNT(*) FROM inventory_items ii WHERE ii.inventory_id=ic.id) AS item_count FROM inventory_counts ic JOIN users u ON ic.conducted_by=u.id WHERE ic.status='Completed' AND ic.id NOT IN (SELECT inventory_id FROM inventory_reports) ORDER BY ic.completed_at DESC"); $stmt->execute(); $pending_counts=$stmt->fetchAll();
        $existing_reports=$processModel->getInventoryReports();
    } catch(Exception $e){$message=$e->getMessage();$message_type='error';} }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Create Inventory Report — <?php echo APP_NAME; ?></title>
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
<h1><i class="fas fa-chart-bar" style="color:var(--accent);margin-right:10px"></i>Create Inventory Report</h1>
<p class="subtitle">Generate reports from completed inventory counts</p>
<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<div class="content-section"><h2>Create Report</h2>
<?php if(empty($pending_counts)): ?><div class="empty-state"><p>No completed counts available. Complete an inventory count first.</p></div>
<?php else: ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="create_report">
<div class="form-group"><label>Select Inventory Count <span class="required">*</span></label><select name="inventory_id" required><option value="">— Select —</option><?php foreach($pending_counts as $c): ?><option value="<?php echo $c['id']; ?>">Count #<?php echo $c['id']; ?> by <?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?> (<?php echo $c['item_count']; ?> items, <?php echo date('M d, Y',strtotime($c['completed_at'])); ?>)</option><?php endforeach; ?></select></div>
<div class="form-group"><label>Report Summary</label><textarea name="report_details" rows="4" placeholder="Findings, discrepancies, notes…"></textarea></div>
<button type="submit" class="btn btn-primary"><i class="fas fa-file-alt"></i> Create Report</button>
</form><?php endif; ?>
</div>
<div class="content-section"><h2>Existing Reports</h2>
<?php if(empty($existing_reports)): ?><div class="empty-state"><p>No reports yet.</p></div>
<?php else: foreach($existing_reports as $r): $slug=strtolower($r['verification_status']); ?>
<div class="info-card"><div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
<div><div style="font-size:14px;font-weight:700;color:var(--text)">Report #<?php echo $r['id']; ?> — Count #<?php echo $r['inventory_id']; ?></div>
<p style="font-size:12px;color:var(--text3);margin-top:3px">By <?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?> · <?php echo $r['total_items']; ?> items · <?php echo date('M d, Y',strtotime($r['created_at'])); ?></p>
<?php if($r['remarks']): ?><p style="font-size:12px;color:var(--text2);margin-top:4px"><?php echo htmlspecialchars($r['remarks']); ?></p><?php endif; ?>
</div><span class="status-badge status-<?php echo $slug; ?>"><?php echo $r['verification_status']; ?></span>
</div></div>
<?php endforeach; endif; ?>
</div>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
