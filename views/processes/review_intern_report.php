<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');

$processModel = new ProcessModel($pdo);
$report_id = $_GET['id'] ?? null;
$error = '';
$message = '';

if (!$report_id) {
    $_SESSION['error'] = 'Report not found.';
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

// Get report details
try {
    $report = $processModel->getInventoryReportById($report_id);
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

// Get inventory items for this report - fetch CURRENT quantities from inventory_items table
try {
    $stmt = $pdo->prepare("
        SELECT 
            ii.id,
            ii.quantity,
            ii.batch_number,
            p.id as product_id,
            p.product_code,
            p.product_name,
            p.generic_name,
            p.form,
            p.pack_size,
            p.category,
            p.current_stock,
            p.reorder_level
        FROM inventory_items ii
        JOIN products p ON ii.product_id = p.id
        WHERE ii.inventory_id = ?
        ORDER BY p.category ASC, p.product_name ASC
    ");
    $stmt->execute([$report['inventory_id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $error = 'Error fetching inventory items: ' . $e->getMessage();
    $items = [];
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review_report') {
    $status = $_POST['status'] ?? null;
    $remarks = $_POST['remarks'] ?? '';

    if (!in_array($status, ['Verified', 'Rejected'])) {
        $error = 'Invalid status selected.';
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
.wrap{max-width:1000px;margin:0 auto;padding:28px 24px}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
@media(max-width:768px){.grid-2{grid-template-columns:1fr}}
.card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px}
.card-title{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin-bottom:14px;display:flex;align-items:center;gap:8px}
.card-title::before{content:'';width:3px;height:14px;background:var(--accent);border-radius:2px}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border)}
.info-row:last-child{border-bottom:none}
.info-label{font-size:12px;color:var(--text3);font-weight:600;text-transform:uppercase;letter-spacing:.05em}
.info-value{font-size:13px;font-weight:600;color:var(--text)}
.item-row{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;gap:12px}
.item-name{font-size:13px;font-weight:600;color:var(--text)}
.item-code{font-size:11px;color:var(--text3)}
.item-qty{font-size:14px;font-weight:700;color:var(--accent)}
.form-group{margin-bottom:18px}
.form-label{display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em}
.form-input,.form-select,.form-textarea{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-family:inherit;font-size:13px;transition:border-color .2s}
.form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,255,176,.1)}
.form-textarea{resize:vertical;min-height:80px}
.btn{display:inline-block;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer;border:none;transition:all .2s;text-align:center}
.btn-primary{background:var(--accent);color:#0a0c10}
.btn-primary:hover{background:var(--accent2);transform:translateY(-1px)}
.btn-danger{background:var(--danger);color:white}
.btn-danger:hover{background:#dc2626;transform:translateY(-1px)}
.btn-secondary{background:var(--surface);border:1px solid var(--border);color:var(--text)}
.btn-secondary:hover{border-color:var(--border2)}
.status-badge{display:inline-block;padding:6px 12px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.status-pending{background:rgba(245,158,11,.12);color:var(--warn)}
.status-approved{background:rgba(79,255,176,.12);color:var(--accent)}
.status-rejected{background:rgba(248,113,113,.12);color:var(--danger)}
.action-buttons{display:flex;gap:12px;margin-top:20px}
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

<?php if($error): ?>
<div class="alert alert-error" style="margin-bottom:20px"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Header -->
<div style="background:linear-gradient(135deg,rgba(249,115,22,.08),rgba(56,189,248,.08));border:1px solid rgba(249,115,22,.18);border-radius:12px;padding:20px 24px;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;gap:14px">
  <div style="display:flex;align-items:center;gap:14px">
    <div style="width:48px;height:48px;border-radius:10px;background:rgba(249,115,22,.15);display:flex;align-items:center;justify-content:center;color:#f97316;font-size:20px;flex-shrink:0">
      <i class="fas fa-chart-bar"></i>
    </div>
    <div>
      <div style="font-size:16px;font-weight:700;color:var(--text)">Review Inventory Report #<?php echo $report['id']; ?></div>
      <div style="font-size:12px;color:var(--text2);margin-top:2px">From <?php echo htmlspecialchars($report['first_name'].' '.$report['last_name']); ?></div>
    </div>
  </div>
  <span class="status-badge status-<?php echo strtolower($report['verification_status']); ?>">
    <i class="fas fa-<?php echo $report['verification_status'] === 'Pending' ? 'clock' : ($report['verification_status'] === 'Verified' ? 'check-circle' : 'times-circle'); ?>" style="margin-right:4px"></i>
    <?php echo $report['verification_status']; ?>
  </span>
</div>

<div class="grid-2">

  <!-- Report Details -->
  <div>
    <div class="card">
      <div class="card-title"><i class="fas fa-info-circle"></i> Report Information</div>
      <div class="info-row">
        <div><div class="info-label">Report ID</div></div>
        <div class="info-value">#<?php echo $report['id']; ?></div>
      </div>
      <div class="info-row">
        <div><div class="info-label">Submitted By</div></div>
        <div class="info-value"><?php echo htmlspecialchars($report['first_name'].' '.$report['last_name']); ?></div>
      </div>
      <div class="info-row">
        <div><div class="info-label">Email</div></div>
        <div class="info-value" style="font-size:12px"><?php echo htmlspecialchars($report['email']); ?></div>
      </div>
      <div class="info-row">
        <div><div class="info-label">Submitted Date</div></div>
        <div class="info-value"><?php echo date('M d, Y h:i A', strtotime($report['created_at'])); ?></div>
      </div>
      <div class="info-row">
        <div><div class="info-label">Total Items</div></div>
        <div class="info-value" style="font-size:16px;color:var(--accent)"><?php echo $report['total_items']; ?></div>
      </div>
      <div class="info-row">
        <div><div class="info-label">Inventory Count Date</div></div>
        <div class="info-value"><?php echo date('M d, Y h:i A', strtotime($report['count_date'])); ?></div>
      </div>
    </div>

    <!-- Inventory Items -->
    <div class="card" style="margin-top:20px;overflow-x:auto">
      <div class="card-title"><i class="fas fa-chart-bar"></i> Inventory Summary</div>
      
      <?php if(!empty($items)): ?>
        <div style="margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border)">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <div>
              <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Date</div>
              <div style="font-size:13px;color:var(--text);margin-top:4px"><?php echo date('M d, Y', strtotime($report['count_date'])); ?></div>
            </div>
            <div>
              <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Conducted By</div>
              <div style="font-size:13px;color:var(--text);margin-top:4px"><?php echo htmlspecialchars($report['first_name'].' '.$report['last_name']); ?></div>
            </div>
            <div>
              <div style="font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Total Products</div>
              <div style="font-size:13px;color:var(--accent);margin-top:4px;font-weight:700"><?php echo count($items); ?></div>
            </div>
          </div>
        </div>
        
        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;font-size:11px">
            <thead>
              <tr style="border-bottom:2px solid var(--border)">
                <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">#</th>
                <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Product Code</th>
                <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Trade Name</th>
                <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Generic Name</th>
                <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Form</th>
                <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Pack Size</th>
                <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Category</th>
                <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">On Hand</th>
                <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Re-Order</th>
                <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Counted</th>
                <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Variance</th>
              </tr>
            </thead>
            <tbody>
              <?php 
                $rowNum = 1;
                foreach($items as $item): 
                  $variance = ($item['quantity'] ?? 0) - ($item['current_stock'] ?? 0);
                  $varianceClass = $variance > 0 ? 'color:var(--accent)' : ($variance < 0 ? 'color:var(--danger)' : 'color:var(--text3)');
                  $isLowStock = ($item['current_stock'] ?? 0) <= ($item['reorder_level'] ?? 0);
              ?>
              <tr style="border-bottom:1px solid var(--border);<?php echo $isLowStock ? 'background:rgba(248,113,113,.05)' : ''; ?>">
                <td style="padding:10px;color:var(--text3)"><?php echo $rowNum; ?></td>
                <td style="padding:10px;color:var(--accent);font-weight:600"><?php echo htmlspecialchars($item['product_code'] ?? '-'); ?></td>
                <td style="padding:10px;color:var(--text);font-weight:600"><?php echo htmlspecialchars($item['product_name'] ?? '-'); ?></td>
                <td style="padding:10px;color:var(--text2)"><?php echo htmlspecialchars($item['generic_name'] ?? '-'); ?></td>
                <td style="padding:10px;color:var(--text2)"><?php echo htmlspecialchars($item['form'] ?? '-'); ?></td>
                <td style="padding:10px;text-align:center;color:var(--text2)"><?php echo $item['pack_size'] ?? '-'; ?></td>
                <td style="padding:10px;color:var(--text2)"><?php echo htmlspecialchars($item['category'] ?? '-'); ?></td>
                <td style="padding:10px;text-align:center;color:<?php echo $isLowStock ? 'var(--danger)' : 'var(--text)'; ?>;font-weight:<?php echo $isLowStock ? '700' : '400'; ?>">
                  <?php echo $item['current_stock'] ?? 0; ?>
                  <?php if($isLowStock): ?>
                  <i class="fas fa-exclamation-triangle" style="margin-left:6px;font-size:10px;color:var(--danger)" title="Low stock - below reorder level"></i>
                  <?php endif; ?>
                </td>
                <td style="padding:10px;text-align:center;color:var(--text3)"><?php echo $item['reorder_level'] ?? 0; ?></td>
                <td style="padding:10px;text-align:center;color:var(--accent);font-weight:700"><?php echo $item['quantity'] ?? 0; ?></td>
                <td style="padding:10px;text-align:center;font-weight:700;<?php echo $varianceClass; ?>"><?php echo $variance > 0 ? '+' : ''; ?><?php echo $variance; ?></td>
              </tr>
              <?php $rowNum++; endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
      <div style="text-align:center;padding:20px;color:var(--text3)">
        <i class="fas fa-inbox" style="font-size:24px;margin-bottom:8px;display:block"></i>
        No items recorded
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Review Form -->
  <div>
    <div class="card">
      <div class="card-title"><i class="fas fa-check-double"></i> Review & Approve</div>

      <?php if($report['verification_status'] === 'Pending'): ?>
      <form method="POST" action="">
        <input type="hidden" name="action" value="review_report">

        <div class="form-group">
          <label class="form-label"><i class="fas fa-check-circle" style="margin-right:6px"></i>Decision</label>
          <select name="status" class="form-select" required>
            <option value="">-- Select a decision --</option>
            <option value="Verified">✓ Verify Report</option>
            <option value="Rejected">✗ Reject Report</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label"><i class="fas fa-comment-dots" style="margin-right:6px"></i>Remarks (Optional)</label>
          <textarea name="remarks" class="form-textarea" placeholder="Add any comments or reasons for rejection..."></textarea>
        </div>

        <div class="action-buttons">
          <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary" style="flex:1"><i class="fas fa-times"></i> Cancel</a>
          <button type="submit" class="btn btn-primary" style="flex:1"><i class="fas fa-paper-plane"></i> Submit Review</button>
        </div>
      </form>
      <?php else: ?>
      <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:16px;margin-bottom:16px">
        <div style="font-size:12px;color:var(--text3);margin-bottom:8px">Status</div>
        <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:12px">
          <?php echo $report['verification_status'] === 'Verified' ? '✓ Verified' : ($report['verification_status'] === 'Rejected' ? '✗ Rejected' : '⏳ Pending'); ?>
        </div>
        <div style="font-size:12px;color:var(--text2)">
          <strong>Reviewed by:</strong> <?php echo htmlspecialchars($report['verified_by'] ? 'Technician' : 'N/A'); ?><br>
          <strong>Reviewed at:</strong> <?php echo $report['verified_at'] ? date('M d, Y h:i A', strtotime($report['verified_at'])) : 'N/A'; ?>
        </div>
        <?php if(!empty($report['remarks'])): ?>
        <div style="margin-top:12px;padding:12px;background:var(--surface);border-radius:6px;border-left:2px solid var(--accent2)">
          <div style="font-size:11px;color:var(--text3);margin-bottom:4px">Remarks:</div>
          <div style="font-size:12px;color:var(--text2)"><?php echo nl2br(htmlspecialchars($report['remarks'])); ?></div>
        </div>
        <?php endif; ?>
      </div>
      <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary" style="width:100%"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
      <?php endif; ?>
    </div>

    <!-- Report Details -->
    <?php if(!empty($report['report_details'])): ?>
    <div class="card" style="margin-top:20px">
      <div class="card-title"><i class="fas fa-file-alt"></i> Report Details</div>
      <div style="font-size:13px;color:var(--text2);line-height:1.6;padding:12px;background:var(--surface2);border-radius:8px;border-left:2px solid var(--accent2)">
        <?php echo nl2br(htmlspecialchars($report['report_details'])); ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>
</body>
</html>
