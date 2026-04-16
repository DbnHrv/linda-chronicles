<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(9);

$processModel = new ProcessModel($pdo);
$message = $message_type = '';

// Load ALL products (shown in the inventory table even before adding to count)
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.product_code, p.product_name,
               COALESCE(p.generic_name, p.product_name) AS generic_name,
               COALESCE(p.description, '—') AS description,
               COALESCE(p.form, '—') AS form,
               COALESCE(p.pack_size, 1) AS pack_size,
               p.category, p.current_stock, p.unit_price, p.cost_price,
               p.reorder_level, p.batch_number, p.expiry_date,
               COALESCE(m.manufacturer_name, '—') AS manufacturer_name
        FROM products p
        LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
        WHERE p.is_active = 1
        ORDER BY p.product_name
    ");
    $stmt->execute();
    $all_products = $stmt->fetchAll();
} catch(Exception $e){ $all_products = []; }

try { $my_counts = $processModel->getInventoryCountsByUser($_SESSION['user_id']); }
catch(Exception $e){ $my_counts = []; }

$active_id = isset($_SESSION['active_inventory_id']) ? (int)$_SESSION['active_inventory_id'] : null;

// Build a map of product_id => counted_qty for the active count
$counted_map = [];
$count_total_value = 0;
if ($active_id) {
    try {
        $stmt = $pdo->prepare("SELECT ii.id AS item_id, ii.product_id, ii.quantity AS counted_qty
            FROM inventory_items ii WHERE ii.inventory_id = ?");
        $stmt->execute([$active_id]);
        foreach ($stmt->fetchAll() as $row) {
            $counted_map[$row['product_id']] = ['item_id'=>$row['item_id'],'qty'=>$row['counted_qty']];
        }
    } catch(Exception $e){}
    foreach ($all_products as $p) {
        if (isset($counted_map[$p['id']])) {
            $count_total_value += $counted_map[$p['id']]['qty'] * $p['cost_price'];
        }
    }
}

/* ── POST handler ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.'; $message_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'start_count') {
                $notes = sanitize($_POST['notes'] ?? '');
                $id = $processModel->startInventoryCount($notes);
                $_SESSION['active_inventory_id'] = $id;
                $active_id = $id;
                $message = 'Inventory count #'.$id.' started. Update quantities below.';
                $message_type = 'success';
                try { $my_counts = $processModel->getInventoryCountsByUser($_SESSION['user_id']); } catch(Exception $e){}
            }
            elseif ($action === 'save_qty') {
                // Save a single product qty
                $inv_id  = intval($_POST['inventory_id'] ?? 0);
                $prod_id = intval($_POST['product_id'] ?? 0);
                $qty     = intval($_POST['quantity'] ?? 0);
                if (!$inv_id || !$prod_id || $qty < 0) throw new Exception('Invalid data.');
                // Upsert
                $stmt = $pdo->prepare("SELECT id FROM inventory_items WHERE inventory_id=? AND product_id=?");
                $stmt->execute([$inv_id, $prod_id]);
                $existing = $stmt->fetch();
                if ($existing) {
                    $stmt = $pdo->prepare("UPDATE inventory_items SET quantity=? WHERE id=?");
                    $stmt->execute([$qty, $existing['id']]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO inventory_items (inventory_id,product_id,quantity) VALUES (?,?,?)");
                    $stmt->execute([$inv_id, $prod_id, $qty]);
                }
                $message = 'Quantity saved.'; $message_type = 'success';
            }
            elseif ($action === 'save_all') {
                // Bulk save all quantities from the table
                $inv_id = intval($_POST['inventory_id'] ?? 0);
                if (!$inv_id) throw new Exception('No active count.');
                $qtys = $_POST['qtys'] ?? [];
                $saved = 0;
                foreach ($qtys as $prod_id => $qty) {
                    $prod_id = intval($prod_id);
                    $qty     = intval($qty);
                    if ($prod_id <= 0 || $qty < 0) continue;
                    $stmt = $pdo->prepare("SELECT id FROM inventory_items WHERE inventory_id=? AND product_id=?");
                    $stmt->execute([$inv_id, $prod_id]);
                    $existing = $stmt->fetch();
                    if ($existing) {
                        $stmt = $pdo->prepare("UPDATE inventory_items SET quantity=? WHERE id=?");
                        $stmt->execute([$qty, $existing['id']]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO inventory_items (inventory_id,product_id,quantity) VALUES (?,?,?)");
                        $stmt->execute([$inv_id, $prod_id, $qty]);
                    }
                    $saved++;
                }
                $message = 'All quantities saved ('.$saved.' products).'; $message_type = 'success';
            }
            elseif ($action === 'complete_count') {
                $inv_id = intval($_POST['inventory_id'] ?? 0);
                if (!$inv_id) throw new Exception('ID required.');
                $processModel->completeInventoryCount($inv_id);
                unset($_SESSION['active_inventory_id']);
                $active_id = null;
                $message = 'Inventory count completed successfully.'; $message_type = 'success';
                try { $my_counts = $processModel->getInventoryCountsByUser($_SESSION['user_id']); } catch(Exception $e){}
            }
            elseif ($action === 'cancel_count') {
                $inv_id = intval($_POST['inventory_id'] ?? 0);
                if ($inv_id) {
                    $pdo->prepare("DELETE FROM inventory_items WHERE inventory_id=?")->execute([$inv_id]);
                    $pdo->prepare("UPDATE inventory_counts SET status='Cancelled' WHERE id=?")->execute([$inv_id]);
                }
                unset($_SESSION['active_inventory_id']);
                $active_id = null;
                $message = 'Count cancelled.'; $message_type = 'success';
                try { $my_counts = $processModel->getInventoryCountsByUser($_SESSION['user_id']); } catch(Exception $e){}
            }
            elseif ($action === 'resume_count') {
                $inv_id = intval($_POST['inventory_id'] ?? 0);
                $stmt = $pdo->prepare("SELECT id FROM inventory_counts WHERE id=? AND conducted_by=? AND status='In Progress'");
                $stmt->execute([$inv_id, $_SESSION['user_id']]);
                if (!$stmt->fetch()) throw new Exception('Count not found.');
                $_SESSION['active_inventory_id'] = $inv_id;
                $active_id = $inv_id;
                $message = 'Resumed count #'.$inv_id.'.'; $message_type = 'success';
            }

            elseif ($action === 'add_new_product_to_count') {
                // Create a new product record then add it to the count
                $inv_id       = intval($_POST['inventory_id'] ?? 0);
                $product_name = sanitize($_POST['product_name'] ?? '');
                $generic_name = sanitize($_POST['generic_name'] ?? '');
                $form         = sanitize($_POST['form'] ?? '');
                $pack_size    = intval($_POST['pack_size'] ?? 1);
                $mfr_name     = sanitize($_POST['manufacturer_name'] ?? '');
                $category     = sanitize($_POST['category'] ?? '');
                $description  = sanitize($_POST['description'] ?? '');
                $current_stock= intval($_POST['current_stock'] ?? 0);
                $reorder_level= intval($_POST['reorder_level'] ?? 50);
                $cost_price   = floatval($_POST['cost_price'] ?? 0);
                $unit_price   = floatval($_POST['unit_price'] ?? 0);
                $counted_qty  = intval($_POST['counted_qty'] ?? 0);

                if (!$product_name) throw new Exception('Trade name is required.');
                if (!$inv_id)       throw new Exception('No active count.');

                // Find or create manufacturer
                $mfr_id = null;
                if ($mfr_name) {
                    $stmt = $pdo->prepare("SELECT id FROM manufacturers WHERE manufacturer_name=? LIMIT 1");
                    $stmt->execute([$mfr_name]);
                    $mfr = $stmt->fetch();
                    if ($mfr) {
                        $mfr_id = $mfr['id'];
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO manufacturers (manufacturer_name) VALUES (?)");
                        $stmt->execute([$mfr_name]);
                        $mfr_id = $pdo->lastInsertId();
                    }
                }

                // Generate a unique product code
                $code = 'MED-' . strtoupper(substr(preg_replace('/[^A-Z0-9]/i','',str_replace(' ','',$product_name)),0,4)) . '-' . time();

                // Insert new product
                $stmt = $pdo->prepare("INSERT INTO products
                    (product_code, product_name, generic_name, description, form, pack_size,
                     category, manufacturer_id, unit_price, cost_price, current_stock, reorder_level, is_active)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)");
                $stmt->execute([$code, $product_name, $generic_name, $description, $form, $pack_size,
                    $category, $mfr_id, $unit_price, $cost_price, $current_stock, $reorder_level]);
                $new_product_id = $pdo->lastInsertId();

                // Add to inventory count
                $stmt = $pdo->prepare("INSERT INTO inventory_items (inventory_id, product_id, quantity) VALUES (?,?,?)");
                $stmt->execute([$inv_id, $new_product_id, $counted_qty]);

                $message = 'Product "'.$product_name.'" added to inventory count.';
                $message_type = 'success';

                // Reload products list
                $stmt = $pdo->prepare("
                    SELECT p.id, p.product_code, p.product_name,
                           COALESCE(p.generic_name, p.product_name) AS generic_name,
                           COALESCE(p.description, '—') AS description,
                           COALESCE(p.form, '—') AS form,
                           COALESCE(p.pack_size, 1) AS pack_size,
                           p.category, p.current_stock, p.unit_price, p.cost_price,
                           p.reorder_level, p.batch_number, p.expiry_date,
                           COALESCE(m.manufacturer_name, '—') AS manufacturer_name
                    FROM products p
                    LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
                    WHERE p.is_active = 1 ORDER BY p.product_name");
                $stmt->execute();
                $all_products = $stmt->fetchAll();
            }

            // Reload counted map
            if ($active_id) {
                $counted_map = []; $count_total_value = 0;
                $stmt = $pdo->prepare("SELECT ii.id AS item_id, ii.product_id, ii.quantity AS counted_qty FROM inventory_items ii WHERE ii.inventory_id=?");
                $stmt->execute([$active_id]);
                foreach ($stmt->fetchAll() as $row) $counted_map[$row['product_id']] = ['item_id'=>$row['item_id'],'qty'=>$row['counted_qty']];
                foreach ($all_products as $p) {
                    if (isset($counted_map[$p['id']])) $count_total_value += $counted_map[$p['id']]['qty'] * $p['cost_price'];
                }
            }
        } catch(Exception $e){ $message = $e->getMessage(); $message_type = 'error'; }
    }
}

$total_system_value = array_sum(array_map(fn($p) => $p['current_stock'] * $p['cost_price'], $all_products));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Conduct Product Inventory — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
/* ── Inventory table ── */
.inv-wrap{overflow-x:auto;border-radius:8px;border:1px solid var(--border)}
.inv-tbl{width:100%;border-collapse:collapse;font-size:12px;min-width:1100px}
.inv-tbl thead tr{background:var(--surface2)}
.inv-tbl th{padding:10px 11px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text3);border-bottom:1px solid var(--border);white-space:nowrap}
.inv-tbl th.r{text-align:right}
.inv-tbl td{padding:9px 11px;border-bottom:1px solid var(--border);color:var(--text);vertical-align:middle;font-size:12px}
.inv-tbl td.r{text-align:right;font-variant-numeric:tabular-nums}
.inv-tbl tbody tr:hover td{background:rgba(255,255,255,.02)}
.inv-tbl tbody tr:last-child td{border-bottom:none}
.inv-tbl tfoot td{padding:11px;background:var(--surface2);font-weight:700;border-top:2px solid var(--border2)}
/* qty input — only editable field */
.qty-input{
    background:var(--surface3);border:1px solid var(--border2);border-radius:6px;
    color:var(--text);font-size:12px;padding:5px 8px;width:72px;text-align:right;
    outline:none;font-family:inherit;transition:border-color .2s;
}
.qty-input:focus{border-color:var(--accent);box-shadow:0 0 0 2px rgba(79,255,176,.12)}
.qty-input:disabled{opacity:.4;cursor:not-allowed}
/* save single btn */
.save-btn{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:5px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid rgba(79,255,176,.3);background:rgba(79,255,176,.1);color:var(--accent);transition:all .15s;font-family:inherit}
.save-btn:hover{background:rgba(79,255,176,.2)}
/* variance */
.var-pos{color:var(--warn);font-weight:600}
.var-neg{color:var(--danger);font-weight:600}
.var-zero{color:var(--accent)}
/* low stock */
.low{color:var(--danger);font-weight:700}
/* code badge */
.code{background:var(--surface2);padding:2px 6px;border-radius:4px;font-size:10px;color:var(--accent2);font-family:monospace}
/* summary header */
.inv-summary-header{padding:18px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px}
.inv-meta{font-size:12px;color:var(--text2);line-height:1.9}
.inv-meta strong{color:var(--text)}
/* stat row */
.stat-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:24px}
.stat{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 16px;display:flex;align-items:center;gap:12px}
.stat-ico{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.stat-val{font-size:18px;font-weight:700;color:var(--text);line-height:1}
.stat-lbl{font-size:10px;color:var(--text3);margin-top:2px}
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

<div class="process-wrapper" style="max-width:1300px">
<h1><i class="fas fa-boxes" style="color:var(--accent);margin-right:10px"></i>Conduct Product Inventory</h1>
<p class="subtitle">View all products, update counted quantities, and complete the inventory count</p>

<?php if($message): ?>
<div class="alert alert-<?php echo $message_type; ?>">
<i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i>
<?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- ── Stats ── -->
<div class="stat-row">
<div class="stat"><div class="stat-ico" style="background:rgba(79,255,176,.1);color:var(--accent)"><i class="fas fa-pills"></i></div><div><div class="stat-val"><?php echo count($all_products); ?></div><div class="stat-lbl">Total Products</div></div></div>
<div class="stat"><div class="stat-ico" style="background:rgba(248,113,113,.1);color:var(--danger)"><i class="fas fa-exclamation-triangle"></i></div><div><div class="stat-val"><?php echo count(array_filter($all_products,fn($p)=>$p['current_stock']<=$p['reorder_level'])); ?></div><div class="stat-lbl">Low / Out of Stock</div></div></div>
<div class="stat"><div class="stat-ico" style="background:rgba(56,189,248,.1);color:var(--accent2)"><i class="fas fa-dollar-sign"></i></div><div><div class="stat-val">₱<?php echo number_format($total_system_value,2); ?></div><div class="stat-lbl">System Stock Value</div></div></div>
<?php if($active_id): ?>
<div class="stat"><div class="stat-ico" style="background:rgba(167,139,250,.1);color:var(--accent3)"><i class="fas fa-clipboard-check"></i></div><div><div class="stat-val"><?php echo count($counted_map); ?></div><div class="stat-lbl">Items Counted</div></div></div>
<div class="stat"><div class="stat-ico" style="background:rgba(79,255,176,.1);color:var(--accent)"><i class="fas fa-hand-holding-usd"></i></div><div><div class="stat-val">₱<?php echo number_format($count_total_value,2); ?></div><div class="stat-lbl">Counted On-Hand Value</div></div></div>
<?php endif; ?>
</div>

<?php if(!$active_id): ?>
<!-- ── Start count ── -->
<div class="content-section">
<h2><i class="fas fa-play" style="color:var(--accent);margin-right:8px"></i>Start New Inventory Count</h2>
<p style="font-size:13px;color:var(--text2);margin-bottom:16px">Starting a count lets you update the "Counted Qty" column for each product below.</p>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
<input type="hidden" name="action" value="start_count">
<div class="form-group"><label>Notes <span style="color:var(--text3);font-weight:400">(optional)</span></label><textarea name="notes" rows="2" placeholder="e.g. Monthly count, spot check…"></textarea></div>
<button type="submit" class="btn btn-primary"><i class="fas fa-play"></i> Start Inventory Count</button>
</form>
</div>
<?php else: ?>
<!-- ── Active count controls ── -->
<div class="active-banner" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px">
<div>
<strong style="color:var(--accent);font-size:14px"><i class="fas fa-circle" style="font-size:8px;margin-right:6px"></i>Active Count #<?php echo $active_id; ?></strong>
<p style="margin-top:4px;color:var(--text2);font-size:12px">Edit the <strong style="color:var(--accent)">Counted Qty</strong> column for each product. Click "Save All" when done, then complete the count.</p>
</div>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<form method="POST" onsubmit="return confirm('Cancel and discard this count?')">
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
<input type="hidden" name="action" value="cancel_count">
<input type="hidden" name="inventory_id" value="<?php echo $active_id; ?>">
<button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Cancel</button>
</form>
<button type="submit" form="bulk-save-form" class="btn btn-sm" style="background:rgba(56,189,248,.1);color:var(--accent2);border:1px solid rgba(56,189,248,.3)"><i class="fas fa-save"></i> Save All</button>
<form method="POST" onsubmit="return confirm('Mark this count as complete?')">
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
<input type="hidden" name="action" value="complete_count">
<input type="hidden" name="inventory_id" value="<?php echo $active_id; ?>">
<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Complete Count</button>
</form>
</div>
</div>
<?php endif; ?>

<!-- ── Inventory Summary Table ── -->
<div class="content-section" style="padding:0;overflow:hidden">
<div class="inv-summary-header">
<div>
<div style="font-size:16px;font-weight:700;color:var(--text)">Inventory Summary</div>
<div class="inv-meta" style="margin-top:6px">
<strong>Date:</strong> <?php echo date('F d, Y'); ?> &nbsp;·&nbsp;
<strong>Conducted by:</strong> <?php echo htmlspecialchars(($_SESSION['user_first_name']??'').' '.($_SESSION['user_last_name']??'')); ?>
<?php if($active_id): ?> &nbsp;·&nbsp; <strong>Count #:</strong> <?php echo $active_id; ?><?php endif; ?>
</div>
</div>
<div style="text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:12px">
<div>
<div style="font-size:11px;color:var(--text3)">Total On-Hand Value</div>
<div style="font-size:20px;font-weight:700;color:var(--accent)">₱<?php echo number_format($active_id ? $count_total_value : $total_system_value, 2); ?></div>
</div>
<?php if($active_id): ?>
<button type="button" onclick="generateReport(<?php echo $active_id; ?>)" class="btn btn-primary btn-sm" style="font-size:12px;padding:8px 14px">
<i class="fas fa-file-pdf"></i> Generate Report
</button>
<?php endif; ?>
</div>
</div>

<?php if($active_id): ?>
<form method="POST" id="bulk-save-form">
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
<input type="hidden" name="action" value="save_all">
<input type="hidden" name="inventory_id" value="<?php echo $active_id; ?>">
<?php endif; ?>

<div class="inv-wrap">
<table class="inv-tbl">
<thead>
<tr>
<th>#</th>
<th>Product Code</th>
<th>Trade Name</th>
<th>Generic Name</th>
<th>Description</th>
<th>Form</th>
<th class="r">Pack Size</th>
<th>Manufacturer</th>
<th>Category</th>
<th class="r">Re-Order<br>Point</th>
<th class="r">Counted<br>Qty</th>
<?php if($active_id): ?><th class="r">Variance</th><?php endif; ?>
<th class="r">Cost Price</th>
<th class="r">Unit Price</th>
<th class="r">On Hand<br>Value</th>
<?php if($active_id): ?><th style="text-align:center">Save</th><?php endif; ?>
</tr>
</thead>
<tbody>
<?php foreach($all_products as $i => $p):
    $counted_qty = isset($counted_map[$p['id']]) ? $counted_map[$p['id']]['qty'] : $p['current_stock'];
    $item_id     = isset($counted_map[$p['id']]) ? $counted_map[$p['id']]['item_id'] : null;
    $variance    = $counted_qty - $p['current_stock'];
    $var_class   = $variance < 0 ? 'var-neg' : ($variance > 0 ? 'var-pos' : 'var-zero');
    $on_hand_val = $counted_qty * $p['cost_price'];
    $is_low      = $p['current_stock'] <= $p['reorder_level'];
?>
<tr id="row-<?php echo $p['id']; ?>">
<td style="color:var(--text3)"><?php echo $i+1; ?></td>
<td><span class="code"><?php echo htmlspecialchars($p['product_code']); ?></span></td>
<td style="font-weight:600;white-space:nowrap"><?php echo htmlspecialchars($p['product_name']); ?></td>
<td style="color:var(--text2)"><?php echo htmlspecialchars($p['generic_name']); ?></td>
<td style="color:var(--text3);font-size:11px;max-width:160px"><?php echo htmlspecialchars(substr($p['description'],0,60)).(strlen($p['description'])>60?'…':''); ?></td>
<td style="color:var(--text2)"><?php echo htmlspecialchars($p['form']); ?></td>
<td class="r" style="color:var(--text2)"><?php echo $p['pack_size']; ?></td>
<td style="color:var(--text3);font-size:11px"><?php echo htmlspecialchars($p['manufacturer_name']); ?></td>
<td><span style="background:var(--surface2);padding:2px 7px;border-radius:4px;font-size:10px;color:var(--text3)"><?php echo htmlspecialchars($p['category']??'—'); ?></span></td>
<td class="r" style="color:var(--text3)"><?php echo $p['reorder_level']; ?></td>
<td class="r">
<?php if($active_id): ?>
<input type="number" class="qty-input" name="qtys[<?php echo $p['id']; ?>]"
    id="qty-<?php echo $p['id']; ?>"
    value="<?php echo $counted_qty; ?>" min="0"
    onchange="markChanged(<?php echo $p['id']; ?>)">
<?php else: ?>
<span style="color:var(--text2)"><?php echo $p['current_stock']; ?></span>
<?php endif; ?>
</td>
<?php if($active_id): ?>
<td class="r <?php echo $var_class; ?>" id="var-<?php echo $p['id']; ?>">
<?php echo ($variance >= 0 ? '+' : '').$variance; ?>
</td>
<?php endif; ?>
<td class="r" style="color:var(--text2)">₱<?php echo number_format($p['cost_price'],4); ?></td>
<td class="r" style="color:var(--text2)">₱<?php echo number_format($p['unit_price'],4); ?></td>
<td class="r" style="font-weight:600;color:var(--accent)" id="val-<?php echo $p['id']; ?>">
₱<?php echo number_format($on_hand_val,4); ?>
</td>
<?php if($active_id): ?>
<td style="text-align:center">
<button type="button" class="save-btn" id="save-<?php echo $p['id']; ?>"
    onclick="saveSingle(<?php echo $p['id']; ?>,<?php echo $p['cost_price']; ?>)"
    style="opacity:.4;pointer-events:none">
<i class="fas fa-save"></i>
</button>
</td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr>
<td colspan="<?php echo $active_id ? 14 : 12; ?>" style="text-align:right;font-size:13px;color:var(--text2)">
<strong>Total On-Hand Value:</strong>
</td>
<td class="r" style="font-size:15px;color:var(--accent)" id="grand-total">
₱<?php echo number_format($active_id ? $count_total_value : $total_system_value, 4); ?>
</td>
<?php if($active_id): ?><td></td><?php endif; ?>
</tr>
</tfoot>
</table>
</div>

<?php if($active_id): ?>
</form><!-- end bulk-save-form -->
<?php endif; ?>
</div>

<!-- ── Past counts ── -->
<?php if(!empty($my_counts)): ?>
<div class="content-section" style="margin-top:20px">
<h2><i class="fas fa-history" style="color:var(--accent2);margin-right:8px"></i>My Inventory Counts</h2>
<table class="submission-table">
<thead><tr><th>Count #</th><th>Started</th><th>Completed</th><th>Notes</th><th>Status</th><th style="text-align:center">Action</th></tr></thead>
<tbody>
<?php foreach($my_counts as $c): $slug=strtolower(str_replace(' ','-',$c['status'])); ?>
<tr>
<td style="font-weight:600">#<?php echo $c['id']; ?></td>
<td style="color:var(--text2)"><?php echo date('M d, Y H:i',strtotime($c['created_at'])); ?></td>
<td style="color:var(--text2)"><?php echo $c['completed_at']?date('M d, Y H:i',strtotime($c['completed_at'])):'—'; ?></td>
<td style="color:var(--text3);font-size:12px"><?php echo $c['notes']?htmlspecialchars(substr($c['notes'],0,40)):'—'; ?></td>
<td><span class="status-badge status-<?php echo $slug; ?>"><?php echo $c['status']; ?></span></td>
<td style="text-align:center">
<?php if($c['status']==='In Progress' && !$active_id): ?>
<form method="POST" style="display:inline">
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
<input type="hidden" name="action" value="resume_count">
<input type="hidden" name="inventory_id" value="<?php echo $c['id']; ?>">
<button type="submit" class="btn btn-sm" style="background:rgba(56,189,248,.1);color:var(--accent2);border:1px solid rgba(56,189,248,.3)"><i class="fas fa-play"></i> Resume</button>
</form>
<?php else: ?><span style="color:var(--text3);font-size:12px">—</span><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<!-- ── Add Product button (active count only) ── -->
<?php if($active_id): ?>
<div style="margin-top:16px;margin-bottom:4px">
    <button onclick="document.getElementById('addProductModal').classList.add('show')" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add Product to Count
    </button>
</div>
<?php endif; ?>

<div style="margin-top:20px">
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>
</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>

<!-- ── Add Product Modal ── -->
<?php if($active_id): ?>
<div id="addProductModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px)" onclick="if(event.target===this)this.classList.remove('show')">
<style>#addProductModal.show{display:flex!important}</style>
<div style="background:var(--surface);border:1px solid var(--border2);border-radius:14px;padding:28px;width:90%;max-width:620px;max-height:90vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,.5)">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px">
        <div style="font-size:17px;font-weight:700;color:var(--text)">
            <i class="fas fa-plus-circle" style="color:var(--accent);margin-right:8px"></i>Add to Inventory Count
        </div>
        <button onclick="document.getElementById('addProductModal').classList.remove('show')" style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text2);width:32px;height:32px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center">&times;</button>
    </div>

    <form method="POST" id="add-product-form">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="add_new_product_to_count">
        <input type="hidden" name="inventory_id" value="<?php echo $active_id; ?>">

        <!-- Row 1: Trade Name + Generic Name -->
        <div class="grid-2">
            <div class="form-group">
                <label>Trade Name <span class="required">*</span></label>
                <input type="text" name="product_name" required placeholder="e.g. Amoxicillin 500mg">
            </div>
            <div class="form-group">
                <label>Generic Name</label>
                <input type="text" name="generic_name" placeholder="e.g. Amoxicillin">
            </div>
        </div>

        <!-- Row 2: Form + Pack Size -->
        <div class="grid-2">
            <div class="form-group">
                <label>Form</label>
                <input type="text" name="form" placeholder="e.g. Capsule, Tablet, Syrup">
            </div>
            <div class="form-group">
                <label>Pack Size</label>
                <input type="number" name="pack_size" min="1" placeholder="e.g. 100">
            </div>
        </div>

        <!-- Row 3: Manufacturer + Category -->
        <div class="grid-2">
            <div class="form-group">
                <label>Manufacturer</label>
                <input type="text" name="manufacturer_name" placeholder="e.g. Pharma Plus Co.">
            </div>
            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category" placeholder="e.g. Antibiotics">
            </div>
        </div>

        <!-- Row 4: System Stock + Re-Order Point -->
        <div class="grid-2">
            <div class="form-group">
                <label>System Stock (On Hand) <span class="required">*</span></label>
                <input type="number" name="current_stock" min="0" required placeholder="e.g. 250" oninput="calcAddValue()">
            </div>
            <div class="form-group">
                <label>Re-Order Point</label>
                <input type="number" name="reorder_level" min="0" placeholder="e.g. 50">
            </div>
        </div>

        <!-- Row 5: Cost Price + Unit Price -->
        <div class="grid-2">
            <div class="form-group">
                <label>Cost Price (₱) <span class="required">*</span></label>
                <input type="number" name="cost_price" step="0.0001" min="0" required placeholder="e.g. 2.5000" oninput="calcAddValue()">
            </div>
            <div class="form-group">
                <label>Unit Price (₱)</label>
                <input type="number" name="unit_price" step="0.0001" min="0" placeholder="e.g. 5.9900">
            </div>
        </div>

        <!-- Description -->
        <div class="form-group">
            <label>Description</label>
            <input type="text" name="description" placeholder="e.g. Pain reliever and fever reducer">
        </div>

        <!-- Counted Qty + On Hand Value -->
        <div class="grid-2">
            <div class="form-group">
                <label>Counted Qty <span class="required">*</span></label>
                <input type="number" name="counted_qty" min="0" required placeholder="0" oninput="calcAddValue()">
            </div>
            <div class="form-group">
                <label>On Hand Value</label>
                <div id="add-onhand-value" style="padding:10px 14px;background:var(--surface3);border:1px solid var(--border);border-radius:8px;font-size:14px;font-weight:700;color:var(--accent);min-height:42px;display:flex;align-items:center">₱0.0000</div>
            </div>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
            <button type="button" onclick="document.getElementById('addProductModal').classList.remove('show')" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add to Inventory</button>
        </div>
    </form>
