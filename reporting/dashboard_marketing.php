<?php
// reporting/dashboard_marketing.php - Dashboard marketing enrichi
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('REPORTING_LIRE');
global $pdo;

$today = date('Y-m-d');
$periode = $_GET['periode'] ?? 'mois';

switch ($periode) {
    case 'semaine':
        $dateDebut = date('Y-m-d', strtotime('monday this week'));
        $dateFin = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'mois':
        $dateDebut = date('Y-m-01');
        $dateFin = date('Y-m-t');
        break;
    default:
        $dateDebut = $today;
        $dateFin = $today;
}

// KPIs Showroom
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT vs.id) as nb_visiteurs,
        (SELECT COUNT(*) FROM devis WHERE canal_vente_id=1 AND date_devis BETWEEN ? AND ?) as nb_devis,
        (SELECT COUNT(*) FROM ventes WHERE canal_vente_id=1 AND date_vente BETWEEN ? AND ?) as nb_ventes,
        (SELECT COALESCE(SUM(montant_total_ttc),0) FROM ventes WHERE canal_vente_id=1 AND date_vente BETWEEN ? AND ?) as ca_ttc
    FROM visiteurs_showroom vs WHERE vs.date_visite BETWEEN ? AND ?
");
$stmt->execute([$dateDebut,$dateFin,$dateDebut,$dateFin,$dateDebut,$dateFin,$dateDebut,$dateFin]);
$kpiShowroom = $stmt->fetch();

// KPIs Terrain
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT pt.id) as nb_prospections,
        (SELECT COUNT(*) FROM devis WHERE canal_vente_id=2 AND date_devis BETWEEN ? AND ?) as nb_devis,
        (SELECT COUNT(*) FROM ventes WHERE canal_vente_id=2 AND date_vente BETWEEN ? AND ?) as nb_ventes,
        (SELECT COALESCE(SUM(montant_total_ttc),0) FROM ventes WHERE canal_vente_id=2 AND date_vente BETWEEN ? AND ?) as ca_ttc
    FROM prospections_terrain pt WHERE pt.date_prospection BETWEEN ? AND ?
");
$stmt->execute([$dateDebut,$dateFin,$dateDebut,$dateFin,$dateDebut,$dateFin,$dateDebut,$dateFin]);
$kpiTerrain = $stmt->fetch();

// KPIs Digital
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as nb_leads,
        SUM(CASE WHEN statut='NOUVEAU' THEN 1 ELSE 0 END) as nb_nouveaux,
        SUM(CASE WHEN statut='CONVERTI' THEN 1 ELSE 0 END) as nb_convertis,
        (SELECT COUNT(*) FROM devis WHERE canal_vente_id=3 AND date_devis BETWEEN ? AND ?) as nb_devis,
        (SELECT COALESCE(SUM(montant_total_ttc),0) FROM ventes WHERE canal_vente_id=3 AND date_vente BETWEEN ? AND ?) as ca_ttc,
        COALESCE(SUM(cout_acquisition),0) as cout_total
    FROM leads_digital WHERE date_lead BETWEEN ? AND ?
");
$stmt->execute([$dateDebut,$dateFin,$dateDebut,$dateFin,$dateDebut,$dateFin]);
$kpiDigital = $stmt->fetch();

// KPIs Hôtel
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as nb_reservations,
        SUM(nb_nuits) as total_nuits,
        COALESCE(SUM(montant_total),0) as ca_chambres
    FROM reservations_hotel WHERE date_reservation BETWEEN ? AND ?
");
$stmt->execute([$dateDebut,$dateFin]);
$kpiHotel = $stmt->fetch();

// KPIs Formation
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM prospects_formation WHERE date_prospect BETWEEN ? AND ?) as nb_prospects,
        COUNT(*) as nb_inscriptions,
        COALESCE(SUM(montant_paye),0) as ca_encaisse
    FROM inscriptions_formation WHERE date_inscription BETWEEN ? AND ?
");
$stmt->execute([$dateDebut,$dateFin,$dateDebut,$dateFin]);
$kpiFormation = $stmt->fetch();

$ca_global = $kpiShowroom['ca_ttc'] + $kpiTerrain['ca_ttc'] + $kpiDigital['ca_ttc'] + $kpiHotel['ca_chambres'] + $kpiFormation['ca_encaisse'];

