<?php
require_once 'security.php';
global $pdo;

echo "=== TABLES AVEC 'caisse' OU 'journal' ===\n";
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    if (stripos($table, 'caisse') !== false || stripos($table, 'journal') !== false) {
        echo "\n>> TABLE: $table\n";
        $cols = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            echo "   " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    }
}
