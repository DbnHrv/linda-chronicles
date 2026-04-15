<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');

$processModel = new ProcessModel($pdo);
$uid = $_SESSION['user_id'];
$message = '';
$error = '';

// Get completed inventory counts for this intern
try {
    $stmt = $pdo->prepare("
        SELECT ic.*, COUNT(ii.id) as item_count
        FROM inventory_counts ic
        LEFT JOIN inventory_items ii ON ic.id = ii.inventory_id
        WHERE ic.conducted_by = ? AND ic.status = 'Completed'
        GROUP BY ic.id
        ORDER BY ic.completed_at DESC
    ");
    $stmt->execute([$uid]);
    $completed_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $completed_counts = [];
}

// Get existing reports
try {
    $my_reports = $processModel->getInventoryReportsByIntern($uid);
} catch(Exception $e) {
    $my_reports = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'submit_report') {
        $inventory_id = $_POST['inventory_id'] ?? null;
        $total_items = $_POST['total_items'] ?? 0;
        $details = $_POST['details'] ?? '';

        if (!$inventory_id) {
            $error = 'Please select an inventory count.';
        } elseif ($total_items <= 0) {
            $error = 'Total items must be greater than 0.';
        } else {
            try {
                // Check if there's already a rejected report for this inventory
                $stmt = $pdo->prepare("
                    SELECT id FROM inventory_reports 
                    WHERE inventory_id = ? AND created_by = ? AND verification_status = 'Rejected'
                    LIMIT 1
                ");
                $stmt->execute([$inventory_id, $uid]);
                $existing_report = $stmt->fetch();
                
                if ($existing_report) {
                    // Update the existing rejected report
                    $report_id = $existing_report['id'];
                    $stmt = $pdo->prepare("
                        UPDATE inventory_reports 
                        SET total_items = ?, report_details = ?, verification_status = 'Pending', 
                            verified_by = NULL, verified_at = NULL, remarks = NULL
                        WHERE id = ?
                    ");
                    $stmt->execute([$total_items, $details, $report_id]);
                    $_SESSION['success'] = 'Rejected report updated and resubmitted successfully. Status: Pending Review';
                } else {
                    // Create a new report
                    $report_id = $processModel->submitInventoryReport($inventory_id, $total_items, $details);
                    $_SESSION['success'] = 'Inventory report submitted successfully. Status: Pending Review';
                }
                
                header('Location: ' . APP_URL . '/views/processes/intern_inventory_report.php');
                exit;
            } catch(Exception $e) {
                $error = 'Error submitting report: ' . $e->getMessage();
            }
        }
    } elseif ($_POST['action'] === 'update_report') {
        $report_id = $_POST['report_id'] ?? null;
        $total_items = $_POST['total_items'] ?? 0;
        $details = $_POST['details'] ?? '';

        if (!$report_id) {
            $error = 'Report ID is missing.';
        } elseif ($total_items <= 0) {
            $error = 'Total items must be greater than 0.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE inventory_reports
                    SET total_items = ?, report_details = ?, verification_status = 'Pending'
                    WHERE id = ? AND created_by = ?
                ");
                $stmt->execute([$total_items, $details, $report_id, $uid]);
                $_SESSION['success'] = 'Report updated successfully. Status reset to Pending Review';
                header('Location: ' . APP_URL . '/views/processes/intern_inventory_report.php');
                exit;
            } catch(Exception $e) {
                $error = 'Error updating report: ' . $e->getMessage();
            }
        }
    }
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
.wrap{max-width:900px;margin:0 auto;padding:28px 24px}
.form-group{margin-bottom:20px}
.form-label{display:block;font-size:13px;font-weight:600;color:var(--text);margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em}
.form-input,.form-select,.form-textarea{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-family:inherit;font-size:13px;transition:border-color .2s}
.form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,255,176,.1)}
.form-textarea{resize:vertical;min-height:100px}
.report-card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:12px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.report-card:hover{border-color:var(--border2)}
.report-info{flex:1;min-width:0}
.report-title{font-size:13px;font-weight:600;color:var(--text);margin-bottom:4px}
.report-meta{font-size:11px;color:var(--text3);margin-bottom:8px}
.report-status{display:inline-block;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em}
.status-pending{background:rgba(245,158,11,.12);color:var(--warn)}
.status-approved{background:rgba(79,255,176,.12);color:var(--accent)}
.status-rejected{background:rgba(248,113,113,.12);color:var(--danger)}
.btn{display:inline-block;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer;border:none;transition:all .2s;text-align:center}
.btn-primary{background:var(--accent);color:#0a0c10}
.btn-primary:hover{background:var(--accent2);transform:translateY(-1px)}
.btn-secondary{background:var(--surface);border:1px solid var(--border);color:var(--text)}
.btn-secondary:hover{border-color:var(--border2)}
.section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--text3);margin:28px 0 14px;display:flex;align-items:center;gap:8px}
.section-title::after{content:'';flex:1;height:1px;background:var(--border)}
.empty-state{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:40px;text-align:center;color:var(--text3)}
.empty-state i{font-size:40px;margin-bottom:12px;display:block;color:var(--accent2)}
.btn-sm{padding:8px 12px;font-size:11px}
#editModal{display:none}
.inventory-card{box-shadow:0 2px 8px rgba(0,0,0,.1)}
.inventory-card:hover{border-color:var(--accent2);box-shadow:0 4px 12px rgba(0,0,0,.15);transform:translateY(-2px)}
.inventory-card.selected{border-color:var(--accent);background:rgba(79,255,176,.05)}
.inventory-card.selected .inventory-radio div{background:var(--accent);box-shadow:0 0 0 2px var(--surface)}
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

