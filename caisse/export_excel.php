<?php
// caisse/export_excel.php - Export du journal de caisse en Excel
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CAISSE_LIRE');

global $pdo;

$dateDebut = $_GET['date_debut'] ?? date('Y-m-d');
$dateFin = $_GET['date_fin'] ?? date('Y-m-d');

// Récupérer les données
$sql = "
    SELECT 
        jc.date_operation,
        jc.type_operation,
        jc.reference,
        jc.libelle,
        jc.montant,
        mp.libelle as mode_paiement,
        u.nom_complet as caissier,
        c.nom as client_nom
    FROM journal_caisse jc
    LEFT JOIN modes_paiement mp ON jc.mode_paiement_id = mp.id
    LEFT JOIN utilisateurs u ON jc.utilisateur_id = u.id
    LEFT JOIN clients c ON jc.client_id = c.id
    WHERE jc.date_operation BETWEEN :date_debut AND :date_fin
    ORDER BY jc.date_operation, jc.id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    'date_debut' => $dateDebut,
    'date_fin' => $dateFin
]);
$operations = $stmt->fetchAll();

// Calculs
$totalEncaissements = 0;
$totalDecaissements = 0;
foreach ($operations as $op) {
    if ($op['type_operation'] === 'ENCAISSEMENT') {
        $totalEncaissements += $op['montant'];
    } else {
        $totalDecaissements += $op['montant'];
    }
}
$solde = $totalEncaissements - $totalDecaissements;

// Headers pour téléchargement Excel
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="journal_caisse_' . $dateDebut . '_' . $dateFin . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF"; // BOM UTF-8
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; font-weight: bold; }
        .header { font-size: 18px; font-weight: bold; margin-bottom: 20px; }
        .total-row { background-color: #f0f0f0; font-weight: bold; }
        .encaissement { color: green; }
        .decaissement { color: red; }
    </style>
</head>
<body>
    <div class="header">
        <h1>KENNE MULTI-SERVICES</h1>
        <h2>JOURNAL DE CAISSE</h2>
        <p>Période : <?= date('d/m/Y', strtotime($dateDebut)) ?> au <?= date('d/m/Y', strtotime($dateFin)) ?></p>
        <p>Date d'export : <?= date('d/m/Y H:i') ?></p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Référence</th>
                <th>Libellé</th>
                <th>Client</th>
                <th>Mode paiement</th>
                <th>Montant</th>
                <th>Caissier</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($operations as $op): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($op['date_operation'])) ?></td>
                <td class="<?= strtolower($op['type_operation']) ?>">
                    <?= htmlspecialchars($op['type_operation']) ?>
                </td>
                <td><?= htmlspecialchars($op['reference'] ?? '') ?></td>
                <td><?= htmlspecialchars($op['libelle']) ?></td>
                <td><?= htmlspecialchars($op['client_nom'] ?? '-') ?></td>
                <td><?= htmlspecialchars($op['mode_paiement'] ?? '-') ?></td>
                <td style="text-align: right;">
                    <?= number_format($op['montant'], 0, ',', ' ') ?>
                </td>
                <td><?= htmlspecialchars($op['caissier'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($operations)): ?>
            <tr>
                <td colspan="8" style="text-align: center; color: #666;">
                    Aucune opération pour cette période
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="6"><strong>Total Encaissements</strong></td>
                <td style="text-align: right; color: green;">
                    <strong><?= number_format($totalEncaissements, 0, ',', ' ') ?> FCFA</strong>
                </td>
                <td></td>
            </tr>
            <tr class="total-row">
                <td colspan="6"><strong>Total Décaissements</strong></td>
                <td style="text-align: right; color: red;">
                    <strong><?= number_format($totalDecaissements, 0, ',', ' ') ?> FCFA</strong>
                </td>
                <td></td>
            </tr>
            <tr class="total-row">
                <td colspan="6"><strong>SOLDE NET</strong></td>
                <td style="text-align: right; font-size: 14px;">
                    <strong><?= number_format($solde, 0, ',', ' ') ?> FCFA</strong>
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
