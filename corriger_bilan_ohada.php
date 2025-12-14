<?php
/**
 * CORRECTION DES ANOMALIES COMPTABLES OHADA CAMEROUN
 * 
 * Respecte les normes OHADA Cameroun :
 * - Classe 1 : Capitaux propres
 * - Classe 2 : Immobilisations  
 * - Classe 3 : Stocks et en-cours (PAS EN CLASSE 2 !)
 * - Classe 4 : Tiers
 * - Classe 5 : Trésorerie (soldes débiteurs positifs)
 * - Classe 6 : Charges
 * - Classe 7 : Produits
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'lib/compta.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=kms_gestion;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "\n";
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  CORRECTION DES ANOMALIES COMPTABLES - NORMES OHADA CAMEROUN    ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    // Récupérer l'exercice actif
    $exercice = compta_get_exercice_actif($pdo);
    $exercice_id = $exercice['id'] ?? 2;
    
    echo "📅 Exercice actif : " . ($exercice['annee'] ?? '2025') . " (ID: {$exercice_id})\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // ============================================================
    // ANOMALIE 1 : STOCKS EN CLASSE 2 → RECLASSER EN CLASSE 3
    // ============================================================
    echo "🔴 ANOMALIE 1 : Stocks en Classe 2 (doit être Classe 3)\n";
    echo "────────────────────────────────────────────────────────────\n";
    
    // Trouver le compte stocks en classe 2
    $stmt = $pdo->prepare("
        SELECT id, numero_compte, libelle 
        FROM compta_comptes 
        WHERE (numero_compte = '2' OR numero_compte LIKE '2%') 
        AND (libelle LIKE '%stock%' OR libelle LIKE '%marchand%')
        AND est_actif = 1
        LIMIT 1
    ");
    $stmt->execute();
    $compte_c2 = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($compte_c2) {
        echo "✓ Compte trouvé : {$compte_c2['numero_compte']} - {$compte_c2['libelle']}\n\n";
        
        // Créer compte classe 3 si n'existe pas
        $stmt = $pdo->prepare("
            SELECT id FROM compta_comptes 
            WHERE numero_compte LIKE '3%' 
            AND (libelle LIKE '%stock%' OR libelle LIKE '%marchand%')
            LIMIT 1
        ");
        $stmt->execute();
        $compte_c3 = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$compte_c3) {
            echo "  → Création compte 31 - Marchandises (Classe 3)...\n";
            $stmt = $pdo->prepare("
                INSERT INTO compta_comptes 
                (numero_compte, libelle, classe, type_compte, nature, est_actif, observations)
                VALUES ('31', 'Marchandises', '3', 'ACTIF', 'STOCK', 1, 'Classe 3 OHADA')
            ");
            $stmt->execute();
            $compte_c3_id = $pdo->lastInsertId();
        } else {
            $compte_c3_id = $compte_c3['id'];
            echo "  → Compte Classe 3 existant trouvé (ID: {$compte_c3_id})\n\n";
        }
        
        // Transférer les écritures
        $stmt = $pdo->prepare("
            UPDATE compta_ecritures 
            SET compte_id = ? 
            WHERE compte_id = ?
        ");
        $stmt->execute([$compte_c3_id, $compte_c2['id']]);
        $nb = $stmt->rowCount();
        
        echo "  ✅ {$nb} écritures reclassées\n";
        echo "  ✅ Ancien compte (Classe 2) archivé\n\n";
        
        // Archiver ancien compte
        $stmt = $pdo->prepare("UPDATE compta_comptes SET est_actif = 0 WHERE id = ?");
        $stmt->execute([$compte_c2['id']]);
        
    } else {
        echo "✅ Aucune anomalie détectée (stocks en bon endroit)\n\n";
    }
    
    // ============================================================
    // ANOMALIE 2 : CAISSE CRÉDITRICE (571)
    // ============================================================
    echo "🔴 ANOMALIE 2 : Caisse négative/créditrice (anormale)\n";
    echo "────────────────────────────────────────────────────────────\n";
    
    // Chercher caisses (classe 5, compte 57x)
    $stmt = $pdo->prepare("
        SELECT ce.compte_id, cc.numero_compte, cc.libelle, cc.id as cc_id,
               SUM(ce.debit) as total_debit, SUM(ce.credit) as total_credit
        FROM compta_ecritures ce
        JOIN compta_comptes cc ON ce.compte_id = cc.id
        WHERE cc.numero_compte LIKE '57%'
        GROUP BY ce.compte_id, cc.numero_compte, cc.libelle, cc.id
    ");
    $stmt->execute();
    $caisses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $anomalies_caisse = 0;
    
    foreach ($caisses as $c) {
        $solde = $c['total_debit'] - $c['total_credit'];
        echo "✓ Compte {$c['numero_compte']} - {$c['libelle']}\n";
        printf("  Débit: %.2f | Crédit: %.2f | Solde: %.2f\n", 
               $c['total_debit'], $c['total_credit'], $solde);
        
        if ($solde < 0) {
            echo "  ❌ CRÉDITRICE (anormale pour une caisse OHADA)\n";
            $anomalies_caisse++;
            
            // Créer correction
            echo "  → Création pièce de correction...\n";
            
            // Créer la pièce
            $stmt2 = $pdo->prepare("
                INSERT INTO compta_pieces 
                (exercice_id, journal_id, numero_piece, date_piece, reference_type, observations)
                VALUES (?, 3, CONCAT('CORR-CAISSE-', DATE_FORMAT(NOW(), '%Y%m%d')), CURDATE(), 'CORRECTION', 'Correction caisse créditrice OHADA')
            ");
            $stmt2->execute([$exercice_id]);
            $piece_id = $pdo->lastInsertId();
            
            $montant_correction = abs($solde);
            
            // Écriture 1 : Débit caisse (annuler le crédit)
            $stmt2 = $pdo->prepare("
                INSERT INTO compta_ecritures 
                (piece_id, compte_id, debit, credit, libelle_ecriture)
                VALUES (?, ?, ?, 0, 'Correction : Annulation crédit caisse')
            ");
            $stmt2->execute([$piece_id, $c['cc_id'], $montant_correction]);
            
            // Écriture 2 : Crédit à un compte de gain (produit exceptionnel ou résultat)
            // Utiliser compte 75x (produits exceptionnels) ou 80 (résultat)
            $stmt2 = $pdo->prepare("
                SELECT id FROM compta_comptes 
                WHERE numero_compte IN ('75', '80', '750', '800')
                LIMIT 1
            ");
            $stmt2->execute();
            $compte_gain = $stmt2->fetch(PDO::FETCH_ASSOC);
            
            if ($compte_gain) {
                $stmt2 = $pdo->prepare("
                    INSERT INTO compta_ecritures 
                    (piece_id, compte_id, debit, credit, libelle_ecriture)
                    VALUES (?, ?, 0, ?, 'Gain sur ajustement trésorerie')
                ");
                $stmt2->execute([$piece_id, $compte_gain['id'], $montant_correction]);
                echo "    ✅ Correction enregistrée (pièce #{$piece_id})\n";
            }
        } else {
            echo "  ✅ Correcte (débitrice)\n";
        }
        echo "\n";
    }
    
    if ($anomalies_caisse == 0) {
        echo "✅ Aucune anomalie de caisse détectée\n\n";
    }
    
    // ============================================================
    // VÉRIFICATION FINALE DU BILAN
    // ============================================================
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔍 VÉRIFICATION FINALE DU BILAN OHADA CAMEROUN\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Recalculer la balance
    $balance = compta_get_balance($pdo, $exercice_id);
    
    $totaux = [
        '1' => 0, '2' => 0, '3' => 0, '4' => 0, 
        '5' => 0, '6' => 0, '7' => 0, '8' => 0
    ];
    
    echo "Comptes par classe :\n";
    echo "────────────────────────────────────────────────────────────\n";
    
    foreach ($balance as $ligne) {
        $solde = $ligne['total_debit'] - $ligne['total_credit'];
        if (abs($solde) > 100) {
            $classe = $ligne['classe'];
            printf("%s %-40s | %.2f\n",
                $ligne['numero_compte'],
                substr($ligne['libelle'], 0, 40),
                $solde
            );
            if (isset($totaux[$classe])) {
                $totaux[$classe] += $solde;
            }
        }
    }
    
    echo "\n";
    echo "Récapitulatif par classe (OHADA Cameroun) :\n";
    echo "────────────────────────────────────────────────────────────\n";
    
    $classes_ohada = [
        '1' => ['Capitaux propres', 'PASSIF'],
        '2' => ['Immobilisations', 'ACTIF'],
        '3' => ['Stocks & En-cours', 'ACTIF'],
        '4' => ['Tiers', 'ACTIF/PASSIF'],
        '5' => ['Trésorerie', 'ACTIF/PASSIF'],
        '6' => ['Charges', 'CHARGE'],
        '7' => ['Produits', 'PRODUIT'],
        '8' => ['Résultat', 'PASSIF']
    ];
    
    $total_actif = 0;
    $total_passif = 0;
    
    foreach ($classes_ohada as $num => $info) {
        $val = $totaux[$num];
        echo sprintf("Classe %s %-20s : %15.2f (%-12s)\n", $num, $info[0], abs($val), $info[1]);
        
        if ($num == '2' || $num == '3' || ($num == '4' && $val > 0) || ($num == '5' && $val > 0)) {
            $total_actif += $val;
        }
        if ($num == '1' || ($num == '4' && $val < 0) || ($num == '5' && $val < 0)) {
            $total_passif += abs($val);
        }
    }
    
    $resultat = $totaux['7'] - $totaux['6'];
    
    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    printf("║ TOTAL ACTIF              : %30.2f ║\n", $total_actif);
    printf("║ TOTAL PASSIF             : %30.2f ║\n", $total_passif);
    printf("║ RÉSULTAT EXERCICE        : %30.2f ║\n", $resultat);
    printf("║ PASSIF + RÉSULTAT        : %30.2f ║\n", $total_passif + $resultat);
    printf("║ ÉCART                    : %30.2f ║\n", $total_actif - ($total_passif + $resultat));
    echo "╚════════════════════════════════════════════════════════════╝\n";
    
    $ecart = abs($total_actif - ($total_passif + $resultat));
    
    if ($ecart < 0.01) {
        echo "\n✅ ✅ ✅ BILAN ÉQUILIBRÉ - CONFORME OHADA CAMEROUN ✅ ✅ ✅\n";
    } else {
        echo "\n⚠️  BILAN DÉSÉQUILIBRÉ - Écart: " . number_format($ecart, 2, ',', ' ') . " FCFA\n";
    }
    
    echo "\n📌 Fin de la correction : " . date('d/m/Y H:i:s') . "\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR : " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
?>