<?php if(isset($_SESSION['success'])): ?>
<div class="alert alert-success" style="margin-bottom:20px"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if($error): ?>
<div class="alert alert-error" style="margin-bottom:20px"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Header -->
<div style="background:linear-gradient(135deg,rgba(249,115,22,.08),rgba(56,189,248,.08));border:1px solid rgba(249,115,22,.18);border-radius:12px;padding:20px 24px;margin-bottom:28px">
  <div style="display:flex;align-items:center;gap:14px">
    <div style="width:48px;height:48px;border-radius:10px;background:rgba(249,115,22,.15);display:flex;align-items:center;justify-content:center;color:#f97316;font-size:20px;flex-shrink:0">
      <i class="fas fa-chart-bar"></i>
    </div>
    <div>
      <div style="font-size:16px;font-weight:700;color:var(--text)">Submit Inventory Report</div>
      <div style="font-size:12px;color:var(--text2);margin-top:2px">Send your completed inventory count to the pharmacy technician for review</div>
    </div>
  </div>
</div>

<!-- Submit Form -->
<?php if(!empty($completed_counts)): ?>
<div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:28px">
  <form method="POST" action="">
    <input type="hidden" name="action" value="submit_report">
    <input type="hidden" name="inventory_id" id="selectedInventoryId">
    <input type="hidden" name="total_items" id="selectedTotalItems">

    <!-- Inventory Details Section -->
    <div id="inventoryDetailsSection" style="display:none;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:24px;overflow-x:auto">
      <div style="margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <div>
            <div style="font-size:14px;font-weight:700;color:var(--text)">Inventory Summary</div>
            <div id="inventoryMeta" style="font-size:11px;color:var(--text3);margin-top:4px"></div>
          </div>
          <div style="text-align:right">
            <div style="font-size:11px;color:var(--text3)">Total On-Hand Value</div>
            <div id="totalValue" style="font-size:18px;font-weight:700;color:var(--accent);margin-top:4px">₱0.00</div>
          </div>
        </div>
      </div>
      
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:12px">
          <thead>
            <tr style="border-bottom:2px solid var(--border)">
              <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">#</th>
              <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Product Code</th>
              <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Trade Name</th>
              <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Generic Name</th>
              <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Form</th>
              <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Pack Size</th>
              <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Manufacturer</th>
              <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Category</th>
              <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">On Hand</th>
              <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Re-Order Point</th>
              <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Counted Qty</th>
              <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Variance</th>
              <th style="padding:10px;text-align:right;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em">Cost Price</th>
            </tr>
          </thead>
          <tbody id="inventoryTableBody">
            <!-- Rows will be populated here -->
          </tbody>
        </table>
      </div>
    </div>

    <div style="display:flex;gap:12px;justify-content:flex-end">
      <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
      <button type="submit" class="btn btn-primary" id="submitBtn" disabled><i class="fas fa-paper-plane"></i> Submit Report</button>
    </div>
  </form>
