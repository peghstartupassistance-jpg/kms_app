<?php
require_once 'security.php';
global $pdo;

$date = '2024-12-15';
$stmt = $pdo->prepare("
    SELECT COUNT(*) as nb,
           COALESCE(SUM(CASE WHEN sens='RECETTE' AND est_annule=0 THEN montant ELSE 0 END),0) as rec,
           COALESCE(SUM(CASE WHEN sens='DEPENSE' AND est_annule=0 THEN montant ELSE 0 END),0) as dep
    FROM journal_caisse 
    WHERE date_operation = ?
");
$stmt->execute([$date]);
$r = $stmt->fetch();

echo "Date: $date\n";
echo "Opérations: " . $r['nb'] . "\n";
echo "Recettes: " . number_format($r['rec'], 0, ',', ' ') . " FCFA\n";
echo "Dépenses: " . number_format($r['dep'], 0, ',', ' ') . " FCFA\n";
echo "Solde: " . number_format($r['rec'] - $r['dep'], 0, ',', ' ') . " FCFA\n";
