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
        $rid=intval($_POST['requisition_id']??0); $sid=intval($_POST['supplier_id']??0)?:null; $addr=sanitize($_POST['delivery_address']??''); $notes=sanitize($_POST['notes']??'');
        if (!$rid) throw new Exception('Requisition required.');
        $po_number='PO-'.date('Ymd').'-'.strtoupper(substr(uniqid(),-6));
        $stmt=$pdo->prepare("INSERT INTO purchase_orders (po_number,requisition_id,supplier_id,created_by,delivery_address,notes,status) VALUES (?,?,?,?,?,?,'Draft')");
        $stmt->execute([$po_number,$rid,$sid,$_SESSION['user_id'],$addr,$notes]);
        $stmt=$pdo->prepare("UPDATE stock_requisitions SET status='PO Generated' WHERE id=?"); $stmt->execute([$rid]);
        $message='Purchase order '.$po_number.' generated.'; $message_type='success';
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
<?php if(empty($approved_requisitions)): ?><div class="empty-state"><p>No approved requisitions. Approve stock requests in Process 13 first.</p></div>
<?php else: foreach($approved_requisitions as $r): ?>
<div class="info-card"><div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
<div><div style="font-size:14px;font-weight:700;color:var(--text)"><?php echo htmlspecialchars($r['product_name']); ?></div>
<p style="font-size:12px;color:var(--text3);margin-top:3px">Code: <?php echo htmlspecialchars($r['product_code']); ?> · Qty: <?php echo $r['quantity_needed']; ?> · Unit: ₱<?php echo number_format($r['unit_price'],2); ?></p>
<p style="font-size:12px;color:var(--text2);margin-top:2px">Requested by <?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?></p>
<p style="font-size:13px;font-weight:600;color:var(--accent);margin-top:4px">Est. Total: ₱<?php echo number_format($r['quantity_needed']*$r['unit_price'],2); ?></p>
</div>
<button onclick="openModal(<?php echo $r['id']; ?>,'<?php echo htmlspecialchars($r['product_name']); ?>')" class="btn btn-sm"><i class="fas fa-file-invoice"></i> Generate PO</button>
</div></div>
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
<p id="modal-product" style="font-size:13px;color:var(--text2);margin-bottom:18px"></p>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="generate_po"><input type="hidden" id="req_id" name="requisition_id">
<div class="form-group"><label>Supplier</label><select name="supplier_id"><option value="">— Select Supplier —</option><?php foreach($suppliers as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['manufacturer_name']); ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>Delivery Address</label><textarea name="delivery_address" rows="2" placeholder="Enter delivery address…"></textarea></div>
<div class="form-group"><label>Notes</label><textarea name="notes" rows="2" placeholder="Additional notes…"></textarea></div>
<div style="display:flex;gap:10px;justify-content:flex-end"><button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-file-invoice"></i> Generate PO</button></div>
</form></div></div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
<script>
function openModal(id,name){document.getElementById('req_id').value=id;document.getElementById('modal-product').textContent='Product: '+name;document.getElementById('poModal').classList.add('show');}
function closeModal(){document.getElementById('poModal').classList.remove('show');}
window.addEventListener('click',e=>{if(e.target===document.getElementById('poModal'))closeModal();});
</script>
</body></html>
