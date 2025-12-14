<?php
/**
 * Créer des données de test pour réconciliation aujourd'hui
 */
require_once 'security.php';
global $pdo;

$dateAujourdhui = date('Y-m-d');

echo "=== CRÉATION DONNÉES TEST RÉCONCILIATION ===\n";
echo "Date: $dateAujourdhui\n\n";

// Vérifier si des données existent déjà
$stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM journal_caisse WHERE date_operation = ?");
$stmt->execute([$dateAujourdhui]);
$existing = $stmt->fetch();

if ($existing['nb'] > 0) {
    echo "⚠️  {$existing['nb']} opérations existent déjà pour aujourd'hui.\n";
    echo "Voulez-vous continuer quand même? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    if ($line !== 'y' && $line !== 'Y') {
        echo "Annulé.\n";
        exit;
    }
    fclose($handle);
}

// Récupérer les modes de paiement
$modes = $pdo->query("SELECT id, libelle, code FROM modes_paiement ORDER BY id")->fetchAll();
echo "\n--- Modes de paiement disponibles ---\n";
foreach ($modes as $m) {
    echo "  {$m['id']}: {$m['libelle']} ({$m['code']})\n";
}

// Créer des recettes variées
$operations = [
    ['nature' => 'Vente comptoir #1001', 'sens' => 'RECETTE', 'montant' => 45000, 'mode_id' => 1], // Espèces
    ['nature' => 'Vente comptoir #1002', 'sens' => 'RECETTE', 'montant' => 25000, 'mode_id' => 1],
    ['nature' => 'Vente entreprise #1003', 'sens' => 'RECETTE', 'montant' => 150000, 'mode_id' => 2], // Virement
    ['nature' => 'Vente mobile #1004', 'sens' => 'RECETTE', 'montant' => 35000, 'mode_id' => 3], // Mobile Money
    ['nature' => 'Vente #1005', 'sens' => 'RECETTE', 'montant' => 50000, 'mode_id' => 4], // Chèque
    ['nature' => 'Remise espèces en banque', 'sens' => 'DEPENSE', 'montant' => 100000, 'mode_id' => 1],
    ['nature' => 'Achat fournitures bureau', 'sens' => 'DEPENSE', 'montant' => 15000, 'mode_id' => 1],
    ['nature' => 'Vente #1006', 'sens' => 'RECETTE', 'montant' => 75000, 'mode_id' => 1],
    ['nature' => 'Vente #1007', 'sens' => 'RECETTE', 'montant' => 60000, 'mode_id' => 1],
    ['nature' => 'Paiement fournisseur', 'sens' => 'DEPENSE', 'montant' => 50000, 'mode_id' => 2],
];

// Récupérer un utilisateur pour le champ obligatoire
$user = $pdo->query("SELECT id FROM utilisateurs WHERE actif = 1 LIMIT 1")->fetch();
$userId = $user['id'];

$stmtInsert = $pdo->prepare("
    INSERT INTO journal_caisse (
        date_operation, numero_piece, nature_operation, sens, montant, 
        mode_paiement_id, responsable_encaissement_id, observations, est_annule, type_operation
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Données de test', 0, 'TEST')
");

$count = 0;
foreach ($operations as $op) {
    $numero_piece = 'CAI-' . date('Ymd') . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    $stmtInsert->execute([
        $dateAujourdhui,
        $numero_piece,
        $op['nature'],
        $op['sens'],
        $op['montant'],
        $op['mode_id'],
        $userId
    ]);
    $count++;
    echo "✓ {$op['nature']}: {$op['sens']} " . number_format($op['montant'], 0, ',', ' ') . " FCFA\n";
}

// Calculer les totaux
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN sens='RECETTE' AND est_annule=0 THEN montant ELSE 0 END), 0) as recettes,
        COALESCE(SUM(CASE WHEN sens='DEPENSE' AND est_annule=0 THEN montant ELSE 0 END), 0) as depenses
    FROM journal_caisse WHERE date_operation = ?
");
$stmt->execute([$dateAujourdhui]);
$totaux = $stmt->fetch();

$solde = $totaux['recettes'] - $totaux['depenses'];

echo "\n=== RÉSUMÉ ===\n";
echo "Opérations créées: $count\n";
echo "Total recettes: " . number_format($totaux['recettes'], 0, ',', ' ') . " FCFA\n";
echo "Total dépenses: " . number_format($totaux['depenses'], 0, ',', ' ') . " FCFA\n";
echo "Solde attendu: " . number_format($solde, 0, ',', ' ') . " FCFA\n";

echo "\n✅ Données créées! Vous pouvez maintenant tester la réconciliation.\n";
echo "\nURL: http://localhost/kms_app/caisse/reconciliation.php?date=$dateAujourdhui\n";