$taux_conversion_showroom = $kpiShowroom['nb_visiteurs'] > 0 ? round($kpiShowroom['nb_ventes']*100/$kpiShowroom['nb_visiteurs'],1) : 0;
$taux_conversion_terrain = $kpiTerrain['nb_prospections'] > 0 ? round($kpiTerrain['nb_ventes']*100/$kpiTerrain['nb_prospections'],1) : 0;
$taux_conversion_digital = $kpiDigital['nb_leads'] > 0 ? round($kpiDigital['nb_convertis']*100/$kpiDigital['nb_leads'],1) : 0;

// ROI Digital
$roi_digital = $kpiDigital['cout_total'] > 0 ? round(($kpiDigital['ca_ttc'] - $kpiDigital['cout_total']) / $kpiDigital['cout_total'] * 100, 0) : 0;

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-graph-up-arrow text-primary"></i> Dashboard Marketing</h1>
            <p class="text-muted small mb-0">Performances multi-canaux</p>
        </div>
        <div class="btn-group">
            <a href="?periode=jour" class="btn btn-sm <?= $periode==='jour'?'btn-primary':'btn-outline-primary' ?>">Aujourd'hui</a>
            <a href="?periode=semaine" class="btn btn-sm <?= $periode==='semaine'?'btn-primary':'btn-outline-primary' ?>">Semaine</a>
            <a href="?periode=mois" class="btn btn-sm <?= $periode==='mois'?'btn-primary':'btn-outline-primary' ?>">Mois</a>
        </div>
    </div>

    <div class="alert alert-light border mb-4">
        <strong>Période :</strong> du <?= date('d/m/Y',strtotime($dateDebut)) ?> au <?= date('d/m/Y',strtotime($dateFin)) ?>
        <a href="<?= url_for('reporting/tunnel_conversion.php') ?>" class="btn btn-sm btn-warning float-end">
            <i class="bi bi-funnel"></i> Tunnel de conversion
        </a>
    </div>

    <!-- CA Global -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-gradient bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="opacity-75 mb-1">Chiffre d'affaires global</h6>
                            <h2 class="mb-0"><?= number_format($ca_global,0,',',' ') ?> FCFA</h2>
                            <small class="opacity-75">Tous canaux confondus</small>
                        </div>
                        <i class="bi bi-cash-stack display-3 opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Canaux -->
    <div class="row g-3 mb-4">
        <!-- Showroom -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-shop-window"></i> <strong>SHOWROOM</strong>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="text-muted small">Visiteurs</div>
                            <div class="fs-5 fw-bold"><?= number_format($kpiShowroom['nb_visiteurs']) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Devis</div>
                            <div class="fs-5 fw-bold"><?= number_format($kpiShowroom['nb_devis']) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Ventes</div>
                            <div class="fs-5 fw-bold text-success"><?= number_format($kpiShowroom['nb_ventes']) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">CA</div>
                            <div class="fs-5 fw-bold text-success"><?= number_format($kpiShowroom['ca_ttc']/1000000,1) ?>M</div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small">Taux conversion</div>
                            <div class="progress" style="height:20px">
                                <div class="progress-bar bg-success" style="width:<?= $taux_conversion_showroom ?>%">
                                    <?= $taux_conversion_showroom ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Terrain -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-geo-alt"></i> <strong>TERRAIN</strong>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="text-muted small">Prospections</div>
                            <div class="fs-5 fw-bold"><?= number_format($kpiTerrain['nb_prospections']) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Devis</div>
                            <div class="fs-5 fw-bold"><?= number_format($kpiTerrain['nb_devis']) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Ventes</div>
                            <div class="fs-5 fw-bold text-success"><?= number_format($kpiTerrain['nb_ventes']) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">CA</div>
                            <div class="fs-5 fw-bold text-success"><?= number_format($kpiTerrain['ca_ttc']/1000000,1) ?>M</div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small">Taux conversion</div>
                            <div class="progress" style="height:20px">
                                <div class="progress-bar bg-success" style="width:<?= $taux_conversion_terrain ?>%">
                                    <?= $taux_conversion_terrain ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Digital -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-megaphone"></i> <strong>DIGITAL</strong>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="text-muted small">Leads</div>
                            <div class="fs-5 fw-bold"><?= number_format($kpiDigital['nb_leads']) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Convertis</div>
                            <div class="fs-5 fw-bold text-success"><?= number_format($kpiDigital['nb_convertis']) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">CA</div>
                            <div class="fs-5 fw-bold text-success"><?= number_format($kpiDigital['ca_ttc']/1000000,1) ?>M</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Coût acquisition</div>
                            <div class="fs-5 fw-bold text-danger"><?= number_format($kpiDigital['cout_total'],0,',',' ') ?></div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small">ROI</div>
                            <div class="progress" style="height:20px">
                                <div class="progress-bar <?= $roi_digital>0?'bg-success':'bg-danger' ?>" style="width:<?= min(abs($roi_digital),100) ?>%">
                                    <?= $roi_digital ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hôtel + Formation -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-warning">
                    <i class="bi bi-building"></i> <strong>HÔTEL & RÉSIDENCES</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-4 text-center">
                            <div class="text-muted small">Réservations</div>
                            <div class="fs-4 fw-bold"><?= number_format($kpiHotel['nb_reservations']) ?></div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="text-muted small">Nuits</div>
                            <div class="fs-4 fw-bold"><?= number_format($kpiHotel['total_nuits']) ?></div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="text-muted small">CA</div>
                            <div class="fs-4 fw-bold text-success"><?= number_format($kpiHotel['ca_chambres']/1000000,1) ?>M</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-mortarboard"></i> <strong>FORMATION (IFP-KMS)</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-4 text-center">
                            <div class="text-muted small">Prospects</div>
                            <div class="fs-4 fw-bold"><?= number_format($kpiFormation['nb_prospects']) ?></div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="text-muted small">Inscriptions</div>
                            <div class="fs-4 fw-bold text-success"><?= number_format($kpiFormation['nb_inscriptions']) ?></div>
                        </div>
                        <div class="col-4 text-center">
                            <div class="text-muted small">CA</div>
                            <div class="fs-4 fw-bold text-success"><?= number_format($kpiFormation['ca_encaisse']/1000000,1) ?>M</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphiques -->
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Répartition CA par canal</h5>
                </div>
                <div class="card-body">
                    <canvas id="chartCA"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Taux de conversion par canal</h5>
                </div>
                <div class="card-body">
                    <canvas id="chartConversion" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tunnel conversion visuel -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-funnel"></i> Funnel de conversion global</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="funnel-stage bg-primary bg-opacity-10 p-3 rounded">
                                <i class="bi bi-eye fs-1 text-primary d-block mb-2"></i>
                                <h4><?= $kpiShowroom['nb_visiteurs'] + $kpiTerrain['nb_prospections'] + $kpiDigital['nb_leads'] ?></h4>
                                <small class="text-muted">Contacts</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="funnel-stage bg-info bg-opacity-10 p-3 rounded">
                                <i class="bi bi-file-earmark-text fs-1 text-info d-block mb-2"></i>
                                <h4><?= $kpiShowroom['nb_devis'] + $kpiTerrain['nb_devis'] + $kpiDigital['nb_devis'] ?></h4>
                                <small class="text-muted">Devis</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="funnel-stage bg-warning bg-opacity-10 p-3 rounded">
                                <i class="bi bi-cart-check fs-1 text-warning d-block mb-2"></i>
                                <h4><?= $kpiShowroom['nb_ventes'] + $kpiTerrain['nb_ventes'] + $kpiDigital['nb_convertis'] ?></h4>
                                <small class="text-muted">Ventes</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="funnel-stage bg-success bg-opacity-10 p-3 rounded">
                                <i class="bi bi-cash-stack fs-1 text-success d-block mb-2"></i>
                                <h4><?= number_format($ca_global/1000000,1) ?>M</h4>
                                <small class="text-muted">CA (FCFA)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const caData = {
    labels: ['Showroom', 'Terrain', 'Digital', 'Hôtel', 'Formation'],
    datasets: [{
        data: [<?= $kpiShowroom['ca_ttc'] ?>, <?= $kpiTerrain['ca_ttc'] ?>, <?= $kpiDigital['ca_ttc'] ?>, <?= $kpiHotel['ca_chambres'] ?>, <?= $kpiFormation['ca_encaisse'] ?>],
        backgroundColor: ['rgba(13,110,253,0.8)','rgba(13,202,240,0.8)','rgba(108,117,125,0.8)','rgba(255,193,7,0.8)','rgba(25,135,84,0.8)']
    }]
};

new Chart(document.getElementById('chartCA'), {
    type: 'doughnut',
    data: caData,
    options: {responsive: true, plugins: {legend: {position: 'bottom'}}}
});

new Chart(document.getElementById('chartConversion'), {
    type: 'bar',
    data: {
        labels: ['Showroom', 'Terrain', 'Digital'],
        datasets: [{
            label: 'Taux (%)',
            data: [<?= $taux_conversion_showroom ?>, <?= $taux_conversion_terrain ?>, <?= $taux_conversion_digital ?>],
            backgroundColor: ['rgba(13,110,253,0.8)','rgba(13,202,240,0.8)','rgba(108,117,125,0.8)']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {y: {beginAtZero: true, max: 100}},
        plugins: {legend: {display: false}}
    }
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>