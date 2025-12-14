<?php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CAISSE_LIRE');

global $pdo;

$date = $_GET['date'] ?? date('Y-m-d');

// Totaux caisse
$stmt = $pdo->prepare("SELECT sens, COALESCE(SUM(montant),0) AS total FROM journal_caisse WHERE est_annule=0 AND date_operation = :d GROUP BY sens");
$stmt->execute([':d' => $date]);
$totaux = ['RECETTE' => 0.0, 'DEPENSE' => 0.0];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $totaux[$r['sens']] = (float)$r['total'];
}
$solde = $totaux['RECETTE'] - $totaux['DEPENSE'];

// Totaux ventes encaissées du jour
$stmtV = $pdo->prepare("SELECT COALESCE(SUM(montant_total_ttc),0) AS total_ventes_ttc FROM ventes WHERE statut_encaissement='ENCAISSE' AND date_vente = :d");
$stmtV->execute([':d' => $date]);
$totalVentesTTC = (float)$stmtV->fetchColumn();

// Liste écarts
$ecarts = [];
if (abs($totaux['RECETTE'] - $totalVentesTTC) > 0.009) {
    $ecarts[] = [
        'type' => 'VENTES_VS_CAISSE',
        'message' => 'Écart entre recettes caisse et ventes encaissées',
        'recettes' => $totaux['RECETTE'],
        'ventes_ttc' => $totalVentesTTC
    ];
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>
<div class="container-fluid p-4">
    <h1 class="h4">Réconciliation Caisse — <?= htmlspecialchars($date) ?></h1>

    <form method="get" class="mb-3 d-flex gap-2">
        <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" class="form-control" style="max-width:200px">
        <button class="btn btn-primary btn-sm">Afficher</button>
    </form>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Recettes (journal_caisse)</div>
                    <div class="h5"><?= number_format($totaux['RECETTE'], 2, ',', ' ') ?> FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Dépenses (journal_caisse)</div>
                    <div class="h5"><?= number_format($totaux['DEPENSE'], 2, ',', ' ') ?> FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Solde (journal_caisse)</div>
                    <div class="h5"><?= number_format($solde, 2, ',', ' ') ?> FCFA</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <div class="text-muted small">Ventes encaissées (TTC)</div>
            <div class="h5"><?= number_format($totalVentesTTC, 2, ',', ' ') ?> FCFA</div>
        </div>
    </div>

    <?php if (!empty($ecarts)): ?>
        <div class="alert alert-warning mt-3">
            <strong>Écarts détectés :</strong>
            <ul class="mb-0">
                <?php foreach ($ecarts as $e): ?>
                    <li>
                        <?= htmlspecialchars($e['message']) ?> — Recettes: <?= number_format($e['recettes'], 2, ',', ' ') ?> FCFA, Ventes: <?= number_format($e['ventes_ttc'], 2, ',', ' ') ?> FCFA
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <div class="alert alert-success mt-3">
            ✅ Aucune discordance détectée pour la date sélectionnée.
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
