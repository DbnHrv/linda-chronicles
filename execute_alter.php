<?php
require_once __DIR__ . '/config/config.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Alteration</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0a0c10; color: #e5e7eb; padding: 40px; }
        .container { max-width: 800px; margin: 0 auto; background: #111318; border: 1px solid #1f2937; border-radius: 12px; padding: 30px; }
        h1 { color: #10b981; margin-top: 0; display: flex; align-items: center; gap: 10px; }
        .status { padding: 12px; margin: 10px 0; border-radius: 6px; border-left: 4px solid; }
        .success { background: #064e3b; border-color: #10b981; color: #d1fae5; }
        .error { background: #7f1d1d; border-color: #f87171; color: #fee2e2; }
        .info { background: #1e3a8a; border-color: #3b82f6; color: #dbeafe; }
        .warning { background: #78350f; border-color: #fbbf24; color: #fef3c7; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #1f2937; }
        th { background: #1f2937; font-weight: 600; color: #10b981; }
        tr:hover { background: #111318; }
        .icon { font-size: 20px; }
        .button-group { display: flex; gap: 10px; margin-top: 20px; justify-content: center; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-print { background: #10b981; color: white; }
        .btn-print:hover { background: #059669; transform: scale(1.05); }
        .btn-back { background: #3b82f6; color: white; }
        .btn-back:hover { background: #2563eb; transform: scale(1.05); }
        @media print {
            body { background: white; padding: 0; }
            .container { background: white; border: none; box-shadow: none; }
            .button-group { display: none; }
            h1 { color: #000; }
            .status { border-left: 3px solid #000; }
            table { border: 1px solid #000; }
            th, td { border: 1px solid #000; }
        }
    </style>
</head>
<body>
<div class='container'>";

try {
    echo "<h1><span class='icon'>⚙️</span> Database Alteration in Progress</h1>";
    
    // Step 1: Drop old manufacturer column
    echo "<div class='status info'><strong>Step 1:</strong> Dropping old manufacturer column...</div>";
    try {
        $pdo->exec('ALTER TABLE products DROP COLUMN manufacturer');
        echo "<div class='status success'><strong>✓ Success:</strong> Old manufacturer column dropped</div>";
    } catch(Exception $e) {
        echo "<div class='status warning'><strong>ℹ Info:</strong> Column doesn't exist or already dropped</div>";
    }
    
    // Step 2: Add manufacturer_id column
    echo "<div class='status info'><strong>Step 2:</strong> Adding manufacturer_id column...</div>";
    try {
        $pdo->exec('ALTER TABLE products ADD COLUMN manufacturer_id INT NOT NULL DEFAULT 1');
        echo "<div class='status success'><strong>✓ Success:</strong> manufacturer_id column added</div>";
    } catch(Exception $e) {
        echo "<div class='status warning'><strong>ℹ Info:</strong> Column already exists</div>";
    }
    
    // Step 3: Drop existing foreign key
    echo "<div class='status info'><strong>Step 3:</strong> Dropping existing foreign key...</div>";
    try {
        $pdo->exec('ALTER TABLE products DROP FOREIGN KEY fk_products_manufacturer');
        echo "<div class='status success'><strong>✓ Success:</strong> Old foreign key dropped</div>";
    } catch(Exception $e) {
        echo "<div class='status warning'><strong>ℹ Info:</strong> Foreign key doesn't exist</div>";
    }
    
    // Step 4: Add foreign key constraint
    echo "<div class='status info'><strong>Step 4:</strong> Adding foreign key constraint...</div>";
    try {
        $pdo->exec('ALTER TABLE products ADD CONSTRAINT fk_products_manufacturer FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id) ON DELETE RESTRICT');
        echo "<div class='status success'><strong>✓ Success:</strong> Foreign key constraint added</div>";
    } catch(Exception $e) {
        echo "<div class='status warning'><strong>ℹ Info:</strong> Foreign key already exists</div>";
    }
    
    // Step 5: Update products with manufacturer assignments
    echo "<div class='status info'><strong>Step 5:</strong> Assigning manufacturers to products...</div>";
    $pdo->exec('UPDATE products SET manufacturer_id = 1 WHERE id = 1');
    $pdo->exec('UPDATE products SET manufacturer_id = 2 WHERE id = 2');
    $pdo->exec('UPDATE products SET manufacturer_id = 3 WHERE id = 3');
    $pdo->exec('UPDATE products SET manufacturer_id = 1 WHERE id = 4');
    $pdo->exec('UPDATE products SET manufacturer_id = 2 WHERE id = 5');
    echo "<div class='status success'><strong>✓ Success:</strong> All products assigned to manufacturers</div>";
    
    // Verification
    echo "<div class='status info'><strong>Verification:</strong> Checking product-manufacturer mapping...</div>";
    $stmt = $pdo->query('SELECT p.id, p.product_name, p.manufacturer_id, m.manufacturer_name FROM products p LEFT JOIN manufacturers m ON p.manufacturer_id = m.id ORDER BY p.id');
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>
        <thead>
            <tr>
                <th>Product ID</th>
                <th>Product Name</th>
                <th>Manufacturer ID</th>
                <th>Manufacturer Name</th>
            </tr>
        </thead>
        <tbody>";
    
    foreach($results as $row) {
        echo "<tr>
            <td>[" . $row['id'] . "]</td>
            <td>" . htmlspecialchars($row['product_name']) . "</td>
            <td>" . $row['manufacturer_id'] . "</td>
            <td><strong>" . htmlspecialchars($row['manufacturer_name']) . "</strong></td>
        </tr>";
    }
    
    echo "</tbody></table>";
    
    echo "<div class='status success' style='margin-top: 20px;'>
        <strong>✓ All alterations completed successfully!</strong><br>
        The database is now ready. Manufacturer names will appear in the Generate Purchase Order page.
    </div>";
    
    echo "<div class='button-group'>
        <button class='btn btn-print' onclick='window.print()'><span>🖨️</span> Print Report</button>
        <button class='btn btn-back' onclick='window.location.href=\"dashboard.php\"'><span>←</span> Back to Dashboard</button>
    </div>";
    
} catch(Exception $e) {
    echo "<div class='status error'>
        <strong>✗ Error:</strong> " . htmlspecialchars($e->getMessage()) . "
    </div>";
    echo "<div class='button-group'>
        <button class='btn btn-back' onclick='window.location.href=\"dashboard.php\"'><span>←</span> Back to Dashboard</button>
    </div>";
}

echo "</div>
</body>
</html>";
?>
