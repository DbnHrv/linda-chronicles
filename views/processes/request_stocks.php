<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(12);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';

// Get verified inventory reports
try {
    $stmt = $pdo->prepare("
        SELECT r.*, ic.id as inventory_id, ic.completed_at, u.first_name, u.last_name
        FROM inventory_reports r
        JOIN inventory_counts ic ON r.inventory_id = ic.id
        JOIN users u ON r.created_by = u.id
        WHERE r.verification_status = 'Verified'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute();
    $verified_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $verified_reports = [];
}

try { $requisitions = $processModel->getRequisitionsByUser($_SESSION['user_id']); } catch(Exception $e){ $requisitions=[]; }

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='request_stock') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) { $message='Invalid token.'; $message_type='error'; }
    else { try {
        $inventory_id = intval($_POST['inventory_id']??0);
        $selected_items = json_decode($_POST['selected_items']??'[]', true);
        $manual_items = json_decode($_POST['manual_items']??'{}', true);
        $reason = sanitize($_POST['reason']??'');
        
        if (empty($selected_items) && empty($manual_items)) {
            throw new Exception('Please select at least one item to request.');
        }
        
        // Create requisitions for each selected item from inventory
        foreach ($selected_items as $product_id => $qty) {
            if ($qty > 0) {
                $processModel->createStockRequisition($product_id, $qty, $reason);
            }
        }
        
        // Create requisitions for manually added items
        foreach ($manual_items as $itemId => $item) {
            if ($item['quantity'] > 0) {
                // Store manual item details in the reason field with item info
                $itemDetails = "Manual Request: {$item['tradeName']}";
                if (!empty($item['genericName'])) {
                    $itemDetails .= " | Generic: {$item['genericName']}";
                }
                if (!empty($item['form'])) {
                    $itemDetails .= " | Form: {$item['form']}";
                }
                if (!empty($item['packSize'])) {
                    $itemDetails .= " | Pack Size: {$item['packSize']}";
                }
                if (!empty($item['description'])) {
                    $itemDetails .= " | Description: {$item['description']}";
                }
                if (!empty($reason)) {
                    $itemDetails .= " | Reason: {$reason}";
                }
                
                // Create a special requisition entry for manual items
                // We'll use product_id = NULL to indicate manual item, and store details in reason
                $stmt = $pdo->prepare("
                    INSERT INTO stock_requisitions (product_id, quantity_needed, reason, requested_by, status, created_at)
                    VALUES (NULL, ?, ?, ?, 'Pending', NOW())
                ");
                $stmt->execute([$item['quantity'], $itemDetails, $_SESSION['user_id']]);
            }
        }
        
        $message = 'Stock requisition(s) submitted successfully and sent to pharmacist for review.';
        $message_type = 'success';
        try { $requisitions = $processModel->getRequisitionsByUser($_SESSION['user_id']); } catch(Exception $e){ $requisitions=[]; }
    } catch(Exception $e){$message=$e->getMessage();$message_type='error';} }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Request Stocks — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
.wrap{max-width:1200px;margin:0 auto;padding:28px 24px}
.header{background:linear-gradient(135deg,rgba(249,115,22,.08),rgba(56,189,248,.08));border:1px solid rgba(249,115,22,.18);border-radius:12px;padding:20px 24px;margin-bottom:28px;display:flex;align-items:center;gap:14px}
.header-icon{width:48px;height:48px;border-radius:10px;background:rgba(249,115,22,.15);display:flex;align-items:center;justify-content:center;color:#f97316;font-size:20px;flex-shrink:0}
.header-text h1{font-size:16px;font-weight:700;color:var(--text);margin:0}
.header-text p{font-size:12px;color:var(--text2);margin:2px 0 0 0}
.section{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:24px}
.section-title{font-size:14px;font-weight:700;color:var(--text);margin-bottom:16px;display:flex;align-items:center;gap:8px}
.section-title::before{content:'';width:3px;height:14px;background:var(--accent);border-radius:2px}
.report-selector{margin-bottom:20px}
.report-selector label{display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em}
.report-selector select{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-family:inherit;font-size:13px;cursor:pointer}
.report-info{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:20px;display:none}
.report-info.active{display:block}
.report-info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:16px}
.report-info-item{display:flex;flex-direction:column}
.report-info-label{font-size:11px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px}
.report-info-value{font-size:14px;font-weight:600;color:var(--text)}
.inventory-table{width:100%;border-collapse:collapse;margin-bottom:20px}
.inventory-table thead{background:var(--surface2);border-bottom:2px solid var(--border)}
.inventory-table th{padding:12px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:11px}
.inventory-table td{padding:12px;border-bottom:1px solid var(--border);font-size:12px}
.inventory-table tbody tr:hover{background:var(--surface2)}
.checkbox-col{text-align:center;width:40px}
.checkbox-col input{cursor:pointer;width:18px;height:18px}
.item-no{text-align:center;color:var(--text3);font-weight:600}
.product-code{color:var(--accent);font-weight:700;font-family:monospace;font-size:10px}
.product-name{color:var(--text);font-weight:600}
.qty-col{text-align:center;color:var(--accent);font-weight:700}
.unit-col{text-align:center;color:var(--text2)}
.low-stock{background:rgba(248,113,113,.05)}
.low-stock-qty{color:var(--danger);font-weight:700}
.reason-field{margin-bottom:20px}
.reason-field label{display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em}
.reason-field textarea{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-family:inherit;font-size:13px;resize:vertical;min-height:80px}
.action-buttons{display:flex;gap:12px;justify-content:flex-end}
.btn{display:inline-block;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer;border:none;transition:all .2s;text-align:center}
.btn-primary{background:var(--accent);color:#0a0c10}
.btn-primary:hover{background:var(--accent2);transform:translateY(-1px)}
.btn-primary:disabled{background:var(--text3);cursor:not-allowed;transform:none}
.btn-secondary{background:var(--surface2);border:1px solid var(--border);color:var(--text)}
.btn-secondary:hover{border-color:var(--border2)}
.empty-state{text-align:center;padding:40px 20px;color:var(--text3)}
.empty-state-icon{font-size:48px;margin-bottom:12px;display:block;color:var(--accent2)}
.requisitions-list{display:grid;gap:12px}
.requisition-card{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:14px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.requisition-info{flex:1}
.requisition-product{font-size:13px;font-weight:600;color:var(--text);margin-bottom:4px}
.requisition-meta{font-size:11px;color:var(--text3);margin-bottom:4px}
.requisition-reason{font-size:12px;color:var(--text2);margin-top:6px}
.status-badge{display:inline-block;padding:6px 12px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.status-pending{background:rgba(245,158,11,.12);color:var(--warn)}
.status-approved{background:rgba(79,255,176,.12);color:var(--accent)}
.status-rejected{background:rgba(248,113,113,.12);color:var(--danger)}
</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>

<div class="wrap">

<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<!-- Header -->
<div class="header">
  <div class="header-icon"><i class="fas fa-shopping-cart"></i></div>
  <div class="header-text">
    <h1>Request Additional Stocks</h1>
    <p>Submit stock requisition requests based on verified inventory reports</p>
  </div>
</div>

<!-- Request Form Section -->
<div class="section">
  <div class="section-title"><i class="fas fa-list-check"></i> Stock Request Form</div>
  
  <form method="POST" id="stockRequestForm">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="action" value="request_stock">
    <input type="hidden" name="inventory_id" id="inventoryIdInput" value="">
    <input type="hidden" name="selected_items" id="selectedItemsInput" value="{}">
    
    <!-- Report Selector -->
    <div class="report-selector">
      <label>Select Verified Inventory Report</label>
      <select id="reportSelect" onchange="loadInventoryReport(this.value)">
        <option value="">— Select a Report —</option>
        <?php if(empty($verified_reports)): ?>
        <option value="" disabled>No verified reports available</option>
        <?php else: ?>
        <?php foreach($verified_reports as $report): ?>
        <option value="<?php echo $report['inventory_id']; ?>" data-report-id="<?php echo $report['id']; ?>">
          Report #<?php echo $report['id']; ?> - <?php echo htmlspecialchars($report['first_name'].' '.$report['last_name']); ?> (<?php echo date('M d, Y', strtotime($report['created_at'])); ?>)
        </option>
        <?php endforeach; ?>
        <?php endif; ?>
      </select>
      <?php if(empty($verified_reports)): ?>
      <div style="background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.2);border-radius:8px;padding:12px;margin-top:8px;font-size:12px;color:var(--text2)">
        <i class="fas fa-info-circle" style="margin-right:6px;color:var(--danger)"></i>
        No verified inventory reports available. Please ask interns to submit and get their inventory reports verified first.
      </div>
      <?php endif; ?>
    </div>
    
    <!-- Report Information -->
    <div id="reportInfo" class="report-info">
      <div class="report-info-grid">
        <div class="report-info-item">
          <div class="report-info-label">Date</div>
          <div class="report-info-value" id="reportDate">-</div>
        </div>
        <div class="report-info-item">
          <div class="report-info-label">Conducted By</div>
          <div class="report-info-value" id="reportConductor">-</div>
        </div>
        <div class="report-info-item">
          <div class="report-info-label">Total Products</div>
          <div class="report-info-value" id="totalProducts" style="color:var(--accent)">0</div>
        </div>
      </div>
    </div>
    
    <!-- Inventory Table -->
    <div id="tableContainer" style="display:none;overflow-x:auto;margin-bottom:20px">
      <table class="inventory-table">
        <thead>
          <tr>
            <th style="width:60px">Item No.</th>
            <th>Item to be Purchased</th>
            <th style="width:100px">Current Stock</th>
            <th style="width:100px">Counted Qty</th>
            <th style="width:140px">Request Qty (PCS)</th>
            <th style="width:80px">Unit</th>
            <th style="width:100px">Action</th>
          </tr>
        </thead>
        <tbody id="inventoryTableBody">
          <!-- Rows will be populated here -->
        </tbody>
      </table>
    </div>
    
    <!-- Fallback: Manual Stock Request (if no reports) -->
    <?php if(empty($verified_reports)): ?>
    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:20px">
      <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:12px">
        <i class="fas fa-plus-circle" style="margin-right:6px;color:var(--accent)"></i>Request Stock Manually
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div>
          <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Product</label>
          <select id="manualProductSelect" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--text);font-size:12px">
            <option value="">— Select Product —</option>
            <?php 
            try {
              $stmt = $pdo->prepare("SELECT id, product_code, product_name FROM products WHERE is_active=1 ORDER BY product_name");
              $stmt->execute();
              $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
              foreach($products as $p): ?>
              <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_code'].' - '.$p['product_name']); ?></option>
              <?php endforeach;
            } catch(Exception $e) {}
            ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px">Quantity (PCS)</label>
          <input type="number" id="manualQtyInput" min="1" value="1" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--text);font-size:12px">
        </div>
      </div>
      <button type="button" onclick="addManualItem()" class="btn btn-primary" style="width:100%;margin-bottom:12px"><i class="fas fa-plus"></i> Add Item</button>
      
      <div id="manualItemsList" style="display:none;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px">
        <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">Items to Request:</div>
        <div id="manualItemsDisplay" style="display:grid;gap:8px"></div>
      </div>
    </div>
    <?php endif; ?>
    
    <!-- Reason Field -->
    <div class="reason-field">
      <label>Reason for Request</label>
      <textarea name="reason" placeholder="Why are additional stocks needed?"></textarea>
    </div>
    
    <!-- Add Item Button -->
    <div style="margin-bottom:20px">
      <button type="button" class="btn btn-primary" onclick="openAddItemModal()" style="width:100%"><i class="fas fa-plus-circle"></i> Add Item to Request</button>
    </div>
    
    <!-- Requisition Items Table -->
    <div id="requisitionTableContainer" style="display:none;margin-bottom:20px">
      <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:12px;display:flex;align-items:center;gap:8px">
        <i class="fas fa-list" style="color:var(--accent)"></i>Items to Request
      </div>
      <div style="overflow-x:auto;border:1px solid var(--border);border-radius:10px">
        <table class="inventory-table">
          <thead>
            <tr>
              <th style="width:50px">#</th>
              <th>Trade Name</th>
              <th>Generic Name</th>
              <th style="width:80px">Form</th>
              <th style="width:80px">Pack Size</th>
              <th style="width:100px">Quantity</th>
              <th style="width:80px">Action</th>
            </tr>
          </thead>
          <tbody id="requisitionTableBody">
            <!-- Items will be populated here -->
          </tbody>
        </table>
      </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="action-buttons">
      <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
      <button type="submit" class="btn btn-primary" id="submitBtn" disabled><i class="fas fa-paper-plane"></i> Submit Request</button>
    </div>
  </form>
</div>

<!-- Add Item Modal -->
<div id="addItemModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;overflow-y:auto;padding:20px;backdrop-filter:blur(4px)">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;max-width:700px;width:100%;box-shadow:0 8px 40px rgba(0,0,0,.5)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <h3 style="font-size:18px;font-weight:700;color:var(--text);margin:0">
        <i class="fas fa-plus-circle" style="margin-right:8px;color:var(--accent)"></i>Add Item to Request
      </h3>
      <button type="button" onclick="closeAddItemModal()" style="background:none;border:none;font-size:24px;color:var(--text3);cursor:pointer;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Add Item Form -->
    <div style="display:grid;gap:14px;margin-bottom:20px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Trade Name <span style="color:var(--danger)">*</span></label>
          <input type="text" id="addTradeName" placeholder="e.g. Amoxicillin 500mg" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-size:13px">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Generic Name</label>
          <input type="text" id="addGenericName" placeholder="e.g. Amoxicillin" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-size:13px">
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Form</label>
          <input type="text" id="addForm" placeholder="e.g. Capsule, Tablet, Syrup" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-size:13px">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Pack Size</label>
          <input type="number" id="addPackSize" placeholder="e.g. 100" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-size:13px">
        </div>
      </div>

      <div>
        <label style="display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Description</label>
        <textarea id="addDescription" placeholder="e.g. Pain reliever and fever reducer" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-size:13px;resize:vertical;min-height:60px"></textarea>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Quantity to Request <span style="color:var(--danger)">*</span></label>
          <input type="number" id="addQuantity" min="1" value="1" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-size:13px">
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div style="display:flex;gap:12px;justify-content:flex-end">
      <button type="button" onclick="closeAddItemModal()" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</button>
      <button type="button" onclick="addNewItem()" class="btn btn-primary"><i class="fas fa-plus"></i> Add Item</button>
    </div>
  </div>
</div>

<!-- Item Details Modal -->
<div id="itemModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;overflow-y:auto;padding:20px;backdrop-filter:blur(4px)">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;max-width:600px;width:100%;box-shadow:0 8px 40px rgba(0,0,0,.5)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <h3 style="font-size:18px;font-weight:700;color:var(--text);margin:0">
        <i class="fas fa-box" style="margin-right:8px;color:var(--accent)"></i>Request Details
      </h3>
      <button type="button" onclick="closeItemModal()" style="background:none;border:none;font-size:24px;color:var(--text3);cursor:pointer;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Product Info -->
    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:20px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:12px">
        <div>
          <div style="color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Product Code</div>
          <div id="modalProductCode" style="color:var(--accent);font-weight:700;font-family:monospace">-</div>
        </div>
        <div>
          <div style="color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Product Name</div>
          <div id="modalProductName" style="color:var(--text);font-weight:600">-</div>
        </div>
        <div>
          <div style="color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Current Stock</div>
          <div id="modalCurrentStock" style="color:var(--text)">-</div>
        </div>
        <div>
          <div style="color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Counted Qty</div>
          <div id="modalCountedQty" style="color:var(--accent);font-weight:700">-</div>
        </div>
      </div>
    </div>

    <!-- Request Details Form -->
    <div style="display:grid;gap:14px;margin-bottom:20px">
      <div>
        <label style="display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Quantity to Request (PCS) <span style="color:var(--danger)">*</span></label>
        <input type="number" id="modalQty" min="1" value="1" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-size:13px">
      </div>

      <div>
        <label style="display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Estimated Unit Price (₱)</label>
        <input type="number" id="modalUnitPrice" min="0" step="0.01" value="0" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-size:13px">
      </div>

      <div>
        <label style="display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Preferred Supplier</label>
        <input type="text" id="modalSupplier" placeholder="e.g., PharmaCorp Inc." style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-size:13px">
      </div>

      <div>
        <label style="display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Delivery Date Required</label>
        <input type="date" id="modalDeliveryDate" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-size:13px">
      </div>

      <div>
        <label style="display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Additional Notes</label>
        <textarea id="modalNotes" placeholder="Any special requirements or notes..." style="width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-size:13px;resize:vertical;min-height:80px"></textarea>
      </div>
    </div>

    <!-- Action Buttons -->
    <div style="display:flex;gap:12px;justify-content:flex-end">
      <button type="button" onclick="closeItemModal()" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</button>
      <button type="button" onclick="saveItemDetails()" class="btn btn-primary"><i class="fas fa-save"></i> Save Details</button>
    </div>
  </div>
</div>

<!-- My Requisitions Section -->
<div class="section">
  <div class="section-title"><i class="fas fa-history"></i> My Requisitions</div>
  
  <?php if(empty($requisitions)): ?>
  <div class="empty-state">
    <i class="fas fa-inbox empty-state-icon"></i>
    <p>No requisitions yet.</p>
  </div>
  <?php else: ?>
  <div class="requisitions-list">
    <?php foreach($requisitions as $r): $slug=strtolower($r['status']); ?>
    <div class="requisition-card">
      <div class="requisition-info">
        <div class="requisition-product"><?php echo htmlspecialchars($r['product_name']); ?></div>
        <div class="requisition-meta">Qty: <?php echo $r['quantity_needed']; ?> · <?php echo date('M d, Y',strtotime($r['created_at'])); ?></div>
        <?php if($r['reason']): ?><div class="requisition-reason"><strong>Reason:</strong> <?php echo htmlspecialchars($r['reason']); ?></div><?php endif; ?>
      </div>
      <span class="status-badge status-<?php echo $slug; ?>"><?php echo $r['status']; ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>

<script>
let currentInventoryData = {};
let selectedItems = {};
let manualItems = {};

function loadInventoryReport(inventoryId) {
  if (!inventoryId) {
    document.getElementById('reportInfo').classList.remove('active');
    document.getElementById('tableContainer').style.display = 'none';
    document.getElementById('inventoryIdInput').value = '';
    document.getElementById('submitBtn').disabled = true;
    return;
  }
  
  document.getElementById('inventoryIdInput').value = inventoryId;
  
  // Fetch inventory details from API
  fetch('<?php echo APP_URL; ?>/api/get_inventory_items.php?inventory_id=' + inventoryId)
    .then(response => response.json())
    .then(data => {
      if (data.success && data.items.length > 0) {
        currentInventoryData = data;
        displayInventoryTable(data);
        document.getElementById('reportInfo').classList.add('active');
        document.getElementById('tableContainer').style.display = 'block';
        document.getElementById('submitBtn').disabled = false;
      } else {
        alert('Error loading inventory data');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error loading inventory data');
    });
}

function displayInventoryTable(data) {
  const inv = data.inventory;
  const date = new Date(inv.completed_at || inv.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
  const conductor = inv.first_name + ' ' + inv.last_name;
  
  document.getElementById('reportDate').textContent = date;
  document.getElementById('reportConductor').textContent = conductor;
  document.getElementById('totalProducts').textContent = data.items.length;
  
  // Populate table
  let html = '';
  let itemNo = 1;
  selectedItems = {};
  
  data.items.forEach(item => {
    const isLowStock = item.current_stock <= item.reorder_level;
    
    html += `
      <tr ${isLowStock ? 'class="low-stock"' : ''}>
        <td class="item-no">${itemNo}</td>
        <td>
          <div class="product-code">${item.product_code}</div>
          <div class="product-name">${item.product_name}</div>
          <div style="font-size:11px;color:var(--text3);margin-top:4px">${item.generic_name || '-'}</div>
        </td>
        <td style="text-align:center;color:${isLowStock ? 'var(--danger)' : 'var(--text)'};font-weight:${isLowStock ? '700' : '600'}">
          ${item.current_stock}
          ${isLowStock ? '<i class="fas fa-exclamation-triangle" style="margin-left:6px;font-size:10px;color:var(--danger)" title="Low stock"></i>' : ''}
        </td>
        <td style="text-align:center;color:var(--accent);font-weight:700">${item.counted_qty}</td>
        <td style="text-align:center">
          <input type="number" class="request-qty-input" data-product-id="${item.product_id}" min="0" value="0" onchange="updateRequestItems()" style="width:80px;padding:8px;border:1px solid var(--border);border-radius:6px;background:var(--surface3);color:var(--accent);font-weight:600;text-align:center;font-size:12px">
        </td>
        <td class="unit-col">PCS</td>
        <td style="text-align:center">
          <button type="button" class="btn btn-primary" style="padding:6px 12px;font-size:11px" onclick="openItemModal(${item.product_id}, '${item.product_code}', '${item.product_name}', ${item.current_stock}, ${item.counted_qty})"><i class="fas fa-edit"></i> Details</button>
        </td>
      </tr>
    `;
    itemNo++;
  });
  
  document.getElementById('inventoryTableBody').innerHTML = html;
}

function updateRequestItems() {
  selectedItems = {};
  document.querySelectorAll('.request-qty-input').forEach(input => {
    const productId = input.dataset.productId;
    const qty = parseInt(input.value) || 0;
    if (qty > 0) {
      selectedItems[productId] = qty;
    }
  });
  
  document.getElementById('selectedItemsInput').value = JSON.stringify(selectedItems);
  document.getElementById('submitBtn').disabled = Object.keys(selectedItems).length === 0 && Object.keys(manualItems).length === 0;
}

let currentModalProductId = null;
let itemDetails = {};
let manualRequisitionItems = {};
let manualItemCounter = 0;

function openAddItemModal() {
  document.getElementById('addTradeName').value = '';
  document.getElementById('addGenericName').value = '';
  document.getElementById('addForm').value = '';
  document.getElementById('addPackSize').value = '';
  document.getElementById('addDescription').value = '';
  document.getElementById('addQuantity').value = 1;
  
  document.getElementById('addItemModal').style.display = 'flex';
}

function closeAddItemModal() {
  document.getElementById('addItemModal').style.display = 'none';
}

function addNewItem() {
  const tradeName = document.getElementById('addTradeName').value.trim();
  const genericName = document.getElementById('addGenericName').value.trim();
  const form = document.getElementById('addForm').value.trim();
  const packSize = document.getElementById('addPackSize').value.trim();
  const description = document.getElementById('addDescription').value.trim();
  const quantity = parseInt(document.getElementById('addQuantity').value) || 0;
  
  if (!tradeName || quantity <= 0) {
    alert('Please enter Trade Name and Quantity');
    return;
  }
  
  manualItemCounter++;
  const itemId = 'manual_' + manualItemCounter;
  
  manualRequisitionItems[itemId] = {
    tradeName: tradeName,
    genericName: genericName,
    form: form,
    packSize: packSize,
    description: description,
    quantity: quantity
  };
  
  displayRequisitionTable();
  closeAddItemModal();
}

function removeRequisitionItem(itemId) {
  delete manualRequisitionItems[itemId];
  displayRequisitionTable();
}

function displayRequisitionTable() {
  if (Object.keys(manualRequisitionItems).length === 0) {
    document.getElementById('requisitionTableContainer').style.display = 'none';
    return;
  }
  
  document.getElementById('requisitionTableContainer').style.display = 'block';
  
  let html = '';
  let itemNo = 1;
  
  Object.entries(manualRequisitionItems).forEach(([itemId, item]) => {
    html += `
      <tr>
        <td style="text-align:center;color:var(--text3);font-weight:600">${itemNo}</td>
        <td style="color:var(--text);font-weight:600">${item.tradeName}</td>
        <td style="color:var(--text2)">${item.genericName || '-'}</td>
        <td style="text-align:center;color:var(--text2)">${item.form || '-'}</td>
        <td style="text-align:center;color:var(--text2)">${item.packSize || '-'}</td>
        <td style="text-align:center;color:var(--accent);font-weight:700">${item.quantity}</td>
        <td style="text-align:center">
          <button type="button" class="btn btn-primary" style="padding:6px 10px;font-size:11px;background:var(--danger)" onclick="removeRequisitionItem('${itemId}')"><i class="fas fa-trash"></i></button>
        </td>
      </tr>
    `;
    itemNo++;
  });
  
  document.getElementById('requisitionTableBody').innerHTML = html;
}

// Close modal when clicking outside
document.getElementById('addItemModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeAddItemModal();
  }
});

function openItemModal(productId, productCode, productName, currentStock, countedQty) {
  currentModalProductId = productId;
  
  document.getElementById('modalProductCode').textContent = productCode;
  document.getElementById('modalProductName').textContent = productName;
  document.getElementById('modalCurrentStock').textContent = currentStock;
  document.getElementById('modalCountedQty').textContent = countedQty;
  
  // Load existing details if available
  if (itemDetails[productId]) {
    document.getElementById('modalQty').value = itemDetails[productId].qty || 1;
    document.getElementById('modalUnitPrice').value = itemDetails[productId].unitPrice || 0;
    document.getElementById('modalSupplier').value = itemDetails[productId].supplier || '';
    document.getElementById('modalDeliveryDate').value = itemDetails[productId].deliveryDate || '';
    document.getElementById('modalNotes').value = itemDetails[productId].notes || '';
  } else {
    document.getElementById('modalQty').value = 1;
    document.getElementById('modalUnitPrice').value = 0;
    document.getElementById('modalSupplier').value = '';
    document.getElementById('modalDeliveryDate').value = '';
    document.getElementById('modalNotes').value = '';
  }
  
  document.getElementById('itemModal').style.display = 'flex';
}

function closeItemModal() {
  document.getElementById('itemModal').style.display = 'none';
  currentModalProductId = null;
}

function saveItemDetails() {
  if (!currentModalProductId) return;
  
  const qty = parseInt(document.getElementById('modalQty').value) || 0;
  if (qty <= 0) {
    alert('Please enter a valid quantity');
    return;
  }
  
  itemDetails[currentModalProductId] = {
    qty: qty,
    unitPrice: parseFloat(document.getElementById('modalUnitPrice').value) || 0,
    supplier: document.getElementById('modalSupplier').value,
    deliveryDate: document.getElementById('modalDeliveryDate').value,
    notes: document.getElementById('modalNotes').value
  };
  
  // Update the quantity input in the table
  const qtyInput = document.querySelector(`input[data-product-id="${currentModalProductId}"]`);
  if (qtyInput) {
    qtyInput.value = qty;
    updateRequestItems();
  }
  
  closeItemModal();
}

// Close modal when clicking outside
document.getElementById('itemModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeItemModal();
  }
});


