<?php
/**
 * TEST FINAL COMPLET - Validation de toutes les corrections
 */

require_once __DIR__ . '/db/db.php';

global $pdo;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         TEST FINAL - VALIDATION DE TOUTES LES CORRECTIONS     ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$tests = [];

// ============================================================================
// 1. Vérifier syntaxe de tous les fichiers modifiés
// ============================================================================
$fichiers_modifies = [
    'lib/stock.php',
    'livraisons/detail.php',
    'livraisons/print.php',
    'ventes/edit.php',
];

echo "ÉTAPE 1: Vérification syntaxe PHP\n";
echo str_repeat("─", 60) . "\n";

foreach ($fichiers_modifies as $fichier) {
    $chemin = __DIR__ . '/' . $fichier;
    if (!file_exists($chemin)) {
        echo "⚠️  $fichier - FICHIER NON TROUVÉ\n";
        continue;
    }
    
    $result = shell_exec('php -l ' . escapeshellarg($chemin) . ' 2>&1');
    if (strpos($result, 'No syntax errors') !== false) {
        echo "✅ $fichier\n";
        $tests[] = ['Syntaxe ' . $fichier, 'PASS'];
    } else {
        echo "❌ $fichier\n$result\n";
        $tests[] = ['Syntaxe ' . $fichier, 'FAIL'];
    }
}

echo "\n";

// ============================================================================
// 2. Vérifier absence de colonnes dangereuses
// ============================================================================
echo "ÉTAPE 2: Vérification des colonnes BD\n";
echo str_repeat("─", 60) . "\n";

try {
    // Vérifier bons_livraison
    $stmt = $pdo->query("DESCRIBE bons_livraison");
    $colonnes = array_column($stmt->fetchAll(), 'Field');
    
    $colonnes_attendues = ['id', 'numero', 'vente_id', 'signe_client'];
    $manquantes = array_diff($colonnes_attendues, $colonnes);
    
    if (empty($manquantes)) {
        echo "✅ Table bons_livraison - Toutes les colonnes présentes\n";
        $tests[] = ['Colonnes bons_livraison', 'PASS'];
    } else {
        echo "❌ Table bons_livraison - Colonnes manquantes: " . implode(', ', $manquantes) . "\n";
        $tests[] = ['Colonnes bons_livraison', 'FAIL'];
    }
    
    // Vérifier bons_livraison_lignes
    $stmt = $pdo->query("DESCRIBE bons_livraison_lignes");
    $colonnes_lignes = array_column($stmt->fetchAll(), 'Field');
    
    $colonnes_lignes_attendues = ['id', 'bon_livraison_id', 'produit_id', 'quantite', 'quantite_commandee', 'quantite_restante'];
    $manquantes_lignes = array_diff($colonnes_lignes_attendues, $colonnes_lignes);
    
    if (empty($manquantes_lignes)) {
        echo "✅ Table bons_livraison_lignes - Toutes les colonnes présentes\n";
        $tests[] = ['Colonnes bons_livraison_lignes', 'PASS'];
    } else {
        echo "❌ Table bons_livraison_lignes - Colonnes manquantes: " . implode(', ', $manquantes_lignes) . "\n";
        $tests[] = ['Colonnes bons_livraison_lignes', 'FAIL'];
    }
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    $tests[] = ['Colonnes BD', 'FAIL'];
}

echo "\n";

// ============================================================================
// 3. Tester chargement des données avec les bonnes colonnes
// ============================================================================
echo "ÉTAPE 3: Test chargement de données\n";
echo str_repeat("─", 60) . "\n";

