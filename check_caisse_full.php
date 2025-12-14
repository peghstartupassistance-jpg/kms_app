<?php
require_once 'security.php';
global $pdo;

echo "=== CAISSE_JOURNAL TABLE ===\n";
try {
    $stmt = $pdo->query('DESCRIBE caisse_journal');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Table doesn't exist or error: " . $e->getMessage() . "\n";
}

echo "\n=== CHECK FOR CLOTURE TABLES ===\n";
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    if (stripos($table, 'cloture') !== false || stripos($table, 'reconcil') !== false) {
        echo ">> $table\n";
    }
}

echo "\n=== FILES RELATED TO CAISSE ===\n";
$files = glob('caisse/*.php');
foreach ($files as $file) {
    echo "  $file\n";
}
