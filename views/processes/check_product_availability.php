<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(16);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$products = [];
$user_prescriptions = [];
$selected_prescription = null;
$selected_items = [];

// Check if prescription ID is passed in URL
$selected_rx_id = intval($_GET['rx_id']??0);

// Handle adding product to cart
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_product') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) {
        $message='Invalid token.';
        $message_type='error';
    } else {
        try {
            $pid = intval($_POST['product_id']??0);
            $qty = intval($_POST['quantity']??0);
            
            if (!$pid || !$qty) throw new Exception('Product and quantity required.');
            
            // Check stock availability
            $stmt = $pdo->prepare("SELECT current_stock FROM products WHERE id = ?");
            $stmt->execute([$pid]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) throw new Exception('Product not found.');
            if ($product['current_stock'] < $qty) throw new Exception('Insufficient stock. Available: ' . $product['current_stock']);
            
            // Store in session
            if (!isset($_SESSION['dispensing_cart'])) {
                $_SESSION['dispensing_cart'] = [];
            }
            
            // Check if product already in cart
            $found = false;
            foreach ($_SESSION['dispensing_cart'] as &$item) {
                if ($item['product_id'] == $pid) {
                    $item['quantity'] += $qty;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $_SESSION['dispensing_cart'][] = [
                    'product_id' => $pid,
                    'quantity' => $qty
                ];
            }
            
            $message = 'Product added to dispensing cart.';
            $message_type = 'success';
        } catch(Exception $e) {
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Handle dispensing all products in cart
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='dispense_all') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) {
        $message='Invalid token.';
        $message_type='error';
    } else {
        try {
            $rxid = intval($_POST['prescription_id']??0);
            
            if (!$rxid) throw new Exception('Prescription ID required.');
            if (empty($_SESSION['dispensing_cart'])) throw new Exception('No products in cart.');
            
            // Dispense all products
            foreach ($_SESSION['dispensing_cart'] as $item) {
                $processModel->dispenseMedicine($rxid, $item['product_id'], $item['quantity']);
            }
            
            $message = 'All medicines dispensed successfully.';
            $message_type = 'success';
            
            // Clear cart and redirect
            unset($_SESSION['dispensing_cart']);
            $_SESSION['success'] = 'Medicines dispensed successfully. Awaiting payment.';
            header('Location: ' . APP_URL . '/views/processes/view_dispensed_medicines.php?rx_id=' . $rxid);
            exit;
        } catch(Exception $e) {
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Handle removing product from cart
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='remove_from_cart') {
    $pid = intval($_POST['product_id']??0);
    if (isset($_SESSION['dispensing_cart'])) {
        $_SESSION['dispensing_cart'] = array_filter($_SESSION['dispensing_cart'], function($item) use ($pid) {
            return $item['product_id'] != $pid;
        });
        $_SESSION['dispensing_cart'] = array_values($_SESSION['dispensing_cart']);
    }
}

// Load cart items with product details
if (isset($_SESSION['dispensing_cart']) && !empty($_SESSION['dispensing_cart'])) {
    foreach ($_SESSION['dispensing_cart'] as $item) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$item['product_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($product) {
            $selected_items[] = array_merge($product, ['cart_quantity' => $item['quantity']]);
        }
    }
}

// Fetch selected prescription if ID provided
if ($selected_rx_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.first_name, u.last_name, u.email 
            FROM prescriptions p
            JOIN users u ON p.customer_id = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$selected_rx_id]);
        $selected_prescription = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        $selected_prescription = null;
    }
}

