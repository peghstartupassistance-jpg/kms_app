<?php
require_once __DIR__ . '/db/db.php';

global $pdo;

$sql = "
    SELECT rl.*,
           c.nom AS client_nom,
           c.telephone AS client_telephone,
           v.numero AS numero_vente,
           p.code_produit,
           p.designation AS produit_designation,
           u.nom_complet AS responsable
    FROM retours_litiges rl
    INNER JOIN clients c ON rl.client_id = c.id
    LEFT JOIN ventes v ON rl.vente_id = v.id
    LEFT JOIN produits p ON rl.produit_id = p.id
    LEFT JOIN utilisateurs u ON rl.responsable_suivi_id = u.id
    ORDER BY 
        CASE rl.statut_traitement
            WHEN 'EN_COURS' THEN 1
            WHEN 'RESOLU' THEN 2
            WHEN 'REMPLACEMENT_EFFECTUE' THEN 3
            WHEN 'REMBOURSEMENT_EFFECTUE' THEN 4
            WHEN 'ABANDONNE' THEN 5
        END,
        rl.date_retour DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([]);
$litiges = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Type de \$litiges: " . gettype($litiges) . "\n";
echo "Count: " . count($litiges) . "\n";

if (!empty($litiges)) {
    echo "\nPremière ligne:\n";
    var_dump($litiges[0]);
    echo "\nDeuxième élément du array retourné:\n";
    var_dump($litiges[1] ?? 'N/A');
}
?>