</div>
</div>
<?php endif; ?>

<script>
var costPrices = <?php echo json_encode(array_column($all_products, 'cost_price', 'id')); ?>;
var systemStock = <?php echo json_encode(array_column($all_products, 'current_stock', 'id')); ?>;

function markChanged(pid) {
    var btn = document.getElementById('save-' + pid);
    if (btn) { btn.style.opacity = '1'; btn.style.pointerEvents = 'auto'; }
    updateRow(pid);
}

function updateRow(pid) {
    var input = document.getElementById('qty-' + pid);
    if (!input) return;
    var qty  = parseInt(input.value) || 0;
    var cost = parseFloat(costPrices[pid]) || 0;
    var sys  = parseInt(systemStock[pid]) || 0;
    var variance = qty - sys;
    var val = qty * cost;

    var varEl = document.getElementById('var-' + pid);
    if (varEl) {
        varEl.textContent = (variance >= 0 ? '+' : '') + variance;
        varEl.className = 'r ' + (variance < 0 ? 'var-neg' : variance > 0 ? 'var-pos' : 'var-zero');
    }
    var valEl = document.getElementById('val-' + pid);
    if (valEl) valEl.textContent = '₱' + val.toFixed(4);

    updateGrandTotal();
}

function updateGrandTotal() {
    var total = 0;
    document.querySelectorAll('.qty-input').forEach(function(inp) {
        var pid  = inp.name.match(/\d+/)[0];
        var qty  = parseInt(inp.value) || 0;
        var cost = parseFloat(costPrices[pid]) || 0;
        total += qty * cost;
    });
    var el = document.getElementById('grand-total');
    if (el) el.textContent = '₱' + total.toFixed(4);
}

