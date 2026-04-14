<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(12);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
try { $stmt=$pdo->prepare("SELECT id,product_name,product_code,current_stock FROM products WHERE is_active=1 ORDER BY product_name"); $stmt->execute(); $products=$stmt->fetchAll(); } catch(Exception $e){$products=[];}
try { $requisitions = $processModel->getRequisitionsByUser($_SESSION['user_id']); } catch(Exception $e){ $requisitions=[]; }
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='request_stock') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
    else { try {
        $pid=intval($_POST['product_id']??0); $qty=intval($_POST['quantity']??0); $reason=sanitize($_POST['reason']??'');
        if ($pid<=0||$qty<=0) throw new Exception('Valid product and quantity required.');
        $processModel->createStockRequisition($pid,$qty,$reason);
        $message='Stock requisition submitted.'; $message_type='success';
        try { $requisitions=$processModel->getRequisitionsByUser($_SESSION['user_id']); } catch(Exception $e){ $requisitions=[]; }
    } catch(Exception $e){$message=$e->getMessage();$message_type='error';} }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Request Stocks — <?php echo APP_NAME; ?></title>
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
<h1><i class="fas fa-shopping-cart" style="color:var(--accent);margin-right:10px"></i>Request Additional Stocks</h1>
<p class="subtitle">Submit a stock requisition request</p>
<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<div class="content-section"><h2>New Stock Request</h2>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="request_stock">
<div class="form-group"><label>Product <span class="required">*</span></label><select name="product_id" required><option value="">— Select —</option><?php foreach($products as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?> (Stock: <?php echo $p['current_stock']; ?>)</option><?php endforeach; ?></select></div>
<div class="form-group"><label>Quantity Needed <span class="required">*</span></label><input type="number" name="quantity" min="1" required></div>
<div class="form-group"><label>Reason</label><textarea name="reason" rows="3" placeholder="Why are additional stocks needed?"></textarea></div>
<button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Request</button>
</form></div>
<div class="content-section"><h2>My Requisitions</h2>
<?php if(empty($requisitions)): ?><div class="empty-state"><p>No requisitions yet.</p></div>
<?php else: foreach($requisitions as $r): $slug=strtolower($r['status']); ?>
<div class="info-card"><div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
<div><div style="font-size:14px;font-weight:700;color:var(--text)"><?php echo htmlspecialchars($r['product_name']); ?></div>
<p style="font-size:12px;color:var(--text3);margin-top:3px">Qty: <?php echo $r['quantity_needed']; ?> · <?php echo date('M d, Y',strtotime($r['created_at'])); ?></p>
<?php if($r['reason']): ?><p style="font-size:12px;color:var(--text2);margin-top:4px"><?php echo htmlspecialchars($r['reason']); ?></p><?php endif; ?>
</div><span class="status-badge status-<?php echo $slug; ?>"><?php echo $r['status']; ?></span>
</div></div>
<?php endforeach; endif; ?>
</div>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