</div>
<?php else: ?>
<div class="empty-state">
  <i class="fas fa-inbox"></i>
  <p style="font-size:13px;margin-bottom:10px">No completed inventory counts yet.</p>
  <p style="font-size:12px;margin-bottom:16px">You need to complete an inventory count before submitting a report.</p>
  <a href="<?php echo APP_URL; ?>/views/processes/conduct_inventory.php" class="btn btn-primary"><i class="fas fa-boxes"></i> Start Inventory Count</a>
</div>
<?php endif; ?>

<!-- My Reports -->
<div class="section-title">My Submitted Reports</div>
<?php if(!empty($my_reports)): ?>
<?php foreach($my_reports as $report):
    $status_class = 'status-' . strtolower($report['verification_status']);
    $status_icon = $report['verification_status'] === 'Approved' ? 'fa-check-circle' : ($report['verification_status'] === 'Rejected' ? 'fa-times-circle' : 'fa-clock');
    $can_edit = $report['verification_status'] === 'Rejected';
?>
<div class="report-card">
  <div class="report-info">
    <div class="report-title">
      <i class="fas fa-chart-bar" style="margin-right:6px;color:#f97316"></i>
      Inventory Report #<?php echo $report['id']; ?>
    </div>
    <div class="report-meta">
      <i class="fas fa-calendar" style="margin-right:4px"></i>
      Submitted: <?php echo date('M d, Y h:i A', strtotime($report['created_at'])); ?>
      &nbsp;·&nbsp;
      <i class="fas fa-boxes" style="margin-right:4px"></i>
      Total Items: <strong><?php echo $report['total_items']; ?></strong>
    </div>
    <?php if(!empty($report['report_details'])): ?>
    <div style="font-size:12px;color:var(--text2);margin-bottom:8px;padding:8px 12px;background:var(--surface2);border-radius:6px">
      <?php echo nl2br(htmlspecialchars(substr($report['report_details'], 0, 150))); ?>
      <?php if(strlen($report['report_details']) > 150): ?>...<?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Feedback Section -->
    <?php if($report['verification_status'] !== 'Pending'): ?>
    <div style="margin-top:12px;padding:12px;background:<?php echo $report['verification_status'] === 'Approved' ? 'rgba(79,255,176,.08)' : 'rgba(248,113,113,.08)'; ?>;border-left:3px solid <?php echo $report['verification_status'] === 'Approved' ? 'var(--accent)' : 'var(--danger)'; ?>;border-radius:6px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text3);margin-bottom:6px">
        <i class="fas fa-<?php echo $report['verification_status'] === 'Approved' ? 'check-circle' : 'times-circle'; ?>" style="margin-right:4px"></i>
        <?php echo $report['verification_status']; ?> by Pharmacy Technician
      </div>
      <div style="font-size:12px;color:var(--text2);margin-bottom:6px">
        <i class="fas fa-calendar" style="margin-right:4px"></i>
        <?php echo $report['verified_at'] ? date('M d, Y h:i A', strtotime($report['verified_at'])) : 'N/A'; ?>
      </div>
      <?php if(!empty($report['remarks'])): ?>
      <div style="font-size:12px;color:var(--text);margin-top:8px;padding:8px;background:var(--surface);border-radius:4px">
        <strong>Feedback:</strong><br>
        <?php echo nl2br(htmlspecialchars($report['remarks'])); ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Inventory Items Toggle -->
    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleInventoryItems(this, <?php echo $report['inventory_id']; ?>)" style="margin-top:12px;width:100%;font-size:11px;padding:8px 12px">
      <i class="fas fa-chevron-down"></i> View Inventory Summary
    </button>
    <div class="inventory-items-detail" style="display:none;margin-top:12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px;max-height:250px;overflow-y:auto"></div>
  </div>
  
  <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0">
    <span class="report-status <?php echo $status_class; ?>">
      <i class="fas <?php echo $status_icon; ?>" style="margin-right:4px"></i>
      <?php echo $report['verification_status']; ?>
    </span>
    <?php if($can_edit): ?>
    <button type="button" class="btn btn-primary btn-sm" onclick="editReport(<?php echo $report['id']; ?>, <?php echo htmlspecialchars(json_encode($report)); ?>)" style="font-size:11px;padding:8px 12px">
      <i class="fas fa-edit"></i> Edit
    </button>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="empty-state">
  <i class="fas fa-file-alt"></i>
  <p>No reports submitted yet.</p>
