<?php
require __DIR__ . '/db/db.php';
echo "Connexion OK\n";
echo "PDO : " . (isset($pdo) ? 'OK' : 'FAIL') . "\n";
$stmt = $pdo->query("SHOW TABLES LIKE 'compta_%'");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Tables actuelles : " . count($tables) . "\n";
foreach ($tables as $t) {
    echo "  - $t\n";
}
?>
