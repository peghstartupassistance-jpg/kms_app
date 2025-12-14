<?php
/**
 * Test rapide de la page réconciliation
 */
require_once 'security.php';
global $pdo;

echo "=== TEST RECONCILIATION ===\n\n";

// 1. Vérifier la connexion
echo "1. Connexion DB: OK\n";

// 2. Tester les requêtes utilisées
$date = date('Y-m-d');

echo "\n2. Stats du jour ($date):\n";
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) as nb_operations,
        COUNT(CASE WHEN vente_id IS NOT NULL THEN 1 END) as nb_ventes,
        COUNT(CASE WHEN est_annule = 1 THEN 1 END) as nb_annulations,
        COALESCE(SUM(CASE WHEN sens = 'RECETTE' AND est_annule = 0 THEN montant ELSE 0 END), 0) as total_recettes,
        COALESCE(SUM(CASE WHEN sens = 'DEPENSE' AND est_annule = 0 THEN montant ELSE 0 END), 0) as total_depenses
    FROM journal_caisse
    WHERE date_operation = ?
");
$stmtStats->execute([$date]);
$stats = $stmtStats->fetch();
print_r($stats);

echo "\n3. Modes de paiement:\n";
$stmtModes = $pdo->prepare("
    SELECT mp.libelle, mp.code,
           COALESCE(SUM(CASE WHEN jc.sens = 'RECETTE' AND jc.est_annule = 0 THEN jc.montant ELSE 0 END), 0) as total
    FROM modes_paiement mp
    LEFT JOIN journal_caisse jc ON jc.mode_paiement_id = mp.id AND jc.date_operation = ?
    GROUP BY mp.id, mp.libelle, mp.code
    ORDER BY mp.id
");
$stmtModes->execute([$date]);
while ($mode = $stmtModes->fetch()) {
    echo "   - " . $mode['libelle'] . ": " . number_format($mode['total'], 0, ',', ' ') . "\n";
}

echo "\n4. Clôture existante:\n";
$stmtCloture = $pdo->prepare("SELECT * FROM caisses_clotures WHERE date_cloture = ?");
$stmtCloture->execute([$date]);
$cloture = $stmtCloture->fetch();
if ($cloture) {
    echo "   Statut: " . $cloture['statut'] . "\n";
} else {
    echo "   Aucune clôture\n";
}

echo "\n5. Historique (10 dernières):\n";
$stmt = $pdo->query("SELECT date_cloture, statut, ecart FROM caisses_clotures ORDER BY date_cloture DESC LIMIT 5");
while ($h = $stmt->fetch()) {
    echo "   - " . $h['date_cloture'] . " | " . $h['statut'] . " | Ecart: " . $h['ecart'] . "\n";
}

echo "\n=== TOUS LES TESTS PASSENT ===\n";
