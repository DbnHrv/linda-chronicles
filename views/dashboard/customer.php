<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
$processModel = new ProcessModel($pdo);
$uid = $_SESSION['user_id'];
try { $prescriptions = $processModel->getPrescriptionsByCustomer($uid); } catch(Exception $e){ $prescriptions=[]; }
try { $payments = $processModel->getPaymentsByCustomer($uid); } catch(Exception $e){ $payments=[]; }
try { $dispensed = $processModel->getDispensedPrescriptions($uid); } catch(Exception $e){ $dispensed=[]; }
$pending_rx=0; $verified_rx=0; $dispensed_rx=0;
foreach($prescriptions as $rx){
    if($rx['status']==='Pending')   $pending_rx++;
    if(in_array($rx['status'],['Verified','Approved'])) $verified_rx++;
    if($rx['status']==='Dispensed') $dispensed_rx++;
}
$pending_payments=0;
foreach($payments as $p){ if($p['status']==='Pending') $pending_payments++; }
$processes = array(
    15=>array('icon'=>'fa-file-medical','name'=>'Upload Prescription','desc'=>"Submit your doctor's prescription for medication processing",'url'=>'/views/processes/upload_prescription.php','color'=>'#38bdf8'),
    17=>array('icon'=>'fa-pills','name'=>'View Dispensed Medicines','desc'=>'View your dispensed medicines and check product availability','url'=>'/views/processes/view_dispensed_medicines.php','color'=>'#4fffb0'),
    21=>array('icon'=>'fa-shopping-cart','name'=>'Checkout','desc'=>'Review and checkout your pending medicines','url'=>'/views/processes/checkout.php','color'=>'#f59e0b'),
);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Customer Dashboard — <?php echo APP_NAME; ?></title>
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
<span class="user-role"><i class="fas fa-user" style="margin-right:5px"></i><?php echo htmlspecialchars($_SESSION['user_first_name']??''); ?> · Customer</span>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>
<div class="wrap">
<?php if(isset($_SESSION['success'])): ?><div class="alert alert-success" style="margin-bottom:20px"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<?php if(isset($_SESSION['error'])): ?><div class="alert alert-error" style="margin-bottom:20px"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>
<div style="background:linear-gradient(135deg,rgba(56,189,248,.08),rgba(167,139,250,.08));border:1px solid rgba(56,189,248,.18);border-radius:14px;padding:22px 26px;margin-bottom:28px;display:flex;align-items:center;gap:18px">
<div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--accent2),var(--accent3));display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff;flex-shrink:0"><?php echo strtoupper(substr($_SESSION['user_first_name']??'C',0,1)); ?></div>
<div style="flex:1">
<div style="font-size:18px;font-weight:700;color:var(--text)">Welcome, <?php echo htmlspecialchars($_SESSION['user_first_name']??'Customer'); ?></div>
<div style="font-size:13px;color:var(--text2);margin-top:3px"><?php echo htmlspecialchars($_SESSION['user_email']??''); ?> · Customer</div>
</div>
</div>
<div class="stat-row">
<div class="stat"><div class="stat-ico" style="background:rgba(56,189,248,.12);color:var(--accent2)"><i class="fas fa-file-medical"></i></div><div><div class="stat-val"><?php echo count($prescriptions); ?></div><div class="stat-lbl">Total Prescriptions</div></div></div>
<div class="stat"><div class="stat-ico" style="background:rgba(245,158,11,.12);color:var(--warn)"><i class="fas fa-clock"></i></div><div><div class="stat-val"><?php echo $pending_rx; ?></div><div class="stat-lbl">Pending Review</div></div></div>
<div class="stat"><div class="stat-ico" style="background:rgba(167,139,250,.12);color:var(--accent3)"><i class="fas fa-check-circle"></i></div><div><div class="stat-val"><?php echo $verified_rx; ?></div><div class="stat-lbl">Verified / Approved</div></div></div>
<div class="stat"><div class="stat-ico" style="background:rgba(79,255,176,.12);color:var(--accent)"><i class="fas fa-pills"></i></div><div><div class="stat-val"><?php echo $dispensed_rx; ?></div><div class="stat-lbl">Dispensed</div></div></div>
<div class="stat"><div class="stat-ico" style="background:rgba(56,189,248,.12);color:var(--accent2)"><i class="fas fa-receipt"></i></div><div><div class="stat-val"><?php echo count($payments); ?></div><div class="stat-lbl">Payments</div></div></div>
</div>
<div class="sec">My Services</div>
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
<?php if(!empty($prescriptions)): ?>
<div class="sec">My Prescriptions</div>
<?php foreach(array_slice($prescriptions,0,5) as $rx): $slug=strtolower($rx['status']); ?>
<div class="rx-row">
<div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">
<div style="width:36px;height:36px;border-radius:8px;background:rgba(56,189,248,.1);display:flex;align-items:center;justify-content:center;color:var(--accent2);flex-shrink:0"><i class="fas fa-file-medical"></i></div>
<div style="min-width:0">
<div style="font-size:13px;font-weight:600;color:var(--text)">Patient: <?php echo htmlspecialchars($rx['patient_name']); ?></div>
<div style="font-size:11px;color:var(--text3);margin-top:2px">Dr. <?php echo htmlspecialchars($rx['doctor_name']); ?> · <?php echo date('M d, Y',strtotime($rx['upload_date'])); ?></div>
</div></div>
<span class="status-badge status-<?php echo $slug; ?>"><?php echo $rx['status']; ?></span>
</div>
<?php endforeach; ?>
<div style="margin-top:10px">
<a href="<?php echo APP_URL; ?>/views/processes/upload_prescription.php" class="btn btn-secondary btn-sm"><i class="fas fa-plus"></i> Upload New</a>
</div>
<?php else: ?>
<div class="sec">My Prescriptions</div>
<div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:32px;text-align:center;color:var(--text3)">
<i class="fas fa-file-medical" style="font-size:32px;margin-bottom:10px;display:block;color:var(--accent2)"></i>
<p>No prescriptions yet. <a href="<?php echo APP_URL; ?>/views/processes/upload_prescription.php" style="color:var(--accent2)">Upload your first prescription →</a></p>
</div>
<?php endif; ?>
<?php if(!empty($payments)): ?>
<div class="sec">Payment History</div>
<?php foreach(array_slice($payments,0,3) as $pay): $slug=strtolower($pay['status']); ?>
<div class="rx-row">
<div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">
<div style="width:36px;height:36px;border-radius:8px;background:rgba(79,255,176,.1);display:flex;align-items:center;justify-content:center;color:var(--accent);flex-shrink:0"><i class="fas fa-receipt"></i></div>
<div style="min-width:0">
<div style="font-size:13px;font-weight:600;color:var(--text)">Rx #<?php echo $pay['prescription_id']; ?> — <?php echo htmlspecialchars($pay['patient_name']); ?></div>
<div style="font-size:11px;color:var(--text3);margin-top:2px"><?php echo htmlspecialchars($pay['payment_method']); ?> · <?php echo date('M d, Y',strtotime($pay['created_at'])); ?></div>
</div></div>
<div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
<div style="font-size:16px;font-weight:700;color:var(--accent)">₱<?php echo number_format($pay['amount'],2); ?></div>
<span class="status-badge status-<?php echo $slug; ?>"><?php echo $pay['status']; ?></span>
</div></div>
<?php endforeach; ?>
<?php endif; ?>
</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
