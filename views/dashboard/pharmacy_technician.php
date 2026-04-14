<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');

$processModel = new ProcessModel($pdo);
$uid = $_SESSION['user_id'];

// All DB calls wrapped — safe even if tables don't exist yet
try { $my_reqs = $processModel->getRequisitionsByUser($uid); } catch(Exception $e){ $my_reqs = []; }
$pending_reqs  = 0;
$approved_reqs = 0;
foreach ($my_reqs as $r) {
    if ($r['status'] === 'Pending')  $pending_reqs++;
    if ($r['status'] === 'Approved') $approved_reqs++;
}
try { $stmt=$pdo->prepare("SELECT COUNT(*) FROM inventory_reports WHERE verification_status='Pending'"); $stmt->execute(); $pending_reports=$stmt->fetchColumn(); } catch(Exception $e){ $pending_reports=0; }
try { $stmt=$pdo->prepare("SELECT COUNT(*) FROM inventory_counts WHERE status='Completed'"); $stmt->execute(); $completed_counts=$stmt->fetchColumn(); } catch(Exception $e){ $completed_counts=0; }

// Recent requisitions for the list
$recent_reqs = array_slice($my_reqs, 0, 5);

$processes = array(
    10 => array('icon'=>'fa-chart-bar',    'name'=>'Create Inventory Report',  'desc'=>'Generate reports from completed inventory counts', 'url'=>'/views/processes/create_inventory_report.php', 'color'=>'#38bdf8'),
    11 => array('icon'=>'fa-check-double', 'name'=>'Check Inventory Report',   'desc'=>'Verify and validate inventory reports',            'url'=>'/views/processes/check_inventory_report.php',  'color'=>'#4fffb0'),
    12 => array('icon'=>'fa-shopping-cart','name'=>'Request Additional Stocks','desc'=>'Submit stock requisition requests to pharmacist',  'url'=>'/views/processes/request_stocks.php',           'color'=>'#f59e0b'),
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pharmacy Technician — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
.wrap{max-width:1100px;margin:0 auto;padding:28px 24px}
.stat-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:28px}
.stat{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px 20px;display:flex;align-items:center;gap:14px;transition:border-color .2s}
.stat:hover{border-color:var(--border2)}
.stat-ico{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.stat-val{font-size:24px;font-weight:700;color:var(--text);line-height:1}
.stat-lbl{font-size:11px;color:var(--text3);margin-top:3px}
.proc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px}
.proc-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px;text-decoration:none;display:block;transition:all .2s;position:relative;overflow:hidden}
.proc-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;opacity:0;transition:opacity .2s}
.proc-card:hover{border-color:var(--border2);transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.3)}
.proc-card:hover::before{opacity:1}
.sec{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin:24px 0 14px;display:flex;align-items:center;gap:8px}
.sec::after{content:'';flex:1;height:1px;background:var(--border)}
.req-row{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:12px;transition:border-color .2s}
.req-row:hover{border-color:var(--border2)}
</style>
</head>
<body>
<nav class="navbar">
  <div class="navbar-content">
    <h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
    <div class="navbar-right">
      <span class="user-role"><i class="fas fa-flask" style="margin-right:5px"></i><?php echo htmlspecialchars($_SESSION['user_first_name']??''); ?> · Pharmacy Technician</span>
      <a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </div>
</nav>

