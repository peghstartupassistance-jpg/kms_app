<?php
// fix_encoding.php - Corriger l'encodage UTF-8 de la base de données

$mysqli = new mysqli('localhost', 'root', '', 'kms_gestion');

if ($mysqli->connect_error) {
    die("❌ Erreur de connexion: " . $mysqli->connect_error);
}

echo "🔧 Correction de l'encodage UTF-8...\n\n";

// 1. Forcer l'encodage UTF-8 pour la connexion
echo "1️⃣ Configuration de la connexion UTF-8...\n";
$mysqli->set_charset('utf8mb4');
$mysqli->query("SET NAMES utf8mb4");
$mysqli->query("SET CHARACTER SET utf8mb4");
$mysqli->query("SET character_set_connection=utf8mb4");
echo "✅ Connexion configurée en UTF-8\n\n";

// 2. Convertir la base de données
echo "2️⃣ Conversion de la base de données...\n";
$mysqli->query("ALTER DATABASE kms_gestion CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci");
echo "✅ Base de données convertie\n\n";

// 3. Lister toutes les tables (exclure les vues)
echo "3️⃣ Conversion des tables...\n";
$result = $mysqli->query("
    SELECT TABLE_NAME 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = 'kms_gestion' 
    AND TABLE_TYPE = 'BASE TABLE'
");
$tables = [];
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

$converted = 0;
foreach ($tables as $table) {
    // Convertir chaque table
    $query = "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($mysqli->query($query)) {
        $converted++;
        echo "  ✅ $table\n";
    } else {
        echo "  ⚠️ $table - Erreur: " . $mysqli->error . "\n";
    }
}

echo "\n✅ $converted tables converties sur " . count($tables) . "\n\n";

// 4. Vérifier quelques données
echo "4️⃣ Vérification des données...\n";
$result = $mysqli->query("SELECT nom, type FROM clients WHERE nom LIKE '%é%' OR nom LIKE '%è%' LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "  • " . $row['nom'] . " (" . $row['type'] . ")\n";
    }
}

echo "\n✅ Encodage UTF-8 corrigé !\n";
echo "🔄 Veuillez actualiser la page dans votre navigateur.\n";

$mysqli->close();
?>
