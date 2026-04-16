<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(17);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$available_prescriptions = [];
$products = [];
$dispensed_history = [];
$customer_prescriptions = [];

try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.first_name, u.last_name, u.email
        FROM prescriptions p
        JOIN users u ON p.customer_id = u.id
        WHERE p.status IN ('Verified','Approved')
        ORDER BY p.upload_date DESC
    ");
    $stmt->execute();
    $available_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $available_prescriptions = [];
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
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $products = [];
}

try {
    $stmt = $pdo->prepare("
        SELECT dm.*, p.product_code, p.product_name, pr.patient_name, u.first_name, u.last_name
        FROM dispensed_medicines dm
        JOIN products p ON dm.product_id = p.id
        JOIN prescriptions pr ON dm.prescription_id = pr.id
        JOIN users u ON dm.dispensed_by = u.id
        ORDER BY dm.dispensed_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $dispensed_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $dispensed_history = [];
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='dispense') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) {
        $message='Invalid token.';
        $message_type='error';
    } else {
        try {
            $rxid = intval($_POST['prescription_id']??0);
            $pid = intval($_POST['product_id']??0);
            $qty = intval($_POST['quantity']??0);
            
            if (!$rxid || !$pid || !$qty) throw new Exception('All fields required.');
            
            // Check stock availability
            $stmt = $pdo->prepare("SELECT current_stock FROM products WHERE id = ?");
            $stmt->execute([$pid]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) throw new Exception('Product not found.');
            if ($product['current_stock'] < $qty) throw new Exception('Insufficient stock. Available: ' . $product['current_stock']);
            
            $processModel->dispenseMedicine($rxid, $pid, $qty);
            $message = 'Medicine dispensed successfully.';
            $message_type = 'success';
            
            // Refresh data
            $stmt = $pdo->prepare("
                SELECT p.*, u.first_name, u.last_name, u.email
                FROM prescriptions p
                JOIN users u ON p.customer_id = u.id
                WHERE p.status IN ('Verified','Approved')
                ORDER BY p.upload_date DESC
            ");
            $stmt->execute();
            $available_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("
                SELECT dm.*, p.product_code, p.product_name, pr.patient_name, u.first_name, u.last_name
                FROM dispensed_medicines dm
                JOIN products p ON dm.product_id = p.id
                JOIN prescriptions pr ON dm.prescription_id = pr.id
                JOIN users u ON dm.dispensed_by = u.id
                ORDER BY dm.dispensed_at DESC
                LIMIT 20
            ");
            $stmt->execute();
            $dispensed_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Handle prescription rejection with remarks
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='reject_prescription') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) {
        $message='Invalid token.';
        $message_type='error';
    } else {
        try {
            $rxid = intval($_POST['prescription_id']??0);
            $remarks = sanitize($_POST['rejection_remarks']??'');
            
            if (!$rxid) throw new Exception('Prescription ID required.');
            if (empty($remarks)) throw new Exception('Rejection remarks required.');
            
            $stmt = $pdo->prepare("
                UPDATE prescriptions 
                SET status = 'Rejected', rejection_remarks = ?, verified_by = ?, verified_date = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$remarks, $_SESSION['user_id'], $rxid]);
            
            $message = 'Prescription rejected with remarks.';
            $message_type = 'success';
            
            // Refresh data
            $stmt = $pdo->prepare("
                SELECT p.*, u.first_name, u.last_name, u.email
                FROM prescriptions p
                JOIN users u ON p.customer_id = u.id
                ORDER BY p.upload_date DESC
            ");
            $stmt->execute();
            $all_customer_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Check for pre-selected prescription or product from URL
$preselected_rx = intval($_GET['rx_id']??0);
$preselected_product = intval($_GET['product_id']??0);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dispense Products — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
.product-card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:12px;cursor:pointer;transition:all .2s}
.product-card:hover{border-color:var(--border2);background:var(--surface2)}
.product-card.selected{border-color:var(--accent);background:rgba(79,255,176,.05)}
.product-info{flex:1}
.product-code{font-size:10px;color:var(--accent);font-family:monospace;font-weight:700}
.product-name{font-size:13px;font-weight:600;color:var(--text);margin-top:2px}
.product-meta{font-size:11px;color:var(--text3);margin-top:4px}
.stock-badge{display:inline-block;padding:4px 8px;border-radius:4px;font-size:10px;font-weight:700;text-transform:uppercase}
.stock-low{background:rgba(248,113,113,.12);color:var(--danger)}
.stock-medium{background:rgba(245,158,11,.12);color:var(--warn)}
.stock-adequate{background:rgba(79,255,176,.12);color:var(--accent)}
.history-table{width:100%;border-collapse:collapse;margin-top:12px}
.history-table thead{background:var(--surface2);border-bottom:2px solid var(--border)}
.history-table th{padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px}
.history-table td{padding:10px;border-bottom:1px solid var(--border);font-size:11px}
.history-table tbody tr:hover{background:var(--surface2)}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px);overflow-y:auto;padding:20px}
.modal.show{display:flex}
.modal-content{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:28px;width:90%;max-width:700px;box-shadow:0 8px 40px rgba(0,0,0,.5)}
.prescription-card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:12px;cursor:pointer;transition:all .2s}
.prescription-card:hover{border-color:var(--border2);background:var(--surface2)}
.prescription-card.selected{border-color:var(--accent);background:rgba(79,255,176,.05)}
.prescription-info{flex:1}
.prescription-patient{font-size:13px;font-weight:600;color:var(--text)}
.prescription-meta{font-size:11px;color:var(--text3);margin-top:4px}
.btn-view-file{padding:6px 12px;font-size:11px;background:var(--surface2);border:1px solid var(--border);color:var(--text);border-radius:6px;cursor:pointer;transition:all .2s}
.btn-view-file:hover{border-color:var(--border2);background:var(--surface3)}
.btn-danger{background:var(--danger);color:white;border:none}
.btn-danger:hover{background:#dc2626;transform:translateY(-1px)}
</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>

<div class="process-wrapper" style="max-width:1100px">
<h1><i class="fas fa-pills" style="color:var(--accent);margin-right:10px"></i>Dispense Products</h1>
<p class="subtitle">Dispense medications to customers with verified prescriptions and check product availability</p>

<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<div class="content-section">
<h2>Dispense Medicine</h2>

<?php if(empty($available_prescriptions)): ?>
<div class="empty-state"><p>No verified prescriptions available to dispense.</p></div>
<?php else: ?>

<form method="POST" id="dispenseForm">
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
<input type="hidden" name="action" value="dispense">

<div class="form-group">
  <label>Select Prescription <span class="required">*</span></label>
  <div id="prescriptionsList" style="max-height:300px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;padding:8px">
    <?php foreach($available_prescriptions as $rx): ?>
    <div class="prescription-card" onclick="selectPrescription(<?php echo $rx['id']; ?>, '<?php echo htmlspecialchars(addslashes($rx['patient_name'])); ?>', '<?php echo htmlspecialchars(addslashes($rx['prescription_image'])); ?>')">
      <div class="prescription-info">
        <div class="prescription-patient">
          <i class="fas fa-user" style="margin-right:6px;color:var(--accent)"></i>
          <?php echo htmlspecialchars($rx['patient_name']); ?>
        </div>
        <div class="prescription-meta">
          Dr. <?php echo htmlspecialchars($rx['doctor_name']); ?> · <?php echo date('M d, Y H:i', strtotime($rx['upload_date'])); ?>
        </div>
      </div>
      <button type="button" class="btn-view-file" onclick="viewPrescriptionFile(event, '<?php echo htmlspecialchars($rx['prescription_image']); ?>')">
        <i class="fas fa-file"></i> View File
      </button>
    </div>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="prescription_id" id="selectedPrescriptionId" required>
</div>

<div id="prescriptionInfo" style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:20px;display:none">
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;font-size:12px">
    <div>
      <div style="color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Patient</div>
      <div id="infoPatient" style="color:var(--text);font-weight:600">—</div>
    </div>
    <div>
      <div style="color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Doctor</div>
      <div id="infoDoctor" style="color:var(--text);font-weight:600">—</div>
    </div>
    <div>
      <div style="color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Date</div>
      <div id="infoDate" style="color:var(--text);font-weight:600">—</div>
    </div>
  </div>
</div>

<div style="margin-bottom:20px">
  <label style="display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:12px;text-transform:uppercase;letter-spacing:.05em">Select Product to Dispense <span class="required">*</span></label>
  <div id="productsList" style="max-height:400px;overflow-y:auto">
    <?php foreach($products as $p): 
        $stockClass = 'stock-adequate';
        if ($p['stock_status'] === 'Low') $stockClass = 'stock-low';
        elseif ($p['stock_status'] === 'Medium') $stockClass = 'stock-medium';
    ?>
    <div class="product-card" onclick="selectProduct(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['product_name'])); ?>', <?php echo $p['current_stock']; ?>)">
      <div class="product-info">
        <div class="product-code"><?php echo htmlspecialchars($p['product_code']); ?></div>
        <div class="product-name"><?php echo htmlspecialchars($p['product_name']); ?></div>
        <div class="product-meta">
          <?php echo htmlspecialchars($p['generic_name'] ?? '—'); ?> · <?php echo htmlspecialchars($p['form'] ?? '—'); ?> · Pack: <?php echo $p['pack_size']; ?>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
        <span class="stock-badge <?php echo $stockClass; ?>">
          <?php echo $p['stock_status']; ?> (<?php echo $p['current_stock']; ?>)
        </span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="product_id" id="selectedProductId" required>
</div>

<div class="grid-2">
  <div class="form-group">
    <label>Selected Product</label>
    <input type="text" id="selectedProductName" readonly style="background:var(--surface2);cursor:not-allowed">
  </div>
  <div class="form-group">
    <label>Quantity to Dispense <span class="required">*</span></label>
    <input type="number" name="quantity" id="quantityInput" min="1" required placeholder="Enter quantity">
  </div>
</div>

<div style="display:flex;gap:10px;justify-content:flex-end">
  <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
  <button type="submit" class="btn btn-primary" onclick="return confirm('Confirm dispensing this medicine?')"><i class="fas fa-check"></i> Dispense Medicine</button>
</div>
</form>

<?php endif; ?>
</div>

<!-- Customer Prescriptions Section -->
<div class="content-section">
<h2>Customer Prescriptions</h2>

<?php 
// Fetch all customer prescriptions (both pending and confirmed)
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.first_name, u.last_name, u.email
        FROM prescriptions p
        JOIN users u ON p.customer_id = u.id
        ORDER BY p.upload_date DESC
    ");
    $stmt->execute();
    $all_customer_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $all_customer_prescriptions = [];
}
?>

