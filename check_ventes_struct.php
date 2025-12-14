<?php
require_once 'security.php';
global $pdo;

echo "=== VENTES TABLE STRUCTURE ===\n";
$stmt = $pdo->query('DESCRIBE ventes');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== SAMPLE VENTE ===\n";
$stmt = $pdo->query('SELECT * FROM ventes LIMIT 1');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    foreach (array_keys($row) as $field) {
        echo "  - $field\n";
    }
}
