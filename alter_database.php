<?php
require_once __DIR__ . '/config/config.php';

echo "<pre style='background:#1a1d23;color:#10b981;padding:20px;border-radius:8px;font-family:monospace;font-size:13px'>";

try {
    // Drop old manufacturer column if it exists
    try {
        $pdo->exec('ALTER TABLE products DROP COLUMN manufacturer');
        echo "✓ Dropped old manufacturer column\n";
    } catch(Exception $e) {
        echo "ℹ Column doesn't exist or already dropped\n";
    }

    // Add manufacturer_id column if it doesn't exist
    try {
        $pdo->exec('ALTER TABLE products ADD COLUMN manufacturer_id INT NOT NULL DEFAULT 1');
        echo "✓ Added manufacturer_id column\n";
    } catch(Exception $e) {
        echo "ℹ Column already exists\n";
    }

    // Drop existing foreign key if it exists
    try {
        $pdo->exec('ALTER TABLE products DROP FOREIGN KEY fk_products_manufacturer');
        echo "✓ Dropped old foreign key\n";
    } catch(Exception $e) {
        echo "ℹ Foreign key doesn't exist\n";
    }

    // Add foreign key constraint
    try {
        $pdo->exec('ALTER TABLE products ADD CONSTRAINT fk_products_manufacturer FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id) ON DELETE RESTRICT');
        echo "✓ Added foreign key constraint\n";
    } catch(Exception $e) {
        echo "ℹ Foreign key already exists\n";
    }

    // Update products with correct manufacturer_id
    $pdo->exec('UPDATE products SET manufacturer_id = 1 WHERE id = 1');
    $pdo->exec('UPDATE products SET manufacturer_id = 2 WHERE id = 2');
    $pdo->exec('UPDATE products SET manufacturer_id = 3 WHERE id = 3');
    $pdo->exec('UPDATE products SET manufacturer_id = 1 WHERE id = 4');
    $pdo->exec('UPDATE products SET manufacturer_id = 2 WHERE id = 5');
    echo "✓ Updated products with manufacturer assignments\n";

    // Verify the changes
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Product-Manufacturer Mapping:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    $stmt = $pdo->query('SELECT p.id, p.product_name, p.manufacturer_id, m.manufacturer_name FROM products p LEFT JOIN manufacturers m ON p.manufacturer_id = m.id ORDER BY p.id');
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "  [" . $row['id'] . "] " . str_pad($row['product_name'], 25) . " → " . $row['manufacturer_name'] . "\n";
    }

    echo "\n✓ Database alterations completed successfully!\n";
} catch(Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>
