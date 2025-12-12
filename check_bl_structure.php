<?php
require 'db/db.php';

echo "Structure table bons_livraison:\n";
$stmt = $pdo->query('DESCRIBE bons_livraison');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  - {$row['Field']} ({$row['Type']})\n";
}
