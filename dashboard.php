<?php
// index.php - Dashboard enrichi Phase 2.3
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/dashboard_helpers.php';
exigerConnexion();

global $pdo;

$utilisateur = utilisateurConnecte();

// Calculer tous les KPIs
$ca_jour = calculateCAJour($pdo);
$ca_mois = calculateCAMois($pdo);
$bl_rate = calculateBLSignedRate($pdo);
$encaissement = calculateEncaissementRate($pdo);
$stock = calculateStockStats($pdo);
$alerts = getAlertsCritiques($pdo);

// Données pour Chart.js
$chartCAJour = getChartCAParJour($pdo);
$chartEncaissement = getChartEncaissementStatut($pdo);

// Conversion en JSON pour JavaScript
$chartCAJSON = json_encode($chartCAJour);
$chartEncJSON = json_encode($chartEncaissement);

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="container-fluid">
    <!-- Titre & Bienvenue -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0">
                <i class="bi bi-graph-up"></i> Dashboard Activité
            </h1>
            <small class="text-muted">
                <?= strftime('%A %d %B %Y', strtotime('today')) ?>
            </small>
        </div>
        <div class="col-md-4 text-end">
            <small class="text-muted">Bienvenue, <strong><?= htmlspecialchars($utilisateur['nom_complet']) ?></strong></small>
        </div>
    </div>

    <!-- ALERTES CRITIQUES -->
    <?php if (!empty($alerts)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-danger">
                    <div class="card-header bg-light">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Alertes Critiques</strong>
                    </div>
                    <div class="card-body p-2">
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($alerts as $alert): ?>
                                <a href="#" class="alert-badge" style="text-decoration: none;">
                                    <span class="badge bg-<?= $alert['type'] === 'danger' ? 'danger' : ($alert['type'] === 'warning' ? 'warning' : 'info') ?>">
                                        <i class="bi <?= $alert['icon'] ?>"></i>
                                        <?= htmlspecialchars($alert['message']) ?>
                                        <strong>(<?= $alert['count'] ?>)</strong>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPI CARDS ROW 1: CA -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card kpi-card border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small class="text-muted d-block mb-2">CA du jour (Consolidé)</small>
                            <h4 class="mb-0 text-primary">
                                <?= number_format($ca_jour['ca_total'], 0, ',', ' ') ?>
                                <span class="fs-6">FCFA</span>
                            </h4>
                        </div>
                        <i class="bi bi-graph-up-arrow text-primary fs-3 opacity-25"></i>
                    </div>
                    <hr class="my-2">
                    <div class="row text-center g-2 small">
                        <div class="col-6">
                            <strong class="text-teal"><?= number_format($ca_jour['ca_ventes'], 0) ?></strong>
                            <div class="text-muted">Ventes</div>
                        </div>
                        <div class="col-6">
                            <strong class="text-orange"><?= number_format($ca_jour['ca_hotel'], 0) ?></strong>
                            <div class="text-muted">Hôtel</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card kpi-card border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small class="text-muted d-block mb-2">CA du mois</small>
                            <h4 class="mb-0 text-success">
                                <?= number_format($ca_mois['ca_total'], 0, ',', ' ') ?>
                                <span class="fs-6">FCFA</span>
                            </h4>
                        </div>
                        <i class="bi bi-calendar-event text-success fs-3 opacity-25"></i>
                    </div>
                    <hr class="my-2">
                    <div class="row text-center g-2 small">
                        <div class="col-6">
                            <strong><?= number_format($ca_mois['ca_moyen_jour'], 0) ?></strong>
                            <div class="text-muted">CA moyen/j</div>
                        </div>
                        <div class="col-6">
                            <strong><?= $ca_mois['nb_jours_actifs'] ?></strong>
                            <div class="text-muted">Jours actifs</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card kpi-card border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small class="text-muted d-block mb-2">Encaissement</small>
                            <h4 class="mb-0 text-info">
                                <?= number_format($encaissement['encaissement_rate'], 1) ?>%
                            </h4>
                        </div>
                        <i class="bi bi-cash-coin text-info fs-3 opacity-25"></i>
                    </div>
                    <hr class="my-2">
                    <div class="row text-center g-2 small">
                        <div class="col-12">
                            <strong class="text-success"><?= number_format($encaissement['montant_encaisse'], 0) ?></strong>
                            <div class="text-muted">Encaissé</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card kpi-card border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small class="text-muted d-block mb-2">BL Signés</small>
                            <h4 class="mb-0 text-warning">
                                <?= number_format($bl_rate['signed_rate'], 1) ?>%
                            </h4>
                        </div>
                        <i class="bi bi-check-circle text-warning fs-3 opacity-25"></i>
                    </div>
                    <hr class="my-2">
                    <div class="row text-center g-2 small">
                        <div class="col-6">
                            <strong class="text-success"><?= $bl_rate['bl_signes'] ?></strong>
                            <div class="text-muted">Signés</div>
                        </div>
                        <div class="col-6">
                            <strong class="text-danger"><?= $bl_rate['bl_non_signes'] ?></strong>
                            <div class="text-muted">Non signés</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI CARDS ROW 2: STOCK -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card kpi-card">
                <div class="card-body">
                    <small class="text-muted d-block mb-2">
                        <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                        Produits en Rupture
                    </small>
                    <h4 class="mb-2">
                        <span class="text-danger"><?= $stock['produits_rupture'] ?></span>
                        <span class="fs-6 text-muted">/ <?= $stock['total_produits'] ?></span>
                    </h4>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-danger" style="width: <?= $stock['rupture_rate'] ?>%"></div>
                    </div>
                    <small class="text-muted d-block mt-2">Taux rupture: <strong><?= $stock['rupture_rate'] ?>%</strong></small>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card kpi-card">
                <div class="card-body">
                    <small class="text-muted d-block mb-2">
                        <i class="bi bi-exclamation-circle text-warning"></i>
                        Faible Stock
                    </small>
                    <h4 class="mb-2">
                        <span class="text-warning"><?= $stock['produits_faible_stock'] ?></span>
                        <span class="fs-6 text-muted">produits</span>
                    </h4>
                    <small class="text-muted d-block mt-3">
                        Total quantités: <strong><?= number_format($stock['quantite_total']) ?></strong>
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card kpi-card">
                <div class="card-body">
                    <small class="text-muted d-block mb-2">
                        <i class="bi bi-boxes text-info"></i>
                        Valeur Stock
                    </small>
                    <h4 class="mb-0 text-info">
                        <?= number_format($stock['valeur_stock'], 0, ',', ' ') ?>
                        <span class="fs-6">FCFA</span>
                    </h4>
                </div>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW -->
    <div class="row g-3 mb-4">
        <!-- Chart CA par jour (30j) -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="bi bi-graph-up"></i>
                        CA par jour (30 jours)
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="chartCAJour" height="60"></canvas>
                </div>
            </div>
        </div>

        <!-- Chart Encaissement statut -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="bi bi-pie-chart"></i>
                        Encaissement (30j)
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="chartEncaissement" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- RECENT ACTIVITY -->
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="bi bi-clock-history"></i>
                        Ventes Récentes
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT v.id, v.numero, c.nom as client, v.montant_total_ttc, v.date_vente
                            FROM ventes v
                            JOIN clients c ON c.id = v.client_id
                            ORDER BY v.date_vente DESC
                            LIMIT 5
                        ");
                        $stmt->execute();
                        $ventes_recentes = $stmt->fetchAll();
                        ?>
                        <?php foreach ($ventes_recentes as $v): ?>
                            <a href="<?= url_for('ventes/detail.php?id=' . (int)$v['id']) ?>" class="list-group-item list-group-item-action py-2">
                                <div class="d-flex justify-content-between align-items-start small">
                                    <div>
                                        <strong><?= htmlspecialchars($v['numero']) ?></strong>
                                        <div class="text-muted"><?= htmlspecialchars($v['client']) ?></div>
                                    </div>
                                    <div class="text-end">
                                        <strong><?= number_format($v['montant_total_ttc'], 0) ?></strong> FCFA
                                        <div class="text-muted"><?= $v['date_vente'] ?></div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="bi bi-truck"></i>
                        Bons Récents
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT b.id, b.numero, c.nom as client, b.date_bl, b.signe_client
                            FROM bons_livraison b
                            JOIN clients c ON c.id = b.client_id
                            ORDER BY b.date_bl DESC
                            LIMIT 5
                        ");
                        $stmt->execute();
                        $bls_recents = $stmt->fetchAll();
                        ?>
                        <?php foreach ($bls_recents as $b): ?>
                            <a href="<?= url_for('livraisons/detail.php?id=' . (int)$b['id']) ?>" class="list-group-item list-group-item-action py-2">
                                <div class="d-flex justify-content-between align-items-start small">
                                    <div>
                                        <strong><?= htmlspecialchars($b['numero']) ?></strong>
                                        <div class="text-muted"><?= htmlspecialchars($b['client']) ?></div>
                                    </div>
                                    <div class="text-end">
                                        <?php if ($b['signe_client']): ?>
                                            <span class="badge bg-success">Signé</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Non signé</span>
                                        <?php endif; ?>
                                        <div class="text-muted"><?= $b['date_bl'] ?></div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.min.js"></script>

<script>
// Chart CA par jour
const chartCACtx = document.getElementById('chartCAJour').getContext('2d');
const chartCA = new Chart(chartCACtx, {
    type: 'line',
    data: <?= $chartCAJSON ?>,
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
            },
            title: {
                display: false,
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return (value / 1000000).toFixed(1) + 'M';
                    }
                }
            }
        }
    }
});

// Chart Encaissement
const chartEncCtx = document.getElementById('chartEncaissement').getContext('2d');
const chartEnc = new Chart(chartEncCtx, {
    type: 'doughnut',
    data: <?= $chartEncJSON ?>,
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    }
});
</script>

<style>
.kpi-card {
    border-top-width: 4px !important;
    transition: transform 0.2s, box-shadow 0.2s;
}

.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.text-teal { color: #17a2b8; }
.text-orange { color: #ff9800; }

.alert-badge {
    transition: all 0.2s;
}

.alert-badge:hover {
    opacity: 0.8;
}
</style>

<?php include __DIR__ . '/partials/footer.php'; ?>
