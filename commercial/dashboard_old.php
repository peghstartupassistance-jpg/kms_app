<?php
// commercial/dashboard.php - Dashboard module commercial
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

$utilisateur = utilisateurConnecte();
$today = date('Y-m-d');
$debut_mois = date('Y-m-01');

// ═══════════════════════════════════════════════════════════════════════════════
// KPIs GLOBAUX
// ═══════════════════════════════════════════════════════════════════════════════

// Clients total
$stmt = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE statut = 'CLIENT'");
$total_clients = $stmt->fetch()['total'] ?? 0;

// Prospects actifs
$stmt = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE statut = 'PROSPECT'");
$total_prospects = $stmt->fetch()['total'] ?? 0;

// Devis du mois
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM devis WHERE date_devis >= ?");
$stmt->execute([$debut_mois]);
$devis_mois = $stmt->fetch()['total'] ?? 0;

// Devis en attente
$stmt = $pdo->query("SELECT COUNT(*) as total FROM devis WHERE statut IN ('ENVOYE', 'EN_COURS')");
$devis_en_attente = $stmt->fetch()['total'] ?? 0;

// Ventes du mois
$stmt = $pdo->prepare("SELECT COUNT(*) as total, COALESCE(SUM(montant_total_ttc), 0) as ca 
                       FROM ventes 
                       WHERE date_vente >= ? AND statut != 'ANNULEE'");
$stmt->execute([$debut_mois]);
$ventes_mois = $stmt->fetch();
$nb_ventes_mois = $ventes_mois['total'] ?? 0;
$ca_mois = $ventes_mois['ca'] ?? 0;

// Ventes du jour
$stmt = $pdo->prepare("SELECT COUNT(*) as total, COALESCE(SUM(montant_total_ttc), 0) as ca 
                       FROM ventes 
                       WHERE DATE(date_vente) = ? AND statut != 'ANNULEE'");
$stmt->execute([$today]);
$ventes_jour = $stmt->fetch();
$nb_ventes_jour = $ventes_jour['total'] ?? 0;
$ca_jour = $ventes_jour['ca'] ?? 0;

// Taux de conversion devis → ventes (mois)
$taux_conversion = $devis_mois > 0 ? round(($nb_ventes_mois / $devis_mois) * 100, 1) : 0;

// ═══════════════════════════════════════════════════════════════════════════════
// VENTES RÉCENTES
// ═══════════════════════════════════════════════════════════════════════════════

$stmt = $pdo->prepare("
    SELECT v.*, c.nom as client_nom, cv.libelle as canal_nom
    FROM ventes v
    JOIN clients c ON c.id = v.client_id
    JOIN canaux_vente cv ON cv.id = v.canal_vente_id
    WHERE DATE(v.date_vente) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY v.date_vente DESC
    LIMIT 10
");
$stmt->execute();
$ventes_recentes = $stmt->fetchAll();

// ═══════════════════════════════════════════════════════════════════════════════
// DEVIS À RELANCER
// ═══════════════════════════════════════════════════════════════════════════════

$stmt = $pdo->prepare("
    SELECT d.*, c.nom as client_nom, c.telephone, c.email,
           DATEDIFF(CURDATE(), d.date_devis) as jours_depuis
    FROM devis d
    JOIN clients c ON c.id = d.client_id
    WHERE d.statut IN ('ENVOYE', 'EN_COURS')
    AND DATEDIFF(CURDATE(), d.date_devis) >= 3
    ORDER BY d.date_devis ASC
    LIMIT 10
");
$stmt->execute();
$devis_a_relancer = $stmt->fetchAll();

// ═══════════════════════════════════════════════════════════════════════════════
// TOP 5 CLIENTS DU MOIS
// ═══════════════════════════════════════════════════════════════════════════════

$stmt = $pdo->prepare("
    SELECT c.nom, c.telephone, COUNT(v.id) as nb_ventes, SUM(v.montant_total_ttc) as ca_total
    FROM clients c
    JOIN ventes v ON v.client_id = c.id
    WHERE v.date_vente >= ? AND v.statut != 'ANNULEE'
    GROUP BY c.id
    ORDER BY ca_total DESC
    LIMIT 5
");
$stmt->execute([$debut_mois]);
$top_clients = $stmt->fetchAll();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="bi bi-briefcase"></i> Dashboard Commercial
        </h1>
        <div>
            <a href="<?= url_for('clients/list.php') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-people"></i> Clients
            </a>
            <a href="<?= url_for('devis/list.php') ?>" class="btn btn-outline-info btn-sm">
                <i class="bi bi-file-earmark-text"></i> Devis
            </a>
            <a href="<?= url_for('ventes/list.php') ?>" class="btn btn-outline-success btn-sm">
                <i class="bi bi-cart-check"></i> Ventes
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Clients</h6>
                            <h3 class="mb-0"><?= number_format($total_clients) ?></h3>
                            <small class="text-muted"><?= number_format($total_prospects) ?> prospects</small>
                        </div>
                        <i class="bi bi-people fs-1 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Devis (mois)</h6>
                            <h3 class="mb-0"><?= number_format($devis_mois) ?></h3>
                            <small class="text-muted"><?= number_format($devis_en_attente) ?> en attente</small>
                        </div>
                        <i class="bi bi-file-earmark-text fs-1 text-info"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Ventes (mois)</h6>
                            <h3 class="mb-0"><?= number_format($nb_ventes_mois) ?></h3>
                            <small class="text-success fw-bold"><?= number_format($ca_mois, 0, ',', ' ') ?> FCFA</small>
                        </div>
                        <i class="bi bi-cart-check fs-1 text-success"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Taux conversion</h6>
                            <h3 class="mb-0"><?= $taux_conversion ?>%</h3>
                            <small class="text-muted">devis → ventes</small>
                        </div>
                        <i class="bi bi-graph-up-arrow fs-1 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CA Aujourd'hui -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">Chiffre d'affaires du jour</h5>
                            <h2 class="mb-0 text-success fw-bold"><?= number_format($ca_jour, 0, ',', ' ') ?> FCFA</h2>
                            <small class="text-muted"><?= number_format($nb_ventes_jour) ?> vente(s) réalisée(s) aujourd'hui</small>
                        </div>
                        <i class="bi bi-calendar-check fs-1 text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Ventes récentes -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-cart-check"></i> Ventes récentes (7 derniers jours)</span>
                    <a href="<?= url_for('ventes/list.php') ?>" class="btn btn-sm btn-light">Tout voir</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($ventes_recentes)): ?>
                        <div class="p-3 text-center text-muted">
                            <i class="bi bi-inbox fs-1"></i>
                            <p class="mb-0">Aucune vente récente</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>N° Vente</th>
                                        <th>Client</th>
                                        <th>Canal</th>
                                        <th class="text-end">Montant</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ventes_recentes as $v): ?>
                                        <tr>
                                            <td><?= date('d/m', strtotime($v['date_vente'])) ?></td>
                                            <td><a href="<?= url_for('ventes/detail.php?id='.$v['id']) ?>" class="text-decoration-none"><?= htmlspecialchars($v['numero']) ?></a></td>
                                            <td><?= htmlspecialchars($v['client_nom']) ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($v['canal_nom']) ?></span></td>
                                            <td class="text-end fw-bold"><?= number_format($v['montant_total_ttc'], 0, ',', ' ') ?></td>
                                            <td>
                                                <?php
                                                $badge_class = match($v['statut']) {
                                                    'EN_ATTENTE_LIVRAISON' => 'warning',
                                                    'PARTIELLEMENT_LIVREE' => 'info',
                                                    'LIVREE' => 'success',
                                                    'ANNULEE' => 'danger',
                                                    default => 'secondary'
                                                };
                                                ?>
                                                <span class="badge bg-<?= $badge_class ?>"><?= htmlspecialchars($v['statut']) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Devis à relancer -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-exclamation-triangle"></i> Devis à relancer (> 3 jours)</span>
                    <a href="<?= url_for('devis/list.php') ?>" class="btn btn-sm btn-light">Tout voir</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($devis_a_relancer)): ?>
                        <div class="p-3 text-center text-muted">
                            <i class="bi bi-check-circle fs-1 text-success"></i>
                            <p class="mb-0">Aucun devis à relancer</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>N° Devis</th>
                                        <th>Client</th>
                                        <th>Contact</th>
                                        <th class="text-end">Montant</th>
                                        <th>Ancienneté</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($devis_a_relancer as $d): ?>
                                        <tr>
                                            <td><a href="<?= url_for('devis/edit.php?id='.$d['id']) ?>" class="text-decoration-none"><?= htmlspecialchars($d['numero_devis']) ?></a></td>
                                            <td><?= htmlspecialchars($d['client_nom']) ?></td>
                                            <td>
                                                <?php if ($d['telephone']): ?>
                                                    <small><i class="bi bi-telephone"></i> <?= htmlspecialchars($d['telephone']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end"><?= number_format($d['montant_total_ttc'], 0, ',', ' ') ?></td>
                                            <td><span class="badge bg-warning"><?= $d['jours_depuis'] ?> j</span></td>
                                            <td>
                                                <a href="<?= url_for('devis/edit.php?id='.$d['id']) ?>" class="btn btn-sm btn-outline-primary" title="Modifier">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 5 clients -->
    <?php if (!empty($top_clients)): ?>
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-trophy"></i> Top 5 Clients du mois
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Client</th>
                                        <th>Contact</th>
                                        <th class="text-center">Nb ventes</th>
                                        <th class="text-end">CA Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rank = 1; foreach ($top_clients as $tc): ?>
                                        <tr>
                                            <td>
                                                <?php if ($rank === 1): ?>
                                                    <i class="bi bi-trophy-fill text-warning fs-4"></i>
                                                <?php elseif ($rank === 2): ?>
                                                    <i class="bi bi-trophy-fill text-secondary fs-5"></i>
                                                <?php elseif ($rank === 3): ?>
                                                    <i class="bi bi-trophy-fill" style="color: #cd7f32;"></i>
                                                <?php else: ?>
                                                    <?= $rank ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?= htmlspecialchars($tc['nom']) ?></strong></td>
                                            <td><?= htmlspecialchars($tc['telephone'] ?? '-') ?></td>
                                            <td class="text-center"><span class="badge bg-info"><?= $tc['nb_ventes'] ?></span></td>
                                            <td class="text-end fw-bold text-success"><?= number_format($tc['ca_total'], 0, ',', ' ') ?> FCFA</td>
                                        </tr>
                                        <?php $rank++; ?>
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
