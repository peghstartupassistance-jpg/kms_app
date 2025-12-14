<?php
require_once __DIR__ . '/../../security.php';
exigerConnexion();
exigerPermission('CLIENTS_LIRE');
header('Content-Type: application/json');

global $pdo;
$q = trim($_GET['q'] ?? '');
if ($q === '') { echo json_encode([]); exit; }

$stmt = $pdo->prepare("SELECT id, nom, telephone FROM clients WHERE nom LIKE :q OR telephone LIKE :q2 ORDER BY nom LIMIT 10");
$stmt->execute(['q' => "%$q%", 'q2' => "%$q%"]);
$rows = $stmt->fetchAll();

echo json_encode(array_map(function($r){
    return [
        'id' => (int)$r['id'],
        'label' => $r['nom'] . (isset($r['telephone']) && $r['telephone'] ? ' • ' . $r['telephone'] : ''),
        'nom' => $r['nom'],
        'telephone' => $r['telephone'] ?? ''
    ];
}, $rows));
