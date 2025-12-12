<?php
session_start();
$_SESSION['user_id'] = 1;

require_once __DIR__ . '/db/db.php';

// Test: vérifier que la table journal_caisse existe
try {
    $stmt = $pdo->query('SELECT COUNT(*) FROM journal_caisse LIMIT 1');
    $count = $stmt->fetchColumn();
    echo "✓ Journal caisse table found with: $count rows\n";
    
    // Afficher les colonnes de la table
    $stmt = $pdo->query('DESCRIBE journal_caisse');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nTable columns:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
