<?php
/**
 * Test de régression - Vérification de tous les fichiers affectés par les modifications
 */

require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/lib/stock.php';

global $pdo;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  TEST RÉGRESSION - FICHIERS AFFECTÉS PAR LES MODIFICATIONS   ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$tests = [];
$failures = [];

// ============================================================================
// TEST 1: lib/stock.php - Syntaxe
// ============================================================================
echo "TEST 1: lib/stock.php - Syntaxe PHP\n";
$result = shell_exec('php -l ' . escapeshellarg(__DIR__ . '/lib/stock.php'));
if (strpos($result, 'No syntax errors') !== false) {
    echo "  ✅ PASS - Pas d'erreur syntaxe\n";
    $tests[] = ['lib/stock.php', 'SYNTAX', 'PASS'];
} else {
    echo "  ❌ FAIL\n$result\n";
    $tests[] = ['lib/stock.php', 'SYNTAX', 'FAIL'];
    $failures[] = 'lib/stock.php - Erreur syntaxe';
}

// ============================================================================
// TEST 2: livraisons/detail.php - Syntaxe
// ============================================================================
echo "\nTEST 2: livraisons/detail.php - Syntaxe PHP\n";
$result = shell_exec('php -l ' . escapeshellarg(__DIR__ . '/livraisons/detail.php'));
if (strpos($result, 'No syntax errors') !== false) {
    echo "  ✅ PASS - Pas d'erreur syntaxe\n";
    $tests[] = ['livraisons/detail.php', 'SYNTAX', 'PASS'];
} else {
    echo "  ❌ FAIL\n$result\n";
    $tests[] = ['livraisons/detail.php', 'SYNTAX', 'FAIL'];
    $failures[] = 'livraisons/detail.php - Erreur syntaxe';
}

// ============================================================================
// TEST 3: ventes/edit.php - Syntaxe
// ============================================================================
echo "\nTEST 3: ventes/edit.php - Syntaxe PHP\n";
$result = shell_exec('php -l ' . escapeshellarg(__DIR__ . '/ventes/edit.php'));
if (strpos($result, 'No syntax errors') !== false) {
    echo "  ✅ PASS - Pas d'erreur syntaxe\n";
    $tests[] = ['ventes/edit.php', 'SYNTAX', 'PASS'];
} else {
    echo "  ❌ FAIL\n$result\n";
    $tests[] = ['ventes/edit.php', 'SYNTAX', 'FAIL'];
    $failures[] = 'ventes/edit.php - Erreur syntaxe';
}

