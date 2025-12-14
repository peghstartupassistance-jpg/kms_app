<?php
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/lib/compta.php';

global $pdo;

// Vérifier exercice actif
$stmt = $pdo->query("SELECT * FROM compta_exercices WHERE est_clos = 0 ORDER BY annee DESC LIMIT 1");
$exercice = $stmt->fetch();
echo "=== EXERCICE ACTIF ===\n";
if ($exercice) {
    echo "ID: {$exercice['id']}, Année: {$exercice['annee']}, Cloturé: {$exercice['est_clos']}\n\n";
    $exercice_id = $exercice['id'];
} else {
    echo "AUCUN EXERCICE ACTIF!\n\n";
    exit(1);
}

// Vérifier les pièces
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total, 
        SUM(CASE WHEN est_validee = 1 THEN 1 ELSE 0 END) as validees,
        SUM(CASE WHEN est_validee = 0 THEN 1 ELSE 0 END) as non_validees
    FROM compta_pieces 
    WHERE exercice_id = ?
");
$stmt->execute([$exercice_id]);
$pieces = $stmt->fetch();
echo "=== PIÈCES COMPTABLES ===\n";
echo "Total: {$pieces['total']}, Validées: {$pieces['validees']}, En attente: {$pieces['non_validees']}\n\n";

// Vérifier les écritures
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(debit) as total_debit,
        SUM(credit) as total_credit
    FROM compta_ecritures ce
    JOIN compta_pieces cp ON ce.piece_id = cp.id
    WHERE cp.exercice_id = ?
");
$stmt->execute([$exercice_id]);
$ecr = $stmt->fetch();
echo "=== ÉCRITURES (TOUTES) ===\n";
echo sprintf("Total: %s écritures\n", $ecr['total']);
echo sprintf("Débit:  %s\n", number_format($ecr['total_debit'], 0, ',', ' '));
echo sprintf("Crédit: %s\n\n", number_format($ecr['total_credit'], 0, ',', ' '));

// Vérifier écritures VALIDÉES
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(debit) as total_debit,
        SUM(credit) as total_credit
    FROM compta_ecritures ce
    JOIN compta_pieces cp ON ce.piece_id = cp.id
    WHERE cp.exercice_id = ? AND cp.est_validee = 1
");
$stmt->execute([$exercice_id]);
$ecr_val = $stmt->fetch();
echo "=== ÉCRITURES VALIDÉES ===\n";
echo sprintf("Total: %s écritures\n", $ecr_val['total']);
echo sprintf("Débit:  %s\n", number_format($ecr_val['total_debit'], 0, ',', ' '));
echo sprintf("Crédit: %s\n\n", number_format($ecr_val['total_credit'], 0, ',', ' '));

// Test fonction compta_get_balance
echo "=== TEST compta_get_balance() ===\n";
$balance = compta_get_balance($pdo, $exercice_id);
echo "Nombre de lignes retournées: " . count($balance) . "\n\n";

// Calculer les totaux par classe
$totaux = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, '6' => 0, '7' => 0];
foreach ($balance as $ligne) {
    $solde = $ligne['total_debit'] - $ligne['total_credit'];
    $classe = $ligne['classe'];
    if (isset($totaux[$classe])) {
        $totaux[$classe] += $solde;
    }
}

echo "=== TOTAUX PAR CLASSE (soldes) ===\n";
foreach ($totaux as $c => $v) {
    echo sprintf("Classe %s: %15s\n", $c, number_format($v, 0, ',', ' '));
}

// Calculer le bilan OHADA
$actif = 0;
$passif = 0;

// Classe 2 : Immobilisations (ACTIF)
if ($totaux['2'] > 0) $actif += $totaux['2'];

// Classe 3 : Stocks (ACTIF)
if ($totaux['3'] > 0) $actif += $totaux['3'];

// Classe 4 : Tiers (ACTIF si débiteur, PASSIF si créditeur)
if ($totaux['4'] > 0) {
    $actif += $totaux['4'];
} else {
    $passif += abs($totaux['4']);
}

// Classe 5 : Trésorerie (ACTIF si débiteur, PASSIF si créditeur)
if ($totaux['5'] > 0) {
    $actif += $totaux['5'];
} else {
    $passif += abs($totaux['5']);
}

// Classe 1 : Capitaux propres (PASSIF)
$passif += abs($totaux['1']);

// Résultat = Produits - Charges
$resultat = $totaux['7'] - $totaux['6'];

echo "\n=== BILAN OHADA CAMEROUN ===\n";
echo sprintf("ACTIF (2+3+4+5):  %15s\n", number_format($actif, 0, ',', ' '));
echo sprintf("PASSIF (1+4+5):   %15s\n", number_format($passif, 0, ',', ' '));
echo sprintf("RÉSULTAT (7-6):   %15s\n", number_format($resultat, 0, ',', ' '));
echo sprintf("ÉCART (A-P-R):    %15s (devrait être 0)\n", number_format($actif - ($passif + $resultat), 0, ',', ' '));

// Détail des comptes significatifs
echo "\n=== COMPTES PRINCIPAUX ===\n";
foreach ($balance as $ligne) {
    $solde = $ligne['total_debit'] - $ligne['total_credit'];
    if (abs($solde) > 100000) {
        echo sprintf("Compte %6s (Cl.%s): %12s (D:%10s C:%10s)\n", 
            $ligne['numero_compte'], 
            $ligne['classe'],
            number_format($solde, 0, ',', ' '),
            number_format($ligne['total_debit'], 0, ',', ' '),
            number_format($ligne['total_credit'], 0, ',', ' ')
        );
    }
}
