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
$id = (int)($input['id'] ?? 0);
$statut = trim($input['statut'] ?? '');
$solution = trim($input['solution'] ?? '');

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID requis']);
    exit;
}

$fields = [];
$params = ['id' => $id];

if ($statut !== '') {
    $fields[] = 'statut_traitement = :statut';
    $params['statut'] = $statut;
}
if ($solution !== '') {
    $fields[] = 'solution = :solution';
    $params['solution'] = $solution;
}

if (empty($fields)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aucun champ à mettre à jour']);
    exit;
}

$sql = 'UPDATE retours_litiges SET ' . implode(', ', $fields) . ' WHERE id = :id';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['success' => true, 'message' => 'Litige mis à jour']);