<div class="wrap">

  <?php if(isset($_SESSION['success'])): ?>
  <div class="alert alert-success" style="margin-bottom:20px"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
  <?php endif; ?>
  <?php if(isset($_SESSION['error'])): ?>
  <div class="alert alert-error" style="margin-bottom:20px"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
  <?php endif; ?>

  <!-- Welcome banner -->
  <div style="background:linear-gradient(135deg,rgba(56,189,248,.08),rgba(79,255,176,.08));border:1px solid rgba(56,189,248,.18);border-radius:14px;padding:22px 26px;margin-bottom:28px;display:flex;align-items:center;gap:18px">
    <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--accent2),var(--accent));display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#0a0c10;flex-shrink:0">
      <?php echo strtoupper(substr($_SESSION['user_first_name']??'T',0,1)); ?>
    </div>
    <div style="flex:1">
      <div style="font-size:18px;font-weight:700;color:var(--text)">Welcome, <?php echo htmlspecialchars($_SESSION['user_first_name']??'Technician'); ?></div>
      <div style="font-size:13px;color:var(--text2);margin-top:3px"><?php echo htmlspecialchars($_SESSION['user_email']??''); ?> · Pharmacy Technician</div>
    </div>
    <?php if($pending_reqs > 0): ?>
    <div style="background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);border-radius:10px;padding:10px 16px;text-align:center;flex-shrink:0">
      <div style="font-size:22px;font-weight:700;color:var(--warn)"><?php echo $pending_reqs; ?></div>
      <div style="font-size:11px;color:var(--warn)">Pending Requests</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Stats -->
  <div class="stat-row">
    <div class="stat">
      <div class="stat-ico" style="background:rgba(245,158,11,.12);color:var(--warn)"><i class="fas fa-shopping-cart"></i></div>
      <div><div class="stat-val"><?php echo count($my_reqs); ?></div><div class="stat-lbl">Total Requisitions</div></div>
    </div>
    <div class="stat">
      <div class="stat-ico" style="background:rgba(248,113,113,.12);color:var(--danger)"><i class="fas fa-clock"></i></div>
      <div><div class="stat-val"><?php echo $pending_reqs; ?></div><div class="stat-lbl">Pending Approval</div></div>
    </div>
    <div class="stat">
      <div class="stat-ico" style="background:rgba(79,255,176,.12);color:var(--accent)"><i class="fas fa-check-circle"></i></div>
      <div><div class="stat-val"><?php echo $approved_reqs; ?></div><div class="stat-lbl">Approved</div></div>
    </div>
    <div class="stat">
      <div class="stat-ico" style="background:rgba(56,189,248,.12);color:var(--accent2)"><i class="fas fa-chart-bar"></i></div>
      <div><div class="stat-val"><?php echo $pending_reports; ?></div><div class="stat-lbl">Reports to Verify</div></div>
    </div>
    <div class="stat">
      <div class="stat-ico" style="background:rgba(167,139,250,.12);color:var(--accent3)"><i class="fas fa-boxes"></i></div>
      <div><div class="stat-val"><?php echo $completed_counts; ?></div><div class="stat-lbl">Completed Counts</div></div>
    </div>
  </div>

  <!-- Processes -->
  <div class="sec">Inventory Processes</div>
  <div class="proc-grid">
    <?php foreach($processes as $pid => $p): ?>
    <a href="<?php echo APP_URL . $p['url']; ?>" class="proc-card" style="--c:<?php echo $p['color']; ?>">
      <style>.proc-card[href*="<?php echo basename($p['url'],'.php'); ?>"]::before{background:linear-gradient(90deg,<?php echo $p['color']; ?>,transparent)}</style>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        <div style="width:40px;height:40px;border-radius:9px;background:<?php echo $p['color']; ?>1a;display:flex;align-items:center;justify-content:center;color:<?php echo $p['color']; ?>;font-size:17px;flex-shrink:0">
          <i class="fas <?php echo $p['icon']; ?>"></i>
        </div>
        <div>
          <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.07em">Process <?php echo $pid; ?></div>
          <div style="font-size:14px;font-weight:700;color:var(--text)"><?php echo $p['name']; ?></div>
        </div>
      </div>
      <p style="font-size:12px;color:var(--text2);line-height:1.5;margin-bottom:14px"><?php echo $p['desc']; ?></p>
      <div style="font-size:12px;font-weight:600;color:<?php echo $p['color']; ?>">Open Process →</div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Recent Requisitions -->
  <?php if(!empty($recent_reqs)): ?>
  <div class="sec">Recent Requisitions</div>
  <?php foreach($recent_reqs as $r):
    $slug = strtolower($r['status']);
  ?>
  <div class="req-row">
    <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">
      <div style="width:36px;height:36px;border-radius:8px;background:rgba(245,158,11,.1);display:flex;align-items:center;justify-content:center;color:var(--warn);flex-shrink:0">
        <i class="fas fa-pills"></i>
      </div>
      <div style="min-width:0">
        <div style="font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo htmlspecialchars($r['product_name']); ?></div>
        <div style="font-size:11px;color:var(--text3);margin-top:2px">
          Qty: <strong style="color:var(--text)"><?php echo $r['quantity_needed']; ?></strong>
          &nbsp;·&nbsp; <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
        </div>
      </div>
    </div>
    <span class="status-badge status-<?php echo $slug; ?>"><?php echo $r['status']; ?></span>
  </div>
  <?php endforeach; ?>
  <div style="margin-top:10px">
    <a href="<?php echo APP_URL; ?>/views/processes/request_stocks.php" class="btn btn-secondary btn-sm"><i class="fas fa-list"></i> View All Requisitions</a>
  </div>
  <?php else: ?>
  <div class="sec">Recent Requisitions</div>
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:32px;text-align:center;color:var(--text3)">
    <i class="fas fa-shopping-cart" style="font-size:32px;margin-bottom:10px;display:block"></i>
    <p>No requisitions yet. <a href="<?php echo APP_URL; ?>/views/processes/request_stocks.php" style="color:var(--accent2)">Submit your first request →</a></p>
  </div>
  <?php endif; ?>

</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body>
</html>