<?php if(empty($all_customer_prescriptions)): ?>
<div class="empty-state"><p>No customer prescriptions available.</p></div>
<?php else: ?>

<div style="display:grid;gap:10px">
<?php foreach($all_customer_prescriptions as $rx): $slug=strtolower($rx['status']); ?>
<div class="rx-row">
<div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">
<div style="width:36px;height:36px;border-radius:8px;background:rgba(56,189,248,.1);display:flex;align-items:center;justify-content:center;color:var(--accent2);flex-shrink:0"><i class="fas fa-file-medical"></i></div>
<div style="min-width:0;flex:1">
<div style="font-size:13px;font-weight:600;color:var(--text)">Patient: <?php echo htmlspecialchars($rx['patient_name']); ?></div>
<div style="font-size:11px;color:var(--text3);margin-top:2px">Dr. <?php echo htmlspecialchars($rx['doctor_name']); ?> · <?php echo date('M d, Y H:i',strtotime($rx['upload_date'])); ?></div>
<div style="font-size:10px;color:var(--text3);margin-top:2px">Customer: <?php echo htmlspecialchars($rx['first_name'].' '.$rx['last_name']); ?> (<?php echo htmlspecialchars($rx['email']); ?>)</div>
<?php if($rx['status'] === 'Rejected' && !empty($rx['rejection_remarks'])): ?>
<div style="font-size:10px;color:var(--danger);margin-top:4px;padding:6px;background:rgba(248,113,113,.1);border-radius:4px;border-left:2px solid var(--danger)">
<strong>Rejection Reason:</strong> <?php echo htmlspecialchars($rx['rejection_remarks']); ?>
</div>
<?php endif; ?>
</div></div>
<div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
<span class="status-badge status-<?php echo $slug; ?>"><?php echo ucfirst($rx['status']); ?></span>
<button type="button" class="btn btn-sm" style="background:var(--surface2);border:1px solid var(--border);color:var(--text)" onclick="viewPrescriptionFile(event, '<?php echo htmlspecialchars($rx['prescription_image']); ?>')"><i class="fas fa-file"></i> View</button>
<?php if($rx['status'] === 'Pending'): ?>
<button type="button" class="btn btn-sm" style="background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.3);color:var(--danger)" onclick="openRejectModal(<?php echo $rx['id']; ?>)"><i class="fas fa-times"></i> Mark as Reject</button>
<?php endif; ?>
</div></div>
<?php endforeach; ?>
</div>

