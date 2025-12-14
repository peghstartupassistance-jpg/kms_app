<?php
require __DIR__ . '/db/db.php';
global $pdo;
$table = $argv[1] ?? null;
if (!$table) { echo "Missing table\n"; exit(1);} 
$stmt = $pdo->prepare('DESCRIBE ' . $table);
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | " . ($row['Default'] ?? 'NULL') . "\n";
}
