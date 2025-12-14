<?php
// Script pour vérifier l'exactitude du bilan comptable
require_once 'lib/compta.php';

$pdo = new PDO('mysql:host=localhost;dbname=kms_gestion;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== VÉRIFICATION DU BILAN COMPTABLE ===\n\n";

// Exercice actif
$exercice = compta_get_exercice_actif($pdo);
echo "Exercice actif: " . ($exercice['annee'] ?? 'AUCUN') . "\n";
echo "ID exercice: " . ($exercice['id'] ?? 'N/A') . "\n\n";

// Balance
$balance = compta_get_balance($pdo, $exercice['id'] ?? null);
echo "Nombre de comptes avec mouvements: " . count($balance) . "\n\n";

// Analyse par classe
$totaux_par_classe = [
    1 => ['nom' => 'CAPITAUX PROPRES', 'debit' => 0, 'credit' => 0, 'solde' => 0],
    2 => ['nom' => 'IMMOBILISATIONS', 'debit' => 0, 'credit' => 0, 'solde' => 0],
    3 => ['nom' => 'STOCKS', 'debit' => 0, 'credit' => 0, 'solde' => 0],
    4 => ['nom' => 'TIERS', 'debit' => 0, 'credit' => 0, 'solde' => 0],
    5 => ['nom' => 'TRÉSORERIE', 'debit' => 0, 'credit' => 0, 'solde' => 0],
    6 => ['nom' => 'CHARGES', 'debit' => 0, 'credit' => 0, 'solde' => 0],
    7 => ['nom' => 'PRODUITS', 'debit' => 0, 'credit' => 0, 'solde' => 0],
];

echo "=== DÉTAIL DES COMPTES PAR CLASSE ===\n\n";

foreach ($balance as $ligne) {
    $classe = (int)$ligne['classe'];
    $debit = (float)($ligne['total_debit'] ?? 0);
    $credit = (float)($ligne['total_credit'] ?? 0);
    $solde = $debit - $credit;
    
    if (isset($totaux_par_classe[$classe])) {
        $totaux_par_classe[$classe]['debit'] += $debit;
        $totaux_par_classe[$classe]['credit'] += $credit;
        $totaux_par_classe[$classe]['solde'] += $solde;
    }
    
    if (abs($solde) > 100) {
        printf("  %s - %-40s | D: %12.2f | C: %12.2f | Solde: %12.2f\n",
            $ligne['numero_compte'],
            substr($ligne['libelle'], 0, 40),
            $debit,
            $credit,
            $solde
        );
    }
}

echo "\n=== TOTAUX PAR CLASSE ===\n\n";
foreach ($totaux_par_classe as $num => $classe) {
    printf("Classe %d - %-20s | D: %15.2f | C: %15.2f | Solde: %15.2f\n",
        $num,
        $classe['nom'],
        $classe['debit'],
        $classe['credit'],
        $classe['solde']
    );
}

// Calcul du bilan
echo "\n=== CALCUL DU BILAN (OHADA) ===\n\n";

$actif = 0;
$passif = 0;

// Classe 2 - Immobilisations (ACTIF si débiteur)
if ($totaux_par_classe[2]['solde'] > 0) {
    $actif += $totaux_par_classe[2]['solde'];
    printf("Immobilisations (Classe 2) : %15.2f (ACTIF)\n", $totaux_par_classe[2]['solde']);
}

// Classe 3 - Stocks (ACTIF si débiteur)
if ($totaux_par_classe[3]['solde'] > 0) {
    $actif += $totaux_par_classe[3]['solde'];
    printf("Stocks (Classe 3)          : %15.2f (ACTIF)\n", $totaux_par_classe[3]['solde']);
}

// Classe 4 - Tiers (ACTIF si débiteur, PASSIF si créditeur)
if ($totaux_par_classe[4]['solde'] > 0) {
    $actif += $totaux_par_classe[4]['solde'];
    printf("Créances (Classe 4)        : %15.2f (ACTIF)\n", $totaux_par_classe[4]['solde']);
} else {
    $passif += abs($totaux_par_classe[4]['solde']);
    printf("Dettes (Classe 4)          : %15.2f (PASSIF)\n", abs($totaux_par_classe[4]['solde']));
}

// Classe 5 - Trésorerie (ACTIF si débiteur, PASSIF si créditeur)
if ($totaux_par_classe[5]['solde'] > 0) {
    $actif += $totaux_par_classe[5]['solde'];
    printf("Trésorerie (Classe 5)      : %15.2f (ACTIF)\n", $totaux_par_classe[5]['solde']);
} else {
    $passif += abs($totaux_par_classe[5]['solde']);
    printf("Trésorerie (Classe 5)      : %15.2f (PASSIF)\n", abs($totaux_par_classe[5]['solde']));
}

// Classe 1 - Capitaux propres (toujours PASSIF en créditeur)
$passif += abs($totaux_par_classe[1]['solde']);
printf("Capitaux propres (Classe 1): %15.2f (PASSIF)\n", abs($totaux_par_classe[1]['solde']));

// Résultat
$resultat = $totaux_par_classe[7]['credit'] - $totaux_par_classe[6]['debit'];
printf("\nRésultat (Produits - Charges): %15.2f\n", $resultat);
printf("  Produits (Classe 7)         : %15.2f\n", $totaux_par_classe[7]['credit']);
printf("  Charges (Classe 6)          : %15.2f\n", $totaux_par_classe[6]['debit']);

// Ajout du résultat au passif
$passif += $resultat;

echo "\n=== ÉQUILIBRE DU BILAN ===\n\n";
printf("TOTAL ACTIF                : %15.2f\n", $actif);
printf("TOTAL PASSIF + RÉSULTAT    : %15.2f\n", $passif);
printf("DIFFÉRENCE                 : %15.2f\n", $actif - $passif);

if (abs($actif - $passif) < 0.01) {
    echo "\n✅ BILAN ÉQUILIBRÉ\n";
} else {
    echo "\n❌ BILAN DÉSÉQUILIBRÉ - ANOMALIE DÉTECTÉE\n";
}

echo "\n=== ANOMALIES POTENTIELLES ===\n\n";

// Vérifier si stocks en classe 2
$stmt = $pdo->query("
    SELECT numero_compte, libelle 
    FROM compta_plan_comptes 
    WHERE LEFT(numero_compte,1) = '2' 
    AND (libelle LIKE '%stock%' OR libelle LIKE '%marchandise%')
");
$stocks_mal_classes = $stmt->fetchAll();
if (count($stocks_mal_classes) > 0) {
    echo "⚠️ STOCKS TROUVÉS EN CLASSE 2 (devrait être classe 3) :\n";
    foreach ($stocks_mal_classes as $c) {
        echo "  - " . $c['numero_compte'] . " : " . $c['libelle'] . "\n";
    }
    echo "\n";
}

// Vérifier si caisse en passif
$stmt = $pdo->query("
    SELECT cel.compte_id, cpc.numero_compte, cpc.libelle, 
           SUM(cel.montant_debit) as debit, 
           SUM(cel.montant_credit) as credit
    FROM compta_ecriture_lignes cel
    JOIN compta_plan_comptes cpc ON cel.compte_id = cpc.id
    WHERE cpc.numero_compte LIKE '57%'
    GROUP BY cel.compte_id, cpc.numero_compte, cpc.libelle
");
$caisses = $stmt->fetchAll();
foreach ($caisses as $c) {
    $solde = $c['debit'] - $c['credit'];
    if ($solde < 0) {
        echo "⚠️ CAISSE EN CRÉDIT (anormal) : " . $c['numero_compte'] . " - " . $c['libelle'] . " : " . number_format($solde, 2) . "\n";
    }
}
