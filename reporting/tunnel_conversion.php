<?php
// reporting/tunnel_conversion.php - Vue tunnel de conversion
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('REPORTING_LIRE');

global $pdo;

// Statistiques clients par statut
$statsClients = $pdo->query("
    SELECT 
        statut,
        COUNT(*) as count,
        COUNT(DISTINCT type_client_id) as nb_types
    FROM clients
    GROUP BY statut
    ORDER BY 
        CASE statut
            WHEN 'PROSPECT' THEN 1
            WHEN 'CLIENT' THEN 2
            WHEN 'APPRENANT' THEN 3
            WHEN 'HOTE' THEN 4
        END
")->fetchAll();

// Statistiques devis par statut
$statsDevis = $pdo->query("
    SELECT 
        statut,
        COUNT(*) as count,
        SUM(montant_total_ttc) as montant_total,
        AVG(montant_total_ttc) as montant_moyen
    FROM devis
    GROUP BY statut
    ORDER BY 
        CASE statut
            WHEN 'EN_ATTENTE' THEN 1
            WHEN 'ACCEPTE' THEN 2
            WHEN 'REFUSE' THEN 3
            WHEN 'ANNULE' THEN 4
        END
")->fetchAll();

// Taux de conversion devis
$totalDevis = array_sum(array_column($statsDevis, 'count'));
$devisAcceptes = 0;
foreach ($statsDevis as $stat) {
    if ($stat['statut'] === 'ACCEPTE') {
        $devisAcceptes = $stat['count'];
        break;
    }
}
$tauxConversionDevis = $totalDevis > 0 ? ($devisAcceptes / $totalDevis * 100) : 0;

// Statistiques prospections terrain
$statsProspections = $pdo->query("
    SELECT 
        resultat,
        COUNT(*) as count
    FROM prospections_terrain
    WHERE resultat IS NOT NULL AND resultat != ''
    GROUP BY resultat
    ORDER BY count DESC
")->fetchAll();

// Conversion prospect → client (dernier mois)
$conversionProspects = $pdo->query("
    SELECT 
        COUNT(CASE WHEN statut = 'PROSPECT' THEN 1 END) as nb_prospects,
        COUNT(CASE WHEN statut = 'CLIENT' THEN 1 END) as nb_clients,
        COUNT(CASE WHEN statut = 'PROSPECT' AND DATE(date_creation) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as nouveaux_prospects_30j,
        COUNT(CASE WHEN statut = 'CLIENT' AND DATE(date_creation) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as nouveaux_clients_30j
    FROM clients
")->fetch();

// Évolution mensuelle (3 derniers mois)
$evolutionMensuelle = $pdo->query("
    SELECT 
        DATE_FORMAT(date_creation, '%Y-%m') as mois,
        COUNT(CASE WHEN statut = 'PROSPECT' THEN 1 END) as prospects,
        COUNT(CASE WHEN statut = 'CLIENT' THEN 1 END) as clients
    FROM clients
    WHERE date_creation >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    GROUP BY DATE_FORMAT(date_creation, '%Y-%m')
    ORDER BY mois DESC
")->fetchAll();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="list-page-header">
        <h1 class="list-page-title h3">
            <i class="bi bi-funnel-fill text-primary"></i>
            Tunnel de conversion
        </h1>
        <p class="text-muted">Visualisation du parcours clients et performances commerciales</p>
    </div>

    <!-- Métriques clés -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                                <i class="bi bi-person-plus fs-3 text-warning"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Prospects actifs</h6>
                            <h3 class="mb-0"><?= number_format($conversionProspects['nb_prospects'] ?? 0, 0, ',', ' ') ?></h3>
                        </div>
                    </div>
                    <small class="text-success">
                        <i class="bi bi-plus-circle me-1"></i>
                        <?= $conversionProspects['nouveaux_prospects_30j'] ?? 0 ?> ce mois
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3">
                                <i class="bi bi-person-check fs-3 text-success"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Clients convertis</h6>
                            <h3 class="mb-0"><?= number_format($conversionProspects['nb_clients'] ?? 0, 0, ',', ' ') ?></h3>
                        </div>
                    </div>
                    <small class="text-success">
                        <i class="bi bi-plus-circle me-1"></i>
                        <?= $conversionProspects['nouveaux_clients_30j'] ?? 0 ?> ce mois
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-info bg-opacity-10 p-3">
                                <i class="bi bi-file-earmark-check fs-3 text-info"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Devis acceptés</h6>
                            <h3 class="mb-0"><?= number_format($devisAcceptes, 0, ',', ' ') ?></h3>
                        </div>
                    </div>
                    <small class="text-muted">
                        sur <?= number_format($totalDevis, 0, ',', ' ') ?> total
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                                <i class="bi bi-percent fs-3 text-primary"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Taux conversion devis</h6>
                            <h3 class="mb-0"><?= number_format($tauxConversionDevis, 1, ',', ' ') ?>%</h3>
                        </div>
                    </div>
                    <small class="text-muted">
                        Performance commerciale
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tunnel de conversion visuel -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-people me-2 text-warning"></i>
                        Pipeline Clients
                    </h5>
                </div>
                <div class="card-body">
                    <?php 
                    $totalClients = array_sum(array_column($statsClients, 'count'));
                    foreach ($statsClients as $stat): 
                        $pct = $totalClients > 0 ? ($stat['count'] / $totalClients * 100) : 0;
                        
                        $color = 'secondary';
                        $icon = 'bi-circle';
                        if ($stat['statut'] === 'PROSPECT') {
                            $color = 'warning';
                            $icon = 'bi-person-plus';
                        } elseif ($stat['statut'] === 'CLIENT') {
                            $color = 'success';
                            $icon = 'bi-person-check';
                        } elseif ($stat['statut'] === 'APPRENANT') {
                            $color = 'info';
                            $icon = 'bi-mortarboard';
                        } elseif ($stat['statut'] === 'HOTE') {
                            $color = 'primary';
                            $icon = 'bi-house-door';
                        }
                    ?>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <i class="<?= $icon ?> me-2 text-<?= $color ?>"></i>
                                    <strong><?= htmlspecialchars($stat['statut']) ?></strong>
                                    <span class="text-muted ms-2">(<?= (int)$stat['count'] ?>)</span>
                                </div>
                                <span class="badge bg-<?= $color ?>">
                                    <?= number_format($pct, 1, ',', ' ') ?>%
                                </span>
                            </div>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-<?= $color ?>" 
                                     role="progressbar" 
                                     style="width: <?= $pct ?>%"
                                     aria-valuenow="<?= $pct ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-file-earmark-text me-2 text-info"></i>
                        Pipeline Devis
                    </h5>
                </div>
                <div class="card-body">
                    <?php 
                    foreach ($statsDevis as $stat): 
                        $pct = $totalDevis > 0 ? ($stat['count'] / $totalDevis * 100) : 0;
                        
                        $color = 'secondary';
                        $icon = 'bi-hourglass';
                        $libelle = $stat['statut'];
                        
                        if ($stat['statut'] === 'EN_ATTENTE') {
                            $color = 'secondary';
                            $icon = 'bi-clock';
                            $libelle = 'En attente';
                        } elseif ($stat['statut'] === 'ACCEPTE') {
                            $color = 'success';
                            $icon = 'bi-check-circle';
                            $libelle = 'Acceptés';
                        } elseif ($stat['statut'] === 'REFUSE') {
                            $color = 'danger';
                            $icon = 'bi-x-circle';
                            $libelle = 'Refusés';
                        } elseif ($stat['statut'] === 'ANNULE') {
                            $color = 'dark';
                            $icon = 'bi-slash-circle';
                            $libelle = 'Annulés';
                        }
                    ?>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <i class="<?= $icon ?> me-2 text-<?= $color ?>"></i>
                                    <strong><?= htmlspecialchars($libelle) ?></strong>
                                    <span class="text-muted ms-2">(<?= (int)$stat['count'] ?>)</span>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?= $color ?> me-2">
                                        <?= number_format($pct, 1, ',', ' ') ?>%
                                    </span>
                                    <small class="text-muted">
                                        <?= number_format($stat['montant_total'] ?? 0, 0, ',', ' ') ?> FCFA
                                    </small>
                                </div>
                            </div>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-<?= $color ?>" 
                                     role="progressbar" 
                                     style="width: <?= $pct ?>%"
                                     aria-valuenow="<?= $pct ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Prospections terrain -->
    <?php if (!empty($statsProspections)): ?>
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-geo-alt me-2 text-danger"></i>
                        Résultats prospections terrain
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        $totalProspections = array_sum(array_column($statsProspections, 'count'));
                        foreach ($statsProspections as $stat): 
                            $pct = $totalProspections > 0 ? ($stat['count'] / $totalProspections * 100) : 0;
                        ?>
                            <div class="col-md-4 mb-3">
                                <div class="card border">
                                    <div class="card-body">
                                        <h6 class="card-title text-truncate" title="<?= htmlspecialchars($stat['resultat']) ?>">
                                            <?= htmlspecialchars($stat['resultat']) ?>
                                        </h6>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fs-4 fw-bold text-primary"><?= (int)$stat['count'] ?></span>
                                            <span class="badge bg-secondary"><?= number_format($pct, 1, ',', ' ') ?>%</span>
                                        </div>
                                        <div class="progress mt-2" style="height: 6px;">
                                            <div class="progress-bar bg-primary" 
                                                 style="width: <?= $pct ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Évolution temporelle -->
    <?php if (!empty($evolutionMensuelle)): ?>
    <div class="row g-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up me-2 text-success"></i>
                        Évolution mensuelle (3 derniers mois)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Mois</th>
                                    <th class="text-center">Nouveaux prospects</th>
                                    <th class="text-center">Nouveaux clients</th>
                                    <th class="text-center">Taux de conversion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($evolutionMensuelle as $evo): 
                                    $total = $evo['prospects'] + $evo['clients'];
                                    $tauxConv = $total > 0 ? ($evo['clients'] / $total * 100) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <i class="bi bi-calendar3 me-2 text-muted"></i>
                                            <strong><?= date('F Y', strtotime($evo['mois'] . '-01')) ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-warning"><?= (int)$evo['prospects'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success"><?= (int)$evo['clients'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="progress" style="height: 20px; width: 150px; margin: 0 auto;">
                                                <div class="progress-bar bg-success" 
                                                     style="width: <?= $tauxConv ?>%"
                                                     title="<?= number_format($tauxConv, 1, ',', ' ') ?>%">
                                                    <?= number_format($tauxConv, 0, ',', ' ') ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
