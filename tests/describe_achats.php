<?php
require_once __DIR__ . '/../db/db.php';

$desc = $pdo->query('DESCRIBE achats')->fetchAll(PDO::FETCH_ASSOC);
foreach ($desc as $col) {
    echo $col['Field'] . "\n";
}
