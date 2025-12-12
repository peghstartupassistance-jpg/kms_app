<?php
require_once __DIR__ . '/db/db.php';

echo "=== CORRECTION DU PLAN COMPTABLE ===\n\n";

try {
    // Corriger 401 - Fournisseurs (classe 4 → 3)
    $sql = "UPDATE compta_comptes SET classe = 3 WHERE numero_compte = '401'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    echo "✓ 401 (Fournisseurs) → Classe 3\n";
    
    // Corriger 411 - Clients (classe 4 → 3)
    $sql = "UPDATE compta_comptes SET classe = 3 WHERE numero_compte = '411'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    echo "✓ 411 (Clients) → Classe 3\n";
    
    echo "\n✓ Plan comptable corrigé !\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}

?>
