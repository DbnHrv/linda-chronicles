<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(15);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$uploads_dir = realpath(__DIR__ . '/../../uploads') ?: (__DIR__ . '/../../uploads');
if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);

// Get all products with availability status
$products = [];
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

try { $prescriptions = $processModel->getPrescriptionsByCustomer($_SESSION['user_id']); } catch(Exception $e){ $prescriptions=[]; }

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='upload_prescription') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
    else { try {
        if (empty($_FILES['prescription_image']['name'])||$_FILES['prescription_image']['error']===UPLOAD_ERR_NO_FILE) throw new Exception('Please select a file.');
        $file=$_FILES['prescription_image'];
        if ($file['error']!==UPLOAD_ERR_OK) throw new Exception('Upload error.');
        if ($file['size']>10*1024*1024) throw new Exception('File exceeds 10MB.');
        $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','pdf'])) throw new Exception('Only JPG, PNG, PDF allowed.');
        $filename='rx_'.$_SESSION['user_id'].'_'.time().'.'.$ext;
        if (!move_uploaded_file($file['tmp_name'],$uploads_dir.DIRECTORY_SEPARATOR.$filename)) throw new Exception('Could not save file.');
        $doctor_name=sanitize($_POST['doctor_name']??''); $patient_name=sanitize($_POST['patient_name']??'');
        $stmt=$pdo->prepare("INSERT INTO prescriptions (customer_id,prescription_image,doctor_name,patient_name,upload_date,status) VALUES (?,?,?,?,NOW(),'Pending')");
        $stmt->execute([$_SESSION['user_id'],$filename,$doctor_name,$patient_name]);
        $message='Prescription uploaded successfully.'; $message_type='success';
        $prescriptions=$processModel->getPrescriptionsByCustomer($_SESSION['user_id']);
    } catch(Exception $e){$message=$e->getMessage();$message_type='error';} }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Upload Prescription — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
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
.availability-badge{display:inline-block;padding:6px 12px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.availability-low{background:rgba(248,113,113,.12);color:var(--danger)}
.availability-medium{background:rgba(245,158,11,.12);color:var(--warn)}
.availability-adequate{background:rgba(79,255,176,.12);color:var(--accent)}
.product-actions{display:flex;gap:8px;margin-top:8px}
.btn-sm{padding:8px 12px;font-size:12px;flex:1}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px);overflow-y:auto;padding:20px}
.modal.show{display:flex}
.modal-content{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:28px;width:90%;max-width:600px;box-shadow:0 8px 40px rgba(0,0,0,.5)}
.modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.modal-title{font-size:18px;font-weight:700;color:var(--text)}
.modal-close{background:none;border:none;font-size:24px;color:var(--text3);cursor:pointer;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center}
.modal-section{margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border)}
.modal-section:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0}
.modal-label{font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px}
.modal-value{font-size:13px;color:var(--text);font-weight:600}
.modal-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
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
<h1><i class="fas fa-file-medical" style="color:var(--accent);margin-right:10px"></i>Upload Doctor's Prescription</h1>
<p class="subtitle">Submit your prescription for medication processing and check product availability</p>
<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<div class="content-section"><h2>Upload New Prescription</h2>
<form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="upload_prescription">
<div class="grid-2">
<div class="form-group"><label>Patient Name <span class="required">*</span></label><input type="text" name="patient_name" required placeholder="Full name of patient"></div>
<div class="form-group"><label>Doctor Name <span class="required">*</span></label><input type="text" name="doctor_name" required placeholder="Prescribing doctor's name"></div>
</div>
<div class="form-group"><label>Prescription File <span class="required">*</span> <span style="color:var(--text3);font-weight:400">(JPG, PNG, PDF — max 10MB)</span></label>
<div class="file-upload" onclick="document.getElementById('rx_file').click()">
<i class="fas fa-cloud-upload-alt" style="font-size:28px;color:var(--text3);display:block;margin-bottom:8px"></i>
<p id="rx_label">Click to upload prescription file</p>
<input type="file" id="rx_file" name="prescription_image" accept=".jpg,.jpeg,.png,.pdf" required onchange="document.getElementById('rx_label').textContent=this.files[0]?this.files[0].name:'Click to upload'">
</div></div>
<button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Prescription</button>
</form></div>

<!-- Product Availability Section -->
<div class="content-section">
<h2>Check Product Availability</h2>

