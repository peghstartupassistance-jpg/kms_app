<?php
require_once 'security.php';
global $pdo;

echo "=== JOURNAL_CAISSE TABLE ===\n";
$stmt = $pdo->query('DESCRIBE journal_caisse');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== MODES_PAIEMENT TABLE ===\n";
$stmt = $pdo->query('DESCRIBE modes_paiement');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== TABLES RELATED TO CAISSE ===\n";
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    if (stripos($table, 'caisse') !== false || stripos($table, 'cloture') !== false) {
        echo ">> $table\n";
    }
}

echo "\n=== SAMPLE JOURNAL CAISSE ===\n";
$stmt = $pdo->query('SELECT * FROM journal_caisse ORDER BY id DESC LIMIT 1');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    print_r($row);
}
