<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(2);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
try { $policies = $processModel->getHRPolicies(); } catch(Exception $e){ $policies=[]; }
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_policy') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
    else { try {
        $title=$_POST['title']??''; $desc=$_POST['description']??''; $cat=$_POST['category']??'';
        if (!trim($title)||!trim($desc)) throw new Exception('Title and description are required.');
        $processModel->addHRPolicy(sanitize($title),sanitize($desc),sanitize($cat));
        $message='Policy added successfully.'; $message_type='success'; $policies=$processModel->getHRPolicies();
    } catch(Exception $e){$message=$e->getMessage();$message_type='error';} }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Organize Policies — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>
<div class="process-wrapper">
<h1><i class="fas fa-book" style="color:var(--accent);margin-right:10px"></i>Organize Pharmacy Policies</h1>
<p class="subtitle">Manage and organize company policies and guidelines</p>
<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<div class="content-section"><h2>Add New Policy</h2>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>"><input type="hidden" name="action" value="add_policy">
<div class="form-group"><label>Policy Title <span class="required">*</span></label><input type="text" name="title" required placeholder="e.g. Code of Conduct"></div>
<div class="form-group"><label>Category</label>
<select name="category"><option value="">Select Category</option>
<?php foreach(['Work Hours','Dress Code','Safety','Conduct','Benefits','Other'] as $c): ?><option value="<?php echo $c; ?>"><?php echo $c; ?></option><?php endforeach; ?>
</select></div>
<div class="form-group"><label>Description <span class="required">*</span></label><textarea name="description" rows="4" required placeholder="Describe the policy in detail..."></textarea></div>
<button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Policy</button>
</form></div>
<div class="content-section"><h2>Existing Policies</h2>
<?php if(empty($policies)): ?><div class="empty-state"><p>No policies yet. Add one above.</p></div>
<?php else: foreach($policies as $p): ?>
<div class="info-card">
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
<div><div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:4px"><?php echo htmlspecialchars($p['title']); ?></div>
<?php if($p['category']): ?><span class="policy-category"><?php echo htmlspecialchars($p['category']); ?></span><?php endif; ?>
<p style="margin-top:8px;font-size:13px;color:var(--text2)"><?php echo htmlspecialchars(substr($p['description'],0,200)).(strlen($p['description'])>200?'…':''); ?></p>
<p style="font-size:11px;color:var(--text3);margin-top:6px">Added <?php echo date('M d, Y',strtotime($p['created_at'])); ?></p>
</div></div></div>
<?php endforeach; endif; ?>
</div>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
