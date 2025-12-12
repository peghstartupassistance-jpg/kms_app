<?php
require_once __DIR__ . '/../db/db.php';
$prodId = isset($argv[1]) ? (int)$argv[1] : 0;
if ($prodId <= 0) { echo json_encode([]); exit(0); }
$stmt = $pdo->prepare('SELECT id, date_mouvement, type_mouvement, quantite, source_type, source_id, commentaire FROM stocks_mouvements WHERE produit_id = ? ORDER BY id DESC LIMIT 10');
$stmt->execute([$prodId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows);
