<?php
// compta/export_balance.php - Export CSV de la balance comptable
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('COMPTABILITE_LIRE');

global $pdo;

$exercice_id = (int)($_GET['exercice_id'] ?? 0);

// Récupérer l'exercice
if ($exercice_id) {
    $stmt = $pdo->prepare("SELECT id, code, date_debut, date_fin, est_actif FROM compta_exercices WHERE id = ?");
    $stmt->execute([$exercice_id]);
    $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Exercice actif par défaut
    $stmt = $pdo->query("SELECT id, code, date_debut, date_fin, est_actif FROM compta_exercices WHERE est_actif = 1 LIMIT 1");
    $exercice = $stmt->fetch(PDO::FETCH_ASSOC);
    $exercice_id = $exercice['id'] ?? 0;
}

if (!$exercice) {
    die('Aucun exercice sélectionné');
}

// Requête balance
$sql = "
    SELECT 
        c.numero_compte,
        c.libelle,
        c.type_compte,
        c.classe,
        COALESCE(SUM(CASE WHEN e.debit > 0 THEN e.debit ELSE 0 END), 0) as total_debit,
        COALESCE(SUM(CASE WHEN e.credit > 0 THEN e.credit ELSE 0 END), 0) as total_credit
    FROM compta_comptes c
    LEFT JOIN compta_ecritures e ON e.compte_id = c.id
    LEFT JOIN compta_pieces p ON e.piece_id = p.id
    WHERE p.exercice_id = ? AND p.est_validee = 1
    GROUP BY c.id, c.numero_compte, c.libelle, c.type_compte, c.classe
    HAVING total_debit > 0 OR total_credit > 0
    ORDER BY c.numero_compte
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$exercice_id]);
$comptes = $stmt->fetchAll();

// Calculer totaux
$total_debit = 0;
$total_credit = 0;
$total_solde_debiteur = 0;
$total_solde_crediteur = 0;

foreach ($comptes as $compte) {
    $total_debit += $compte['total_debit'];
    $total_credit += $compte['total_credit'];
    
    $solde = $compte['total_debit'] - $compte['total_credit'];
    if ($solde > 0) {
        $total_solde_debiteur += $solde;
    } else {
        $total_solde_crediteur += abs($solde);
    }
}

// Générer le CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="balance_' . $exercice['code'] . '_' . date('Y-m-d_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM UTF-8
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// En-tête
fputcsv($output, ['BALANCE GÉNÉRALE - KENNE MULTI-SERVICES'], ';');
fputcsv($output, ['Exercice: ' . $exercice['code'] . ' (' . date('d/m/Y', strtotime($exercice['date_debut'])) . ' - ' . date('d/m/Y', strtotime($exercice['date_fin'])) . ')'], ';');
fputcsv($output, ['Édité le ' . date('d/m/Y H:i')], ';');
fputcsv($output, [], ';');

// Colonnes
fputcsv($output, [
    'N° Compte',
    'Libellé',
    'Type',
    'Classe',
    'Total Débit',
    'Total Crédit',
    'Solde Débiteur',
    'Solde Créditeur'
], ';');

// Données
foreach ($comptes as $compte) {
    $solde = $compte['total_debit'] - $compte['total_credit'];
    $solde_debiteur = $solde > 0 ? number_format($solde, 2, ',', ' ') : '';
    $solde_crediteur = $solde < 0 ? number_format(abs($solde), 2, ',', ' ') : '';
    
    fputcsv($output, [
        $compte['numero_compte'],
        $compte['libelle'],
        $compte['type_compte'],
        'Classe ' . $compte['classe'],
        number_format($compte['total_debit'], 2, ',', ' '),
        number_format($compte['total_credit'], 2, ',', ' '),
        $solde_debiteur,
        $solde_crediteur
    ], ';');
}

// Totaux
fputcsv($output, [], ';');
fputcsv($output, [
    '',
    'TOTAUX',
    '',
    '',
    number_format($total_debit, 2, ',', ' ') . ' FCFA',
    number_format($total_credit, 2, ',', ' ') . ' FCFA',
    number_format($total_solde_debiteur, 2, ',', ' ') . ' FCFA',
    number_format($total_solde_crediteur, 2, ',', ' ') . ' FCFA'
], ';');

fputcsv($output, [], ';');
fputcsv($output, ['Balance équilibrée: ' . ($total_debit == $total_credit ? 'OUI' : 'NON - ÉCART: ' . number_format(abs($total_debit - $total_credit), 2, ',', ' ') . ' FCFA')], ';');

fclose($output);
exit;
