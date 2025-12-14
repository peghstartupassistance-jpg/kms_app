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
$client_id = (int)($input['client_id'] ?? 0);
$vente_id = (int)($input['vente_id'] ?? 0) ?: null;
$produit_id = (int)($input['produit_id'] ?? 0);
$type_probleme = trim($input['type_probleme'] ?? 'AUTRE');
$motif_detaille = trim($input['motif_detaille'] ?? '');
$date_retour = $input['date_retour'] ?? date('Y-m-d');
$quantite_retournee = (int)($input['quantite_retournee'] ?? 0);
$responsable_id = (int)($_SESSION['utilisateur']['id'] ?? 0);

if (!$client_id || !$produit_id || $motif_detaille === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Champs requis manquants (client, produit, motif)']);
    exit;
}

try {
    $result = litiges_creer_avec_retour(
        $pdo,
        $client_id,
        $produit_id,
        $vente_id,
        $type_probleme,
        $motif_detaille,
        $responsable_id,
        [
            'date_retour' => $date_retour,
            'quantite_retournee' => $quantite_retournee,
        ]
    );
    
    http_response_code(201);
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la création du litige : ' . $e->getMessage()
    ]);
}