try {
    // Test requête BL complète
    $stmt = $pdo->prepare("
        SELECT bl.*, 
               c.nom as client_nom,
               signe_client
        FROM bons_livraison bl
        JOIN clients c ON c.id = bl.client_id
        LIMIT 1
    ");
    $stmt->execute();
    $bl = $stmt->fetch();
    
    if ($bl) {
        if (isset($bl['numero']) && isset($bl['signe_client'])) {
            echo "✅ Requête BL - Colonnes numero et signe_client accessibles\n";
            $tests[] = ['Requête BL', 'PASS'];
        } else {
            echo "❌ Requête BL - Colonnes manquantes\n";
            $tests[] = ['Requête BL', 'FAIL'];
        }
    } else {
        echo "⚠️  Requête BL - Pas de données pour tester\n";
        $tests[] = ['Requête BL', 'SKIP'];
    }
    
    // Test requête lignes avec designation et prix
    $stmt = $pdo->prepare("
        SELECT bll.*, p.designation, p.prix_vente
        FROM bons_livraison_lignes bll
        LEFT JOIN produits p ON p.id = bll.produit_id
        LIMIT 1
    ");
    $stmt->execute();
    $ligne = $stmt->fetch();
    
    if ($ligne) {
        if (isset($ligne['quantite']) && isset($ligne['designation']) && isset($ligne['prix_vente'])) {
            echo "✅ Requête lignes - Colonnes quantite, designation, prix_vente accessibles\n";
            $tests[] = ['Requête lignes', 'PASS'];
        } else {
            echo "❌ Requête lignes - Colonnes manquantes\n";
            $tests[] = ['Requête lignes', 'FAIL'];
        }
    } else {
        echo "⚠️  Requête lignes - Pas de données pour tester\n";
        $tests[] = ['Requête lignes', 'SKIP'];
    }
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    $tests[] = ['Test chargement données', 'FAIL'];
}

echo "\n";

// ============================================================================
// 4. Vérifier absence de références à colonnes cassées
// ============================================================================
echo "ÉTAPE 4: Scan des références dangereuses\n";
echo str_repeat("─", 60) . "\n";

$fichiers_a_scanner = [
    'livraisons/detail.php',
    'livraisons/print.php',
];

$colonnes_dangereuses = [
    '$bl[\'signature\']' => 'Devrait être signe_client',
    '$bl["signature"]' => 'Devrait être signe_client',
    '$bl[\'numero_bl\']' => 'Devrait être numero',
    '$bl["numero_bl"]' => 'Devrait être numero',
];

foreach ($fichiers_a_scanner as $fichier) {
    $chemin = __DIR__ . '/' . $fichier;
    if (!file_exists($chemin)) continue;
    
    $contenu = file_get_contents($chemin);
    $problemes = [];
    
    foreach ($colonnes_dangereuses as $pattern => $note) {
        if (strpos($contenu, $pattern) !== false) {
            $problemes[] = $pattern;
        }
    }
    
    if (empty($problemes)) {
        echo "✅ $fichier - Pas de références cassées\n";
        $tests[] = ["Scan références $fichier", 'PASS'];
    } else {
        echo "❌ $fichier - Références cassées trouvées: " . implode(', ', $problemes) . "\n";
        $tests[] = ["Scan références $fichier", 'FAIL'];
    }
}

echo "\n";

// ============================================================================
// RÉSUMÉ
// ============================================================================
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                        RÉSUMÉ FINAL                            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$pass = array_reduce($tests, fn($c, $t) => $c + ($t[1] === 'PASS' ? 1 : 0), 0);
$fail = array_reduce($tests, fn($c, $t) => $c + ($t[1] === 'FAIL' ? 1 : 0), 0);
$skip = array_reduce($tests, fn($c, $t) => $c + ($t[1] === 'SKIP' ? 1 : 0), 0);

echo "Total: " . count($tests) . " tests\n";
echo "  ✅ PASS: " . $pass . "\n";
echo "  ❌ FAIL: " . $fail . "\n";
echo "  ⚠️ SKIP: " . $skip . "\n\n";

if ($fail === 0) {
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  ✅✅✅ TOUS LES TESTS PASSENT - PRÊT POUR PRODUCTION ✅✅✅  ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n";
    exit(0);
} else {
    echo "❌ ATTENTION - " . $fail . " test(s) échoué(s)\n";
    echo "Veuillez corriger les problèmes avant de continuer.\n";
    exit(1);
}
