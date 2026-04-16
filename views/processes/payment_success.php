<?php
require_once __DIR__ . '/../../config/config.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');

$payment_success = $_SESSION['payment_success'] ?? false;
$payment_ids = $_SESSION['payment_ids'] ?? [];
$payment_method = $_SESSION['payment_method'] ?? 'unknown';

if (!$payment_success) {
    redirect(APP_URL . '/views/processes/checkout.php');
}

// Map payment method to display name
$method_names = [
    'credit_card' => 'Credit Card',
    'gcash' => 'GCash',
    'bank_transfer' => 'Bank Transfer',
    'Cash' => 'Cash Payment',
    'paymongo' => 'PayMongo'
];
$method_display = $method_names[$payment_method] ?? 'Unknown';

// Clear session
unset($_SESSION['payment_success']);
unset($_SESSION['payment_ids']);
unset($_SESSION['payment_method']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Payment Successful — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
.success-container{text-align:center;padding:60px 20px}
.success-icon{font-size:80px;color:var(--accent);margin-bottom:20px;display:block;animation:bounce 0.6s}
@keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.success-title{font-size:28px;font-weight:700;color:var(--text);margin-bottom:12px}
.success-message{font-size:14px;color:var(--text2);margin-bottom:32px;max-width:500px;margin-left:auto;margin-right:auto}
.success-details{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:32px;max-width:600px;margin-left:auto;margin-right:auto;text-align:left}
.detail-row{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border);font-size:13px}
.detail-row:last-child{border-bottom:none}
.detail-label{color:var(--text3);font-weight:600}
.detail-value{color:var(--text);font-weight:600}
.success-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>

<div class="process-wrapper">
<div class="success-container">
  <i class="fas fa-check-circle success-icon"></i>
  <h1 class="success-title">Payment Successful!</h1>
  <p class="success-message">Your payment has been processed successfully. Your dispensed medicines are now ready for pickup.</p>
  
  <div class="success-details">
    <div class="detail-row">
      <span class="detail-label">Prescriptions Paid</span>
      <span class="detail-value"><?php echo count($payment_ids); ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Payment Method</span>
      <span class="detail-value"><?php echo htmlspecialchars($method_display); ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Payment Status</span>
      <span class="detail-value" style="color:<?php echo ($payment_method === 'Cash') ? 'var(--warn)' : 'var(--accent)'; ?>">
        <i class="fas fa-<?php echo ($payment_method === 'Cash') ? 'clock' : 'check-circle'; ?>"></i> 
        <?php echo ($payment_method === 'Cash') ? 'Pending Verification' : 'Verified'; ?>
      </span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Date & Time</span>
      <span class="detail-value"><?php echo date('M d, Y H:i:s'); ?></span>
    </div>
  </div>
  
  <div class="success-actions">
    <a href="<?php echo APP_URL; ?>/views/processes/view_dispensed_medicines.php" class="btn btn-primary"><i class="fas fa-pills"></i> View Dispensed Medicines</a>
    <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-home"></i> Back to Dashboard</a>
  </div>
</div>
</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
