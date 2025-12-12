<?php
// compta/export_bilan.php - Export du bilan comptable en Excel
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('COMPTABILITE_LIRE');

global $pdo;

$exerciceId = isset($_GET['exercice_id']) ? (int)$_GET['exercice_id'] : 0;

// Récupérer l'exercice actif ou celui demandé
if ($exerciceId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM compta_exercices WHERE id = ?");
    $stmt->execute([$exerciceId]);
    $exercice = $stmt->fetch();
} else {
    $stmt = $pdo->query("SELECT * FROM compta_exercices WHERE est_actif = 1 LIMIT 1");
    $exercice = $stmt->fetch();
}

if (!$exercice) {
    die("Aucun exercice trouvé");
}

// Récupérer le bilan
$sql = "
    SELECT 
        cc.numero_compte,
        cc.libelle,
        cc.classe,
        COALESCE(SUM(CASE WHEN ce.debit > 0 THEN ce.debit ELSE 0 END), 0) as total_debit,
        COALESCE(SUM(CASE WHEN ce.credit > 0 THEN ce.credit ELSE 0 END), 0) as total_credit
    FROM compta_comptes cc
    LEFT JOIN compta_ecritures ce ON ce.compte_id = cc.id
    LEFT JOIN compta_pieces cp ON cp.id = ce.piece_id AND cp.est_validee = 1
    WHERE cc.classe IN ('1', '2', '3', '4', '5')
    GROUP BY cc.id, cc.numero_compte, cc.libelle, cc.classe
    HAVING (total_debit - total_credit) != 0
    ORDER BY cc.numero_compte
";
$stmt = $pdo->query($sql);
$comptes = $stmt->fetchAll();

// Organiser par classe
$actif = [];
$passif = [];

foreach ($comptes as $compte) {
    $solde = $compte['total_debit'] - $compte['total_credit'];
    $compte['solde'] = abs($solde);
    
    // Classe 1 = Capitaux propres (Passif)
    // Classe 2 = Immobilisations (Actif)
    // Classe 3 = Stocks (Actif)
    // Classe 4 = Tiers (Actif si débiteur, Passif si créditeur)
    // Classe 5 = Trésorerie (Actif si débiteur, Passif si créditeur)
    
    if ($compte['classe'] == '1') {
        $passif[] = $compte;
    } elseif (in_array($compte['classe'], ['2', '3'])) {
        $actif[] = $compte;
    } elseif (in_array($compte['classe'], ['4', '5'])) {
        if ($solde >= 0) {
            $actif[] = $compte;
        } else {
            $passif[] = $compte;
        }
    }
}

$totalActif = array_sum(array_column($actif, 'solde'));
$totalPassif = array_sum(array_column($passif, 'solde'));