</div>
<?php endif; ?>

</div>
<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>

<!-- Edit Report Modal -->
<div id="editModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;max-width:500px;width:90%;max-height:90vh;overflow-y:auto">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <h3 style="font-size:16px;font-weight:700;color:var(--text);margin:0">Edit Inventory Report</h3>
      <button type="button" onclick="closeEditModal()" style="background:none;border:none;font-size:20px;color:var(--text3);cursor:pointer;padding:0;width:24px;height:24px;display:flex;align-items:center;justify-content:center">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <form method="POST" action="">
      <input type="hidden" name="action" value="update_report">
      <input type="hidden" name="report_id" id="editReportId">

      <div class="form-group">
        <label class="form-label"><i class="fas fa-calculator" style="margin-right:6px"></i>Total Items Counted</label>
        <input type="number" name="total_items" id="editTotalItems" class="form-input" placeholder="Enter total number of items" min="1" required>
      </div>

      <div style="display:flex;gap:12px;justify-content:flex-end">
        <button type="button" onclick="closeEditModal()" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function selectInventory(inventoryId, totalItems) {
  // Update hidden fields
  document.getElementById('selectedInventoryId').value = inventoryId;
  document.getElementById('selectedTotalItems').value = totalItems;
  
  // Update card selection UI
  document.querySelectorAll('.inventory-card').forEach(card => {
    card.classList.remove('selected');
  });
  event.currentTarget.classList.add('selected');
  
  // Update radio buttons
  document.querySelectorAll('.inventory-radio div').forEach(radio => {
    radio.style.background = 'transparent';
  });
  document.getElementById('radio-' + inventoryId).style.background = 'var(--accent)';
  
  // Enable submit button
  document.getElementById('submitBtn').disabled = false;
  
  // Load and display inventory details
  loadInventoryDetails(inventoryId);
}

function loadInventoryDetails(inventoryId) {
  const detailsSection = document.getElementById('inventoryDetailsSection');
  const tableBody = document.getElementById('inventoryTableBody');
  const metaDiv = document.getElementById('inventoryMeta');
  const totalValueDiv = document.getElementById('totalValue');
  
  fetch('<?php echo APP_URL; ?>/api/get_inventory_items.php?inventory_id=' + inventoryId)
    .then(response => response.json())
    .then(data => {
      if (data.success && data.items.length > 0) {
        // Set metadata
        const inv = data.inventory;
        const date = new Date(inv.completed_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        const conductor = inv.first_name + ' ' + inv.last_name;
        metaDiv.innerHTML = `Date: <strong>${date}</strong> · Conducted by: <strong>${conductor}</strong> · Count #: <strong>${data.totals.product_count}</strong>`;
        
        // Set total value
        totalValueDiv.textContent = '₱' + parseFloat(data.totals.total_cost).toFixed(2);
        
        // Populate table rows
        let html = '';
        let rowNum = 1;
        data.items.forEach(item => {
          const variance = item.counted_qty - item.current_stock;
          const varianceClass = variance > 0 ? 'color:var(--accent)' : (variance < 0 ? 'color:var(--danger)' : 'color:var(--text3)');
          const rowCost = (item.cost_price * item.counted_qty).toFixed(2);
          
          html += `
            <tr style="border-bottom:1px solid var(--border);hover:background:var(--surface)">
              <td style="padding:10px;color:var(--text3)">${rowNum}</td>
              <td style="padding:10px;color:var(--accent);font-weight:600">${item.product_code}</td>
              <td style="padding:10px;color:var(--text);font-weight:600">${item.product_name}</td>
              <td style="padding:10px;color:var(--text2)">${item.generic_name || '-'}</td>
              <td style="padding:10px;color:var(--text2)">${item.form || '-'}</td>
              <td style="padding:10px;text-align:center;color:var(--text2)">${item.pack_size}</td>
              <td style="padding:10px;color:var(--text2);font-size:11px">${item.manufacturer_name || '-'}</td>
              <td style="padding:10px;color:var(--text2)">${item.category || '-'}</td>
              <td style="padding:10px;text-align:center;color:var(--text)">${item.current_stock}</td>
              <td style="padding:10px;text-align:center;color:var(--text3)">${item.reorder_level}</td>
              <td style="padding:10px;text-align:center;color:var(--accent);font-weight:700">${item.counted_qty}</td>
              <td style="padding:10px;text-align:center;font-weight:700;${varianceClass}">${variance > 0 ? '+' : ''}${variance}</td>
              <td style="padding:10px;text-align:right;color:var(--text)">₱${rowCost}</td>
            </tr>
          `;
          rowNum++;
        });
        
        tableBody.innerHTML = html;
        detailsSection.style.display = 'block';
      }
    })
    .catch(error => {
      console.error('Error loading inventory details:', error);
    });
}

function editReport(reportId, reportData) {
  document.getElementById('editReportId').value = reportId;
  document.getElementById('editTotalItems').value = reportData.total_items;
  document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeEditModal();
  }
});

