<?php
// fix_all_encoding.php - Correction complète de l'encodage UTF-8 pour toutes les tables

set_time_limit(300); // 5 minutes max

$mysqli = new mysqli('localhost', 'root', '', 'kms_gestion');
$mysqli->set_charset('utf8mb4');

echo "🔧 CORRECTION COMPLÈTE DE L'ENCODAGE UTF-8\n";
echo str_repeat("=", 70) . "\n\n";

// 1. Forcer UTF-8 pour la connexion
echo "1️⃣ Configuration de la connexion...\n";
$mysqli->query("SET NAMES utf8mb4");
$mysqli->query("SET CHARACTER SET utf8mb4");
$mysqli->query("SET character_set_connection=utf8mb4");
echo "✅ Connexion UTF-8 configurée\n\n";

// 2. Mapping des conversions
$conversions = [
    // Lettres accentuées
    'Ã©' => 'é',
    'Ã¨' => 'è',
    'Ãª' => 'ê',
    'Ã«' => 'ë',
    'Ã ' => 'à',
    'Ã¢' => 'â',
    'Ã´' => 'ô',
    'Ã®' => 'î',
    'Ã¯' => 'ï',
    'Ã§' => 'ç',
    'Ã¹' => 'ù',
    'Ã»' => 'û',
    
    // Combinaisons courantes
    'h??tel' => 'hôtel',
    'h??bergement' => 'hébergement',
    'int??rieur' => 'intérieur',
    'ext??rieur' => 'extérieur',
    'R??mun??rations' => 'Rémunérations',
    'r??mun??rations' => 'rémunérations',
    'R??mun' => 'Rémun',
    'r??seaux' => 'réseaux',
    'R??servation' => 'Réservation',
    'r??servation' => 'réservation',
    'cr????es' => 'créées',
    'cr????e' => 'créée',
    '??' => 'é',
    
    // Noms propres
    'Tour??' => 'Touré',
    'Traor??' => 'Traoré',
    'Kon??' => 'Koné',
    'tour??' => 'touré',
    'traor??' => 'traoré',
    'kon??' => 'koné',
];

// 3. Récupérer toutes les tables
echo "2️⃣ Analyse des tables...\n";
$result = $mysqli->query("
    SELECT TABLE_NAME 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = 'kms_gestion' 
    AND TABLE_TYPE = 'BASE TABLE'
    ORDER BY TABLE_NAME
");

$tables = [];
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}
echo "📊 " . count($tables) . " tables à analyser\n\n";

// 4. Pour chaque table, identifier les colonnes texte et corriger
echo "3️⃣ Correction des données...\n";
$total_corrections = 0;
$tables_corrected = 0;

foreach ($tables as $table) {
    // Récupérer les colonnes de type texte
    $columns_result = $mysqli->query("
        SELECT COLUMN_NAME, DATA_TYPE 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = 'kms_gestion' 
        AND TABLE_NAME = '$table'
        AND DATA_TYPE IN ('varchar', 'text', 'char', 'mediumtext', 'longtext', 'tinytext', 'enum')
    ");
    
    $text_columns = [];
    while ($col = $columns_result->fetch_assoc()) {
        $text_columns[] = $col['COLUMN_NAME'];
    }
    
    if (empty($text_columns)) {
        continue;
    }
    
    $table_corrections = 0;
    
    foreach ($text_columns as $column) {
        foreach ($conversions as $wrong => $correct) {
            $stmt = $mysqli->prepare("UPDATE `$table` SET `$column` = REPLACE(`$column`, ?, ?) WHERE `$column` LIKE ?");
            if (!$stmt) {
                continue;
            }
            
            $search = "%$wrong%";
            $stmt->bind_param('sss', $wrong, $correct, $search);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            
            if ($affected > 0) {
                $table_corrections += $affected;
                echo "  ✅ $table.$column: '$wrong' → '$correct' ($affected)\n";
            }
            $stmt->close();
        }
    }
    
    if ($table_corrections > 0) {
        $total_corrections += $table_corrections;
        $tables_corrected++;
    }
}

echo "\n✅ Total: $total_corrections corrections dans $tables_corrected tables\n\n";

// 5. Vérifier quelques exemples
echo "4️⃣ Vérification des résultats...\n";

// Vérifier les noms
$result = $mysqli->query("SELECT nom FROM clients WHERE nom LIKE '%é%' OR nom LIKE '%è%' LIMIT 5");
if ($result && $result->num_rows > 0) {
    echo "📋 Clients:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  • " . $row['nom'] . "\n";
    }
}

// Vérifier les comptes compta
$result = $mysqli->query("SELECT numero, libelle FROM compta_comptes WHERE libelle LIKE '%é%' OR libelle LIKE '%è%' LIMIT 5");
if ($result && $result->num_rows > 0) {
    echo "\n📋 Comptes comptables:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  • " . $row['numero'] . " - " . $row['libelle'] . "\n";
    }
}

// Vérifier les formations
$result = $mysqli->query("SELECT titre FROM formations WHERE titre LIKE '%é%' OR titre LIKE '%è%' LIMIT 3");
if ($result && $result->num_rows > 0) {
    echo "\n📋 Formations:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  • " . $row['titre'] . "\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "✅ CORRECTION TERMINÉE !\n";
echo "🔄 Actualisez vos pages pour voir les changements.\n";

$mysqli->close();
?>