// Headers pour téléchargement Excel
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="bilan_comptable_' . $exercice['annee'] . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF"; // BOM UTF-8
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #2c3e50; color: white; font-weight: bold; }
        .header { font-size: 18px; font-weight: bold; margin-bottom: 20px; text-align: center; }
        .section-title { background-color: #34495e; color: white; font-weight: bold; font-size: 14px; }
        .total-row { background-color: #3498db; color: white; font-weight: bold; font-size: 13px; }
        .numero { width: 15%; }
        .libelle { width: 60%; }
        .montant { width: 25%; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>KENNE MULTI-SERVICES</h1>
        <h2>BILAN COMPTABLE</h2>
        <h3>Exercice : <?= htmlspecialchars($exercice['annee']) ?></h3>
        <p>Du <?= date('d/m/Y', strtotime($exercice['date_debut'])) ?> 
           au <?= date('d/m/Y', strtotime($exercice['date_fin'])) ?></p>
        <p>Date d'export : <?= date('d/m/Y H:i') ?></p>
    </div>
    
    <table>
        <tr>
            <th colspan="3" style="text-align: center; font-size: 16px;">ACTIF</th>
        </tr>
        <tr>
            <th class="numero">N° Compte</th>
            <th class="libelle">Libellé</th>
            <th class="montant">Montant (FCFA)</th>
        </tr>
        
        <?php 
        $currentClasse = '';
        foreach ($actif as $compte): 
            if ($currentClasse != $compte['classe']) {
                $currentClasse = $compte['classe'];
                $classeLib = [
                    '2' => 'IMMOBILISATIONS',
                    '3' => 'STOCKS',
                    '4' => 'CRÉANCES',
                    '5' => 'TRÉSORERIE - ACTIF'
                ];
        ?>
        <tr class="section-title">
            <td colspan="3">CLASSE <?= $currentClasse ?> - <?= $classeLib[$currentClasse] ?? '' ?></td>
        </tr>
        <?php } ?>
        <tr>
            <td><?= htmlspecialchars($compte['numero_compte']) ?></td>
            <td><?= htmlspecialchars($compte['libelle']) ?></td>
            <td class="montant"><?= number_format($compte['solde'], 0, ',', ' ') ?></td>
        </tr>
        <?php endforeach; ?>
        
        <?php if (empty($actif)): ?>
        <tr>
            <td colspan="3" style="text-align: center; color: #666;">Aucun compte à l'actif</td>
        </tr>
        <?php endif; ?>
        
        <tr class="total-row">
            <td colspan="2"><strong>TOTAL ACTIF</strong></td>
            <td class="montant"><strong><?= number_format($totalActif, 0, ',', ' ') ?></strong></td>
        </tr>
    </table>
    
    <table>
        <tr>
            <th colspan="3" style="text-align: center; font-size: 16px;">PASSIF</th>
        </tr>
        <tr>
            <th class="numero">N° Compte</th>
            <th class="libelle">Libellé</th>
            <th class="montant">Montant (FCFA)</th>
        </tr>
        
        <?php 
        $currentClasse = '';
        foreach ($passif as $compte): 
            if ($currentClasse != $compte['classe']) {
                $currentClasse = $compte['classe'];
                $classeLib = [
                    '1' => 'CAPITAUX PROPRES',
                    '4' => 'DETTES',
                    '5' => 'TRÉSORERIE - PASSIF'
                ];
        ?>
        <tr class="section-title">
            <td colspan="3">CLASSE <?= $currentClasse ?> - <?= $classeLib[$currentClasse] ?? '' ?></td>
        </tr>
        <?php } ?>
        <tr>
            <td><?= htmlspecialchars($compte['numero_compte']) ?></td>
            <td><?= htmlspecialchars($compte['libelle']) ?></td>
            <td class="montant"><?= number_format($compte['solde'], 0, ',', ' ') ?></td>
        </tr>
        <?php endforeach; ?>
        
        <?php if (empty($passif)): ?>
        <tr>
            <td colspan="3" style="text-align: center; color: #666;">Aucun compte au passif</td>
        </tr>
        <?php endif; ?>
        
        <tr class="total-row">
            <td colspan="2"><strong>TOTAL PASSIF</strong></td>
            <td class="montant"><strong><?= number_format($totalPassif, 0, ',', ' ') ?></strong></td>
        </tr>
    </table>
    
    <table style="margin-top: 20px;">
        <tr style="background-color: <?= abs($totalActif - $totalPassif) < 1 ? '#27ae60' : '#e74c3c' ?>; color: white;">
            <td colspan="2" style="font-weight: bold; font-size: 14px;">ÉQUILIBRE DU BILAN</td>
            <td class="montant" style="font-weight: bold; font-size: 14px;">
                Écart : <?= number_format(abs($totalActif - $totalPassif), 0, ',', ' ') ?> FCFA
            </td>
        </tr>
        <tr>
            <td colspan="3" style="text-align: center; font-style: italic; color: #666;">
                <?= abs($totalActif - $totalPassif) < 1 ? '✓ Bilan équilibré' : '⚠ Bilan non équilibré' ?>
            </td>
        </tr>
    </table>
</body>
</html>
