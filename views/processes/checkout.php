<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(18);

$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$checkout_items = [];
$total_amount = 0;

try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id as prescription_id, p.patient_name, p.doctor_name, p.upload_date,
               GROUP_CONCAT(CONCAT(pr.product_name, ' x', dm.quantity) SEPARATOR ', ') as medicines,
               SUM(pr.unit_price * dm.quantity) as total_amount
        FROM prescriptions p
        INNER JOIN dispensed_medicines dm ON p.id = dm.prescription_id
        LEFT JOIN products pr ON dm.product_id = pr.id
        WHERE p.customer_id = ? AND dm.payment_status = 'Pending'
        GROUP BY p.id
        ORDER BY p.upload_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $checkout_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($checkout_items as $item) {
        $total_amount += $item['total_amount'];
    }
} catch(Exception $e) {
    $checkout_items = [];
}

// Handle proceed to payment
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='proceed_to_payment') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) {
        $message='Invalid token.';
        $message_type='error';
    } else {
        try {
            if (empty($checkout_items)) {
                throw new Exception('No items to checkout.');
            }
            
            $_SESSION['checkout_items'] = $checkout_items;
            $_SESSION['checkout_total'] = $total_amount;
            
            header('Location: ' . APP_URL . '/views/processes/paymongo_payment.php');
            exit;
        } catch(Exception $e) {
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Checkout — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
.checkout-container{display:grid;grid-template-columns:1fr 350px;gap:24px;margin-bottom:28px}
.checkout-items{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px}
.checkout-item{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;margin-bottom:12px}
.item-info{flex:1}
.item-patient{font-size:13px;font-weight:700;color:var(--text)}
.item-meta{font-size:11px;color:var(--text3);margin-top:4px}
.item-medicines{font-size:12px;color:var(--text2);margin-top:6px;max-height:60px;overflow-y:auto}
.item-amount{text-align:right;min-width:100px}
.amount-label{font-size:10px;color:var(--text3);text-transform:uppercase;font-weight:700}
.amount-value{font-size:16px;font-weight:700;color:var(--accent);margin-top:4px}
.order-summary{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px;height:fit-content;position:sticky;top:20px}
.summary-title{font-size:13px;font-weight:700;color:var(--text);margin-bottom:16px;display:flex;align-items:center;gap:8px}
.summary-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);font-size:12px}
.summary-row:last-child{border-bottom:none}
.summary-label{color:var(--text2)}
.summary-value{color:var(--text);font-weight:600}
.summary-total{display:flex;justify-content:space-between;align-items:center;padding:16px 0;border-top:2px solid var(--border);margin-top:16px;font-size:14px;font-weight:700}
.summary-total-label{color:var(--text)}
.summary-total-value{color:var(--accent);font-size:18px}
.checkout-actions{display:flex;gap:10px;margin-top:20px}
.empty-state{text-align:center;padding:40px 20px;background:var(--surface2);border:1px dashed var(--border);border-radius:8px}
</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>

<div class="process-wrapper" style="max-width:1200px">
<h1><i class="fas fa-shopping-cart" style="color:var(--accent);margin-right:10px"></i>Checkout</h1>
<p class="subtitle">Review your dispensed medicines and proceed to payment</p>

<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<?php if(empty($checkout_items)): ?>
<div class="empty-state">
  <i class="fas fa-inbox" style="font-size:48px;margin-bottom:12px;display:block;color:var(--accent2)"></i>
  <p>No pending medicines to checkout.</p>
  <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary" style="margin-top:16px"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>
<?php else: ?>

<div class="checkout-container">
  <div class="checkout-items">
    <h2 style="margin-top:0;margin-bottom:16px">Order Items</h2>
    <?php foreach($checkout_items as $item): ?>
    <div class="checkout-item">
      <div class="item-info">
        <div class="item-patient">Rx #<?php echo $item['prescription_id']; ?> - <?php echo htmlspecialchars($item['patient_name']); ?></div>
        <div class="item-meta">Dr. <?php echo htmlspecialchars($item['doctor_name']); ?> · <?php echo date('M d, Y', strtotime($item['upload_date'])); ?></div>
        <div class="item-medicines"><strong>Medicines:</strong> <?php echo htmlspecialchars($item['medicines']); ?></div>
      </div>
      <div class="item-amount">
        <div class="amount-label">Amount</div>
        <div class="amount-value">₱<?php echo number_format($item['total_amount'], 2); ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="order-summary">
    <div class="summary-title"><i class="fas fa-receipt"></i> Order Summary</div>
    <div class="summary-row">
      <span class="summary-label">Items</span>
      <span class="summary-value"><?php echo count($checkout_items); ?></span>
    </div>
    <div class="summary-row">
      <span class="summary-label">Subtotal</span>
      <span class="summary-value">₱<?php echo number_format($total_amount, 2); ?></span>
    </div>
    <div class="summary-row">
      <span class="summary-label">Shipping</span>
      <span class="summary-value">₱0.00</span>
    </div>
    <div class="summary-row">
      <span class="summary-label">Tax</span>
      <span class="summary-value">₱0.00</span>
    </div>
    <div class="summary-total">
      <span class="summary-total-label">Total</span>
      <span class="summary-total-value">₱<?php echo number_format($total_amount, 2); ?></span>
    </div>
    
    <form method="POST" style="margin-top:20px">
      <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
      <input type="hidden" name="action" value="proceed_to_payment">
      <div class="checkout-actions">
        <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary" style="flex:1"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
        <button type="submit" class="btn btn-primary" style="flex:1"><i class="fas fa-credit-card"></i> Proceed to Payment</button>
      </div>
    </form>
  </div>
</div>

<?php endif; ?>
</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
