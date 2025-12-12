<?php
// ajax/clients_search.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CLIENTS_LIRE');

header('Content-Type: application/json; charset=utf-8');

global $pdo;

$term = trim($_GET['term'] ?? '');

if ($term === '' || mb_strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

// On recherche dans nom / téléphone / email de la table `clients`
$sql = "
    SELECT id, nom, telephone, email
    FROM clients
    WHERE nom LIKE :term
       OR telephone LIKE :term
       OR email LIKE :term
    ORDER BY nom
    LIMIT 10
";

$stmt = $pdo->prepare($sql);
$like = '%' . $term . '%';
$stmt->execute(['term' => $like]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = [];
foreach ($rows as $row) {
    $parts = [$row['nom']];

    if (!empty($row['telephone'])) {
        $parts[] = $row['telephone'];
    }
    if (!empty($row['email'])) {
        $parts[] = $row['email'];
    }

    $result[] = [
        'id'        => (int)$row['id'],
        'nom'       => $row['nom'],
        'telephone' => $row['telephone'],
        'email'     => $row['email'],
        'label'     => implode(' • ', $parts),
    ];
}

echo json_encode($result);
