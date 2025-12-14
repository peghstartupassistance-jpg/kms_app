<?php
require_once __DIR__ . '/db/db.php';
global $pdo;

echo "=== VÉRIFICATION FINALE DU BILAN ÉQUILIBRÉ ===\n\n";

// Récupérer exercice actif
$stmt = $pdo->query("SELECT id, annee FROM compta_exercices WHERE est_clos = 0 ORDER BY annee DESC LIMIT 1");
$ex = $stmt->fetch();
$exercice_id = $ex['id'];
echo "Exercice: {$ex['annee']} (ID: {$exercice_id})\n\n";

// Totaux VALIDÉES
$stmt = $pdo->prepare("
    SELECT 
        SUM(ce.debit) as total_deb,
        SUM(ce.credit) as total_cre
    FROM compta_ecritures ce
    JOIN compta_pieces cp ON ce.piece_id = cp.id
    WHERE cp.exercice_id = ? AND cp.est_validee = 1
");
$stmt->execute([$exercice_id]);
$verif = $stmt->fetch();

echo "TOUTES LES ÉCRITURES VALIDÉES:\n";
echo sprintf("  Débits:  %15s FCFA\n", number_format($verif['total_deb'], 0, ',', ' '));
echo sprintf("  Crédits: %15s FCFA\n", number_format($verif['total_cre'], 0, ',', ' '));
echo sprintf("  Écart:   %15s FCFA\n", number_format($verif['total_deb'] - $verif['total_cre'], 0, ',', ' '));

// Par classe
echo "\n\nBALANCE PAR CLASSE (Débits - Crédits):\n";
$stmt = $pdo->prepare("
    SELECT cc.classe, SUM(ce.debit) as deb, SUM(ce.credit) as cre
    FROM compta_ecritures ce
    JOIN compta_comptes cc ON ce.compte_id = cc.id
    JOIN compta_pieces cp ON ce.piece_id = cp.id
    WHERE cp.exercice_id = ? AND cp.est_validee = 1
    GROUP BY cc.classe
    ORDER BY cc.classe
");
$stmt->execute([$exercice_id]);
$classes = $stmt->fetchAll();

$actif_total = 0;
$passif_total = 0;

foreach ($classes as $c) {
    $solde = $c['deb'] - $c['cre'];
    echo sprintf("Classe %s: %15s (D:%12s C:%12s)\n", 
        $c['classe'], number_format($solde, 0, ',', ' '),
        number_format($c['deb'], 0, ',', ' '),
        number_format($c['cre'], 0, ',', ' ')
    );
    
    // Classification
    if ($c['classe'] == '1') {
        $passif_total += abs($solde);
    } elseif ($c['classe'] == '2' || $c['classe'] == '3') {
        $actif_total += max(0, $solde);
    } elseif ($c['classe'] == '4') {
        if ($solde > 0) $actif_total += $solde;
        else $passif_total += abs($solde);
    } elseif ($c['classe'] == '5') {
        if ($solde > 0) $actif_total += $solde;
        else $passif_total += abs($solde);
    }
}

$resultat = 0;
foreach ($classes as $c) {
    $solde = $c['deb'] - $c['cre'];
    if ($c['classe'] == '6') $resultat -= $solde;  // Charges
    if ($c['classe'] == '7') $resultat += abs($solde);  // Produits (négatif)
}

echo "\n\nÉQUATION OHADA:\n";
echo sprintf("ACTIF:    %15s FCFA\n", number_format($actif_total, 0, ',', ' '));
echo sprintf("PASSIF:   %15s FCFA\n", number_format($passif_total, 0, ',', ' '));
echo sprintf("RÉSULTAT: %15s FCFA\n", number_format($resultat, 0, ',', ' '));
echo sprintf("─────────────────────────\n");
echo sprintf("P + R :   %15s FCFA\n", number_format($passif_total + $resultat, 0, ',', ' '));
echo sprintf("ÉCART:    %15s FCFA\n", number_format($actif_total - ($passif_total + $resultat), 0, ',', ' '));

if (abs($actif_total - ($passif_total + $resultat)) < 1) {
    echo "\n✅ BILAN ÉQUILIBRÉ\n";
} else {
    echo "\n❌ BILAN DÉSÉQUILIBRÉ\n";
}
