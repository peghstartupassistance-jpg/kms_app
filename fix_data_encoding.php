<?php
// fix_data_encoding.php - Corriger les données mal encodées dans la base

$mysqli = new mysqli('localhost', 'root', '', 'kms_gestion');
$mysqli->set_charset('utf8mb4');

echo "🔧 Correction des données mal encodées...\n\n";

// Mapping des conversions courantes
$conversions = [
    'h??tel' => 'hôtel',
    'h??bergement' => 'hébergement',
    'Tour??' => 'Touré',
    'Traor??' => 'Traoré',
    'Kon??' => 'Koné',
    '??' => 'é',
    'r??seaux' => 'réseaux',
    'R??servation' => 'Réservation',
    'cr????es' => 'créées',
];

// Tables et colonnes à corriger
$tables_columns = [
    'clients' => ['nom', 'type_client'],
    'types_client' => ['libelle', 'description'],
    'utilisateurs' => ['nom_complet'],
    'produits' => ['nom', 'description'],
    'canaux_vente' => ['nom', 'description'],
];

$total_corrections = 0;

foreach ($tables_columns as $table => $columns) {
    echo "📋 Table: $table\n";
    
    // Vérifier si la table existe
    $check = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows == 0) {
        echo "  ⚠️ Table inexistante, ignorée\n\n";
        continue;
    }
    
    foreach ($columns as $column) {
        // Vérifier si la colonne existe
        $check_col = $mysqli->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($check_col->num_rows == 0) {
            echo "  ⚠️ Colonne '$column' inexistante\n";
            continue;
        }
        
        $corrections = 0;
        foreach ($conversions as $wrong => $correct) {
            $stmt = $mysqli->prepare("UPDATE `$table` SET `$column` = REPLACE(`$column`, ?, ?) WHERE `$column` LIKE ?");
            $search = "%$wrong%";
            $stmt->bind_param('sss', $wrong, $correct, $search);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            if ($affected > 0) {
                $corrections += $affected;
                echo "  ✅ $column: '$wrong' → '$correct' ($affected lignes)\n";
            }
            $stmt->close();
        }
        
        if ($corrections > 0) {
            $total_corrections += $corrections;
        } else {
            echo "  ✓ $column: aucune correction nécessaire\n";
        }
    }
    echo "\n";
}

echo "✅ Total: $total_corrections corrections effectuées\n\n";

// Vérifier le résultat
echo "🔍 Vérification des données corrigées:\n";
$result = $mysqli->query("SELECT nom, type_client FROM clients LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "  • " . $row['nom'] . " - " . $row['type_client'] . "\n";
    }
}

echo "\n✅ Correction terminée !\n";
$mysqli->close();
?>
