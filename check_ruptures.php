<?php
require_once __DIR__ . '/db/db.php';

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'ruptures_signalees'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "✓ Table ruptures_signalees existe\n\n";
        
        $stmt = $pdo->query("DESCRIBE ruptures_signalees");
        echo "Structure:\n";
        while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - {$col['Field']}\n";
        }
    } else {
        echo "✗ Table ruptures_signalees n'existe PAS\n";
        echo "\n>>> Il faut créer cette table manuellement:\n\n";
        
        $sql = file_get_contents(__DIR__ . '/db/extensions_marketing.sql');
        
        // Extraire juste la création de ruptures_signalees
        if (preg_match('/CREATE TABLE IF NOT EXISTS `ruptures_signalees`[^;]+;/s', $sql, $matches)) {
            echo $matches[0] . "\n\n";
            
            echo "Voulez-vous l'exécuter maintenant? (y/n): ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            
            if (trim($line) == 'y') {
                try {
                    $pdo->exec($matches[0]);
                    echo "\n✅ Table créée avec succès!\n";
                } catch (PDOException $e) {
                    echo "\n❌ Erreur: " . $e->getMessage() . "\n";
                }
            }
        }
    }
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