<?php endif; ?>
</div>

<!-- Prescription File Viewer Modal -->
<div id="fileModal" class="modal">
<div class="modal-content" style="max-width:800px">
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

<!-- Rejection Modal -->
<div id="rejectModal" class="modal">
<div class="modal-content" style="max-width:500px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h2 style="font-size:18px;font-weight:700;color:var(--text);margin:0">Reject Prescription</h2>
    <button type="button" style="background:none;border:none;font-size:24px;color:var(--text3);cursor:pointer;padding:0" onclick="closeRejectModal()"><i class="fas fa-times"></i></button>
  </div>
  <form id="rejectForm" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="action" value="reject_prescription">
    <input type="hidden" name="prescription_id" id="rejectPrescriptionId">
    
    <div class="form-group">
      <label style="display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em">Rejection Reason <span class="required">*</span></label>
      <textarea name="rejection_remarks" id="rejectionRemarks" required placeholder="Explain why this prescription is being rejected..." style="width:100%;padding:12px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-family:inherit;font-size:13px;min-height:120px;resize:vertical"></textarea>
    </div>
    
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
      <button type="button" class="btn btn-secondary" onclick="closeRejectModal()"><i class="fas fa-times"></i> Cancel</button>
      <button type="submit" class="btn btn-danger"><i class="fas fa-ban"></i> Reject Prescription</button>
    </div>
  </form>
