<?php
// commercial/dashboard.php - Dashboard commercial enrichi
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');
global $pdo;

$today = date('Y-m-d');
$debut_mois = date('Y-m-01');
$debut_annee = date('Y-01-01');

// Stats clients
$stmt = $pdo->query("SELECT statut, COUNT(*) as count FROM clients GROUP BY statut");
$clients_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_clients = $clients_stats['CLIENT'] ?? 0;
$total_prospects = $clients_stats['PROSPECT'] ?? 0;

// Stats devis mois
$stmt = $pdo->query("SELECT statut, COUNT(*) as count FROM devis WHERE date_devis >= '$debut_mois' GROUP BY statut");
$devis_stats = [];
while ($row = $stmt->fetch()) { $devis_stats[$row['statut']] = $row['count']; }
$devis_mois = array_sum($devis_stats);
$devis_acceptes = $devis_stats['ACCEPTE'] ?? 0;
$devis_en_attente = $devis_stats['EN_ATTENTE'] ?? 0;

// Ventes mois
$stmt = $pdo->query("SELECT COUNT(*) as nb, COALESCE(SUM(montant_total_ttc),0) as ca FROM ventes WHERE date_vente >= '$debut_mois' AND statut != 'ANNULEE'");
$vm = $stmt->fetch();
$nb_ventes_mois = $vm['nb'];
$ca_mois = $vm['ca'];

// Ventes jour
$stmt = $pdo->query("SELECT COUNT(*) as nb, COALESCE(SUM(montant_total_ttc),0) as ca FROM ventes WHERE DATE(date_vente) = '$today' AND statut != 'ANNULEE'");
$vj = $stmt->fetch();
$nb_ventes_jour = $vj['nb'];
$ca_jour = $vj['ca'];

// CA année
$stmt = $pdo->query("SELECT COALESCE(SUM(montant_total_ttc),0) as ca FROM ventes WHERE date_vente >= '$debut_annee' AND statut != 'ANNULEE'");
$ca_annee = $stmt->fetch()['ca'];

$taux_conversion = $devis_mois > 0 ? round(($nb_ventes_mois / $devis_mois) * 100, 1) : 0;
$panier_moyen = $nb_ventes_mois > 0 ? $ca_mois / $nb_ventes_mois : 0;

