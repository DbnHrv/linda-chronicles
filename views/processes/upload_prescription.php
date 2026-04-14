<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(15);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$uploads_dir = realpath(__DIR__ . '/../../uploads') ?: (__DIR__ . '/../../uploads');
if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
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
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>
<div class="process-wrapper">
<h1><i class="fas fa-file-medical" style="color:var(--accent);margin-right:10px"></i>Upload Doctor's Prescription</h1>
<p class="subtitle">Submit your prescription for medication processing</p>
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
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
