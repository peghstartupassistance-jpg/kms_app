<?php
require_once __DIR__ . '/db/db.php';

echo "=== STRUCTURES CLIENTS ET PRODUITS ===\n\n";

echo "1. Table clients:\n";
$stmt = $pdo->query("DESCRIBE clients");
while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  - {$col['Field']}\n";
}

echo "\n2. Table produits:\n";
$stmt = $pdo->query("DESCRIBE produits");
while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  - {$col['Field']}\n";
}

echo "\n3. Table utilisateurs:\n";
$stmt = $pdo->query("DESCRIBE utilisateurs");
while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  - {$col['Field']}\n";
}
