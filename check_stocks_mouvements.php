<?php
require_once 'security.php';
global $pdo;
echo "=== STOCKS_MOUVEMENTS TABLE ===\n";
$stmt = $pdo->query('DESCRIBE stocks_mouvements');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . "\n";
}

echo "\n=== SAMPLE MOUVEMENT ===\n";
$stmt = $pdo->query('SELECT * FROM stocks_mouvements LIMIT 1');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    foreach (array_keys($row) as $field) {
        echo "  - $field\n";
    }
}
