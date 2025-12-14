<?php
require_once __DIR__ . '/../../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');
verifierCsrf($_POST['csrf_token'] ?? '');

// Nettoyer tout output buffer avant le JSON
if (ob_get_level()) ob_clean();
header('Content-Type: application/json; charset=utf-8');

global $pdo;

$input = $_POST;
$client_id = (int)($input['client_id'] ?? 0);
$vente_id = (int)($input['vente_id'] ?? 0);
$produit_id = (int)($input['produit_id'] ?? 0);
$type_probleme = trim($input['type_probleme'] ?? '');
$motif_detaille = trim($input['motif_detaille'] ?? '');
$date_retour = $input['date_retour'] ?? date('Y-m-d');
$responsable_id = (int)($_SESSION['utilisateur']['id'] ?? 0);

if (!$client_id || !$produit_id || $motif_detaille === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Champs requis manquants (client, produit, motif)']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO retours_litiges (client_id, produit_id, vente_id, motif, date_retour, responsable_suivi_id, statut_traitement) VALUES (:client, :produit, :vente, :motif, :date_retour, :resp, 'EN_COURS')");
$stmt->execute([
    'client' => $client_id,
    'produit' => $produit_id,
    'vente' => $vente_id ?: null,
    'motif' => $motif_detaille,
    'date_retour' => $date_retour,
    'resp' => $responsable_id,
]);

$id = $pdo->lastInsertId();
echo json_encode(['success' => true, 'message' => 'Litige créé', 'id' => $id]);
