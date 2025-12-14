<?php
require_once 'security.php';
global $pdo;

$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "=== ALL TABLES ===\n";
foreach ($tables as $table) {
    if (stripos($table, 'perm') !== false || stripos($table, 'role') !== false) {
        echo ">> $table\n";
    } else {
        echo "   $table\n";
    }
}

echo "\n=== CHECK FOR ROLE/PERMISSION COLUMNS ===\n";
foreach ($tables as $table) {
    $columns = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($columns as $col) {
        if (stripos($col, 'role') !== false) {
            echo "$table.$col\n";
        }
    }
}
