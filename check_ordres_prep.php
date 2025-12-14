<?php
require_once 'security.php';
global $pdo;

echo "=== ORDRES_PREPARATION TABLE STRUCTURE ===\n";
$stmt = $pdo->query('DESCRIBE ordres_preparation');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== SAMPLE ORDRE ===\n";
$stmt = $pdo->query('SELECT * FROM ordres_preparation LIMIT 1');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    foreach (array_keys($row) as $field) {
        echo "  - $field\n";
    }
}
