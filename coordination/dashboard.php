<?php
// coordination/dashboard.php - Dashboard coordination enrichi
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');
require_once __DIR__ . '/../lib/navigation_helpers.php';
global $pdo;

// Stats ventes 30j
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN statut='LIVREE' THEN 1 END) as livrees,
        COUNT(CASE WHEN statut='PARTIELLEMENT_LIVREE' THEN 1 END) as partielles,
        SUM(montant_total_ttc) as montant
    FROM ventes WHERE DATE(date_vente) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$statsVentes = $stmt->fetch();

// Litiges
$stmt = $pdo->query("SELECT COUNT(*) as c FROM retours_litiges WHERE statut_traitement='EN_COURS'");
$litigesEnCours = $stmt->fetch()['c'];

// Ventes sans livraison (hors annulées)
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT v.id) as c FROM ventes v
    LEFT JOIN bons_livraison bl ON bl.vente_id = v.id
    WHERE bl.id IS NULL 
      AND v.statut NOT IN ('ANNULEE', 'DEVIS')
      AND DATE(v.date_vente) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
$ventesSansLivraison = $stmt->fetch()['c'];

// Anomalies 7 derniers jours
$stmt = $pdo->query("
    SELECT DATE(date_vente) as jour, COUNT(*) as nb
    FROM ventes WHERE DATE(date_vente) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(date_vente) ORDER BY jour
");
$evolutionVentes = $stmt->fetchAll();

// Vérifier anomalies sur ventes récentes
$stmt = $pdo->query("SELECT id, numero FROM ventes WHERE DATE(date_vente) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) LIMIT 20");
$ventesRecentes = $stmt->fetchAll();
$ventesAvecProblemes = [];
foreach ($ventesRecentes as $v) {
    $verif = verify_vente_coherence($pdo, $v['id']);
    if (!$verif['ok']) {
        $ventesAvecProblemes[] = ['id' => $v['id'], 'numero' => $v['numero'], 'problemes' => $verif['problemes']];
    }
}

// Ordres de préparation
$stmt = $pdo->query("
    SELECT statut, COUNT(*) as nb FROM ordres_preparation 
    WHERE DATE(date_creation) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY statut
");
$ordresStats = [];
while($row = $stmt->fetch()) { $ordresStats[$row['statut']] = $row['nb']; }

// Dernières ventes
$stmt = $pdo->query("
    SELECT v.id, v.numero, v.date_vente, c.nom as client, v.montant_total_ttc, v.statut
    FROM ventes v JOIN clients c ON c.id = v.client_id
    ORDER BY v.date_vente DESC LIMIT 10
");
$dernieres = $stmt->fetchAll();

// Stats livraisons - tous les BL des 30 derniers jours
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN statut='LIVRE' THEN 1 END) as livres,
        AVG(CASE 
            WHEN date_livraison_effective IS NOT NULL 
            THEN DATEDIFF(date_livraison_effective, (SELECT date_vente FROM ventes WHERE id = bons_livraison.vente_id))
            ELSE NULL 
        END) as delai_moyen
    FROM bons_livraison WHERE DATE(date_bl) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$statsLivraisons = $stmt->fetch();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
include __DIR__ . '/navigation.php';  // Navigation coordination
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-1"><i class="bi bi-diagram-3 text-primary"></i> Coordination Ventes-Livraisons</h1>
            <p class="text-muted">Suivi complet du parcours vente → livraison → litiges</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?= url_for('coordination/verification_synchronisation.php') ?>" class="btn btn-success btn-sm">
                <i class="bi bi-check-all"></i> Vérification
            </a>
            <a href="<?= url_for('coordination/corriger_synchronisation.php') ?>" class="btn btn-warning btn-sm">
                <i class="bi bi-wrench"></i> Corriger
            </a>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                                <i class="bi bi-receipt fs-2 text-primary"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Ventes 30j</h6>
                            <h3 class="mb-0"><?= (int)$statsVentes['total'] ?></h3>
                            <small class="text-muted"><?= number_format($statsVentes['montant'],0,',',' ') ?> FCFA</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-success bg-gradient">
                <div class="card-body text-white">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle fs-1 me-3"></i>
                        <div>
                            <h6 class="mb-0 small opacity-75">Livrées</h6>
                            <h3 class="mb-0"><?= (int)$statsVentes['livrees'] ?></h3>
                            <small class="opacity-75">
                                <?= $statsVentes['total'] > 0 ? round(($statsVentes['livrees']/$statsVentes['total'])*100) : 0 ?>%
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 <?= $litigesEnCours > 0 ? 'border-warning' : '' ?>">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                                <i class="bi bi-exclamation-triangle fs-2 text-warning"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Litiges EN_COURS</h6>
                            <h3 class="mb-0"><?= $litigesEnCours ?></h3>
                            <small class="text-muted">À traiter</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 <?= count($ventesAvecProblemes) > 0 ? 'border-danger' : '' ?>">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                                <i class="bi bi-bug fs-2 text-danger"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Anomalies</h6>
                            <h3 class="mb-0 text-danger"><?= count($ventesAvecProblemes) ?></h3>
                            <small class="text-muted">Ventes problématiques</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alertes critiques -->
    <?php if (!empty($ventesAvecProblemes)): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-4">
        <h5 class="alert-heading">
            <i class="bi bi-exclamation-triangle"></i> <?= count($ventesAvecProblemes) ?> anomalie(s) détectée(s)
        </h5>
        <div class="row g-2 mt-2">
            <?php foreach (array_slice($ventesAvecProblemes, 0, 3) as $v): ?>
                <div class="col-md-4">
                    <div class="card bg-white">
                        <div class="card-body p-2">
                            <strong class="text-danger">#<?= htmlspecialchars($v['numero']) ?></strong>
                            <ul class="list-unstyled small mb-2">
                                <?php foreach ($v['problemes'] as $p): ?>
                                    <li><i class="bi bi-x-circle text-danger"></i> <?= htmlspecialchars($p) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="<?= url_for('coordination/corriger_synchronisation.php?vente_id='.$v['id']) ?>" class="btn btn-sm btn-danger">
                                <i class="bi bi-wrench"></i> Corriger
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-3">
            <a href="<?= url_for('coordination/verification_synchronisation.php') ?>" class="btn btn-danger">
                <i class="bi bi-search"></i> Voir toutes les anomalies
            </a>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($ventesSansLivraison > 0): ?>
    <div class="alert alert-warning">
        <i class="bi bi-truck"></i> <strong><?= $ventesSansLivraison ?> vente(s) sans livraison</strong> (7 derniers jours)
    </div>
    <?php endif; ?>

    <!-- Graphiques -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-graph-up"></i> Ventes 7 derniers jours</h5></div>
                <div class="card-body"><canvas id="chartVentes" height="80"></canvas></div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-clipboard-check"></i> Ordres préparation</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>EN_ATTENTE</span>
                            <strong><?= $ordresStats['EN_ATTENTE'] ?? 0 ?></strong>
                        </div>
                        <div class="progress" style="height:8px">
                            <div class="progress-bar bg-secondary" style="width:<?= array_sum($ordresStats) > 0 ? (($ordresStats['EN_ATTENTE']??0)/array_sum($ordresStats)*100) : 0 ?>%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>EN_COURS</span>
                            <strong><?= $ordresStats['EN_COURS'] ?? 0 ?></strong>
                        </div>
                        <div class="progress" style="height:8px">
                            <div class="progress-bar bg-warning" style="width:<?= array_sum($ordresStats) > 0 ? (($ordresStats['EN_COURS']??0)/array_sum($ordresStats)*100) : 0 ?>%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>PRET</span>
                            <strong><?= $ordresStats['PRET'] ?? 0 ?></strong>
                        </div>
                        <div class="progress" style="height:8px">
                            <div class="progress-bar bg-success" style="width:<?= array_sum($ordresStats) > 0 ? (($ordresStats['PRET']??0)/array_sum($ordresStats)*100) : 0 ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats livraisons -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm bg-info text-white">
                <div class="card-body">
                    <h6 class="opacity-75">Livraisons 30j</h6>
                    <h3><?= (int)$statsLivraisons['total'] ?> bons de livraison</h3>
                    <small class="opacity-75">
                        <?= (int)$statsLivraisons['livres'] ?> livrés • 
                        Délai moyen: <?= $statsLivraisons['delai_moyen'] ? round($statsLivraisons['delai_moyen'], 1) : 'N/A' ?> jour(s)
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted">Taux de livraison complète</h6>
                    <h3 class="text-success">
                        <?= $statsVentes['total'] > 0 ? round(($statsVentes['livrees']/$statsVentes['total'])*100) : 0 ?>%
                    </h3>
                    <div class="progress" style="height:10px">
                        <div class="progress-bar bg-success" style="width:<?= $statsVentes['total'] > 0 ? (($statsVentes['livrees']/$statsVentes['total'])*100) : 0 ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation rapide -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <a href="<?= url_for('ventes/list.php') ?>" class="btn btn-outline-primary w-100 py-3">
                <i class="bi bi-cart-check fs-4 d-block mb-2"></i>
                <strong>Ventes</strong>
            </a>
        </div>
        <div class="col-md-4">
            <a href="<?= url_for('livraisons/list.php') ?>" class="btn btn-outline-success w-100 py-3">
                <i class="bi bi-truck fs-4 d-block mb-2"></i>
                <strong>Bons de livraison</strong>
            </a>
        </div>
        <div class="col-md-4">
            <a href="<?= url_for('litiges/list.php') ?>" class="btn btn-outline-danger w-100 py-3">
                <i class="bi bi-exclamation-triangle fs-4 d-block mb-2"></i>
                <strong>Litiges</strong>
            </a>
        </div>
    </div>

    <!-- Dernières ventes -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-list"></i> Dernières ventes</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>N°</th><th>Client</th><th>Date</th><th class="text-end">Montant</th><th>Statut</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($dernieres as $v): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($v['numero']) ?></strong></td>
                            <td><?= htmlspecialchars($v['client']) ?></td>
                            <td><?= date('d/m/Y', strtotime($v['date_vente'])) ?></td>
                            <td class="text-end"><?= number_format($v['montant_total_ttc'],0,',',' ') ?></td>
                            <td><span class="badge bg-<?= $v['statut']=='LIVREE'?'success':($v['statut']=='PARTIELLEMENT_LIVREE'?'info':'warning') ?>"><?= $v['statut'] ?></span></td>
                            <td>
                                <a href="<?= url_for('ventes/detail.php?id='.$v['id']) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const evolutionVentes = <?= json_encode($evolutionVentes) ?>;

new Chart(document.getElementById('chartVentes'), {
    type: 'bar',
    data: {
        labels: evolutionVentes.map(e => new Date(e.jour).toLocaleDateString('fr-FR', {weekday: 'short', day: 'numeric'})),
        datasets: [{
            label: 'Ventes',
            data: evolutionVentes.map(e => e.nb),
            backgroundColor: 'rgba(54, 162, 235, 0.8)'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {legend: {display: false}},
        scales: {y: {beginAtZero: true, ticks: {stepSize: 1}}}
    }
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>