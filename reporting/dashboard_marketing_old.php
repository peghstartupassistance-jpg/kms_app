<?php
// reporting/dashboard_marketing.php - Dashboard marketing consolidé
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('REPORTING_LIRE');

global $pdo;

$today = date('Y-m-d');
$periode = $_GET['periode'] ?? 'jour'; // jour, semaine, mois

// Calculer les dates selon la période
switch ($periode) {
    case 'semaine':
        $dateDebut = date('Y-m-d', strtotime('monday this week'));
        $dateFin = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'mois':
        $dateDebut = date('Y-m-01');
        $dateFin = date('Y-m-t');
        break;
    default: // jour
        $dateDebut = $today;
        $dateFin = $today;
        break;
}

// KPIs Showroom
$stmtShowroom = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT vs.id) as nb_visiteurs,
        COUNT(DISTINCT CASE WHEN d.id IS NOT NULL THEN vs.client_id END) as nb_convertis_devis,
        COUNT(DISTINCT CASE WHEN v.id IS NOT NULL THEN vs.client_id END) as nb_convertis_vente,
        (SELECT COUNT(*) FROM devis d WHERE d.canal_vente_id = 1 AND d.date_devis BETWEEN :d1 AND :d2) as nb_devis,
        (SELECT COUNT(*) FROM ventes v WHERE v.canal_vente_id = 1 AND v.date_vente BETWEEN :d3 AND :d4) as nb_ventes,
        (SELECT COALESCE(SUM(v.montant_total_ttc), 0) FROM ventes v WHERE v.canal_vente_id = 1 AND v.date_vente BETWEEN :d5 AND :d6) as ca_ttc
    FROM visiteurs_showroom vs
    LEFT JOIN devis d ON vs.client_id = d.client_id AND d.date_devis BETWEEN :d7 AND :d8
    LEFT JOIN ventes v ON vs.client_id = v.client_id AND v.date_vente BETWEEN :d9 AND :d10
    WHERE vs.date_visite BETWEEN :debut AND :fin
");
$stmtShowroom->execute([
    'debut' => $dateDebut, 'fin' => $dateFin,
    'd1' => $dateDebut, 'd2' => $dateFin,
    'd3' => $dateDebut, 'd4' => $dateFin,
    'd5' => $dateDebut, 'd6' => $dateFin,
    'd7' => $dateDebut, 'd8' => $dateFin,
    'd9' => $dateDebut, 'd10' => $dateFin
]);
$kpiShowroom = $stmtShowroom->fetch();

// KPIs Terrain
$stmtTerrain = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT pt.id) as nb_prospections,
        COUNT(DISTINCT CASE WHEN d.id IS NOT NULL THEN pt.client_id END) as nb_convertis_devis,
        COUNT(DISTINCT CASE WHEN v.id IS NOT NULL THEN pt.client_id END) as nb_convertis_vente,
        (SELECT COUNT(*) FROM devis d WHERE d.canal_vente_id = 2 AND d.date_devis BETWEEN :d1 AND :d2) as nb_devis,
        (SELECT COUNT(*) FROM ventes v WHERE v.canal_vente_id = 2 AND v.date_vente BETWEEN :d3 AND :d4) as nb_ventes,
        (SELECT COALESCE(SUM(v.montant_total_ttc), 0) FROM ventes v WHERE v.canal_vente_id = 2 AND v.date_vente BETWEEN :d5 AND :d6) as ca_ttc,
        (SELECT COUNT(*) FROM rendezvous_terrain rt WHERE rt.date_rdv BETWEEN :d7 AND :d8) as nb_rdv,
        (SELECT COUNT(*) FROM rendezvous_terrain rt WHERE rt.date_rdv BETWEEN :d9 AND :d10 AND rt.statut = 'HONORE') as nb_rdv_honores
    FROM prospections_terrain pt
    LEFT JOIN devis d ON pt.client_id = d.client_id AND d.date_devis BETWEEN :d11 AND :d12
    LEFT JOIN ventes v ON pt.client_id = v.client_id AND v.date_vente BETWEEN :d13 AND :d14
    WHERE pt.date_prospection BETWEEN :debut AND :fin
");
$stmtTerrain->execute([
    'debut' => $dateDebut, 'fin' => $dateFin,
    'd1' => $dateDebut, 'd2' => $dateFin,
    'd3' => $dateDebut, 'd4' => $dateFin,
    'd5' => $dateDebut, 'd6' => $dateFin,
    'd7' => $dateDebut, 'd8' => $dateFin,
    'd9' => $dateDebut, 'd10' => $dateFin,
    'd11' => $dateDebut, 'd12' => $dateFin,
    'd13' => $dateDebut, 'd14' => $dateFin
]);
$kpiTerrain = $stmtTerrain->fetch();

