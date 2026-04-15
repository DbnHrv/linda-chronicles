<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
$processModel = new ProcessModel($pdo);
try { $stmt=$pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE status IN ('Verified','Approved')"); $stmt->execute(); $pending_rx=$stmt->fetchColumn(); } catch(Exception $e){ $pending_rx=0; }
try { $stmt=$pdo->prepare("SELECT COUNT(*) FROM dispensed_medicines WHERE DATE(dispensed_at)=CURDATE()"); $stmt->execute(); $dispensed_today=$stmt->fetchColumn(); } catch(Exception $e){ $dispensed_today=0; }
try { $stmt=$pdo->prepare("SELECT COUNT(*) FROM products WHERE current_stock < reorder_level AND is_active=1"); $stmt->execute(); $low_stock=$stmt->fetchColumn(); } catch(Exception $e){ $low_stock=0; }
try { $stmt=$pdo->prepare("SELECT COUNT(*) FROM dispensed_medicines WHERE dispensed_by=?"); $stmt->execute([$_SESSION['user_id']]); $my_total=$stmt->fetchColumn(); } catch(Exception $e){ $my_total=0; }

// Fetch ALL prescriptions from all customers
try {
    $stmt=$pdo->prepare("SELECT p.id,p.customer_id,p.patient_name,p.doctor_name,p.upload_date,p.status,p.prescription_image,u.first_name,u.last_name,u.email FROM prescriptions p JOIN users u ON p.customer_id=u.id ORDER BY p.upload_date DESC");
    $stmt->execute(); $all_prescriptions=$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e){ $all_prescriptions=[]; }

// Fetch prescriptions ready to dispense (Verified/Approved)
try {
    $stmt=$pdo->prepare("SELECT p.id,p.customer_id,p.patient_name,p.doctor_name,p.upload_date,p.status,p.prescription_image,u.first_name,u.last_name,u.email FROM prescriptions p JOIN users u ON p.customer_id=u.id WHERE p.status IN ('Verified','Approved') ORDER BY p.upload_date DESC LIMIT 10");
    $stmt->execute(); $ready_prescriptions=$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e){ $ready_prescriptions=[]; }
$processes = array(
    16=>array('icon'=>'fa-search','name'=>'Check Product Availability','desc'=>'Verify stock levels before dispensing','url'=>'/views/processes/check_product_availability.php','color'=>'#38bdf8'),
    17=>array('icon'=>'fa-pills','name'=>'Dispense Product','desc'=>'Dispense medications to customers with verified prescriptions','url'=>'/views/processes/dispense_products.php','color'=>'#4fffb0'),
);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pharmacist Assistant — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
.wrap{max-width:1100px;margin:0 auto;padding:28px 24px}
.stat-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:28px}
.stat{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px 20px;display:flex;align-items:center;gap:14px}
.stat-ico{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.stat-val{font-size:24px;font-weight:700;color:var(--text);line-height:1}
.stat-lbl{font-size:11px;color:var(--text3);margin-top:3px}
.proc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
.proc-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px;text-decoration:none;display:block;transition:all .2s}
.proc-card:hover{border-color:var(--border2);transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.3)}
.sec{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin:24px 0 14px;display:flex;align-items:center;gap:8px}
.sec::after{content:'';flex:1;height:1px;background:var(--border)}
.rx-row{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.rx-row:hover{border-color:var(--border2)}
</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right">
<span class="user-role"><i class="fas fa-hand-holding-medical" style="margin-right:5px"></i><?php echo htmlspecialchars($_SESSION['user_first_name']??''); ?> · Pharmacist Assistant</span>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>
<div class="wrap">
<?php if(isset($_SESSION['success'])): ?><div class="alert alert-success" style="margin-bottom:20px"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<?php if(isset($_SESSION['error'])): ?><div class="alert alert-error" style="margin-bottom:20px"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<div style="background:linear-gradient(135deg,rgba(79,255,176,.08),rgba(34,197,94,.08));border:1px solid rgba(79,255,176,.18);border-radius:14px;padding:22px 26px;margin-bottom:28px;display:flex;align-items:center;gap:18px">
<div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#22c55e);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#0a0c10;flex-shrink:0"><?php echo strtoupper(substr($_SESSION['user_first_name']??'A',0,1)); ?></div>
<div style="flex:1">
<div style="font-size:18px;font-weight:700;color:var(--text)">Welcome, <?php echo htmlspecialchars($_SESSION['user_first_name']??'Assistant'); ?></div>
<div style="font-size:13px;color:var(--text2);margin-top:3px"><?php echo htmlspecialchars($_SESSION['user_email']??''); ?> · Pharmacist Assistant</div>
</div>
<?php if($pending_rx>0): ?>
<a href="<?php echo APP_URL; ?>/views/processes/dispense_products.php" style="text-decoration:none;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);border-radius:10px;padding:10px 16px;text-align:center;flex-shrink:0">
<div style="font-size:22px;font-weight:700;color:var(--warn)"><?php echo $pending_rx; ?></div>
<div style="font-size:11px;color:var(--warn)">Ready to Dispense</div>
</a>
<?php endif; ?>
</div>
<div class="stat-row">
<div class="stat"><div class="stat-ico" style="background:rgba(245,158,11,.12);color:var(--warn)"><i class="fas fa-file-medical"></i></div><div><div class="stat-val"><?php echo $pending_rx; ?></div><div class="stat-lbl">Ready to Dispense</div></div></div>
<div class="stat"><div class="stat-ico" style="background:rgba(79,255,176,.12);color:var(--accent)"><i class="fas fa-pills"></i></div><div><div class="stat-val"><?php echo $dispensed_today; ?></div><div class="stat-lbl">Dispensed Today</div></div></div>
<div class="stat"><div class="stat-ico" style="background:rgba(56,189,248,.12);color:var(--accent2)"><i class="fas fa-history"></i></div><div><div class="stat-val"><?php echo $my_total; ?></div><div class="stat-lbl">My Total Dispensed</div></div></div>
<div class="stat"><div class="stat-ico" style="background:rgba(248,113,113,.12);color:var(--danger)"><i class="fas fa-exclamation-triangle"></i></div><div><div class="stat-val"><?php echo $low_stock; ?></div><div class="stat-lbl">Low Stock Items</div></div></div>
</div>
<div class="sec">Dispensing Processes</div>
<div class="proc-grid">
<?php foreach($processes as $pid=>$p): ?>
<a href="<?php echo APP_URL.$p['url']; ?>" class="proc-card">
<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
<div style="width:40px;height:40px;border-radius:9px;background:<?php echo $p['color']; ?>1a;display:flex;align-items:center;justify-content:center;color:<?php echo $p['color']; ?>;font-size:17px;flex-shrink:0"><i class="fas <?php echo $p['icon']; ?>"></i></div>
<div><div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.07em">Process <?php echo $pid; ?></div>
<div style="font-size:14px;font-weight:700;color:var(--text)"><?php echo $p['name']; ?></div></div></div>
<p style="font-size:12px;color:var(--text2);line-height:1.5;margin-bottom:14px"><?php echo $p['desc']; ?></p>
<div style="font-size:12px;font-weight:600;color:<?php echo $p['color']; ?>">Open Process →</div>
</a>
<?php endforeach; ?>
</div>
<div class="sec">Prescriptions Ready to Dispense</div>
<?php if(empty($ready_prescriptions)): ?>
<div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:32px;text-align:center;color:var(--text3)">
<i class="fas fa-check-circle" style="font-size:32px;margin-bottom:10px;display:block;color:var(--accent)"></i>
<p>No prescriptions waiting to be dispensed right now.</p>
</div>
<?php else: ?>
<?php foreach($ready_prescriptions as $rx): $slug=strtolower($rx['status']); ?>
<div class="rx-row">
<div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">
<div style="width:36px;height:36px;border-radius:8px;background:rgba(79,255,176,.1);display:flex;align-items:center;justify-content:center;color:var(--accent);flex-shrink:0"><i class="fas fa-file-medical"></i></div>
<div style="min-width:0;flex:1">
<div style="font-size:13px;font-weight:600;color:var(--text)">Patient: <?php echo htmlspecialchars($rx['patient_name']); ?></div>
<div style="font-size:11px;color:var(--text3);margin-top:2px">Dr. <?php echo htmlspecialchars($rx['doctor_name']); ?> · <?php echo date('M d, Y',strtotime($rx['upload_date'])); ?></div>
<div style="font-size:10px;color:var(--text3);margin-top:2px">Customer: <?php echo htmlspecialchars($rx['first_name'].' '.$rx['last_name']); ?> (<?php echo htmlspecialchars($rx['email']); ?>)</div>
</div></div>
<div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
<span class="status-badge status-<?php echo $slug; ?>"><?php echo $rx['status']; ?></span>
<a href="<?php echo APP_URL; ?>/views/processes/dispense_products.php?rx_id=<?php echo $rx['id']; ?>" class="btn btn-sm" style="background:rgba(79,255,176,.1);color:var(--accent);border:1px solid rgba(79,255,176,.3)"><i class="fas fa-pills"></i> Dispense</a>
<button type="button" class="btn btn-sm" style="background:var(--surface2);border:1px solid var(--border);color:var(--text)" onclick="viewPrescriptionFile('<?php echo htmlspecialchars($rx['prescription_image']); ?>')"><i class="fas fa-file"></i> View</button>
</div></div>
<?php endforeach; ?>
<div style="margin-top:10px"><a href="<?php echo APP_URL; ?>/views/processes/dispense_products.php" class="btn btn-secondary btn-sm"><i class="fas fa-list"></i> Go to Dispense</a></div>
<?php endif; ?>
</div>

<div class="sec">All Customer Prescriptions</div>
<div style="margin-bottom:20px;display:flex;gap:8px;flex-wrap:wrap">
  <button class="filter-btn active" onclick="filterPrescriptions('all')"><i class="fas fa-list"></i> All</button>
  <button class="filter-btn" onclick="filterPrescriptions('pending')"><i class="fas fa-clock"></i> Pending</button>
  <button class="filter-btn" onclick="filterPrescriptions('verified')"><i class="fas fa-check"></i> Verified</button>
  <button class="filter-btn" onclick="filterPrescriptions('approved')"><i class="fas fa-thumbs-up"></i> Approved</button>
  <button class="filter-btn" onclick="filterPrescriptions('dispensed')"><i class="fas fa-pills"></i> Dispensed</button>
</div>

<?php if(empty($all_prescriptions)): ?>
<div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:32px;text-align:center;color:var(--text3)">
<i class="fas fa-inbox" style="font-size:32px;margin-bottom:10px;display:block;color:var(--accent2)"></i>
<p>No prescriptions available.</p>
</div>
<?php else: ?>
<div style="display:grid;gap:10px">
<?php foreach($all_prescriptions as $rx): $slug=strtolower($rx['status']); ?>
<div class="rx-row prescription-item" data-status="<?php echo $slug; ?>">
<div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">
<div style="width:36px;height:36px;border-radius:8px;background:rgba(56,189,248,.1);display:flex;align-items:center;justify-content:center;color:var(--accent2);flex-shrink:0"><i class="fas fa-file-medical"></i></div>
<div style="min-width:0;flex:1">
<div style="font-size:13px;font-weight:600;color:var(--text)">Patient: <?php echo htmlspecialchars($rx['patient_name']); ?></div>
<div style="font-size:11px;color:var(--text3);margin-top:2px">Dr. <?php echo htmlspecialchars($rx['doctor_name']); ?> · <?php echo date('M d, Y H:i',strtotime($rx['upload_date'])); ?></div>
<div style="font-size:10px;color:var(--text3);margin-top:2px">Customer: <?php echo htmlspecialchars($rx['first_name'].' '.$rx['last_name']); ?> (<?php echo htmlspecialchars($rx['email']); ?>)</div>
</div></div>
<div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
<span class="status-badge status-<?php echo $slug; ?>"><?php echo ucfirst($rx['status']); ?></span>
<button type="button" class="btn btn-sm" style="background:var(--surface2);border:1px solid var(--border);color:var(--text)" onclick="viewPrescriptionFile('<?php echo htmlspecialchars($rx['prescription_image']); ?>', <?php echo $rx['id']; ?>)"><i class="fas fa-file"></i> View</button>
<?php if($rx['status'] === 'Verified' || $rx['status'] === 'Approved'): ?>
<a href="<?php echo APP_URL; ?>/views/processes/dispense_products.php?rx_id=<?php echo $rx['id']; ?>" class="btn btn-sm" style="background:rgba(79,255,176,.1);color:var(--accent);border:1px solid rgba(79,255,176,.3)"><i class="fas fa-pills"></i> Dispense</a>
<?php endif; ?>
</div></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>

<!-- File Viewer Modal -->
<div id="fileModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px);overflow-y:auto;padding:20px">
<div style="background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:28px;width:90%;max-width:800px;box-shadow:0 8px 40px rgba(0,0,0,.5)">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h2 style="font-size:18px;font-weight:700;color:var(--text);margin:0">Prescription File</h2>
    <button type="button" style="background:none;border:none;font-size:24px;color:var(--text3);cursor:pointer;padding:0" onclick="closeFileModal()"><i class="fas fa-times"></i></button>
  </div>
  <div id="fileContent" style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:20px;min-height:400px;display:flex;align-items:center;justify-content:center">
    <p style="color:var(--text3)">Loading file...</p>
  </div>
  <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
    <a id="checkAvailabilityBtn" href="#" class="btn" style="background:rgba(56,189,248,.1);color:var(--accent2);border:1px solid rgba(56,189,248,.3)"><i class="fas fa-boxes"></i> Check Availability</a>
    <button type="button" class="btn btn-secondary" onclick="closeFileModal()"><i class="fas fa-times"></i> Close</button>
  </div>
</div>
</div>

<script>
function viewPrescriptionFile(filename, prescriptionId) {
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
  
  // Set the Check Availability button link with prescription ID
  if (prescriptionId) {
    document.getElementById('checkAvailabilityBtn').href = '<?php echo APP_URL; ?>/views/processes/check_product_availability.php?rx_id=' + prescriptionId;
  }
  
  document.getElementById('fileModal').style.display = 'flex';
}

function closeFileModal() {
  document.getElementById('fileModal').style.display = 'none';
}

function filterPrescriptions(status) {
  // Update active button
  document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
  event.target.classList.add('active');
  
  // Filter prescription items
  const items = document.querySelectorAll('.prescription-item');
  items.forEach(item => {
    if (status === 'all') {
      item.style.display = '';
    } else {
      item.style.display = item.dataset.status === status ? '' : 'none';
    }
  });
}

window.addEventListener('click', e => {
  if (e.target === document.getElementById('fileModal')) {
    closeFileModal();
  }
});
</script>
  document.getElementById('fileModal').style.display = 'none';
}

window.addEventListener('click', e => {
  if (e.target === document.getElementById('fileModal')) {
    closeFileModal();
  }
});
</script>
</body></html>
