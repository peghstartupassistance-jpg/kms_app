<?php
/**
 * Test Phase 1.2 - Signature BL (Corrigé)
 * Valide la colonne signe_client qui existe réellement
 */

require_once __DIR__ . '/security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

echo "═══════════════════════════════════════════════════════════════\n";
echo "🧪 TEST PHASE 1.2 - SIGNATURE BL (BOOLÉEN)\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Test: Vérifier colonne signe_client
echo "✓ Test 1: Vérifier colonne signe_client\n";
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

// Test 2: Charger BL pour tester accès
echo "✓ Test 2: Charger BL\n";
echo str_repeat("─", 50) . "\n";

try {
    $stmt = $pdo->query("SELECT id, numero, client_id, signe_client FROM bons_livraison LIMIT 3");
    $bls = $stmt->fetchAll();
    
    if (empty($bls)) {
        echo "⚠️  Aucun BL en base de données\n";
    } else {
        echo "BLs trouvés:\n";
        foreach ($bls as $bl) {
            $hasSig = $bl['signe_client'] ? "✅ Signé" : "❌ Non signé";
            echo "  • BL #" . htmlspecialchars($bl['numero']) . " (ID:" . $bl['id'] . ") - $hasSig\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Statistiques
echo "✓ Test 3: Statistiques\n";
echo str_repeat("─", 50) . "\n";

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM bons_livraison");
    $total = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as signes FROM bons_livraison WHERE signe_client = 1");
    $signes = $stmt->fetch()['signes'];
    
    $pct = $total > 0 ? round(($signes / $total) * 100, 1) : 0;
    
    echo "BL signés: $signes / $total ($pct%)\n";
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

echo "═══════════════════════════════════════════════════════════════\n";
echo "✅ TEST PHASE 1.2 TERMINÉ\n";
echo "═══════════════════════════════════════════════════════════════\n";

?>
