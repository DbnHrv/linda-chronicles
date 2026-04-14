<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(11);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$reports = $processModel->getInventoryReports();
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='verify_report') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
    else { try {
        $rid=intval($_POST['report_id']??0); $status=sanitize($_POST['status']??''); $remarks=sanitize($_POST['remarks']??'');
        if (!$rid||!$status) throw new Exception('Report and decision required.');
        $processModel->verifyInventoryReport($rid,$status,$remarks);
        $message='Report '.$status.'.'; $message_type='success'; $reports=$processModel->getInventoryReports();
    } catch(Exception $e){$message=$e->getMessage();$message_type='error';} }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Check Inventory Report — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
.modal.show{display:flex}.modal-content{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:28px;width:90%;max-width:500px;box-shadow:0 8px 40px rgba(0,0,0,.5)}
</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>
<div class="process-wrapper">
<h1><i class="fas fa-check-double" style="color:var(--accent);margin-right:10px"></i>Check Inventory Report</h1>
<p class="subtitle">Verify and validate inventory reports</p>
<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<div class="content-section"><h2>Inventory Reports</h2>
<?php if(empty($reports)): ?><div class="empty-state"><p>No reports found.</p></div>
<?php else: foreach($reports as $r): $slug=strtolower($r['verification_status']); ?>
<div class="info-card"><div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
<div style="flex:1"><div style="font-size:14px;font-weight:700;color:var(--text)">Report #<?php echo $r['id']; ?> — Count #<?php echo $r['inventory_id']; ?></div>
<p style="font-size:12px;color:var(--text3);margin-top:3px">By <?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?> · <?php echo $r['total_items']; ?> items · <?php echo date('M d, Y',strtotime($r['created_at'])); ?></p>
<?php if($r['report_details']): ?><p style="font-size:12px;color:var(--text2);margin-top:4px"><?php echo htmlspecialchars(substr($r['report_details'],0,150)); ?></p><?php endif; ?>
<?php if($r['remarks']): ?><p style="font-size:12px;color:var(--accent2);margin-top:4px"><strong>Remarks:</strong> <?php echo htmlspecialchars($r['remarks']); ?></p><?php endif; ?>
</div>
<div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
<span class="status-badge status-<?php echo $slug; ?>"><?php echo $r['verification_status']; ?></span>
<?php if($r['verification_status']==='Pending'): ?><button onclick="openModal(<?php echo $r['id']; ?>)" class="btn btn-sm"><i class="fas fa-check"></i> Verify</button><?php endif; ?>
</div></div></div>
<?php endforeach; endif; ?>
</div>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<div id="verifyModal" class="modal">
<div class="modal-content">
<span style="float:right;font-size:22px;cursor:pointer;color:var(--text3)" onclick="closeModal()">&times;</span>
<h2 style="font-size:17px;font-weight:700;color:var(--text);margin-bottom:18px">Verify Report</h2>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="verify_report"><input type="hidden" id="report_id" name="report_id">
<div class="form-group"><label>Decision <span class="required">*</span></label><select id="vstatus" name="status" required><option value="">— Select —</option><option value="Verified">Verify (Approve)</option><option value="Rejected">Reject</option></select></div>
<div class="form-group"><label>Remarks</label><textarea name="remarks" rows="3" placeholder="Add feedback…"></textarea></div>
<div style="display:flex;gap:10px;justify-content:flex-end"><button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Submit</button></div>
</form></div></div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
<script>
function openModal(id){document.getElementById('report_id').value=id;document.getElementById('verifyModal').classList.add('show');}
function closeModal(){document.getElementById('verifyModal').classList.remove('show');}
window.addEventListener('click',e=>{if(e.target===document.getElementById('verifyModal'))closeModal();});
</script>
</body></html>
