<?php
require_once __DIR__ . '/../../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');
header('Content-Type: application/json');

$venteId = (int)($_GET['id'] ?? 0);
if ($venteId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vente invalide']);
    exit;
}

global $pdo;
$stmt = $pdo->prepare('SELECT client_id FROM ventes WHERE id = :id');
$stmt->execute(['id' => $venteId]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Vente introuvable']);
    exit;
}

echo json_encode([
    'success' => true,
    'client_id' => (int)$row['client_id'],
]);
