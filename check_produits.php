<?php
require_once __DIR__ . '/db/db.php';

echo "=== STRUCTURE DE LA TABLE produits ===\n\n";

$sql = "DESCRIBE produits";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n=== PREMIER PRODUIT ===\n";

$sql = "SELECT * FROM produits LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$produit = $stmt->fetch(PDO::FETCH_ASSOC);

var_dump($produit);
?>