// Fetch user's prescriptions
try {
    $stmt = $pdo->prepare("
        SELECT * FROM prescriptions 
        WHERE customer_id = ? 
        ORDER BY upload_date DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $user_prescriptions = [];
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
        ORDER BY p.current_stock ASC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $products = [];
}

// Calculate statistics
$low_stock_count = 0;
$medium_stock_count = 0;
$adequate_stock_count = 0;
$total_value = 0;

foreach ($products as $p) {
    if ($p['stock_status'] === 'Low') $low_stock_count++;
    elseif ($p['stock_status'] === 'Medium') $medium_stock_count++;
    else $adequate_stock_count++;
    $total_value += ($p['current_stock'] * $p['cost_price']);
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Check Product Availability — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
.inventory-table{width:100%;border-collapse:collapse;margin-bottom:20px}
.inventory-table thead{background:var(--surface2);border-bottom:2px solid var(--border)}
.inventory-table th{padding:12px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:11px}
.inventory-table td{padding:12px;border-bottom:1px solid var(--border);font-size:12px}
.inventory-table tbody tr:hover{background:var(--surface2)}
.product-code{color:var(--accent);font-weight:700;font-family:monospace;font-size:10px}
.product-name{color:var(--text);font-weight:600}
.low-stock{background:rgba(248,113,113,.05)}
.low-stock-qty{color:var(--danger);font-weight:700}
.medium-stock{background:rgba(245,158,11,.05)}
.medium-stock-qty{color:var(--warn);font-weight:700}
.adequate-stock{background:rgba(79,255,176,.05)}
.adequate-stock-qty{color:var(--accent);font-weight:700}
.stat-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:28px}
.stat{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px 20px;display:flex;align-items:center;gap:14px;transition:border-color .2s}
.stat:hover{border-color:var(--border2)}
.stat-ico{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.stat-val{font-size:24px;font-weight:700;color:var(--text);line-height:1}
.stat-lbl{font-size:11px;color:var(--text3);margin-top:3px}
.filter-bar{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.filter-btn{padding:8px 14px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);cursor:pointer;font-size:12px;font-weight:600;transition:all .2s}
.filter-btn.active{background:var(--accent);color:#0a0c10;border-color:var(--accent)}
.dispensing-form{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px}
.dispensing-form h3{font-size:14px;font-weight:700;color:var(--text);margin:0 0 16px 0;display:flex;align-items:center;gap:8px}
.dispensing-cart{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px}
.dispensing-cart h3{font-size:14px;font-weight:700;color:var(--text);margin:0 0 16px 0;display:flex;align-items:center;gap:8px}
.cart-items{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:16px;max-height:400px;overflow-y:auto}
.cart-item{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;background:var(--surface);border:1px solid var(--border);border-radius:8px;margin-bottom:8px}
.cart-item-info{flex:1}
.cart-item-code{font-size:10px;color:var(--accent);font-family:monospace;font-weight:700}
.cart-item-name{font-size:12px;font-weight:600;color:var(--text);margin-top:2px}
.cart-item-meta{font-size:11px;color:var(--text3);margin-top:2px}
.cart-item-qty{text-align:center;min-width:60px}
.qty-label{font-size:10px;color:var(--text3);text-transform:uppercase;font-weight:700}
.qty-value{font-size:14px;font-weight:700;color:var(--text);margin-top:2px}
.cart-item-price{text-align:right;min-width:100px}
.price-label{font-size:10px;color:var(--text3);text-transform:uppercase;font-weight:700}
.price-value{font-size:13px;font-weight:700;color:var(--accent);margin-top:2px}
.btn-remove{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.3);color:var(--danger);padding:6px 10px;border-radius:6px;cursor:pointer;font-size:12px;transition:all .2s}
.btn-remove:hover{background:rgba(248,113,113,.2);border-color:var(--danger)}
.cart-total{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:rgba(79,255,176,.08);border:1px solid rgba(79,255,176,.18);border-radius:8px}
.total-label{font-size:12px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.05em}
.total-value{font-size:18px;font-weight:700;color:var(--accent)}
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:12px}
.form-group{display:flex;flex-direction:column}
.form-group label{font-size:12px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px}
.form-group input,.form-group select{padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-size:13px}
.form-group input:focus,.form-group select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,255,176,.1)}
.form-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:16px}
.product-selector{background:var(--surface2);border:1px solid var(--border);border-radius:8px;max-height:300px;overflow-y:auto;margin-bottom:12px}
.product-option{padding:12px;border-bottom:1px solid var(--border);cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:space-between;gap:12px}
.product-option:hover{background:var(--surface3)}
.product-option.selected{background:rgba(79,255,176,.1);border-left:3px solid var(--accent)}
.product-option-info{flex:1}
.product-option-code{font-size:10px;color:var(--accent);font-family:monospace;font-weight:700}
.product-option-name{font-size:12px;font-weight:600;color:var(--text);margin-top:2px}
.product-option-stock{font-size:11px;color:var(--text3);margin-top:2px}
.product-option-badge{display:inline-block;padding:4px 8px;border-radius:4px;font-size:10px;font-weight:700;text-transform:uppercase}
.stock-low{background:rgba(248,113,113,.12);color:var(--danger)}
.stock-medium{background:rgba(245,158,11,.12);color:var(--warn)}
.stock-adequate{background:rgba(79,255,176,.12);color:var(--accent)}
.search-container{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.search-input{flex:1;min-width:250px;padding:10px 14px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-size:13px}
.search-input::placeholder{color:var(--text3)}
.search-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,255,176,.1)}
.search-results{max-height:400px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:8px;background:var(--surface2);display:none;margin-bottom:20px}
.search-results.show{display:block}
.product-search-result{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:12px 14px;margin-bottom:8px;display:flex;align-items:center;justify-content:space-between;gap:12px;cursor:pointer;transition:all .2s}
.product-search-result:hover{border-color:var(--border2);background:var(--surface2)}
.product-search-info{flex:1}
.product-code-small{font-size:10px;color:var(--accent);font-family:monospace;font-weight:700}
.product-name-small{font-size:12px;font-weight:600;color:var(--text);margin-top:2px}
.product-stock-small{font-size:11px;color:var(--text3);margin-top:2px}
.stock-badge-small{display:inline-block;padding:4px 8px;border-radius:4px;font-size:10px;font-weight:700;text-transform:uppercase}
.stock-low-small{background:rgba(248,113,113,.12);color:var(--danger)}
.stock-medium-small{background:rgba(245,158,11,.12);color:var(--warn)}
.stock-adequate-small{background:rgba(79,255,176,.12);color:var(--accent)}
.prescription-card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.prescription-card:hover{border-color:var(--border2)}
.prescription-info{flex:1}
.prescription-patient{font-size:13px;font-weight:600;color:var(--text)}
.prescription-meta{font-size:11px;color:var(--text3);margin-top:2px}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px);overflow-y:auto;padding:20px}
.modal.show{display:flex}
.modal-content{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:28px;width:90%;max-width:800px;box-shadow:0 8px 40px rgba(0,0,0,.5)}
</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>

