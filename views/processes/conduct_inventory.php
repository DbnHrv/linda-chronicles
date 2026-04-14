<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(9);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
try { $stmt=$pdo->prepare("SELECT id,product_code,product_name,current_stock FROM products WHERE is_active=1 ORDER BY product_name"); $stmt->execute(); $products=$stmt->fetchAll(); } catch(Exception $e){$products=[];}
$my_counts = $processModel->getInventoryCountsByUser($_SESSION['user_id']);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
    else {
        $action=$_POST['action']??'';
        try {
            if ($action==='start_count') { $notes=sanitize($_POST['notes']??''); $id=$processModel->startInventoryCount($notes); $_SESSION['active_inventory_id']=$id; $message='Inventory count #'.$id.' started.'; $message_type='success'; $my_counts=$processModel->getInventoryCountsByUser($_SESSION['user_id']); }
            elseif ($action==='add_item') { $inv_id=intval($_POST['inventory_id']??0); $prod_id=intval($_POST['product_id']??0); $qty=intval($_POST['quantity']??0); $batch=sanitize($_POST['batch_number']??''); if (!$inv_id||!$prod_id||$qty<0) throw new Exception('All fields required.'); $processModel->addInventoryItem($inv_id,$prod_id,$qty,$batch); $message='Item recorded.'; $message_type='success'; }
            elseif ($action==='complete_count') { $inv_id=intval($_POST['inventory_id']??0); if (!$inv_id) throw new Exception('ID required.'); $processModel->completeInventoryCount($inv_id); unset($_SESSION['active_inventory_id']); $message='Count completed.'; $message_type='success'; $my_counts=$processModel->getInventoryCountsByUser($_SESSION['user_id']); }
        } catch(Exception $e){$message=$e->getMessage();$message_type='error';}
    }
}
$active_id = $_SESSION['active_inventory_id'] ?? null;
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Conduct Inventory — <?php echo APP_NAME; ?></title>
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
<h1><i class="fas fa-boxes" style="color:var(--accent);margin-right:10px"></i>Conduct Product Inventory</h1>
<p class="subtitle">Count and record pharmacy product inventory</p>
<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if($active_id): ?>
<div class="active-banner"><strong style="color:var(--accent)">Active Count #<?php echo $active_id; ?></strong><p style="margin-top:4px;color:var(--text2);font-size:13px">Add items below, then complete when done.</p></div>
<div class="content-section"><h2>Record Product Count</h2>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="add_item"><input type="hidden" name="inventory_id" value="<?php echo $active_id; ?>">
<div class="grid-2">
<div class="form-group"><label>Product <span class="required">*</span></label><select name="product_id" required><option value="">— Select —</option><?php foreach($products as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?> (<?php echo $p['product_code']; ?>)</option><?php endforeach; ?></select></div>
<div class="form-group"><label>Counted Quantity <span class="required">*</span></label><input type="number" name="quantity" min="0" required></div>
</div>
<div class="form-group"><label>Batch Number</label><input type="text" name="batch_number" placeholder="e.g. BATCH-2026-001"></div>
<button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Record Item</button>
</form></div>
<div class="content-section"><h2>Complete Count</h2>
<form method="POST" onsubmit="return confirm('Complete this inventory count?')"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="complete_count"><input type="hidden" name="inventory_id" value="<?php echo $active_id; ?>">
<button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Complete Inventory Count</button>
</form></div>
<?php else: ?>
<div class="content-section"><h2>Start New Inventory Count</h2>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="start_count">
<div class="form-group"><label>Notes</label><textarea name="notes" rows="3" placeholder="Any notes about this count…"></textarea></div>
<button type="submit" class="btn btn-primary"><i class="fas fa-play"></i> Start Count</button>
</form></div>
<?php endif; ?>
<div class="content-section"><h2>My Inventory Counts</h2>
<?php if(empty($my_counts)): ?><div class="empty-state"><p>No counts recorded yet.</p></div>
<?php else: foreach($my_counts as $c): ?>
<div class="info-card"><p><strong>Count #<?php echo $c['id']; ?></strong> — <span class="status-badge status-<?php echo strtolower(str_replace(' ','-',$c['status'])); ?>"><?php echo $c['status']; ?></span></p>
<p style="font-size:12px;color:var(--text3);margin-top:4px">Started: <?php echo date('M d, Y H:i',strtotime($c['created_at'])); ?><?php if($c['completed_at']): ?> · Completed: <?php echo date('M d, Y H:i',strtotime($c['completed_at'])); ?><?php endif; ?></p>
<?php if($c['notes']): ?><p style="font-size:12px;color:var(--text2);margin-top:4px"><?php echo htmlspecialchars($c['notes']); ?></p><?php endif; ?>
</div>
<?php endforeach; endif; ?>
</div>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
