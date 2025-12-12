<?php
require_once __DIR__ . '/db/db.php';

echo "=== STRUCTURE ordres_preparation ===\n\n";
$stmt = $pdo->query("DESCRIBE ordres_preparation");
while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}
