<?php
require_once __DIR__ . '/../security.php';
global $pdo;

echo "=== DIAGNOSTIC JOURNAL_CAISSE ===\n\n";

// 1. Structure de la table
try {
    $stmt = $pdo->query('DESCRIBE journal_caisse');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "COLONNES:\n";
    foreach ($columns as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. Nombre de lignes
try {
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM journal_caisse');
    $count = $stmt->fetch()['total'];
    echo "NOMBRE DE LIGNES: $count\n\n";
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}

// 3. Dernières données
try {
    $stmt = $pdo->query('SELECT id, date_operation, sens, montant, est_annule FROM journal_caisse ORDER BY id DESC LIMIT 5');
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($data)) {
        echo "DERNIÈRES DONNÉES:\n";
        foreach ($data as $row) {
            echo json_encode($row) . "\n";
        }
    } else {
        echo "AUCUNE DONNÉE\n";
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Calcul du CA
try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('
        SELECT 
            SUM(montant) as total,
            COUNT(*) as count
        FROM journal_caisse 
        WHERE DATE(date_operation) = ? 
          AND sens = "RECETTE" 
          AND est_annule = 0
    ');
    $stmt->execute([$today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "CA D'AUJOURD'HUI:\n";
    echo "  Date: $today\n";
    echo "  Nombre d'écritures: " . ($result['count'] ?? 0) . "\n";
    echo "  Total: " . ($result['total'] ?? 0) . " F\n";
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}

echo "\n";

// 5. Vérifier caisse_journal (ancienne table)
try {
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM caisse_journal');
    $count = $stmt->fetch()['total'];
    echo "ANCIENNE TABLE caisse_journal: $count lignes (devrait être vide après migration)\n";
} catch (Exception $e) {
    echo "caisse_journal n'existe pas (OK)\n";
}
?>