<div class="process-wrapper" style="max-width:1200px">
<h1><i class="fas fa-boxes" style="color:var(--accent);margin-right:10px"></i>Check Product Availability</h1>
<p class="subtitle">Monitor current stock levels and product availability</p>

<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<!-- Selected Prescription Display -->
<?php if($selected_prescription): ?>
<div style="background:linear-gradient(135deg,rgba(56,189,248,.08),rgba(167,139,250,.08));border:1px solid rgba(56,189,248,.18);border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:16px">
<div style="display:flex;align-items:center;gap:12px;flex:1">
<div style="width:40px;height:40px;border-radius:10px;background:rgba(56,189,248,.12);display:flex;align-items:center;justify-content:center;color:var(--accent2);flex-shrink:0"><i class="fas fa-file-medical"></i></div>
<div style="flex:1">
<div style="font-size:13px;font-weight:700;color:var(--text)">Selected Prescription</div>
<div style="font-size:12px;color:var(--text2);margin-top:2px">Patient: <?php echo htmlspecialchars($selected_prescription['patient_name']); ?> · Dr. <?php echo htmlspecialchars($selected_prescription['first_name'].' '.$selected_prescription['last_name']); ?></div>
<div style="font-size:11px;color:var(--text3);margin-top:2px"><?php echo date('M d, Y H:i',strtotime($selected_prescription['upload_date'])); ?> · Status: <span class="status-badge status-<?php echo strtolower($selected_prescription['status']); ?>" style="font-size:10px"><?php echo $selected_prescription['status']; ?></span></div>
</div>
</div>
<button type="button" class="btn btn-sm" style="background:var(--surface2);border:1px solid var(--border);color:var(--text);flex-shrink:0" onclick="viewPrescriptionFile('<?php echo htmlspecialchars($selected_prescription['prescription_image']); ?>')"><i class="fas fa-file"></i> View File</button>
</div>

