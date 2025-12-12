<?php
require_once __DIR__ . '/db/db.php';

echo "=== STRUCTURE DE LA TABLE compta_exercices ===\n\n";

$sql = "DESCRIBE compta_exercices";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n\n=== CONTENU ===\n\n";

$sql = "SELECT * FROM compta_exercices";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);

var_dump($exercices);
?>
