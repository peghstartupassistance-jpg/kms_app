<?php
// caisse/export_journal.php - Export CSV du journal de caisse
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CAISSE_LIRE');

global $pdo;

// Récupérer les filtres
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$type = $_GET['type'] ?? '';
$mode_paiement = $_GET['mode_paiement'] ?? '';

// Construire la requête
$sql = "
    SELECT 
        jc.date_operation,
        jc.type_operation,
        jc.montant,
        jc.description,
        mp.libelle as mode_paiement,
        c.nom as client_nom,
        v.numero as vente_numero,
        u.nom_complet as caissier
    FROM journal_caisse jc
    LEFT JOIN modes_paiement mp ON jc.mode_paiement_id = mp.id
    LEFT JOIN clients c ON jc.client_id = c.id
    LEFT JOIN ventes v ON jc.vente_id = v.id
    LEFT JOIN utilisateurs u ON jc.utilisateur_id = u.id
    WHERE jc.date_operation BETWEEN ? AND ?
";

$params = [$date_debut, $date_fin];

if ($type) {
    $sql .= " AND jc.type_operation = ?";
    $params[] = $type;
}

if ($mode_paiement) {
    $sql .= " AND jc.mode_paiement_id = ?";
    $params[] = $mode_paiement;
}

$sql .= " ORDER BY jc.date_operation DESC, jc.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$operations = $stmt->fetchAll();

// Calculer les totaux
$total_encaissements = 0;
$total_decaissements = 0;
foreach ($operations as $op) {
    if ($op['type_operation'] === 'ENCAISSEMENT') {
        $total_encaissements += $op['montant'];
    } else {
        $total_decaissements += $op['montant'];
    }
}
$solde = $total_encaissements - $total_decaissements;

// Générer le CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="journal_caisse_' . date('Y-m-d_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM UTF-8 pour Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// En-tête du fichier
fputcsv($output, ['JOURNAL DE CAISSE - KENNE MULTI-SERVICES'], ';');
fputcsv($output, ['Période du ' . date('d/m/Y', strtotime($date_debut)) . ' au ' . date('d/m/Y', strtotime($date_fin))], ';');
fputcsv($output, ['Édité le ' . date('d/m/Y H:i')], ';');
fputcsv($output, [], ';'); // Ligne vide

// En-têtes colonnes
fputcsv($output, [
    'Date',
    'Type',
    'Description',
    'Mode paiement',
    'Client',
    'N° Vente',
    'Caissier',
    'Encaissement',
    'Décaissement',
    'Solde cumulé'
], ';');

// Données
$solde_cumule = 0;
foreach ($operations as $op) {
    $encaissement = '';
    $decaissement = '';
    
    if ($op['type_operation'] === 'ENCAISSEMENT') {
        $encaissement = number_format($op['montant'], 0, ',', ' ');
        $solde_cumule += $op['montant'];
    } else {
        $decaissement = number_format($op['montant'], 0, ',', ' ');
        $solde_cumule -= $op['montant'];
    }
    
    fputcsv($output, [
        date('d/m/Y H:i', strtotime($op['date_operation'])),
        $op['type_operation'],
        $op['description'] ?? '',
        $op['mode_paiement'] ?? '',
        $op['client_nom'] ?? '',
        $op['vente_numero'] ?? '',
        $op['caissier'] ?? '',
        $encaissement,
        $decaissement,
        number_format($solde_cumule, 0, ',', ' ')
    ], ';');
}

// Ligne de séparation
fputcsv($output, [], ';');

// Totaux
fputcsv($output, [
    '',
    '',
    '',
    '',
    '',
    '',
    'TOTAUX:',
    number_format($total_encaissements, 0, ',', ' ') . ' FCFA',
    number_format($total_decaissements, 0, ',', ' ') . ' FCFA',
    number_format($solde, 0, ',', ' ') . ' FCFA'
], ';');

fputcsv($output, [], ';');
fputcsv($output, ['Solde de caisse:', number_format($solde, 0, ',', ' ') . ' FCFA'], ';');

fclose($output);
exit;
