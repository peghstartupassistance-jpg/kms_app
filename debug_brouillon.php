<?php
// Script pour analyser les pièces brouillon

require_once __DIR__ . '/db/db.php';

echo "=== ANALYSE DES PIÈCES BROUILLON ===\n\n";

$sql = "
    SELECT 
        p.id,
        p.numero_piece,
        p.date_piece,
        p.est_validee,
        COUNT(ce.id) as nb_ecritures,
        SUM(CASE WHEN ce.debit > 0 THEN ce.debit ELSE 0 END) as total_debit,
        SUM(CASE WHEN ce.credit > 0 THEN ce.credit ELSE 0 END) as total_credit
    FROM compta_pieces p
    LEFT JOIN compta_ecritures ce ON ce.piece_id = p.id
    WHERE p.est_validee = 0
    GROUP BY p.id, p.numero_piece, p.date_piece, p.est_validee
    ORDER BY p.date_piece DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $pieces_brouillon = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pieces_brouillon)) {
        echo "✓ Aucune pièce en brouillon\n\n";
    } else {
        echo "Found " . count($pieces_brouillon) . " pièce(s) en brouillon :\n\n";
        
        $total_debit_brouillon = 0;
        $total_credit_brouillon = 0;
        
        foreach ($pieces_brouillon as $piece) {
            echo "Pièce : " . htmlspecialchars($piece['numero_piece']) . "\n";
            echo "  Date : " . htmlspecialchars($piece['date_piece']) . "\n";
            echo "  Écritures : " . $piece['nb_ecritures'] . "\n";
            echo "  Débit : " . number_format($piece['total_debit'], 2, ',', ' ') . " €\n";
            echo "  Crédit : " . number_format($piece['total_credit'], 2, ',', ' ') . " €\n";
            echo "  ----\n\n";
            
            $total_debit_brouillon += $piece['total_debit'];
            $total_credit_brouillon += $piece['total_credit'];
        }
        
        echo "\n=== TOTAUX BROUILLON ===\n";
        echo "Total Débits (brouillon) : " . number_format($total_debit_brouillon, 2, ',', ' ') . " €\n";
        echo "Total Crédits (brouillon) : " . number_format($total_credit_brouillon, 2, ',', ' ') . " €\n";
    }
    
} catch (PDOException $e) {
    echo "Erreur SQL : " . $e->getMessage() . "\n";
}

?>
