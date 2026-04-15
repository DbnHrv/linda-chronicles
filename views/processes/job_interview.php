<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(4);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$is_hr     = ((int)$_SESSION['role_id'] === ROLE_HR_PERSONNEL);
$is_intern = ((int)$_SESSION['role_id'] === ROLE_INTERN);

/* ── HR: schedule only ─────────────────────────────────────────── */
if ($is_hr) {
    try {
        $stmt = $pdo->prepare("SELECT u.id,u.first_name,u.last_name,u.email,s.school_name,s.course
            FROM users u JOIN internship_submissions s ON s.user_id=u.id
            WHERE u.role_id=? AND s.status='Approved' ORDER BY u.first_name");
        $stmt->execute([ROLE_INTERN]);
        $approved_interns = $stmt->fetchAll();
    } catch(Exception $e){ $approved_interns = []; }

    try { $interviews = $processModel->getInterviewsByHR(); } catch(Exception $e){ $interviews = []; }

    if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='schedule') {
        if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
        else { try {
            $intern_id = intval($_POST['intern_id']??0);
            $date      = sanitize($_POST['interview_date']??'');
            $time      = sanitize($_POST['interview_time']??'');
            $loc       = sanitize($_POST['location']??'');
            $notes     = sanitize($_POST['notes']??'');
            if (!$intern_id||!$date||!$time) throw new Exception('Intern, date and time are required.');
            $stmt = $pdo->prepare("INSERT INTO interview_invitations
                (intern_id,hr_personnel_id,interview_date,interview_location,interview_notes,status)
                VALUES (?,?,?,?,?,'Pending')");
            $stmt->execute([$intern_id,$_SESSION['user_id'],$date.' '.$time.':00',$loc,$notes]);
            $message='Interview scheduled successfully.'; $message_type='success';
            try { $interviews = $processModel->getInterviewsByHR(); } catch(Exception $e){}
        } catch(Exception $e){ $message=$e->getMessage(); $message_type='error'; } }
    }
}

/* ── Intern: accept / decline ──────────────────────────────────── */
if ($is_intern) {
    $my_submission = $processModel->getInternshipSubmissionByUserId($_SESSION['user_id']);
    try { $interviews = $processModel->getInterviewsByIntern($_SESSION['user_id']); } catch(Exception $e){ $interviews=[]; }

    if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='respond') {
        if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
        else { try {
            $iid      = intval($_POST['interview_id']??0);
            $response = sanitize($_POST['response']??'');
            $reason   = sanitize($_POST['decline_reason']??'');
            if (!$iid || !in_array($response,['Accepted','Declined'])) throw new Exception('Invalid response.');
            $stmt = $pdo->prepare("UPDATE interview_invitations
                SET status=?, decline_reason=?, acceptance_date=NOW()
                WHERE id=? AND intern_id=?");
            $stmt->execute([$response, $response==='Declined'?$reason:null, $iid, $_SESSION['user_id']]);
            $message = $response==='Accepted'
                ? 'Interview accepted! See you there.'
                : 'Interview declined. HR has been notified to reschedule.';
            $message_type = 'success';
            try { $interviews = $processModel->getInterviewsByIntern($_SESSION['user_id']); } catch(Exception $e){}
        } catch(Exception $e){ $message=$e->getMessage(); $message_type='error'; } }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Job Interview — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
.iv-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:14px;position:relative;overflow:hidden;transition:all .2s}
.iv-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;opacity:0;transition:opacity .2s}
.iv-card:hover{border-color:var(--border2)}.iv-card:hover::before{opacity:1}
.iv-card.status-pending::before   {background:linear-gradient(90deg,var(--warn),transparent)}
.iv-card.status-accepted::before  {background:linear-gradient(90deg,var(--accent),transparent)}
.iv-card.status-declined::before  {background:linear-gradient(90deg,var(--danger),transparent)}
.iv-card.status-completed::before {background:linear-gradient(90deg,var(--accent2),transparent)}
.iv-meta{display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;margin:12px 0}
.iv-meta .mk{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--text3)}
.iv-meta .mv{font-size:13px;font-weight:600;color:var(--text);margin-top:2px}
/* respond buttons */
.respond-btns{display:flex;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--border)}
.btn-accept{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:rgba(79,255,176,.12);color:var(--accent);border:1px solid rgba(79,255,176,.3);transition:all .2s}
.btn-accept:hover{background:rgba(79,255,176,.22)}
.btn-decline{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:rgba(248,113,113,.1);color:var(--danger);border:1px solid rgba(248,113,113,.3);transition:all .2s}
.btn-decline:hover{background:rgba(248,113,113,.2)}
/* decline reason box */
.decline-box{display:none;margin-top:12px;padding:14px;background:var(--surface2);border:1px solid rgba(248,113,113,.25);border-radius:8px}
.decline-box textarea{width:100%;background:var(--surface3);border:1px solid var(--border2);border-radius:6px;color:var(--text);font-size:13px;font-family:inherit;padding:8px 12px;resize:vertical;outline:none}
.decline-box textarea:focus{border-color:var(--danger)}
/* status note for declined */
.reschedule-note{background:rgba(248,113,113,.07);border:1px solid rgba(248,113,113,.2);border-radius:8px;padding:12px 14px;font-size:12px;color:var(--danger);margin-top:10px;display:flex;align-items:flex-start;gap:8px}
/* banner */
.banner{border-radius:12px;padding:18px 22px;margin-bottom:22px;display:flex;align-items:center;gap:14px;border:1px solid transparent}
.banner.approved{background:rgba(79,255,176,.07);border-color:rgba(79,255,176,.2)}
.banner.pending{background:rgba(245,158,11,.07);border-color:rgba(245,158,11,.2)}
.banner.rejected{background:rgba(248,113,113,.07);border-color:rgba(248,113,113,.2)}
.banner.under-review{background:rgba(56,189,248,.07);border-color:rgba(56,189,248,.2)}
/* drawer */
.drawer-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:900;opacity:0;pointer-events:none;transition:opacity .3s;backdrop-filter:blur(4px)}
.drawer-overlay.open{opacity:1;pointer-events:all}
.drawer{position:fixed;top:0;right:0;bottom:0;width:500px;max-width:100vw;background:var(--surface);border-left:1px solid var(--border2);z-index:901;display:flex;flex-direction:column;transform:translateX(100%);transition:transform .35s cubic-bezier(.4,0,.2,1);box-shadow:-8px 0 40px rgba(0,0,0,.5)}
.drawer.open{transform:translateX(0)}
.drawer-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;flex-shrink:0}
.drawer-close{margin-left:auto;width:32px;height:32px;border-radius:8px;background:var(--surface2);border:1px solid var(--border);color:var(--text2);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:16px}
.drawer-body{flex:1;overflow-y:auto;padding:24px}
.drawer-footer{padding:16px 24px;border-top:1px solid var(--border);flex-shrink:0;display:flex;gap:10px;justify-content:flex-end}
</style>
</head>
<body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right">
<span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>

