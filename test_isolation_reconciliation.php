<?php
/**
 * Test isolation du problème réconciliation
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test isolation</h1>";

try {
    require_once 'security.php';
    echo "<p>✅ 1. Security loaded</p>";
    
    exigerConnexion();
    echo "<p>✅ 2. Connection required</p>";
    
    exigerPermission('CAISSE_LIRE');
    echo "<p>✅ 3. Permission checked</p>";
    
    global $pdo;
    echo "<p>✅ 4. PDO available</p>";
    
    $date = '2025-12-14';
    
    // Test la requête stats
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(*) as nb_operations,
            COALESCE(SUM(CASE WHEN sens = 'RECETTE' AND est_annule = 0 THEN montant ELSE 0 END), 0) as total_recettes,
            COALESCE(SUM(CASE WHEN sens = 'DEPENSE' AND est_annule = 0 THEN montant ELSE 0 END), 0) as total_depenses
        FROM journal_caisse
        WHERE date_operation = ?
    ");
    $stmtStats->execute([$date]);
    $stats = $stmtStats->fetch();
    echo "<p>✅ 5. Stats query OK: " . $stats['nb_operations'] . " ops</p>";
    
    // Test la requête modes
    $stmtModes = $pdo->prepare("
        SELECT mp.libelle, mp.code,
               COALESCE(SUM(CASE WHEN jc.sens = 'RECETTE' AND jc.est_annule = 0 THEN jc.montant ELSE 0 END), 0) as total
        FROM modes_paiement mp
        LEFT JOIN journal_caisse jc ON jc.mode_paiement_id = mp.id AND jc.date_operation = ?
        GROUP BY mp.id, mp.libelle, mp.code
        ORDER BY mp.id
    ");
    $stmtModes->execute([$date]);
    $modes = $stmtModes->fetchAll();
    echo "<p>✅ 6. Modes query OK: " . count($modes) . " modes</p>";
    
    // Test la requête clôture
    $stmtCloture = $pdo->prepare("SELECT * FROM caisses_clotures WHERE date_cloture = ?");
    $stmtCloture->execute([$date]);
    $cloture = $stmtCloture->fetch();
    echo "<p>✅ 7. Cloture query OK: " . ($cloture ? 'existe' : 'n\'existe pas') . "</p>";
    
    // Test la requête operations
    $stmtOps = $pdo->prepare("
        SELECT jc.*, mp.libelle as mode_libelle, c.nom as client_nom
        FROM journal_caisse jc
        LEFT JOIN modes_paiement mp ON mp.id = jc.mode_paiement_id
        LEFT JOIN clients c ON c.id = jc.client_id
        WHERE jc.date_operation = ?
        ORDER BY jc.id DESC
        LIMIT 20
    ");
    $stmtOps->execute([$date]);
    $operations = $stmtOps->fetchAll();
    echo "<p>✅ 8. Operations query OK: " . count($operations) . " ops</p>";
    
    // Test la requête historique
    $stmtHistorique = $pdo->query("
        SELECT cc.*, u.nom_complet as caissier_nom
        FROM caisses_clotures cc
        LEFT JOIN utilisateurs u ON u.id = cc.caissier_id
        ORDER BY cc.date_cloture DESC
        LIMIT 10
    ");
    $historique = $stmtHistorique->fetchAll();
    echo "<p>✅ 9. Historique query OK: " . count($historique) . " clôtures</p>";
    
    echo "<p>✅ 10. Test include header...</p>";
    ob_start();
    include __DIR__ . '/partials/header.php';
    $headerContent = ob_get_clean();
    echo "<p>✅ 11. Header OK (" . strlen($headerContent) . " bytes)</p>";
    
    echo "<p>✅ 12. Test include sidebar...</p>";
    ob_start();
    include __DIR__ . '/partials/sidebar.php';
    $sidebarContent = ob_get_clean();
    echo "<p>✅ 13. Sidebar OK (" . strlen($sidebarContent) . " bytes)</p>";
    
    echo "<p>✅ <strong>TOUS LES TESTS PASSENT!</strong></p>";
    echo "<p>Le problème n'est PAS dans les requêtes ou includes.</p>";
    echo "<p>Le problème doit être dans le HTML/PHP entre les KPIs et le formulaire.</p>";
    
} catch (Exception $e) {
    echo "<p>❌ ERREUR: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
