<?php
require_once 'security.php';
global $pdo;

echo "=== UTILISATEUR_ROLE TABLE ===\n";
$stmt = $pdo->query('DESCRIBE utilisateur_role');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\nSample data:\n";
$stmt = $pdo->query('SELECT * FROM utilisateur_role LIMIT 3');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}

echo "\n=== CHECK JOURNAL_CAISSE FOR SOURCE TRACKING ===\n";
$stmt = $pdo->query('SELECT * FROM journal_caisse LIMIT 1');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo "Available fields in journal_caisse:\n";
    foreach (array_keys($row) as $field) {
        echo "  - $field\n";
    }
}
