<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(14);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$approved_requisitions = [];
$purchase_orders = [];
try { $approved_requisitions = $processModel->getApprovedRequisitions(); } catch(Exception $e){}
try { $purchase_orders = $processModel->getPurchaseOrders(); } catch(Exception $e){}
try { $stmt=$pdo->prepare("SELECT id,manufacturer_name FROM manufacturers ORDER BY manufacturer_name"); $stmt->execute(); $suppliers=$stmt->fetchAll(); } catch(Exception $e){$suppliers=[];}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='generate_po') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
    else { try {
        $manufacturer_id=intval($_POST['manufacturer_id']??0); $addr=sanitize($_POST['delivery_address']??''); $notes=sanitize($_POST['notes']??'');
        if (!$manufacturer_id) throw new Exception('Manufacturer required.');
        
        // Get all approved requisitions for this manufacturer
        $stmt=$pdo->prepare("SELECT sr.id FROM stock_requisitions sr JOIN products p ON sr.product_id=p.id WHERE sr.status='Approved' AND p.manufacturer_id=? ORDER BY sr.id");
        $stmt->execute([$manufacturer_id]);
        $requisitions=$stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($requisitions)) throw new Exception('No approved requisitions for this manufacturer.');
        
        // Create single PO for all requisitions from this manufacturer
        $po_number='PO-'.date('Ymd').'-'.strtoupper(substr(uniqid(),-6));
        $stmt=$pdo->prepare("INSERT INTO purchase_orders (po_number,requisition_id,supplier_id,created_by,delivery_address,notes,status) VALUES (?,?,?,?,?,?,'Draft')");
        
        // Use first requisition ID for the PO record, but mark all as PO Generated
        $first_req_id=$requisitions[0]['id'];
        $stmt->execute([$po_number,$first_req_id,$manufacturer_id,$_SESSION['user_id'],$addr,$notes]);
        
        // Mark all requisitions for this manufacturer as PO Generated
        foreach($requisitions as $req) {
            $stmt=$pdo->prepare("UPDATE stock_requisitions SET status='PO Generated' WHERE id=?");
            $stmt->execute([$req['id']]);
        }
        
        $message='Purchase order '.$po_number.' generated for '.count($requisitions).' requisition(s).'; $message_type='success';
        $approved_requisitions=$processModel->getApprovedRequisitions(); $purchase_orders=$processModel->getPurchaseOrders();
    } catch(Exception $e){$message=$e->getMessage();$message_type='error';} }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Generate Purchase Order — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px)}.modal.show{display:flex}.modal-content{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:28px;width:90%;max-width:560px;max-height:90vh;overflow-y:auto}</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>
<div class="process-wrapper" style="max-width:1100px">
<h1><i class="fas fa-file-invoice" style="color:var(--accent);margin-right:10px"></i>Generate Purchase Order</h1>
<p class="subtitle">Create purchase orders from approved stock requisitions</p>
<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<div class="content-section"><h2>Approved Requisitions</h2>
<?php 
// Group requisitions by manufacturer
$grouped_by_mfg = [];
foreach($approved_requisitions as $r) {
    $mfg_id = $r['manufacturer_id'] ?? 1;
    $mfg_name = $r['manufacturer_name'] ?? 'Unknown';
    if (!isset($grouped_by_mfg[$mfg_id])) {
        $grouped_by_mfg[$mfg_id] = ['name' => $mfg_name, 'items' => []];
    }
    $grouped_by_mfg[$mfg_id]['items'][] = $r;
}
?>
<?php if(empty($grouped_by_mfg)): ?><div class="empty-state"><p>No approved requisitions. Approve stock requests in Process 13 first.</p></div>
<?php else: foreach($grouped_by_mfg as $mfg_id => $mfg_data): ?>
<div style="margin-bottom:24px;border:1px solid var(--border2);border-radius:12px;overflow:hidden">
<!-- Manufacturer Header -->
<div style="background:linear-gradient(135deg, var(--accent), rgba(59,130,246,0.8));padding:16px;color:white">
<div style="display:flex;align-items:center;justify-content:space-between">
<div>
<h3 style="font-size:16px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px">
<i class="fas fa-industry"></i> <?php echo htmlspecialchars($mfg_data['name']); ?>
</h3>
<p style="font-size:12px;margin:4px 0 0 0;opacity:0.9">Total Items: <?php echo count($mfg_data['items']); ?></p>
</div>
<button onclick="openModal(<?php echo $mfg_id; ?>,'<?php echo htmlspecialchars($mfg_data['name']); ?>')" class="btn btn-sm" style="background:white;color:var(--accent);border:none;font-weight:600">
<i class="fas fa-file-invoice"></i> Generate PO
</button>
</div>
</div>

