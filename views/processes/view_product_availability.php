<?php
require_once __DIR__ . '/../../config/config.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');

$products = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, m.manufacturer_name,
               CASE 
                   WHEN p.current_stock <= p.reorder_level THEN 'Low'
                   WHEN p.current_stock <= (p.reorder_level * 1.5) THEN 'Medium'
                   ELSE 'Adequate'
               END as stock_status
        FROM products p
        LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
        WHERE p.is_active = 1
        ORDER BY p.product_name
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $products = [];
}

// Calculate statistics
$low_stock_count = 0;
$medium_stock_count = 0;
$adequate_stock_count = 0;

foreach ($products as $p) {
    if ($p['stock_status'] === 'Low') $low_stock_count++;
    elseif ($p['stock_status'] === 'Medium') $medium_stock_count++;
    else $ad