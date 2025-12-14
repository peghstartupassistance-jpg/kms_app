<?php
require __DIR__ . '/db/db.php';
global $pdo;
$row = $pdo->query("SELECT id, numero FROM bons_livraison ORDER BY id DESC LIMIT 1")->fetch();
if ($row) {
    echo $row['id'] . "|" . $row['numero'];
} else {
    echo "NONE";
}