<!-- Dispensing Form -->
<?php if($selected_prescription): ?>
<div class="dispensing-form">
<h3><i class="fas fa-pills" style="color:var(--accent)"></i>Dispense Medicine</h3>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
<input type="hidden" name="action" value="add_product">
<input type="hidden" name="prescription_id" value="<?php echo $selected_prescription['id']; ?>">

<div class="form-group">
  <label>Select Product to Dispense</label>
  <div class="product-selector" id="productSelector">
    <?php foreach($products as $p): 
        $stockClass = $p['stock_status'] === 'Low' ? 'stock-low' : ($p['stock_status'] === 'Medium' ? 'stock-medium' : 'stock-adequate');
        $statusIcon = $p['stock_status'] === 'Low' ? 'exclamation-triangle' : ($p['stock_status'] === 'Medium' ? 'info-circle' : 'check-circle');
    ?>
    <div class="product-option" onclick="selectProduct(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['product_name'])); ?>', <?php echo $p['current_stock']; ?>)">
      <div class="product-option-info">
        <div class="product-option-code"><?php echo htmlspecialchars($p['product_code']); ?></div>
        <div class="product-option-name"><?php echo htmlspecialchars($p['product_name']); ?></div>
        <div class="product-option-stock">
          <?php echo htmlspecialchars($p['generic_name'] ?? '—'); ?> · <?php echo htmlspecialchars($p['form'] ?? '—'); ?> · Pack: <?php echo $p['pack_size']; ?>
        </div>
      </div>
      <span class="product-option-badge <?php echo $stockClass; ?>">
        <i class="fas fa-<?php echo $statusIcon; ?>" style="margin-right:4px"></i>
        <?php echo $p['stock_status']; ?> (<?php echo $p['current_stock']; ?>)
      </span>
    </div>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="product_id" id="selectedProductId" required>
</div>

<div class="form-row">
  <div class="form-group">
    <label>Selected Product</label>
    <input type="text" id="selectedProductName" readonly style="background:var(--surface2);cursor:not-allowed">
  </div>
  <div class="form-group">
    <label>Quantity to Dispense</label>
    <input type="number" name="quantity" id="quantityInput" min="1" required placeholder="Enter quantity">
  </div>
</div>

<div class="form-actions">
  <button type="button" class="btn btn-secondary" onclick="clearSelection()"><i class="fas fa-times"></i> Clear</button>
  <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add to Cart</button>
</div>
</form>
</div>

<!-- Dispensing Cart -->
<?php if(!empty($selected_items)): ?>
<div class="dispensing-cart">
<h3><i class="fas fa-shopping-cart" style="color:var(--accent)"></i>Dispensing Cart (<?php echo count($selected_items); ?> items)</h3>
<div class="cart-items">
<?php $total_value = 0; foreach($selected_items as $item): $item_total = $item['cart_quantity'] * $item['unit_price']; $total_value += $item_total; ?>
<div class="cart-item">
  <div class="cart-item-info">
    <div class="cart-item-code"><?php echo htmlspecialchars($item['product_code']); ?></div>
    <div class="cart-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
    <div class="cart-item-meta"><?php echo htmlspecialchars($item['generic_name'] ?? '—'); ?> · <?php echo htmlspecialchars($item['form'] ?? '—'); ?></div>
  </div>
  <div class="cart-item-qty">
    <div class="qty-label">Qty</div>
    <div class="qty-value"><?php echo $item['cart_quantity']; ?></div>
  </div>
  <div class="cart-item-price">
    <div class="price-label">₱<?php echo number_format($item['unit_price'], 2); ?></div>
    <div class="price-value">₱<?php echo number_format($item_total, 2); ?></div>
  </div>
  <form method="POST" style="display:inline">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="action" value="remove_from_cart">
    <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
    <button type="submit" class="btn-remove" title="Remove from cart"><i class="fas fa-trash"></i></button>
  </form>
</div>
<?php endforeach; ?>
</div>
<div class="cart-total">
  <div class="total-label">Total Amount:</div>
  <div class="total-value">₱<?php echo number_format($total_value, 2); ?></div>
