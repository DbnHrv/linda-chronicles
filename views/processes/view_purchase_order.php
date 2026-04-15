<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(14);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$purchase_orders = [];

try {
    $stmt = $pdo->prepare("
        SELECT po.*, 
               sr.product_id, sr.quantity_needed, sr.reason,
               p.product_code, p.product_name, p.generic_name, p.form, p.pack_size, p.unit_price, p.cost_price, p.current_stock, p.reorder_level,
               m.manufacturer_name,
               u.first_name, u.last_name
        FROM purchase_orders po
        LEFT JOIN stock_requisitions sr ON po.requisition_id = sr.id
        LEFT JOIN products p ON sr.product_id = p.id
        LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
        JOIN users u ON po.created_by = u.id
        ORDER BY po.po_date DESC
    ");
    $stmt->execute();
    $purchase_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $purchase_orders = [];
}

// Handle PO status update
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update_status') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) { 
        $message='Invalid token.'; 
        $message_type='error'; 
    } else { 
        try {
            $po_id = intval($_POST['po_id']??0);
            $status = sanitize($_POST['status']??'');
            
            if (!$po_id || !$status) throw new Exception('Required fields missing.');
            
            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
            $stmt->execute([$status, $po_id]);
            
            $message = 'Purchase order status updated to ' . $status . '.';
            $message_type = 'success';
            
            // Refresh list
            $stmt = $pdo->prepare("
                SELECT po.*, 
                       sr.product_id, sr.quantity_needed, sr.reason,
                       p.product_code, p.product_name, p.generic_name, p.form, p.pack_size, p.unit_price, p.cost_price, p.current_stock, p.reorder_level,
                       m.manufacturer_name,
                       u.first_name, u.last_name
                FROM purchase_orders po
                LEFT JOIN stock_requisitions sr ON po.requisition_id = sr.id
                LEFT JOIN products p ON sr.product_id = p.id
                LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
                JOIN users u ON po.created_by = u.id
                ORDER BY po.po_date DESC
            ");
            $stmt->execute();
            $purchase_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Purchase Order Details — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
.po-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:16px;display:grid;grid-template-columns:1fr auto;gap:20px;align-items:start}
.po-header{display:grid;gap:12px}
.po-number{font-size:16px;font-weight:700;color:var(--text)}
.po-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;font-size:12px}
.po-meta-item{display:flex;flex-direction:column}
.po-meta-label{color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
.po-meta-value{color:var(--text);font-weight:600}
.po-details{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px;margin-top:12px}
.po-details-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;font-size:12px}
.po-details-item{display:flex;flex-direction:column}
.po-details-label{color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
.po-details-value{color:var(--text);font-weight:600}
.status-badge{display:inline-block;padding:6px 12px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.status-draft{background:rgba(156,163,175,.12);color:#6b7280}
.status-sent{background:rgba(59,130,246,.12);color:#3b82f6}
.status-acknowledged{background:rgba(34,197,94,.12);color:#22c55e}
.status-delivered{background:rgba(79,255,176,.12);color:var(--accent)}
.status-cancelled{background:rgba(248,113,113,.12);color:var(--danger)}
.po-actions{display:flex;flex-direction:column;gap:8px}
.btn-sm{padding:8px 12px;font-size:12px}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
.modal.show{display:flex}
.modal-content{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:28px;width:90%;max-width:500px}
</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>

<div class="process-wrapper" style="max-width:1200px">
<h1><i class="fas fa-file-invoice-dollar" style="color:var(--accent);margin-right:10px"></i>Purchase Order Details</h1>
<p class="subtitle">View and manage all generated purchase orders</p>

<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<div class="content-section">
<h2>All Purchase Orders</h2>

<?php if(empty($purchase_orders)): ?>
<div class="empty-state">
  <i class="fas fa-inbox" style="font-size:48px;margin-bottom:12px;display:block;color:var(--accent2)"></i>
  <p>No purchase orders yet.</p>
</div>
<?php else: ?>

<?php foreach($purchase_orders as $po): 
    $isManualItem = is_null($po['product_id']);
    $statusClass = 'status-' . strtolower($po['status']);
    $totalAmount = $isManualItem ? 0 : ($po['quantity_needed'] * $po['unit_price']);
?>

<div class="po-card">
  <div class="po-header">
    <div class="po-number">
      <i class="fas fa-file-invoice" style="margin-right:8px;color:var(--accent)"></i>
      <?php echo htmlspecialchars($po['po_number']); ?>
    </div>
    
    <div class="po-meta">
      <div class="po-meta-item">
        <div class="po-meta-label">Date</div>
        <div class="po-meta-value"><?php echo date('M d, Y', strtotime($po['po_date'])); ?></div>
      </div>
      <div class="po-meta-item">
        <div class="po-meta-label">Created By</div>
        <div class="po-meta-value"><?php echo htmlspecialchars($po['first_name'] . ' ' . $po['last_name']); ?></div>
      </div>
      <div class="po-meta-item">
        <div class="po-meta-label">Supplier</div>
        <div class="po-meta-value"><?php echo htmlspecialchars($po['manufacturer_name'] ?? '—'); ?></div>
      </div>
    </div>

    <div class="po-details">
      <div style="font-weight:700;color:var(--text);margin-bottom:12px;font-size:13px">
        <?php if($isManualItem): ?>
          <i class="fas fa-plus-circle" style="margin-right:6px;color:var(--accent)"></i>Manual Request
        <?php else: ?>
          <i class="fas fa-box" style="margin-right:6px;color:var(--accent)"></i><?php echo htmlspecialchars($po['product_name']); ?>
        <?php endif; ?>
      </div>

      <div class="po-details-grid">
        <?php if(!$isManualItem): ?>
        <div class="po-details-item">
          <div class="po-details-label">Product Code</div>
          <div class="po-details-value" style="font-family:monospace;color:var(--accent)"><?php echo htmlspecialchars($po['product_code']); ?></div>
        </div>
        <div class="po-details-item">
          <div class="po-details-label">Generic Name</div>
          <div class="po-details-value"><?php echo htmlspecialchars($po['generic_name'] ?? '—'); ?></div>
        </div>
        <div class="po-details-item">
          <div class="po-details-label">Form</div>
          <div class="po-details-value"><?php echo htmlspecialchars($po['form'] ?? '—'); ?></div>
        </div>
        <div class="po-details-item">
          <div class="po-details-label">Pack Size</div>
          <div class="po-details-value"><?php echo htmlspecialchars($po['pack_size'] ?? '—'); ?></div>
        </div>
        <div class="po-details-item">
          <div class="po-details-label">Quantity</div>
          <div class="po-details-value" style="color:var(--accent);font-weight:700"><?php echo $po['quantity_needed']; ?> PCS</div>
        </div>
        <div class="po-details-item">
          <div class="po-details-label">Current Stock</div>
          <div class="po-details-value" style="color:<?php echo ($po['current_stock'] ?? 0) < ($po['reorder_level'] ?? 50) ? 'var(--danger)' : 'var(--accent)'; ?>;font-weight:700">
            <?php echo $po['current_stock'] ?? 0; ?> PCS
            <?php if(($po['current_stock'] ?? 0) < ($po['reorder_level'] ?? 50)): ?>
              <i class="fas fa-exclamation-triangle" style="margin-left:6px;font-size:10px;color:var(--danger)" title="Low stock"></i>
            <?php endif; ?>
          </div>
        </div>
        <div class="po-details-item">
          <div class="po-details-label">Reorder Level</div>
          <div class="po-details-value"><?php echo $po['reorder_level'] ?? 50; ?> PCS</div>
        </div>
        <div class="po-details-item">
          <div class="po-details-label">Unit Price</div>
          <div class="po-details-value">₱<?php echo number_format($po['unit_price'], 2); ?></div>
        </div>
        <div class="po-details-item">
          <div class="po-details-label">Cost Price</div>
          <div class="po-details-value">₱<?php echo number_format($po['cost_price'], 2); ?></div>
        </div>
        <div class="po-details-item">
          <div class="po-details-label">Total Amount</div>
          <div class="po-details-value" style="color:var(--accent);font-weight:700">₱<?php echo number_format($totalAmount, 2); ?></div>
        </div>
        <?php else: ?>
        <div class="po-details-item" style="grid-column:1/-1">
          <div class="po-details-label">Request Details</div>
          <div class="po-details-value" style="font-size:12px;line-height:1.5;color:var(--text2)"><?php echo htmlspecialchars($po['reason']); ?></div>
        </div>
        <?php endif; ?>
      </div>

      <?php if(!empty($po['notes'])): ?>
      <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
        <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Notes</div>
        <div style="font-size:12px;color:var(--text2)"><?php echo htmlspecialchars($po['notes']); ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="po-actions">
    <span class="status-badge <?php echo $statusClass; ?>"><?php echo $po['status']; ?></span>
    <?php if(!$isManualItem && ($po['current_stock'] ?? 0) < ($po['quantity_needed'] ?? 0)): ?>
    <span class="status-badge" style="background:rgba(248,113,113,.12);color:var(--danger)">
      <i class="fas fa-exclamation-circle" style="margin-right:4px"></i>Low Stock
    </span>
    <?php elseif(!$isManualItem && ($po['current_stock'] ?? 0) >= ($po['quantity_needed'] ?? 0)): ?>
    <span class="status-badge" style="background:rgba(79,255,176,.12);color:var(--accent)">
      <i class="fas fa-check-circle" style="margin-right:4px"></i>Available
    </span>
    <?php endif; ?>
    <button type="button" class="btn btn-sm" onclick="openStatusModal(<?php echo $po['id']; ?>)" style="background:var(--surface2);border:1px solid var(--border);color:var(--text)">
      <i class="fas fa-edit"></i> Update Status
    </button>
    <button type="button" class="btn btn-sm" style="background:var(--surface2);border:1px solid var(--border);color:var(--text)">
      <i class="fas fa-print"></i> Print
    </button>
  </div>
</div>

<?php endforeach; ?>

<?php endif; ?>
</div>

<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="modal">
<div class="modal-content">
  <span style="float:right;font-size:22px;cursor:pointer;color:var(--text3)" onclick="closeStatusModal()">&times;</span>
  <h2 style="font-size:17px;font-weight:700;color:var(--text);margin-bottom:18px">Update Purchase Order Status</h2>
  
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" id="modal_po_id" name="po_id">
    
    <div class="form-group">
      <label>Status <span class="required">*</span></label>
      <select name="status" required>
        <option value="">— Select Status —</option>
        <option value="Draft">Draft</option>
        <option value="Sent">Sent to Supplier</option>
        <option value="Acknowledged">Acknowledged</option>
        <option value="Delivered">Delivered</option>
        <option value="Cancelled">Cancelled</option>
      </select>
    </div>
    
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
      <button type="button" onclick="closeStatusModal()" class="btn btn-secondary">Cancel</button>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
    </div>
  </form>
</div>
</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>

<script>
function openStatusModal(poId) {
  document.getElementById('modal_po_id').value = poId;
  document.getElementById('statusModal').classList.add('show');
}

function closeStatusModal() {
  document.getElementById('statusModal').classList.remove('show');
}

window.addEventListener('click', e => {
  if (e.target === document.getElementById('statusModal')) {
    closeStatusModal();
  }
});
</script>
</body></html>
