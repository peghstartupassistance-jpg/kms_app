<?php
// Test d'affichage simple pour debug
require_once 'security.php';
global $pdo;

$date = '2025-12-14';

// Test rapide
$stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM journal_caisse WHERE date_operation = ?");
$stmt->execute([$date]);
$result = $stmt->fetch();

echo "<!DOCTYPE html><html><head><title>Test Debug</title></head><body>";
echo "<h1>Test Debug Reconciliation</h1>";
echo "<p>Opérations trouvées: " . $result['nb'] . "</p>";

// Test inclusion header
echo "<h2>Test inclusion partials</h2>";
try {
    include __DIR__ . '/partials/header.php';
    echo "<p>✅ Header chargé</p>";
    
    include __DIR__ . '/partials/sidebar.php';
    echo "<p>✅ Sidebar chargée</p>";
    
    echo '<div class="container-fluid"><h1>Test contenu</h1></div>';
    
    include __DIR__ . '/partials/footer.php';
    echo "<p>✅ Footer chargé</p>";
} catch (Exception $e) {
    echo "<p>❌ Erreur: " . $e->getMessage() . "</p>";
}

echo "</body></html>";