<!-- Products Table -->
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead>
<tr style="background:var(--bg2);border-bottom:1px solid var(--border2)">
<th style="padding:12px;text-align:left;font-weight:600;color:var(--text)">Product Name</th>
<th style="padding:12px;text-align:left;font-weight:600;color:var(--text)">Code</th>
<th style="padding:12px;text-align:center;font-weight:600;color:var(--text)">Quantity</th>
<th style="padding:12px;text-align:right;font-weight:600;color:var(--text)">Unit Price</th>
<th style="padding:12px;text-align:right;font-weight:600;color:var(--text)">Total</th>
<th style="padding:12px;text-align:center;font-weight:600;color:var(--text)">Action</th>
</tr>
</thead>
<tbody>
<?php foreach($mfg_data['items'] as $r): ?>
<tr style="border-bottom:1px solid var(--border2);transition:background 0.2s">
<td style="padding:12px;color:var(--text);font-weight:500"><?php echo htmlspecialchars($r['product_name']); ?></td>
<td style="padding:12px;color:var(--text3)"><?php echo htmlspecialchars($r['product_code']); ?></td>
<td style="padding:12px;text-align:center;color:var(--text)"><?php echo $r['quantity_needed']; ?></td>
<td style="padding:12px;text-align:right;color:var(--text)">₱<?php echo number_format($r['unit_price'],2); ?></td>
<td style="padding:12px;text-align:right;color:var(--accent);font-weight:600">₱<?php echo number_format($r['quantity_needed']*$r['unit_price'],2); ?></td>
<td style="padding:12px;text-align:center">
<button onclick="openDetailsModal(<?php echo htmlspecialchars(json_encode($r)); ?>)" class="btn btn-sm" style="background:transparent;border:1px solid var(--accent);color:var(--accent);padding:4px 8px;font-size:11px">
<i class="fas fa-info-circle"></i> Details
</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endforeach; endif; ?>
</div>
<div class="content-section"><h2>Generated Purchase Orders</h2>
<?php if(empty($purchase_orders)): ?><div class="empty-state"><p>No purchase orders yet.</p></div>
<?php else: foreach($purchase_orders as $po): ?>
<div class="info-card"><div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
<div><div style="font-size:14px;font-weight:700;color:var(--text)"><?php echo htmlspecialchars($po['po_number']); ?></div>
<p style="font-size:12px;color:var(--text3);margin-top:3px"><?php echo htmlspecialchars($po['product_name']??'—'); ?> · <?php echo date('M d, Y',strtotime($po['po_date'])); ?></p>
<p style="font-size:12px;color:var(--text2);margin-top:2px">By <?php echo htmlspecialchars($po['first_name'].' '.$po['last_name']); ?></p>
</div><span class="status-badge status-<?php echo strtolower($po['status']); ?>"><?php echo $po['status']; ?></span>
</div></div>
<?php endforeach; endif; ?>
</div>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<div id="poModal" class="modal">
<div class="modal-content">
<span style="float:right;font-size:22px;cursor:pointer;color:var(--text3)" onclick="closeModal()">&times;</span>
<h2 style="font-size:17px;font-weight:700;color:var(--text);margin-bottom:4px">Generate Purchase Order</h2>
<p id="modal-mfg" style="font-size:13px;color:var(--text2);margin-bottom:18px"></p>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="generate_po"><input type="hidden" id="mfg_id" name="manufacturer_id">
<div class="form-group"><label>Delivery Address</label><textarea name="delivery_address" rows="2" placeholder="Enter delivery address…"></textarea></div>
<div class="form-group"><label>Notes</label><textarea name="notes" rows="2" placeholder="Additional notes…"></textarea></div>
<div style="display:flex;gap:10px;justify-content:flex-end"><button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-file-invoice"></i> Generate PO</button></div>
</form></div></div>

