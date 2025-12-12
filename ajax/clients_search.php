<?php
// ajax/clients_search.php
require_once __DIR__ . '/../security.php';
exigerConnexion();

header('Content-Type: application/json; charset=utf-8');

global $pdo;

$q = trim($_GET['q'] ?? '');
$resultats = [];

// On lance la recherche dès qu'il y a au moins 1 caractère
if ($q !== '') {
    $like = '%' . $q . '%';

    $sql = "
        SELECT 
            id,
            nom,
            telephone,
            email
        FROM clients
        WHERE 
            nom LIKE ?
            OR telephone LIKE ?
            OR email LIKE ?
        ORDER BY nom ASC
        LIMIT 20
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$like, $like, $like]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $parts = [];

        if (!empty($row['nom'])) {
            $parts[] = $row['nom'];
        }
        if (!empty($row['telephone'])) {
            $parts[] = $row['telephone'];
        }
        if (!empty($row['email'])) {
            $parts[] = $row['email'];
        }

        $label = implode(' • ', $parts);

        $resultats[] = [
            'id'    => (int) $row['id'],
            'label' => $label !== '' ? $label : ('Client #' . (int)$row['id']),
        ];
    }
}

echo json_encode($resultats);
