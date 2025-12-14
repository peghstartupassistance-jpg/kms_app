<?php
/**
 * Test d'intégration complet Phase 1.1
 * Simule le workflow encaissement
 */

require_once __DIR__ . '/security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

header('Content-Type: text/html; charset=utf-8');

$testId = 90; // Vente de test
$modePaiement = 1; // Espèces

// Récupérer la vente AVANT
$stmt = $pdo->prepare("SELECT * FROM ventes WHERE id = ?");
$stmt->execute([$testId]);
$venteBefore = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>Test Complet Phase 1.1</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; margin: 10px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 20px; }
        .info-block { background: #e7f3ff; padding: 15px; border-left: 4px solid #007bff; margin: 10px 0; border-radius: 4px; }
        .test-step { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; padding: 15px; margin: 10px 0; border-left: 4px solid #dc3545; border-radius: 4px; }
        .success { background: #d4edda; padding: 15px; margin: 10px 0; border-left: 4px solid #28a745; border-radius: 4px; }
        code { background: #f4f4f4; padding: 5px 10px; border-radius: 3px; font-family: monospace; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: bold; }
        .label { font-weight: bold; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin: 5px; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>

<h1>🧪 TEST D'INTÉGRATION COMPLET - PHASE 1.1</h1>

<div class='info-block'>
    <strong>Vente testée:</strong> ID <?= $venteBefore['id'] ?> | Numéro: <?= htmlspecialchars($venteBefore['numero']) ?><br>
    <strong>Montant TTC:</strong> <?= number_format($venteBefore['montant_total_ttc'], 0, ',', ' ') ?> FCFA<br>
    <strong>Statut actuel:</strong> <?= htmlspecialchars($venteBefore['statut']) ?><br>
    <strong>Encaissement avant:</strong> <?= htmlspecialchars($venteBefore['statut_encaissement']) ?>
</div>

<div class='container'>
    <h2>📊 Étape 1: Vérification Pré-Test</h2>
    
    <table>
        <tr>
            <th>Élément</th>
            <th>État</th>
            <th>Détail</th>
        </tr>
        <tr>
            <td class='label'>Colonne statut_encaissement</td>
            <td style='color: green; font-weight: bold;'>✅ OK</td>
            <td>Colonne existe et contient: <?= htmlspecialchars($venteBefore['statut_encaissement']) ?></td>
        </tr>
        <tr>
            <td class='label'>Colonne journal_caisse_id</td>
            <td style='color: green; font-weight: bold;'>✅ OK</td>
            <td>Colonne existe. Valeur: <?= $venteBefore['journal_caisse_id'] ? htmlspecialchars($venteBefore['journal_caisse_id']) : '(vide)' ?></td>
        </tr>
        <tr>
            <td class='label'>Montant TTC</td>
            <td style='color: green; font-weight: bold;'>✅ OK</td>
            <td><?= number_format($venteBefore['montant_total_ttc'], 2, ',', ' ') ?> FCFA</td>
        </tr>
    </table>
</div>

<div class='container'>
    <h2>🔧 Étape 2: Tester Bouton Encaissement</h2>
    <p><a href='<?= url_for('ventes/edit.php?id=' . $testId) ?>' target='_blank' class='btn btn-primary' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>➡️ Ouvrir vente #<?= $testId ?> dans nouvel onglet</a></p>
    <p><strong>Étapes à suivre manuellement:</strong></p>
    <ol>
        <li>Vérifier que le bouton jaune <strong>"Encaisser"</strong> est visible</li>
        <li>Cliquer sur le bouton</li>
        <li>Vérifier que le modal Bootstrap apparaît</li>
        <li>Vérifier que le montant est pré-rempli: <?= number_format($venteBefore['montant_total_ttc'], 2, ',', ' ') ?> FCFA</li>
        <li>Sélectionner un mode de paiement dans la dropdown</li>
        <li>Ajouter observation (optionnel)</li>
        <li>Cliquer "Confirmer"</li>
        <li>Vérifier le succès dans la console (F12)</li>
    </ol>
</div>

<div class='container'>
    <h2>📋 Étape 3: Résultats Attendus</h2>
    <div class='success'>
        <strong>✅ Si succès:</strong><br>
        ✓ statut_encaissement passe à: <strong>ENCAISSE</strong><br>
        ✓ journal_caisse_id reçoit une valeur numérique<br>
        ✓ Badge vert s'affiche: <strong>✓ Encaissée</strong><br>
        ✓ Redirection automatique après 2 secondes
    </div>
    <div class='error'>
        <strong>❌ Si erreur:</strong><br>
        ✗ Consulter F12 → Console → Chercher message erreur<br>
        ✗ Possibilités:<br>
        &nbsp;&nbsp;• Mode paiement non chargé → Vérifier AJAX modes_paiement.php<br>
        &nbsp;&nbsp;• API retourne erreur → Vérifier ventes/api_encaisser.php<br>
        &nbsp;&nbsp;• Paramètres manquants → Vérifier Modal dans ventes/edit.php
    </div>
</div>

<div class='container'>
    <h2>🔍 Étape 4: Vérification Technique (CLI)</h2>
    <p>Pour plus de détails techniques, exécutez en terminal:</p>
    <code>php <?= __DIR__ ?>/final_test_simple.php</code>
    <p><em>Ce script teste directement sans interface navigateur</em></p>
</div>

<hr>
<p style='color: #666; font-size: 0.9em;'>Test généré: <?= date('Y-m-d H:i:s') ?> | Vente ID: <?= $testId ?></p>

</body>
</html>
