<?php
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/lib/compta.php';

echo "=== CRÉATION DE DONNÉES RÉALISTES POUR LE BILAN ===\n\n";

try {
    $exercice = compta_get_exercice_actif($pdo);
    $exercice_id = $exercice['id'];
    
    // 1. Créer les comptes manquants
    echo "1️⃣  CRÉATION DES COMPTES MANQUANTS\n\n";
    
    $comptes_a_creer = [
        ['100', 'Capital social', 1],
        ['110', 'Réserves', 1],
        ['150', 'Provisions', 1],
        ['200', 'Amortissements', 1],
        ['301', 'Matières premières', 2],
        ['512', 'Banque', 3],
        ['530', 'Caisse', 3],
        ['601', 'Achats de matières premières', 6],
        ['608', 'Frais de transport', 6],
        ['622', 'Rémunérations du personnel', 6],
        ['631', 'Impôts et taxes', 6],
        ['701', 'Ventes de produits finis', 7],
        ['708', 'Revenus annexes', 7],
    ];
    
    foreach ($comptes_a_creer as $compte) {
        $sql = "SELECT id FROM compta_comptes WHERE numero_compte = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$compte[0]]);
        
        if (!$stmt->fetch()) {
            $sql = "INSERT INTO compta_comptes (numero_compte, libelle, classe) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($compte);
            echo "✓ Compte créé : " . $compte[0] . " - " . $compte[1] . "\n";
        }
    }
    
    // 2. Créer des pièces de ventes supplémentaires
    echo "\n2️⃣  CRÉATION DE VENTES SUPPLÉMENTAIRES\n\n";
    
    $ventes_supplementaires = [
        ['VE-2025-00009', '2025-12-05', 3500000, 'Vente mobilier décoration'],
        ['VE-2025-00010', '2025-12-06', 2100000, 'Vente accessoires'],
        ['VE-2025-00011', '2025-12-07', 1850000, 'Vente panneaux'],
    ];
    
    $sql_journal = "SELECT id FROM compta_journaux WHERE code = 'VE' LIMIT 1";
    $stmt_journal = $pdo->prepare($sql_journal);
    $stmt_journal->execute();
    $journal = $stmt_journal->fetch();
    $journal_id = $journal['id'];
    
    // Récupérer les IDs des comptes
    $sql = "SELECT id FROM compta_comptes WHERE numero_compte = '411'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $compte_411 = $stmt->fetch()['id'];
    
    $sql = "SELECT id FROM compta_comptes WHERE numero_compte = '701'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $compte_701 = $stmt->fetch()['id'];
    
    foreach ($ventes_supplementaires as $vente) {
        $sql = "SELECT id FROM compta_pieces WHERE numero_piece = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$vente[0]]);
        
        if (!$stmt->fetch()) {
            // Créer la pièce
            $sql = "
                INSERT INTO compta_pieces 
                (numero_piece, date_piece, exercice_id, journal_id, est_validee, observations)
                VALUES (?, ?, ?, ?, 1, ?)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$vente[0], $vente[1], $exercice_id, $journal_id, $vente[3]]);
            $piece_id = $pdo->lastInsertId();
            
            // Créer les écritures
            $sql = "
                INSERT INTO compta_ecritures 
                (piece_id, compte_id, debit, credit, libelle_ecriture, ordre_ligne)
                VALUES (?, ?, ?, 0, ?, 1), (?, ?, 0, ?, ?, 2)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $piece_id, $compte_411, $vente[2], $vente[3],
                $piece_id, $compte_701, $vente[2], $vente[3]
            ]);
            
            echo "✓ Vente créée : " . $vente[0] . " - " . number_format($vente[2], 2, ',', ' ') . " €\n";
        }
    }
    
    // 3. Créer des achats supplémentaires
    echo "\n3️⃣  CRÉATION D'ACHATS SUPPLÉMENTAIRES\n\n";
    
    $achats_supplementaires = [
        ['AC-2025-00004', '2025-12-03', 1500000, 'Achat matières premières'],
        ['AC-2025-00005', '2025-12-04', 900000, 'Achat accessoires'],
    ];
    
    $sql_journal = "SELECT id FROM compta_journaux WHERE code = 'AC' LIMIT 1";
    $stmt_journal = $pdo->prepare($sql_journal);
    $stmt_journal->execute();
    $journal = $stmt_journal->fetch();
    $journal_id = $journal['id'];
    
    $sql = "SELECT id FROM compta_comptes WHERE numero_compte = '401'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $compte_401 = $stmt->fetch()['id'];
    
    $sql = "SELECT id FROM compta_comptes WHERE numero_compte = '607'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $compte_607 = $stmt->fetch()['id'];
    
    foreach ($achats_supplementaires as $achat) {
        $sql = "SELECT id FROM compta_pieces WHERE numero_piece = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$achat[0]]);
        
        if (!$stmt->fetch()) {
            $sql = "
                INSERT INTO compta_pieces 
                (numero_piece, date_piece, exercice_id, journal_id, est_validee, observations)
                VALUES (?, ?, ?, ?, 1, ?)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$achat[0], $achat[1], $exercice_id, $journal_id, $achat[3]]);
            $piece_id = $pdo->lastInsertId();
            
            $sql = "
                INSERT INTO compta_ecritures 
                (piece_id, compte_id, debit, credit, libelle_ecriture, ordre_ligne)
                VALUES (?, ?, ?, 0, ?, 1), (?, ?, 0, ?, ?, 2)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $piece_id, $compte_607, $achat[2], $achat[3],
                $piece_id, $compte_401, $achat[2], $achat[3]
            ]);
            
            echo "✓ Achat créé : " . $achat[0] . " - " . number_format($achat[2], 2, ',', ' ') . " €\n";
        }
    }
    
    // 4. Créer des pièces de trésorerie (paiements)
    echo "\n4️⃣  CRÉATION DE PAIEMENTS (TRÉSORERIE)\n\n";
    
    // Paiement fournisseur
    $sql_journal = "SELECT id FROM compta_journaux WHERE code = 'TR' LIMIT 1";
    $stmt_journal = $pdo->prepare($sql_journal);
    $stmt_journal->execute();
    $journal = $stmt_journal->fetch();
    $journal_id = $journal['id'];
    
    $sql = "SELECT id FROM compta_comptes WHERE numero_compte = '512'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $compte_512 = $stmt->fetch()['id'];
    
    $paiements = [
        ['TR-2025-00001', '2025-12-05', 2509000, 'Paiement fournisseurs', 'AC', 401],
        ['TR-2025-00002', '2025-12-08', 3000000, 'Encaissement clients', 'VE', 411],
    ];
    
    foreach ($paiements as $paiement) {
        $sql = "SELECT id FROM compta_pieces WHERE numero_piece = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$paiement[0]]);
        
        if (!$stmt->fetch()) {
            $sql = "SELECT id FROM compta_comptes WHERE numero_compte = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([strval($paiement[5])]);
            $compte_tiers = $stmt->fetch()['id'];
            
            $sql = "
                INSERT INTO compta_pieces 
                (numero_piece, date_piece, exercice_id, journal_id, est_validee, observations)
                VALUES (?, ?, ?, ?, 1, ?)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$paiement[0], $paiement[1], $exercice_id, $journal_id, $paiement[3]]);
            $piece_id = $pdo->lastInsertId();
            
            // Si paiement à fournisseur : crédit 401, débit 512
            // Si encaissement client : débit 512, crédit 411
            if ($paiement[4] == 'AC') {
                $sql = "
                    INSERT INTO compta_ecritures 
                    (piece_id, compte_id, debit, credit, libelle_ecriture, ordre_ligne)
                    VALUES (?, ?, ?, 0, ?, 1), (?, ?, 0, ?, ?, 2)
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $piece_id, $compte_512, $paiement[2], $paiement[3],
                    $piece_id, $compte_tiers, $paiement[2], $paiement[3]
                ]);
            } else {
                $sql = "
                    INSERT INTO compta_ecritures 
                    (piece_id, compte_id, debit, credit, libelle_ecriture, ordre_ligne)
                    VALUES (?, ?, ?, 0, ?, 1), (?, ?, 0, ?, ?, 2)
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $piece_id, $compte_512, $paiement[2], $paiement[3],
                    $piece_id, $compte_tiers, $paiement[2], $paiement[3]
                ]);
            }
            
            echo "✓ Paiement créé : " . $paiement[0] . " - " . number_format($paiement[2], 2, ',', ' ') . " €\n";
        }
    }
    
    // 5. Créer des pièces de charges
    echo "\n5️⃣  CRÉATION DE PIÈCES DE CHARGES\n\n";
    
    $sql_journal = "SELECT id FROM compta_journaux WHERE code = 'OD' LIMIT 1";
    $stmt_journal = $pdo->prepare($sql_journal);
    $stmt_journal->execute();
    $journal = $stmt_journal->fetch();
    $journal_id = $journal['id'];
    
    $sql = "SELECT id FROM compta_comptes WHERE numero_compte = '622'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $compte_622 = $stmt->fetch()['id'];
    
    $sql = "SELECT id FROM compta_comptes WHERE numero_compte = '530'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $compte_530 = $stmt->fetch()['id'];
    
    $charges = [
        ['CH-2025-00001', '2025-12-06', 450000, 'Salaires décembre'],
        ['CH-2025-00002', '2025-12-08', 150000, 'Frais de transport'],
    ];
    
    foreach ($charges as $charge) {
        $sql = "SELECT id FROM compta_pieces WHERE numero_piece = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$charge[0]]);
        
        if (!$stmt->fetch()) {
            $sql = "
                INSERT INTO compta_pieces 
                (numero_piece, date_piece, exercice_id, journal_id, est_validee, observations)
                VALUES (?, ?, ?, ?, 1, ?)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$charge[0], $charge[1], $exercice_id, $journal_id, $charge[3]]);
            $piece_id = $pdo->lastInsertId();
            
            $sql = "
                INSERT INTO compta_ecritures 
                (piece_id, compte_id, debit, credit, libelle_ecriture, ordre_ligne)
                VALUES (?, ?, ?, 0, ?, 1), (?, ?, 0, ?, ?, 2)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $piece_id, $compte_622, $charge[2], $charge[3],
                $piece_id, $compte_530, $charge[2], $charge[3]
            ]);
            
            echo "✓ Charge créée : " . $charge[0] . " - " . number_format($charge[2], 2, ',', ' ') . " €\n";
        }
    }
    
    // 6. Vérifier le bilan final
    echo "\n6️⃣  VÉRIFICATION DU BILAN FINAL\n\n";
    
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
    
    $total_debit = 0;
    $total_credit = 0;
    
    foreach ($balance as $ligne) {
        $d = floatval($ligne['total_debit']);
        $c = floatval($ligne['total_credit']);
        $total_debit += $d;
        $total_credit += $c;
        echo "Classe " . $ligne['classe'] . " : Débit " . number_format($d, 2, ',', ' ') . " | Crédit " . number_format($c, 2, ',', ' ') . "\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Total Débits   : " . number_format($total_debit, 2, ',', ' ') . " €\n";
    echo "Total Crédits  : " . number_format($total_credit, 2, ',', ' ') . " €\n";
    echo "Équilibre      : " . (abs($total_debit - $total_credit) < 0.01 ? "✓ OK" : "✗ ERREUR") . "\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}

?>
