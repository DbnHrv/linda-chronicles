<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$inventory_id = $_GET['inventory_id'] ?? null;

if (!$inventory_id || !is_numeric($inventory_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid inventory ID']);
    exit;
}

try {
    // Get detailed inventory items with all product information
    $stmt = $pdo->prepare("
        SELECT 
            ii.id,
            ii.quantity as counted_qty,
            ii.batch_number,
            p.id as product_id,
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
    
    // Get inventory metadata
    $stmt = $pdo->prepare("
        SELECT ic.*, u.first_name, u.last_name
        FROM inventory_counts ic
        JOIN users u ON ic.conducted_by = u.id
        WHERE ic.id = ?
    ");
    $stmt->execute([$inventory_id]);
    $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $total_qty = 0;
    $total_cost = 0;
    foreach ($items as $item) {
        $total_qty += $item['counted_qty'];
        $total_cost += ($item['cost_price'] * $item['counted_qty']);
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'inventory' => $inventory,
        'totals' => [
            'total_items' => $total_qty,
            'total_cost' => $total_cost,
            'product_count' => count($items)
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching inventory data: ' . $e->getMessage()
    ]);
}
?>
