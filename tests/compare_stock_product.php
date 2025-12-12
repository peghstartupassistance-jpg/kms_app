<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../lib/stock.php';
$prodId = isset($argv[1]) ? (int)$argv[1] : 0;
if ($prodId <= 0) { echo json_encode(null); exit(0); }
$stmt = $pdo->prepare('SELECT stock_actuel FROM produits WHERE id = ?');
$stmt->execute([$prodId]);
$stockActuel = $stmt->fetchColumn();
$theorique = stock_get_quantite_produit($pdo, $prodId);
echo json_encode(['produit_id' => $prodId, 'stock_actuel' => (int)$stockActuel, 'theorique' => (int)$theorique]);
