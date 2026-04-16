<?php
require_once __DIR__ . '/config/config.php';

// Test connection
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✓ Database connected\n\n";
} catch(Exception $e) {
    die("✗ Connection failed: " . $e->getMessage());
}

// Check current table structure
echo "Current Products Table Structure:\n";
$stmt = $pdo->query("DESCRIBE products");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $col) {
    if (in_array($col['Field'], ['id', 'product_name', 'manufacturer', 'manufacturer_id'])) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
}

echo "\nCurrent Products Data:\n";
$stmt = $pdo->query("SELECT id, product_name, manufacturer_id FROM products LIMIT 5");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "  [" . $row['id'] . "] " . $row['product_name'] . " (mfg_id: " . $row['manufacturer_id'] . ")\n";
}
?>
