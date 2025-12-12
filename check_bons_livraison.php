<?php
require_once 'db/db.php';
$stmt = $pdo->query('DESCRIBE bons_livraison');
while($row = $stmt->fetch()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