// Évolution CA 6 mois
$stmt = $pdo->query("
    SELECT DATE_FORMAT(date_vente, '%b') as mois, SUM(montant_total_ttc) as ca
    FROM ventes WHERE date_vente >= DATE_SUB(NOW(), INTERVAL 6 MONTH) AND statut != 'ANNULEE'
    GROUP BY DATE_FORMAT(date_vente, '%Y-%m'), DATE_FORMAT(date_vente, '%b')
    ORDER BY DATE_FORMAT(date_vente, '%Y-%m')
");
$evolution_ca = $stmt->fetchAll();

// Ventes par canal
$stmt = $pdo->query("
    SELECT cv.libelle, COUNT(v.id) as count, SUM(v.montant_total_ttc) as ca
    FROM ventes v JOIN canaux_vente cv ON cv.id = v.canal_vente_id
    WHERE v.date_vente >= '$debut_mois' AND v.statut != 'ANNULEE'
    GROUP BY cv.id, cv.libelle ORDER BY ca DESC
");
$ventes_par_canal = $stmt->fetchAll();

// Ventes récentes
$stmt = $pdo->query("
    SELECT v.*, c.nom as client_nom, cv.libelle as canal_nom, u.nom_complet as commercial
    FROM ventes v
    JOIN clients c ON c.id = v.client_id
    JOIN canaux_vente cv ON cv.id = v.canal_vente_id
    JOIN utilisateurs u ON u.id = v.utilisateur_id
    WHERE DATE(v.date_vente) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY v.date_vente DESC LIMIT 10
");
$ventes_recentes = $stmt->fetchAll();

// Devis à relancer
$stmt = $pdo->query("
    SELECT d.*, c.nom as client_nom, c.telephone, DATEDIFF(CURDATE(), d.date_devis) as jours
    FROM devis d JOIN clients c ON c.id = d.client_id
    WHERE d.statut = 'EN_ATTENTE' AND DATEDIFF(CURDATE(), d.date_devis) >= 3
    ORDER BY d.date_devis ASC LIMIT 10
");
$devis_a_relancer = $stmt->fetchAll();

// Top clients
$stmt = $pdo->query("
    SELECT c.nom, COUNT(v.id) as nb, SUM(v.montant_total_ttc) as ca
    FROM clients c JOIN ventes v ON v.client_id = c.id
    WHERE v.date_vente >= '$debut_mois' AND v.statut != 'ANNULEE'
    GROUP BY c.id ORDER BY ca DESC LIMIT 5
");
$top_clients = $stmt->fetchAll();

// Top commerciaux
$stmt = $pdo->query("
    SELECT u.nom_complet, COUNT(v.id) as nb, SUM(v.montant_total_ttc) as ca
    FROM ventes v JOIN utilisateurs u ON u.id = v.utilisateur_id
    WHERE v.date_vente >= '$debut_mois' AND v.statut != 'ANNULEE'
    GROUP BY u.id ORDER BY ca DESC LIMIT 5
");
$top_commerciaux = $stmt->fetchAll();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-briefcase text-primary"></i> Dashboard Commercial</h1>
            <p class="text-muted small mb-0">Vue d'ensemble des performances commerciales</p>
        </div>
        <div class="btn-group">
            <a href="<?= url_for('clients/list.php') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-people"></i> Clients</a>
            <a href="<?= url_for('devis/list.php') ?>" class="btn btn-outline-info btn-sm"><i class="bi bi-file-earmark-text"></i> Devis</a>
            <a href="<?= url_for('ventes/list.php') ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-cart-check"></i> Ventes</a>
            <a href="<?= url_for('reporting/tunnel_conversion.php') ?>" class="btn btn-warning btn-sm"><i class="bi bi-funnel"></i> Tunnel</a>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                                <i class="bi bi-people fs-2 text-primary"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Clients actifs</h6>
                            <h3 class="mb-0"><?= number_format($total_clients) ?></h3>
                            <small class="text-muted"><?= number_format($total_prospects) ?> prospects</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-info bg-opacity-10 p-3">
                                <i class="bi bi-file-earmark-text fs-2 text-info"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Devis (mois)</h6>
                            <h3 class="mb-0"><?= number_format($devis_mois) ?></h3>
                            <small class="text-warning"><?= $devis_en_attente ?> en attente</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-success bg-gradient">
                <div class="card-body text-white">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-cart-check fs-1 me-3"></i>
                        <div>
                            <h6 class="mb-0 small opacity-75">CA du mois</h6>
                            <h3 class="mb-0"><?= number_format($ca_mois/1000000,1) ?>M</h3>
                            <small class="opacity-75"><?= $nb_ventes_mois ?> ventes</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                                <i class="bi bi-graph-up-arrow fs-2 text-warning"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Conversion</h6>
                            <h3 class="mb-0"><?= $taux_conversion ?>%</h3>
                            <div class="progress mt-2" style="height:6px">
                                <div class="progress-bar bg-warning" style="width:<?= $taux_conversion ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CA Aujourd'hui -->
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">CA du jour</h6>
                            <h2 class="text-success fw-bold"><?= number_format($ca_jour,0,',',' ') ?> FCFA</h2>
                            <small class="text-muted"><?= $nb_ventes_jour ?> vente(s) aujourd'hui</small>
                        </div>
                        <i class="bi bi-calendar-check display-3 text-success opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body">
                    <h6 class="opacity-75">CA Annuel 2025</h6>
                    <h3><?= number_format($ca_annee/1000000,2) ?>M FCFA</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphiques -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-graph-up"></i> Évolution CA (6 mois)</h5></div>
                <div class="card-body"><canvas id="chartCA" height="100"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-pie-chart"></i> Par canal</h5></div>
                <div class="card-body"><canvas id="chartCanal"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Tables -->
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white"><i class="bi bi-cart-check"></i> Ventes récentes (7j)</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light"><tr><th>Date</th><th>N°</th><th>Client</th><th class="text-end">Montant</th><th>Statut</th></tr></thead>
                            <tbody>
                            <?php if(empty($ventes_recentes)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Aucune vente</td></tr>
                            <?php else: foreach($ventes_recentes as $v): ?>
                                <tr>
                                    <td><?= date('d/m',strtotime($v['date_vente'])) ?></td>
                                    <td><a href="<?= url_for('ventes/detail.php?id='.$v['id']) ?>"><?= htmlspecialchars($v['numero']) ?></a></td>
                                    <td><?= htmlspecialchars($v['client_nom']) ?></td>
                                    <td class="text-end fw-bold"><?= number_format($v['montant_total_ttc'],0,',',' ') ?></td>
                                    <td><span class="badge bg-<?= $v['statut']=='LIVREE'?'success':($v['statut']=='ANNULEE'?'danger':'warning') ?>"><?= $v['statut'] ?></span></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-warning"><i class="bi bi-exclamation-triangle"></i> Devis à relancer</div>
                <div class="card-body p-0" style="max-height:400px;overflow-y:auto">
                    <?php if(empty($devis_a_relancer)): ?>
                        <p class="text-center py-4 text-muted"><i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>Aucun devis</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                        <?php foreach($devis_a_relancer as $d): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <h6><a href="<?= url_for('devis/edit.php?id='.$d['id']) ?>"><?= htmlspecialchars($d['numero']) ?></a></h6>
                                    <span class="badge bg-warning"><?= $d['jours'] ?>j</span>
                                </div>
                                <p class="mb-1 small"><?= htmlspecialchars($d['client_nom']) ?></p>
                                <strong><?= number_format($d['montant_total_ttc'],0,',',' ') ?> FCFA</strong>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Top performers -->
    <div class="row g-3 mt-3">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white"><i class="bi bi-trophy"></i> Top 5 Clients</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <tbody>
                        <?php $r=1; foreach($top_clients as $tc): ?>
                            <tr>
                                <td><?= $r==1?'🥇':($r==2?'🥈':($r==3?'🥉':$r)) ?></td>
                                <td><strong><?= htmlspecialchars($tc['nom']) ?></strong></td>
                                <td class="text-center"><span class="badge bg-info"><?= $tc['nb'] ?></span></td>
                                <td class="text-end fw-bold text-success"><?= number_format($tc['ca'],0,',',' ') ?></td>
                            </tr>
                        <?php $r++; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white"><i class="bi bi-star"></i> Top 5 Commerciaux</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <tbody>
                        <?php $r=1; foreach($top_commerciaux as $tc): ?>
                            <tr>
                                <td><?= $r==1?'🥇':($r==2?'🥈':($r==3?'🥉':$r)) ?></td>
                                <td><strong><?= htmlspecialchars($tc['nom_complet']) ?></strong></td>
                                <td class="text-center"><span class="badge bg-info"><?= $tc['nb'] ?></span></td>
                                <td class="text-end fw-bold text-success"><?= number_format($tc['ca'],0,',',' ') ?></td>
                            </tr>
                        <?php $r++; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const evolutionCA = <?= json_encode($evolution_ca) ?>;
const ventesCanal = <?= json_encode($ventes_par_canal) ?>;

new Chart(document.getElementById('chartCA'), {
    type: 'line',
    data: {
        labels: evolutionCA.map(e => e.mois),
        datasets: [{label: 'CA', data: evolutionCA.map(e => e.ca), borderColor: 'rgb(75,192,192)', backgroundColor: 'rgba(75,192,192,0.1)', tension: 0.4, fill: true}]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {legend: {display: false}},
        scales: {y: {ticks: {callback: v => (v/1000000).toFixed(1)+'M'}}}
    }
});

new Chart(document.getElementById('chartCanal'), {
    type: 'doughnut',
    data: {
        labels: ventesCanal.map(v => v.libelle),
        datasets: [{data: ventesCanal.map(v => v.ca), backgroundColor: ['rgba(255,99,132,0.8)','rgba(54,162,235,0.8)','rgba(255,206,86,0.8)','rgba(75,192,192,0.8)','rgba(153,102,255,0.8)']}]
    },
    options: {responsive: true, plugins: {legend: {position: 'bottom'}}}
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>