<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(4); // Pharmacy Technician

$processModel = new ProcessModel($pdo);
$report_id = $_GET['report_id'] ?? null;
$error = '';
$message = '';

if (!$report_id || !is_numeric($report_id)) {
    $_SESSION['error'] = 'Invalid report ID.';
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

// Get report details
try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.first_name, u.last_name, u.email, ic.id as inventory_id, ic.created_at as count_date, ic.completed_at
        FROM inventory_reports r
        JOIN users u ON r.created_by = u.id
        JOIN inventory_counts ic ON r.inventory_id = ic.id
        WHERE r.id = ?
    ");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        $_SESSION['error'] = 'Report not found.';
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
} catch(Exception $e) {
    $_SESSION['error'] = 'Error loading report: ' . $e->getMessage();
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

// Get inventory items with product details
try {
    $stmt = $pdo->prepare("
        SELECT 
            ii.id,
            ii.quantity as counted_qty,
            ii.batch_number,
            p.product_code,
            p.product_name,
            p.generic_name,
            p.description,
            p.form,
            p.pack_size,
            p.category,
            m.manufacturer_name,
            p.unit_price,
            p.cost_price,
            p.current_stock,
            p.reorder_level
        FROM inventory_items ii
        JOIN products p ON ii.product_id = p.id
        LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
        WHERE ii.inventory_id = ?
        ORDER BY p.product_code ASC
    ");
    $stmt->execute([$report['inventory_id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$items) {
        $items = [];
    }
} catch(Exception $e) {
    $error = 'Error loading inventory items: ' . $e->getMessage();
    $items = [];
}

// Handle report review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review_report') {
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    if (!in_array($status, ['Verified', 'Rejected'])) {
        $error = 'Invalid status.';
    } else {
        try {
            $processModel->reviewInventoryReport($report_id, $status, $remarks);
            $_SESSION['success'] = 'Report ' . strtolower($status) . ' successfully.';
            header('Location: ' . APP_URL . '/dashboard.php');
            exit;
        } catch(Exception $e) {
            $error = 'Error reviewing report: ' . $e->getMessage();
        }
    }
}

// Calculate totals
$total_qty = 0;
$total_cost = 0;
foreach ($items as $item) {
    $total_qty += $item['counted_qty'];
    $total_cost += ($item['cost_price'] * $item['counted_qty']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Review Inventory Report — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
.wrap{max-width:1400px;margin:0 auto;padding:28px 24px}
.form-group{margin-bottom:20px}
.form-label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em}
.form-textarea{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-family:inherit;font-size:13px;resize:vertical;min-height:100px;transition:border-color .2s}
.form-textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,255,176,.1)}
.btn{display:inline-block;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer;border:none;transition:all .2s;text-align:center}
.btn-primary{background:var(--accent);color:#0a0c10}
.btn-primary:hover{background:var(--accent2);transform:translateY(-1px)}
.btn-secondary{background:var(--surface);border:1px solid var(--border);color:var(--text)}
.btn-secondary:hover{border-color:var(--border2)}
.btn-danger{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);color:var(--danger)}
.btn-danger:hover{background:rgba(248,113,113,.2)}
.section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin:28px 0 14px;display:flex;align-items:center;gap:8px}
.section-title::after{content:'';flex:1;height:1px;background:var(--border)}
.status-badge{display:inline-block;padding:6px 12px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.status-pending{background:rgba(245,158,11,.12);color:var(--warn)}
.status-verified{background:rgba(79,255,176,.12);color:var(--accent)}
.status-rejected{background:rgba(248,113,113,.12);color:var(--danger)}
.report-header{background:linear-gradient(135deg,rgba(249,115,22,.08),rgba(56,189,248,.08));border:1px solid rgba(249,115,22,.18);border-radius:12px;padding:20px 24px;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;gap:14px}
.report-info{flex:1}
.report-title{font-size:16px;font-weight:700;color:var(--text)}
.report-meta{font-size:12px;color:var(--text2);margin-top:6px}
.table-wrap{overflow-x:auto;border:1px solid var(--border);border-radius:10px}
.inv-table{width:100%;border-collapse:collapse;font-size:12px}
.inv-table thead{background:var(--surface2)}
.inv-table th{padding:12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text3);border-bottom:1px solid var(--border)}
.inv-table th.r{text-align:right}
.inv-table td{padding:11px 12px;border-bottom:1px solid var(--border);color:var(--text)}
.inv-table td.r{text-align:right}
.inv-table tbody tr:hover{background:var(--surface2)}
.inv-table tfoot td{padding:12px;background:var(--surface2);font-weight:700;border-top:2px solid var(--border2)}
.summary-box{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
.summary-item{text-align:center}
.summary-label{font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px}
.summary-value{font-size:18px;font-weight:700;color:var(--accent)}
</style>
</head>
<body>
<nav class="navbar">
  <div class="navbar-content">
    <h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
    <div class="navbar-right">
      <span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
      <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
      <a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </div>
</nav>

<div class="process-wrapper" style="max-width:1400px">
<h1><i class="fas fa-file-check" style="color:var(--accent);margin-right:10px"></i>Review Inventory Report</h1>
<p class="subtitle">Review and approve the inventory summary report submitted by the intern</p>

<?php if(isset($_SESSION['success'])): ?>
<div class="alert alert-success" style="margin-bottom:20px"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if($error): ?>
<div class="alert alert-error" style="margin-bottom:20px"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Main Content Grid -->
<div style="display:grid;grid-template-columns:1fr 350px;gap:20px;margin-bottom:20px">

<!-- Left Column: Report Information & Details -->
<div>

<!-- Report Header Card -->
<div style="background:linear-gradient(135deg,rgba(249,115,22,.08),rgba(56,189,248,.08));border:1px solid rgba(249,115,22,.18);border-radius:12px;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between">
  <div>
    <div style="font-size:16px;font-weight:700;color:var(--text)">Inventory Report #<?php echo $report['id']; ?></div>
    <div style="font-size:12px;color:var(--text2);margin-top:6px">
      <i class="fas fa-user" style="margin-right:4px"></i>
      From: <?php echo htmlspecialchars($report['first_name'].' '.$report['last_name']); ?>
    </div>
  </div>
  <div style="text-align:right">
    <span class="status-badge status-<?php echo strtolower($report['verification_status']); ?>">
      <?php echo $report['verification_status']; ?>
    </span>
  </div>
</div>

<!-- Report Information Section -->
<div class="content-section" style="margin-bottom:20px">
<h2 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text3);margin-bottom:14px"><i class="fas fa-info-circle" style="margin-right:6px"></i>Report Information</h2>

<div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:12px">
    <div>
      <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Report ID</div>
      <div style="font-size:14px;font-weight:600;color:var(--text)">#<?php echo $report['id']; ?></div>
    </div>
    <div>
      <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Submitted By</div>
      <div style="font-size:14px;font-weight:600;color:var(--text)"><?php echo htmlspecialchars($report['first_name'].' '.$report['last_name']); ?></div>
    </div>
  </div>
  
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:12px">
    <div>
      <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Email</div>
      <div style="font-size:12px;color:var(--text2)"><?php echo htmlspecialchars($report['email']); ?></div>
    </div>
    <div>
      <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Submitted Date</div>
      <div style="font-size:12px;color:var(--text2)"><?php echo date('M d, Y h:i A', strtotime($report['created_at'])); ?></div>
    </div>
  </div>
  
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div>
      <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Inventory Date</div>
      <div style="font-size:12px;color:var(--text2)"><?php echo date('M d, Y h:i A', strtotime($report['completed_at'] ?? $report['count_date'])); ?></div>
    </div>
    <div>
      <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Inventory Count #</div>
      <div style="font-size:12px;color:var(--text2)">#<?php echo $report['inventory_id']; ?></div>
    </div>
  </div>
</div>
</div>

<!-- Inventory Summary Section -->
<div class="content-section" style="margin-bottom:20px">
<h2 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text3);margin-bottom:14px"><i class="fas fa-chart-bar" style="margin-right:6px"></i>Inventory Summary</h2>

