<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(16);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$availability_result = null;
try { $stmt=$pdo->prepare("SELECT id,product_code,product_name,current_stock FROM products WHERE is_active=1 ORDER BY product_name"); $stmt->execute(); $products=$stmt->fetchAll(); } catch(Exception $e){$products=[];}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='check_availability') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
    else { try {
        $pid=intval($_POST['product_id']??0); $qty=intval($_POST['quantity']??0);
        if ($pid<=0||$qty<=0) throw new Exception('Valid product and quantity required.');
        $availability_result=$processModel->checkProductAvailability($pid,$qty);
    } catch(Exception $e){$message=$e->getMessage();$message_type='error';} }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Check Availability — <?php echo APP_NAME; ?></title>
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
<h1><i class="fas fa-search" style="color:var(--accent);margin-right:10px"></i>Check Product Availability</h1>
<p class="subtitle">Verify stock levels before dispensing</p>
<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<div class="content-section"><h2>Check Availability</h2>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="check_availability">
<div class="grid-2">
<div class="form-group"><label>Product <span class="required">*</span></label><select name="product_id" required><option value="">— Select —</option><?php foreach($products as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?> (<?php echo $p['product_code']; ?>)</option><?php endforeach; ?></select></div>
<div class="form-group"><label>Quantity Needed <span class="required">*</span></label><input type="number" name="quantity" min="1" required></div>
</div>
<button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Check</button>
</form>
<?php if($availability_result): ?>
<div class="result-box result-<?php echo $availability_result['available']?'available':'unavailable'; ?>" style="margin-top:20px">
<h3 style="color:<?php echo $availability_result['available']?'var(--accent)':'var(--danger)'; ?>;font-size:16px;margin-bottom:8px">
<i class="fas fa-<?php echo $availability_result['available']?'check-circle':'times-circle'; ?>"></i>
<?php echo $availability_result['available']?'Available':'Not Available'; ?>
</h3>
<p><strong>Product:</strong> <?php echo htmlspecialchars($availability_result['product_name']); ?></p>
<p><strong>Current Stock:</strong> <?php echo $availability_result['quantity']; ?> units</p>
<?php if($availability_result['available']): ?>
<p style="color:var(--accent);margin-top:8px">Ready to dispense. <a href="<?php echo APP_URL; ?>/views/processes/dispense_products.php" style="color:var(--accent2);font-weight:600">Go to Dispense →</a></p>
<?php else: ?><p style="color:var(--danger);margin-top:8px">Insufficient stock. Consider requesting additional stocks.</p><?php endif; ?>
</div><?php endif; ?>
</div>
<div class="content-section"><h2>All Products Stock</h2>
<table class="submission-table">
<thead><tr><th>Code</th><th>Product</th><th>Current Stock</th><th>Status</th></tr></thead>
<tbody><?php foreach($products as $p): ?>
<tr><td style="color:var(--text3)"><?php echo htmlspecialchars($p['product_code']); ?></td>
<td><?php echo htmlspecialchars($p['product_name']); ?></td>
<td style="font-weight:600"><?php echo $p['current_stock']; ?></td>
<td><span class="status-badge <?php echo $p['current_stock']>50?'status-approved':'status-pending'; ?>"><?php echo $p['current_stock']>50?'In Stock':'Low Stock'; ?></span></td>
</tr><?php endforeach; ?>
</tbody></table>
</div>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
