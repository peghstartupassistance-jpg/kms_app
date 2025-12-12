<?php
require_once __DIR__ . '/../db/db.php';
$stmt = $pdo->query("SELECT v.id FROM ventes v JOIN ventes_lignes l ON l.vente_id = v.id GROUP BY v.id LIMIT 1");
$r = $stmt->fetch(PDO::FETCH_ASSOC);
echo $r ? (int)$r['id'] : 0;
