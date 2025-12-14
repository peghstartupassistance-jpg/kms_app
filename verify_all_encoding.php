<?php
// verify_all_encoding.php - Vérification complète de l'encodage UTF-8

require_once 'db/db.php';

echo "🔍 VÉRIFICATION COMPLÈTE DE L'ENCODAGE UTF-8\n";
echo str_repeat("=", 70) . "\n\n";

// 1. Configuration MySQL
echo "1️⃣ Configuration MySQL:\n";
$result = $pdo->query('SELECT @@character_set_database, @@character_set_client, @@character_set_connection, @@character_set_results')->fetch(PDO::FETCH_NUM);
echo "   ✅ Database: " . $result[0] . "\n";
echo "   ✅ Client: " . $result[1] . "\n";
echo "   ✅ Connection: " . $result[2] . "\n";
echo "   ✅ Results: " . $result[3] . "\n\n";

// 2. Tables
echo "2️⃣ Encodage des tables:\n";
$tables = $pdo->query("
    SELECT TABLE_NAME, TABLE_COLLATION 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = 'kms_gestion' 
    AND TABLE_TYPE = 'BASE TABLE'
    AND TABLE_COLLATION NOT LIKE 'utf8mb4%'
")->fetchAll();

if (empty($tables)) {
    echo "   ✅ Toutes les tables sont en UTF8MB4\n\n";
} else {
    echo "   ⚠️ Tables non UTF8MB4:\n";
    foreach ($tables as $t) {
        echo "      • " . $t['TABLE_NAME'] . " (" . $t['TABLE_COLLATION'] . ")\n";
    }
    echo "\n";
}

// 3. Vérification des données
echo "3️⃣ Vérification des données:\n";

// Clients
$result = $pdo->query("SELECT nom FROM clients WHERE nom LIKE '%Touré%' OR nom LIKE '%Koné%' OR nom LIKE '%Traoré%' LIMIT 5");
$rows = $result->fetchAll();
if (!empty($rows)) {
    echo "   ✅ Clients (noms avec accents):\n";
    foreach ($rows as $r) {
        echo "      • " . $r['nom'] . "\n";
    }
}

// Comptes compta
$result = $pdo->query("SELECT numero, libelle FROM compta_comptes WHERE libelle LIKE '%Rémun%' OR libelle LIKE '%tér%' LIMIT 3");
$rows = $result->fetchAll();
if (!empty($rows)) {
    echo "\n   ✅ Comptes comptables:\n";
    foreach ($rows as $r) {
        echo "      • " . $r['numero'] . " - " . $r['libelle'] . "\n";
    }
}

// Formations
$result = $pdo->query("SELECT titre FROM formations WHERE titre LIKE '%intér%' OR titre LIKE '%Agenc%' LIMIT 3");
$rows = $result->fetchAll();
if (!empty($rows)) {
    echo "\n   ✅ Formations:\n";
    foreach ($rows as $r) {
        echo "      • " . $r['titre'] . "\n";
    }
}

// Produits catalogue
$result = $pdo->query("SELECT designation FROM catalogue_produits WHERE description LIKE '%intérieur%' OR description LIKE '%extérieur%' LIMIT 3");
$rows = $result->fetchAll();
if (!empty($rows)) {
    echo "\n   ✅ Produits catalogue:\n";
    foreach ($rows as $r) {
        echo "      • " . $r['designation'] . "\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "✅ VÉRIFICATION TERMINÉE !\n";
echo "🔄 Si vous voyez correctement les accents ci-dessus, l'encodage est OK.\n";
?>