<?php 
$low_count = 0;
$medium_count = 0;
$adequate_count = 0;
foreach ($products as $p) {
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
<?php if(empty($products)): ?>
<div class="empty-state">
  <p>No products available.</p>
</div>
<?php else: ?>

<div class="product-grid">
<?php foreach($products as $product): 
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
    <div><strong>Generic:</strong> <?php echo htmlspecialchars($product['generic_name'] ?? '—'); ?></div>
    <div><strong>Form:</strong> <?php echo htmlspecialchars($product['form'] ?? '—'); ?></div>
    <div><strong>Pack Size:</strong> <?php echo $product['pack_size']; ?></div>
    <div><strong>Manufacturer:</strong> <?php echo htmlspecialchars($product['manufacturer_name'] ?? '—'); ?></div>
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

  <div class="product-actions">
    <button type="button" class="btn btn-sm" style="background:var(--surface2);border:1px solid var(--border);color:var(--text)" onclick="openProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)">
      <i class="fas fa-eye"></i> View Product
    </button>
  </div>
</div>

<?php endforeach; ?>
</div>

<?php endif; ?>
</div>

<div class="content-section"><h2>My Prescriptions</h2>
<?php if(empty($prescriptions)): ?><div class="empty-state"><p>No prescriptions uploaded yet.</p></div>
<?php else: foreach($prescriptions as $rx): $slug=strtolower($rx['status']); ?>
<div class="info-card"><div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
<div><div style="font-size:14px;font-weight:700;color:var(--text)">Patient: <?php echo htmlspecialchars($rx['patient_name']); ?></div>
<p style="font-size:12px;color:var(--text3);margin-top:3px">Dr. <?php echo htmlspecialchars($rx['doctor_name']); ?> · <?php echo date('M d, Y H:i',strtotime($rx['upload_date'])); ?></p>
</div><span class="status-badge status-<?php echo $slug; ?>"><?php echo $rx['status']; ?></span>
</div></div>
<?php endforeach; endif; ?>
</div>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<!-- Product Details Modal -->
<div id="productModal" class="modal">
<div class="modal-content">
  <div class="modal-header">
    <h2 class="modal-title">Product Details</h2>
    <button type="button" class="modal-close" onclick="closeProductModal()"><i class="fas fa-times"></i></button>
  </div>

  <div class="modal-section">
    <div class="modal-label">Product Code</div>
    <div class="modal-value" id="modalCode" style="font-family:monospace;color:var(--accent)">—</div>
  </div>

  <div class="modal-section">
    <div class="modal-label">Product Name</div>
    <div class="modal-value" id="modalName">—</div>
  </div>

  <div class="modal-section">
    <div class="modal-label">Generic Name</div>
    <div class="modal-value" id="modalGeneric">—</div>
  </div>

  <div class="modal-section">
    <div class="modal-grid">
      <div>
        <div class="modal-label">Form</div>
        <div class="modal-value" id="modalForm">—</div>
      </div>
      <div>
        <div class="modal-label">Pack Size</div>
        <div class="modal-value" id="modalPackSize">—</div>
      </div>
      <div>
        <div class="modal-label">Manufacturer</div>
        <div class="modal-value" id="modalManufacturer">—</div>
      </div>
    </div>
  </div>

  <div class="modal-section">
    <div class="modal-label">Description</div>
    <div class="modal-value" id="modalDescription">—</div>
  </div>

  <div class="modal-section">
    <div class="modal-grid">
      <div>
        <div class="modal-label">Unit Price</div>
        <div class="modal-value" id="modalUnitPrice">—</div>
      </div>
      <div>
        <div class="modal-label">Cost Price</div>
        <div class="modal-value" id="modalCostPrice">—</div>
      </div>
    </div>
  </div>

  <div class="modal-section">
    <div class="modal-label">Stock Information</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:8px">
      <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px">
        <div class="modal-label">Current Stock</div>
        <div class="modal-value" id="modalCurrentStock" style="font-size:18px;color:var(--accent)">—</div>
      </div>
      <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px">
        <div class="modal-label">Reorder Level</div>
        <div class="modal-value" id="modalReorderLevel" style="font-size:18px">—</div>
      </div>
    </div>
  </div>

  <div class="modal-section">
    <div class="modal-label">Availability Status</div>
    <div id="modalStatus" style="margin-top:8px"></div>
  </div>

  <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
    <button type="button" class="btn btn-secondary" onclick="closeProductModal()"><i class="fas fa-times"></i> Close</button>
  </div>
</div>
</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>

<script>
function openProductModal(product) {
  document.getElementById('modalCode').textContent = product.product_code;
  document.getElementById('modalName').textContent = product.product_name;
  document.getElementById('modalGeneric').textContent = product.generic_name || '—';
  document.getElementById('modalForm').textContent = product.form || '—';
  document.getElementById('modalPackSize').textContent = product.pack_size;
  document.getElementById('modalManufacturer').textContent = product.manufacturer_name || '—';
  document.getElementById('modalDescription').textContent = product.description || 'No description available';
  document.getElementById('modalUnitPrice').textContent = '₱' + parseFloat(product.unit_price).toFixed(2);
  document.getElementById('modalCostPrice').textContent = '₱' + parseFloat(product.cost_price).toFixed(2);
  document.getElementById('modalCurrentStock').textContent = product.current_stock + ' PCS';
  document.getElementById('modalReorderLevel').textContent = product.reorder_level + ' PCS';
  
  // Set status badge
  let statusClass = 'availability-adequate';
  let statusText = 'Adequate Stock';
  let statusIcon = 'check-circle';
  
  if (product.stock_status === 'Low') {
    statusClass = 'availability-low';
    statusText = 'Low Stock - Limited Availability';
    statusIcon = 'exclamation-triangle';
  } else if (product.stock_status === 'Medium') {
    statusClass = 'availability-medium';
    statusText = 'Medium Stock - Moderate Availability';
    statusIcon = 'info-circle';
  }
  
  document.getElementById('modalStatus').innerHTML = '<span class="availability-badge ' + statusClass + '"><i class="fas fa-' + statusIcon + '" style="margin-right:4px"></i>' + statusText + '</span>';
  
  document.getElementById('productModal').classList.add('show');
}

function closeProductModal() {
  document.getElementById('productModal').classList.remove('show');
}

window.addEventListener('click', e => {
  if (e.target === document.getElementById('productModal')) {
    closeProductModal();
  }
});
</script>
</body></html>