</div>
<form method="POST" style="margin-top:16px">
  <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
  <input type="hidden" name="action" value="dispense_all">
  <input type="hidden" name="prescription_id" value="<?php echo $selected_prescription['id']; ?>">
  <div class="form-actions">
    <button type="button" class="btn btn-secondary" onclick="clearCart()"><i class="fas fa-times"></i> Clear Cart</button>
    <button type="submit" class="btn btn-primary" onclick="return confirm('Confirm dispensing all medicines?')"><i class="fas fa-check"></i> Dispense All & Proceed to Payment</button>
  </div>
</form>
</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<!-- Your Prescriptions Section -->
<?php if(!empty($user_prescriptions)): ?>
<div class="content-section">
<h2><i class="fas fa-file-medical" style="margin-right:8px;color:var(--accent)"></i>Your Prescriptions</h2>
<?php foreach($user_prescriptions as $rx): $slug=strtolower($rx['status']); ?>
<div class="prescription-card">
  <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">
    <div style="width:36px;height:36px;border-radius:8px;background:rgba(56,189,248,.1);display:flex;align-items:center;justify-content:center;color:var(--accent2);flex-shrink:0"><i class="fas fa-file-medical"></i></div>
    <div style="min-width:0;flex:1">
      <div class="prescription-patient">Patient: <?php echo htmlspecialchars($rx['patient_name']); ?></div>
      <div class="prescription-meta">Dr. <?php echo htmlspecialchars($rx['doctor_name']); ?> · <?php echo date('M d, Y H:i',strtotime($rx['upload_date'])); ?></div>
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
    <span class="status-badge status-<?php echo $slug; ?>"><?php echo $rx['status']; ?></span>
    <button type="button" class="btn btn-sm" style="background:var(--surface2);border:1px solid var(--border);color:var(--text)" onclick="viewPrescriptionFile('<?php echo htmlspecialchars($rx['prescription_image']); ?>')"><i class="fas fa-file"></i> View</button>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Search Product Availability Section -->
<div class="content-section">
<h2><i class="fas fa-search" style="margin-right:8px;color:var(--accent)"></i>Search Product Availability</h2>
<div class="search-container">
  <input type="text" id="productSearch" class="search-input" placeholder="Search by product name, code, or generic name..." onkeyup="searchProducts()">
  <button onclick="clearSearch()" class="btn btn-secondary" style="padding:10px 16px"><i class="fas fa-times"></i> Clear</button>
</div>
<div id="searchResults" class="search-results">
  <div style="padding:20px;text-align:center;color:var(--text3)">
    <p>Start typing to search products...</p>
  </div>
</div>
</div>

<!-- Statistics -->
<div class="stat-row">
  <div class="stat">
    <div class="stat-ico" style="background:rgba(248,113,113,.12);color:var(--danger)"><i class="fas fa-exclamation-triangle"></i></div>
    <div><div class="stat-val"><?php echo $low_stock_count; ?></div><div class="stat-lbl">Low Stock Items</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico" style="background:rgba(245,158,11,.12);color:var(--warn)"><i class="fas fa-info-circle"></i></div>
    <div><div class="stat-val"><?php echo $medium_stock_count; ?></div><div class="stat-lbl">Medium Stock Items</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico" style="background:rgba(79,255,176,.12);color:var(--accent)"><i class="fas fa-check-circle"></i></div>
    <div><div class="stat-val"><?php echo $adequate_stock_count; ?></div><div class="stat-lbl">Adequate Stock Items</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico" style="background:rgba(56,189,248,.12);color:#38bdf8"><i class="fas fa-coins"></i></div>
    <div><div class="stat-val">₱<?php echo number_format($total_value, 0); ?></div><div class="stat-lbl">Total Inventory Value</div></div>
  </div>
</div>

<div class="content-section">
<h2>Product Inventory Status</h2>

<!-- Filter Buttons -->
<div class="filter-bar">
  <button class="filter-btn active" onclick="filterTable('all')"><i class="fas fa-list"></i> All Products</button>
  <button class="filter-btn" onclick="filterTable('low')"><i class="fas fa-exclamation-triangle"></i> Low Stock</button>
  <button class="filter-btn" onclick="filterTable('medium')"><i class="fas fa-info-circle"></i> Medium Stock</button>
  <button class="filter-btn" onclick="filterTable('adequate')"><i class="fas fa-check-circle"></i> Adequate Stock</button>
</div>

