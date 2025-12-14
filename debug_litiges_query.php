<?php
require_once 'security.php';
global $pdo;

echo "<h1>Debug Litiges Query</h1>";

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
$litiges = $stmt->fetchAll();

echo "<p>Type de \$litiges: " . gettype($litiges) . "</p>";
echo "<p>Count: " . count($litiges) . "</p>";

if (is_array($litiges) && count($litiges) > 0) {
    echo "<h2>Première ligne:</h2>";
    $first = $litiges[0];
    echo "<pre>";
    var_dump($first);
    echo "</pre>";
    
    echo "<h2>Toutes les lignes:</h2>";
    foreach ($litiges as $i => $litige) {
        echo "<p><strong>Ligne $i:</strong>";
        echo " Type: " . gettype($litige) . " | ";
        if (is_array($litige)) {
            echo "ID: " . $litige['id'] . " | Client: " . $litige['client_nom'];
        } else {
            echo "VALEUR: $litige";
        }
        echo "</p>";
    }
} else {
    echo "<p>Aucun litige retourné ou pas un array</p>";
}

// Vérifier la table retours_litiges
echo "<h2>Contenu de retours_litiges:</h2>";
$test = $pdo->query("SELECT id, client_id, statut_traitement, date_retour FROM retours_litiges LIMIT 5");
$rows = $test->fetchAll();
echo "<pre>";
var_dump($rows);
echo "</pre>";
