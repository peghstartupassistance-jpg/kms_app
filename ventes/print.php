<?php
// ventes/print.php - Vue d'impression pour facture de vente
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    die('ID vente manquant');
}

// Charger la vente
$stmt = $pdo->prepare("
    SELECT v.*, c.nom as client_nom, c.telephone, c.email, c.adresse,
           cv.libelle as canal_libelle, u.nom_complet as commercial
    FROM ventes v
    LEFT JOIN clients c ON v.client_id = c.id
    LEFT JOIN canaux_vente cv ON v.canal_vente_id = cv.id
    LEFT JOIN utilisateurs u ON v.utilisateur_id = u.id
    WHERE v.id = ?
");
$stmt->execute([$id]);
$vente = $stmt->fetch();

if (!$vente) {
    die('Vente introuvable');
}

// Charger les lignes
$stmt = $pdo->prepare("
    SELECT vl.*, p.code_produit, p.designation
    FROM ventes_lignes vl
    JOIN produits p ON vl.produit_id = p.id
    WHERE vl.vente_id = ?
    ORDER BY vl.id
");
$stmt->execute([$id]);
$lignes = $stmt->fetchAll();

// Vérifier si la vente est payée
$stmt = $pdo->prepare("
    SELECT SUM(montant) as total_paye
    FROM journal_caisse
    WHERE vente_id = ? AND sens = 'RECETTE' AND est_annule = 0
");
$stmt->execute([$id]);
$paiement = $stmt->fetch();
$montant_paye = $paiement['total_paye'] ?? 0;
$solde_du = $vente['montant_total_ttc'] - $montant_paye;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?= htmlspecialchars($vente['numero']) ?></title>
    <style>
        @page {
            margin: 1cm;
            size: A4;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #27ae60;
        }
        
        .company-info h1 {
            font-size: 24pt;
            color: #27ae60;
            margin-bottom: 5px;
        }
        
        .company-info p {
            font-size: 9pt;
            color: #666;
            margin: 2px 0;
        }
        
        .doc-info {
            text-align: right;
        }
        
        .doc-type {
            font-size: 18pt;
            font-weight: bold;
            color: #27ae60;
            margin-bottom: 10px;
        }
        
        .doc-number {
            font-size: 14pt;
            color: #e74c3c;
            margin-bottom: 5px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 9pt;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .status-VALIDEE { background: #d4edda; color: #155724; }
        .status-BROUILLON { background: #fff3cd; color: #856404; }
        .status-ANNULEE { background: #f8d7da; color: #721c24; }
        
        .client-section {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #27ae60;
        }
        
        .client-section h3 {
            font-size: 11pt;
            color: #27ae60;
            margin-bottom: 10px;
        }
        
        .client-section p {
            margin: 3px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        table thead {
            background: #27ae60;
            color: white;
        }
        
        table th {
            padding: 10px;
            text-align: left;
            font-size: 10pt;
            font-weight: 600;
        }
        
        table th.text-right {
            text-align: right;
        }
        
        table td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
        }
        
        table td.text-right {
            text-align: right;
        }
        
        table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .totals {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
        }
        
        .totals-box {
            width: 300px;
            border: 2px solid #27ae60;
        }
        
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .totals-row:last-child {
            border-bottom: none;
            background: #27ae60;
            color: white;
            font-weight: bold;
            font-size: 12pt;
        }
        
        .totals-row.paye {
            background: #d4edda;
            color: #155724;
        }
        
        .totals-row.solde {
            background: #f8d7da;
            color: #721c24;
            font-weight: bold;
        }
        
        .totals-label {
            font-weight: 600;
        }
        
        .payment-info {
            background: #e8f5e9;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #27ae60;
        }
        
        .payment-info.due {
            background: #ffebee;
            border-left-color: #e74c3c;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            font-size: 9pt;
            color: #666;
        }
        
        .signature-box {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature {
            width: 200px;
            text-align: center;
        }
        
        .signature-line {
            margin-top: 60px;
            border-top: 1px solid #000;
            padding-top: 5px;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .container {
                max-width: 100%;
                padding: 0;
            }
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #229954;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Imprimer</button>
    
    <div class="container">
        <!-- En-tête -->
        <div class="header">
            <div class="company-info">
                <h1>KENNE MULTI-SERVICES</h1>
                <p>📍 Abidjan, Côte d'Ivoire</p>
                <p>📞 +225 XX XX XX XX XX</p>
                <p>📧 contact@kms.ci</p>
            </div>
            <div class="doc-info">
                <div class="doc-type">FACTURE</div>
                <div class="doc-number"><?= htmlspecialchars($vente['numero']) ?></div>
                <p>Date : <?= date('d/m/Y', strtotime($vente['date_vente'])) ?></p>
                <span class="status-badge status-<?= $vente['statut'] ?>">
                    <?= $vente['statut'] ?>
                </span>
            </div>
        </div>
        
        <!-- Informations client -->
        <div class="client-section">
            <h3>FACTURÉ À</h3>
            <p><strong><?= htmlspecialchars($vente['client_nom']) ?></strong></p>
            <?php if ($vente['telephone']): ?>
                <p>📞 <?= htmlspecialchars($vente['telephone']) ?></p>
            <?php endif; ?>
            <?php if ($vente['email']): ?>
                <p>📧 <?= htmlspecialchars($vente['email']) ?></p>
            <?php endif; ?>
            <?php if ($vente['adresse']): ?>
                <p>📍 <?= htmlspecialchars($vente['adresse']) ?></p>
            <?php endif; ?>
            <?php if ($vente['canal_libelle']): ?>
                <p>Canal : <?= htmlspecialchars($vente['canal_libelle']) ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Tableau des lignes -->
        <table>
            <thead>
                <tr>
                    <th>Réf.</th>
                    <th>Désignation</th>
                    <th class="text-right">Qté</th>
                    <th class="text-right">P.U. HT</th>
                    <th class="text-right">Remise</th>
                    <th class="text-right">Montant HT</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lignes as $ligne): ?>
                    <tr>
                        <td><?= htmlspecialchars($ligne['code_produit']) ?></td>
                        <td><?= htmlspecialchars($ligne['designation']) ?></td>
                        <td class="text-right"><?= (int)$ligne['quantite'] ?></td>
                        <td class="text-right"><?= number_format($ligne['prix_unitaire'], 0, ',', ' ') ?> FCFA</td>
                        <td class="text-right"><?= number_format($ligne['remise'], 0, ',', ' ') ?> FCFA</td>
                        <td class="text-right"><strong><?= number_format($ligne['montant_ligne_ht'], 0, ',', ' ') ?> FCFA</strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Totaux -->
        <?php 
        $montant_ht = $vente['montant_total_ht'] ?? 0;
        $montant_ttc = $vente['montant_total_ttc'] ?? 0;
        $montant_tva = $montant_ttc - $montant_ht;
        ?>
        <div class="totals">
            <div class="totals-box">
                <div class="totals-row">
                    <span class="totals-label">Total HT :</span>
                    <span><?= number_format($montant_ht, 0, ',', ' ') ?> FCFA</span>
                </div>
                <div class="totals-row">
                    <span class="totals-label">TVA (19.25%) :</span>
                    <span><?= number_format($montant_tva, 0, ',', ' ') ?> FCFA</span>
                </div>
                <div class="totals-row">
                    <span class="totals-label">TOTAL TTC :</span>
                    <span><?= number_format($montant_ttc, 0, ',', ' ') ?> FCFA</span>
                </div>
                <?php if ($montant_paye > 0): ?>
                    <div class="totals-row paye">
                        <span class="totals-label">Payé :</span>
                        <span><?= number_format($montant_paye, 0, ',', ' ') ?> FCFA</span>
                    </div>
                <?php endif; ?>
                <?php if ($solde_du > 0): ?>
                    <div class="totals-row solde">
                        <span class="totals-label">SOLDE DÛ :</span>
                        <span><?= number_format($solde_du, 0, ',', ' ') ?> FCFA</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Info paiement -->
        <?php if ($solde_du <= 0 && $montant_paye > 0): ?>
            <div class="payment-info">
                <strong>✅ FACTURE PAYÉE INTÉGRALEMENT</strong><br>
                Montant encaissé : <?= number_format($montant_paye, 0, ',', ' ') ?> FCFA
            </div>
        <?php elseif ($solde_du > 0): ?>
            <div class="payment-info due">
                <strong>⚠️ SOLDE À RÉGLER</strong><br>
                Montant restant dû : <?= number_format($solde_du, 0, ',', ' ') ?> FCFA
            </div>
        <?php endif; ?>
        
        <!-- Signatures -->
        <div class="signature-box">
            <div class="signature">
                <div class="signature-line">
                    <strong>Pour KMS</strong><br>
                    <?= htmlspecialchars($vente['commercial'] ?? 'Direction') ?>
                </div>
            </div>
            <div class="signature">
                <div class="signature-line">
                    <strong>Le client</strong><br>
                    Bon pour accord
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p style="text-align: center;">
                <strong>Conditions de paiement :</strong> Paiement à réception de facture<br>
                En cas de retard de paiement, des pénalités pourront être appliquées.<br>
                Merci de votre confiance - KENNE MULTI-SERVICES
            </p>
        </div>
    </div>
    
    <script>
        // Auto-print si paramètre print=1
        if (new URLSearchParams(window.location.search).get('print') === '1') {
            window.print();
        }
    </script>
</body>
</html>