// ============================================================================
// TEST 4: livraisons/detail.php - Requête BL
// ============================================================================
echo "\nTEST 4: livraisons/detail.php - Requête chargement BL\n";
try {
    $stmt = $pdo->prepare("
        SELECT bl.*, 
               c.nom as client_nom, c.adresse as client_adresse, c.telephone as client_telephone,
               v.numero as vente_numero, v.date_vente, v.montant_total_ttc as vente_montant,
               op.numero_ordre as ordre_numero, op.id as ordre_id,
               u_mag.nom_complet as magasinier_nom,
               u_liv.nom_complet as livreur_nom
        FROM bons_livraison bl
        JOIN clients c ON c.id = bl.client_id
        LEFT JOIN ventes v ON v.id = bl.vente_id
        LEFT JOIN ordres_preparation op ON op.id = bl.ordre_preparation_id
        LEFT JOIN utilisateurs u_mag ON u_mag.id = bl.magasinier_id
        LEFT JOIN utilisateurs u_liv ON u_liv.id = bl.livreur_id
        WHERE bl.id = ?
    ");
    $stmt->execute([1]);
    $bl = $stmt->fetch();
    
    if ($bl) {
        // Vérifier colonnes critiques
        $colonnes_requises = ['id', 'numero', 'vente_id', 'client_nom', 'livreur_nom'];
        $manquantes = [];
        foreach ($colonnes_requises as $col) {
            if (!isset($bl[$col])) {
                $manquantes[] = $col;
            }
        }
        
        if (empty($manquantes)) {
            echo "  ✅ PASS - Toutes les colonnes présentes\n";
            $tests[] = ['livraisons/detail.php', 'QUERY_BL', 'PASS'];
        } else {
            echo "  ❌ FAIL - Colonnes manquantes: " . implode(', ', $manquantes) . "\n";
            $tests[] = ['livraisons/detail.php', 'QUERY_BL', 'FAIL'];
            $failures[] = 'livraisons/detail.php - Requête BL - Colonnes manquantes: ' . implode(', ', $manquantes);
        }
    } else {
        echo "  ⚠️ SKIP - Pas de BL pour tester\n";
        $tests[] = ['livraisons/detail.php', 'QUERY_BL', 'SKIP'];
    }
} catch (Exception $e) {
    echo "  ❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $tests[] = ['livraisons/detail.php', 'QUERY_BL', 'FAIL'];
    $failures[] = 'livraisons/detail.php - Requête BL - ' . $e->getMessage();
}

// ============================================================================
// TEST 5: livraisons/detail.php - Requête lignes
// ============================================================================
echo "\nTEST 5: livraisons/detail.php - Requête chargement lignes\n";
try {
    $stmt = $pdo->prepare("
        SELECT bll.*, p.code_produit, p.designation, p.prix_vente
        FROM bons_livraison_lignes bll
        LEFT JOIN produits p ON p.id = bll.produit_id
        WHERE bll.bon_livraison_id = ?
    ");
    $stmt->execute([1]);
    $lignes = $stmt->fetchAll();
    
    if (!empty($lignes)) {
        // Vérifier colonnes critiques
        $ligne = $lignes[0];
        $colonnes_requises = ['quantite', 'code_produit', 'designation', 'prix_vente'];
        $manquantes = [];
        foreach ($colonnes_requises as $col) {
            if (!isset($ligne[$col])) {
                $manquantes[] = $col;
            }
        }
        
        if (empty($manquantes)) {
            echo "  ✅ PASS - Toutes les colonnes présentes (" . count($lignes) . " lignes)\n";
            $tests[] = ['livraisons/detail.php', 'QUERY_LIGNES', 'PASS'];
        } else {
            echo "  ❌ FAIL - Colonnes manquantes: " . implode(', ', $manquantes) . "\n";
            $tests[] = ['livraisons/detail.php', 'QUERY_LIGNES', 'FAIL'];
            $failures[] = 'livraisons/detail.php - Requête lignes - Colonnes manquantes: ' . implode(', ', $manquantes);
        }
    } else {
        echo "  ⚠️ SKIP - Pas de lignes BL pour tester\n";
        $tests[] = ['livraisons/detail.php', 'QUERY_LIGNES', 'SKIP'];
    }
} catch (Exception $e) {
    echo "  ❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $tests[] = ['livraisons/detail.php', 'QUERY_LIGNES', 'FAIL'];
    $failures[] = 'livraisons/detail.php - Requête lignes - ' . $e->getMessage();
}

// ============================================================================
// TEST 6: Colonne signature
// ============================================================================
echo "\nTEST 6: livraisons/detail.php - Colonne signature_client\n";
try {
    $stmt = $pdo->prepare("DESCRIBE bons_livraison");
    $stmt->execute();
    $colonnes = [];
    while ($row = $stmt->fetch()) {
        $colonnes[] = $row['Field'];
    }
    
    if (in_array('signe_client', $colonnes)) {
        echo "  ✅ PASS - Colonne 'signe_client' existe (devrait être utilisée au lieu de 'signature')\n";
        $tests[] = ['bons_livraison', 'COLUMN_SIGNE', 'PASS'];
    } else {
        echo "  ❌ FAIL - Colonne 'signe_client' n'existe pas\n";
        $tests[] = ['bons_livraison', 'COLUMN_SIGNE', 'FAIL'];
        $failures[] = 'bons_livraison - Colonne signe_client manquante';
    }
} catch (Exception $e) {
    echo "  ❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $tests[] = ['bons_livraison', 'COLUMN_SIGNE', 'FAIL'];
    $failures[] = 'bons_livraison - ' . $e->getMessage();
}

// ============================================================================
// TEST 7: stock_synchroniser_vente() en transaction
// ============================================================================
echo "\nTEST 7: stock_synchroniser_vente() - Transaction-aware\n";
try {
    $pdo->beginTransaction();
    $etat_avant = $pdo->inTransaction();
    
    // Appeler la fonction dans une transaction
    $stmt = $pdo->query("SELECT id FROM ventes WHERE statut='LIVREE' LIMIT 1");
    $vente = $stmt->fetch();
    
    if ($vente) {
        stock_synchroniser_vente($pdo, (int)$vente['id']);
        $etat_apres = $pdo->inTransaction();
        
        if ($etat_avant && $etat_apres) {
            echo "  ✅ PASS - Transaction préservée\n";
            $tests[] = ['stock_synchroniser_vente', 'TRANSACTION', 'PASS'];
        } else {
            echo "  ❌ FAIL - Transaction fermée par la fonction\n";
            $tests[] = ['stock_synchroniser_vente', 'TRANSACTION', 'FAIL'];
            $failures[] = 'stock_synchroniser_vente - Transaction non préservée';
        }
    } else {
        echo "  ⚠️ SKIP - Pas de vente LIVREE pour tester\n";
        $tests[] = ['stock_synchroniser_vente', 'TRANSACTION', 'SKIP'];
    }
    
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "  ❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $tests[] = ['stock_synchroniser_vente', 'TRANSACTION', 'FAIL'];
    $failures[] = 'stock_synchroniser_vente - ' . $e->getMessage();
}

// ============================================================================
// RÉSUMÉ
// ============================================================================
echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║                        RÉSUMÉ DES TESTS                        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$pass = array_reduce($tests, fn($c, $t) => $c + ($t[2] === 'PASS' ? 1 : 0), 0);
$fail = array_reduce($tests, fn($c, $t) => $c + ($t[2] === 'FAIL' ? 1 : 0), 0);
$skip = array_reduce($tests, fn($c, $t) => $c + ($t[2] === 'SKIP' ? 1 : 0), 0);

echo "Total: " . count($tests) . " tests\n";
echo "  ✅ PASS: " . $pass . "\n";
echo "  ❌ FAIL: " . $fail . "\n";
echo "  ⚠️ SKIP: " . $skip . "\n\n";

if (!empty($failures)) {
    echo "PROBLÈMES IDENTIFIÉS:\n";
    foreach ($failures as $i => $f) {
        echo "  " . ($i + 1) . ". " . $f . "\n";
    }
    echo "\n";
}

if ($fail === 0) {
    echo "✅✅✅ TOUS LES TESTS PASSENT ✅✅✅\n";
    exit(0);
} else {
    echo "❌ ATTENTION - " . $fail . " test(s) échoué(s)\n";
    exit(1);
}
