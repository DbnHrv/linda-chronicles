<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');

// Allow both customers (process 18) and pharmacist assistants (process 17)
$user_role = getCurrentUserRole();
if ($user_role != ROLE_CUSTOMER && $user_role != ROLE_PHARMACIST_ASSISTANT) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;padding:40px;background:#0a0c10;color:#f87171;min-height:100vh">
         <h2>Access Denied</h2><p>You do not have permission to access this process.</p>
         <a href="' . APP_URL . '/dashboard.php" style="color:#38bdf8">← Back to Dashboard</a></div>');
}

$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$dispensed_medicines = [];
$available_products = [];
$rx_id = intval($_GET['rx_id']??0);

$customer_id = $_SESSION['user_id'];

// Handle marking payment as verified
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='verify_payment') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) {
        $message='Invalid token.';
        $message_type='error';
    } else {
        try {
            $dm_id = intval($_POST['dispensed_id']??0);
            if (!$dm_id) throw new Exception('Invalid dispensed medicine ID.');
            
            // Update payment status
            $stmt = $pdo->prepare("UPDATE dispensed_medicines SET payment_status='Verified' WHERE id=?");
            $stmt->execute([$dm_id]);
            
            $message = 'Payment verified successfully.';
            $message_type = 'success';
        } catch(Exception $e) {
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}

try {
    $query = "
        SELECT dm.*, 
               p.product_code, p.product_name, p.generic_name, p.form, p.pack_size, p.current_stock, p.reorder_level, p.unit_price,
               pr.id as prescription_id, pr.patient_name, pr.doctor_name, pr.prescription_date,
               u.first_name, u.last_name
        FROM dispensed_medicines dm
        JOIN products p ON dm.product_id = p.id
        JOIN prescriptions pr ON dm.prescription_id = pr.id
        JOIN users u ON dm.dispensed_by = u.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // If customer, only show their own dispensed medicines
    if ($user_role == ROLE_CUSTOMER) {
        $query .= " AND pr.customer_id = ?";
        $params[] = $_SESSION['user_id'];
    }
    // If pharmacist assistant, show all dispensed medicines
    
    if ($rx_id > 0) {
        $query .= " AND pr.id = ?";
        $params[] = $rx_id;
    }
    
    $query .= " ORDER BY dm.dispensed_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $dispensed_medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $dispensed_medicines = [];
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, m.manufacturer_name,
               CASE 
                   WHEN p.current_stock <= p.reorder_level THEN 'Low'
                   WHEN p.current_stock <= (p.reorder_level * 1.5) THEN 'Medium'
                   ELSE 'Adequate'
               END as stock_status
        FROM products p
        LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
        WHERE p.is_active = 1
        ORDER BY p.product_name
    ");
    $stmt->execute();
    $available_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $available_products = [];
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Dispensed Medicines — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
.medicine-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;display:grid;grid-template-columns:1fr auto;gap:16px;align-items:start}
.medicine-info{display:flex;flex-direction:column;gap:8px}
.medicine-name{font-size:14px;font-weight:700;color:var(--text)}
.medicine-meta{font-size:12px;color:var(--text2);display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px}
.medicine-meta-item{display:flex;align-items:center;gap:4px}
.medicine-meta-label{color:var(--text3);font-weight:600}
.availability-badge{display:inline-block;padding:6px 12px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.availability-low{background:rgba(248,113,113,.12);color:var(--danger)}
.availability-medium{background:rgba(245,158,11,.12);color:var(--warn)}
.availability-adequate{background:rgba(79,255,176,.12);color:var(--accent)}
.payment-badge{display:inline-block;padding:6px 12px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.payment-pending{background:rgba(245,158,11,.12);color:var(--warn)}
.payment-verified{background:rgba(79,255,176,.12);color:var(--accent)}
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-bottom:20px}
.product-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;display:flex;flex-direction:column;gap:12px;transition:all .2s}
.product-card:hover{border-color:var(--border2);box-shadow:0 4px 12px rgba(0,0,0,.1)}
.product-header{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}
.product-code{font-size:10px;color:var(--accent);font-family:monospace;font-weight:700}
.product-name{font-size:13px;font-weight:700;color:var(--text);margin-top:4px}
.product-details{font-size:11px;color:var(--text2);display:grid;gap:4px}
.product-stock{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:12px;background:var(--surface2);border-radius:8px;margin-top:8px}
.stock-item{display:flex;flex-direction:column}
.stock-label{font-size:10px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
.stock-value{font-size:14px;font-weight:700;color:var(--accent)}
.stat-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:20px}
.stat{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;display:flex;align-items:center;gap:12px}
.stat-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.stat-val{font-size:20px;font-weight:700;color:var(--text);line-height:1}
.stat-lbl{font-size:11px;color:var(--text3);margin-top:2px}
</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>

<div class="process-wrapper" style="max-width:1200px">
<h1><i class="fas fa-pills" style="color:var(--accent);margin-right:10px"></i>My Dispensed Medicines</h1>
<p class="subtitle">View your dispensed medicines and check product availability</p>

<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<!-- My Dispensed Medicines Section -->
<div class="content-section">
<h2>My Dispensed Medicines</h2>

<?php if(empty($dispensed_medicines)): ?>
<div class="empty-state">
  <i class="fas fa-inbox" style="font-size:48px;margin-bottom:12px;display:block;color:var(--accent2)"></i>
  <p>No medicines dispensed yet.</p>
</div>
<?php else: ?>

<?php foreach($dispensed_medicines as $medicine): 
    $stockStatus = 'adequate';
    if ($medicine['current_stock'] <= $medicine['reorder_level']) {
        $stockStatus = 'low';
    } elseif ($medicine['current_stock'] <= ($medicine['reorder_level'] * 1.5)) {
        $stockStatus = 'medium';
    }
    $badgeClass = 'availability-' . $stockStatus;
    $statusText = ucfirst($stockStatus);
?>

<div class="medicine-card">
  <div class="medicine-info">
    <div class="medicine-name">
      <i class="fas fa-capsule" style="margin-right:8px;color:var(--accent)"></i>
      <?php echo htmlspecialchars($medicine['product_name']); ?>
    </div>
    <div class="medicine-meta">
      <div class="medicine-meta-item">
        <span class="medicine-meta-label">Code:</span>
        <span style="font-family:monospace;color:var(--accent);font-weight:700"><?php echo htmlspecialchars($medicine['product_code']); ?></span>
      </div>
      <div class="medicine-meta-item">
        <span class="medicine-meta-label">Generic:</span>
        <span><?php echo htmlspecialchars($medicine['generic_name'] ?? '—'); ?></span>
      </div>
      <div class="medicine-meta-item">
        <span class="medicine-meta-label">Form:</span>
        <span><?php echo htmlspecialchars($medicine['form'] ?? '—'); ?></span>
      </div>
      <div class="medicine-meta-item">
        <span class="medicine-meta-label">Qty Dispensed:</span>
        <span style="color:var(--accent);font-weight:700"><?php echo $medicine['quantity']; ?> PCS</span>
      </div>
      <div class="medicine-meta-item">
        <span class="medicine-meta-label">Date:</span>
        <span><?php echo date('M d, Y H:i', strtotime($medicine['dispensed_at'])); ?></span>
      </div>
      <div class="medicine-meta-item">
        <span class="medicine-meta-label">Dispensed By:</span>
        <span><?php echo htmlspecialchars($medicine['first_name'] . ' ' . $medicine['last_name']); ?></span>
      </div>
    </div>
  </div>
  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
    <span class="availability-badge <?php echo $badgeClass; ?>">
      <i class="fas fa-<?php echo $stockStatus === 'low' ? 'exclamation-triangle' : 'check-circle'; ?>" style="margin-right:4px"></i>
      <?php echo $statusText; ?> Stock
    </span>
    <span class="payment-badge payment-<?php echo strtolower($medicine['payment_status']); ?>">
      <i class="fas fa-<?php echo $medicine['payment_status'] === 'Verified' ? 'check-circle' : 'clock'; ?>" style="margin-right:4px"></i>
      <?php echo $medicine['payment_status']; ?>
    </span>
    <?php if($medicine['payment_status'] === 'Pending'): ?>
    <form method="POST" style="display:inline">
      <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
      <input type="hidden" name="action" value="verify_payment">
      <input type="hidden" name="dispensed_id" value="<?php echo $medicine['id']; ?>">
      <button type="submit" class="btn btn-sm" style="background:rgba(79,255,176,.1);color:var(--accent);border:1px solid rgba(79,255,176,.3);font-size:11px"><i class="fas fa-check"></i> Mark as Paid</button>
    </form>
    <?php endif; ?>
    <div style="font-size:11px;color:var(--text3);text-align:right">
      <div>Current: <strong style="color:var(--accent)"><?php echo $medicine['current_stock']; ?> PCS</strong></div>
      <div>Reorder: <strong><?php echo $medicine['reorder_level']; ?> PCS</strong></div>
    </div>
  </div>
</div>

<?php endforeach; ?>

<?php endif; ?>
</div>

<!-- Product Availability Section -->
<div class="content-section">
<h2>Product Availability in Pharmacy</h2>

<?php 
$low_count = 0;
$medium_count = 0;
$adequate_count = 0;
foreach ($available_products as $p) {
    if ($p['stock_status'] === 'Low') $low_count++;
    elseif ($p['stock_status'] === 'Medium') $medium_count++;
    else $adequate_count++;
}
?>

<!-- Statistics -->
<div class="stat-row">
  <div class="stat">
    <div class="stat-ico" style="background:rgba(248,113,113,.12);color:var(--danger)"><i class="fas fa-exclamation-triangle"></i></div>
    <div><div class="stat-val"><?php echo $low_count; ?></div><div class="stat-lbl">Low Stock</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico" style="background:rgba(245,158,11,.12);color:var(--warn)"><i class="fas fa-info-circle"></i></div>
    <div><div class="stat-val"><?php echo $medium_count; ?></div><div class="stat-lbl">Medium Stock</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico" style="background:rgba(79,255,176,.12);color:var(--accent)"><i class="fas fa-check-circle"></i></div>
    <div><div class="stat-val"><?php echo $adequate_count; ?></div><div class="stat-lbl">Adequate Stock</div></div>
  </div>
</div>

<!-- Product Grid -->
<?php if(empty($available_products)): ?>
<div class="empty-state">
  <p>No products available.</p>
</div>
<?php else: ?>

<div class="product-grid">
<?php foreach($available_products as $product): 
    $stockStatus = 'adequate';
    $statusIcon = 'check-circle';
    if ($product['stock_status'] === 'Low') {
        $stockStatus = 'low';
        $statusIcon = 'exclamation-triangle';
    } elseif ($product['stock_status'] === 'Medium') {
        $stockStatus = 'medium';
        $statusIcon = 'info-circle';
    }
    $badgeClass = 'availability-' . $stockStatus;
?>

<div class="product-card">
  <div class="product-header">
    <div>
      <div class="product-code"><?php echo htmlspecialchars($product['product_code']); ?></div>
      <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
    </div>
    <span class="availability-badge <?php echo $badgeClass; ?>">
      <i class="fas fa-<?php echo $statusIcon; ?>" style="margin-right:4px"></i>
      <?php echo ucfirst($product['stock_status']); ?>
    </span>
  </div>

  <div class="product-details">
    <div><strong>Generic Name:</strong> <?php echo htmlspecialchars($product['generic_name'] ?? '—'); ?></div>
    <div><strong>Form:</strong> <?php echo htmlspecialchars($product['form'] ?? '—'); ?></div>
    <div><strong>Pack Size:</strong> <?php echo $product['pack_size']; ?></div>
    <div><strong>Manufacturer:</strong> <?php echo htmlspecialchars($product['manufacturer_name'] ?? '—'); ?></div>
    <div><strong>Unit Price:</strong> ₱<?php echo number_format($product['unit_price'], 2); ?></div>
  </div>

  <div class="product-stock">
    <div class="stock-item">
      <div class="stock-label">Current Stock</div>
      <div class="stock-value" style="color:<?php echo $product['stock_status'] === 'Low' ? 'var(--danger)' : 'var(--accent)'; ?>">
        <?php echo $product['current_stock']; ?> PCS
      </div>
    </div>
    <div class="stock-item">
      <div class="stock-label">Reorder Level</div>
      <div class="stock-value"><?php echo $product['reorder_level']; ?> PCS</div>
    </div>
  </div>
</div>

<?php endforeach; ?>
</div>

<?php endif; ?>
</div>

<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
