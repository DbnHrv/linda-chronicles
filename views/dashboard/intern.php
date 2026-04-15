<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');

$processModel = new ProcessModel($pdo);
$uid = $_SESSION['user_id'];

// Live data — wrapped in try/catch so missing tables never crash the page
try { $submission    = $processModel->getInternshipSubmissionByUserId($uid); } catch(Exception $e){ $submission = null; }
try { $interviews    = $processModel->getInterviewsByIntern($uid); }          catch(Exception $e){ $interviews = []; }
try { $my_schedule   = $processModel->getScheduleByIntern($uid); }            catch(Exception $e){ $my_schedule = null; }
try { $my_tasks      = $processModel->getTasksByIntern($uid); }               catch(Exception $e){ $my_tasks = []; }
try { $my_orientations = $processModel->getOrientationsByIntern($uid); }      catch(Exception $e){ $my_orientations = []; }
try { $my_reports    = $processModel->getInventoryReportsByIntern($uid); }    catch(Exception $e){ $my_reports = []; }

// Next upcoming orientation
$next_orientation = null;
foreach ($my_orientations as $o) {
    if ($o['status'] === 'Scheduled' && strtotime($o['orientation_date']) >= time()) {
        $next_orientation = $o;
        break;
    }
}

$processes = array(
    1 => array('icon'=>'fa-file-alt',    'name'=>'Submit Requirements',  'desc'=>'Upload internship documents',       'url'=>'/views/processes/intern_submit_requirements.php', 'color'=>'#4fffb0'),
    4 => array('icon'=>'fa-comments',    'name'=>'Job Interview',         'desc'=>'View your interview schedule',      'url'=>'/views/processes/job_interview.php',              'color'=>'#a78bfa'),
    6 => array('icon'=>'fa-calendar-alt','name'=>'Organize Schedule',     'desc'=>'View your assigned schedule',       'url'=>'/views/processes/organize_schedule.php',          'color'=>'#38bdf8'),
    8 => array('icon'=>'fa-tasks',       'name'=>'Internship Tasks',      'desc'=>'View tasks assigned to you',        'url'=>'/views/processes/assign_intern_tasks.php',         'color'=>'#f59e0b'),
    9 => array('icon'=>'fa-boxes',       'name'=>'Conduct Inventory',     'desc'=>'Count and record product inventory','url'=>'/views/processes/conduct_inventory.php',           'color'=>'#4fffb0'),
    10 => array('icon'=>'fa-chart-bar',  'name'=>'View My Reports',       'desc'=>'Check status of your inventory reports','url'=>'/views/processes/intern_view_inventory_reports.php', 'color'=>'#56bdf8'),
);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Intern Dashboard — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
.stat-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:28px}
.stat{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px 18px;display:flex;align-items:center;gap:12px}
.stat-ico{width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.stat-val{font-size:20px;font-weight:700;color:var(--text);line-height:1}
.stat-lbl{font-size:11px;color:var(--text3);margin-top:2px}
.proc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px}
.proc-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px;text-decoration:none;display:block;transition:all .2s;position:relative;overflow:hidden}
.proc-card:hover{border-color:var(--border2);transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.3)}
.proc-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;opacity:0;transition:opacity .2s}
.proc-card:hover::before{opacity:1}
.section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin-bottom:14px;display:flex;align-items:center;gap:8px}
.section-title::after{content:'';flex:1;height:1px;background:var(--border)}
</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right">
<span class="user-role"><i class="fas fa-user-graduate" style="margin-right:4px"></i><?php echo htmlspecialchars($_SESSION['user_first_name']??''); ?> · Intern</span>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>

<div style="max-width:1100px;margin:0 auto;padding:28px 24px">

<?php if(isset($_SESSION['success'])): ?><div class="alert alert-success" style="margin-bottom:20px"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<?php if(isset($_SESSION['error'])): ?><div class="alert alert-error" style="margin-bottom:20px"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>

