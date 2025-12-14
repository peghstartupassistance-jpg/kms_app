<?php
require_once 'security.php';
global $pdo;

echo "=== ROLES TABLE ===\n";
$stmt = $pdo->query('DESCRIBE roles');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== ROLE_PERMISSION TABLE ===\n";
$stmt = $pdo->query('DESCRIBE role_permission');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== PERMISSIONS TABLE ===\n";
$stmt = $pdo->query('DESCRIBE permissions');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n=== CHECK FOR UTILISATEUR_ROLE ===\n";
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$found = false;
foreach ($tables as $table) {
    if (stripos($table, 'utilisateur') !== false && stripos($table, 'role') !== false) {
        echo "Found: $table\n";
        $found = true;
    }
}
if (!$found) {
    echo "No utilisateur_role table found\n";
    echo "\nChecking utilisateurs table for role column again...\n";
    $stmt = $pdo->query('DESCRIBE utilisateurs');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (stripos($row['Field'], 'role') !== false) {
            echo "  Found role column: " . $row['Field'] . "\n";
        }
    }
}
