<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(18);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
try { $payments = $processModel->getPaymentsByCustomer($_SESSION['user_id']); } catch(Exception $e){ $payments=[]; }
try { $ready_to_pay = $processModel->getDispensedPrescriptions($_SESSION['user_id']); } catch(Exception $e){ $ready_to_pay=[]; }
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='process_payment') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
    else { try {
        $rxid=intval($_POST['prescription_id']??0); $amount=floatval($_POST['total_amount']??0); $method=sanitize($_POST['payment_method']??'');
        if (!$rxid||$amount<=0||!$method) throw new Exception('All fields required.');
        $processModel->createPayment($rxid,$amount,$method);
        $message='Payment recorded. Status: Pending.'; $message_type='success';
        $payments=$processModel->getPaymentsByCustomer($_SESSION['user_id']);
        $ready_to_pay=$processModel->getDispensedPrescriptions($_SESSION['user_id']);
    } catch(Exception $e){$message=$e->getMessage();$message_type='error';} }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Process Payment — <?php echo APP_NAME; ?></title>
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
<h1><i class="fas fa-credit-card" style="color:var(--accent);margin-right:10px"></i>Process Payment</h1>
<p class="subtitle">Pay for your dispensed medications</p>
<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<div class="content-section"><h2>Make a Payment</h2>
<?php if(empty($ready_to_pay)): ?><div class="empty-state"><p>No dispensed prescriptions ready for payment.</p></div>
<?php else: ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="process_payment">
<div class="form-group"><label>Select Prescription <span class="required">*</span></label>
<select name="prescription_id" required><option value="">— Select —</option>
<?php foreach($ready_to_pay as $rx): ?><option value="<?php echo $rx['id']; ?>">Rx #<?php echo $rx['id']; ?> — <?php echo htmlspecialchars($rx['patient_name']); ?> (<?php echo htmlspecialchars($rx['medicines']??'Dispensed'); ?>)</option><?php endforeach; ?>
</select></div>
<div class="grid-2">
<div class="form-group"><label>Amount (₱) <span class="required">*</span></label><input type="number" name="total_amount" step="0.01" min="0.01" required placeholder="0.00"></div>
<div class="form-group"><label>Payment Method <span class="required">*</span></label>
<select name="payment_method" required><option value="">— Select —</option>
<?php foreach(['Cash','Credit Card','Debit Card','Bank Transfer'] as $m): ?><option value="<?php echo $m; ?>"><?php echo $m; ?></option><?php endforeach; ?>
</select></div>
</div>
<button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Process Payment</button>
</form><?php endif; ?>
</div>
<div class="content-section"><h2>Payment History</h2>
<?php if(empty($payments)): ?><div class="empty-state"><p>No payment records yet.</p></div>
<?php else: foreach($payments as $pay): $slug=strtolower($pay['status']); ?>
<div class="info-card"><div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
<div><div style="font-size:14px;font-weight:700;color:var(--text)">Rx #<?php echo $pay['prescription_id']; ?> — <?php echo htmlspecialchars($pay['patient_name']); ?></div>
<p style="font-size:12px;color:var(--text3);margin-top:3px"><?php echo htmlspecialchars($pay['payment_method']); ?> · <?php echo date('M d, Y H:i',strtotime($pay['created_at'])); ?></p>
</div>
<div style="text-align:right"><div style="font-size:20px;font-weight:700;color:var(--accent)">₱<?php echo number_format($pay['amount'],2); ?></div>
<span class="status-badge status-<?php echo $slug; ?>"><?php echo $pay['status']; ?></span>
</div></div></div>
<?php endforeach; endif; ?>
</div>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
