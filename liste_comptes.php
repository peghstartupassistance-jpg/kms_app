<?php
require_once __DIR__ . '/db/db.php';

$sql = "SELECT * FROM compta_comptes ORDER BY numero_compte";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== PLAN COMPTABLE COMPLET ===\n\n";

foreach ($comptes as $compte) {
    echo $compte['numero_compte'] . " | " . $compte['libelle'] . " | Classe: " . $compte['classe'] . "\n";
}
?>
