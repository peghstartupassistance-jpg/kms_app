<?php
/**
 * Script d'export de la base de données KMS Gestion en SQL
 * Génère un dump SQL complet avec structure et données
 */

require_once __DIR__ . '/db/db.php';
global $pdo;

$filename = __DIR__ . '/kms_gestion_updated.sql';
$handle = fopen($filename, 'w');

if (!$handle) {
    die("Impossible de créer le fichier $filename\n");
}

// En-tête
fwrite($handle, "-- KMS Gestion - Export complet\n");
fwrite($handle, "-- Généré : " . date('Y-m-d H:i:s') . "\n");
fwrite($handle, "-- Cet export contient la structure et les données de la base KMS Gestion\n\n");

fwrite($handle, "SET NAMES utf8mb4;\n");
fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

// Récupérer liste des tables
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "Exporting table: $table\n";
    
    // DROP TABLE
    fwrite($handle, "-- ============================================\n");
    fwrite($handle, "-- TABLE: $table\n");
    fwrite($handle, "-- ============================================\n\n");
    fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n\n");
    
    // CREATE TABLE
    $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $createTable = $result['Create Table'];
    fwrite($handle, $createTable . ";\n\n");
    
    // INSERT DATA
    $stmt = $pdo->query("SELECT * FROM `$table`");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($rows) > 0) {
        // Récupérer les noms de colonnes
        $columns = array_keys($rows[0]);
        $columnList = "`" . implode("`, `", $columns) . "`";
        
        // Générer les INSERT
        foreach ($rows as $row) {
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = "NULL";
                } else {
                    $values[] = "'" . $pdo->quote($value) . "'";
                }
            }
            $valueList = implode(", ", $values);
            fwrite($handle, "INSERT INTO `$table` ($columnList) VALUES ($valueList);\n");
        }
        fwrite($handle, "\n");
    }
}

// Réactiver les clés étrangères
fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
fwrite($handle, "\n-- Fin du dump SQL\n");

fclose($handle);

echo "\n✅ Export complet générée avec succès !\n";
echo "Fichier : $filename\n";
echo "Taille : " . number_format(filesize($filename), 0, ',', ' ') . " bytes\n";
