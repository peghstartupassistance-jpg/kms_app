<?php
require_once __DIR__ . '/db/db.php';

echo "=== CORRECTION DU PLAN COMPTABLE ===\n\n";

try {
    // 1. Corriger le Compte 100 - Classe 1 → Classe 5
    $sql = "UPDATE compta_comptes SET classe = 5 WHERE numero_compte = '100'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    echo "✓ Compte 100 (Capital) : Classe 1 → Classe 5\n";
    
    // 2. Vérifier que 512 et 530 soient en Classe 3 (Tiers - Actif courant)
    // C'est correct car ce sont des comptes de trésorerie
    
    // 3. Mais attendez - en OHADA, les comptes de banque/caisse sont en Classe 5
    // Regardons la classification OHADA correcte :
    // Classe 1 : Immobilisations
    // Classe 2 : Stocks
    // Classe 3 : Tiers
    // Classe 4 : Crédits (dettes financières)
    // Classe 5 : Financements (Capitaux, Banque, Caisse)
    
    // Donc 512 et 530 devraient être en Classe 5
    $sql = "UPDATE compta_comptes SET classe = 5 WHERE numero_compte IN ('512', '530')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    echo "✓ Compte 512 (Banque) : Classe 3 → Classe 5\n";
    echo "✓ Compte 530 (Caisse) : Classe 3 → Classe 5\n";
    
    echo "\n✓ Plan comptable corrigé !\n\n";
    
    // Vérifier la nouvelle structure
    echo "=== NOUVELLE STRUCTURE ===\n\n";
    
    $sql = "
        SELECT 
            classe,
            GROUP_CONCAT(CONCAT(numero_compte, ' - ', libelle) SEPARATOR ', ') as comptes
        FROM compta_comptes
        WHERE numero_compte IN ('100', '500', '512', '530', '411', '401', '2')
        GROUP BY classe
        ORDER BY classe
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($resultats as $r) {
        echo "Classe " . $r['classe'] . " : " . $r['comptes'] . "\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}

?>
