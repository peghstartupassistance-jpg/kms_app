<?php
require_once __DIR__ . '/../../security.php';
exigerConnexion();
exigerPermission('PRODUITS_LIRE');
header('Content-Type: application/json');

global $pdo;
$q = trim($_GET['q'] ?? '');
if ($q === '') { echo json_encode([]); exit; }

$stmt = $pdo->prepare("SELECT id, code_produit, designation, stock_actuel FROM produits WHERE code_produit LIKE :q OR designation LIKE :q2 ORDER BY designation LIMIT 10");
$stmt->execute(['q' => "%$q%", 'q2' => "%$q%"]);
$rows = $stmt->fetchAll();

echo json_encode(array_map(function($r){
    return [
        'id' => (int)$r['id'],
        'label' => ($r['code_produit'] ?: '') . ' • ' . $r['designation'] . ' • Stock: ' . (int)($r['stock_actuel'] ?? 0),
        'code' => $r['code_produit'] ?: '',
        'designation' => $r['designation'],
        'stock' => (int)($r['stock_actuel'] ?? 0)
    ];
}, $rows));