<div id="detailsModal" class="modal">
<div class="modal-content" style="max-width:600px">
<span style="float:right;font-size:22px;cursor:pointer;color:var(--text3)" onclick="closeDetailsModal()">&times;</span>
<h2 style="font-size:17px;font-weight:700;color:var(--text);margin-bottom:16px">Requisition Details</h2>
<div id="details-content" style="font-size:13px">
<!-- Details will be populated by JavaScript -->
</div>
<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px"><button type="button" onclick="closeDetailsModal()" class="btn btn-secondary">Close</button></div>
</div></div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
<script>
function openModal(id,name){document.getElementById('mfg_id').value=id;document.getElementById('modal-mfg').textContent='Manufacturer: '+name;document.getElementById('poModal').classList.add('show');}
function closeModal(){document.getElementById('poModal').classList.remove('show');}

function openDetailsModal(data){
  const html = `
    <div style="display:grid;gap:12px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <p style="color:var(--text3);margin:0 0 4px 0;font-size:11px;text-transform:uppercase;font-weight:600">Product Name</p>
          <p style="color:var(--text);margin:0;font-weight:600">${data.product_name}</p>
        </div>
        <div>
          <p style="color:var(--text3);margin:0 0 4px 0;font-size:11px;text-transform:uppercase;font-weight:600">Product Code</p>
          <p style="color:var(--text);margin:0;font-weight:600">${data.product_code}</p>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <p style="color:var(--text3);margin:0 0 4px 0;font-size:11px;text-transform:uppercase;font-weight:600">Quantity Needed</p>
          <p style="color:var(--text);margin:0;font-weight:600">${data.quantity_needed} units</p>
        </div>
        <div>
          <p style="color:var(--text3);margin:0 0 4px 0;font-size:11px;text-transform:uppercase;font-weight:600">Unit Price</p>
          <p style="color:var(--text);margin:0;font-weight:600">₱${parseFloat(data.unit_price).toFixed(2)}</p>
        </div>
      </div>
      <div>
        <p style="color:var(--text3);margin:0 0 4px 0;font-size:11px;text-transform:uppercase;font-weight:600">Total Amount</p>
        <p style="color:var(--accent);margin:0;font-weight:700;font-size:16px">₱${(data.quantity_needed * data.unit_price).toFixed(2)}</p>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <p style="color:var(--text3);margin:0 0 4px 0;font-size:11px;text-transform:uppercase;font-weight:600">Requested By</p>
          <p style="color:var(--text);margin:0;font-weight:600">${data.first_name} ${data.last_name}</p>
        </div>
        <div>
          <p style="color:var(--text3);margin:0 0 4px 0;font-size:11px;text-transform:uppercase;font-weight:600">Status</p>
          <p style="color:var(--accent);margin:0;font-weight:600">${data.status}</p>
        </div>
      </div>
      <div>
        <p style="color:var(--text3);margin:0 0 4px 0;font-size:11px;text-transform:uppercase;font-weight:600">Reason</p>
        <p style="color:var(--text);margin:0">${data.reason || '—'}</p>
      </div>
    </div>
  `;
  document.getElementById('details-content').innerHTML = html;
  document.getElementById('detailsModal').classList.add('show');
}

function closeDetailsModal(){document.getElementById('detailsModal').classList.remove('show');}

window.addEventListener('click',e=>{
  if(e.target===document.getElementById('poModal'))closeModal();
  if(e.target===document.getElementById('detailsModal'))closeDetailsModal();
});
</script>
</body></html>
