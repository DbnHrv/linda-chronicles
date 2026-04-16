<?php
require_once __DIR__ . '/config/config.php';

try {
    // Check current products table structure
    $stmt = $pdo->query("DESCRIBE products");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current Products Table Structure:\n";
    foreach($columns as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    echo "\n\nCurrent Products Data:\n";
    $stmt = $pdo->query("SELECT p.id, p.product_name, p.manufacturer_id, m.manufacturer_name FROM products p LEFT JOIN manufacturers m ON p.manufacturer_id = m.id");
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "  - " . $row['product_name'] . " (ID: " . $row['id'] . ") → Mfg ID: " . $row['manufacturer_id'] . " (" . $row['manufacturer_name'] . ")\n";
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
