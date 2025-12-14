<?php
// reset_db.php - Réinitialiser la base de données avec import SQL correct

$DB_HOST = 'localhost';
$DB_NAME = 'kms_gestion';
$DB_USER = 'root';
$DB_PASS = '';

try {
    // Connexion mysqli
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS);
    if ($mysqli->connect_error) {
        die("❌ Erreur de connexion: " . $mysqli->connect_error);
    }
    
    // 1. Supprimer la BD existante
    echo "❌ Suppression de la base de données kms_gestion...\n";
    $mysqli->query("DROP DATABASE IF EXISTS kms_gestion");
    echo "✅ Supprimée\n\n";
    
    // 2. Créer la BD
    echo "📦 Création de la nouvelle base de données...\n";
    $mysqli->query("CREATE DATABASE kms_gestion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $mysqli->select_db('kms_gestion');
    $mysqli->set_charset('utf8mb4');
    echo "✅ Créée\n\n";
    
    // 3. Charger le schéma SQL
    echo "📥 Importation du schéma SQL...\n";
    
    $sql_file = __DIR__ . '/kms_gestion (5).sql';
    if (!file_exists($sql_file)) {
        die("❌ Fichier SQL non trouvé: $sql_file\n");
    }
    
    $sql = file_get_contents($sql_file);
    
    // Nettoyer les commentaires et les espacements excessifs
    $lines = explode("\n", $sql);
    $cleaned = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        // Ignorer les commentaires et les lignes vides
        if (empty($line) || substr($line, 0, 2) === '--' || substr($line, 0, 3) === '/*!') {
            continue;
        }
        $cleaned[] = $line;
    }
    
    $sql = implode(" ", $cleaned);
    
    // Remplacer DELIMITER par un marqueur temporaire
    $sql = str_replace('DELIMITER $$', '___DELIMITER___', $sql);
    $sql = str_replace('DELIMITER ;', '___DELIMITER_SEMICOLON___', $sql);
    
    // Splitter par le marqueur
    $parts = explode('___DELIMITER___', $sql);
    
    $count = 0;
    foreach ($parts as $idx => $part) {
        // Remettre le délimiteur si c'est une procédure/fonction
        if ($idx > 0) {
            $part = 'DELIMITER $$' . $part;
            // Trouver la fin de la procédure/fonction
            $subparts = explode('END$$', $part, 2);
            if (count($subparts) === 2) {
                $procedure = $subparts[0] . 'END$$DELIMITER ;';
                $rest = $subparts[1];
                
                // Exécuter la procédure/fonction
                $mysqli->multi_query($procedure);
                while ($mysqli->next_result()) {
                    if ($rs = $mysqli->use_result()) {
                        $rs->free();
                    }
                }
                $count++;
                
                // Continuer avec le reste
                $part = $rest;
            }
        }
        
        // Exécuter les requêtes standard (avec ;)
        $queries = explode(';', $part);
        foreach ($queries as $q) {
            $q = trim($q);
            if (!empty($q)) {
                if (!$mysqli->query($q)) {
                    echo "⚠️ Erreur: " . $mysqli->error . "\n";
                    echo "Query: " . substr($q, 0, 100) . "...\n";
                } else {
                    $count++;
                }
            }
        }
    }
    
    echo "✅ Schéma importé ($count requêtes exécutées)\n\n";
    
    // 4. Vérification des tables
    $result = $mysqli->query("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = 'kms_gestion'");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "📊 Nombre de tables créées: " . $row['cnt'] . "\n";
    }
    
    echo "\n✅ Réinitialisation complète terminée !\n";
    echo "🚀 L'application est prête à l'emploi.\n";
    
    $mysqli->close();
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>