function saveSingle(pid, cost) {
    var qty = parseInt(document.getElementById('qty-' + pid).value) || 0;
    var form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML =
        '<input name="csrf_token" value="<?php echo generateCSRFToken(); ?>">' +
        '<input name="action" value="save_qty">' +
        '<input name="inventory_id" value="<?php echo $active_id; ?>">' +
        '<input name="product_id" value="' + pid + '">' +
        '<input name="quantity" value="' + qty + '">';
    document.body.appendChild(form);
    form.submit();
}

// Live update on any qty change
document.querySelectorAll('.qty-input').forEach(function(inp) {
    inp.addEventListener('input', function() {
        var pid = this.name.match(/\d+/)[0];
        markChanged(parseInt(pid));
    });
});

function calcAddValue() {
    var qtyEl  = document.querySelector('#add-product-form [name="counted_qty"]');
    var costEl = document.querySelector('#add-product-form [name="cost_price"]');
    var qty    = parseFloat(qtyEl  ? qtyEl.value  : 0) || 0;
    var cost   = parseFloat(costEl ? costEl.value : 0) || 0;
    var el = document.getElementById('add-onhand-value');
    if (el) el.textContent = '₱' + (qty * cost).toFixed(4);
}

// ── Add Product Modal ──
function fillProductDetails(sel) {
    var opt = sel.options[sel.selectedIndex];
    var det = document.getElementById('modal-product-details');
    if (!opt.value) { det.style.display='none'; return; }
    det.style.display = 'block';
    document.getElementById('d-generic').textContent  = opt.dataset.generic  || '—';
    document.getElementById('d-form').textContent     = opt.dataset.form     || '—';
    document.getElementById('d-pack').textContent     = opt.dataset.pack     || '—';
    document.getElementById('d-mfr').textContent      = opt.dataset.mfr      || '—';
    document.getElementById('d-stock').textContent    = opt.dataset.stock    || '0';
    document.getElementById('d-reorder').textContent  = opt.dataset.reorder  || '—';
    document.getElementById('d-cost').textContent     = '₱' + parseFloat(opt.dataset.cost||0).toFixed(4);
    document.getElementById('d-unit').textContent     = '₱' + parseFloat(opt.dataset.unit||0).toFixed(4);
    document.getElementById('d-desc').textContent     = opt.dataset.desc     || '';
    calcModalValue();
}

