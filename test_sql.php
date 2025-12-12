<?php
require_once __DIR__ . '/db/db.php';

// Requête simple pour voir ce qu'il y a
$sql = "
    SELECT 
        p.id,
        p.numero_piece,
        p.est_validee,
        COUNT(ce.id) as nb_ecritures
    FROM compta_pieces p
    LEFT JOIN compta_ecritures ce ON ce.piece_id = p.id
    GROUP BY p.id
    LIMIT 5
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

var_dump($result);
?>
