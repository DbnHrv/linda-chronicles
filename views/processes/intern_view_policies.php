<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(22);

$processModel = new ProcessModel($pdo);
$message = $message_type = '';

try {
    $policies = $processModel->getHRPolicies();
} catch(Exception $e) {
    $policies = [];
    $message = 'Unable to load policies.';
    $message_type = 'error';
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Policies & Guidelines — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
.policy-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:16px;transition:all .2s}
.policy-card:hover{border-color:var(--border2);box-shadow:0 4px 12px rgba(0,0,0,.2)}
.policy-header{display:flex;align-items:flex-start;gap:16px;margin-bottom:16px}
.policy-icon{width:48px;height:48px;border-radius:10px;background:rgba(167,139,250,.12);display:flex;align-items:center;justify-content:center;color:var(--accent3);font-size:20px;flex-shrink:0}
.policy-title{font-size:18px;font-weight:700;color:var(--text);margin-bottom:4px}
.policy-category{display:inline-block;background:rgba(167,139,250,.1);border:1px solid rgba(167,139,250,.2);color:var(--accent3);padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-top:8px}
.policy-meta{font-size:12px;color:var(--text3);margin-top:8px}
.policy-description{font-size:14px;color:var(--text2);line-height:1.6;margin-top:12px;padding-top:12px;border-top:1px solid var(--border)}
.empty-state{text-align:center;padding:60px 20px;background:var(--surface);border:1px dashed var(--border);border-radius:12px}
.empty-icon{font-size:48px;color:var(--accent3);margin-bottom:16px;display:block}
</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>

<div class="process-wrapper">
<h1><i class="fas fa-book" style="color:var(--accent);margin-right:10px"></i>Policies & Guidelines</h1>
<p class="subtitle">Review company policies and guidelines organized by HR Personnel</p>

<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<?php if(empty($policies)): ?>
<div class="empty-state">
  <i class="fas fa-file-alt empty-icon"></i>
  <p style="font-size:16px;font-weight:600;color:var(--text);margin-bottom:8px">No Policies Available</p>
  <p style="font-size:14px;color:var(--text2)">HR Personnel will organize and publish policies here. Check back soon.</p>
</div>
<?php else: ?>

<div style="margin-bottom:28px">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
    <i class="fas fa-info-circle" style="color:var(--accent2)"></i>
    <p style="font-size:13px;color:var(--text2)">Total Policies: <strong><?php echo count($policies); ?></strong></p>
  </div>
</div>

<?php foreach($policies as $policy): ?>
<div class="policy-card">
  <div class="policy-header">
    <div class="policy-icon">
      <i class="fas fa-file-contract"></i>
    </div>
    <div style="flex:1">
      <div class="policy-title"><?php echo htmlspecialchars($policy['title']); ?></div>
      <?php if($policy['category']): ?>
      <span class="policy-category"><?php echo htmlspecialchars($policy['category']); ?></span>
      <?php endif; ?>
      <div class="policy-meta">
        <i class="fas fa-calendar-alt" style="margin-right:4px"></i>
        Published <?php echo date('M d, Y', strtotime($policy['created_at'])); ?>
      </div>
    </div>
  </div>
  
  <div class="policy-description">
    <?php echo nl2br(htmlspecialchars($policy['description'])); ?>
  </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<div style="margin-top:28px">
  <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>
</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
