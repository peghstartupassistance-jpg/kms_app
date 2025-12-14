<?php
/**
 * Test Phase 1.2 - Signature BL (Statut)
 * Valide la structure BD pour la signature (booléen signe_client)
 */

require_once __DIR__ . '/security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

echo "═══════════════════════════════════════════════════════════════\n";
echo "🧪 TEST PHASE 1.2 - SIGNATURE BL (BOOLÉEN STATUT)\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// ===== Test 1: Vérifier colonne signe_client
echo "✓ TEST 1: Vérifier colonne signe_client en BD\n";
echo str_repeat("─", 50) . "\n";

try {
    $stmt = $pdo->query("DESCRIBE bons_livraison");
    $columns = array_column($stmt->fetchAll(), 'Field');
    
    if (in_array('signe_client', $columns)) {
        echo "✅ Colonne 'signe_client' existe\n";
    } else {
        echo "❌ Colonne 'signe_client' MANQUANTE\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// ===== Test 2: Détails colonne signe_client
echo "✓ TEST 2: Détails colonne signe_client\n";
echo str_repeat("─", 50) . "\n";

try {
    $stmt = $pdo->query("DESCRIBE bons_livraison");
    $cols = $stmt->fetchAll();
    
    $signe_col = array_filter($cols, fn($c) => $c['Field'] === 'signe_client')[0] ?? null;
    
    if ($signe_col) {
        printf("  Type: %-20s | Null: %s | Default: %s\n",
            $signe_col['Type'],
            $signe_col['Null'],
            $signe_col['Default'] ?? 'NULL'
        );
    }
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// ===== Test 3: Charger un BL pour tester accès colonne
echo "✓ TEST 3: Charger BL et accès signe_client\n";
echo str_repeat("─", 50) . "\n";

try {
    $stmt = $pdo->query("SELECT id, numero, client_id, signe_client FROM bons_livraison LIMIT 1");
    $bl = $stmt->fetch();
    
    if ($bl) {
        echo "✅ BL chargé avec succès\n";
        echo "   • ID: " . $bl['id'] . "\n";
        echo "   • Numéro: " . $bl['numero'] . "\n";
        echo "   • Signé: " . ($bl['signe_client'] ? 'OUI' : 'NON') . "\n";
    } else {
        echo "⚠️  Aucun BL en base de données\n";
    }
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// ===== Test 4: Tester condition signe_client
echo "✓ TEST 4: Test condition signe_client\n";
echo str_repeat("─", 50) . "\n";

try {
    $stmt = $pdo->query("SELECT COUNT(*) as nb_signes FROM bons_livraison WHERE signe_client = 1");
    $result = $stmt->fetch();
    
    echo "✅ BL signés: " . $result['nb_signes'] . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as nb_non_signes FROM bons_livraison WHERE signe_client = 0");
    $result = $stmt->fetch();
    
    echo "✅ BL non signés: " . $result['nb_non_signes'] . "\n";
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\nTEST PHASE 1.2 TERMINÉ\n";
