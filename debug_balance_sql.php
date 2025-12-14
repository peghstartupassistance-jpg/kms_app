<?php
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/lib/compta.php';
global $pdo;

echo "=== COMPARAISON BALANCE vs BILAN ===\n\n";

// Récupérer exercice actif
$stmt = $pdo->query("SELECT id FROM compta_exercices WHERE est_clos = 0 ORDER BY annee DESC LIMIT 1");
$ex = $stmt->fetch();
$exercice_id = $ex['id'];

// Méthode 1 : compta_get_balance()
echo "MÉTHODE 1: compta_get_balance() (utilisée par analyse_corrections.php)\n";
echo "═════════════════════════════════════════════════════\n";
$balance = compta_get_balance($pdo, $exercice_id);
$totaux1 = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, '6' => 0, '7' => 0];
foreach ($balance as $ligne) {
    $solde = $ligne['total_debit'] - $ligne['total_credit'];
    $classe = $ligne['classe'];
    if (isset($totaux1[$classe])) {
        $totaux1[$classe] += $solde;
    }
}
foreach ($totaux1 as $c => $v) {
    echo sprintf("Classe %s: %15s\n", $c, number_format($v, 0, ',', ' '));
}

// Méthode 2 : Requête SQL directe
echo "\nMÉTHODE 2: Requête SQL directe (lecture de la balance brute)\n";
echo "═════════════════════════════════════════════════════\n";
$stmt = $pdo->prepare("
    SELECT cc.classe, SUM(ce.debit) as total_debit, SUM(ce.credit) as total_credit
    FROM compta_ecritures ce
    JOIN compta_comptes cc ON ce.compte_id = cc.id
    JOIN compta_pieces cp ON ce.piece_id = cp.id
    WHERE cp.exercice_id = ? AND cp.est_validee = 1
    GROUP BY cc.classe
");
$stmt->execute([$exercice_id]);
$totaux2 = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, '6' => 0, '7' => 0];
foreach ($stmt->fetchAll() as $ligne) {
    $solde = $ligne['total_debit'] - $ligne['total_credit'];
    $classe = $ligne['classe'];
    if (isset($totaux2[$classe])) {
        $totaux2[$classe] += $solde;
    }
}
foreach ($totaux2 as $c => $v) {
    echo sprintf("Classe %s: %15s\n", $c, number_format($v, 0, ',', ' '));
}

// Comparaison détail Classe 1
echo "\nDÉTAIL CLASSE 1 (Capitaux propres)\n";
echo "═════════════════════════════════════════════════════\n";
$stmt = $pdo->prepare("
    SELECT cc.numero_compte, cc.libelle, SUM(ce.debit) as td, SUM(ce.credit) as tc
    FROM compta_ecritures ce
    JOIN compta_comptes cc ON ce.compte_id = cc.id
    JOIN compta_pieces cp ON ce.piece_id = cp.id
    WHERE cp.exercice_id = ? AND cp.est_validee = 1 AND cc.classe = '1'
    GROUP BY cc.id, cc.numero_compte, cc.libelle
");
$stmt->execute([$exercice_id]);
$comptes_c1 = $stmt->fetchAll();
echo "Comptes de la classe 1:\n";
foreach ($comptes_c1 as $c) {
    $solde = $c['td'] - $c['tc'];
    echo sprintf("  %6s %-30s: %15s (D:%10s C:%10s)\n", 
        $c['numero_compte'], 
        substr($c['libelle'], 0, 28),
        number_format($solde, 0, ',', ' '),
        number_format($c['td'], 0, ',', ' '),
        number_format($c['tc'], 0, ',', ' ')
    );
}
$total_c1 = array_reduce($comptes_c1, function($carry, $item) {
    return $carry + ($item['td'] - $item['tc']);
}, 0);
echo sprintf("TOTAL Classe 1: %15s\n", number_format($total_c1, 0, ',', ' '));

echo "\n=== DIFFÉRENCES ===\n";
for ($i = 1; $i <= 7; $i++) {
    $diff = $totaux2[$i] - $totaux1[$i];
    if ($diff != 0) {
        echo sprintf("Classe %s: Différence de %15s\n", $i, number_format($diff, 0, ',', ' '));
    }
}
