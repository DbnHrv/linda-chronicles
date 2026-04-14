<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
$processModel = new ProcessModel($pdo);
$pending_reqs = count($processModel->getPendingRequisitions());
$approved_reqs = count($processModel->getApprovedRequisitions());
$purchase_orders = count($processModel->getPurchaseOrders());
$processes = array(
    13 => array('icon'=>'fa-clipboard-list','name'=>'Check Stock Requisition','desc'=>'Review & approve stock requests',          'url'=>'/views/processes/check_stock_requisition.php', 'color'=>'#f59e0b'),
    14 => array('icon'=>'fa-file-invoice',  'name'=>'Generate Purchase Order', 'desc'=>'Create POs from approved requisitions',   'url'=>'/views/processes/generate_purchase_order.php', 'color'=>'#4fffb0'),
);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pharmacist Dashboard — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
.stat-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:28px}
.stat{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px 18px;display:flex;align-items:center;gap:12px}
.stat-ico{width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.stat-val{font-size:20px;font-weight:700;color:var(--text);line-height:1}.stat-lbl{font-size:11px;color:var(--text3);margin-top:2px}
.proc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px}
.proc-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px;text-decoration:none;display:block;transition:all .2s}
.proc-card:hover{border-color:var(--border2);transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.3)}
.section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin-bottom:14px;display:flex;align-items:center;gap:8px}
.section-title::after{content:'';flex:1;height:1px;background:var(--border)}</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><i class="fas fa-user-md" style="margin-right:4px"></i><?php echo htmlspecialchars($_SESSION['user_first_name']??''); ?> · Pharmacist</span>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>
<div style="max-width:1100px;margin:0 auto;padding:28px 24px">
<?php if(isset($_SESSION['success'])): ?><div class="alert alert-success" style="margin-bottom:20px"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<div style="background:linear-gradient(135deg,rgba(245,158,11,.08),rgba(248,113,113,.08));border:1px solid rgba(245,158,11,.15);border-radius:12px;padding:20px 24px;margin-bottom:28px;display:flex;align-items:center;gap:16px">
<div style="width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#ef4444);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#fff;flex-shrink:0"><?php echo strtoupper(substr($_SESSION['user_first_name']??'P',0,1)); ?></div>
<div><div style="font-size:17px;font-weight:700;color:var(--text)">Welcome, <?php echo htmlspecialchars($_SESSION['user_first_name']??'Pharmacist'); ?></div>
<div style="font-size:13px;color:var(--text2);margin-top:2px"><?php echo htmlspecialchars($_SESSION['user_email']??''); ?> · Licensed Pharmacist</div></div>
<?php if($pending_reqs > 0): ?>
<div style="margin-left:auto;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);border-radius:8px;padding:8px 14px;text-align:center">
<div style="font-size:20px;font-weight:700;color:var(--warn)"><?php echo $pending_reqs; ?></div>
<div style="font-size:11px;color:var(--warn)">Pending Requests</div>
</div>
<?php endif; ?>
</div>
<div class="stat-row">
<div class="stat"><div class="stat-ico" style="background:rgba(245,158,11,.1);color:var(--warn)"><i class="fas fa-clock"></i></div><div><div class="stat-val"><?php echo $pending_reqs; ?></div><div class="stat-lbl">Pending Requisitions</div></div></div>
<div class="stat"><div class="stat-ico" style="background:rgba(79,255,176,.1);color:var(--accent)"><i class="fas fa-check-circle"></i></div><div><div class="stat-val"><?php echo $approved_reqs; ?></div><div class="stat-lbl">Approved (Awaiting PO)</div></div></div>
<div class="stat"><div class="stat-ico" style="background:rgba(56,189,248,.1);color:var(--accent2)"><i class="fas fa-file-invoice"></i></div><div><div class="stat-val"><?php echo $purchase_orders; ?></div><div class="stat-lbl">Purchase Orders</div></div></div>
</div>
<div class="section-title">Pharmacy Processes</div>
<div class="proc-grid">
<?php foreach($processes as $pid => $p): ?>
<a href="<?php echo APP_URL . $p['url']; ?>" class="proc-card">
<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
<div style="width:36px;height:36px;border-radius:8px;background:<?php echo $p['color']; ?>1a;display:flex;align-items:center;justify-content:center;color:<?php echo $p['color']; ?>;font-size:15px;flex-shrink:0"><i class="fas <?php echo $p['icon']; ?>"></i></div>
<div><div style="font-size:10px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.06em">Process <?php echo $pid; ?></div>
<div style="font-size:13px;font-weight:700;color:var(--text)"><?php echo $p['name']; ?></div></div></div>
<p style="font-size:12px;color:var(--text2);margin-bottom:10px"><?php echo $p['desc']; ?></p>
<div style="font-size:12px;font-weight:600;color:<?php echo $p['color']; ?>">Open →</div>
</a>
<?php endforeach; ?>
</div>
</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
