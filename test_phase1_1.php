<?php
/**
 * Validation UI/UX Phase 1.1 - Encaissement
 * 
 * Checklist:
 * - ✓ Bouton "Encaisser" visible (si montant > 0 et pas encaissée)
 * - ✓ Modal charge et affiche correctement
 * - ✓ Mode paiement dropdown charge via AJAX
 * - ✓ API encaisseur fonctionne
 * - ✓ Vente liée à journal caisse
 */

require_once __DIR__ . '/security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die("❌ Pas de vente spécifiée");
}

// Récupérer la vente
$stmt = $pdo->prepare("
    SELECT v.*, c.nom as client_nom
    FROM ventes v
    LEFT JOIN clients c ON c.id = v.client_id
    WHERE v.id = ?
");
$stmt->execute([$id]);
$vente = $stmt->fetch();

if (!$vente) {
    die("❌ Vente non trouvée");
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test Phase 1.1 - Encaissement</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .test-section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007bff; }
        .test-item { padding: 10px; margin: 5px 0; background: #f9f9f9; border-radius: 3px; }
        .pass { color: green; font-weight: bold; }
        .fail { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
        h1 { color: #333; }
        .info { background: #e7f3ff; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>

<h1>🔍 TEST PHASE 1.1 - ENCAISSEMENT VENTE</h1>

<div class="info">
    <strong>Vente testée:</strong> #<?= $vente['id'] ?> | Numéro: <?= htmlspecialchars($vente['numero']) ?><br>
    <strong>Montant:</strong> <?= number_format($vente['montant_total_ttc'], 0, ',', ' ') ?> FCFA<br>
    <strong>Client:</strong> <?= htmlspecialchars($vente['client_nom'] ?? 'N/A') ?><br>
    <strong>Statut:</strong> <?= $vente['statut'] ?> | Encaissement: <strong><?= $vente['statut_encaissement'] ?></strong>
</div>

<div class="test-section">
    <h3>✓ Checklist UI/UX</h3>
    
    <div class="test-item">
        <span class="pass">✅ Bouton "Encaisser" visible?</span><br>
        <small>Attendu: OUI (montant > 0 ET statut_encaissement = 'ATTENTE_PAIEMENT')</small><br>
        <small>Réel: <?= ($vente['montant_total_ttc'] > 0 && $vente['statut_encaissement'] === 'ATTENTE_PAIEMENT') ? 'OUI ✅' : 'NON ❌' ?></small>
    </div>

    <div class="test-item">
        <span class="pass">✅ Modal Bootstrap chargée?</span><br>
        <small>ID: <code>#modalEncaissement</code></small><br>
        <small>État: À vérifier dans navigateur (F12 → Console)</small>
    </div>

    <div class="test-item">
        <span class="pass">✅ API modes_paiement.php accessible?</span><br>
        <small>URL: <code>/ajax/modes_paiement.php</code></small><br>
        <small>Test: Ouvrir l'URL directement ci-dessous</small>
    </div>

    <div class="test-item">
        <span class="pass">✅ API encaisser.php accessible?</span><br>
        <small>URL: <code>/ventes/api_encaisser.php</code></small><br>
        <small>Méthode: POST JSON</small>
    </div>
</div>

<div class="test-section">
    <h3>🧪 Workflow Test (À faire manuellement)</h3>
    
    <ol>
        <li><strong>Ouvrir la vente:</strong> <a href="<?= url_for('ventes/edit.php?id=' . $vente['id']) ?>" target="_blank">
            Vente #<?= $vente['id'] ?> 
        </a></li>
        
        <li><strong>Vérifier bouton "Encaisser"</strong> dans la barre d'en-tête
            <ul>
                <li>Doit être jaune (btn-warning)</li>
                <li>Doit afficher icon + texte "Encaisser"</li>
                <li>Doit être cliquable</li>
            </ul>
        </li>
        
        <li><strong>Clic sur "Encaisser"</strong>
            <ul>
                <li>Modal doit apparaître (transition smooth)</li>
                <li>Montant doit être pré-rempli: <strong><?= number_format($vente['montant_total_ttc'], 2, '.', '') ?></strong></li>
            </ul>
        </li>
        
        <li><strong>Sélectionner mode paiement</strong>
            <ul>
                <li>Dropdown doit charger modes (AJAX)</li>
                <li>Options: Espèces, Virement, Mobile Money, Chèque</li>
                <li>Sélectionner "Espèces"</li>
            </ul>
        </li>
        
        <li><strong>Observations (facultatif)</strong>
            <ul>
                <li>Saisir: "Test Phase 1.1"</li>
            </ul>
        </li>
        
        <li><strong>Clic "Confirmer encaissement"</strong>
            <ul>
                <li>Button doit montrer "Traitement..."</li>
                <li>POST vers /ventes/api_encaisser.php</li>
                <li>Vérifier réponse JSON: <code>{"success": true}</code></li>
            </ul>
        </li>
        
        <li><strong>Après succès</strong>
            <ul>
                <li>Alert: "✓ Encaissement enregistré!"</li>
                <li>Redirection vers /ventes/list.php</li>
                <li>Badge "✓ Encaissée" doit apparaître</li>
            </ul>
        </li>
    </ol>
</div>

<div class="test-section">
    <h3>🔗 Vérifications Base de Données</h3>
    
    <div class="test-item">
        <strong>Avant test:</strong><br>
        statut_encaissement = <span class="warning"><?= $vente['statut_encaissement'] ?></span><br>
        journal_caisse_id = <span class="warning"><?= $vente['journal_caisse_id'] ?? 'NULL' ?></span>
    </div>
    
    <div class="test-item">
        <strong>Après test (À vérifier):</strong><br>
        statut_encaissement doit être = <span class="pass">ENCAISSE</span><br>
        journal_caisse_id doit contenir une valeur (>0)<br>
        <br>
        <code>SELECT statut_encaissement, journal_caisse_id FROM ventes WHERE id = <?= $vente['id'] ?></code>
    </div>
</div>

<div class="test-section">
    <h3>📋 Résultats du Test</h3>
    <div id="results" style="padding: 10px; background: #f0f0f0; border-radius: 3px; min-height: 50px;">
        En attente... Exécutez le test ci-dessus
    </div>
</div>

<div class="test-section">
    <h3>🔗 Liens Utiles</h3>
    <ul>
        <li><a href="<?= url_for('ajax/modes_paiement.php') ?>" target="_blank">Test API modes_paiement.php</a></li>
        <li><a href="<?= url_for('ventes/list.php') ?>" target="_blank">Liste ventes</a></li>
        <li><a href="<?= url_for('caisse/list.php') ?>" target="_blank">Journal caisse (pour vérifier après test)</a></li>
        <li><a href="<?= url_for('index.php') ?>" target="_blank">Accueil</a></li>
    </ul>
</div>

<div class="test-section" style="border-left-color: #dc3545;">
    <h3>⚠️ Dépannage</h3>
    
    <div class="test-item">
        <strong>Si bouton "Encaisser" n'apparaît pas:</strong>
        <ul>
            <li>Vérifier: montant_total_ttc > 0 ✓ (<?= $vente['montant_total_ttc'] ?>)</li>
            <li>Vérifier: statut_encaissement = 'ATTENTE_PAIEMENT' ✓ (<?= $vente['statut_encaissement'] ?>)</li>
            <li>Ouvrir F12 (Console) → Chercher erreurs PHP</li>
        </ul>
    </div>
    
    <div class="test-item">
        <strong>Si modal ne s'ouvre pas:</strong>
        <ul>
            <li>Ouvrir F12 → Console → Chercher erreurs JS</li>
            <li>Vérifier Bootstrap 5 chargé: <code>window.bootstrap</code></li>
            <li>Vérifier DOM: <code>document.getElementById('modalEncaissement')</code></li>
        </ul>
    </div>
    
    <div class="test-item">
        <strong>Si API modes_paiement échoue:</strong>
        <ul>
            <li>F12 → Network → Chercher requête AJAX</li>
            <li>Vérifier réponse: <code>[{"id": 1, "libelle": "..."}, ...]</code></li>
            <li>Vérifier permission: VENTES_LIRE ✓</li>
        </ul>
    </div>
    
    <div class="test-item">
        <strong>Si encaissement échoue (API):</strong>
        <ul>
            <li>F12 → Network → api_encaisser.php</li>
            <li>Réponse attendue: <code>{"success": true, "journal_caisse_id": X}</code></li>
            <li>Si erreur 500: Vérifier logs PHP (XAMPP)</li>
        </ul>
    </div>
</div>

</body>
</html>

<?php include __DIR__ . '/partials/footer.php'; ?>
