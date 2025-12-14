<?php
require_once __DIR__ . '/security.php';
global $pdo;

header('Content-Type: text/plain; charset=utf-8');

echo "=== VÉRIFICATION DATES ===\n\n";

// Récupérer toutes les dates dans journal_caisse
echo "Dates dans journal_caisse:\n";

$stmt = $pdo->query("
    SELECT 
        DATE(date_operation) as date,
        COUNT(*) as cnt,
        SUM(montant) as total,
        GROUP_CONCAT(DISTINCT sens) as sens
    FROM journal_caisse
    GROUP BY DATE(date_operation)
    ORDER BY date DESC
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  " . $row['date'] . ": " . $row['cnt'] . " lignes, " . $row['total'] . " F, sens=" . $row['sens'] . "\n";
}

echo "\nDate système:\n";
echo "  date('Y-m-d') = " . date('Y-m-d') . "\n";
echo "  date('Y-m-d H:i:s') = " . date('Y-m-d H:i:s') . "\n";

// Vérifier CURDATE() en base
$stmt = $pdo->query("SELECT CURDATE() as date, DATE(NOW()) as now, CURRENT_TIMESTAMP as ts");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nDate base de données:\n";
echo "  CURDATE() = " . $row['date'] . "\n";
echo "  DATE(NOW()) = " . $row['now'] . "\n";
echo "  CURRENT_TIMESTAMP = " . $row['ts'] . "\n";

echo "\n=== INSERTION DE TEST ===\n";
try {
    $stmt = $pdo->prepare("
        INSERT INTO journal_caisse 
        (date_operation, sens, montant, type_operation, commentaire)
        VALUES (NOW(), 'RECETTE', 25000, 'TEST', 'Test de date')
    ");
    $stmt->execute();
    echo "✓ Ligne test créée avec date_operation = NOW()\n";
    
    // Vérifier qu'elle est trouvée
    $stmt = $pdo->query("SELECT DATE(date_operation) as d FROM journal_caisse WHERE commentaire = 'Test de date'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  Trouvée avec DATE = " . $row['d'] . "\n";
    
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}

?>
