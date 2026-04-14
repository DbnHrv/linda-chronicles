<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(1);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$uploads_dir = realpath(__DIR__ . '/../../uploads') ?: (__DIR__ . '/../../uploads');
if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.'; $message_type = 'error';
    } else {
        try {
            $user_id     = $_SESSION['user_id'];
            $school_name = trim($_POST['school_name'] ?? '');
            $course      = trim($_POST['course'] ?? '');
            $year_level  = trim($_POST['year_level'] ?? '');
            if (!$school_name) throw new Exception('School name is required.');
            if (!$course)      throw new Exception('Course is required.');
            if (!$year_level)  throw new Exception('Year level is required.');
            $student_id    = trim($_POST['student_id'] ?? '') ?: null;
            $phone_number  = trim($_POST['phone_number'] ?? '') ?: null;
            $contact_email = trim($_POST['contact_email'] ?? '') ?: null;
            $allowed_ext = ['pdf','doc','docx'];
            $file_fields = ['coe_file','resume_file','cover_letter_file','parent_consent_file','medical_certificate_file','school_endorsement_file','tor_file','moa_file'];
            $uploaded = [];
            foreach ($file_fields as $field) {
                $uploaded[$field] = null;
                if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) continue;
                $file = $_FILES[$field];
                if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception(ucwords(str_replace('_',' ',$field)) . ': upload error.');
                if ($file['size'] > 5*1024*1024) throw new Exception(ucwords(str_replace('_',' ',$field)) . ' exceeds 5MB.');
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext)) throw new Exception(ucwords(str_replace('_',' ',$field)) . ' must be PDF/DOC/DOCX.');
                $filename = 'p1_'.$user_id.'_'.$field.'_'.time().'.'.$ext;
                if (!move_uploaded_file($file['tmp_name'], $uploads_dir.DIRECTORY_SEPARATOR.$filename))
                    throw new Exception('Could not save '.ucwords(str_replace('_',' ',$field)).'.');
                $uploaded[$field] = $filename;
            }
            $existing = $processModel->getInternshipSubmissionByUserId($user_id);
            if ($existing) {
                $stmt = $pdo->prepare("UPDATE internship_submissions SET school_name=?,course=?,year_level=?,student_id_number=?,phone_number=?,contact_email=?,coe_file=COALESCE(?,coe_file),resume_file=COALESCE(?,resume_file),cover_letter_file=COALESCE(?,cover_letter_file),parent_consent_file=COALESCE(?,parent_consent_file),medical_certificate_file=COALESCE(?,medical_certificate_file),school_endorsement_file=COALESCE(?,school_endorsement_file),tor_file=COALESCE(?,tor_file),moa_file=COALESCE(?,moa_file),status='Pending',submission_date=NOW() WHERE user_id=?");
                $stmt->execute([$school_name,$course,$year_level,$student_id,$phone_number,$contact_email,$uploaded['coe_file'],$uploaded['resume_file'],$uploaded['cover_letter_file'],$uploaded['parent_consent_file'],$uploaded['medical_certificate_file'],$uploaded['school_endorsement_file'],$uploaded['tor_file'],$uploaded['moa_file'],$user_id]);
                $message = 'Requirements updated successfully.'; $message_type = 'success';
            } else {
                $stmt = $pdo->prepare("INSERT INTO internship_submissions (user_id,school_name,course,year_level,student_id_number,phone_number,contact_email,coe_file,resume_file,cover_letter_file,parent_consent_file,medical_certificate_file,school_endorsement_file,tor_file,moa_file,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Pending')");
                $stmt->execute([$user_id,$school_name,$course,$year_level,$student_id,$phone_number,$contact_email,$uploaded['coe_file'],$uploaded['resume_file'],$uploaded['cover_letter_file'],$uploaded['parent_consent_file'],$uploaded['medical_certificate_file'],$uploaded['school_endorsement_file'],$uploaded['tor_file'],$uploaded['moa_file']]);
                $message = 'Requirements submitted successfully.'; $message_type = 'success';
            }
        } catch (Exception $e) { $message = $e->getMessage(); $message_type = 'error'; }
    }
}
$existing_submission = $processModel->getInternshipSubmissionByUserId($_SESSION['user_id']);
$doc_fields = ['coe_file'=>'Certificate of Enrollment','resume_file'=>'Resume / CV','cover_letter_file'=>'Cover Letter','parent_consent_file'=>'Parental Consent','medical_certificate_file'=>'Medical Certificate','school_endorsement_file'=>'School Endorsement','tor_file'=>'Transcript of Records','moa_file'=>'Memorandum of Agreement'];
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Submit Requirements — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right">
<span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>
<div class="process-wrapper">
<h1><i class="fas fa-file-alt" style="color:var(--accent);margin-right:10px"></i>Submit Internship Requirements</h1>
<p class="subtitle">Upload your required documents for the internship program</p>
<?php if ($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($existing_submission): ?>
<div class="submission-status">
<h3><i class="fas fa-info-circle" style="color:var(--accent2);margin-right:6px"></i>Current Submission</h3>
<p>Status: <span class="status-badge status-<?php echo strtolower($existing_submission['status']); ?>"><?php echo $existing_submission['status']; ?></span></p>
<p>Submitted: <?php echo date('F d, Y', strtotime($existing_submission['submission_date'])); ?></p>
<p><?php echo htmlspecialchars($existing_submission['school_name']); ?> — <?php echo htmlspecialchars($existing_submission['course']); ?></p>
<?php if ($existing_submission['remarks']): ?><p style="margin-top:8px;padding-top:8px;border-top:1px solid var(--border)"><strong>Remarks:</strong> <?php echo htmlspecialchars($existing_submission['remarks']); ?></p><?php endif; ?>
</div><?php endif; ?>
<form method="POST" enctype="multipart/form-data" id="reqForm">
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
<div class="content-section">
<h2><i class="fas fa-user" style="color:var(--accent);margin-right:8px"></i>Personal Information</h2>
<div class="grid-2">
<div class="form-group"><label>School / University <span class="required">*</span></label><input type="text" name="school_name" required placeholder="e.g. University of the Philippines" value="<?php echo htmlspecialchars($existing_submission['school_name']??''); ?>"></div>
<div class="form-group"><label>Course / Program <span class="required">*</span></label><input type="text" name="course" required placeholder="e.g. BS Pharmacy" value="<?php echo htmlspecialchars($existing_submission['course']??''); ?>"></div>
</div>
<div class="grid-2">
<div class="form-group"><label>Year Level <span class="required">*</span></label>
<select name="year_level" required><?php foreach(['1st Year','2nd Year','3rd Year','4th Year','5th Year'] as $y): ?><option value="<?php echo $y; ?>" <?php echo ($existing_submission['year_level']??'')===$y?'selected':''; ?>><?php echo $y; ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>Student ID</label><input type="text" name="student_id" placeholder="e.g. 2021-12345" value="<?php echo htmlspecialchars($existing_submission['student_id_number']??''); ?>"></div>
</div>
<div class="grid-2">
<div class="form-group"><label>Phone Number</label><input type="tel" name="phone_number" placeholder="09171234567" value="<?php echo htmlspecialchars($existing_submission['phone_number']??''); ?>"></div>
<div class="form-group"><label>Contact Email</label><input type="email" name="contact_email" placeholder="your@email.com" value="<?php echo htmlspecialchars($existing_submission['contact_email']??''); ?>"></div>
</div>
</div>
<div class="content-section">
<h2><i class="fas fa-paperclip" style="color:var(--accent);margin-right:8px"></i>Required Documents</h2>
<p style="color:var(--text2);font-size:13px;margin-bottom:20px">PDF, DOC, DOCX — max 5MB each. <?php if($existing_submission): ?><span style="color:var(--accent2)">Previously uploaded files are kept unless replaced.</span><?php endif; ?></p>
<div class="file-input-group">
<?php foreach($doc_fields as $field=>$label): $has=!empty($existing_submission[$field]); ?>
<div class="file-input-item" id="wrap-<?php echo $field; ?>">
<i class="fas fa-file-alt" style="font-size:20px;color:<?php echo $has?'var(--accent)':'var(--text3)'; ?>;margin-bottom:6px;display:block" id="icon-<?php echo $field; ?>"></i>
<div style="font-size:12px;font-weight:600;color:var(--text);margin-bottom:4px"><?php echo $label; ?></div>
<div id="status-<?php echo $field; ?>" style="font-size:11px;margin-bottom:6px;color:<?php echo $has?'var(--accent)':'var(--text3)'; ?>"><?php echo $has?'<i class="fas fa-check-circle"></i> Uploaded':'No file chosen'; ?></div>
<label for="<?php echo $field; ?>" style="display:inline-block;padding:4px 12px;background:var(--surface3);border:1px solid var(--border2);border-radius:4px;font-size:11px;font-weight:600;color:var(--text2);cursor:pointer">
<i class="fas fa-upload" style="margin-right:4px"></i><?php echo $has?'Replace':'Choose'; ?></label>
<input type="file" id="<?php echo $field; ?>" name="<?php echo $field; ?>" accept=".pdf,.doc,.docx" onchange="updateFile('<?php echo $field; ?>',this)">
</div><?php endforeach; ?>
</div></div>
<div style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px">
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
<button type="submit" class="btn btn-primary" id="submitBtn"><i class="fas fa-paper-plane"></i> <?php echo $existing_submission?'Update Submission':'Submit Requirements'; ?></button>
</div></form></div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
<script>
function updateFile(f,i){if(i.files&&i.files[0]){var n=i.files[0].name,s=(i.files[0].size/1024).toFixed(0);document.getElementById('status-'+f).innerHTML='<i class="fas fa-check-circle" style="color:var(--accent)"></i> <span style="color:var(--accent)">'+n+' ('+s+' KB)</span>';document.getElementById('icon-'+f).style.color='var(--accent)';}}
document.getElementById('reqForm').addEventListener('submit',function(){var b=document.getElementById('submitBtn');b.disabled=true;b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Submitting...';});
</script></body></html>