function addManualItem() {
  const productId = document.getElementById('manualProductSelect').value;
  const qty = parseInt(document.getElementById('manualQtyInput').value) || 0;
  
  if (!productId || qty <= 0) {
    alert('Please select a product and enter a valid quantity');
    return;
  }
  
  const productText = document.getElementById('manualProductSelect').options[document.getElementById('manualProductSelect').selectedIndex].text;
  manualItems[productId] = { qty: qty, name: productText };
  
  displayManualItems();
  document.getElementById('manualProductSelect').value = '';
  document.getElementById('manualQtyInput').value = '1';
  
  updateSelectedItems();
}

function removeManualItem(productId) {
  delete manualItems[productId];
  displayManualItems();
  updateSelectedItems();
}

function displayManualItems() {
  if (Object.keys(manualItems).length === 0) {
    document.getElementById('manualItemsList').style.display = 'none';
    return;
  }
  
  document.getElementById('manualItemsList').style.display = 'block';
  let html = '';
  
  Object.entries(manualItems).forEach(([productId, item]) => {
    html += `
      <div style="background:var(--surface2);border:1px solid var(--border);border-radius:6px;padding:8px;display:flex;justify-content:space-between;align-items:center">
        <div style="font-size:12px;color:var(--text)">${item.name} <strong style="color:var(--accent)">(${item.qty} PCS)</strong></div>
        <button type="button" onclick="removeManualItem('${productId}')" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:14px"><i class="fas fa-trash"></i></button>
      </div>
    `;
  });
  
  document.getElementById('manualItemsDisplay').innerHTML = html;
  
  // Update selected items with manual items
  selectedItems = { ...selectedItems, ...Object.fromEntries(Object.entries(manualItems).map(([id, item]) => [id, item.qty])) };
  document.getElementById('selectedItemsInput').value = JSON.stringify(selectedItems);
}

// Form submission
document.getElementById('stockRequestForm').addEventListener('submit', function(e) {
  if (Object.keys(selectedItems).length === 0 && Object.keys(manualRequisitionItems).length === 0) {
    e.preventDefault();
    alert('Please select at least one item to request');
    return;
  }
  
  // Include item details in the submission
  const detailsInput = document.createElement('input');
  detailsInput.type = 'hidden';
  detailsInput.name = 'item_details';
  detailsInput.value = JSON.stringify(itemDetails);
  this.appendChild(detailsInput);
  
  // Include manual requisition items
  const manualItemsInput = document.createElement('input');
  manualItemsInput.type = 'hidden';
  manualItemsInput.name = 'manual_items';
  manualItemsInput.value = JSON.stringify(manualRequisitionItems);
  this.appendChild(manualItemsInput);
});
</script>
</body></html>
