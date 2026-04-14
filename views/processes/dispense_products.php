<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(17);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
try { $stmt=$pdo->prepare("SELECT id,product_code,product_name,current_stock FROM products WHERE is_active=1 ORDER BY product_name"); $stmt->execute(); $products=$stmt->fetchAll(); } catch(Exception $e){$products=[];}
$available_prescriptions = $processModel->getVerifiedPrescriptions();
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='dispense') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
    else { try {
        $rxid=intval($_POST['prescription_id']??0); $pid=intval($_POST['product_id']??0); $qty=intval($_POST['quantity']??0);
        if (!$rxid||!$pid||!$qty) throw new Exception('All fields required.');
        $processModel->dispenseMedicine($rxid,$pid,$qty);
        $message='Medicine dispensed successfully.'; $message_type='success';
        $available_prescriptions=$processModel->getVerifiedPrescriptions();
        $stmt=$pdo->prepare("SELECT id,product_code,product_name,current_stock FROM products WHERE is_active=1 ORDER BY product_name"); $stmt->execute(); $products=$stmt->fetchAll();
    } catch(Exception $e){$message=$e->getMessage();$message_type='error';} }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dispense Products — <?php echo APP_NAME; ?></title>
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
<h1><i class="fas fa-pills" style="color:var(--accent);margin-right:10px"></i>Dispense Product</h1>
<p class="subtitle">Dispense medications to customers with verified prescriptions</p>
<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<div class="content-section"><h2>Dispense Medicine</h2>
<?php if(empty($available_prescriptions)): ?><div class="empty-state"><p>No verified prescriptions available to dispense.</p></div>
<?php else: ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="dispense">
<div class="form-group"><label>Select Prescription <span class="required">*</span></label>
<select name="prescription_id" required><option value="">— Select —</option>
<?php foreach($available_prescriptions as $rx): ?><option value="<?php echo $rx['id']; ?>">Rx #<?php echo $rx['id']; ?> — <?php echo htmlspecialchars($rx['patient_name']); ?> (Dr. <?php echo htmlspecialchars($rx['doctor_name']); ?>) · <?php echo date('M d, Y',strtotime($rx['upload_date'])); ?></option><?php endforeach; ?>
</select></div>
<div class="grid-2">
<div class="form-group"><label>Product to Dispense <span class="required">*</span></label><select name="product_id" required><option value="">— Select —</option><?php foreach($products as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?> (Stock: <?php echo $p['current_stock']; ?>)</option><?php endforeach; ?></select></div>
<div class="form-group"><label>Quantity <span class="required">*</span></label><input type="number" name="quantity" min="1" required></div>
</div>
<button type="submit" class="btn btn-primary" onclick="return confirm('Confirm dispensing?')"><i class="fas fa-check"></i> Dispense Medicine</button>
</form><?php endif; ?>
</div>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