<div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px;display:grid;grid-template-columns:repeat(2,1fr);gap:12px">
  <div style="text-align:center;padding:12px;background:var(--surface2);border-radius:8px">
    <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Total Products</div>
    <div style="font-size:20px;font-weight:700;color:var(--accent)"><?php echo count($items); ?></div>
  </div>
  <div style="text-align:center;padding:12px;background:var(--surface2);border-radius:8px">
    <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Total Items</div>
    <div style="font-size:20px;font-weight:700;color:var(--accent)"><?php echo $total_qty; ?></div>
  </div>
  <div style="text-align:center;padding:12px;background:var(--surface2);border-radius:8px">
    <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Total Cost Value</div>
    <div style="font-size:18px;font-weight:700;color:var(--accent)">₱<?php echo number_format($total_cost, 2); ?></div>
  </div>
  <div style="text-align:center;padding:12px;background:var(--surface2);border-radius:8px">
    <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Status</div>
    <span class="status-badge status-<?php echo strtolower($report['verification_status']); ?>" style="display:inline-block;margin-top:4px">
      <?php echo $report['verification_status']; ?>
    </span>
  </div>
</div>
</div>

<!-- Inventory Details Table -->
<div class="content-section">
<h2 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text3);margin-bottom:14px"><i class="fas fa-table" style="margin-right:6px"></i>Product Inventory Details</h2>

