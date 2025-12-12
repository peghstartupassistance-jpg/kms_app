<?php
// Script de migration : applique le schéma compta_schema_clean.sql à la base de données

require_once __DIR__ . '/db/db.php';

// Lire le fichier SQL
$sql_file = __DIR__ . '/db/compta_schema_clean.sql';
if (!file_exists($sql_file)) {
    die("❌ Fichier $sql_file non trouvé\n");
}

$sql_content = file_get_contents($sql_file);

echo "📋 Migration KMS - Module Comptabilité\n";
echo "========================================\n\n";
echo "📄 Fichier : " . $sql_file . "\n";

$success_count = 0;
$error_count = 0;
$errors = [];

// Diviser par points-virgules
$statements = array_filter(explode(';', $sql_content), function($stmt) {
    $stmt = trim($stmt);
    return !empty($stmt) && substr($stmt, 0, 2) !== '--';
});

echo "📊 Nombre de requêtes : " . count($statements) . "\n\n";

foreach ($statements as $query) {
    $query = trim($query);
    if (empty($query)) {
        continue;
    }
    
    try {
        $pdo->exec($query);
        $success_count++;
        
        // Afficher le type de requête
        if (stripos($query, 'CREATE TABLE') === 0) {
            preg_match('/CREATE TABLE IF NOT EXISTS\s+(\w+)/i', $query, $m);
            echo "✓ CREATE TABLE : " . ($m[1] ?? 'unknown') . "\n";
        } elseif (stripos($query, 'ALTER TABLE') === 0) {
            preg_match('/ALTER TABLE\s+(\w+)/i', $query, $m);
            echo "✓ ALTER TABLE : " . ($m[1] ?? 'unknown') . "\n";
        } elseif (stripos($query, 'INSERT INTO') === 0) {
            preg_match('/INSERT INTO\s+(\w+)/i', $query, $m);
            echo "✓ INSERT INTO : " . ($m[1] ?? 'unknown') . "\n";
        } else {
            echo "✓ Requête exécutée\n";
        }
    } catch (Exception $e) {
        $error_count++;
        $error_msg = $e->getMessage();
        echo "✗ ERREUR : " . substr($query, 0, 50) . "...\n";
        echo "  → " . $error_msg . "\n";
        $errors[] = $error_msg;
    }
}

echo "\n========================================\n";
echo "✓ Succès : $success_count\n";
echo "✗ Erreurs : $error_count\n";

if ($error_count > 0) {
    echo "\n⚠️  Détails des erreurs :\n";
    foreach (array_unique($errors) as $err) {
        echo "  - $err\n";
    }
}

echo "\n========================================\n";

// Vérifier les tables créées
try {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='kms_gestion' AND TABLE_NAME LIKE 'compta_%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $table_count = $result['nb'] ?? 0;
    
    echo "\n📊 Tables comptables créées : $table_count\n";
    
    // Lister les tables
    $stmt = $pdo->query("SHOW TABLES LIKE 'compta_%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "  ✓ $table\n";
    }
} catch (Exception $e) {
    echo "⚠️  Erreur lors de la vérification : " . $e->getMessage() . "\n";
}

echo "\n✅ Migration terminée !\n";
?>
