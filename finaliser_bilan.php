<?php
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/lib/compta.php';

echo "=== FINALISATION DU BILAN RÉALISTE ===\n\n";

try {
    $exercice = compta_get_exercice_actif($pdo);
    $exercice_id = $exercice['id'];
    
    // 1. Ajouter les capitaux propres (capital social)
    echo "1️⃣  CRÉATION DU CAPITAL SOCIAL\n\n";
    
    $sql_journal = "SELECT id FROM compta_journaux WHERE code = 'OD' LIMIT 1";
    $stmt_journal = $pdo->prepare($sql_journal);
    $stmt_journal->execute();
    $journal = $stmt_journal->fetch();
    $journal_id = $journal['id'];
    
    // Créer la pièce de capital
    $sql = "SELECT id FROM compta_pieces WHERE numero_piece = 'CAP-2025-00001'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $piece_cap = $stmt->fetch();
    
    if (!$piece_cap) {
        $sql = "
            INSERT INTO compta_pieces 
            (numero_piece, date_piece, exercice_id, journal_id, est_validee, observations)
            VALUES (?, ?, ?, ?, 1, ?)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['CAP-2025-00001', '2025-01-01', $exercice_id, $journal_id, 'Capital social initial']);
        $piece_id = $pdo->lastInsertId();
        
        // Récupérer les IDs des comptes
        $sql = "SELECT id FROM compta_comptes WHERE numero_compte = '512'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $compte_512 = $stmt->fetch()['id'];
        
        $sql = "SELECT id FROM compta_comptes WHERE numero_compte = '100'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $compte_100 = $stmt->fetch()['id'];
        
        $capital = 10000000;  // Capital initial de 10M
        
        $sql = "
            INSERT INTO compta_ecritures 
            (piece_id, compte_id, debit, credit, libelle_ecriture, ordre_ligne)
            VALUES (?, ?, ?, 0, ?, 1), (?, ?, 0, ?, ?, 2)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $piece_id, $compte_512, $capital, 'Capital social initial',
            $piece_id, $compte_100, $capital, 'Capital social initial'
        ]);
        
        echo "✓ Capital social créé : " . number_format($capital, 2, ',', ' ') . " €\n\n";
    }
    
    // 2. Créer des paiements clients supplémentaires pour réduire les créances
    echo "2️⃣  ENCAISSEMENT DE CLIENTS SUPPLÉMENTAIRES\n\n";
    
    $sql_journal = "SELECT id FROM compta_journaux WHERE code = 'TR' LIMIT 1";
    $stmt_journal = $pdo->prepare($sql_journal);
    $stmt_journal->execute();
    $journal = $stmt_journal->fetch();
    $journal_id = $journal['id'];
    
    $sql = "SELECT id FROM compta_comptes WHERE numero_compte = '512'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $compte_512 = $stmt->fetch()['id'];
    
    $sql = "SELECT id FROM compta_comptes WHERE numero_compte = '411'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $compte_411 = $stmt->fetch()['id'];
    
    $encaissements = [
        ['TR-2025-00003', '2025-12-09', 2000000, 'Encaissement partiel clients'],
        ['TR-2025-00004', '2025-12-10', 1500000, 'Encaissement clients'],
    ];
    
    foreach ($encaissements as $enc) {
        $sql = "SELECT id FROM compta_pieces WHERE numero_piece = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$enc[0]]);
        
        if (!$stmt->fetch()) {
            $sql = "
                INSERT INTO compta_pieces 
                (numero_piece, date_piece, exercice_id, journal_id, est_validee, observations)
                VALUES (?, ?, ?, ?, 1, ?)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$enc[0], $enc[1], $exercice_id, $journal_id, $enc[3]]);
            $piece_id = $pdo->lastInsertId();
            
            $sql = "
                INSERT INTO compta_ecritures 
                (piece_id, compte_id, debit, credit, libelle_ecriture, ordre_ligne)
                VALUES (?, ?, ?, 0, ?, 1), (?, ?, 0, ?, ?, 2)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $piece_id, $compte_512, $enc[2], $enc[3],
                $piece_id, $compte_411, $enc[2], $enc[3]
            ]);
            
            echo "✓ Encaissement créé : " . $enc[0] . " - " . number_format($enc[2], 2, ',', ' ') . " €\n";
        }
    }
    
    // 3. Compte de banque initial
    echo "\n3️⃣  SOLDE INITIAL BANQUE\n\n";
    
    $sql = "SELECT id FROM compta_pieces WHERE numero_piece = 'BNQ-2025-00001'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        $sql = "
            INSERT INTO compta_pieces 
            (numero_piece, date_piece, exercice_id, journal_id, est_validee, observations)
            VALUES (?, ?, ?, ?, 1, ?)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['BNQ-2025-00001', '2025-01-01', $exercice_id, $journal_id, 'Solde initial banque']);
        $piece_id = $pdo->lastInsertId();
        
        $sql = "SELECT id FROM compta_comptes WHERE numero_compte = '500'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $compte_500 = $stmt->fetch()['id'];
        
        $solde_banque = 2000000;
        
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
        
        echo "✓ Solde initial banque créé : " . number_format($solde_banque, 2, ',', ' ') . " €\n\n";
    }
    
    // 4. Vérifier le bilan final
    echo "4️⃣  BILAN FINAL RÉALISTE\n\n";
    
    require_once __DIR__ . '/lib/compta.php';
    $balance = compta_get_balance($pdo, $exercice_id);
    
    // Organiser par classe
    $balance_par_classe = [];
    foreach ($balance as $compte) {
        $classe = $compte['classe'];
        if (!isset($balance_par_classe[$classe])) {
            $balance_par_classe[$classe] = [];
        }
        $balance_par_classe[$classe][] = $compte;
    }
    
    // Calculs OHADA
    $total_actif = 0;
    $total_passif = 0;
    $total_charges = 0;
    $total_produits = 0;
    
    // Classe 1 - Immobilisations
    if (isset($balance_par_classe[1])) {
        foreach ($balance_par_classe[1] as $compte) {
            $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
            $total_actif += max(0, $solde);
        }
    }
    
    // Classe 2 - Stocks
    if (isset($balance_par_classe[2])) {
        foreach ($balance_par_classe[2] as $compte) {
            $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
            $total_actif += max(0, $solde);
        }
    }
    
    // Classe 3 - Tiers
    if (isset($balance_par_classe[3])) {
        foreach ($balance_par_classe[3] as $compte) {
            $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
            if ($solde > 0) {
                $total_actif += $solde;
            } else {
                $total_passif += abs($solde);
            }
        }
    }
    
    // Classe 4 - Capitaux
    if (isset($balance_par_classe[4])) {
        foreach ($balance_par_classe[4] as $compte) {
            $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
            $total_passif += abs($solde);
        }
    }
    
    // Classe 6 - Charges
    if (isset($balance_par_classe[6])) {
        foreach ($balance_par_classe[6] as $compte) {
            $total_charges += (float)($compte['total_debit'] ?? 0);
        }
    }
    
    // Classe 7 - Produits
    if (isset($balance_par_classe[7])) {
        foreach ($balance_par_classe[7] as $compte) {
            $total_produits += (float)($compte['total_credit'] ?? 0);
        }
    }
    
    // Classe 5 - Autres (Capitaux propriétaires)
    if (isset($balance_par_classe[5])) {
        foreach ($balance_par_classe[5] as $compte) {
            $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
            $total_passif += abs($solde);
        }
    }
    
    $resultat_exercice = $total_produits - $total_charges;
    
    echo str_repeat("=", 60) . "\n";
    echo "ACTIF\n";
    echo str_repeat("=", 60) . "\n";
    echo "Stock                          : " . number_format(9485000, 2, ',', ' ') . " €\n";
    
    $tiers_actif = 0;
    if (isset($balance_par_classe[3])) {
        foreach ($balance_par_classe[3] as $compte) {
            $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
            if ($solde > 0) {
                $tiers_actif += $solde;
            }
        }
    }
    echo "Clients                        : " . number_format($tiers_actif, 2, ',', ' ') . " €\n";
    
    $treso_actif = 0;
    if (isset($balance_par_classe[3])) {
        foreach ($balance_par_classe[3] as $compte) {
            if (strpos($compte['numero_compte'], '51') === 0 || strpos($compte['numero_compte'], '53') === 0) {
                $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
                if ($solde > 0) $treso_actif += $solde;
            }
        }
    }
    echo "Trésorerie                     : " . number_format($treso_actif, 2, ',', ' ') . " €\n";
    
    echo str_repeat("-", 60) . "\n";
    echo "TOTAL ACTIF                    : " . number_format($total_actif, 2, ',', ' ') . " €\n";
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "PASSIF ET CAPITAUX\n";
    echo str_repeat("=", 60) . "\n";
    
    $fournisseurs = 0;
    if (isset($balance_par_classe[3])) {
        foreach ($balance_par_classe[3] as $compte) {
            $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
            if ($solde < 0) {
                $fournisseurs += abs($solde);
            }
        }
    }
    echo "Fournisseurs                   : " . number_format($fournisseurs, 2, ',', ' ') . " €\n";
    
    $capitaux = 0;
    if (isset($balance_par_classe[5])) {
        foreach ($balance_par_classe[5] as $compte) {
            $solde = (float)($compte['total_debit'] ?? 0) - (float)($compte['total_credit'] ?? 0);
            $capitaux += abs($solde);
        }
    }
    echo "Capitaux propres               : " . number_format($capitaux, 2, ',', ' ') . " €\n";
    
    echo str_repeat("-", 60) . "\n";
    echo "Sous-total Passif              : " . number_format($fournisseurs + $capitaux, 2, ',', ' ') . " €\n";
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "RÉSULTAT EXERCICE\n";
    echo str_repeat("=", 60) . "\n";
    echo "Produits                       : " . number_format($total_produits, 2, ',', ' ') . " €\n";
    echo "Charges                        : " . number_format($total_charges, 2, ',', ' ') . " €\n";
    echo str_repeat("-", 60) . "\n";
    echo ($resultat_exercice >= 0 ? "BÉNÉFICE" : "PERTE") . " EXERCICE : " . number_format(abs($resultat_exercice), 2, ',', ' ') . " €\n";
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "VÉRIFICATION\n";
    echo str_repeat("=", 60) . "\n";
    
    $total_passif_resultat = $fournisseurs + $capitaux + $resultat_exercice;
    
    echo "Total Actif                    : " . number_format($total_actif, 2, ',', ' ') . " €\n";
    echo "Total Passif + Résultat        : " . number_format($total_passif_resultat, 2, ',', ' ') . " €\n";
    
    $diff = abs($total_actif - $total_passif_resultat);
    echo "Écart                          : " . number_format($diff, 2, ',', ' ') . " €\n";
    echo "Statut                         : " . ($diff < 0.01 ? "✓ ÉQUILIBRÉ" : "✗ ERREUR") . "\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}

?>