<?php if(empty($products)): ?>
<div class="empty-state">
  <i class="fas fa-inbox" style="font-size:48px;margin-bottom:12px;display:block;color:var(--accent2)"></i>
  <p>No products available.</p>
</div>
<?php else: ?>

<div style="overflow-x:auto">
<table class="inventory-table">
<thead>
  <tr>
    <th style="width:80px">Item No.</th>
    <th>Product Code</th>
    <th>Product Name</th>
    <th>Generic Name</th>
    <th style="width:80px">Form</th>
    <th style="width:80px">Pack Size</th>
    <th style="width:100px">Current Stock</th>
    <th style="width:100px">Reorder Level</th>
    <th style="width:100px">Cost Price</th>
    <th style="width:100px">Total Value</th>
    <th style="width:100px">Status</th>
  </tr>
</thead>
<tbody>
<?php 
$itemNo = 1;
foreach($products as $p): 
    $rowClass = 'low-stock';
    $statusBadge = '<span style="background:rgba(248,113,113,.12);color:var(--danger);padding:4px 8px;border-radius:4px;font-size:10px;font-weight:700">LOW</span>';
    $qtyClass = 'low-stock-qty';
    
    if ($p['stock_status'] === 'Medium') {
        $rowClass = 'medium-stock';
        $statusBadge = '<span style="background:rgba(245,158,11,.12);color:var(--warn);padding:4px 8px;border-radius:4px;font-size:10px;font-weight:700">MEDIUM</span>';
        $qtyClass = 'medium-stock-qty';
    } elseif ($p['stock_status'] === 'Adequate') {
        $rowClass = 'adequate-stock';
        $statusBadge = '<span style="background:rgba(79,255,176,.12);color:var(--accent);padding:4px 8px;border-radius:4px;font-size:10px;font-weight:700">ADEQUATE</span>';
        $qtyClass = 'adequate-stock-qty';
    }
    
    $totalValue = $p['current_stock'] * $p['cost_price'];
?>
<tr class="<?php echo $rowClass; ?> product-row" data-status="<?php echo strtolower($p['stock_status']); ?>">
  <td style="text-align:center;color:var(--text3);font-weight:600"><?php echo $itemNo; ?></td>
  <td><span class="product-code"><?php echo htmlspecialchars($p['product_code']); ?></span></td>
  <td><span class="product-name"><?php echo htmlspecialchars($p['product_name']); ?></span></td>
  <td style="color:var(--text2)"><?php echo htmlspecialchars($p['generic_name'] ?? '—'); ?></td>
  <td style="text-align:center;color:var(--text2)"><?php echo htmlspecialchars($p['form'] ?? '—'); ?></td>
  <td style="text-align:center;color:var(--text2)"><?php echo $p['pack_size']; ?></td>
  <td style="text-align:center;font-weight:700" class="<?php echo $qtyClass; ?>"><?php echo $p['current_stock']; ?></td>
  <td style="text-align:center;color:var(--text2)"><?php echo $p['reorder_level']; ?></td>
  <td style="text-align:right;color:var(--text2)">₱<?php echo number_format($p['cost_price'], 2); ?></td>
  <td style="text-align:right;font-weight:600;color:var(--accent)">₱<?php echo number_format($totalValue, 2); ?></td>
  <td style="text-align:center"><?php echo $statusBadge; ?></td>
</tr>
<?php $itemNo++; endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>
</div>

<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>

<!-- File Viewer Modal -->
<div id="fileModal" class="modal">
<div class="modal-content">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h2 style="font-size:18px;font-weight:700;color:var(--text);margin:0">Prescription File</h2>
    <button type="button" style="background:none;border:none;font-size:24px;color:var(--text3);cursor:pointer;padding:0" onclick="closeFileModal()"><i class="fas fa-times"></i></button>
  </div>
  <div id="fileContent" style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:20px;min-height:400px;display:flex;align-items:center;justify-content:center">
    <p style="color:var(--text3)">Loading file...</p>
  </div>
  <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
    <button type="button" class="btn btn-secondary" onclick="closeFileModal()"><i class="fas fa-times"></i> Close</button>
  </div>
</div>
</div>

<script>
const productsData = <?php echo json_encode($products); ?>;

