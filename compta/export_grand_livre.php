<?php
// compta/export_grand_livre.php - Export CSV du grand livre
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('COMPTABILITE_LIRE');

global $pdo;

$compte_id = (int)($_GET['compte_id'] ?? 0);
$exercice_id = (int)($_GET['exercice_id'] ?? 0);

if (!$compte_id) {
    die('Compte non spécifié');
}

// Récupérer le compte
$stmt = $pdo->prepare("SELECT * FROM compta_comptes WHERE id = ?");
$stmt->execute([$compte_id]);
$compte = $stmt->fetch();

if (!$compte) {
    die('Compte introuvable');
}

// Récupérer l'exercice
if ($exercice_id) {
    $stmt = $pdo->prepare("SELECT * FROM compta_exercices WHERE id = ?");
    $stmt->execute([$exercice_id]);
    $exercice = $stmt->fetch();
} else {
    $stmt = $pdo->query("SELECT * FROM compta_exercices WHERE est_actif = 1 LIMIT 1");
    $exercice = $stmt->fetch();
    $exercice_id = $exercice['id'] ?? 0;
}

// Récupérer les écritures
$sql = "
    SELECT 
        p.date_piece,
        p.numero_piece,
        j.code as journal_code,
        j.libelle as journal_libelle,
        e.libelle,
        e.debit,
        e.credit
    FROM compta_ecritures e
    JOIN compta_pieces p ON e.piece_id = p.id
    JOIN compta_journaux j ON p.journal_id = j.id
    WHERE e.compte_id = ? AND p.exercice_id = ? AND p.est_validee = 1
    ORDER BY p.date_piece, p.numero_piece
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$compte_id, $exercice_id]);
$ecritures = $stmt->fetchAll();

// Calculer solde
$solde = 0;
$total_debit = 0;
$total_credit = 0;

foreach ($ecritures as $ecriture) {
    $total_debit += $ecriture['debit'];
    $total_credit += $ecriture['credit'];
}

$solde = $total_debit - $total_credit;

// Générer CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="grand_livre_' . $compte['numero'] . '_' . date('Y-m-d_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// En-tête
fputcsv($output, ['GRAND LIVRE - KENNE MULTI-SERVICES'], ';');
fputcsv($output, ['Compte: ' . $compte['numero'] . ' - ' . $compte['libelle']], ';');
fputcsv($output, ['Exercice: ' . ($exercice['code'] ?? 'N/A')], ';');
fputcsv($output, ['Édité le ' . date('d/m/Y H:i')], ';');
fputcsv($output, [], ';');

// Colonnes
fputcsv($output, [
    'Date',
    'N° Pièce',
    'Journal',
    'Libellé',
    'Débit',
    'Crédit',
    'Solde'
], ';');

// Écritures
$solde_cumule = 0;
foreach ($ecritures as $ecriture) {
    $solde_cumule += $ecriture['debit'] - $ecriture['credit'];
    
    fputcsv($output, [
        date('d/m/Y', strtotime($ecriture['date_piece'])),
        $ecriture['numero_piece'],
        $ecriture['journal_code'],
        $ecriture['libelle'],
        $ecriture['debit'] > 0 ? number_format($ecriture['debit'], 2, ',', ' ') : '',
        $ecriture['credit'] > 0 ? number_format($ecriture['credit'], 2, ',', ' ') : '',
        number_format($solde_cumule, 2, ',', ' ')
    ], ';');
}

// Totaux
fputcsv($output, [], ';');
fputcsv($output, [
    '',
    '',
    '',
    'TOTAUX',
    number_format($total_debit, 2, ',', ' ') . ' FCFA',
    number_format($total_credit, 2, ',', ' ') . ' FCFA',
    number_format($solde, 2, ',', ' ') . ' FCFA'
], ';');

fputcsv($output, [], ';');
fputcsv($output, ['Solde: ' . number_format($solde, 2, ',', ' ') . ' FCFA (' . ($solde >= 0 ? 'Débiteur' : 'Créditeur') . ')'], ';');

fclose($output);
exit;
