<?php
require_once __DIR__ . '/../../security.php';
exigerConnexion();
exigerPermission('VENTES_CREER');
verifierCsrf($_POST['csrf_token'] ?? '');
require_once __DIR__ . '/../../lib/litiges.php';

// Nettoyer tout output buffer avant le JSON
if (ob_get_level()) ob_clean();
header('Content-Type: application/json; charset=utf-8');

global $pdo;

$input = $_POST;
$id = (int)($input['id'] ?? 0);
$statut = trim($input['statut'] ?? '');
$solution = trim($input['solution'] ?? '');
$montant_rembourse = (float)($input['montant_rembourse'] ?? 0);
$montant_avoir = (float)($input['montant_avoir'] ?? 0);
$quantite_remplacement = (int)($input['quantite_remplacement'] ?? 0);
$utilisateur_id = (int)($_SESSION['utilisateur']['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID requis']);
    exit;
}

try {
    // Dispatcher selon le statut
    if ($statut === 'REMBOURSEMENT_EFFECTUE' && $montant_rembourse > 0) {
        $result = litiges_resoudre_avec_remboursement(
            $pdo,
            $id,
            $montant_rembourse,
            $solution,
            ['utilisateur_id' => $utilisateur_id]
        );
    } elseif ($statut === 'REMPLACEMENT_EFFECTUE' && $quantite_remplacement > 0) {
        $result = litiges_resoudre_avec_remplacement(
            $pdo,
            $id,
            $quantite_remplacement,
            $solution,
            ['utilisateur_id' => $utilisateur_id]
        );
    } elseif ($statut === 'RESOLU' && $montant_avoir > 0) {
        $result = litiges_resoudre_avec_avoir(
            $pdo,
            $id,
            $montant_avoir,
            $solution,
            ['utilisateur_id' => $utilisateur_id]
        );
    } elseif ($statut === 'ABANDONNE') {
        $result = litiges_abandonner(
            $pdo,
            $id,
            $solution,
            $utilisateur_id
        );
    } else {
        // Mise à jour simple (solution seulement, pas d'impact financier/stock)
        $stmt = $pdo->prepare("
            UPDATE retours_litiges
            SET solution = :solution,
                statut_traitement = :statut
            WHERE id = :id
        ");
        $stmt->execute([
            'solution' => $solution,
            'statut'   => $statut,
            'id'       => $id,
        ]);
        
        $result = ['success' => true, 'message' => 'Litige mis à jour'];
    }
    
    http_response_code(200);
    echo json_encode($result);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur : ' . $e->getMessage()
    ]);
}
