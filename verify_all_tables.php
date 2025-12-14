<?php
require_once 'security.php';
global $pdo;

echo "=== CLIENTS TABLE ===\n";
$stmt = $pdo->query('DESCRIBE clients');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . "\n";
}

echo "\n=== BONS_LIVRAISON TABLE ===\n";
$stmt = $pdo->query('DESCRIBE bons_livraison');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . "\n";
}

echo "\n=== RETOURS_LITIGES TABLE ===\n";
$stmt = $pdo->query('DESCRIBE retours_litiges');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . "\n";
}
