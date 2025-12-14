<?php
require 'db/db.php';
global $pdo;
$stmt = $pdo->query('DESCRIBE bons_livraison');
while($row = $stmt->fetch()) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Null'] . "\n";
}
