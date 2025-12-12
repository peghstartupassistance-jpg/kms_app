<?php
/**
 * Script d'exécution SQL - extensions_marketing_complement.sql
 * Exécute automatiquement les tables manquantes
 */

require_once __DIR__ . '/db/db.php';

echo "=== EXÉCUTION SCRIPT SQL COMPLÉMENTAIRE ===\n\n";

try {
    // Lire le fichier SQL
    $sqlFile = __DIR__ . '/db/extensions_marketing_complement.sql';
    
    if (!file_exists($sqlFile)) {
        die("❌ ERREUR: Fichier $sqlFile introuvable\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Nettoyer les commentaires SQL
    $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    
    // Diviser par CREATE TABLE et CREATE VIEW
    $pattern = '/(CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS.*?ENGINE=InnoDB[^;]*;|CREATE\s+OR\s+REPLACE\s+VIEW.*?FROM\s+\w+\s*[^;]*;)/is';
    preg_match_all($pattern, $sql, $matches);
    
    $statements = $matches[0];
    
    if (empty($statements)) {
        die("❌ ERREUR: Aucune requête valide trouvée dans le fichier\n");
    }
    
    $executed = 0;
    $errors = 0;
    
    echo "Fichier chargé: " . count($statements) . " requêtes à exécuter\n\n";
    
    foreach ($statements as $statement) {
        try {
            // Déterminer le type de requête
            if (stripos($statement, 'CREATE TABLE') !== false) {
                preg_match('/CREATE TABLE.*?`(\w+)`/i', $statement, $matches);
                $tableName = $matches[1] ?? 'inconnue';
                echo "Création table '$tableName'... ";
                
            } elseif (stripos($statement, 'CREATE OR REPLACE VIEW') !== false) {
                preg_match('/VIEW\s+`?(\w+)`?/i', $statement, $matches);
                $viewName = $matches[1] ?? 'inconnue';
                echo "Création vue '$viewName'... ";
                
            } else {
                echo "Exécution requête... ";
            }
            
            $pdo->exec($statement);
            echo "✓ OK\n";
            $executed++;
            
        } catch (PDOException $e) {
            // Si l'erreur est "table already exists", ce n'est pas grave
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "⚠ Existe déjà\n";
                $executed++;
            } else {
                echo "✗ ERREUR: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
    }
    
    echo "\n=== RÉSUMÉ ===\n";
    echo "Requêtes exécutées: $executed\n";
    echo "Erreurs: $errors\n";
    
    if ($errors === 0) {
        echo "\n✅ SUCCÈS: Toutes les tables ont été créées !\n";
        echo "\nVérification des tables créées:\n";
        
        $tables = ['relances_devis', 'conversions_pipeline', 'objectifs_commerciaux', 'kpis_quotidiens'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "  ✓ Table '$table' existe\n";
            } else {
                echo "  ✗ Table '$table' MANQUANTE\n";
            }
        }
        
        echo "\nVérification des vues:\n";
        $views = ['v_pipeline_commercial', 'v_ventes_livraison_encaissement'];
        foreach ($views as $view) {
            $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_kms_gestion = '$view'");
            if ($stmt->rowCount() > 0) {
                echo "  ✓ Vue '$view' existe\n";
            } else {
                echo "  ⚠ Vue '$view' non vérifiable\n";
            }
        }
        
        echo "\n🚀 Vous pouvez maintenant re-lancer les tests:\n";
        echo "   php test_module_marketing.php\n";
        echo "\nOu tester dans le navigateur:\n";
        echo "   http://localhost/kms_app/reporting/dashboard_marketing.php\n";
        
    } else {
        echo "\n⚠️ ATTENTION: $errors erreur(s) détectée(s)\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERREUR FATALE: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== FIN ===\n";
