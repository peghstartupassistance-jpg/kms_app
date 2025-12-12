<?php
require_once __DIR__ . '/../db/db.php';
$code = $argv[1] ?? '';
if ($code === '') { echo json_encode(null); exit(0); }
$stmt = $pdo->prepare('SELECT id, code_produit, stock_actuel FROM produits WHERE code_produit = ?');
$stmt->execute([$code]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($r);
