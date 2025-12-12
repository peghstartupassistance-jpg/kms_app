<?php
require_once __DIR__ . '/db/db.php';

echo "=== VALIDATION DE LA PIÈCE DE STOCK ===\n\n";

try {
    $sql = "UPDATE compta_pieces SET est_validee = 1 WHERE numero_piece = 'INV-2025-00001'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    echo "✓ Pièce INV-2025-00001 validée\n\n";
    
    // Vérifier la balance après validation
    echo "=== NOUVEL ÉQUILIBRE ===\n\n";
    
    $sql = "
        SELECT 
            cc.classe,
            SUM(COALESCE(ce.debit, 0)) as total_debit,
            SUM(COALESCE(ce.credit, 0)) as total_credit
        FROM compta_comptes cc
        LEFT JOIN compta_ecritures ce ON ce.compte_id = cc.id
        LEFT JOIN compta_pieces cp ON cp.id = ce.piece_id AND cp.est_validee = 1
        GROUP BY cc.classe
        ORDER BY cc.classe
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $balance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($balance as $ligne) {
        echo "Classe " . $ligne['classe'] . " : Débit " . number_format($ligne['total_debit'], 2) . " | Crédit " . number_format($ligne['total_credit'], 2) . "\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}

?>
