<?php
require_once __DIR__ . '/db/db.php';

echo "=== ACTIVATION DE L'EXERCICE 2025 ===\n\n";

try {
    // D'abord, désactiver tous les exercices
    $sql = "UPDATE compta_exercices SET est_actif = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    echo "✓ Tous les exercices désactivés\n";
    
    // Activer l'exercice 2025
    $sql = "UPDATE compta_exercices SET est_actif = 1 WHERE annee = 2025";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✓ Exercice 2025 activé\n\n";
        
        // Vérifier l'activation
        $sql = "SELECT * FROM compta_exercices WHERE annee = 2025";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Exercice 2025 :\n";
        echo "  ID: " . $exercice['id'] . "\n";
        echo "  Actif: " . ($exercice['est_actif'] ? "OUI" : "NON") . "\n";
    } else {
        echo "❌ Exercice 2025 non trouvé\n";
        
        // Lister les exercices disponibles
        echo "\nExercices disponibles :\n";
        $sql = "SELECT * FROM compta_exercices";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($exercices as $ex) {
            echo "  - " . $ex['annee'] . " (ID: " . $ex['id'] . ")\n";
        }
    }
    
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}

?>