<div style="overflow-x:auto;border:1px solid var(--border);border-radius:10px">
  <table style="width:100%;border-collapse:collapse;font-size:11px">
    <thead>
      <tr style="background:var(--surface2);border-bottom:2px solid var(--border)">
        <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">#</th>
        <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Product Code</th>
        <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Trade Name</th>
        <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Generic Name</th>
        <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Form</th>
        <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Mfr</th>
        <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Category</th>
        <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">On Hand</th>
        <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Re-Order</th>
        <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Qty</th>
        <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Variance</th>
        <th style="padding:10px;text-align:right;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Cost Price</th>
        <th style="padding:10px;text-align:right;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">On Hand Value</th>
      </tr>
    </thead>
    <tbody>
      <?php 
        $rowNum = 1;
        if (!empty($items)) {
          foreach($items as $item): 
            $variance = (isset($item['counted_qty']) && isset($item['current_stock'])) ? ($item['counted_qty'] - $item['current_stock']) : 0;
            $varianceClass = $variance > 0 ? 'color:var(--accent)' : ($variance < 0 ? 'color:var(--danger)' : 'color:var(--text3)');
            $onHandValue = (isset($item['cost_price']) && isset($item['counted_qty'])) ? ($item['cost_price'] * $item['counted_qty']) : 0;
      ?>
      <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:10px;text-align:center;color:var(--text3)"><?php echo $rowNum; ?></td>
        <td style="padding:10px;color:var(--accent);font-weight:600;font-family:monospace"><?php echo htmlspecialchars($item['product_code'] ?? '-'); ?></td>
        <td style="padding:10px;color:var(--text);font-weight:600"><?php echo htmlspecialchars($item['product_name'] ?? '-'); ?></td>
        <td style="padding:10px;color:var(--text2)"><?php echo htmlspecialchars($item['generic_name'] ?? '-'); ?></td>
        <td style="padding:10px;color:var(--text2)"><?php echo htmlspecialchars($item['form'] ?? '-'); ?></td>
        <td style="padding:10px;text-align:center;color:var(--text2);font-size:10px"><?php echo htmlspecialchars($item['manufacturer_name'] ?? '-'); ?></td>
        <td style="padding:10px;text-align:center;color:var(--text2)"><?php echo htmlspecialchars($item['category'] ?? '-'); ?></td>
        <td style="padding:10px;text-align:center;color:var(--text)"><?php echo $item['current_stock'] ?? 0; ?></td>
        <td style="padding:10px;text-align:center;color:var(--text3)"><?php echo $item['reorder_level'] ?? 0; ?></td>
        <td style="padding:10px;text-align:center;color:var(--accent);font-weight:700"><?php echo $item['counted_qty'] ?? 0; ?></td>
        <td style="padding:10px;text-align:center;font-weight:700;<?php echo $varianceClass; ?>"><?php echo $variance > 0 ? '+' : ''; ?><?php echo $variance; ?></td>
        <td style="padding:10px;text-align:right;color:var(--text2)">₱<?php echo number_format($item['cost_price'] ?? 0, 4); ?></td>
        <td style="padding:10px;text-align:right;color:var(--accent);font-weight:600">₱<?php echo number_format($onHandValue, 2); ?></td>
      </tr>
      <?php $rowNum++; endforeach; 
        } else {
      ?>
      <tr>
        <td colspan="13" style="padding:20px;text-align:center;color:var(--text3)"><i class="fas fa-inbox"></i> No inventory items found</td>
      </tr>
      <?php } ?>
    </tbody>
    <tfoot>
      <tr style="background:var(--surface2);border-top:2px solid var(--border)">
        <td colspan="10" style="padding:10px;text-align:right;font-weight:700;color:var(--text)">Total On-Hand Value:</td>
        <td style="padding:10px;text-align:right;font-size:13px;font-weight:700;color:var(--accent)">₱<?php echo number_format($total_cost, 2); ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>