<div class="process-wrapper" style="max-width:1000px">
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px">
<div>
<h1><i class="fas fa-comments" style="color:var(--accent);margin-right:10px"></i>Job Interview</h1>
<p class="subtitle"><?php echo $is_hr?'Schedule interviews for approved interns':'Your interview schedule — accept or decline below'; ?></p>
</div>
<?php if($is_hr): ?>
<button onclick="openDrawer()" class="btn btn-primary"><i class="fas fa-plus"></i> Schedule Interview</button>
<?php endif; ?>
</div>

<?php if($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
<i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i>
<?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php /* ══════════ HR VIEW ══════════ */ if($is_hr): ?>

<?php if(empty($approved_interns)): ?>
<div class="alert alert-warning">
<i class="fas fa-info-circle"></i> No approved interns yet.
<a href="<?php echo APP_URL; ?>/views/processes/hr_check_requirements.php" style="color:var(--accent2);font-weight:600;margin-left:4px">Approve requirements first →</a>
</div>
<?php endif; ?>

<?php if(empty($interviews)): ?>
<div class="content-section" style="text-align:center;padding:48px">
<i class="fas fa-calendar-times" style="font-size:40px;color:var(--text3);display:block;margin-bottom:12px"></i>
<p style="color:var(--text2)">No interviews scheduled yet. Click "Schedule Interview" to get started.</p>
</div>
<?php else: foreach($interviews as $iv):
    $slug = strtolower(str_replace(' ','-',$iv['status']));
    $ini  = strtoupper(substr($iv['first_name'],0,1).substr($iv['last_name'],0,1));
?>
<div class="iv-card status-<?php echo $slug; ?>">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#0a0c10;flex-shrink:0"><?php echo $ini; ?></div>
        <div style="flex:1">
            <div style="font-size:15px;font-weight:700;color:var(--text)"><?php echo htmlspecialchars($iv['first_name'].' '.$iv['last_name']); ?></div>
            <div style="font-size:11px;color:var(--text3)"><?php echo htmlspecialchars($iv['email']); ?></div>
        </div>
        <span class="status-badge status-<?php echo $slug; ?>"><?php echo $iv['status']; ?></span>
    </div>
    <div class="iv-meta">
        <div><div class="mk"><i class="fas fa-calendar" style="margin-right:4px"></i>Date</div><div class="mv"><?php echo date('M d, Y',strtotime($iv['interview_date'])); ?></div></div>
        <div><div class="mk"><i class="fas fa-clock" style="margin-right:4px"></i>Time</div><div class="mv"><?php echo date('h:i A',strtotime($iv['interview_date'])); ?></div></div>
        <div style="grid-column:span 2"><div class="mk"><i class="fas fa-map-marker-alt" style="margin-right:4px"></i>Location</div><div class="mv"><?php echo htmlspecialchars($iv['interview_location']?:'TBD'); ?></div></div>
        <?php if($iv['interview_notes']): ?>
        <div style="grid-column:span 2"><div class="mk">Notes</div><div class="mv" style="font-weight:400;color:var(--text2)"><?php echo htmlspecialchars($iv['interview_notes']); ?></div></div>
        <?php endif; ?>
    </div>
    <?php if($iv['status']==='Declined' && !empty($iv['decline_reason'])): ?>
    <div class="reschedule-note">
        <i class="fas fa-exclamation-circle" style="margin-top:1px;flex-shrink:0"></i>
        <div><strong>Intern declined — reschedule needed.</strong><br>Reason: <?php echo htmlspecialchars($iv['decline_reason']); ?></div>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; endif; ?>

<?php /* ══════════ INTERN VIEW ══════════ */ elseif($is_intern): ?>

<?php
$sub_status = $my_submission['status'] ?? null;
$bc = strtolower(str_replace(' ','-',$sub_status??'pending'));
$configs = [
    'approved'     => ['icon'=>'fa-check-circle',   'color'=>'var(--accent)',  'title'=>'Requirements Approved!',    'sub'=>'Your requirements are approved. HR will schedule your interview soon.'],
    'pending'      => ['icon'=>'fa-hourglass-half', 'color'=>'var(--warn)',    'title'=>'Submission Under Review',   'sub'=>'Your requirements are being reviewed by HR.'],
    'under-review' => ['icon'=>'fa-search',         'color'=>'var(--accent2)', 'title'=>'Currently Being Reviewed', 'sub'=>'HR is actively reviewing your documents.'],
    'rejected'     => ['icon'=>'fa-times-circle',   'color'=>'var(--danger)',  'title'=>'Submission Rejected',       'sub'=>'Please check the remarks and resubmit.'],
];
$cfg = $configs[$bc] ?? $configs['pending'];
?>

<?php if($my_submission): ?>
<div class="banner <?php echo $bc; ?>">
    <div style="width:46px;height:46px;border-radius:12px;background:<?php echo $cfg['color']; ?>1a;color:<?php echo $cfg['color']; ?>;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">
        <i class="fas <?php echo $cfg['icon']; ?>"></i>
    </div>
    <div style="flex:1">
        <div style="font-size:15px;font-weight:700;color:var(--text)"><?php echo $cfg['title']; ?></div>
        <div style="font-size:13px;color:var(--text2);margin-top:2px"><?php echo $cfg['sub']; ?></div>
        <?php if($my_submission['remarks']): ?>
        <div style="margin-top:8px;padding:8px 12px;background:rgba(255,255,255,.04);border-radius:6px;font-size:12px;color:var(--text2)">
            <strong style="color:var(--text)">HR Remarks:</strong> <?php echo htmlspecialchars($my_submission['remarks']); ?>
        </div>
        <?php endif; ?>
    </div>
    <span class="status-badge status-<?php echo $bc; ?>"><?php echo $my_submission['status']; ?></span>
</div>
<?php else: ?>
<div class="alert alert-warning">
<i class="fas fa-exclamation-triangle"></i> No submission yet.
<a href="<?php echo APP_URL; ?>/views/processes/intern_submit_requirements.php" style="color:var(--accent2);font-weight:600;margin-left:4px">Submit requirements →</a>
</div>
<?php endif; ?>

<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin:20px 0 12px;display:flex;align-items:center;gap:8px">
My Interview Schedule <span style="flex:1;height:1px;background:var(--border)"></span>
</div>

<?php if(empty($interviews)): ?>
<div class="content-section" style="text-align:center;padding:48px">
    <i class="fas fa-calendar-times" style="font-size:40px;color:var(--text3);display:block;margin-bottom:12px"></i>
    <p style="color:var(--text2)">
        <?php echo $sub_status==='Approved'
            ? 'Requirements approved — HR will schedule your interview soon.'
            : ($sub_status==='Rejected' ? 'Please resubmit your requirements.' : 'Awaiting HR approval of your requirements.'); ?>
    </p>
    <?php if($sub_status!=='Approved'): ?>
    <a href="<?php echo APP_URL; ?>/views/processes/intern_submit_requirements.php" class="btn btn-primary" style="margin-top:16px;display:inline-flex">
        <i class="fas fa-file-alt"></i> <?php echo $my_submission?'View Submission':'Submit Requirements'; ?>
    </a>
    <?php endif; ?>
</div>
<?php else: foreach($interviews as $iv):
    $slug = strtolower(str_replace(' ','-',$iv['status']));
    $can_respond = ($iv['status'] === 'Pending');
?>
<div class="iv-card status-<?php echo $slug; ?>">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <div style="width:38px;height:38px;border-radius:9px;background:rgba(79,255,176,.1);display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:16px">
            <i class="fas fa-user-tie"></i>
        </div>
        <div style="flex:1">
            <div style="font-size:14px;font-weight:700;color:var(--text)">Interview with HR</div>
            <div style="font-size:11px;color:var(--text3)"><?php echo htmlspecialchars($iv['hr_first'].' '.$iv['hr_last']); ?></div>
        </div>
        <span class="status-badge status-<?php echo $slug; ?>"><?php echo $iv['status']; ?></span>
    </div>

    <div class="iv-meta">
        <div><div class="mk"><i class="fas fa-calendar" style="margin-right:4px"></i>Date</div><div class="mv"><?php echo date('F d, Y',strtotime($iv['interview_date'])); ?></div></div>
        <div><div class="mk"><i class="fas fa-clock" style="margin-right:4px"></i>Time</div><div class="mv"><?php echo date('h:i A',strtotime($iv['interview_date'])); ?></div></div>
        <div style="grid-column:span 2"><div class="mk"><i class="fas fa-map-marker-alt" style="margin-right:4px"></i>Location</div><div class="mv"><?php echo htmlspecialchars($iv['interview_location']?:'TBD — check with HR'); ?></div></div>
        <?php if($iv['interview_notes']): ?>
        <div style="grid-column:span 2"><div class="mk">Notes from HR</div><div class="mv" style="font-weight:400;color:var(--text2)"><?php echo htmlspecialchars($iv['interview_notes']); ?></div></div>
        <?php endif; ?>
    </div>

    <?php if($iv['status']==='Accepted'): ?>
    <div style="background:rgba(79,255,176,.07);border:1px solid rgba(79,255,176,.2);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--accent);display:flex;align-items:center;gap:8px">
        <i class="fas fa-check-circle"></i> You accepted this interview. Be prepared and on time!
    </div>
    <?php elseif($iv['status']==='Declined'): ?>
    <div class="reschedule-note">
        <i class="fas fa-info-circle" style="margin-top:1px;flex-shrink:0"></i>
        <div>You declined this interview. HR has been notified to reschedule.
        <?php if(!empty($iv['decline_reason'])): ?><br><strong>Your reason:</strong> <?php echo htmlspecialchars($iv['decline_reason']); ?><?php endif; ?>
        </div>
    </div>
    <?php elseif($can_respond): ?>
    <!-- Accept / Decline buttons -->
    <form method="POST" id="respond-form-<?php echo $iv['id']; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="respond">
        <input type="hidden" name="interview_id" value="<?php echo $iv['id']; ?>">
        <input type="hidden" name="response" id="response-<?php echo $iv['id']; ?>" value="">

        <div class="respond-btns">
            <button type="button" class="btn-accept" onclick="submitResponse(<?php echo $iv['id']; ?>,'Accepted')">
                <i class="fas fa-check-circle"></i> Accept Interview
            </button>
            <button type="button" class="btn-decline" onclick="showDecline(<?php echo $iv['id']; ?>)">
                <i class="fas fa-times-circle"></i> Decline & Request Reschedule
            </button>
        </div>

        <!-- Decline reason (hidden until clicked) -->
        <div class="decline-box" id="decline-box-<?php echo $iv['id']; ?>">
            <p style="font-size:12px;font-weight:600;color:var(--danger);margin-bottom:8px">
                <i class="fas fa-exclamation-triangle" style="margin-right:4px"></i>
                Please provide a reason so HR can reschedule appropriately:
            </p>
            <textarea name="decline_reason" rows="3" placeholder="e.g. I have a conflict on this date, please reschedule to next week…" required></textarea>
            <div style="display:flex;gap:8px;margin-top:10px">
                <button type="button" onclick="hideDecline(<?php echo $iv['id']; ?>)" class="btn btn-secondary btn-sm">Cancel</button>
                <button type="button" onclick="submitResponse(<?php echo $iv['id']; ?>,'Declined')" class="btn btn-sm" style="background:rgba(248,113,113,.15);color:var(--danger);border:1px solid rgba(248,113,113,.3)">
                    <i class="fas fa-paper-plane"></i> Send Decline
                </button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php endforeach; endif; ?>

<?php endif; /* end role views */ ?>

<div style="margin-top:20px">
    <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
</div>

<?php if($is_hr): ?>
<!-- Schedule Drawer -->
<div class="drawer-overlay" id="dOverlay" onclick="closeDrawer()"></div>
<div class="drawer" id="schedDrawer">
    <div class="drawer-header">
        <div style="width:36px;height:36px;border-radius:9px;background:rgba(79,255,176,.1);display:flex;align-items:center;justify-content:center;color:var(--accent)"><i class="fas fa-calendar-plus"></i></div>
        <div>
            <div style="font-size:15px;font-weight:700;color:var(--text)">Schedule Interview</div>
            <div style="font-size:12px;color:var(--text3)">Approved interns only</div>
        </div>
        <button class="drawer-close" onclick="closeDrawer()"><i class="fas fa-times"></i></button>
    </div>
    <div class="drawer-body">
        <?php if(empty($approved_interns)): ?>
        <div style="text-align:center;padding:40px">
            <i class="fas fa-user-slash" style="font-size:36px;color:var(--text3);display:block;margin-bottom:12px"></i>
            <p style="color:var(--text2)">No approved interns yet.</p>
            <a href="<?php echo APP_URL; ?>/views/processes/hr_check_requirements.php" class="btn btn-primary" style="margin-top:12px;display:inline-flex">
                <i class="fas fa-clipboard-check"></i> Review Requirements
            </a>
        </div>
        <?php else: ?>
        <form method="POST" id="schedForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="schedule">
            <div class="form-group">
                <label>Intern <span class="required">*</span></label>
                <select name="intern_id" required>
                    <option value="">— Select —</option>
                    <?php foreach($approved_interns as $i): ?>
                    <option value="<?php echo $i['id']; ?>"><?php echo htmlspecialchars($i['first_name'].' '.$i['last_name']); ?> — <?php echo htmlspecialchars($i['school_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid-2">
                <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" name="interview_date" required min="<?php echo date('Y-m-d'); ?>"></div>
                <div class="form-group"><label>Time <span class="required">*</span></label><input type="time" name="interview_time" required></div>
            </div>
            <div class="form-group"><label>Location</label><input type="text" name="location" placeholder="e.g. Conference Room A, Zoom"></div>
            <div class="form-group"><label>Notes for Intern</label><textarea name="notes" rows="3" placeholder="What to bring, dress code…"></textarea></div>
        </form>
        <?php endif; ?>
    </div>
    <?php if(!empty($approved_interns)): ?>
    <div class="drawer-footer">
        <button onclick="closeDrawer()" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</button>
        <button type="submit" form="schedForm" class="btn btn-primary"><i class="fas fa-calendar-check"></i> Schedule</button>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>

<script>
/* Drawer (HR only) */
function openDrawer(){document.getElementById('dOverlay').classList.add('open');document.getElementById('schedDrawer').classList.add('open');document.body.style.overflow='hidden';}
function closeDrawer(){document.getElementById('dOverlay').classList.remove('open');document.getElementById('schedDrawer').classList.remove('open');document.body.style.overflow='';}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeDrawer();});

/* Intern respond */
function submitResponse(id, response) {
    document.getElementById('response-'+id).value = response;
    if (response === 'Accepted') {
        if (!confirm('Confirm you are accepting this interview?')) return;
    }
    document.getElementById('respond-form-'+id).submit();
}
function showDecline(id) {
    document.getElementById('decline-box-'+id).style.display = 'block';
}
function hideDecline(id) {
    document.getElementById('decline-box-'+id).style.display = 'none';
}
</script>
</body>
</html>