function filterTable(status) {
  // Update active button
  document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
  event.target.classList.add('active');
  
  // Filter rows
  const rows = document.querySelectorAll('.product-row');
  rows.forEach(row => {
    if (status === 'all') {
      row.style.display = '';
    } else {
      row.style.display = row.dataset.status === status ? '' : 'none';
    }
  });
}

function searchProducts() {
  const query = document.getElementById('productSearch').value.toLowerCase().trim();
  const resultsDiv = document.getElementById('searchResults');
  
  if (!query) {
    resultsDiv.classList.remove('show');
    resultsDiv.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text3)"><p>Start typing to search products...</p></div>';
    return;
  }
  
  const filtered = productsData.filter(p => 
    p.product_name.toLowerCase().includes(query) ||
    p.product_code.toLowerCase().includes(query) ||
    (p.generic_name && p.generic_name.toLowerCase().includes(query))
  );
  
  if (filtered.length === 0) {
    resultsDiv.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text3)"><p>No products found.</p></div>';
    resultsDiv.classList.add('show');
    return;
  }
  
  let html = '';
  filtered.forEach(p => {
    const stockClass = p.stock_status === 'Low' ? 'stock-low-small' : (p.stock_status === 'Medium' ? 'stock-medium-small' : 'stock-adequate-small');
    const statusIcon = p.stock_status === 'Low' ? 'exclamation-triangle' : (p.stock_status === 'Medium' ? 'info-circle' : 'check-circle');
    
    html += `
      <div class="product-search-result">
        <div class="product-search-info">
          <div class="product-code-small">${p.product_code}</div>
          <div class="product-name-small">${p.product_name}</div>
          <div class="product-stock-small">
            ${p.generic_name ? p.generic_name + ' · ' : ''}
            ${p.form || '—'} · Pack: ${p.pack_size}
          </div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
          <span class="stock-badge-small ${stockClass}">
            <i class="fas fa-${statusIcon}" style="margin-right:4px"></i>
            ${p.stock_status} (${p.current_stock})
          </span>
        </div>
      </div>
    `;
  });
  
  resultsDiv.innerHTML = html;
  resultsDiv.classList.add('show');
}

function clearSearch() {
  document.getElementById('productSearch').value = '';
  document.getElementById('searchResults').classList.remove('show');
  document.getElementById('searchResults').innerHTML = '<div style="padding:20px;text-align:center;color:var(--text3)"><p>Start typing to search products...</p></div>';
}

function selectProduct(productId, productName, currentStock) {
  document.getElementById('selectedProductId').value = productId;
  document.getElementById('selectedProductName').value = productName + ' (Stock: ' + currentStock + ')';
  
  // Update product option selection
  document.querySelectorAll('.product-option').forEach(option => option.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  
  // Set max quantity
  document.getElementById('quantityInput').max = currentStock;
  document.getElementById('quantityInput').value = 1;
}

function clearSelection() {
  document.getElementById('selectedProductId').value = '';
  document.getElementById('selectedProductName').value = '';
  document.getElementById('quantityInput').value = '';
  document.querySelectorAll('.product-option').forEach(option => option.classList.remove('selected'));
}

function clearCart() {
  if (confirm('Clear all items from cart?')) {
    location.reload();
  }
}

function viewPrescriptionFile(filename) {
  const fileContent = document.getElementById('fileContent');
  const fileExt = filename.split('.').pop().toLowerCase();
  const filePath = '<?php echo APP_URL; ?>/uploads/' + filename;
  
  if (fileExt === 'pdf') {
    fileContent.innerHTML = '<iframe src="' + filePath + '" style="width:100%;height:500px;border:none;border-radius:6px"></iframe>';
  } else if (['jpg', 'jpeg', 'png'].includes(fileExt)) {
    fileContent.innerHTML = '<img src="' + filePath + '" style="max-width:100%;max-height:500px;border-radius:6px">';
  } else {
    fileContent.innerHTML = '<p style="color:var(--text3)">File type not supported for preview</p>';
  }
  
  document.getElementById('fileModal').classList.add('show');
}

function closeFileModal() {
  document.getElementById('fileModal').classList.remove('show');
}

window.addEventListener('click', e => {
  if (e.target === document.getElementById('fileModal')) {
    closeFileModal();
  }
});
</script>
</body></html>
