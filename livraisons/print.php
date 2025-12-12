<?php
// livraisons/print.php - Impression bon de livraison
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('LIVRAISONS_LIRE');

global $pdo;

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    die('ID BL manquant');
}

// Charger le BL
$stmt = $pdo->prepare("
    SELECT bl.*, c.nom as client_nom, c.telephone, c.email, c.adresse,
           v.numero as vente_numero, u.nom_complet as livreur
    FROM bons_livraison bl
    LEFT JOIN ventes v ON bl.vente_id = v.id
    LEFT JOIN clients c ON v.client_id = c.id
    LEFT JOIN utilisateurs u ON bl.livreur_id = u.id
    WHERE bl.id = ?
");
$stmt->execute([$id]);
$bl = $stmt->fetch();

if (!$bl) {
    die('Bon de livraison introuvable');
}

// Charger les lignes depuis la vente
$stmt = $pdo->prepare("
    SELECT vl.*, p.code_produit, p.designation
    FROM ventes_lignes vl
    JOIN produits p ON vl.produit_id = p.id
    WHERE vl.vente_id = ?
    ORDER BY vl.id
");
$stmt->execute([$bl['vente_id']]);
$lignes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bon de Livraison <?= htmlspecialchars($bl['numero_bl']) ?></title>
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
            border-bottom: 3px solid #f39c12;
        }
        
        .company-info h1 {
            font-size: 24pt;
            color: #f39c12;
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
            color: #f39c12;
            margin-bottom: 10px;
        }
        
        .doc-number {
            font-size: 14pt;
            color: #e67e22;
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
        
        .status-EN_ATTENTE { background: #fff3cd; color: #856404; }
        .status-LIVRE { background: #d4edda; color: #155724; }
        .status-SIGNE { background: #d1ecf1; color: #0c5460; }
        
        .info-section {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-box {
            flex: 1;
            background: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #f39c12;
        }
        
        .info-box h3 {
            font-size: 11pt;
            color: #f39c12;
            margin-bottom: 10px;
        }
        
        .info-box p {
            margin: 3px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        table thead {
            background: #f39c12;
            color: white;
        }
        
        table th {
            padding: 10px;
            text-align: left;
            font-size: 10pt;
            font-weight: 600;
        }
        
        table th.text-right, table td.text-right {
            text-align: right;
        }
        
        table th.text-center, table td.text-center {
            text-align: center;
        }
        
        table td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            width: 45%;
            border: 2px solid #f39c12;
            padding: 20px;
            min-height: 150px;
        }
        
        .signature-box h4 {
            color: #f39c12;
            margin-bottom: 10px;
            border-bottom: 1px solid #f39c12;
            padding-bottom: 5px;
        }
        
        .signature-box p {
            font-size: 9pt;
            color: #666;
            margin: 5px 0;
        }
        
        .signature-line {
            margin-top: 60px;
            border-top: 1px solid #000;
            padding-top: 5px;
            text-align: center;
            font-size: 9pt;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            font-size: 9pt;
            color: #666;
            text-align: center;
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
            background: #f39c12;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #e67e22;
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
                <div class="doc-type">BON DE LIVRAISON</div>
                <div class="doc-number"><?= htmlspecialchars($bl['numero_bl']) ?></div>
                <p>Date BL : <?= date('d/m/Y', strtotime($bl['date_bl'])) ?></p>
                <p>Facture : <?= htmlspecialchars($bl['vente_numero']) ?></p>
                <span class="status-badge status-<?= $bl['statut'] ?>">
                    <?= $bl['statut'] ?>
                </span>
            </div>
        </div>
        
        <!-- Informations client et livraison -->
        <div class="info-section">
            <div class="info-box">
                <h3>CLIENT</h3>
                <p><strong><?= htmlspecialchars($bl['client_nom']) ?></strong></p>
                <?php if ($bl['telephone']): ?>
                    <p>📞 <?= htmlspecialchars($bl['telephone']) ?></p>
                <?php endif; ?>
                <?php if ($bl['email']): ?>
                    <p>📧 <?= htmlspecialchars($bl['email']) ?></p>
                <?php endif; ?>
            </div>
            
            <div class="info-box">
                <h3>LIVRAISON</h3>
                <p><strong>Adresse :</strong></p>
                <p><?= htmlspecialchars($bl['adresse_livraison'] ?? $bl['adresse'] ?? 'Non spécifiée') ?></p>
                <?php if ($bl['livreur']): ?>
                    <p style="margin-top: 10px;"><strong>Livreur :</strong> <?= htmlspecialchars($bl['livreur']) ?></p>
                <?php endif; ?>
                <?php if ($bl['date_livraison_effective']): ?>
                    <p><strong>Livrée le :</strong> <?= date('d/m/Y H:i', strtotime($bl['date_livraison_effective'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tableau des articles -->
        <table>
            <thead>
                <tr>
                    <th>Réf.</th>
                    <th>Désignation</th>
                    <th class="text-center">Qté livrée</th>
                    <th class="text-center">Qté reçue</th>
                    <th>Observations</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lignes as $ligne): ?>
                    <tr>
                        <td><?= htmlspecialchars($ligne['code_produit']) ?></td>
                        <td><?= htmlspecialchars($ligne['designation']) ?></td>
                        <td class="text-center"><strong><?= (int)$ligne['quantite'] ?></strong></td>
                        <td class="text-center">_______</td>
                        <td style="width: 150px;"></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($bl['commentaires']): ?>
            <div style="background: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #f39c12;">
                <strong>Remarques :</strong><br>
                <?= nl2br(htmlspecialchars($bl['commentaires'])) ?>
            </div>
        <?php endif; ?>
        
        <!-- Signatures -->
        <div class="signature-section">
            <div class="signature-box">
                <h4>LIVREUR</h4>
                <p>Nom : <?= htmlspecialchars($bl['livreur'] ?? '___________________') ?></p>
                <p>Date : <?= $bl['date_livraison_effective'] ? date('d/m/Y', strtotime($bl['date_livraison_effective'])) : '___/___/_____' ?></p>
                <div class="signature-line">
                    Signature
                </div>
            </div>
            
            <div class="signature-box">
                <h4>CLIENT / DESTINATAIRE</h4>
                <p>Nom : ___________________</p>
                <p>Date : ___/___/_____</p>
                <p>Heure : ___:___</p>
                <div class="signature-line">
                    Signature et cachet
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>
                ⚠️ <strong>IMPORTANT :</strong> Merci de vérifier la conformité des articles à la réception.<br>
                Toute réclamation doit être formulée dans les 24 heures suivant la réception.<br>
                <strong>Ce bon de livraison ne vaut pas facture.</strong>
            </p>
        </div>
    </div>
    
    <script>
        if (new URLSearchParams(window.location.search).get('print') === '1') {
            window.print();
        }
    </script>
</body>
</html>
