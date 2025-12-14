<?php
/**
 * Vérification Post-Encaissement
 * Récupère l'état actuel de la vente et journal caisse après test
 */

require_once __DIR__ . '/security.php';
exigerConnexion();

global $pdo;

$venteId = (int)($_GET['vente_id'] ?? 0);

if (!$venteId) {
    http_response_code(400);
    echo '<div class="error">❌ Pas de vente_id</div>';
    exit;
}

// Récupérer vente APRÈS
$stmt = $pdo->prepare("SELECT * FROM ventes WHERE id = ?");
$stmt->execute([$venteId]);
$venteAfter = $stmt->fetch();

// Récupérer journal caisse lié
$stmtJournal = $pdo->prepare("
    SELECT j.* FROM journal_caisse j
    WHERE j.vente_id = ?
    ORDER BY j.id DESC
    LIMIT 1
");
$stmtJournal->execute([$venteId]);
$journalCaisse = $stmtJournal->fetch();

?>

<div class="test-step">
    <h3>✅ Résultats Vérification</h3>
    
    <table>
        <tr>
            <th>Champ</th>
            <th>Avant Test</th>
            <th>Après Test</th>
            <th>Statut</th>
        </tr>
        <tr>
            <td class="label">statut_encaissement</td>
            <td><code>ATTENTE_PAIEMENT</code></td>
            <td><code><?= htmlspecialchars($venteAfter['statut_encaissement']) ?></code></td>
            <td><?= $venteAfter['statut_encaissement'] === 'ENCAISSE' ? '✅ OK' : '❌ FAIL' ?></td>
        </tr>
        <tr>
            <td class="label">journal_caisse_id</td>
            <td><code>NULL</code></td>
            <td><code><?= $venteAfter['journal_caisse_id'] ?? 'NULL' ?></code></td>
            <td><?= $venteAfter['journal_caisse_id'] ? '✅ OK' : '❌ FAIL' ?></td>
        </tr>
    </table>
</div>

<?php if ($journalCaisse): ?>
    <div class="test-step">
        <h3>📋 Entrée Journal Caisse Créée</h3>
        
        <table>
            <tr>
                <th>Champ</th>
                <th>Valeur</th>
            </tr>
            <tr>
                <td class="label">ID</td>
                <td><code><?= $journalCaisse['id'] ?></code></td>
            </tr>
            <tr>
                <td class="label">Date opération</td>
                <td><?= htmlspecialchars($journalCaisse['date_operation']) ?></td>
            </tr>
            <tr>
                <td class="label">Sens</td>
                <td><code><?= htmlspecialchars($journalCaisse['sens']) ?></code></td>
            </tr>
            <tr>
                <td class="label">Montant</td>
                <td><?= number_format($journalCaisse['montant'], 0, ',', ' ') ?> FCFA</td>
            </tr>
            <tr>
                <td class="label">Nature</td>
                <td><?= htmlspecialchars($journalCaisse['nature_operation']) ?></td>
            </tr>
            <tr>
                <td class="label">Vente ID</td>
                <td><code><?= $journalCaisse['vente_id'] ?></code></td>
            </tr>
            <tr>
                <td class="label">Observations</td>
                <td><?= htmlspecialchars($journalCaisse['observations'] ?? '(vide)') ?></td>
            </tr>
        </table>
    </div>

    <div class="success">
        <strong>✅ TEST RÉUSSI!</strong><br>
        <br>
        Vente #<?= $venteId ?> a été encaissée avec succès.<br>
        Journal caisse #<?= $journalCaisse['id'] ?> créé et lié.<br>
        <br>
        La synchronisation vente ↔ caisse fonctionne correctement!
    </div>
<?php else: ?>
    <div class="error">
        <strong>❌ ÉCHEC TEST</strong><br>
        <br>
        La vente a le statut_encaissement = '<?= htmlspecialchars($venteAfter['statut_encaissement']) ?>'<br>
        Mais AUCUNE entrée journal caisse liée trouvée!<br>
        <br>
        Vérifier: api_encaisser.php crée bien l'entry caisse
    </div>
<?php endif; ?>

<?php
