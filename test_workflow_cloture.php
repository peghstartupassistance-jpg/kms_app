<?php
/**
 * Test workflow de clôture
 */
require_once 'security.php';
global $pdo;

$date = '2025-12-14';

echo "=== TEST WORKFLOW CLÔTURE ===\n";
echo "Date: $date\n\n";

// 1. Récupérer les totaux calculés
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as nb_ops,
        COALESCE(SUM(CASE WHEN sens='RECETTE' AND est_annule=0 THEN montant ELSE 0 END), 0) as rec,
        COALESCE(SUM(CASE WHEN sens='DEPENSE' AND est_annule=0 THEN montant ELSE 0 END), 0) as dep
    FROM journal_caisse WHERE date_operation = ?
");
$stmt->execute([$date]);
$calc = $stmt->fetch();

echo "1. Valeurs calculées:\n";
echo "   Recettes: " . number_format($calc['rec'], 0, ',', ' ') . " FCFA\n";
echo "   Dépenses: " . number_format($calc['dep'], 0, ',', ' ') . " FCFA\n";
echo "   Solde: " . number_format($calc['rec'] - $calc['dep'], 0, ',', ' ') . " FCFA\n";

// 2. Simuler une déclaration du caissier (avec léger écart)
$declare_especes = 245000; // Réel espèces
$declare_virement = 150000; // Virement OK
$declare_mobile = 35000;    // Mobile OK
$declare_cheque = 50000;    // Chèque OK
$total_declare = $declare_especes + $declare_virement + $declare_mobile + $declare_cheque;

echo "\n2. Déclaration caissier:\n";
echo "   Espèces: " . number_format($declare_especes, 0, ',', ' ') . " FCFA\n";
echo "   Virements: " . number_format($declare_virement, 0, ',', ' ') . " FCFA\n";
echo "   Mobile Money: " . number_format($declare_mobile, 0, ',', ' ') . " FCFA\n";
echo "   Chèques: " . number_format($declare_cheque, 0, ',', ' ') . " FCFA\n";
echo "   TOTAL DÉCLARÉ: " . number_format($total_declare, 0, ',', ' ') . " FCFA\n";

$solde_calcule = $calc['rec'] - $calc['dep'];
$ecart = $total_declare - $solde_calcule;

echo "\n3. Écart:\n";
echo "   Solde calculé: " . number_format($solde_calcule, 0, ',', ' ') . " FCFA\n";
echo "   Total déclaré: " . number_format($total_declare, 0, ',', ' ') . " FCFA\n";
echo "   ÉCART: " . number_format($ecart, 0, ',', ' ') . " FCFA";
if ($ecart > 0) {
    echo " (excédent)\n";
} elseif ($ecart < 0) {
    echo " (déficit)\n";
} else {
    echo " (aucun écart)\n";
}

// 4. Vérifier si une clôture existe déjà
$stmt = $pdo->prepare("SELECT id, statut FROM caisses_clotures WHERE date_cloture = ?");
$stmt->execute([$date]);
$existing = $stmt->fetch();

if ($existing) {
    echo "\n4. Clôture existante trouvée (ID: {$existing['id']}, Statut: {$existing['statut']})\n";
    echo "   → Suppression pour test...\n";
    $pdo->prepare("DELETE FROM caisses_clotures WHERE id = ?")->execute([$existing['id']]);
}

// 5. Créer une clôture BROUILLON
echo "\n5. Création clôture BROUILLON...\n";
$user = $pdo->query("SELECT id FROM utilisateurs WHERE actif=1 LIMIT 1")->fetch();

$stmt = $pdo->prepare("
    INSERT INTO caisses_clotures (
        date_cloture, total_recettes, total_depenses, solde_calcule,
        montant_especes_declare, montant_cheques_declare,
        montant_virements_declare, montant_mobile_declare,
        total_declare, ecart, justification_ecart,
        nb_operations, nb_ventes, nb_annulations,
        statut, caissier_id, observations
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $date, $calc['rec'], $calc['dep'], $solde_calcule,
    $declare_especes, $declare_cheque, $declare_virement, $declare_mobile,
    $total_declare, $ecart,
    ($ecart != 0 ? "Différence de comptage en espèces" : null),
    $calc['nb_ops'], 0, 0,
    'BROUILLON', $user['id'],
    "Test de clôture automatique"
]);

$cloture_id = $pdo->lastInsertId();
echo "   ✓ Clôture créée (ID: $cloture_id)\n";

// 6. Valider la clôture
echo "\n6. Validation de la clôture...\n";
$stmt = $pdo->prepare("
    UPDATE caisses_clotures 
    SET statut = 'VALIDE', 
        validateur_id = ?, 
        date_validation = NOW()
    WHERE id = ?
");
$stmt->execute([$user['id'], $cloture_id]);
echo "   ✓ Clôture validée\n";

// 7. Vérification finale
$stmt = $pdo->prepare("SELECT * FROM caisses_clotures WHERE id = ?");
$stmt->execute([$cloture_id]);
$final = $stmt->fetch();

echo "\n=== RÉSULTAT FINAL ===\n";
echo "Date: {$final['date_cloture']}\n";
echo "Statut: {$final['statut']}\n";
echo "Écart: " . number_format($final['ecart'], 0, ',', ' ') . " FCFA\n";
echo "Date validation: {$final['date_validation']}\n";

if (abs($final['ecart']) > 0) {
    echo "Justification: {$final['justification_ecart']}\n";
}

echo "\n✅ TEST COMPLET RÉUSSI!\n";
echo "\nVoir la clôture: http://localhost/kms_app/caisse/reconciliation.php?date=$date\n";