// KPIs Digital
$stmtDigital = $pdo->prepare("
    SELECT 
        COUNT(*) as nb_leads,
        SUM(CASE WHEN statut = 'NOUVEAU' THEN 1 ELSE 0 END) as nb_nouveaux,
        SUM(CASE WHEN statut = 'QUALIFIE' THEN 1 ELSE 0 END) as nb_qualifies,
        SUM(CASE WHEN statut = 'CONVERTI' THEN 1 ELSE 0 END) as nb_convertis,
        (SELECT COUNT(*) FROM devis d WHERE d.canal_vente_id = 3 AND d.date_devis BETWEEN :d1 AND :d2) as nb_devis,
        (SELECT COUNT(*) FROM ventes v WHERE v.canal_vente_id = 3 AND v.date_vente BETWEEN :d3 AND :d4) as nb_ventes,
        (SELECT COALESCE(SUM(v.montant_total_ttc), 0) FROM ventes v WHERE v.canal_vente_id = 3 AND v.date_vente BETWEEN :d5 AND :d6) as ca_ttc,
        COALESCE(SUM(cout_acquisition), 0) as cout_total_acquisition
    FROM leads_digital
    WHERE date_lead BETWEEN :debut AND :fin
");
$stmtDigital->execute([
    'debut' => $dateDebut, 'fin' => $dateFin,
    'd1' => $dateDebut, 'd2' => $dateFin,
    'd3' => $dateDebut, 'd4' => $dateFin,
    'd5' => $dateDebut, 'd6' => $dateFin
]);
$kpiDigital = $stmtDigital->fetch();

// KPIs Hôtel
$stmtHotel = $pdo->prepare("
    SELECT 
        COUNT(*) as nb_reservations,
        SUM(nb_nuits) as total_nuits,
        COALESCE(SUM(montant_total), 0) as ca_chambres,
        (SELECT COALESCE(SUM(montant), 0) FROM upsell_hotel u 
         INNER JOIN reservations_hotel r ON u.reservation_id = r.id 
         WHERE r.date_reservation BETWEEN :u1 AND :u2) as ca_upsell,
        (SELECT COUNT(*) FROM visiteurs_hotel vh WHERE vh.date_visite BETWEEN :v1 AND :v2) as nb_visiteurs
    FROM reservations_hotel
    WHERE date_reservation BETWEEN :debut AND :fin
");
$stmtHotel->execute([
    'debut' => $dateDebut, 'fin' => $dateFin,
    'u1' => $dateDebut, 'u2' => $dateFin,
    'v1' => $dateDebut, 'v2' => $dateFin
]);
$kpiHotel = $stmtHotel->fetch();

// KPIs Formation
$stmtFormation = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM prospects_formation pf WHERE pf.date_prospect BETWEEN :p1 AND :p2) as nb_prospects,
        COUNT(*) as nb_inscriptions,
        COALESCE(SUM(montant_paye), 0) as ca_encaisse,
        COALESCE(SUM(solde_du), 0) as solde_du
    FROM inscriptions_formation
    WHERE date_inscription BETWEEN :debut AND :fin
");
$stmtFormation->execute([
    'debut' => $dateDebut, 'fin' => $dateFin,
    'p1' => $dateDebut, 'p2' => $dateFin
]);
$kpiFormation = $stmtFormation->fetch();

// Calculs globaux
$ca_global = $kpiShowroom['ca_ttc'] + $kpiTerrain['ca_ttc'] + $kpiDigital['ca_ttc'] + 
             $kpiHotel['ca_chambres'] + $kpiHotel['ca_upsell'] + $kpiFormation['ca_encaisse'];

$taux_conversion_showroom = $kpiShowroom['nb_visiteurs'] > 0 ? 
    round($kpiShowroom['nb_ventes'] * 100 / $kpiShowroom['nb_visiteurs'], 1) : 0;
    
$taux_conversion_terrain = $kpiTerrain['nb_prospections'] > 0 ? 
    round($kpiTerrain['nb_ventes'] * 100 / $kpiTerrain['nb_prospections'], 1) : 0;
    
$taux_conversion_digital = $kpiDigital['nb_leads'] > 0 ? 
    round($kpiDigital['nb_convertis'] * 100 / $kpiDigital['nb_leads'], 1) : 0;

// Satisfaction moyenne
$stmtSatisfaction = $pdo->prepare("
    SELECT AVG(note) as moyenne, COUNT(*) as total
    FROM satisfaction_clients
    WHERE date_satisfaction BETWEEN :debut AND :fin
");
$stmtSatisfaction->execute(['debut' => $dateDebut, 'fin' => $dateFin]);
$satisfaction = $stmtSatisfaction->fetch();

// Litiges & ruptures
$stmtProblemes = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM retours_litiges WHERE date_retour BETWEEN :l1 AND :l2 AND statut_traitement = 'EN_COURS') as litiges_en_cours,
        (SELECT COUNT(*) FROM ruptures_signalees WHERE date_signalement BETWEEN :r1 AND :r2 AND statut_traitement IN ('SIGNALE', 'EN_COURS')) as ruptures_actives
");
$stmtProblemes->execute([
    'l1' => $dateDebut, 'l2' => $dateFin,
    'r1' => $dateDebut, 'r2' => $dateFin
]);
$problemes = $stmtProblemes->fetch();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0">
            <i class="bi bi-graph-up-arrow"></i> Dashboard Marketing
        </h1>
        
        <div class="btn-group" role="group">
            <a href="?periode=jour" class="btn btn-sm <?= $periode === 'jour' ? 'btn-primary' : 'btn-outline-primary' ?>">
                Aujourd'hui
            </a>
            <a href="?periode=semaine" class="btn btn-sm <?= $periode === 'semaine' ? 'btn-primary' : 'btn-outline-primary' ?>">
                Cette semaine
            </a>
            <a href="?periode=mois" class="btn btn-sm <?= $periode === 'mois' ? 'btn-primary' : 'btn-outline-primary' ?>">
                Ce mois
            </a>
        </div>
    </div>

    <div class="alert alert-light border">
        <strong>Période :</strong> du <?= date('d/m/Y', strtotime($dateDebut)) ?> au <?= date('d/m/Y', strtotime($dateFin)) ?>
    </div>

    <!-- KPIs Globaux -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card kms-card-hover border-success">
                <div class="card-body p-3">
                    <div class="text-muted small">CA Global</div>
                    <div class="fs-4 fw-bold text-success"><?= number_format($ca_global, 0, ',', ' ') ?> FCFA</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kms-card-hover">
                <div class="card-body p-3">
                    <div class="text-muted small">Satisfaction moyenne</div>
                    <div class="fs-4 fw-bold">
                        <?= $satisfaction['moyenne'] ? number_format($satisfaction['moyenne'], 1) : '-' ?> / 5
                        <small class="text-muted">(<?= $satisfaction['total'] ?> avis)</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kms-card-hover border-warning">
                <div class="card-body p-3">
                    <div class="text-muted small">Litiges en cours</div>
                    <div class="fs-4 fw-bold text-warning"><?= $problemes['litiges_en_cours'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kms-card-hover border-danger">
                <div class="card-body p-3">
                    <div class="text-muted small">Ruptures actives</div>
                    <div class="fs-4 fw-bold text-danger"><?= $problemes['ruptures_actives'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- SHOWROOM -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-shop-window"></i> <strong>SHOWROOM</strong>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <div class="text-muted small">Visiteurs</div>
                    <div class="fs-5 fw-bold"><?= number_format($kpiShowroom['nb_visiteurs']) ?></div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Devis</div>
                    <div class="fs-5 fw-bold"><?= number_format($kpiShowroom['nb_devis']) ?></div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Ventes</div>
                    <div class="fs-5 fw-bold text-success"><?= number_format($kpiShowroom['nb_ventes']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Chiffre d'affaires</div>
                    <div class="fs-5 fw-bold text-success"><?= number_format($kpiShowroom['ca_ttc'], 0, ',', ' ') ?> F</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Taux conversion</div>
                    <div class="fs-5 fw-bold"><?= $taux_conversion_showroom ?>%</div>
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar bg-success" style="width: <?= min($taux_conversion_showroom, 100) ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TERRAIN -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <i class="bi bi-geo-alt"></i> <strong>TERRAIN</strong>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <div class="text-muted small">Prospections</div>
                    <div class="fs-5 fw-bold"><?= number_format($kpiTerrain['nb_prospections']) ?></div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">RDV planifiés</div>
                    <div class="fs-5 fw-bold"><?= number_format($kpiTerrain['nb_rdv']) ?></div>
                    <small class="text-muted">(<?= $kpiTerrain['nb_rdv_honores'] ?> honorés)</small>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Devis</div>
                    <div class="fs-5 fw-bold"><?= number_format($kpiTerrain['nb_devis']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Ventes</div>
                    <div class="fs-5 fw-bold text-success"><?= number_format($kpiTerrain['nb_ventes']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Chiffre d'affaires</div>
                    <div class="fs-5 fw-bold text-success"><?= number_format($kpiTerrain['ca_ttc'], 0, ',', ' ') ?> F</div>
                </div>
            </div>
        </div>
    </div>

    <!-- DIGITAL -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <i class="bi bi-megaphone"></i> <strong>DIGITAL</strong>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <div class="text-muted small">Leads</div>
                    <div class="fs-5 fw-bold"><?= number_format($kpiDigital['nb_leads']) ?></div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Nouveaux</div>
                    <div class="fs-5 fw-bold text-warning"><?= number_format($kpiDigital['nb_nouveaux']) ?></div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Qualifiés</div>
                    <div class="fs-5 fw-bold text-primary"><?= number_format($kpiDigital['nb_qualifies']) ?></div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Convertis</div>
                    <div class="fs-5 fw-bold text-success"><?= number_format($kpiDigital['nb_convertis']) ?></div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">CA</div>
                    <div class="fs-5 fw-bold text-success"><?= number_format($kpiDigital['ca_ttc'], 0, ',', ' ') ?> F</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Coût acquisition</div>
                    <div class="fs-5 fw-bold"><?= number_format($kpiDigital['cout_total_acquisition'], 0, ',', ' ') ?> F</div>
                </div>
            </div>
        </div>
    </div>

    <!-- HÔTEL -->
    <div class="card mb-4">
        <div class="card-header bg-warning">
            <i class="bi bi-building"></i> <strong>HÔTEL & RÉSIDENCES</strong>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2">
                    <div class="text-muted small">Réservations</div>
                    <div class="fs-5 fw-bold"><?= number_format($kpiHotel['nb_reservations']) ?></div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Total nuits</div>
                    <div class="fs-5 fw-bold"><?= number_format($kpiHotel['total_nuits']) ?></div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Visiteurs</div>
                    <div class="fs-5 fw-bold"><?= number_format($kpiHotel['nb_visiteurs']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">CA Chambres</div>
                    <div class="fs-5 fw-bold text-success"><?= number_format($kpiHotel['ca_chambres'], 0, ',', ' ') ?> F</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">CA Upsell</div>
                    <div class="fs-5 fw-bold text-success"><?= number_format($kpiHotel['ca_upsell'], 0, ',', ' ') ?> F</div>
                </div>
            </div>
        </div>
    </div>

    <!-- FORMATION -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <i class="bi bi-mortarboard"></i> <strong>FORMATION (IFP-KMS)</strong>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Prospects</div>
                    <div class="fs-5 fw-bold"><?= number_format($kpiFormation['nb_prospects']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Inscriptions</div>
                    <div class="fs-5 fw-bold text-success"><?= number_format($kpiFormation['nb_inscriptions']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">CA Encaissé</div>
                    <div class="fs-5 fw-bold text-success"><?= number_format($kpiFormation['ca_encaisse'], 0, ',', ' ') ?> F</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Solde dû</div>
                    <div class="fs-5 fw-bold text-warning"><?= number_format($kpiFormation['solde_du'], 0, ',', ' ') ?> F</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphique répartition CA -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-pie-chart"></i> Répartition du chiffre d'affaires
        </div>
        <div class="card-body">
            <div class="row">
                <?php
                $repartition = [
                    'Showroom' => ['montant' => $kpiShowroom['ca_ttc'], 'color' => 'primary'],
                    'Terrain' => ['montant' => $kpiTerrain['ca_ttc'], 'color' => 'info'],
                    'Digital' => ['montant' => $kpiDigital['ca_ttc'], 'color' => 'secondary'],
                    'Hôtel' => ['montant' => $kpiHotel['ca_chambres'] + $kpiHotel['ca_upsell'], 'color' => 'warning'],
                    'Formation' => ['montant' => $kpiFormation['ca_encaisse'], 'color' => 'success']
                ];
                
                foreach ($repartition as $canal => $data):
                    $pct = $ca_global > 0 ? round($data['montant'] * 100 / $ca_global, 1) : 0;
                ?>
                    <div class="col-md-4 mb-3">
                        <div><strong><?= $canal ?></strong></div>
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar bg-<?= $data['color'] ?>" style="width: <?= $pct ?>%">
                                <?= $pct ?>%
                            </div>
                        </div>
                        <small class="text-muted"><?= number_format($data['montant'], 0, ',', ' ') ?> FCFA</small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
