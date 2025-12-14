<?php
require_once 'security.php';
global $pdo;

echo "=== UTILISATEURS COLUMNS ===\n";
$stmt = $pdo->query('DESCRIBE utilisateurs');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== JOURNAL_CAISSE COLUMNS ===\n";
$stmt = $pdo->query('DESCRIBE journal_caisse');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== BONS_LIVRAISON COLUMNS ===\n";
$stmt = $pdo->query('DESCRIBE bons_livraison');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
