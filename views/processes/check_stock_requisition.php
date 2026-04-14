<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(13);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$requisitions = $processModel->getPendingRequisitions();
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='verify') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
    else { try {
        $rid=intval($_POST['requisition_id']??0); $status=sanitize($_POST['status']??''); $remarks=sanitize($_POST['remarks']??'');
        if (!$rid||!$status) throw new Exception('Required fields missing.');
        $processModel->verifyStockRequisition($rid,$status,$remarks);
        $message='Requisition '.$status.'.'; $message_type='success'; $requisitions=$processModel->getPendingRequisitions();
    } catch(Exception $e){$message=$e->getMessage();$message_type='error';} }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Check Stock Requisition — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px)}.modal.show{display:flex}.modal-content{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:28px;width:90%;max-width:500px}</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>
<div class="process-wrapper" style="max-width:1100px">
<h1><i class="fas fa-clipboard-list" style="color:var(--accent);margin-right:10px"></i>Check Stock Requisition Report</h1>
<p class="subtitle">Review and approve or reject stock requests from technicians</p>
<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<div class="content-section"><h2>Pending Requisitions</h2>
<?php if(empty($requisitions)): ?><div class="empty-state"><p>No pending requisitions.</p></div>
<?php else: ?>
<table class="submission-table">
<thead><tr><th>Product</th><th>Requested By</th><th>Qty Needed</th><th>Current Stock</th><th>Date</th><th>Action</th></tr></thead>
<tbody><?php foreach($requisitions as $r): ?>
<tr>
<td><div style="font-weight:600;color:var(--text)"><?php echo htmlspecialchars($r['product_name']); ?></div><div style="font-size:11px;color:var(--text3)"><?php echo htmlspecialchars($r['product_code']); ?></div></td>
<td><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?></td>
<td style="font-weight:600;color:var(--warn)"><?php echo $r['quantity_needed']; ?></td>
<td style="color:<?php echo $r['current_stock']<50?'var(--danger)':'var(--accent)'; ?>"><?php echo $r['current_stock']; ?></td>
<td><?php echo date('M d, Y',strtotime($r['created_at'])); ?></td>
<td><button onclick="openModal(<?php echo $r['id']; ?>,'<?php echo htmlspecialchars($r['product_name']); ?>')" class="btn btn-sm"><i class="fas fa-eye"></i> Review</button></td>
</tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</div>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<div id="verifyModal" class="modal">
<div class="modal-content">
<span style="float:right;font-size:22px;cursor:pointer;color:var(--text3)" onclick="closeModal()">&times;</span>
<h2 style="font-size:17px;font-weight:700;color:var(--text);margin-bottom:4px">Review Requisition</h2>
<p id="modal-product" style="font-size:13px;color:var(--text2);margin-bottom:18px"></p>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="verify"><input type="hidden" id="req_id" name="requisition_id">
<div class="form-group"><label>Decision <span class="required">*</span></label><select name="status" required><option value="">— Select —</option><option value="Approved">Approve</option><option value="Rejected">Reject</option></select></div>
<div class="form-group"><label>Remarks</label><textarea name="remarks" rows="3" placeholder="Add feedback…"></textarea></div>
<div style="display:flex;gap:10px;justify-content:flex-end"><button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Submit</button></div>
</form></div></div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
<script>
function openModal(id,name){document.getElementById('req_id').value=id;document.getElementById('modal-product').textContent='Product: '+name;document.getElementById('verifyModal').classList.add('show');}
function closeModal(){document.getElementById('verifyModal').classList.remove('show');}
window.addEventListener('click',e=>{if(e.target===document.getElementById('verifyModal'))closeModal();});
</script>
</body></html>
