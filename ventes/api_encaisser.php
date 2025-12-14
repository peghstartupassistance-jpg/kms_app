<?php
/**
 * API endpoint: Encaisser une vente
 * POST /ventes/api_encaisser.php
 * 
 * Payload:
 * {
 *   "vente_id": 123,
 *   "montant": 1000000,
 *   "mode_paiement_id": 1,
 *   "observations": "chèque client X"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "journal_caisse_id": 456,
 *   "message": "Encaissement enregistré"
 * }
 */

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../lib/caisse.php';

exigerConnexion();

global $pdo;

// Récupérer données JSON
$data = json_decode(file_get_contents('php://input'), true);

$vente_id = (int)($data['vente_id'] ?? 0);
$montant = (float)($data['montant'] ?? 0);
$mode_paiement_id = (int)($data['mode_paiement_id'] ?? 0);
$observations = trim($data['observations'] ?? '');

if (!$vente_id || !$montant || !$mode_paiement_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Paramètres manquants'
    ]);
    exit;
}

try {
    // Vérifier la vente
    $stmtVente = $pdo->prepare("
        SELECT v.*, c.nom as client_nom
        FROM ventes v
        LEFT JOIN clients c ON c.id = v.client_id
        WHERE v.id = :id
    ");
    $stmtVente->execute(['id' => $vente_id]);
    $vente = $stmtVente->fetch();

    if (!$vente) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Vente non trouvée'
        ]);
        exit;
    }

    // Éviter l'encaissement multiple
    if (($vente['statut_encaissement'] ?? 'ATTENTE_PAIEMENT') === 'ENCAISSE') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Vente déjà encaissée'
        ]);
        exit;
    }

    // Vérifier mode paiement
    $stmtMode = $pdo->prepare("SELECT id FROM modes_paiement WHERE id = ?");
    $stmtMode->execute([$mode_paiement_id]);
    if (!$stmtMode->fetch()) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Mode de paiement invalide'
        ]);
        exit;
    }

    // Contrôle montant (strict égal au TTC de la vente)
    $montantVenteTTC = (float)($vente['montant_total_ttc'] ?? 0);
    if ($montant <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Montant invalide'
        ]);
        exit;
    }
    // Refus si écart
    if (abs($montant - $montantVenteTTC) > 0.009) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Discordance montant: attendu ' . number_format($montantVenteTTC, 2, ',', ' ') . ' FCFA'
        ]);
        exit;
    }

    $pdo->beginTransaction();

    $utilisateur = utilisateurConnecte();
    $utilisateurId = $utilisateur['id'] ?? null;
    $today = date('Y-m-d');

    // Enregistrer dans journal caisse
    $journal_caisse_id = null;
    try {
        $journal_caisse_id = caisse_enregistrer_ecriture(
            $pdo,
            'RECETTE',
            $montant,
            'VENTE',          // source_type
            $vente_id,        // source_id
            'Encaissement vente ' . htmlspecialchars($vente['numero']),  // commentaire
            $utilisateurId,   // utilisateur_id
            $today,           // date_operation
            $vente['numero'], // numero_piece
            $mode_paiement_id, // mode_paiement_id
            'VENTE',          // type_operation
            $vente_id         // vente_id ← IMPORTANT: Passer vente_id correctement!
        );
    } catch (Throwable $e) {
        throw new Exception("Erreur enregistrement caisse: " . $e->getMessage());
    }

    if (!$journal_caisse_id) {
        throw new Exception("Impossible de créer l'écriture caisse");
    }

    // Mettre à jour la vente: statut_encaissement + journal_caisse_id
    $stmtUpdate = $pdo->prepare("
        UPDATE ventes 
        SET statut_encaissement = 'ENCAISSE',
            journal_caisse_id = ?
        WHERE id = ?
    ");
    $stmtUpdate->execute([$journal_caisse_id, $vente_id]);

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'journal_caisse_id' => $journal_caisse_id,
        'message' => 'Encaissement enregistré avec succès'
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>
