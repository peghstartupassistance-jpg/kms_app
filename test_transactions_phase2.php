<?php
/**
 * Test des transactions imbriquées - Vérification correction Phase 2
 */

require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/lib/stock.php';

global $pdo;

echo "=== TEST TRANSACTIONS IMBRIQUÉES ===\n\n";

// Test 1: stock_synchroniser_vente() appelé SANS transaction
echo "Test 1: stock_synchroniser_vente() standalone (sans transaction parente)\n";
try {
    $stmt = $pdo->query("SELECT id FROM ventes WHERE statut='LIVREE' LIMIT 1");
    $vente = $stmt->fetch();
    
    if ($vente) {
        echo "  État avant: inTransaction = " . ($pdo->inTransaction() ? 'YES' : 'NO') . "\n";
        
        stock_synchroniser_vente($pdo, (int)$vente['id']);
        
        echo "  État après: inTransaction = " . ($pdo->inTransaction() ? 'YES' : 'NO') . "\n";
        
        if (!$pdo->inTransaction()) {
            echo "  ✅ OK - Pas de transaction résiduelle\n";
        } else {
            echo "  ❌ ERREUR - Transaction laissée ouverte\n";
        }
    } else {
        echo "  ⚠️ SKIP - Aucune vente LIVREE pour tester\n";
    }
} catch (Exception $e) {
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: stock_synchroniser_vente() appelé DANS une transaction
echo "Test 2: stock_synchroniser_vente() dans transaction parente\n";
try {
    $stmt = $pdo->query("SELECT id FROM ventes WHERE statut='LIVREE' LIMIT 1");
    $vente = $stmt->fetch();
    
    if ($vente) {
        $pdo->beginTransaction();
        echo "  Transaction parente ouverte\n";
        echo "  État avant appel: inTransaction = " . ($pdo->inTransaction() ? 'YES' : 'NO') . "\n";
        
        stock_synchroniser_vente($pdo, (int)$vente['id']);
        
        echo "  État après appel: inTransaction = " . ($pdo->inTransaction() ? 'YES' : 'NO') . "\n";
        
        if ($pdo->inTransaction()) {
            echo "  ✅ OK - Transaction parente toujours active\n";
            $pdo->rollBack(); // Annuler le test
            echo "  Transaction parente annulée (test)\n";
        } else {
            echo "  ❌ ERREUR - Transaction parente fermée par la fonction\n";
        }
    } else {
        echo "  ⚠️ SKIP - Aucune vente LIVREE pour tester\n";
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Simulation ventes/edit.php - Transaction globale avec stock_synchroniser_vente()
echo "Test 3: Simulation ventes/edit.php (transaction globale)\n";
try {
    $stmt = $pdo->query("SELECT id, numero, montant_total_ttc FROM ventes WHERE statut='LIVREE' LIMIT 1");
    $vente = $stmt->fetch();
    
    if ($vente) {
        $pdo->beginTransaction();
        echo "  1. Transaction globale ouverte\n";
        
        // Simuler UPDATE vente
        $stmt = $pdo->prepare("UPDATE ventes SET commentaires = ? WHERE id = ?");
        $stmt->execute(['Test transaction ' . date('H:i:s'), $vente['id']]);
        echo "  2. UPDATE vente exécuté\n";
        
        // Appeler stock_synchroniser_vente (ne doit PAS créer sa propre transaction)
        stock_synchroniser_vente($pdo, (int)$vente['id']);
        echo "  3. stock_synchroniser_vente() appelé\n";
        
        echo "  4. État transaction: inTransaction = " . ($pdo->inTransaction() ? 'YES' : 'NO') . "\n";
        
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            echo "  5. Transaction globale annulée (test)\n";
            echo "  ✅ OK - Workflow complet fonctionnel\n";
        } else {
            echo "  ❌ ERREUR - Transaction fermée prématurément\n";
        }
    } else {
        echo "  ⚠️ SKIP - Aucune vente LIVREE pour tester\n";
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Vérifier que stock_synchroniser_achat() a le même comportement
echo "Test 4: stock_synchroniser_achat() dans transaction parente\n";
try {
    $stmt = $pdo->query("SELECT id FROM achats LIMIT 1");
    $achat = $stmt->fetch();
    
    if ($achat) {
        $pdo->beginTransaction();
        echo "  Transaction parente ouverte\n";
        
        stock_synchroniser_achat($pdo, (int)$achat['id']);
        
        if ($pdo->inTransaction()) {
            echo "  ✅ OK - Transaction parente toujours active\n";
            $pdo->rollBack();
        } else {
            echo "  ❌ ERREUR - Transaction parente fermée\n";
        }
    } else {
        echo "  ⚠️ SKIP - Aucun achat pour tester\n";
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "  ❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DES TESTS ===\n";
