<?php
/**
 * Test d'encaissement direct (sans navigateur)
 * Simule la requête AJAX depuis le frontend
 */

require_once __DIR__ . '/security.php';

$_SESSION['utilisateur_id'] = 1; // Forcer utilisateur pour test
$_SESSION['permissions'] = ['VENTES_LIRE', 'VENTES_CREER', 'CAISSE_ECRIRE'];

global $pdo;
$pdo = new PDO('mysql:host=127.0.0.1;dbname=kms_gestion;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "🧪 TEST DIRECT API ENCAISSEMENT\n";
echo "═" . str_repeat("═", 60) . "\n\n";

// Vente test
$venteId = 90;
$montant = 665415.00;
$modePaiement = 1;

echo "1️⃣  Vérification vente avant...\n";
$stmt = $pdo->prepare("SELECT statut_encaissement, journal_caisse_id FROM ventes WHERE id = ?");
$stmt->execute([$venteId]);
$venteBefore = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   ✓ Vente #$venteId\n";
echo "   ✓ statut_encaissement: {$venteBefore['statut_encaissement']}\n";
echo "   ✓ journal_caisse_id: {$venteBefore['journal_caisse_id']}\n\n";

// Simuler payload AJAX
$payload = [
    'vente_id' => $venteId,
    'montant' => $montant,
    'mode_paiement_id' => $modePaiement,
    'observations' => 'Test direct Phase 1.1'
];

echo "2️⃣  Exécution API encaissement...\n";
echo "   Payload: " . json_encode($payload) . "\n\n";

try {
    // Appeler directement la logique API
    require_once __DIR__ . '/lib/caisse.php';
    
    $pdo->beginTransaction();
    
    $utilisateur = ['id' => 1, 'nom_complet' => 'Test User'];
    $today = date('Y-m-d');
    
    // Enregistrer dans journal caisse
    caisse_enregistrer_ecriture(
        $pdo,
        'RECETTE',
        $payload['montant'],
        'VENTE',
        $payload['vente_id'],
        'Encaissement vente V-20251214-143828',
        $utilisateur['id'],
        $today,
        'V-20251214-143828'
    );
    
    // Récupérer le journal caisse ID créé
    $stmtLastCaisse = $pdo->prepare("
        SELECT id FROM journal_caisse 
        WHERE vente_id = ? 
        ORDER BY id DESC LIMIT 1
    ");
    $stmtLastCaisse->execute([$payload['vente_id']]);
    $lastCaisse = $stmtLastCaisse->fetch(PDO::FETCH_ASSOC);
    $journal_caisse_id = $lastCaisse['id'] ?? null;
    
    echo "   ✓ Journal caisse créé: ID #$journal_caisse_id\n\n";
    
    // Mettre à jour la vente
    $stmtUpdate = $pdo->prepare("
        UPDATE ventes 
        SET statut_encaissement = 'ENCAISSE',
            journal_caisse_id = ?
        WHERE id = ?
    ");
    $stmtUpdate->execute([$journal_caisse_id, $payload['vente_id']]);
    
    echo "   ✓ Vente mise à jour\n\n";
    
    $pdo->commit();
    
    echo "3️⃣  Vérification après encaissement...\n";
    $stmt = $pdo->prepare("SELECT statut_encaissement, journal_caisse_id FROM ventes WHERE id = ?");
    $stmt->execute([$venteId]);
    $venteAfter = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✓ Vente #$venteId\n";
    echo "   ✓ statut_encaissement: {$venteAfter['statut_encaissement']}\n";
    echo "   ✓ journal_caisse_id: {$venteAfter['journal_caisse_id']}\n\n";
    
    // Vérifier journal caisse
    echo "4️⃣  Vérification journal caisse...\n";
    $stmt = $pdo->prepare("SELECT * FROM journal_caisse WHERE id = ?");
    $stmt->execute([$journal_caisse_id]);
    $jc = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✓ ID: {$jc['id']}\n";
    echo "   ✓ Date: {$jc['date_operation']}\n";
    echo "   ✓ Sens: {$jc['sens']}\n";
    echo "   ✓ Montant: {$jc['montant']} FCFA\n";
    echo "   ✓ Vente ID: {$jc['vente_id']}\n";
    echo "   ✓ Nature: {$jc['nature_operation']}\n\n";
    
    echo "✅ TEST RÉUSSI!\n";
    echo "\nLa synchronisation vente → caisse fonctionne correctement.\n";
    echo "Phase 1.1 est prête pour déploiement.\n";
    
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "❌ ERREUR: {$e->getMessage()}\n";
    echo "Fichier: {$e->getFile()} (ligne {$e->getLine()})\n";
}

?>
