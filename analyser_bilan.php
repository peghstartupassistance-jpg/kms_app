<?php
/**
 * Diagnostic détaillé du déséquilibre du bilan
 */

require_once __DIR__ . '/db/db.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostic Bilan</title>
    <style>
        body { font-family: 'Courier New', monospace; max-width: 1400px; margin: 20px auto; padding: 20px; background: #f5f5f5; font-size: 14px; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #e74c3c; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; background: #ecf0f1; padding: 10px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; }
        th { background: #34495e; color: white; padding: 10px; text-align: left; position: sticky; top: 0; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f8f9fa; }
        .error { background: #ffebee; border-left: 4px solid #e74c3c; padding: 10px; margin: 10px 0; }
        .warning { background: #fff3e0; border-left: 4px solid #ef6c00; padding: 10px; margin: 10px 0; }
        .success { background: #e8f5e9; border-left: 4px solid #2e7d32; padding: 10px; margin: 10px 0; }
        .total { font-weight: bold; background: #f0f0f0; font-size: 15px; }
        .highlight { background: #fff9c4; font-weight: bold; }
        .right { text-align: right; }
        .center { text-align: center; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 DIAGNOSTIC DÉTAILLÉ DU BILAN</h1>";

try {
    // Récupérer l'exercice actif
    $stmt = $pdo->query("SELECT * FROM compta_exercices WHERE est_clos = 0 ORDER BY annee DESC LIMIT 1");
    $exercice = $stmt->fetch();
    
    if (!$exercice) {
        die("<div class='error'>❌ Aucun exercice ouvert</div>");
    }
    
    echo "<p><strong>Exercice :</strong> {$exercice['annee']}</p>";
    
    // =========================================================================
    // 1. TOUS LES COMPTES AVEC SOLDES
    // =========================================================================
    echo "<h2>📊 1. TOUS LES COMPTES AVEC SOLDES (par classe)</h2>";
    
    $sql = "
        SELECT 
            cc.id,
            cc.numero_compte,
            cc.libelle,
            cc.classe,
            cc.type_compte,
            COALESCE(SUM(CASE WHEN cp.est_validee = 1 THEN ce.debit ELSE 0 END), 0) as total_debit,
            COALESCE(SUM(CASE WHEN cp.est_validee = 1 THEN ce.credit ELSE 0 END), 0) as total_credit,
            COALESCE(SUM(CASE WHEN cp.est_validee = 1 THEN ce.debit ELSE 0 END), 0) - 
            COALESCE(SUM(CASE WHEN cp.est_validee = 1 THEN ce.credit ELSE 0 END), 0) as solde
        FROM compta_comptes cc
        LEFT JOIN compta_ecritures ce ON ce.compte_id = cc.id
        LEFT JOIN compta_pieces cp ON cp.id = ce.piece_id AND cp.exercice_id = ?
        WHERE cc.est_actif = 1
        GROUP BY cc.id
        HAVING ABS(solde) > 0.01
        ORDER BY cc.classe, cc.numero_compte
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$exercice['id']]);
    $comptes = $stmt->fetchAll();
    
    $total_debit_global = 0;
    $total_credit_global = 0;
    
    echo "<table>
            <tr>
                <th>ID</th>
                <th>N° Compte</th>
                <th>Libellé</th>
                <th class='center'>Classe</th>
                <th>Type</th>
                <th class='right'>Débit</th>
                <th class='right'>Crédit</th>
                <th class='right'>Solde</th>
                <th>Position</th>
            </tr>";
    
    $totaux_par_classe = [];
    
    foreach ($comptes as $compte) {
        $classe = $compte['classe'];
        $solde = (float)$compte['solde'];
        $total_debit_global += (float)$compte['total_debit'];
        $total_credit_global += (float)$compte['total_credit'];
        
        // Déterminer où ce compte devrait apparaître
        $position = '';
        $position_color = '';
        
        if ($classe == '1') {
            $position = 'PASSIF (Capitaux propres)';
            $position_color = '#fff3e0';
        } elseif ($classe == '2') {
            $position = 'ACTIF (Immobilisations)';
            $position_color = '#e3f2fd';
        } elseif ($classe == '3') {
            $position = 'ACTIF (Stocks)';
            $position_color = '#e8f5e9';
        } elseif ($classe == '4') {
            if ($solde > 0) {
                $position = 'ACTIF (Créances)';
                $position_color = '#e8f5e9';
            } else {
                $position = 'PASSIF (Dettes)';
                $position_color = '#ffebee';
            }
        } elseif ($classe == '5') {
            if ($solde > 0) {
                $position = 'ACTIF (Trésorerie)';
                $position_color = '#e8f5e9';
            } else {
                $position = 'PASSIF (Trésorerie)';
                $position_color = '#ffebee';
            }
        } elseif ($classe == '6') {
            $position = 'CHARGES (Compte de résultat)';
            $position_color = '#fce4ec';
        } elseif ($classe == '7') {
            $position = 'PRODUITS (Compte de résultat)';
            $position_color = '#f3e5f5';
        }
        
        // Vérifier incohérences
        $class_row = '';
        $premier_chiffre = substr($compte['numero_compte'], 0, 1);
        if ($premier_chiffre != $classe) {
            $class_row = 'highlight';
            $position .= " ⚠️ INCOHÉRENT (N° commence par {$premier_chiffre} mais classe={$classe})";
        }
        
        if (!isset($totaux_par_classe[$classe])) {
            $totaux_par_classe[$classe] = 0;
        }
        $totaux_par_classe[$classe] += $solde;
        
        echo "<tr class='{$class_row}' style='background: {$position_color};'>
                <td>{$compte['id']}</td>
                <td><strong>{$compte['numero_compte']}</strong></td>
                <td>{$compte['libelle']}</td>
                <td class='center'><strong>{$classe}</strong></td>
                <td>{$compte['type_compte']}</td>
                <td class='right'>" . number_format($compte['total_debit'], 2, ',', ' ') . "</td>
                <td class='right'>" . number_format($compte['total_credit'], 2, ',', ' ') . "</td>
                <td class='right'><strong>" . number_format($solde, 2, ',', ' ') . "</strong></td>
                <td><em>{$position}</em></td>
              </tr>";
    }
    
    echo "<tr class='total'>
            <td colspan='5'>TOTAUX GÉNÉRAUX</td>
            <td class='right'>" . number_format($total_debit_global, 2, ',', ' ') . "</td>
            <td class='right'>" . number_format($total_credit_global, 2, ',', ' ') . "</td>
            <td class='right'>" . number_format($total_debit_global - $total_credit_global, 2, ',', ' ') . "</td>
            <td></td>
          </tr>";
    
    echo "</table>";
    
    // Vérification équilibre écritures
    if (abs($total_debit_global - $total_credit_global) < 0.01) {
        echo "<div class='success'><strong>✅ Écritures équilibrées :</strong> Débit = Crédit (principe de la partie double respecté)</div>";
    } else {
        echo "<div class='error'><strong>❌ PROBLÈME GRAVE :</strong> Débit ≠ Crédit dans les écritures ! Différence : " . 
             number_format(abs($total_debit_global - $total_credit_global), 2, ',', ' ') . " €</div>";
    }
    
    // =========================================================================
    // 2. CALCUL BILAN SELON SYSCOHADA
    // =========================================================================
    echo "<h2>📋 2. RECONSTITUTION DU BILAN (Logique SYSCOHADA)</h2>";
    
    $actif_total = 0;
    $passif_total = 0;
    
    echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 20px;'>";
    
    // ACTIF
    echo "<div style='border: 2px solid #2196F3; padding: 15px; border-radius: 8px;'>";
    echo "<h3 style='background: #2196F3; color: white; padding: 10px; margin: -15px -15px 15px -15px;'>ACTIF</h3>";
    
    echo "<table style='width: 100%; font-size: 12px;'>
            <tr><th>Compte</th><th>Libellé</th><th class='right'>Montant</th></tr>";
    
    // Classe 2 : Immobilisations
    if (isset($totaux_par_classe['2'])) {
        $montant = $totaux_par_classe['2'];
        if ($montant > 0) {
            echo "<tr><td colspan='2' style='background: #e3f2fd; font-weight: bold;'>Classe 2 - Immobilisations</td><td class='right' style='background: #e3f2fd;'>" . number_format($montant, 2, ',', ' ') . "</td></tr>";
            $actif_total += $montant;
        }
    }
    
    // Classe 3 : Stocks
    if (isset($totaux_par_classe['3'])) {
        $montant = $totaux_par_classe['3'];
        if ($montant > 0) {
            echo "<tr><td colspan='2' style='background: #e8f5e9; font-weight: bold;'>Classe 3 - Stocks</td><td class='right' style='background: #e8f5e9;'>" . number_format($montant, 2, ',', ' ') . "</td></tr>";
            $actif_total += $montant;
        }
    }
    
    // Classe 4 : Créances (solde débiteur)
    foreach ($comptes as $compte) {
        if ($compte['classe'] == '4' && (float)$compte['solde'] > 0) {
            echo "<tr><td>{$compte['numero_compte']}</td><td>{$compte['libelle']}</td><td class='right'>" . number_format($compte['solde'], 2, ',', ' ') . "</td></tr>";
            $actif_total += (float)$compte['solde'];
        }
    }
    
    // Classe 5 : Trésorerie-Actif (solde débiteur)
    foreach ($comptes as $compte) {
        if ($compte['classe'] == '5' && (float)$compte['solde'] > 0) {
            echo "<tr><td>{$compte['numero_compte']}</td><td>{$compte['libelle']}</td><td class='right'>" . number_format($compte['solde'], 2, ',', ' ') . "</td></tr>";
            $actif_total += (float)$compte['solde'];
        }
    }
    
    echo "<tr class='total'><td colspan='2'>TOTAL ACTIF</td><td class='right'>" . number_format($actif_total, 2, ',', ' ') . "</td></tr>";
    echo "</table></div>";
    
    // PASSIF
    echo "<div style='border: 2px solid #FF9800; padding: 15px; border-radius: 8px;'>";
    echo "<h3 style='background: #FF9800; color: white; padding: 10px; margin: -15px -15px 15px -15px;'>PASSIF + RÉSULTAT</h3>";
    
    echo "<table style='width: 100%; font-size: 12px;'>
            <tr><th>Compte</th><th>Libellé</th><th class='right'>Montant</th></tr>";
    
    // Classe 1 : Capitaux propres
    if (isset($totaux_par_classe['1'])) {
        $montant = abs($totaux_par_classe['1']);
        echo "<tr><td colspan='2' style='background: #fff3e0; font-weight: bold;'>Classe 1 - Capitaux propres</td><td class='right' style='background: #fff3e0;'>" . number_format($montant, 2, ',', ' ') . "</td></tr>";
        $passif_total += $montant;
    }
    
    // Classe 4 : Dettes (solde créditeur)
    foreach ($comptes as $compte) {
        if ($compte['classe'] == '4' && (float)$compte['solde'] < 0) {
            echo "<tr><td>{$compte['numero_compte']}</td><td>{$compte['libelle']}</td><td class='right'>" . number_format(abs($compte['solde']), 2, ',', ' ') . "</td></tr>";
            $passif_total += abs((float)$compte['solde']);
        }
    }
    
    // Classe 5 : Trésorerie-Passif (solde créditeur)
    foreach ($comptes as $compte) {
        if ($compte['classe'] == '5' && (float)$compte['solde'] < 0) {
            echo "<tr><td>{$compte['numero_compte']}</td><td>{$compte['libelle']}</td><td class='right'>" . number_format(abs($compte['solde']), 2, ',', ' ') . "</td></tr>";
            $passif_total += abs((float)$compte['solde']);
        }
    }
    
    // Résultat (Produits - Charges)
    $charges = isset($totaux_par_classe['6']) ? abs($totaux_par_classe['6']) : 0;
    $produits = isset($totaux_par_classe['7']) ? abs($totaux_par_classe['7']) : 0;
    $resultat = $produits - $charges;
    
    echo "<tr style='background: #d4edda;'><td colspan='2' style='font-weight: bold;'>Résultat exercice (Produits - Charges)</td><td class='right'><strong>" . number_format($resultat, 2, ',', ' ') . "</strong></td></tr>";
    $passif_total += $resultat;
    
    echo "<tr class='total'><td colspan='2'>TOTAL PASSIF + RÉSULTAT</td><td class='right'>" . number_format($passif_total, 2, ',', ' ') . "</td></tr>";
    echo "</table></div>";
    
    echo "</div>";
    
    // =========================================================================
    // 3. DIAGNOSTIC FINAL
    // =========================================================================
    echo "<h2>🎯 3. DIAGNOSTIC FINAL</h2>";
    
    $ecart = $actif_total - $passif_total;
    
    if (abs($ecart) < 0.01) {
        echo "<div class='success'>
                <h3>✅ BILAN ÉQUILIBRÉ</h3>
                <p>Actif = Passif + Résultat</p>
                <p>Le bilan est correct selon les normes SYSCOHADA.</p>
              </div>";
    } else {
        echo "<div class='error'>
                <h3>❌ BILAN NON ÉQUILIBRÉ</h3>
                <p><strong>Actif :</strong> " . number_format($actif_total, 2, ',', ' ') . " €</p>
                <p><strong>Passif + Résultat :</strong> " . number_format($passif_total, 2, ',', ' ') . " €</p>
                <p><strong>Écart :</strong> " . number_format(abs($ecart), 2, ',', ' ') . " €</p>
                <hr>
                <h4>🔍 Causes possibles :</h4>
                <ul>";
        
        // Vérifier les comptes suspects
        $problemes = [];
        
        foreach ($comptes as $compte) {
            $premier_chiffre = substr($compte['numero_compte'], 0, 1);
            if ($premier_chiffre != $compte['classe']) {
                $problemes[] = "Compte <strong>{$compte['numero_compte']}</strong> ({$compte['libelle']}) : numéro commence par {$premier_chiffre} mais classe={$compte['classe']}";
            }
        }
        
        if (count($problemes) > 0) {
            echo "<li><strong>Comptes mal classés :</strong><ul>";
            foreach ($problemes as $pb) {
                echo "<li>{$pb}</li>";
            }
            echo "</ul></li>";
        }
        
        echo "  <li>Vérifiez si toutes les pièces comptables sont validées</li>
                    <li>Vérifiez les écritures de clôture d'exercice</li>
                    <li>Utilisez le script <code>corriger_comptes_syscohada.php</code> pour corriger les classes</li>
                </ul>
              </div>";
    }
    
    echo "<p><a href='compta/balance.php' style='display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px;'>← Retour au bilan</a></p>";
    
} catch (Exception $e) {
    echo "<div class='error'><strong>Erreur :</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</div></body></html>";
