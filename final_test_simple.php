<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=kms_gestion;charset=utf8mb4', 'root', '');

echo "🔍 VÉRIFICATION SIMPLE\n\n";

// Avant
$pdo->exec("UPDATE ventes SET statut_encaissement = 'ATTENTE_PAIEMENT', journal_caisse_id = NULL WHERE id = 90");

// Récupérer avant
$stmt = $pdo->prepare("SELECT id, statut_encaissement, journal_caisse_id, montant_total_ttc FROM ventes WHERE id = 90");
$stmt->execute();
$before = $stmt->fetch(PDO::FETCH_ASSOC);

echo "AVANT:\n";
echo "  statut_encaissement: {$before['statut_encaissement']}\n";
echo "  journal_caisse_id: " . ($before['journal_caisse_id'] ?? '(NULL)') . "\n\n";

// Appeler directement l'API logique
require_once __DIR__ . '/lib/caisse.php';

$vente_id = 90;
$montant = $before['montant_total_ttc'];
$mode_paiement_id = 1;

echo "ENCAISSEMENT:\n";
echo "  vente_id: $vente_id\n";
echo "  montant: $montant\n";
echo "  mode_paiement_id: $mode_paiement_id\n\n";

try {
    $pdo->beginTransaction();
    
    // Créer journal caisse
    $journal_id = caisse_enregistrer_ecriture(
        $pdo,
        'RECETTE',
        $montant,
        'VENTE',
        $vente_id,
        'Encaissement vente V-20251214-143828',
        1,
        date('Y-m-d'),
        'V-20251214-143828',
        1,
        'VENTE',
        $vente_id
    );
    
    echo "Journal caisse créé: ID #$journal_id\n\n";
    
    // Mettre à jour vente
    $upd = $pdo->prepare("UPDATE ventes SET statut_encaissement = ?, journal_caisse_id = ? WHERE id = ?");
    $upd->execute(['ENCAISSE', $journal_id, $vente_id]);
    
    $pdo->commit();
    
    // Vérifier après
    $stmt = $pdo->prepare("SELECT id, statut_encaissement, journal_caisse_id FROM ventes WHERE id = 90");
    $stmt->execute();
    $after = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "APRÈS:\n";
    echo "  statut_encaissement: {$after['statut_encaissement']}\n";
    echo "  journal_caisse_id: {$after['journal_caisse_id']}\n\n";
    
    // Vérifier journal caisse
    $stmt = $pdo->prepare("SELECT id, vente_id, montant, sens FROM journal_caisse WHERE id = ?");
    $stmt->execute([$journal_id]);
    $jc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($jc) {
        echo "JOURNAL CAISSE:\n";
        echo "  ID: {$jc['id']}\n";
        echo "  Vente ID: {$jc['vente_id']}\n";
        echo "  Montant: {$jc['montant']} FCFA\n";
        echo "  Sens: {$jc['sens']}\n\n";
        echo "✅ TEST RÉUSSI! Phase 1.1 fonctionne!\n";
    } else {
        echo "❌ Journal caisse not found\n";
    }
    
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "❌ Erreur: {$e->getMessage()}\n";
}
?>
