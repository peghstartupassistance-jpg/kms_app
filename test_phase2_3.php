<?php
/**
 * Script de test pour Phase 2.3 - Dashboards Enrichis
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

echo "=== TEST PHASE 2.3 - Dashboards Enrichis ===\n\n";

// Test 1: Vérifier fichiers existent
echo "TEST 1: Fichiers dashboard\n";
$files = [
    'lib/dashboard_helpers.php',
    'dashboard.php'
];

foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        echo "✅ $f existe\n";
    } else {
        echo "❌ $f manquant\n";
    }
}

// Test 2: Validation syntaxe
echo "\nTEST 2: Syntaxe PHP\n";
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    $output = shell_exec("php -l \"$path\" 2>&1");
    if (strpos($output, 'No syntax errors') !== false) {
        echo "✅ $f - OK\n";
    } else {
        echo "❌ $f - ERREUR:\n";
        echo "   " . trim($output) . "\n";
    }
}

// Test 3: Charger et vérifier fonctions
echo "\nTEST 3: Fonctions dashboard helpers\n";
require_once __DIR__ . '/lib/dashboard_helpers.php';

$functions = [
    'calculateCAJour',
    'calculateCAMois',
    'calculateBLSignedRate',
    'calculateEncaissementRate',
    'calculateStockStats',
    'getAlertsCritiques',
    'getChartCAParJour',
    'getChartEncaissementStatut',
];

foreach ($functions as $fn) {
    if (function_exists($fn)) {
        echo "✅ $fn() disponible\n";
    } else {
        echo "❌ $fn() manquante\n";
    }
}

// Test 4: Vérifier connexion BDD
echo "\nTEST 4: Connexion base de données\n";
try {
    // Simuler connexion
    $test_config = [
        'host' => 'localhost',
        'db' => 'kms_gestion',
        'user' => 'root',
        'pass' => ''
    ];
    
    @$pdo = new PDO(
        "mysql:host={$test_config['host']};dbname={$test_config['db']};charset=utf8mb4",
        $test_config['user'],
        $test_config['pass']
    );
    
    if ($pdo) {
        echo "✅ Connexion BDD réussie\n";
        
        // Test 5: Vérifier tables nécessaires
        echo "\nTEST 5: Tables nécessaires\n";
        $tables = ['caisse_journal', 'bons_livraison', 'ventes', 'retours_litiges', 'produits', 'devis'];
        
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->rowCount() > 0) {
                echo "✅ Table $table existe\n";
            } else {
                echo "⚠️  Table $table manquante\n";
            }
        }
        
        // Test 6: Appeler fonctions avec vraies données
        echo "\nTEST 6: Exécution fonctions avec données réelles\n";
        
        try {
            $result = calculateCAJour($pdo);
            if (is_array($result) && isset($result['ca_total'])) {
                echo "✅ calculateCAJour() retourne array valide\n";
                echo "   CA jour: " . number_format($result['ca_total'], 0) . " FCFA\n";
            } else {
                echo "❌ calculateCAJour() format invalide\n";
            }
            
            $result = calculateBLSignedRate($pdo);
            if (is_array($result) && isset($result['signed_rate'])) {
                echo "✅ calculateBLSignedRate() retourne array valide\n";
                echo "   Taux signé: " . $result['signed_rate'] . "%\n";
            }
            
            $result = calculateStockStats($pdo);
            if (is_array($result) && isset($result['rupture_rate'])) {
                echo "✅ calculateStockStats() retourne array valide\n";
                echo "   Taux rupture: " . $result['rupture_rate'] . "%\n";
            }
            
            $alerts = getAlertsCritiques($pdo);
            echo "✅ getAlertsCritiques() retourne " . count($alerts) . " alertes\n";
            
            $chartData = getChartCAParJour($pdo);
            if (is_array($chartData) && isset($chartData['labels'])) {
                echo "✅ getChartCAParJour() retourne " . count($chartData['labels']) . " jours\n";
            }
            
        } catch (Exception $e) {
            echo "⚠️  Erreur exécution: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "⚠️  Pas de connexion BDD (config manquante?)\n";
    }
} catch (PDOException $e) {
    echo "⚠️  Connexion BDD échouée: " . $e->getMessage() . "\n";
    echo "   (C'est normal si config BDD non disponible)\n";
}

echo "\n=== Tests complétés ===\n";