</div>
</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>

<script>
function selectPrescription(id, patient, filename) {
  document.getElementById('selectedPrescriptionId').value = id;
  document.getElementById('prescriptionInfo').style.display = 'block';
  document.getElementById('infoPatient').textContent = patient;
  
  // Update card selection
  document.querySelectorAll('.prescription-card').forEach(card => card.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
}

function selectProduct(productId, productName, currentStock) {
  document.getElementById('selectedProductId').value = productId;
  document.getElementById('selectedProductName').value = productName + ' (Stock: ' + currentStock + ')';
  
  // Update card selection
  document.querySelectorAll('.product-card').forEach(card => card.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  
  // Set max quantity
  document.getElementById('quantityInput').max = currentStock;
  document.getElementById('quantityInput').value = 1;
}

function openRejectModal(prescriptionId) {
  document.getElementById('rejectPrescriptionId').value = prescriptionId;
  document.getElementById('rejectionRemarks').value = '';
  document.getElementById('rejectModal').classList.add('show');
}

function closeRejectModal() {
  document.getElementById('rejectModal').classList.remove('show');
}

function viewPrescriptionFile(e, filename) {
  e.stopPropagation();
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
  if (e.target === document.getElementById('rejectModal')) {
    closeRejectModal();
  }
});

// Handle pre-selection from URL parameters
window.addEventListener('load', () => {
  const preselectedRx = <?php echo $preselected_rx; ?>;
  const preselectedProduct = <?php echo $preselected_product; ?>;
  
  if (preselectedRx > 0) {
    const rxCard = document.querySelector(`.prescription-card[onclick*="selectPrescription(${preselectedRx}"]`);
    if (rxCard) {
      rxCard.click();
    }
  }
  
  if (preselectedProduct > 0) {
    const productCard = document.querySelector(`.product-card[onclick*="selectProduct(${preselectedProduct}"]`);
    if (productCard) {
      productCard.click();
    }
  }
});
</script>
</body></html>