</div>
</div>

</div>

<!-- Right Column: Review & Approve Section -->
<div>

<!-- Review & Approve Card -->
<div class="content-section" style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px;position:sticky;top:20px">
<h2 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text3);margin-bottom:14px"><i class="fas fa-check-circle" style="margin-right:6px"></i>Review & Approve</h2>

<?php if($report['verification_status'] === 'Pending'): ?>
<form method="POST" action="">
  <input type="hidden" name="action" value="review_report">
  
  <div class="form-group" style="margin-bottom:16px">
    <label class="form-label" style="font-size:11px">Decision</label>
    <select name="status" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-size:12px;cursor:pointer" required>
      <option value="">-- Select a decision --</option>
      <option value="Verified">✓ Approve Report</option>
      <option value="Rejected">✗ Reject Report</option>
    </select>
  </div>

  <div class="form-group" style="margin-bottom:16px">
    <label class="form-label" style="font-size:11px">Remarks (Optional)</label>
    <textarea name="remarks" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-family:inherit;font-size:12px;resize:vertical;min-height:80px" placeholder="Add comments or observations..."></textarea>
  </div>

  <div style="display:flex;gap:8px;flex-direction:column">
    <button type="submit" class="btn btn-primary" style="width:100%;padding:10px;font-size:12px"><i class="fas fa-check"></i> Submit Review</button>
    <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary" style="width:100%;padding:10px;font-size:12px;text-align:center"><i class="fas fa-times"></i> Cancel</a>
  </div>
</form>
<?php else: ?>
<div style="text-align:center;padding:20px">
  <i class="fas fa-check-circle" style="font-size:32px;color:var(--accent);margin-bottom:12px;display:block"></i>
  <div style="font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px">Already Reviewed</div>
  <div style="font-size:11px;color:var(--text2);margin-bottom:12px">This report has been reviewed.</div>
  <span class="status-badge status-<?php echo strtolower($report['verification_status']); ?>">
    <?php echo $report['verification_status']; ?>
  </span>
  <?php if(!empty($report['remarks'])): ?>
  <div style="margin-top:12px;padding:10px;background:var(--surface3);border-radius:6px;text-align:left">
    <div style="font-size:10px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Remarks:</div>
    <div style="font-size:11px;color:var(--text2)"><?php echo nl2br(htmlspecialchars($report['remarks'])); ?></div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
</div>

</div>

</div>

</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body>
</html>
