<?php
require_once __DIR__ . '/db/db.php';

echo "=== CRÉATION DU COMPTE STOCK ET VALORISATION ===\n\n";

try {
    // 1. Vérifier si le compte 2 (Stock) existe
    $sql = "SELECT * FROM compta_comptes WHERE numero_compte = '2'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $compte = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$compte) {
        echo "❌ Compte 2 (Stocks) n'existe pas\n";
        echo "✓ Création du compte...\n\n";
        
        $sql = "INSERT INTO compta_comptes (numero_compte, libelle, classe) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['2', 'Stocks de marchandises', 2]);
        
        $compte_id = $pdo->lastInsertId();
        echo "✓ Compte créé avec ID : " . $compte_id . "\n\n";
    } else {
        $compte_id = $compte['id'];
        echo "✓ Compte 2 (Stocks) existe déjà (ID: " . $compte_id . ")\n\n";
    }
    
    // 2. Calculer la valeur du stock
    echo "2️⃣  VALORISATION DU STOCK\n\n";
    
    $sql = "
        SELECT 
            id,
            code_produit,
            designation,
            stock_actuel,
            prix_achat,
            (stock_actuel * prix_achat) as valeur
        FROM produits
        WHERE stock_actuel > 0
        ORDER BY code_produit
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $valeur_totale = 0;
    foreach ($produits as $p) {
        $valeur = floatval($p['stock_actuel']) * floatval($p['prix_achat']);
        $valeur_totale += $valeur;
        echo $p['code_produit'] . " | " . $p['stock_actuel'] . " x " . $p['prix_achat'] . " = " . number_format($valeur, 2, ',', ' ') . " €\n";
    }
    
    echo "\nValeur totale du stock : " . number_format($valeur_totale, 2, ',', ' ') . " €\n";
    
    // 3. Créer une pièce de stock initial
    echo "\n3️⃣  CRÉATION DE LA PIÈCE COMPTABLE\n\n";
    
    $exercice_sql = "SELECT id FROM compta_exercices WHERE est_clos = 0 ORDER BY annee DESC LIMIT 1";
    $stmt_ex = $pdo->prepare($exercice_sql);
    $stmt_ex->execute();
    $exercice = $stmt_ex->fetch(PDO::FETCH_ASSOC);
    
    if (!$exercice) {
        echo "❌ Aucun exercice actif\n";
        exit;
    }
    
    $exercice_id = $exercice['id'];
    
    // Vérifier si une pièce de stock initial existe déjà
    $sql = "SELECT * FROM compta_pieces WHERE numero_piece = 'INV-2025-00001'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $piece_existe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($piece_existe) {
        echo "⚠️  Pièce INV-2025-00001 existe déjà\n";
        $piece_id = $piece_existe['id'];
    } else {
        // Créer la pièce
        $sql = "
            INSERT INTO compta_pieces 
            (numero_piece, date_piece, exercice_id, journal_id, est_validee, observations)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        $stmt = $pdo->prepare($sql);
        
        // Récupérer l'ID du journal (Opérations Diverses = OD)
        $sql_journal = "SELECT id FROM compta_journaux WHERE code = 'OD' LIMIT 1";
        $stmt_journal = $pdo->prepare($sql_journal);
        $stmt_journal->execute();
        $journal = $stmt_journal->fetch(PDO::FETCH_ASSOC);
        
        $journal_id = $journal['id'] ?? 1;  // Par défaut OD
        
        $stmt->execute([
            'INV-2025-00001',
            date('Y-m-d'),
            $exercice_id,
            $journal_id,
            0,  // Brouillon
            'Stock initial valorisé'
        ]);
        
        $piece_id = $pdo->lastInsertId();
        echo "✓ Pièce créée : INV-2025-00001 (ID: " . $piece_id . ")\n";
    }
    
    // 4. Créer les écritures
    echo "\n4️⃣  CRÉATION DES ÉCRITURES\n\n";
    
    // Supprimer les écritures existantes pour cette pièce (si brouillon)
    $sql = "DELETE FROM compta_ecritures WHERE piece_id = ? AND (SELECT est_validee FROM compta_pieces WHERE id = ?) = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$piece_id, $piece_id]);
    
    // Écriture débit : Compte 2 (Stock) = valeur du stock
    $sql = "
        INSERT INTO compta_ecritures 
        (piece_id, compte_id, debit, credit, libelle_ecriture, ordre_ligne)
        VALUES (?, ?, ?, 0, ?, 1)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $piece_id,
        $compte_id,
        $valeur_totale,
        'Stock initial valorisé'
    ]);
    echo "✓ Débit Compte 2 (Stock) : " . number_format($valeur_totale, 2, ',', ' ') . " €\n";
    
    // Écriture crédit : Compte 5 (Capitaux propres ou ajustement)
    // On va utiliser 500 (Capitaux propres) ou créer un compte d'ajustement
    $sql_compte_5 = "SELECT id FROM compta_comptes WHERE numero_compte = '500' LIMIT 1";
    $stmt_compte_5 = $pdo->prepare($sql_compte_5);
    $stmt_compte_5->execute();
    $compte_5 = $stmt_compte_5->fetch(PDO::FETCH_ASSOC);
    
    if (!$compte_5) {
        // Créer le compte 500 s'il n'existe pas
        $sql = "INSERT INTO compta_comptes (numero_compte, libelle, classe) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['500', 'Capitaux propres', 5]);
        $compte_5_id = $pdo->lastInsertId();
    } else {
        $compte_5_id = $compte_5['id'];
    }
    
    // Crédit Compte 5
    $sql = "
        INSERT INTO compta_ecritures 
        (piece_id, compte_id, debit, credit, libelle_ecriture, ordre_ligne)
        VALUES (?, ?, 0, ?, ?, 2)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $piece_id,
        $compte_5_id,
        $valeur_totale,
        'Stock initial valorisé'
    ]);
    echo "✓ Crédit Compte 5 (Capitaux) : " . number_format($valeur_totale, 2, ',', ' ') . " €\n";
    
    echo "\n✓ Pièce équilibrée et prête à valider !\n";
    echo "   Débit = Crédit = " . number_format($valeur_totale, 2, ',', ' ') . " €\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}

?>
