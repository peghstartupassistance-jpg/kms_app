<?php
require_once __DIR__ . '/../db/db.php';
$venteId = isset($argv[1]) ? (int)$argv[1] : 0;
if ($venteId <= 0) { echo 0; exit(0); }
$stmt = $pdo->prepare('SELECT COUNT(*) as c FROM stocks_mouvements WHERE source_type = ? AND source_id = ?');
$stmt->execute(['VENTE', $venteId]);
echo (int)$stmt->fetchColumn();