function toggleInventoryItems(button, inventoryId) {
  const detailDiv = button.nextElementSibling;
  const isVisible = detailDiv.style.display !== 'none';
  
  if (isVisible) {
    detailDiv.style.display = 'none';
    button.innerHTML = '<i class="fas fa-chevron-down"></i> View Inventory Summary';
  } else {
    // Load summary if not already loaded
    if (!detailDiv.dataset.loaded) {
      fetch('<?php echo APP_URL; ?>/api/get_inventory_items.php?inventory_id=' + inventoryId)
        .then(response => response.json())
        .then(data => {
          if (data.success && data.summary.length > 0) {
            let html = '<div style="margin-bottom:12px;padding:10px;background:var(--surface);border-radius:6px;border-left:3px solid var(--accent)">';
            html += '<div style="font-size:10px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Summary</div>';
            html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:12px">';
            html += '<div><span style="color:var(--text3)">Products:</span> <strong style="color:var(--accent)">' + data.totals.total_products + '</strong></div>';
            html += '<div><span style="color:var(--text3)">Total Items:</span> <strong style="color:var(--accent)">' + data.totals.total_items + '</strong></div>';
            html += '</div></div>';
            
            html += '<div style="font-size:10px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">By Category</div>';
            data.summary.forEach(cat => {
              html += `
                <div style="padding:8px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;font-size:11px">
                  <div>
                    <div style="font-weight:600;color:var(--text)">${cat.category || 'Uncategorized'}</div>
                    <div style="color:var(--text3);margin-top:1px">${cat.product_count} product(s)</div>
                  </div>
                  <div style="text-align:right;font-weight:700;color:var(--accent)">${cat.total_quantity} units</div>
                </div>
              `;
            });
            detailDiv.innerHTML = html;
          } else {
            detailDiv.innerHTML = '<div style="padding:12px;text-align:center;color:var(--text3);font-size:11px"><i class="fas fa-inbox" style="margin-right:4px"></i>No items in this inventory</div>';
          }
          detailDiv.dataset.loaded = 'true';
          detailDiv.style.display = 'block';
          button.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Inventory Summary';
        })
        .catch(error => {
          console.error('Error loading inventory summary:', error);
          detailDiv.innerHTML = '<div style="padding:12px;text-align:center;color:var(--danger);font-size:11px"><i class="fas fa-exclamation-circle" style="margin-right:4px"></i>Error loading summary</div>';
          detailDiv.style.display = 'block';
        });
    } else {
      detailDiv.style.display = 'block';
      button.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Inventory Summary';
    }
  }
}
</script>

</body>
</html>
