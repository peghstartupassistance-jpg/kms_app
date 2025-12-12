<?php
// api/produits_recherche.php
require_once __DIR__ . '/../security.php';
exigerConnexion();

header('Content-Type: application/json; charset=utf-8');

global $pdo;

$q = trim($_GET['q'] ?? '');
$resultats = [];

if ($q !== '') {
    $like = '%' . $q . '%';

    $sql = "
        SELECT
            id,
            code_produit,
            designation,
            prix_vente,
            stock_actuel
        FROM produits
        WHERE actif = 1
          AND (code_produit LIKE ? OR designation LIKE ?)
        ORDER BY designation ASC
        LIMIT 20
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$like, $like]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $resultats[] = [
            'id'          => (int)$row['id'],
            'label'       => $row['code_produit'] . ' – ' . $row['designation'],
            'prix_vente'  => (float)$row['prix_vente'],
            'stock_actuel' => (int)$row['stock_actuel'],
        ];
    }
}

echo json_encode($resultats);
