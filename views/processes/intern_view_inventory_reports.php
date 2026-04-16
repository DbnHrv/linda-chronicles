<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');

$processModel = new ProcessModel($pdo);
$uid = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get all inventory reports for this intern
try {
    $reports = $processModel->getInventoryReportsByIntern($uid);
} catch(Exception $e) {
    $reports = [];
    $message = 'Error loading reports: ' . $e->getMessage();
    $message_type = 'error';
}

// Handle report resubmission (when rejected report is updated)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'resubmit_report') {
        $report_id = intval($_POST['report_id'] ?? 0);
        $inventory_id = intval($_POST['inventory_id'] ?? 0);
        
        if (!$report_id || !$inventory_id) {
            $message = 'Invalid report data.';
            $message_type = 'error';
        } else {
            try {
                // Update the report status back to Pending
                $stmt = $pdo->prepare("
                    UPDATE inventory_reports 
                    SET verification_status = 'Pending', verified_by = NULL, verified_at = NULL, remarks = NULL
                    WHERE id = ? AND created_by = ?
                ");
                $stmt->execute([$report_id, $uid]);
                
                $message = 'Report resubmitted successfully. Waiting for pharmacy technician review.';
                $message_type = 'success';
                
                // Reload reports
                $reports = $processModel->getInventoryReportsByIntern($uid);
            } catch(Exception $e) {
                $message = 'Error resubmitting report: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    } elseif ($_POST['action'] === 'update_and_resubmit') {
        $report_id = intval($_POST['report_id'] ?? 0);
        $inventory_id = intval($_POST['inventory_id'] ?? 0);
        $total_items = intval($_POST['total_items'] ?? 0);
        $quantities = json_decode($_POST['quantities'] ?? '{}', true);
        
        if (!$report_id || !$inventory_id || $total_items <= 0) {
            $message = 'Invalid report data.';
            $message_type = 'error';
        } else {
            try {
                // Update inventory items with new quantities
                foreach ($quantities as $product_id => $qty) {
                    $stmt = $pdo->prepare("
                        UPDATE inventory_items 
                        SET quantity = ? 
                        WHERE inventory_id = ? AND product_id = ?
                    ");
                    $stmt->execute([$qty, $inventory_id, $product_id]);
                }
                
                // Update the report with new total items and reset status to Pending
                $stmt = $pdo->prepare("
                    UPDATE inventory_reports 
                    SET total_items = ?, verification_status = 'Pending', verified_by = NULL, verified_at = NULL, remarks = NULL
                    WHERE id = ? AND created_by = ?
                ");
                $stmt->execute([$total_items, $report_id, $uid]);
                
                $message = 'Inventory updated and report resubmitted successfully. Waiting for pharmacy technician review.';
                $message_type = 'success';
                
                // Reload reports
                $reports = $processModel->getInventoryReportsByIntern($uid);
            } catch(Exception $e) {
                $message = 'Error updating report: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Inventory Reports — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
.wrap{max-width:1000px;margin:0 auto;padding:28px 24px}
.report-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:16px;transition:all .2s}
.report-card:hover{border-color:var(--border2);box-shadow:0 4px 12px rgba(0,0,0,.1)}
.report-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px;flex-wrap:wrap}
.report-info{flex:1;min-width:0}
.report-title{font-size:15px;font-weight:700;color:var(--text);margin-bottom:8px}
.report-meta{display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:var(--text2)}
.report-meta-item{display:flex;align-items:center;gap:6px}
.status-badge{display:inline-block;padding:6px 12px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.status-pending{background:rgba(245,158,11,.12);color:var(--warn)}
.status-approved{background:rgba(79,255,176,.12);color:var(--accent)}
.status-rejected{background:rgba(248,113,113,.12);color:var(--danger)}
.report-details{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:14px;font-size:12px}
.report-details-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)}
.report-details-row:last-child{border-bottom:none}
.report-details-label{color:var(--text3);font-weight:600}
.report-details-value{color:var(--text);font-weight:600}
.remarks-box{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.2);border-radius:8px;padding:12px;margin-bottom:14px;border-left:3px solid var(--danger)}
.remarks-label{font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px}
.remarks-text{font-size:12px;color:var(--text2);line-height:1.5}
.action-buttons{display:flex;gap:10px;flex-wrap:wrap}
.btn{display:inline-block;padding:10px 16px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;cursor:pointer;border:none;transition:all .2s;text-align:center}
.btn-primary{background:var(--accent);color:#0a0c10}
.btn-primary:hover{background:var(--accent2);transform:translateY(-1px)}
.btn-secondary{background:var(--surface);border:1px solid var(--border);color:var(--text)}
.btn-secondary:hover{border-color:var(--border2)}
.btn-sm{padding:8px 12px;font-size:11px}
.empty-state{text-align:center;padding:40px 20px;color:var(--text3)}
.empty-state-icon{font-size:48px;margin-bottom:12px;display:block;color:var(--accent2)}
.tabs{display:flex;gap:12px;margin-bottom:24px;border-bottom:2px solid var(--border);flex-wrap:wrap}
.tab{padding:12px 16px;border:none;background:none;color:var(--text2);font-size:13px;font-weight:600;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .2s}
.tab.active{color:var(--accent);border-bottom-color:var(--accent)}
.tab-content{display:none}
.tab-content.active{display:block}
.inventory-table{width:100%;border-collapse:collapse;font-size:11px;margin-top:12px}
.inventory-table th{padding:10px;text-align:left;background:var(--surface3);color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border)}
.inventory-table td{padding:10px;border-bottom:1px solid var(--border);color:var(--text2)}
.inventory-table tbody tr:hover{background:var(--surface3)}
</style>
</head>
<body>
<nav class="navbar">
  <div class="navbar-content">
    <h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
    <div class="navbar-right">
      <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
      <a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </div>
</nav>

<div class="wrap">

<?php if($message): ?>
<div class="alert alert-<?php echo $message_type; ?>" style="margin-bottom:20px"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- Header -->
<div style="background:linear-gradient(135deg,rgba(79,255,176,.08),rgba(56,189,248,.08));border:1px solid rgba(79,255,176,.15);border-radius:12px;padding:20px 24px;margin-bottom:28px;display:flex;align-items:center;gap:14px">
  <div style="width:48px;height:48px;border-radius:10px;background:rgba(79,255,176,.15);display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:20px;flex-shrink:0">
    <i class="fas fa-chart-bar"></i>
  </div>
  <div>
    <div style="font-size:16px;font-weight:700;color:var(--text)">My Inventory Reports</div>
    <div style="font-size:12px;color:var(--text2);margin-top:2px">View and manage your submitted inventory reports</div>
  </div>
</div>

<?php
// Separate reports by status
$pending_reports = array_filter($reports, fn($r) => $r['verification_status'] === 'Pending');
$approved_reports = array_filter($reports, fn($r) => $r['verification_status'] === 'Verified');
$rejected_reports = array_filter($reports, fn($r) => $r['verification_status'] === 'Rejected');

$total_pending = count($pending_reports);
$total_approved = count($approved_reports);
$total_rejected = count($rejected_reports);
?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:24px">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
    <div style="font-size:20px;font-weight:700;color:var(--warn)"><?php echo $total_pending; ?></div>
    <div style="font-size:11px;color:var(--text3);margin-top:4px">Pending Review</div>
  </div>
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
    <div style="font-size:20px;font-weight:700;color:var(--accent)"><?php echo $total_approved; ?></div>
    <div style="font-size:11px;color:var(--text3);margin-top:4px">Approved</div>
  </div>
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
    <div style="font-size:20px;font-weight:700;color:var(--danger)"><?php echo $total_rejected; ?></div>
    <div style="font-size:11px;color:var(--text3);margin-top:4px">Rejected</div>
  </div>
</div>

<!-- Tabs -->
<div class="tabs">
  <button class="tab active" onclick="switchTab('pending')"><i class="fas fa-clock" style="margin-right:6px"></i>Pending (<?php echo $total_pending; ?>)</button>
  <button class="tab" onclick="switchTab('approved')"><i class="fas fa-check-circle" style="margin-right:6px"></i>Approved (<?php echo $total_approved; ?>)</button>
  <button class="tab" onclick="switchTab('rejected')"><i class="fas fa-times-circle" style="margin-right:6px"></i>Rejected (<?php echo $total_rejected; ?>)</button>
</div>

<!-- Pending Reports -->
<div id="pending" class="tab-content active">
  <?php if(empty($pending_reports)): ?>
  <div class="empty-state">
    <i class="fas fa-inbox empty-state-icon"></i>
    <p>No pending reports. All your reports have been reviewed.</p>
  </div>
  <?php else: ?>
  <?php foreach($pending_reports as $report): ?>
  <div class="report-card">
    <div class="report-header">
      <div class="report-info">
        <div class="report-title">Report #<?php echo $report['id']; ?></div>
        <div class="report-meta">
          <div class="report-meta-item"><i class="fas fa-calendar"></i><?php echo date('M d, Y', strtotime($report['created_at'])); ?></div>
          <div class="report-meta-item"><i class="fas fa-boxes"></i>Total Items: <strong><?php echo $report['total_items']; ?></strong></div>
          <div class="report-meta-item"><i class="fas fa-hourglass-half"></i>Submitted <?php echo date('h:i A', strtotime($report['created_at'])); ?></div>
        </div>
      </div>
      <span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>
    </div>
    
    <div class="report-details">
      <div class="report-details-row">
        <span class="report-details-label">Inventory Count Date</span>
        <span class="report-details-value"><?php echo date('M d, Y', strtotime($report['count_date'])); ?></span>
      </div>
      <div class="report-details-row">
        <span class="report-details-label">Report Status</span>
        <span class="report-details-value">Awaiting Technician Review</span>
      </div>
    </div>

    <div class="action-buttons">
      <a href="<?php echo APP_URL; ?>/views/processes/conduct_inventory.php" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i> View Inventory</a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Approved Reports -->
<div id="approved" class="tab-content">
  <?php if(empty($approved_reports)): ?>
  <div class="empty-state">
    <i class="fas fa-inbox empty-state-icon"></i>
    <p>No approved reports yet. Keep submitting quality inventory reports!</p>
  </div>
  <?php else: ?>
  <?php foreach($approved_reports as $report): ?>
  <div class="report-card" style="border-left:3px solid var(--accent)">
    <div class="report-header">
      <div class="report-info">
        <div class="report-title">Report #<?php echo $report['id']; ?></div>
        <div class="report-meta">
          <div class="report-meta-item"><i class="fas fa-calendar"></i><?php echo date('M d, Y', strtotime($report['created_at'])); ?></div>
          <div class="report-meta-item"><i class="fas fa-boxes"></i>Total Items: <strong><?php echo $report['total_items']; ?></strong></div>
          <div class="report-meta-item"><i class="fas fa-check"></i>Approved <?php echo date('M d, Y h:i A', strtotime($report['verified_at'])); ?></div>
        </div>
      </div>
      <span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Verified</span>
    </div>
    
    <div class="report-details">
      <div class="report-details-row">
        <span class="report-details-label">Inventory Count Date</span>
        <span class="report-details-value"><?php echo date('M d, Y', strtotime($report['count_date'])); ?></span>
      </div>
      <div class="report-details-row">
        <span class="report-details-label">Verified By</span>
        <span class="report-details-value">Pharmacy Technician</span>
      </div>
      <div class="report-details-row">
        <span class="report-details-label">Verification Date</span>
        <span class="report-details-value"><?php echo date('M d, Y h:i A', strtotime($report['verified_at'])); ?></span>
      </div>
    </div>

    <?php if(!empty($report['remarks'])): ?>
    <div class="remarks-box">
      <div class="remarks-label"><i class="fas fa-comment-dots"></i> Technician Comments</div>
      <div class="remarks-text"><?php echo nl2br(htmlspecialchars($report['remarks'])); ?></div>
    </div>
    <?php endif; ?>

    <div class="action-buttons">
      <a href="<?php echo APP_URL; ?>/views/processes/conduct_inventory.php" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i> View Inventory</a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Rejected Reports -->
<div id="rejected" class="tab-content">
  <?php if(empty($rejected_reports)): ?>
  <div class="empty-state">
    <i class="fas fa-inbox empty-state-icon"></i>
    <p>No rejected reports. Great work!</p>
  </div>
  <?php else: ?>
  <?php foreach($rejected_reports as $report): ?>
  <div class="report-card" style="border-left:3px solid var(--danger)">
    <div class="report-header">
      <div class="report-info">
        <div class="report-title">Report #<?php echo $report['id']; ?></div>
        <div class="report-meta">
          <div class="report-meta-item"><i class="fas fa-calendar"></i><?php echo date('M d, Y', strtotime($report['created_at'])); ?></div>
          <div class="report-meta-item"><i class="fas fa-boxes"></i>Total Items: <strong><?php echo $report['total_items']; ?></strong></div>
          <div class="report-meta-item"><i class="fas fa-times"></i>Rejected <?php echo date('M d, Y h:i A', strtotime($report['verified_at'])); ?></div>
        </div>
      </div>
      <span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Rejected</span>
    </div>
    
    <div class="report-details">
      <div class="report-details-row">
        <span class="report-details-label">Inventory Count Date</span>
        <span class="report-details-value"><?php echo date('M d, Y', strtotime($report['count_date'])); ?></span>
      </div>
      <div class="report-details-row">
        <span class="report-details-label">Rejected By</span>
        <span class="report-details-value">Pharmacy Technician</span>
      </div>
      <div class="report-details-row">
        <span class="report-details-label">Rejection Date</span>
        <span class="report-details-value"><?php echo date('M d, Y h:i A', strtotime($report['verified_at'])); ?></span>
      </div>
    </div>

    <?php if(!empty($report['remarks'])): ?>
    <div class="remarks-box">
      <div class="remarks-label"><i class="fas fa-exclamation-circle"></i> Rejection Reason</div>
      <div class="remarks-text"><?php echo nl2br(htmlspecialchars($report['remarks'])); ?></div>
    </div>
    <?php endif; ?>

    <!-- Edit Inventory Button -->
    <button type="button" class="btn btn-primary btn-sm" onclick="openEditModal(<?php echo $report['id']; ?>, <?php echo $report['inventory_id']; ?>)" style="margin-bottom:14px;width:100%">
      <i class="fas fa-edit"></i> Edit Inventory & Resubmit
    </button>

    <div class="action-buttons">
      <a href="<?php echo APP_URL; ?>/views/processes/conduct_inventory.php" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i> View Inventory</a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>

<!-- Edit Inventory Modal -->
<div id="editModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;overflow-y:auto;padding:20px;backdrop-filter:blur(4px)">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;max-width:1400px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,.5)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <h3 style="font-size:18px;font-weight:700;color:var(--text);margin:0">
        <i class="fas fa-edit" style="margin-right:8px;color:var(--accent)"></i>Edit Inventory Report
      </h3>
      <button type="button" onclick="closeEditModal()" style="background:none;border:none;font-size:24px;color:var(--text3);cursor:pointer;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Inventory Summary Header -->
    <div id="editModalHeader" style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:20px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
        <div>
          <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:8px">Inventory Summary Report</div>
          <div id="editModalMeta" style="font-size:12px;color:var(--text2);line-height:1.8"></div>
        </div>
        <div style="text-align:right">
          <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Total On-Hand Value</div>
          <div id="editModalTotalValue" style="font-size:22px;font-weight:700;color:var(--accent)">₱0.00</div>
          <div style="font-size:11px;color:var(--text3);margin-top:12px">
            <div style="margin-bottom:4px">Total Items: <strong id="editModalTotalItems" style="color:var(--text)">0</strong></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Editable Inventory Table -->
    <div style="overflow-x:auto;border:1px solid var(--border);border-radius:10px;margin-bottom:20px">
      <table style="width:100%;border-collapse:collapse;font-size:11px">
        <thead>
          <tr style="background:var(--surface2);border-bottom:2px solid var(--border)">
            <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">#</th>
            <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">DIN</th>
            <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Trade Name</th>
            <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Generic Name</th>
            <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Form</th>
            <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Mfr</th>
            <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Category</th>
            <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Re-Order</th>
            <th style="padding:10px;text-align:center;color:var(--accent);font-weight:700;text-transform:uppercase;letter-spacing:.05em">QTY <i class="fas fa-edit" style="font-size:9px;margin-left:4px"></i></th>
            <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Variance</th>
            <th style="padding:10px;text-align:right;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Cost Price</th>
            <th style="padding:10px;text-align:right;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Unit Price</th>
            <th style="padding:10px;text-align:right;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">On Hand Value</th>
          </tr>
        </thead>
        <tbody id="editTableBody">
          <!-- Rows will be populated here -->
        </tbody>
      </table>
    </div>

    <div style="display:flex;gap:12px;justify-content:flex-end">
      <button type="button" onclick="closeEditModal()" class="btn btn-secondary"><i class="fas fa-times"></i> Close</button>
      <button type="button" onclick="submitEditedReport()" class="btn btn-primary"><i class="fas fa-save"></i> Submit to Technician</button>
    </div>
  </div>
</div>

<script>
let currentReportId = null;
let currentInventoryId = null;
let costPrices = {};
let systemStock = {};

function openEditModal(reportId, inventoryId) {
  currentReportId = reportId;
  currentInventoryId = inventoryId;
  
  document.getElementById('editModal').style.display = 'flex';
  
  // Load inventory details
  fetch('<?php echo APP_URL; ?>/api/get_inventory_items.php?inventory_id=' + inventoryId)
    .then(response => response.json())
    .then(data => {
      if (data.success && data.items.length > 0) {
        // Set header info
        const inv = data.inventory;
        const date = new Date(inv.completed_at || inv.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        const conductor = inv.first_name + ' ' + inv.last_name;
        
        document.getElementById('editModalMeta').innerHTML = `
          <div><strong>Date:</strong> ${date}</div>
          <div><strong>Conducted by:</strong> ${conductor}</div>
          <div><strong>Count #:</strong> ${inv.id}</div>
        `;
        
        document.getElementById('editModalTotalValue').textContent = '₱' + parseFloat(data.totals.total_cost).toFixed(2);
        document.getElementById('editModalTotalItems').textContent = data.totals.total_items;
        
        // Populate table
        let html = '';
        let rowNum = 1;
        data.items.forEach(item => {
          costPrices[item.product_id] = parseFloat(item.cost_price);
          systemStock[item.product_id] = parseInt(item.current_stock);
          
          const variance = item.counted_qty - item.current_stock;
          const varianceClass = variance > 0 ? 'color:var(--accent)' : (variance < 0 ? 'color:var(--danger)' : 'color:var(--text3)');
          const onHandValue = (item.cost_price * item.counted_qty).toFixed(2);
          
          html += `
            <tr style="border-bottom:1px solid var(--border)">
              <td style="padding:10px;text-align:center;color:var(--text3)">${rowNum}</td>
              <td style="padding:10px;color:var(--accent);font-weight:600;font-family:monospace;font-size:10px">${item.product_code}</td>
              <td style="padding:10px;color:var(--text);font-weight:600">${item.product_name}</td>
              <td style="padding:10px;color:var(--text2)">${item.generic_name || '-'}</td>
              <td style="padding:10px;color:var(--text2)">${item.form || '-'}</td>
              <td style="padding:10px;text-align:center;color:var(--text2);font-size:10px">${item.manufacturer_name || '-'}</td>
              <td style="padding:10px;color:var(--text2)">${item.category || '-'}</td>
              <td style="padding:10px;text-align:center;color:var(--text3)">${item.reorder_level}</td>
              <td style="padding:10px;text-align:center">
                <input type="number" class="qty-input" data-product-id="${item.product_id}" data-cost="${item.cost_price}" value="${item.counted_qty}" min="0" onchange="updateRowValue(this)" style="width:60px;padding:6px;border:1px solid var(--border);border-radius:6px;background:var(--surface3);color:var(--accent);font-weight:600;text-align:center">
              </td>
              <td style="padding:10px;text-align:center;font-weight:700;${varianceClass}" id="variance-${item.product_id}">${variance > 0 ? '+' : ''}${variance}</td>
              <td style="padding:10px;text-align:right;color:var(--text2)">₱${parseFloat(item.cost_price).toFixed(4)}</td>
              <td style="padding:10px;text-align:right;color:var(--text2)">₱${parseFloat(item.unit_price).toFixed(4)}</td>
              <td style="padding:10px;text-align:right;color:var(--accent);font-weight:600" id="value-${item.product_id}">₱${onHandValue}</td>
            </tr>
          `;
          rowNum++;
        });
        
        document.getElementById('editTableBody').innerHTML = html;
      }
    })
    .catch(error => {
      console.error('Error loading inventory:', error);
      alert('Error loading inventory data');
    });
}

function updateRowValue(input) {
  const productId = input.dataset.productId;
  const cost = parseFloat(input.dataset.cost);
  const qty = parseInt(input.value) || 0;
  const systemQty = systemStock[productId] || 0;
  
  // Update variance
  const variance = qty - systemQty;
  const varianceEl = document.getElementById('variance-' + productId);
  if (varianceEl) {
    varianceEl.textContent = (variance > 0 ? '+' : '') + variance;
    varianceEl.style.color = variance > 0 ? 'var(--accent)' : (variance < 0 ? 'var(--danger)' : 'var(--text3)');
  }
  
  // Update on-hand value
  const valueEl = document.getElementById('value-' + productId);
  if (valueEl) {
    valueEl.textContent = '₱' + (qty * cost).toFixed(2);
  }
  
  // Update total
  updateTotalValue();
}

function updateTotalValue() {
  let total = 0;
  document.querySelectorAll('.qty-input').forEach(input => {
    const qty = parseInt(input.value) || 0;
    const cost = parseFloat(input.dataset.cost);
    total += qty * cost;
  });
  
  document.getElementById('editModalTotalValue').textContent = '₱' + total.toFixed(2);
  
  // Update total items
  let totalItems = 0;
  document.querySelectorAll('.qty-input').forEach(input => {
    totalItems += parseInt(input.value) || 0;
  });
  document.getElementById('editModalTotalItems').textContent = totalItems;
}

function submitEditedReport() {
  if (!currentReportId || !currentInventoryId) {
    alert('Error: Report data missing');
    return;
  }
  
  // Collect updated quantities
  const quantities = {};
  document.querySelectorAll('.qty-input').forEach(input => {
    const productId = input.dataset.productId;
    quantities[productId] = parseInt(input.value) || 0;
  });
  
  // Calculate total items
  let totalItems = 0;
  Object.values(quantities).forEach(qty => {
    totalItems += qty;
  });
  
  if (totalItems === 0) {
    alert('Total items must be greater than 0');
    return;
  }
  
  // Show confirmation
  if (!confirm('Are you sure you want to save these changes and resubmit the report?')) {
    return;
  }
  
  // Submit the form
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = '';
  
  const actionInput = document.createElement('input');
  actionInput.type = 'hidden';
  actionInput.name = 'action';
  actionInput.value = 'update_and_resubmit';
  
  const reportIdInput = document.createElement('input');
  reportIdInput.type = 'hidden';
  reportIdInput.name = 'report_id';
  reportIdInput.value = currentReportId;
  
  const inventoryIdInput = document.createElement('input');
  inventoryIdInput.type = 'hidden';
  inventoryIdInput.name = 'inventory_id';
  inventoryIdInput.value = currentInventoryId;
  
  const totalItemsInput = document.createElement('input');
  totalItemsInput.type = 'hidden';
  totalItemsInput.name = 'total_items';
  totalItemsInput.value = totalItems;
  
  const quantitiesInput = document.createElement('input');
  quantitiesInput.type = 'hidden';
  quantitiesInput.name = 'quantities';
  quantitiesInput.value = JSON.stringify(quantities);
  
  form.appendChild(actionInput);
  form.appendChild(reportIdInput);
  form.appendChild(inventoryIdInput);
  form.appendChild(totalItemsInput);
  form.appendChild(quantitiesInput);
  
  document.body.appendChild(form);
  form.submit();
}

function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
  currentReportId = null;
  currentInventoryId = null;
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeEditModal();
  }
});

function switchTab(tabName) {
  // Hide all tabs
  document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
  
  // Show selected tab
  document.getElementById(tabName).classList.add('active');
  event.target.classList.add('active');
}
</script>
