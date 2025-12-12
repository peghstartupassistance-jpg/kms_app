<?php
require 'db/db.php';
echo "Structure prospections_terrain:\n";
$stmt = $pdo->query("DESCRIBE prospections_terrain");
while($r = $stmt->fetch()) {
    echo "  - {$r['Field']}\n";
}
