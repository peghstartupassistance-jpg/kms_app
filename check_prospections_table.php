<?php
require_once 'db/db.php';
$stmt = $pdo->query('DESCRIBE prospections_terrain');
while($row = $stmt->fetch()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
