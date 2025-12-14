<?php
require 'db/db.php';
$result = $pdo->query('SHOW COLUMNS FROM compta_comptes');
echo "Colonnes de compta_comptes:\n";
while ($row = $result->fetch()) {
    echo "  • " . $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
