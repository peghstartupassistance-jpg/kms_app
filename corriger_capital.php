<?php
require_once __DIR__ . '/db/db.php';

echo "=== CORRECTION DE LA PIÈCE CAPITAL ===\n\n";

try {
    // Supprimer les écritures de la pièce CAP
    $sql = "DELETE FROM compta_ecritures WHERE piece_id IN (SELECT id FROM compta_pieces WHERE numero_piece = 'CAP-2025-00001')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    echo "✓ Écritures supprimées\n";
    
    // Récupérer l'ID de la pièce
    $sql = "SELECT id FROM compta_pieces WHERE numero_piece = 'CAP-2025-00001'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $piece = $stmt->fetch();
    $piece_id = $piece['id'];
    
    // Récupérer les IDs des comptes
    $sql = "SELECT id FROM compta_comptes WHERE numero_compte = '512'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $compte_512 = $stmt->fetch()['id'];
    
    $sql = "SELECT id FROM compta_comptes WHERE numero_compte = '100'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $compte_100 = $stmt->fetch()['id'];
    
    $capital = 10000000;
    
    // Créer les écritures avec les bons débits/crédits
    // Débit : 512 (Banque - Actif) | Crédit : 100 (Capital - Passif/Classe 5)
    $sql = "
        INSERT INTO compta_ecritures 
        (piece_id, compte_id, debit, credit, libelle_ecriture, ordre_ligne)
        VALUES (?, ?, ?, 0, ?, 1), (?, ?, 0, ?, ?, 2)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $piece_id, $compte_512, $capital, 'Capital social apporté',
        $piece_id, $compte_100, $capital, 'Capital social apporté'
    ]);
    
    echo "✓ Écritures recréées avec débits/crédits corrects\n\n";
    
    // Aussi corriger BNQ-2025-00001
    echo "=== CORRECTION DE LA PIÈCE BANQUE ===\n\n";
    
    $sql = "DELETE FROM compta_ecritures WHERE piece_id IN (SELECT id FROM compta_pieces WHERE numero_piece = 'BNQ-2025-00001')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    echo "✓ Écritures supprimées\n";
    
    $sql = "SELECT id FROM compta_pieces WHERE numero_piece = 'BNQ-2025-00001'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $piece = $stmt->fetch();
    $piece_id = $piece['id'];
    
    $sql = "SELECT id FROM compta_comptes WHERE numero_compte = '500'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $compte_500 = $stmt->fetch()['id'];
    
    $solde_banque = 2000000;
    
    // Débit : 512 (Banque) | Crédit : 500 (Capitaux propres)
    $sql = "
        INSERT INTO compta_ecritures 
        (piece_id, compte_id, debit, credit, libelle_ecriture, ordre_ligne)
        VALUES (?, ?, ?, 0, ?, 1), (?, ?, 0, ?, ?, 2)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $piece_id, $compte_512, $solde_banque, 'Solde initial banque',
        $piece_id, $compte_500, $solde_banque, 'Solde initial banque'
    ]);
    
    echo "✓ Écritures recréées\n\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}

?>
