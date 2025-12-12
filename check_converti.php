<?php
require 'db/db.php';
echo "Colonnes 'converti' dans visiteurs_showroom:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM visiteurs_showroom LIKE 'converti%'");
while($r = $stmt->fetch()) {
    echo "  - {$r['Field']}\n";
}

echo "\nColonnes 'converti' dans prospections_terrain:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM prospections_terrain LIKE 'converti%'");
while($r = $stmt->fetch()) {
    echo "  - {$r['Field']}\n";
}
