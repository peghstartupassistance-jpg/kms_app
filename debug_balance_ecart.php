<?php
// Script de diagnostic pour trouver les pièces non équilibrées

require_once __DIR__ . '/db/db.php';

echo "=== VÉRIFICATION DES PIÈCES NON ÉQUILIBRÉES ===\n\n";

// Requête pour trouver les pièces avec débits != crédits
$sql = "
    SELECT 
        p.id, 
        p.numero_piece,
        p.date_piece,
        p.est_validee,
        SUM(CASE WHEN ce.debit > 0 THEN ce.debit ELSE 0 END) as total_debit,
        SUM(CASE WHEN ce.credit > 0 THEN ce.credit ELSE 0 END) as total_credit,
        ABS(SUM(CASE WHEN ce.debit > 0 THEN ce.debit ELSE 0 END) - SUM(CASE WHEN ce.credit > 0 THEN ce.credit ELSE 0 END)) as ecart
    FROM compta_pieces p
    LEFT JOIN compta_ecritures ce ON ce.piece_id = p.id
    GROUP BY p.id, p.numero_piece, p.date_piece, p.est_validee
    HAVING total_debit != total_credit
    ORDER BY ecart DESC, p.numero_piece
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $pieces_non_equilibrees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pieces_non_equilibrees)) {
        echo "✓ BONNE NOUVELLE : Toutes les pièces sont équilibrées !\n\n";
    } else {
        echo "✗ PROBLÈME TROUVÉ : " . count($pieces_non_equilibrees) . " pièce(s) non équilibrée(s)\n\n";
        
        foreach ($pieces_non_equilibrees as $piece) {
            echo "Pièce : " . htmlspecialchars($piece['numero_piece']) . "\n";
            echo "  Date : " . htmlspecialchars($piece['date_piece']) . "\n";
            echo "  Statut : " . ($piece['est_validee'] ? "VALIDÉE" : "BROUILLON") . "\n";
            echo "  Débit : " . number_format($piece['total_debit'], 2, ',', ' ') . " €\n";
            echo "  Crédit : " . number_format($piece['total_credit'], 2, ',', ' ') . " €\n";
            echo "  Écart : " . number_format($piece['ecart'], 2, ',', ' ') . " €\n";
            echo "  ----\n\n";
        }
    }
    
    // Vérifier l'équilibre global
    echo "\n=== VÉRIFICATION DE L'ÉQUILIBRE GLOBAL ===\n\n";
    
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
    $ecart_global = abs($debit_global - $credit_global);
    
    echo "Total Débits (validées) : " . number_format($debit_global, 2, ',', ' ') . " €\n";
    echo "Total Crédits (validées) : " . number_format($credit_global, 2, ',', ' ') . " €\n";
    echo "Écart global : " . number_format($ecart_global, 2, ',', ' ') . " €\n";
    
    if ($ecart_global < 0.01) {
        echo "\n✓ Les pièces validées sont équilibrées\n";
    } else {
        echo "\n✗ ÉCART DÉTECTÉ : " . number_format($ecart_global, 2, ',', ' ') . " €\n";
    }
    
} catch (PDOException $e) {
    echo "Erreur SQL : " . $e->getMessage() . "\n";
}

?>
