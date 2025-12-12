<?php
require __DIR__ . '/db/db.php';

echo "=== Structure des tables ===\n\n";

// Vérifier la structure de la table permissions
$stmt = $pdo->query("DESCRIBE permissions");
$cols = $stmt->fetchAll();
echo "Table: permissions\n";
foreach ($cols as $c) {
    echo "  - {$c['Field']} ({$c['Type']})\n";
}

// Lister les permissions existantes
echo "\n=== Permissions existantes ===\n";
$stmt = $pdo->query("SELECT id, code FROM permissions LIMIT 10");
$perms = $stmt->fetchAll();
foreach ($perms as $p) {
    echo "  - ID: {$p['id']}, CODE: {$p['code']}\n";
}

// Vérifier les tables de rôles
echo "\n=== Tables disponibles contenant 'role' ===\n";
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll();
foreach ($tables as $t) {
    $table_name = $t[0];
    if (stripos($table_name, 'role') !== false || stripos($table_name, 'permission') !== false) {
        echo "  ✓ $table_name\n";
        
        // Afficher la structure
        $stmt2 = $pdo->query("DESCRIBE $table_name");
        $cols2 = $stmt2->fetchAll();
        foreach ($cols2 as $c) {
            echo "    - {$c['Field']}\n";
        }
    }
}
?>
