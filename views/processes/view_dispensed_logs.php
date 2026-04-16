<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(20);

$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$dispensed_logs = [];

try {
    $stmt = $pdo->prepare("
        SELECT dm.id, dm.quantity, dm.dispensed_at, dm.payment_status,
               p.product_code, p.product_name, p.generic_name, p.form, p.unit_price,
               pr.id as prescription_id, pr.patient_name, pr.doctor_name,
               u.first_name, u.last_name, u.email,
               cu.first_name as customer_first, cu.last_name as customer_last
        FROM dispensed_medicines dm
        JOIN products p ON dm.product_id = p.id
        JOIN prescriptions pr ON dm.prescription_id = pr.id
        JOIN users u ON dm.dispensed_by = u.id
        JOIN users cu ON pr.customer_id = cu.id
        WHERE dm.dispensed_by = ?
        ORDER BY dm.dispensed_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $dispensed_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $dispensed_logs = [];
}

// Calculate statistics
$total_dispensed = count($dispensed_logs);
$pending_payment = 0;
$verified_payment = 0;
$total_revenue = 0;

foreach ($dispensed_logs as $log) {
    if ($log['payment_status'] === 'Pending') {
        $pending_payment++;
    } else {
        $verified_payment++;
    }
    $total_revenue += ($log['quantity'] * $log['unit_price']);
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dispensed Logs — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
.stat-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:20px}
.stat{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;display:flex;align-items:center;gap:12px}
.stat-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.stat-val{font-size:20px;font-weight:700;color:var(--text);line-height:1}
.stat-lbl{font-size:11px;color:var(--text3);margin-top:2px}
.log-table{width:100%;border-collapse:collapse;margin-bottom:20px}
.log-table thead{background:var(--surface2);border-bottom:2px solid var(--border)}
.log-table th{padding:12px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:11px}
.log-table td{padding:12px;border-bottom:1px solid var(--border);font-size:12px}
.log-table tbody tr:hover{background:var(--surface2)}
.product-code{color:var(--accent);font-weight:700;font-family:monospace;font-size:10px}
.product-name{color:var(--text);font-weight:600}
.payment-badge{display:inline-block;padding:4px 8px;border-radius:4px;font-size:10px;font-weight:700;text-transform:uppercase}
.payment-pending{background:rgba(245,158,11,.12);color:var(--warn)}
.payment-verified{background:rgba(79,255,176,.12);color:var(--accent)}
</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>

<div class="process-wrapper" style="max-width:1200px">
<h1><i class="fas fa-history" style="color:var(--accent);margin-right:10px"></i>Dispensed Medicines Logs</h1>
<p class="subtitle">View all medicines you have dispensed and their payment status</p>

<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<!-- Statistics -->
<div class="stat-row">
  <div class="stat">
    <div class="stat-ico" style="background:rgba(56,189,248,.12);color:#38bdf8"><i class="fas fa-pills"></i></div>
    <div><div class="stat-val"><?php echo $total_dispensed; ?></div><div class="stat-lbl">Total Dispensed</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico" style="background:rgba(245,158,11,.12);color:var(--warn)"><i class="fas fa-clock"></i></div>
    <div><div class="stat-val"><?php echo $pending_payment; ?></div><div class="stat-lbl">Pending Payment</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico" style="background:rgba(79,255,176,.12);color:var(--accent)"><i class="fas fa-check-circle"></i></div>
    <div><div class="stat-val"><?php echo $verified_payment; ?></div><div class="stat-lbl">Verified Payment</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico" style="background:rgba(56,189,248,.12);color:#38bdf8"><i class="fas fa-coins"></i></div>
    <div><div class="stat-val">₱<?php echo number_format($total_revenue, 0); ?></div><div class="stat-lbl">Total Revenue</div></div>
  </div>
</div>

<!-- Dispensed Logs Table -->
<div class="content-section">
<h2>Dispensing History</h2>

<?php if(empty($dispensed_logs)): ?>
<div class="empty-state">
  <i class="fas fa-inbox" style="font-size:48px;margin-bottom:12px;display:block;color:var(--accent2)"></i>
  <p>No dispensed medicines yet.</p>
</div>
<?php else: ?>

<div style="overflow-x:auto">
<table class="log-table">
<thead>
  <tr>
    <th>Date & Time</th>
    <th>Product Code</th>
    <th>Product Name</th>
    <th>Generic Name</th>
    <th style="width:60px">Qty</th>
    <th style="width:80px">Unit Price</th>
    <th style="width:80px">Total</th>
    <th>Customer</th>
    <th>Patient</th>
    <th style="width:100px">Payment Status</th>
  </tr>
</thead>
<tbody>
<?php foreach($dispensed_logs as $log): 
    $item_total = $log['quantity'] * $log['unit_price'];
    $badge_class = $log['payment_status'] === 'Pending' ? 'payment-pending' : 'payment-verified';
?>
<tr>
  <td><?php echo date('M d, Y H:i', strtotime($log['dispensed_at'])); ?></td>
  <td><span class="product-code"><?php echo htmlspecialchars($log['product_code']); ?></span></td>
  <td><span class="product-name"><?php echo htmlspecialchars($log['product_name']); ?></span></td>
  <td><?php echo htmlspecialchars($log['generic_name'] ?? '—'); ?></td>
  <td style="text-align:center;font-weight:600"><?php echo $log['quantity']; ?></td>
  <td style="text-align:right">₱<?php echo number_format($log['unit_price'], 2); ?></td>
  <td style="text-align:right;font-weight:600;color:var(--accent)">₱<?php echo number_format($item_total, 2); ?></td>
  <td><?php echo htmlspecialchars($log['customer_first'] . ' ' . $log['customer_last']); ?></td>
  <td><?php echo htmlspecialchars($log['patient_name']); ?></td>
  <td><span class="payment-badge <?php echo $badge_class; ?>"><i class="fas fa-<?php echo $log['payment_status'] === 'Pending' ? 'clock' : 'check-circle'; ?>" style="margin-right:4px"></i><?php echo $log['payment_status']; ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>
</div>

<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
