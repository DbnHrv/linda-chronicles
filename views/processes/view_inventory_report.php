<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');

$processModel = new ProcessModel($pdo);
$inventory_id = $_GET['inventory_id'] ?? null;
$error = '';

if (!$inventory_id || !is_numeric($inventory_id)) {
    $_SESSION['error'] = 'Invalid inventory ID.';
    header('Location: ' . APP_URL . '/views/processes/conduct_inventory.php');
    exit;
}

// Get inventory details
try {
    $stmt = $pdo->prepare("
        SELECT ic.*, u.first_name, u.last_name
        FROM inventory_counts ic
        JOIN users u ON ic.conducted_by = u.id
        WHERE ic.id = ? AND ic.conducted_by = ?
    ");
    $stmt->execute([$inventory_id, $_SESSION['user_id']]);
    $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$inventory) {
        $_SESSION['error'] = 'Inventory not found or access denied.';
        header('Location: ' . APP_URL . '/views/processes/conduct_inventory.php');
        exit;
    }
} catch(Exception $e) {
    $_SESSION['error'] = 'Error loading inventory: ' . $e->getMessage();
    header('Location: ' . APP_URL . '/views/processes/conduct_inventory.php');
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
    $stmt->execute([$inventory_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $items = [];
}

// Check if report already exists
try {
    $stmt = $pdo->prepare("
        SELECT * FROM inventory_reports
        WHERE inventory_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$inventory_id]);
    $existing_report = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $existing_report = null;
}

// Handle report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_report') {
    $total_items = array_sum(array_column($items, 'counted_qty'));
    $details = $_POST['details'] ?? '';
    
    try {
        if ($existing_report) {
            // Update existing report
            $stmt = $pdo->prepare("
                UPDATE inventory_reports
                SET total_items = ?, report_details = ?, verification_status = 'Pending'
                WHERE id = ?
            ");
            $stmt->execute([$total_items, $details, $existing_report['id']]);
            $_SESSION['success'] = 'Report updated and resubmitted for review.';
        } else {
            // Create new report
            $report_id = $processModel->submitInventoryReport($inventory_id, $total_items, $details);
            $_SESSION['success'] = 'Inventory report submitted successfully. Status: Pending Review';
        }
        header('Location: ' . APP_URL . '/views/processes/conduct_inventory.php');
        exit;
    } catch(Exception $e) {
        $error = 'Error submitting report: ' . $e->getMessage();
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
<title>Inventory Report — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
.wrap{max-width:1200px;margin:0 auto;padding:28px 24px}
.form-group{margin-bottom:20px}
.form-label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em}
.form-textarea{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-family:inherit;font-size:13px;resize:vertical;min-height:100px;transition:border-color .2s}
.form-textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,255,176,.1)}
.btn{display:inline-block;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer;border:none;transition:all .2s;text-align:center}
.btn-primary{background:var(--accent);color:#0a0c10}
.btn-primary:hover{background:var(--accent2);transform:translateY(-1px)}
.btn-secondary{background:var(--surface);border:1px solid var(--border);color:var(--text)}
.btn-secondary:hover{border-color:var(--border2)}
.section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin:28px 0 14px;display:flex;align-items:center;gap:8px}
.section-title::after{content:'';flex:1;height:1px;background:var(--border)}
.status-badge{display:inline-block;padding:6px 12px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.status-pending{background:rgba(245,158,11,.12);color:var(--warn)}
.status-approved{background:rgba(79,255,176,.12);color:var(--accent)}
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
      <a href="<?php echo APP_URL; ?>/views/processes/conduct_inventory.php" class="btn-nav"><i class="fas fa-arrow-left"></i> Back to Inventory</a>
      <a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </div>
</nav>

<div class="wrap">

<?php if(isset($_SESSION['success'])): ?>
<div class="alert alert-success" style="margin-bottom:20px"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if($error): ?>
<div class="alert alert-error" style="margin-bottom:20px"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Header -->
<div class="report-header">
  <div class="report-info">
    <div class="report-title">Inventory Report #<?php echo $inventory_id; ?></div>
    <div class="report-meta">
      <i class="fas fa-calendar" style="margin-right:4px"></i>
      Date: <?php echo date('M d, Y', strtotime($inventory['completed_at'] ?? $inventory['created_at'])); ?>
      &nbsp;·&nbsp;
      <i class="fas fa-user" style="margin-right:4px"></i>
      Conducted by: <?php echo htmlspecialchars($inventory['first_name'].' '.$inventory['last_name']); ?>
    </div>
  </div>
  <div style="text-align:right">
    <div style="font-size:11px;color:var(--text3)">Total On-Hand Value</div>
    <div style="font-size:20px;font-weight:700;color:var(--accent);margin-top:4px">₱<?php echo number_format($total_cost, 2); ?></div>
  </div>
</div>

<!-- Summary -->
<div class="summary-box">
  <div class="summary-item">
    <div class="summary-label">Total Products</div>
    <div class="summary-value"><?php echo count($items); ?></div>
  </div>
  <div class="summary-item">
    <div class="summary-label">Total Items</div>
    <div class="summary-value"><?php echo $total_qty; ?></div>
  </div>
  <div class="summary-item">
    <div class="summary-label">Total Cost Value</div>
    <div class="summary-value">₱<?php echo number_format($total_cost, 2); ?></div>
  </div>
  <?php if($existing_report): ?>
  <div class="summary-item">
    <div class="summary-label">Report Status</div>
    <div style="margin-top:6px">
      <span class="status-badge status-<?php echo strtolower($existing_report['verification_status']); ?>">
        <?php echo $existing_report['verification_status']; ?>
      </span>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Inventory Table -->
<div class="section-title">Product Inventory Details</div>
<div class="table-wrap">
  <table class="inv-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Product Code</th>
        <th>Trade Name</th>
        <th>Generic Name</th>
        <th>Form</th>
        <th class="r">Pack Size</th>
        <th>Manufacturer</th>
        <th>Category</th>
        <th class="r">On Hand</th>
        <th class="r">Re-Order</th>
        <th class="r">Counted</th>
        <th class="r">Variance</th>
        <th class="r">Cost Price</th>
      </tr>
    </thead>
    <tbody>
      <?php 
        $rowNum = 1;
        foreach($items as $item): 
          $variance = $item['counted_qty'] - $item['current_stock'];
          $varianceClass = $variance > 0 ? 'color:var(--accent)' : ($variance < 0 ? 'color:var(--danger)' : 'color:var(--text3)');
      ?>
      <tr>
        <td style="color:var(--text3)"><?php echo $rowNum; ?></td>
        <td style="color:var(--accent);font-weight:600"><?php echo htmlspecialchars($item['product_code']); ?></td>
        <td style="font-weight:600"><?php echo htmlspecialchars($item['product_name']); ?></td>
        <td style="color:var(--text2)"><?php echo htmlspecialchars($item['generic_name'] ?? '-'); ?></td>
        <td style="color:var(--text2)"><?php echo htmlspecialchars($item['form'] ?? '-'); ?></td>
        <td class="r" style="color:var(--text2)"><?php echo $item['pack_size']; ?></td>
        <td style="color:var(--text2);font-size:11px"><?php echo htmlspecialchars($item['manufacturer_name'] ?? '-'); ?></td>
        <td style="color:var(--text2)"><?php echo htmlspecialchars($item['category'] ?? '-'); ?></td>
        <td class="r"><?php echo $item['current_stock']; ?></td>
        <td class="r" style="color:var(--text3)"><?php echo $item['reorder_level']; ?></td>
        <td class="r" style="font-weight:700;color:var(--accent)"><?php echo $item['counted_qty']; ?></td>
        <td class="r" style="font-weight:700;<?php echo $varianceClass; ?>"><?php echo $variance > 0 ? '+' : ''; ?><?php echo $variance; ?></td>
        <td class="r" style="color:var(--text2)">₱<?php echo number_format($item['cost_price'], 4); ?></td>
      </tr>
      <?php $rowNum++; endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="10" style="text-align:right">Total On-Hand Value:</td>
        <td class="r" style="font-size:14px;color:var(--accent)">₱<?php echo number_format($total_cost, 2); ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>
</div>

<!-- Report Submission Form -->
<?php if(!$existing_report || $existing_report['verification_status'] === 'Rejected'): ?>
<form method="POST" action="">
  <input type="hidden" name="action" value="submit_report">
  
  <div class="form-group" style="margin-top:28px">
    <label class="form-label"><i class="fas fa-file-alt" style="margin-right:6px"></i>Report Details (Optional)</label>
    <textarea name="details" placeholder="Add any notes or observations about this inventory count..."></textarea>
  </div>

  <div style="display:flex;gap:12px;justify-content:flex-end">
    <a href="<?php echo APP_URL; ?>/views/processes/conduct_inventory.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Report</button>
  </div>
</form>
<?php else: ?>
<div style="margin-top:28px;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:20px;text-align:center">
  <i class="fas fa-check-circle" style="font-size:32px;color:var(--accent);margin-bottom:12px;display:block"></i>
  <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:6px">Report Already Submitted</div>
  <div style="font-size:12px;color:var(--text2);margin-bottom:16px">This report is currently under review by the pharmacy technician.</div>
  <div style="display:inline-block;padding:8px 12px;background:rgba(79,255,176,.12);border-radius:6px;color:var(--accent);font-size:12px;font-weight:600">
    Status: <?php echo $existing_report['verification_status']; ?>
  </div>
  <?php if(!empty($existing_report['remarks'])): ?>
  <div style="margin-top:16px;padding:12px;background:var(--surface2);border-radius:6px;text-align:left">
    <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Feedback from Technician:</div>
    <div style="font-size:12px;color:var(--text2)"><?php echo nl2br(htmlspecialchars($existing_report['remarks'])); ?></div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body>
</html>