<!-- Welcome -->
<div style="background:linear-gradient(135deg,rgba(79,255,176,.08),rgba(56,189,248,.08));border:1px solid rgba(79,255,176,.15);border-radius:12px;padding:20px 24px;margin-bottom:28px;display:flex;align-items:center;gap:16px">
<div style="width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#0a0c10;flex-shrink:0"><?php echo strtoupper(substr($_SESSION['user_first_name']??'I',0,1)); ?></div>
<div>
<div style="font-size:17px;font-weight:700;color:var(--text)">Welcome back, <?php echo htmlspecialchars($_SESSION['user_first_name']??'Intern'); ?></div>
<div style="font-size:13px;color:var(--text2);margin-top:2px"><?php echo htmlspecialchars($_SESSION['user_email']??''); ?> · Intern</div>
</div>
<?php if($submission): ?>
<div style="margin-left:auto;text-align:right">
<div style="font-size:11px;color:var(--text3)">Submission Status</div>
<span class="status-badge status-<?php echo strtolower($submission['status']); ?>" style="margin-top:4px;display:inline-block"><?php echo $submission['status']; ?></span>
</div>
<?php endif; ?>
</div>

<!-- Stats -->
<div class="stat-row">
<div class="stat">
<div class="stat-ico" style="background:rgba(79,255,176,.1);color:var(--accent)"><i class="fas fa-file-alt"></i></div>
<div><div class="stat-val"><?php echo $submission ? '1' : '0'; ?></div><div class="stat-lbl">Submission</div></div>
</div>
<div class="stat">
<div class="stat-ico" style="background:rgba(167,139,250,.1);color:var(--accent3)"><i class="fas fa-comments"></i></div>
<div><div class="stat-val"><?php echo count($interviews); ?></div><div class="stat-lbl">Interviews</div></div>
</div>
<div class="stat">
<div class="stat-ico" style="background:rgba(245,158,11,.1);color:var(--warn)"><i class="fas fa-tasks"></i></div>
<div><div class="stat-val"><?php echo count($my_tasks); ?></div><div class="stat-lbl">Tasks</div></div>
</div>
<div class="stat">
<div class="stat-ico" style="background:rgba(56,189,248,.1);color:var(--accent2)"><i class="fas fa-calendar-alt"></i></div>
<div><div class="stat-val"><?php echo $my_schedule ? '1' : '0'; ?></div><div class="stat-lbl">Schedule</div></div>
</div>
<div class="stat">
<div class="stat-ico" style="background:rgba(167,139,250,.1);color:var(--accent3)"><i class="fas fa-chalkboard-teacher"></i></div>
<div><div class="stat-val"><?php echo count($my_orientations); ?></div><div class="stat-lbl">Orientations</div></div>
</div>
<div class="stat">
<div class="stat-ico" style="background:rgba(56,189,248,.1);color:var(--accent2)"><i class="fas fa-chart-bar"></i></div>
<div><div class="stat-val"><?php echo count($my_reports); ?></div><div class="stat-lbl">Reports</div></div>
</div>
</div>
<div class="section-title">My Processes</div>
<div class="proc-grid">
<?php foreach($processes as $pid => $p): ?>
<a href="<?php echo APP_URL . $p['url']; ?>" class="proc-card" style="--c:<?php echo $p['color']; ?>">
<style>.proc-card[href*="<?php echo basename($p['url'],'.php'); ?>"]::before{background:linear-gradient(90deg,<?php echo $p['color']; ?>,transparent)}</style>
<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
<div style="width:36px;height:36px;border-radius:8px;background:<?php echo $p['color']; ?>1a;display:flex;align-items:center;justify-content:center;color:<?php echo $p['color']; ?>;font-size:15px;flex-shrink:0"><i class="fas <?php echo $p['icon']; ?>"></i></div>
<div>
<div style="font-size:10px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.06em">Process <?php echo $pid; ?></div>
<div style="font-size:13px;font-weight:700;color:var(--text)"><?php echo $p['name']; ?></div>
</div>
</div>
<p style="font-size:12px;color:var(--text2);margin-bottom:10px"><?php echo $p['desc']; ?></p>
<div style="font-size:12px;font-weight:600;color:<?php echo $p['color']; ?>">Open →</div>
</a>
<?php endforeach; ?>
</div>

