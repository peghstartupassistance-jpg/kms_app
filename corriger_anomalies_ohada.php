<?php
/**
 * Script de correction des anomalies comptables OHADA Cameroun
 * 
 * Corrections :
 * 1. Stocks : Classe 2 → Classe 3 (ACTIF CIRCULANT)
 * 2. Caisse négative : Correction du compte 571
 * 3. Reclassification en respectant SYSCOHADA-OHADA Cameroun
 * 
 * Normes OHADA Cameroun :
 * - Classe 1 : Capitaux propres (10 Capital, 11 Réserves, 12 Bénéfices distribués)
 * - Classe 2 : Immobilisations (20 Corporelles, 21 Incorporelles, 22 Financières)
 * - Classe 3 : Stocks et en-cours (31 Marchandises, 32 Produits finis, 33 Matières premières, 37 Autres stocks)
 * - Classe 4 : Tiers (40 Fournisseurs, 41 Clients, 42 Personnel, 43 Organismes sociaux, 44 État, 45 Groupe, 46 Associés, 47 Autres tiers, 48 Fournisseurs-Factures non reçues)
 * - Classe 5 : Trésorerie (51 Banques, 52 Chèques postaux, 57 Caisse, 58 Crédits de trésorerie, 59 Placements)
 * - Classe 6 : Charges (60 Approvisionnements, 61 Services extérieurs, 62 Rémunérations du personnel, 63 Impôts et taxes, 64 Frais divers, 65 Charges financières, 66 Charges exceptionnelles)
 * - Classe 7 : Produits (70 Ventes de marchandises, 71 Ventes de produits finis, 72 Prestations de services, 73 Subventions, 74 Produits financiers, 75 Produits exceptionnels)
 * - Classe 8 : Résultats (80 Résultat net)
 * - Classe 9 : Comptes analytiques/mémoriel
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Connexion à la base
    $pdo = new PDO(
        'mysql:host=localhost;dbname=kms_gestion;charset=utf8mb4',
        'root',
        ''
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CORRECTION DES ANOMALIES COMPTABLES OHADA CAMEROUN ===\n\n";
    
    // ==============================================
    // ANOMALIE 1 : Stocks en Classe 2 → Classe 3
    // ==============================================
    echo "📊 ANOMALIE 1 : Reclassement des stocks (Classe 2 → Classe 3)\n";
    echo "─────────────────────────────────────────────────\n";
    
    // Chercher le compte stocks en classe 2
    $stmt = $pdo->prepare("
        SELECT id, numero_compte, libelle, classe 
        FROM compta_comptes 
        WHERE numero_compte = '2' OR (numero_compte LIKE '2%' AND libelle LIKE '%stock%')
        LIMIT 1
    ");
    $stmt->execute();
    $compte_stock_c2 = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($compte_stock_c2) {
        echo "✓ Compte trouvé : {$compte_stock_c2['numero_compte']} - {$compte_stock_c2['libelle']} (Classe {$compte_stock_c2['classe']})\n";
        
        // Créer ou récupérer compte stocks en classe 3
        $stmt = $pdo->prepare("
            SELECT id FROM compta_comptes 
            WHERE numero_compte LIKE '3%' AND (libelle LIKE '%stock%' OR libelle LIKE '%marchandise%')
            LIMIT 1
        ");
        $stmt->execute();
        $compte_stock_c3 = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$compte_stock_c3) {
            echo "  → Création du compte Classe 3 - Stocks...\n";
            // Créer le compte 31 Marchandises en Classe 3
            $stmt = $pdo->prepare("
                INSERT INTO compta_comptes 
                (numero_compte, libelle, classe, type_compte, nature, est_actif, observations)
                VALUES ('31', 'Marchandises', '3', 'ACTIF', 'STOCK', 1, 'Stocks OHADA Cameroun')
            ");
            $stmt->execute();
            $compte_stock_c3_id = $pdo->lastInsertId();
            echo "  ✓ Compte 31 créé (ID: {$compte_stock_c3_id})\n";
        } else {
            $compte_stock_c3_id = $compte_stock_c3['id'];
            echo "  → Compte Classe 3 existant : ID {$compte_stock_c3_id}\n";
        }
        
        // Transférer les écritures du compte Classe 2 vers Classe 3
        $stmt = $pdo->prepare("
            SELECT SUM(debit) as total_debit, SUM(credit) as total_credit
            FROM compta_ecritures
            WHERE compte_id = ?
        ");
        $stmt->execute([$compte_stock_c2['id']]);
        $soldes = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "  → Soldes du compte stocks : D={$soldes['total_debit']} C={$soldes['total_credit']}\n";
        
        // Reclasser les écritures
        $stmt = $pdo->prepare("
            UPDATE compta_ecritures 
            SET compte_id = ? 
            WHERE compte_id = ?
        ");
        $stmt->execute([$compte_stock_c3_id, $compte_stock_c2['id']]);
        $nb_lignes = $stmt->rowCount();
        echo "  ✓ {$nb_lignes} ligne(s) d'écriture reclassée(s)\n";
        
        // Archiver l'ancien compte
        $stmt = $pdo->prepare("
            UPDATE compta_comptes 
            SET est_actif = 0, observations = 'Archivé - Reclassé en Classe 3'
            WHERE id = ?
        ");
        $stmt->execute([$compte_stock_c2['id']]);
        echo "  ✓ Ancien compte archivé\n";
        
    } else {
        echo "⚠️ Aucun compte stocks en Classe 2 trouvé\n";
    }
    
    echo "\n";
    
    // ==============================================
    // ANOMALIE 2 : Caisse créditrice (571)
    // ==============================================
    echo "💰 ANOMALIE 2 : Correction de la caisse (571 - créditrice)\n";
    echo "─────────────────────────────────────────────────\n";
    
    // Chercher le compte caisse
    $stmt = $pdo->prepare("
        SELECT cel.compte_id, cc.numero_compte, cc.libelle,
               SUM(cel.debit) as total_debit, SUM(cel.credit) as total_credit
        FROM compta_ecriture_lignes cel
        JOIN compta_comptes cc ON cel.compte_id = cc.id
        WHERE cc.numero_compte LIKE '57%'
        GROUP BY cel.compte_id, cc.numero_compte, cc.libelle
    ");
    $stmt->execute();
    $caisses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($caisses as $caisse) {
        $solde = $caisse['total_debit'] - $caisse['total_credit'];
        echo "✓ Compte {$caisse['numero_compte']} - {$caisse['libelle']}\n";
        echo "  Débit : {$caisse['total_debit']}, Crédit : {$caisse['total_credit']}\n";
        echo "  Solde : {$solde}\n";
        
        if ($solde < 0) {
            echo "  ❌ CRÉDITRICE (anormal pour une caisse) : " . abs($solde) . " FCFA\n";
            echo "  → Recherche des écritures inverses...\n";
            
            // Chercher les écritures suspects
            $stmt2 = $pdo->prepare("
                SELECT ce.id, ce.debit, ce.credit, ce.libelle_ecriture, 
                       cp.numero_piece, cp.date_piece
                FROM compta_ecriture_lignes ce
                JOIN compta_pieces cp ON ce.piece_id = cp.id
                WHERE ce.compte_id = ?
                ORDER BY cp.date_piece DESC
                LIMIT 10
            ");
            $stmt2->execute([$caisse['compte_id']]);
            $ecritures = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($ecritures as $ec) {
                if ($ec['credit'] > 0 && $ec['debit'] == 0) {
                    echo "    Ligne suspecte : {$ec['libelle_ecriture']} - Crédit: {$ec['credit']} - Pièce: {$ec['numero_piece']} ({$ec['date_piece']})\n";
                }
            }
            
            // Créer une pièce de correction
            echo "  → Création d'une pièce de correction...\n";
            
            // Créer la pièce de correction
            $stmt2 = $pdo->prepare("
                INSERT INTO compta_pieces 
                (exercice_id, journal_id, numero_piece, date_piece, reference_type, observations)
                VALUES (2, 3, 'CORR-CAISSE-' . DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'), CURDATE(), 'CORRECTION_ANOMALIE', 'Correction anomalie caisse créditrice')
            ");
            $stmt2->execute();
            $piece_id = $pdo->lastInsertId();
            echo "    ✓ Pièce correction créée : ID {$piece_id}\n";
            
            // Créer les écritures de correction (inverser débit/crédit)
            $correction_montant = abs($solde);
            
            // Crédit du compte caisse → Débit (annulation)
            $stmt2 = $pdo->prepare("
                INSERT INTO compta_ecriture_lignes 
                (piece_id, compte_id, debit, credit, libelle_ecriture)
                VALUES (?, ?, ?, 0, 'Correction : Annulation crédit caisse')
            ");
            $stmt2->execute([$piece_id, $caisse['compte_id'], $correction_montant]);
            echo "    ✓ Ligne 1 : Débit {$correction_montant}\n";
            
            // Compte de correction (marge ou bénéfice)
            $stmt2 = $pdo->prepare("
                SELECT id FROM compta_comptes 
                WHERE numero_compte = '80' OR numero_compte LIKE '75%'
                LIMIT 1
            ");
            $stmt2->execute();
            $compte_correction = $stmt2->fetch(PDO::FETCH_ASSOC);
            
            if ($compte_correction) {
                $stmt2 = $pdo->prepare("
                    INSERT INTO compta_ecriture_lignes 
                    (piece_id, compte_id, debit, credit, libelle_ecriture)
                    VALUES (?, ?, 0, ?, 'Correction : Gain sur ajustement trésorerie')
                ");
                $stmt2->execute([$piece_id, $compte_correction['id'], $correction_montant]);
                echo "    ✓ Ligne 2 : Crédit {$correction_montant} (Compte résultat)\n";
            }
            
            echo "  ✓ Correction enregistrée dans pièce #{$piece_id}\n";
            
        } else {
            echo "  ✓ CORRECTE (débitrice)\n";
        }
    }
    
    echo "\n";
    
    // ==============================================
    // VÉRIFICATION FINALE
    // ==============================================
    echo "✅ VÉRIFICATION FINALE APRÈS CORRECTIONS\n";
    echo "─────────────────────────────────────────────────\n";
    
    // Recalculer la balance
    $stmt = $pdo->prepare("
        SELECT cc.classe, cc.numero_compte, cc.libelle,
               SUM(cel.debit) as total_debit, SUM(cel.credit) as total_credit
        FROM compta_ecriture_lignes cel
        JOIN compta_comptes cc ON cel.compte_id = cc.id
        GROUP BY cc.classe, cc.numero_compte, cc.libelle
        ORDER BY cc.numero_compte
    ");
    $stmt->execute();
    $balance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totaux = [
        '1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, '6' => 0, '7' => 0
    ];
    
    foreach ($balance as $ligne) {
        $solde = $ligne['total_debit'] - $ligne['total_credit'];
        if (abs($solde) > 100) {
            $classe = $ligne['classe'];
            printf("%s - %-40s | D: %12.2f | C: %12.2f | Solde: %12.2f\n",
                $ligne['numero_compte'],
                substr($ligne['libelle'], 0, 40),
                $ligne['total_debit'],
                $ligne['total_credit'],
                $solde
            );
            
            if (isset($totaux[$classe])) {
                $totaux[$classe] += $solde;
            }
        }
    }
    
    echo "\n📊 RÉSUMÉ PAR CLASSE (OHADA CAMEROUN)\n";
    echo "─────────────────────────────────────────────────\n";
    
    $classes = [
        '1' => 'Capitaux propres',
        '2' => 'Immobilisations',
        '3' => 'Stocks & En-cours',
        '4' => 'Tiers',
        '5' => 'Trésorerie',
        '6' => 'Charges',
        '7' => 'Produits'
    ];
    
    $total_actif = $totaux['2'] + $totaux['3'] + ($totaux['4'] > 0 ? $totaux['4'] : 0) + ($totaux['5'] > 0 ? $totaux['5'] : 0);
    $total_passif = abs($totaux['1']) + (abs($totaux['4']) < 0 ? abs($totaux['4']) : 0) + (abs($totaux['5']) < 0 ? abs($totaux['5']) : 0);
    $resultat = $totaux['7'] - $totaux['6'];
    
    foreach ($classes as $num => $nom) {
        $val = $totaux[$num];
        $type = match($num) {
            '1' => '(PASSIF)',
            '2', '3', '4', '5' => $val > 0 ? '(ACTIF)' : '(PASSIF)',
            '6', '7' => ''
        };
        printf("Classe %d - %-30s : %15.2f FCFA %s\n", $num, $nom, abs($val), $type);
    }
    
    echo "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    printf("TOTAL ACTIF (2+3+4+5)           : %15.2f FCFA\n", $total_actif);
    printf("TOTAL PASSIF (1+4+5)            : %15.2f FCFA\n", $total_passif);
    printf("RÉSULTAT (Produits - Charges)   : %15.2f FCFA\n", $resultat);
    printf("TOTAL PASSIF + RÉSULTAT         : %15.2f FCFA\n", $total_passif + $resultat);
    printf("ÉCART                           : %15.2f FCFA\n", ($total_actif) - ($total_passif + $resultat));
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    if (abs($total_actif - ($total_passif + $resultat)) < 0.01) {
        echo "\n✅ ✅ ✅ BILAN ÉQUILIBRÉ - CORRECTIONS APPLIQUÉES ✅ ✅ ✅\n";
    } else {
        echo "\n⚠️ BILAN TOUJOURS DÉSÉQUILIBRÉ - Vérification manuelle nécessaire\n";
    }
    
    echo "\n" . date('Y-m-d H:i:s') . " - Corrections OHADA Cameroun effectuées\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR : " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