function calcModalValue() {
    var sel  = document.getElementById('modal-product-select');
    var opt  = sel.options[sel.selectedIndex];
    var qty  = parseInt(document.getElementById('modal-qty').value) || 0;
    var cost = parseFloat((opt && opt.dataset.cost) || 0);
    document.getElementById('modal-value').textContent = '₱' + (qty * cost).toFixed(4);
}

// ── Generate Report Modal ──
function generateReport(inventoryId) {
    const modal = document.getElementById('generateReportModal');
    const tableBody = document.getElementById('reportTableBody');
    const reportHeader = document.getElementById('reportHeader');
    
    // Store inventory ID for submission
    document.getElementById('currentInventoryId').value = inventoryId;
    
    // Show loading state
    modal.style.display = 'flex';
    tableBody.innerHTML = '<tr><td colspan="14" style="text-align:center;padding:20px;color:var(--text3)"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
    
    // Fetch inventory details
    fetch('<?php echo APP_URL; ?>/api/get_inventory_items.php?inventory_id=' + inventoryId)
        .then(response => {
            if (!response.ok) {
                throw new Error('API Error: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('API Response:', data);
            
            if (!data.success) {
                tableBody.innerHTML = '<tr><td colspan="14" style="text-align:center;padding:20px;color:var(--danger)"><i class="fas fa-exclamation-circle"></i> Error: ' + (data.message || 'Unknown error') + '</td></tr>';
                return;
            }
            
            if (!data.items || data.items.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="14" style="text-align:center;padding:20px;color:var(--warn)"><i class="fas fa-inbox"></i> No items have been counted yet. Please add items to the inventory count and save before generating a report.</td></tr>';
                reportHeader.innerHTML = '<div style="padding:12px;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);border-radius:6px;color:var(--warn);font-size:12px"><i class="fas fa-info-circle"></i> Please complete the inventory count first.</div>';
                return;
            }
            
            // Set header info
            const inv = data.inventory;
            const reportDate = new Date(inv.completed_at || inv.created_at);
            const formattedDate = reportDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            const conductor = inv.first_name + ' ' + inv.last_name;
            
            reportHeader.innerHTML = `
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
                    <div>
                        <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:8px">Inventory Summary Report</div>
                        <div style="font-size:12px;color:var(--text2);margin-bottom:4px">
                            <strong>Date:</strong> ${formattedDate}
                        </div>
                        <div style="font-size:12px;color:var(--text2);margin-bottom:4px">
                            <strong>Conducted by:</strong> ${conductor}
                        </div>
                        <div style="font-size:12px;color:var(--text2)">
                            <strong>Inventory Count #:</strong> ${inv.id}
                        </div>
                    </div>
                    <div style="text-align:right">
                        <div style="font-size:11px;color:var(--text3);margin-bottom:4px">Total On-Hand Value</div>
                        <div style="font-size:20px;font-weight:700;color:var(--accent)">₱${parseFloat(data.totals.total_cost).toFixed(2)}</div>
                        <div style="font-size:11px;color:var(--text3);margin-top:12px">
                            <div style="margin-bottom:4px">Total Products: <strong style="color:var(--text)">${data.totals.product_count}</strong></div>
                            <div>Total Items: <strong style="color:var(--text)">${data.totals.total_items}</strong></div>
                        </div>
                    </div>
                </div>
            `;
            
            // Populate table rows
            let html = '';
            let rowNum = 1;
            data.items.forEach(item => {
                const variance = item.counted_qty - item.current_stock;
                const varianceClass = variance > 0 ? 'color:var(--accent)' : (variance < 0 ? 'color:var(--danger)' : 'color:var(--text3)');
                const onHandValue = (item.cost_price * item.counted_qty).toFixed(2);
                
                html += `
                    <tr style="border-bottom:1px solid var(--border)">
                        <td style="padding:10px;color:var(--text3);text-align:center">${rowNum}</td>
                        <td style="padding:10px;color:var(--accent);font-weight:600;font-family:monospace">${item.product_code}</td>
                        <td style="padding:10px;color:var(--text);font-weight:600">${item.product_name}</td>
                        <td style="padding:10px;color:var(--text2)">${item.generic_name || '-'}</td>
                        <td style="padding:10px;color:var(--text2)">${item.form || '-'}</td>
                        <td style="padding:10px;color:var(--text2);text-align:center">${item.manufacturer_name || '-'}</td>
                        <td style="padding:10px;color:var(--text2);text-align:center">${item.category || '-'}</td>
                        <td style="padding:10px;text-align:center;color:var(--text)">${item.current_stock}</td>
                        <td style="padding:10px;text-align:center;color:var(--text3)">${item.reorder_level}</td>
                        <td style="padding:10px;text-align:center;color:var(--accent);font-weight:700">${item.counted_qty}</td>
                        <td style="padding:10px;text-align:center;font-weight:700;${varianceClass}">${variance > 0 ? '+' : ''}${variance}</td>
                        <td style="padding:10px;text-align:right;color:var(--text2)">₱${parseFloat(item.cost_price).toFixed(4)}</td>
                        <td style="padding:10px;text-align:right;color:var(--text2)">₱${parseFloat(item.unit_price).toFixed(4)}</td>
                        <td style="padding:10px;text-align:right;color:var(--accent);font-weight:600">₱${onHandValue}</td>
                    </tr>
                `;
                rowNum++;
            });
            
            tableBody.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading inventory details:', error);
            tableBody.innerHTML = '<tr><td colspan="14" style="text-align:center;padding:20px;color:var(--danger)"><i class="fas fa-exclamation-circle"></i> Error: ' + error.message + '</td></tr>';
        });
}

function submitReportToTechnician() {
    const inventoryId = document.getElementById('currentInventoryId').value;
    
    if (!inventoryId) {
        alert('Error: Inventory ID not found');
        return;
    }
    
    // Show confirmation
    if (!confirm('Are you sure you want to submit this inventory report to the pharmacy technician for review?')) {
        return;
    }
    
    // Disable button to prevent double submission
    const submitBtn = event.target;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
    // Fetch the total items count from the API
    fetch('<?php echo APP_URL; ?>/api/get_inventory_items.php?inventory_id=' + inventoryId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.totals) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?php echo APP_URL; ?>/views/processes/intern_inventory_report.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'submit_report';
                
                const inventoryInput = document.createElement('input');
                inventoryInput.type = 'hidden';
                inventoryInput.name = 'inventory_id';
                inventoryInput.value = inventoryId;
                
                const totalItemsInput = document.createElement('input');
                totalItemsInput.type = 'hidden';
                totalItemsInput.name = 'total_items';
                totalItemsInput.value = data.totals.total_items;
                
                const detailsInput = document.createElement('input');
                detailsInput.type = 'hidden';
                detailsInput.name = 'details';
                detailsInput.value = 'Inventory summary report generated from conduct inventory page';
                
                form.appendChild(actionInput);
                form.appendChild(inventoryInput);
                form.appendChild(totalItemsInput);
                form.appendChild(detailsInput);
                
                document.body.appendChild(form);
                form.submit();
            } else {
                alert('Error: Could not retrieve inventory data');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit to Technician';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error submitting report: ' + error.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit to Technician';
        });
}

