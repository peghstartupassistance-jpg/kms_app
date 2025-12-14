<?php
require_once __DIR__ . '/../../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');
header('Content-Type: application/json');

global $pdo;
$q = trim($_GET['q'] ?? '');
$clientId = (int)($_GET['client_id'] ?? 0);
if ($q === '' && $clientId === 0) { echo json_encode([]); exit; }

$where = [];
$params = [];
if ($q !== '') { $where[] = 'v.numero LIKE :q'; $params['q'] = "%$q%"; }
if ($clientId > 0) { $where[] = 'v.client_id = :cid'; $params['cid'] = $clientId; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT v.id, v.numero, v.client_id, v.date_vente, v.montant_total_ttc, v.statut, c.nom AS client_nom FROM ventes v JOIN clients c ON c.id = v.client_id $whereSql ORDER BY v.date_vente DESC LIMIT 10");
$stmt->execute($params);
$rows = $stmt->fetchAll();

echo json_encode(array_map(function($r){
    return [
        'id' => (int)$r['id'],
        'label' => $r['numero'] . ' • ' . $r['client_nom'] . ' • ' . date('d/m/Y', strtotime($r['date_vente'])) . ' • ' . number_format((float)$r['montant_total_ttc'], 0, ',', ' ') . ' FCFA',
        'numero' => $r['numero'],
        'client_nom' => $r['client_nom'],
        'client_id' => (int)$r['client_id'],
        'date' => $r['date_vente'],
        'montant' => (float)$r['montant_total_ttc'],
        'statut' => $r['statut']
    ];
}, $rows));
