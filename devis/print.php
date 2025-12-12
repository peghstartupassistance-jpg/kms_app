<?php
// devis/print.php - Vue d'impression pour devis
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('DEVIS_LIRE');

global $pdo;

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    die('ID devis manquant');
}

// Charger le devis
$stmt = $pdo->prepare("
    SELECT d.*, c.nom as client_nom, c.telephone, c.email, c.adresse,
           cv.libelle as canal_libelle, u.nom_complet as commercial
    FROM devis d
    LEFT JOIN clients c ON d.client_id = c.id
    LEFT JOIN canaux_vente cv ON d.canal_vente_id = cv.id
    LEFT JOIN utilisateurs u ON d.utilisateur_id = u.id
    WHERE d.id = ?
");
$stmt->execute([$id]);
$devis = $stmt->fetch();

if (!$devis) {
    die('Devis introuvable');
}

// Charger les lignes
$stmt = $pdo->prepare("
    SELECT dl.*, p.code_produit, p.designation
    FROM devis_lignes dl
    JOIN produits p ON dl.produit_id = p.id
    WHERE dl.devis_id = ?
    ORDER BY dl.id
");
$stmt->execute([$id]);
$lignes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devis <?= htmlspecialchars($devis['numero']) ?></title>
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
            border-bottom: 3px solid #2c3e50;
        }
        
        .company-info h1 {
            font-size: 24pt;
            color: #2c3e50;
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
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .doc-number {
            font-size: 14pt;
            color: #3498db;
            margin-bottom: 5px;
        }
        
        .client-section {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .client-section h3 {
            font-size: 11pt;
            color: #2c3e50;
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
            background: #2c3e50;
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
            border: 2px solid #2c3e50;
        }
        
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .totals-row:last-child {
            border-bottom: none;
            background: #2c3e50;
            color: white;
            font-weight: bold;
            font-size: 12pt;
        }
        
        .totals-label {
            font-weight: 600;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            font-size: 9pt;
            color: #666;
        }
        
        .conditions {
            margin-top: 20px;
            font-size: 9pt;
            line-height: 1.6;
        }
        
        .conditions h4 {
            font-size: 10pt;
            margin-bottom: 10px;
            color: #2c3e50;
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
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #2980b9;
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
                <div class="doc-type">DEVIS</div>
                <div class="doc-number"><?= htmlspecialchars($devis['numero']) ?></div>
                <p>Date : <?= date('d/m/Y', strtotime($devis['date_devis'])) ?></p>
                <?php if (!empty($devis['date_validite'])): ?>
                    <p>Valide jusqu'au : <?= date('d/m/Y', strtotime($devis['date_validite'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Informations client -->
        <div class="client-section">
            <h3>CLIENT</h3>
            <p><strong><?= htmlspecialchars($devis['client_nom']) ?></strong></p>
            <?php if ($devis['telephone']): ?>
                <p>📞 <?= htmlspecialchars($devis['telephone']) ?></p>
            <?php endif; ?>
            <?php if ($devis['email']): ?>
                <p>📧 <?= htmlspecialchars($devis['email']) ?></p>
            <?php endif; ?>
            <?php if ($devis['adresse']): ?>
                <p>📍 <?= htmlspecialchars($devis['adresse']) ?></p>
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
        $montant_ht = $devis['montant_total_ht'] ?? 0;
        $montant_ttc = $devis['montant_total_ttc'] ?? 0;
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
            </div>
        </div>
        
        <!-- Conditions -->
        <?php if ($devis['conditions']): ?>
            <div class="conditions">
                <h4>Conditions particulières</h4>
                <p><?= nl2br(htmlspecialchars($devis['conditions'])) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($devis['commentaires']): ?>
            <div class="conditions">
                <h4>Commentaires</h4>
                <p><?= nl2br(htmlspecialchars($devis['commentaires'])) ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Signatures -->
        <div class="signature-box">
            <div class="signature">
                <div class="signature-line">
                    <strong>Pour KMS</strong><br>
                    <?= htmlspecialchars($devis['commercial'] ?? 'Direction') ?>
                </div>
            </div>
            <div class="signature">
                <div class="signature-line">
                    <strong>Le client</strong><br>
                    Signature et cachet
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p style="text-align: center;">
                Ce devis est valable jusqu'à la date indiquée. Passé ce délai, les prix peuvent être révisés.<br>
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