function closeReportModal() {
    document.getElementById('generateReportModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('generateReportModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReportModal();
    }
});

function printReport() {
    window.print();
}
</script>
<!-- ── Generate Report Modal ── -->
<div id="generateReportModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:24px;max-width:1400px;width:100%;max-height:90vh;overflow-y:auto">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <h3 style="font-size:16px;font-weight:700;color:var(--text);margin:0">Inventory Summary Report</h3>
      <div style="display:flex;gap:8px">
        <button type="button" onclick="printReport()" class="btn btn-secondary btn-sm" style="font-size:11px;padding:8px 12px">
          <i class="fas fa-print"></i> Print
        </button>
        <button type="button" onclick="closeReportModal()" style="background:none;border:none;font-size:20px;color:var(--text3);cursor:pointer;padding:0;width:24px;height:24px;display:flex;align-items:center;justify-content:center">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>

    <!-- Report Header -->
    <div id="reportHeader" style="margin-bottom:20px;padding-bottom:16px;border-bottom:2px solid var(--border)"></div>

    <!-- Hidden field to store inventory ID -->
    <input type="hidden" id="currentInventoryId" value="">

    <!-- Inventory Table -->
    <div style="overflow-x:auto;border:1px solid var(--border);border-radius:10px">
      <table style="width:100%;border-collapse:collapse;font-size:12px">
        <thead>
          <tr style="background:var(--surface2);border-bottom:2px solid var(--border)">
            <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px">#</th>
            <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px">DIN</th>
            <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px">Trade Name</th>
            <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px">Generic Name</th>
            <th style="padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px">Form</th>
            <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px">Mfr</th>
            <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px">Category</th>
            <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px">On Hand</th>
            <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px">Re-Order</th>
            <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px">Qty</th>
            <th style="padding:10px;text-align:center;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px">Variance</th>
            <th style="padding:10px;text-align:right;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px">Cost Price</th>
            <th style="padding:10px;text-align:right;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px">Unit Price</th>
            <th style="padding:10px;text-align:right;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px">On Hand Value</th>
          </tr>
        </thead>
        <tbody id="reportTableBody">
          <!-- Rows will be populated here -->
        </tbody>
      </table>
    </div>

    <div style="margin-top:20px;display:flex;gap:12px;justify-content:flex-end">
      <button type="button" onclick="closeReportModal()" class="btn btn-secondary"><i class="fas fa-times"></i> Close</button>
      <button type="button" onclick="submitReportToTechnician()" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit to Technician</button>
    </div>
  </div>
</div>

</body>
</html>
