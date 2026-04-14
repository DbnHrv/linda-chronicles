<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(3);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
try { $submissions = $processModel->getAllInternshipSubmissions(); }
catch (Exception $e) { $submissions = []; $message = $e->getMessage(); $message_type = 'error'; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { $message = 'Invalid token.'; $message_type = 'error'; }
    else { try {
        $sid = intval($_POST['submission_id'] ?? 0);
        $status = sanitize($_POST['status'] ?? '');
        $rem = sanitize($_POST['remarks'] ?? '');
        if (!$sid || !$status) throw new Exception('Required.');
        $processModel->reviewInternshipSubmission($sid, $status, $rem);
        $message = 'Review submitted.'; $message_type = 'success';
        $submissions = $processModel->getAllInternshipSubmissions();
    } catch(Exception $e) { $message = $e->getMessage(); $message_type = 'error'; } }
}
$doc_fields = [
    'coe_file'                  => ['label'=>'Certificate of Enrollment',  'icon'=>'fa-id-card'],
    'resume_file'               => ['label'=>'Resume / CV',                'icon'=>'fa-file-user'],
    'cover_letter_file'         => ['label'=>'Cover Letter',               'icon'=>'fa-envelope-open-text'],
    'parent_consent_file'       => ['label'=>'Parental Consent',           'icon'=>'fa-hands-helping'],
    'medical_certificate_file'  => ['label'=>'Medical Certificate',        'icon'=>'fa-notes-medical'],
    'school_endorsement_file'   => ['label'=>'School Endorsement',         'icon'=>'fa-school'],
    'tor_file'                  => ['label'=>'Transcript of Records',      'icon'=>'fa-graduation-cap'],
    'moa_file'                  => ['label'=>'Memorandum of Agreement',    'icon'=>'fa-file-signature'],
];
$total    = count($submissions);
$pending  = 0; $approved = 0; $rejected = 0;
foreach ($submissions as $s) {
    if ($s['status'] === 'Pending')  $pending++;
    if ($s['status'] === 'Approved') $approved++;
    if ($s['status'] === 'Rejected') $rejected++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Check Requirements — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
:root{--bg:#0a0c10;--surface:#111318;--surface2:#161a22;--surface3:#1c2130;--border:rgba(255,255,255,.07);--border2:rgba(255,255,255,.12);--accent:#38bdf8;--accent2:#a78bfa;--accent3:#34d399;--warn:#fbbf24;--danger:#f87171;--text:#f1f5f9;--text2:#94a3b8;--text3:#475569}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}
.navbar{background:var(--surface);border-bottom:1px solid var(--border);padding:0 24px;height:60px;display:flex;align-items:center;position:sticky;top:0;z-index:100}
.navbar-content{display:flex;align-items:center;justify-content:space-between;width:100%}
.navbar-content h2{font-size:16px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.navbar-right{display:flex;align-items:center;gap:10px}
.user-role{font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;background:rgba(167,139,250,.15);color:var(--accent2);border:1px solid rgba(167,139,250,.3)}
.btn-nav{font-size:12px;font-weight:500;padding:5px 12px;border-radius:6px;border:1px solid var(--border2);color:var(--text2);text-decoration:none;display:flex;align-items:center;gap:6px;transition:.2s}
.btn-nav:hover{background:var(--surface3);color:var(--text)}
.page-wrapper{max-width:1200px;margin:0 auto;padding:32px 24px}
.page-header{margin-bottom:28px}
.page-header h1{font-size:24px;font-weight:700;display:flex;align-items:center;gap:10px}
.page-header h1 i{color:var(--accent)}
.page-header p{color:var(--text2);font-size:14px;margin-top:6px}
.alert{padding:12px 16px;border-radius:8px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;margin-bottom:20px}
.alert-success{background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.3);color:var(--accent3)}
.alert-error{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);color:var(--danger)}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px;display:flex;align-items:center;gap:16px}
.stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.stat-icon.blue{background:rgba(56,189,248,.12);color:var(--accent)}
.stat-icon.amber{background:rgba(251,191,36,.12);color:var(--warn)}
.stat-icon.green{background:rgba(52,211,153,.12);color:var(--accent3)}
.stat-icon.red{background:rgba(248,113,113,.12);color:var(--danger)}
.stat-val{font-size:26px;font-weight:700;line-height:1}
.stat-lbl{font-size:12px;color:var(--text2);margin-top:3px}
.toolbar{display:flex;align-items:center;gap:10px;margin-bottom:20px;flex-wrap:wrap}
.filter-btn{font-size:12px;font-weight:600;padding:6px 14px;border-radius:20px;border:1px solid var(--border2);background:transparent;color:var(--text2);cursor:pointer;transition:.2s}
.filter-btn:hover,.filter-btn.active{background:var(--accent);border-color:var(--accent);color:#000}
.search-wrap{margin-left:auto;position:relative}
.search-wrap i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:13px}
.search-wrap input{background:var(--surface2);border:1px solid var(--border2);border-radius:8px;padding:7px 12px 7px 32px;color:var(--text);font-size:13px;width:220px;outline:none}
.search-wrap input:focus{border-color:var(--accent)}
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px}
.sub-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px;transition:.2s;cursor:default}
.sub-card:hover{border-color:var(--border2);background:var(--surface2)}
.card-top{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:#fff;flex-shrink:0}
.card-name{font-size:14px;font-weight:600;color:var(--text)}
.card-email{font-size:12px;color:var(--text2);margin-top:2px}
.status-badge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:3px 9px;border-radius:20px}
.status-pending{background:rgba(251,191,36,.12);color:var(--warn);border:1px solid rgba(251,191,36,.3)}
.status-approved{background:rgba(52,211,153,.12);color:var(--accent3);border:1px solid rgba(52,211,153,.3)}
.status-rejected{background:rgba(248,113,113,.12);color:var(--danger);border:1px solid rgba(248,113,113,.3)}
.status-under.review,.status-under-review{background:rgba(56,189,248,.12);color:var(--accent);border:1px solid rgba(56,189,248,.3)}
.card-meta{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}
.card-meta span{font-size:12px;color:var(--text2);display:flex;align-items:center;gap:6px}
.card-meta span i{color:var(--text3);width:12px}
.progress-wrap{margin-bottom:14px}
.progress-label{font-size:11px;color:var(--text2);margin-bottom:5px;display:flex;justify-content:space-between}
.progress-bar{height:5px;background:var(--surface3);border-radius:3px;overflow:hidden}
.progress-fill{height:100%;background:linear-gradient(90deg,var(--accent),var(--accent2));border-radius:3px;transition:.4s}
.btn-review{width:100%;padding:8px;border-radius:8px;border:1px solid rgba(56,189,248,.4);background:rgba(56,189,248,.08);color:var(--accent);font-size:13px;font-weight:600;cursor:pointer;transition:.2s;display:flex;align-items:center;justify-content:center;gap:6px}
.btn-review:hover{background:rgba(56,189,248,.18)}
.empty-state{text-align:center;padding:60px 20px;color:var(--text3)}
.empty-state i{font-size:48px;margin-bottom:16px;display:block}
/* Drawer */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;opacity:0;pointer-events:none;transition:.3s}
.overlay.open{opacity:1;pointer-events:all}
.drawer{position:fixed;top:0;right:0;width:680px;max-width:100vw;height:100vh;background:var(--surface);border-left:1px solid var(--border2);z-index:201;transform:translateX(100%);transition:.35s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;overflow:hidden}
.drawer.open{transform:translateX(0)}
.drawer-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.drawer-header h3{font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px}
.drawer-header h3 i{color:var(--accent)}
.drawer-close{background:none;border:none;color:var(--text2);font-size:18px;cursor:pointer;padding:4px 8px;border-radius:6px;transition:.2s}
.drawer-close:hover{background:var(--surface3);color:var(--text)}
.drawer-body{flex:1;overflow-y:auto;padding:24px}
.drawer-body::-webkit-scrollbar{width:5px}
.drawer-body::-webkit-scrollbar-track{background:transparent}
.drawer-body::-webkit-scrollbar-thumb{background:var(--surface3);border-radius:3px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px}
.info-cell{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px}
.info-cell .lbl{font-size:11px;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.info-cell .val{font-size:13px;color:var(--text);font-weight:500}
.section-title{font-size:13px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;display:flex;align-items:center;gap:6px}
.doc-list{display:flex;flex-direction:column;gap:8px;margin-bottom:24px}
.doc-row{display:flex;align-items:center;gap:12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 14px}
.doc-row i.doc-icon{width:28px;height:28px;border-radius:6px;background:var(--surface3);display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--text2);flex-shrink:0}
.doc-row .doc-name{flex:1;font-size:13px;color:var(--text)}
.doc-row .doc-status{font-size:11px}
.doc-row .doc-status.uploaded{color:var(--accent3)}
.doc-row .doc-status.missing{color:var(--text3)}
.btn-view{font-size:11px;font-weight:600;padding:4px 10px;border-radius:5px;border:1px solid rgba(56,189,248,.4);background:rgba(56,189,248,.08);color:var(--accent);text-decoration:none;transition:.2s;white-space:nowrap}
.btn-view:hover{background:rgba(56,189,248,.2)}
.decision-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px}
.decision-card{position:relative}
.decision-card input[type=radio]{position:absolute;opacity:0;width:0;height:0}
.decision-card label{display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 10px;border-radius:10px;border:2px solid var(--border);background:var(--surface2);cursor:pointer;transition:.2s;text-align:center}
.decision-card label i{font-size:22px}
.decision-card label span{font-size:12px;font-weight:600}
.decision-card input:checked + label.approve{border-color:var(--accent3);background:rgba(52,211,153,.1)}
.decision-card input:checked + label.review{border-color:var(--warn);background:rgba(251,191,36,.1)}
.decision-card input:checked + label.reject{border-color:var(--danger);background:rgba(248,113,113,.1)}
.decision-card label.approve i{color:var(--accent3)}
.decision-card label.review i{color:var(--warn)}
.decision-card label.reject i{color:var(--danger)}
.decision-card label:hover{border-color:var(--border2)}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:12px;font-weight:600;color:var(--text2);margin-bottom:6px}
.form-group textarea{width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;padding:10px 12px;color:var(--text);font-size:13px;font-family:inherit;resize:vertical;outline:none;min-height:80px}
.form-group textarea:focus{border-color:var(--accent)}
.drawer-footer{padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;flex-shrink:0}
.btn{padding:9px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:6px;transition:.2s;text-decoration:none}
.btn-primary{background:var(--accent);color:#000}
.btn-primary:hover{background:#7dd3fc}
.btn-secondary{background:var(--surface3);color:var(--text2);border:1px solid var(--border2)}
.btn-secondary:hover{background:var(--surface2);color:var(--text)}
.footer{text-align:center;padding:24px;color:var(--text3);font-size:12px;border-top:1px solid var(--border);margin-top:40px}
@media(max-width:768px){.stats-grid{grid-template-columns:repeat(2,1fr)}.drawer{width:100vw}.info-grid{grid-template-columns:1fr}.decision-cards{grid-template-columns:1fr}}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
  <div class="navbar-content">
    <h2><i class="fas fa-capsules"></i><?php echo APP_NAME; ?></h2>
    <div class="navbar-right">
      <span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']] ?? 'User'); ?></span>
      <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
      <a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </div>
</nav>

<div class="page-wrapper">

  <!-- Page Header -->
  <div class="page-header">
    <h1><i class="fas fa-clipboard-check"></i> Internship Requirements</h1>
    <p>Review and manage intern document submissions</p>
  </div>

  <!-- Alert -->
  <?php if ($message): ?>
  <div class="alert alert-<?php echo $message_type; ?>">
    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <?php echo htmlspecialchars($message); ?>
  </div>
  <?php endif; ?>

  <!-- Stat Cards -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="fas fa-users"></i></div>
      <div><div class="stat-val"><?php echo $total; ?></div><div class="stat-lbl">Total Submissions</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
      <div><div class="stat-val"><?php echo $pending; ?></div><div class="stat-lbl">Pending Review</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
      <div><div class="stat-val"><?php echo $approved; ?></div><div class="stat-lbl">Approved</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
      <div><div class="stat-val"><?php echo $rejected; ?></div><div class="stat-lbl">Rejected</div></div>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <button class="filter-btn active" onclick="filterCards('all',this)">All <span style="opacity:.6">(<?php echo $total; ?>)</span></button>
    <button class="filter-btn" onclick="filterCards('pending',this)">Pending</button>
    <button class="filter-btn" onclick="filterCards('approved',this)">Approved</button>
    <button class="filter-btn" onclick="filterCards('under review',this)">Under Review</button>
    <button class="filter-btn" onclick="filterCards('rejected',this)">Rejected</button>
    <div class="search-wrap">
      <i class="fas fa-search"></i>
      <input type="text" placeholder="Search by name or school…" oninput="searchCards(this.value)">
    </div>
  </div>

  <!-- Cards Grid -->
  <div class="cards-grid" id="cardsGrid">
    <?php if (empty($submissions)): ?>
    <div class="empty-state" style="grid-column:1/-1">
      <i class="fas fa-inbox"></i>
      <p>No submissions found.</p>
    </div>
    <?php else: ?>
    <?php foreach ($submissions as $sub):
      $docs_uploaded = 0;
      foreach ($doc_fields as $field => $info) { if (!empty($sub[$field])) $docs_uploaded++; }
      $progress_pct = round(($docs_uploaded / 8) * 100);
      $status_lc = strtolower(str_replace(' ', '-', $sub['status']));
      $initials = strtoupper(substr($sub['first_name'] ?? 'U', 0, 1) . substr($sub['last_name'] ?? '', 0, 1));
      $full_name = htmlspecialchars(trim(($sub['first_name'] ?? '') . ' ' . ($sub['last_name'] ?? '')));
    ?>
    <div class="sub-card"
         data-status="<?php echo strtolower($sub['status']); ?>"
         data-name="<?php echo strtolower($full_name); ?>"
         data-school="<?php echo strtolower(htmlspecialchars($sub['school_name'] ?? '')); ?>">
      <div class="card-top">
        <div class="avatar"><?php echo $initials; ?></div>
        <div style="flex:1;min-width:0">
          <div class="card-name"><?php echo $full_name; ?></div>
          <div class="card-email"><?php echo htmlspecialchars($sub['email'] ?? ''); ?></div>
        </div>
        <span class="status-badge status-<?php echo $status_lc; ?>"><?php echo htmlspecialchars($sub['status']); ?></span>
      </div>
      <div class="card-meta">
        <span><i class="fas fa-university"></i><?php echo htmlspecialchars($sub['school_name'] ?? '—'); ?></span>
        <span><i class="fas fa-book"></i><?php echo htmlspecialchars($sub['course'] ?? '—'); ?></span>
        <span><i class="fas fa-layer-group"></i><?php echo htmlspecialchars($sub['year_level'] ?? '—'); ?></span>
        <span><i class="fas fa-calendar-alt"></i><?php echo $sub['submission_date'] ? date('M d, Y', strtotime($sub['submission_date'])) : '—'; ?></span>
      </div>
      <div class="progress-wrap">
        <div class="progress-label">
          <span>Documents</span>
          <span><?php echo $docs_uploaded; ?>/8</span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?php echo $progress_pct; ?>%"></div></div>
      </div>
      <button class="btn-review" onclick="openDrawer(<?php echo intval($sub['id']); ?>)">
        <i class="fas fa-eye"></i> Review Submission
      </button>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div><!-- /page-wrapper -->

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>

<!-- Overlay -->
<div class="overlay" id="overlay" onclick="closeDrawer()"></div>

<!-- Drawer -->
<div class="drawer" id="drawer">
  <div class="drawer-header">
    <h3><i class="fas fa-clipboard-list"></i> Review Submission</h3>
    <button class="drawer-close" onclick="closeDrawer()"><i class="fas fa-times"></i></button>
  </div>
  <div class="drawer-body" id="drawerBody">
    <!-- populated by JS -->
  </div>
  <div class="drawer-footer">
    <button class="btn btn-secondary" onclick="closeDrawer()"><i class="fas fa-times"></i> Cancel</button>
    <button class="btn btn-primary" onclick="document.getElementById('reviewForm').submit()"><i class="fas fa-paper-plane"></i> Submit Review</button>
  </div>
</div>

<script>
const SUBS = <?php echo json_encode(array_values($submissions), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const DOC_FIELDS = <?php echo json_encode($doc_fields, JSON_HEX_TAG); ?>;
const UPLOADS = '<?php echo APP_URL; ?>/uploads/';
const CSRF = '<?php echo generateCSRFToken(); ?>';

function openDrawer(id) {
  const sub = SUBS.find(s => s.id == id);
  if (!sub) return;
  const fullName = ((sub.first_name || '') + ' ' + (sub.last_name || '')).trim() || 'Unknown';
  const initials = ((sub.first_name||'U')[0] + (sub.last_name||'')[0]).toUpperCase();
  const statusLc = (sub.status||'').toLowerCase().replace(/\s+/g,'-');

  let infoHtml = `
    <div class="info-grid">
      <div class="info-cell"><div class="lbl">Full Name</div><div class="val">${esc(fullName)}</div></div>
      <div class="info-cell"><div class="lbl">Email</div><div class="val">${esc(sub.email||'—')}</div></div>
      <div class="info-cell"><div class="lbl">School</div><div class="val">${esc(sub.school_name||'—')}</div></div>
      <div class="info-cell"><div class="lbl">Course</div><div class="val">${esc(sub.course||'—')}</div></div>
      <div class="info-cell"><div class="lbl">Year Level</div><div class="val">${esc(sub.year_level||'—')}</div></div>
      <div class="info-cell"><div class="lbl">Submitted</div><div class="val">${sub.submission_date ? fmtDate(sub.submission_date) : '—'}</div></div>
    </div>`;

  let docHtml = '<div class="section-title"><i class="fas fa-paperclip"></i> Documents</div><div class="doc-list">';
  for (const [field, info] of Object.entries(DOC_FIELDS)) {
    const file = sub[field];
    const hasFile = file && file.trim() !== '';
    docHtml += `
      <div class="doc-row">
        <div class="doc-icon"><i class="fas ${info.icon}"></i></div>
        <div class="doc-name">${esc(info.label)}</div>
        ${hasFile
          ? `<span class="doc-status uploaded"><i class="fas fa-check-circle"></i> Uploaded</span>
             <a class="btn-view" href="${UPLOADS}${encodeURIComponent(file)}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> View</a>`
          : `<span class="doc-status missing"><i class="fas fa-minus-circle"></i> Not uploaded</span>`
        }
      </div>`;
  }
  docHtml += '</div>';

  const isApproved = sub.status === 'Approved';
  const isUnder    = sub.status === 'Under Review';
  const isRejected = sub.status === 'Rejected';

  let decisionHtml = `
    <div class="section-title"><i class="fas fa-gavel"></i> Decision</div>
    <div class="decision-cards">
      <div class="decision-card">
        <input type="radio" name="status" id="dec-approve" value="Approved" form="reviewForm" ${isApproved?'checked':''}>
        <label for="dec-approve" class="approve"><i class="fas fa-check-circle"></i><span>Approve</span></label>
      </div>
      <div class="decision-card">
        <input type="radio" name="status" id="dec-review" value="Under Review" form="reviewForm" ${isUnder?'checked':''}>
        <label for="dec-review" class="review"><i class="fas fa-search"></i><span>Under Review</span></label>
      </div>
      <div class="decision-card">
        <input type="radio" name="status" id="dec-reject" value="Rejected" form="reviewForm" ${isRejected?'checked':''}>
        <label for="dec-reject" class="reject"><i class="fas fa-times-circle"></i><span>Reject</span></label>
      </div>
    </div>
    <div class="form-group">
      <label for="remarksField">Remarks / Notes</label>
      <textarea id="remarksField" name="remarks" form="reviewForm" placeholder="Optional remarks for the intern…">${esc(sub.remarks||'')}</textarea>
    </div>`;

  const formHtml = `
    <form id="reviewForm" method="POST">
      <input type="hidden" name="action" value="review">
      <input type="hidden" name="csrf_token" value="${CSRF}">
      <input type="hidden" name="submission_id" value="${sub.id}">
    </form>`;

  document.getElementById('drawerBody').innerHTML = infoHtml + docHtml + decisionHtml + formHtml;
  document.getElementById('overlay').classList.add('open');
  document.getElementById('drawer').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeDrawer() {
  document.getElementById('overlay').classList.remove('open');
  document.getElementById('drawer').classList.remove('open');
  document.body.style.overflow = '';
}

function filterCards(status, btn) {
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.sub-card').forEach(card => {
    const match = status === 'all' || card.dataset.status === status;
    card.style.display = match ? '' : 'none';
  });
}

function searchCards(q) {
  const lq = q.toLowerCase().trim();
  document.querySelectorAll('.sub-card').forEach(card => {
    const name   = card.dataset.name   || '';
    const school = card.dataset.school || '';
    card.style.display = (!lq || name.includes(lq) || school.includes(lq)) ? '' : 'none';
  });
}

function esc(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function fmtDate(str) {
  const d = new Date(str);
  return isNaN(d) ? str : d.toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'});
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });
</script>
</body>
</html>
