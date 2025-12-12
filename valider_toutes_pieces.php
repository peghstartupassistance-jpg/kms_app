<?php
// Script pour valider toutes les pièces brouillon

require_once __DIR__ . '/db/db.php';

echo "=== VALIDATION AUTOMATIQUE DES PIÈCES BROUILLON ===\n\n";

try {
    // Récupérer toutes les pièces brouillon équilibrées
    $sql_pieces = "
        SELECT 
            p.id,
            p.numero_piece,
            SUM(CASE WHEN ce.debit > 0 THEN ce.debit ELSE 0 END) as total_debit,
            SUM(CASE WHEN ce.credit > 0 THEN ce.credit ELSE 0 END) as total_credit
        FROM compta_pieces p
        LEFT JOIN compta_ecritures ce ON ce.piece_id = p.id
        WHERE p.est_validee = 0
        GROUP BY p.id, p.numero_piece
        HAVING total_debit = total_credit
    ";
    
    $stmt = $pdo->prepare($sql_pieces);
    $stmt->execute();
    $pieces = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pieces)) {
        echo "❌ Aucune pièce en brouillon à valider\n";
        exit;
    }
    
    echo "✓ Pièces à valider : " . count($pieces) . "\n\n";
    
    // Valider chaque pièce
    $sql_update = "UPDATE compta_pieces SET est_validee = 1 WHERE id = ?";
    $stmt_update = $pdo->prepare($sql_update);
    
    $count = 0;
    foreach ($pieces as $piece) {
        $stmt_update->execute([$piece['id']]);
        echo "✓ " . htmlspecialchars($piece['numero_piece']) . " validée\n";
        $count++;
    }
    
    echo "\n✓ " . $count . " pièce(s) validée(s) avec succès !\n";
    
    // Vérifier le nouvel équilibre
    echo "\n=== VÉRIFICATION DE L'ÉQUILIBRE ===\n\n";
    
    $sql_global = "
        SELECT 
            SUM(CASE WHEN ce.debit > 0 THEN ce.debit ELSE 0 END) as total_global_debit,
            SUM(CASE WHEN ce.credit > 0 THEN ce.credit ELSE 0 END) as total_global_credit
        FROM compta_ecritures ce
        LEFT JOIN compta_pieces p ON p.id = ce.piece_id
        WHERE p.est_validee = 1
    ";
    
    $stmt_global = $pdo->prepare($sql_global);
    $stmt_global->execute();
    $balance_global = $stmt_global->fetch(PDO::FETCH_ASSOC);
    
    $debit_global = (float)$balance_global['total_global_debit'];
    $credit_global = (float)$balance_global['total_global_credit'];
    
    echo "Total Débits validés : " . number_format($debit_global, 2, ',', ' ') . " €\n";
    echo "Total Crédits validés : " . number_format($credit_global, 2, ',', ' ') . " €\n";
    
    if (abs($debit_global - $credit_global) < 0.01) {
        echo "\n✓ La balance est équilibrée !\n";
    } else {
        echo "\n❌ Écart détecté : " . number_format(abs($debit_global - $credit_global), 2, ',', ' ') . " €\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erreur SQL : " . $e->getMessage() . "\n";
}

?>