<!-- Recent tasks -->
<?php if(!empty($my_tasks)): ?>
<div class="section-title" style="margin-top:28px">Recent Tasks</div>
<?php foreach(array_slice($my_tasks,0,3) as $t): $slug=strtolower(str_replace(' ','-',$t['status'])); ?>
<div class="info-card" style="margin-bottom:10px">
<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
<div><div style="font-size:13px;font-weight:600;color:var(--text)"><?php echo htmlspecialchars($t['task_title']); ?></div>
<?php if($t['task_deadline']): ?><div style="font-size:11px;color:var(--warn);margin-top:3px"><i class="fas fa-clock" style="margin-right:4px"></i>Due <?php echo date('M d, Y',strtotime($t['task_deadline'])); ?></div><?php endif; ?>
</div><span class="status-badge status-<?php echo $slug; ?>"><?php echo $t['status']; ?></span>
</div></div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Orientation Schedule -->
<div class="section-title" style="margin-top:28px">Company Orientation Schedule</div>
<?php if(empty($my_orientations)): ?>
<div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:28px;text-align:center;color:var(--text3)">
    <i class="fas fa-chalkboard-teacher" style="font-size:32px;margin-bottom:10px;display:block;color:var(--accent3)"></i>
    <p style="font-size:13px">No orientation sessions scheduled yet. HR will schedule one after your interview.</p>
</div>
<?php else: ?>
<?php foreach($my_orientations as $o):
    $slug = strtolower($o['status']);
    $is_upcoming = strtotime($o['orientation_date']) >= time();
    $border_color = $slug === 'completed' ? 'var(--accent)' : ($slug === 'cancelled' ? 'var(--danger)' : 'var(--accent3)');
?>
<div style="background:var(--surface);border:1px solid var(--border);border-left:3px solid <?php echo $border_color; ?>;border-radius:10px;padding:16px 18px;margin-bottom:12px">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div style="flex:1;min-width:0">
            <!-- Date & time highlight -->
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                <div style="width:44px;height:44px;border-radius:10px;background:rgba(167,139,250,.12);display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0">
                    <div style="font-size:16px;font-weight:800;color:var(--accent3);line-height:1"><?php echo date('d',strtotime($o['orientation_date'])); ?></div>
                    <div style="font-size:9px;font-weight:600;color:var(--text3);text-transform:uppercase"><?php echo date('M',strtotime($o['orientation_date'])); ?></div>
                </div>
                <div>
                    <div style="font-size:14px;font-weight:700;color:var(--text)">Company Orientation</div>
                    <div style="font-size:12px;color:var(--text2);margin-top:2px">
                        <i class="fas fa-clock" style="margin-right:4px;color:var(--text3)"></i><?php echo date('h:i A', strtotime($o['orientation_date'])); ?>
                        <?php if(!empty($o['venue'])): ?>
                        &nbsp;·&nbsp;<i class="fas fa-map-marker-alt" style="margin-right:4px;color:var(--text3)"></i><?php echo htmlspecialchars($o['venue']); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if(!empty($o['content'])): ?>
            <div style="background:var(--surface2);border-radius:6px;padding:10px 12px;margin-bottom:8px">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);margin-bottom:4px">Agenda</div>
                <div style="font-size:12px;color:var(--text2);line-height:1.5"><?php echo nl2br(htmlspecialchars($o['content'])); ?></div>
            </div>
            <?php endif; ?>

            <?php if(!empty($o['facilitator_first'])): ?>
            <div style="font-size:12px;color:var(--text3)">
                <i class="fas fa-user-tie" style="margin-right:4px"></i>
                Facilitated by <?php echo htmlspecialchars($o['facilitator_first'].' '.$o['facilitator_last']); ?>
            </div>
            <?php endif; ?>

            <?php if($is_upcoming && $slug === 'scheduled'): ?>
            <div style="margin-top:10px;background:rgba(167,139,250,.07);border:1px solid rgba(167,139,250,.2);border-radius:6px;padding:8px 12px;font-size:12px;color:var(--accent3)">
                <i class="fas fa-bell" style="margin-right:6px"></i>
                <strong>Upcoming:</strong> <?php
                    $diff = strtotime($o['orientation_date']) - time();
                    $days = floor($diff / 86400);
                    if ($days === 0) echo 'Today!';
                    elseif ($days === 1) echo 'Tomorrow!';
                    else echo 'In '.$days.' days';
                ?>
            </div>
            <?php endif; ?>
        </div>
        <span class="status-badge status-<?php echo $slug; ?>" style="flex-shrink:0"><?php echo $o['status']; ?></span>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body></html>
