<?php
require_once 'db/db.php';
$stmt = $pdo->query('DESCRIBE ordres_preparation');
echo "=== Structure table ordres_preparation ===\n";
while($row = $stmt->fetch()) {
    echo $row['Field'] . " - " . $row['Type'] . " - " . ($row['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . "\n";
}

echo "\n=== Ordres existants ===\n";
$stmt = $pdo->query('SELECT * FROM ordres_preparation ORDER BY id DESC LIMIT 5');
$ordres = $stmt->fetchAll();
if (empty($ordres)) {
    echo "Aucun ordre trouvé\n";
} else {
    foreach ($ordres as $ordre) {
        echo "ID: {$ordre['id']} - Numéro: {$ordre['numero_ordre']} - Date: {$ordre['date_ordre']} - Statut: {$ordre['statut']}\n";
    }
}
