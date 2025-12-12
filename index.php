<?php
// index.php - Dashboard principal KMS Gestion
require_once __DIR__ . '/security.php';
exigerConnexion();

global $pdo;
$utilisateur = utilisateurConnecte();
$today = date('Y-m-d');
$permissions = $_SESSION['permissions'] ?? [];

// ═══════════════════════════════════════════════════════════════════════════════
// REQUÊTES KPIs PRINCIPAUX
// ═══════════════════════════════════════════════════════════════════════════════

// Visiteurs showroom du jour
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM visiteurs_showroom WHERE DATE(date_visite) = CURDATE()");
$stmt->execute();
$visiteurs_jour = $stmt->fetch()['total'] ?? 0;

// Devis du jour
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM devis WHERE DATE(date_devis) = CURDATE()");
$stmt->execute();
$devis_jour = $stmt->fetch()['total'] ?? 0;

// Ventes du jour
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ventes WHERE DATE(date_vente) = CURDATE()");
$stmt->execute();
$ventes_jour = $stmt->fetch()['total'] ?? 0;

// CA du jour
$stmt = $pdo->prepare("SELECT SUM(montant_total_ttc) as total FROM ventes WHERE DATE(date_vente) = CURDATE() AND statut != 'ANNULEE'");
$stmt->execute();
$ca_jour = $stmt->fetch()['total'] ?? 0;

// Taux occupation hôtel
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT c.id) as total_chambres,
        COUNT(DISTINCT CASE WHEN r.statut = 'EN_COURS' AND CURDATE() BETWEEN r.date_debut AND r.date_fin THEN r.chambre_id END) as chambres_occupees
    FROM chambres c
    LEFT JOIN reservations_hotel r ON c.id = r.chambre_id
    WHERE c.actif = 1
");
$stmt->execute();
$occ = $stmt->fetch();
$taux_occupation = $occ['total_chambres'] > 0 ? round(($occ['chambres_occupees'] / $occ['total_chambres']) * 100, 1) : 0;

// ═══════════════════════════════════════════════════════════════════════════════
// ALERTES & NOTIFICATIONS
// ═══════════════════════════════════════════════════════════════════════════════

// Produits en rupture
$stmt = $pdo->query("SELECT COUNT(*) as total FROM produits WHERE stock_actuel = 0 AND actif = 1");
$ruptures = $stmt->fetch()['total'] ?? 0;

// Produits en alerte
$stmt = $pdo->query("SELECT COUNT(*) as total FROM produits WHERE stock_actuel > 0 AND stock_actuel <= seuil_alerte AND actif = 1");
$alertes_stock = $stmt->fetch()['total'] ?? 0;

// Ordres de préparation en attente
$stmt = $pdo->query("SELECT COUNT(*) as total FROM ordres_preparation WHERE statut = 'EN_ATTENTE'");
$ordres_attente = $stmt->fetch()['total'] ?? 0;

// Bons de livraison non signés
$stmt = $pdo->query("SELECT COUNT(*) as total FROM bons_livraison WHERE signe_client = 0");
$bl_non_signes = $stmt->fetch()['total'] ?? 0;

// Devis à relancer (date relance dépassée ou proche)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM devis 
    WHERE statut IN ('ENVOYE', 'EN_COURS') 
    AND (date_relance IS NULL OR date_relance <= DATE_ADD(CURDATE(), INTERVAL 3 DAY))
");
$stmt->execute();
$devis_relancer = $stmt->fetch()['total'] ?? 0;

// Pièces comptables à valider
$stmt = $pdo->query("SELECT COUNT(*) as total FROM compta_pieces WHERE est_validee = 0");
$pieces_valider = $stmt->fetch()['total'] ?? 0;

// ═══════════════════════════════════════════════════════════════════════════════
// STATISTIQUES PÉRIODE (7 JOURS)
// ═══════════════════════════════════════════════════════════════════════════════

// CA 7 derniers jours
$stmt = $pdo->prepare("
    SELECT SUM(montant_total_ttc) as total 
    FROM ventes 
    WHERE date_vente >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
    AND statut != 'ANNULEE'
");
$stmt->execute();
$ca_7j = $stmt->fetch()['total'] ?? 0;

// Nouveaux clients (7j)
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM clients WHERE date_creation >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute();
$nouveaux_clients = $stmt->fetch()['total'] ?? 0;

// Mouvements stock (7j)
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM stocks_mouvements WHERE date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute();
$mouvements_7j = $stmt->fetch()['total'] ?? 0;

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<div class="container-fluid">
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- EN-TÊTE -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">Tableau de bord KMS</h1>
            <p class="text-muted mb-0">
                <i class="bi bi-calendar3"></i> <?= strftime('%A %d %B %Y', strtotime($today)) ?>
                <span class="ms-3"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($utilisateur['nom_complet']) ?></span>
            </p>
        </div>
        <div>
            <span class="badge bg-success fs-6">
                <i class="bi bi-circle-fill"></i> Système opérationnel
            </span>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- KPIs PRINCIPAUX -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-start border-primary border-4 kms-card-hover">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold">Visiteurs showroom (jour)</div>
                            <div class="fs-2 fw-bold text-primary"><?= $visiteurs_jour ?></div>
                            <small class="text-muted">
                                <i class="bi bi-clock-history"></i> Temps réel
                            </small>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded p-3">
                            <i class="bi bi-shop fs-1 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-start border-success border-4 kms-card-hover">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold">Ventes du jour</div>
                            <div class="fs-2 fw-bold text-success"><?= $ventes_jour ?></div>
                            <small class="text-success">
                                <i class="bi bi-arrow-up"></i> <?= number_format($ca_jour, 0, ',', ' ') ?> F
                            </small>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded p-3">
                            <i class="bi bi-cart-check fs-1 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-start border-warning border-4 kms-card-hover">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold">Devis du jour</div>
                            <div class="fs-2 fw-bold text-warning"><?= $devis_jour ?></div>
                            <small class="text-muted">
                                <i class="bi bi-file-earmark-text"></i> En cours
                            </small>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded p-3">
                            <i class="bi bi-file-earmark-text fs-1 text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-start border-info border-4 kms-card-hover">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold">Occupation hôtel</div>
                            <div class="fs-2 fw-bold text-info"><?= $taux_occupation ?> %</div>
                            <small class="text-muted">
                                <i class="bi bi-building"></i> <?= $occ['chambres_occupees'] ?> / <?= $occ['total_chambres'] ?> chambres
                            </small>
                        </div>
                        <div class="bg-info bg-opacity-10 rounded p-3">
                            <i class="bi bi-building fs-1 text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- ALERTES & ACTIONS URGENTES -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <?php if ($ruptures > 0 || $alertes_stock > 0 || $ordres_attente > 0 || $devis_relancer > 0 || $bl_non_signes > 0 || $pieces_valider > 0): ?>
    <div class="alert alert-warning border-warning d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
        <div class="flex-grow-1">
            <h5 class="alert-heading mb-2">Actions urgentes requises</h5>
            <div class="row g-2">
                <?php if ($ruptures > 0): ?>
                <div class="col-md-4">
                    <a href="<?= url_for('stock/alertes.php') ?>" class="btn btn-sm btn-outline-danger w-100">
                        <i class="bi bi-exclamation-circle"></i> <?= $ruptures ?> rupture(s) stock
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($alertes_stock > 0): ?>
                <div class="col-md-4">
                    <a href="<?= url_for('stock/alertes.php') ?>" class="btn btn-sm btn-outline-warning w-100">
                        <i class="bi bi-exclamation-triangle"></i> <?= $alertes_stock ?> alerte(s) stock
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($ordres_attente > 0): ?>
                <div class="col-md-4">
                    <a href="<?= url_for('coordination/ordres_preparation.php') ?>" class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-list-check"></i> <?= $ordres_attente ?> ordre(s) à préparer
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($bl_non_signes > 0): ?>
                <div class="col-md-4">
                    <a href="<?= url_for('livraisons/list.php') ?>" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-truck"></i> <?= $bl_non_signes ?> BL non signé(s)
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($devis_relancer > 0): ?>
                <div class="col-md-4">
                    <a href="<?= url_for('reporting/relances_devis.php') ?>" class="btn btn-sm btn-outline-info w-100">
                        <i class="bi bi-clock-history"></i> <?= $devis_relancer ?> devis à relancer
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($pieces_valider > 0): ?>
                <div class="col-md-4">
                    <a href="<?= url_for('compta/index.php') ?>" class="btn btn-sm btn-outline-success w-100">
                        <i class="bi bi-check-circle"></i> <?= $pieces_valider ?> pièce(s) comptable(s)
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- WIDGETS PRINCIPAUX -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        
        <!-- WIDGET : Performance commerciale -->
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-graph-up-arrow"></i> Performance commerciale (7 jours)
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="text-muted small">Chiffre d'affaires</div>
                                <div class="fs-4 fw-bold text-success"><?= number_format($ca_7j, 0, ',', ' ') ?> F</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="text-muted small">Nouveaux clients</div>
                                <div class="fs-4 fw-bold text-primary"><?= $nouveaux_clients ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="text-muted small">Mouvements stock</div>
                                <div class="fs-4 fw-bold text-info"><?= $mouvements_7j ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="text-muted small">Taux de conversion</div>
                                <div class="fs-4 fw-bold text-warning">
                                    <?php
                                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM devis WHERE date_devis >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
                                    $total_devis_7j = $stmt->fetch()['total'] ?? 1;
                                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ventes WHERE date_vente >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
                                    $total_ventes_7j = $stmt->fetch()['total'] ?? 0;
                                    $taux_conv = $total_devis_7j > 0 ? round(($total_ventes_7j / $total_devis_7j) * 100, 1) : 0;
                                    echo $taux_conv . '%';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- WIDGET : État du stock -->
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-box-seam"></i> État du stock
                </div>
                <div class="card-body">
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produits WHERE actif = 1");
                    $total_produits = $stmt->fetch()['total'] ?? 0;
                    $stock_ok = $total_produits - $ruptures - $alertes_stock;
                    $pct_rupture = $total_produits > 0 ? round(($ruptures / $total_produits) * 100, 1) : 0;
                    $pct_alerte = $total_produits > 0 ? round(($alertes_stock / $total_produits) * 100, 1) : 0;
                    $pct_ok = 100 - $pct_rupture - $pct_alerte;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-danger"><i class="bi bi-exclamation-circle-fill"></i> Ruptures</span>
                            <strong><?= $ruptures ?> (<?= $pct_rupture ?>%)</strong>
                        </div>
                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar bg-danger" style="width: <?= $pct_rupture ?>%"></div>
                        </div>

                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-warning"><i class="bi bi-exclamation-triangle-fill"></i> Alertes</span>
                            <strong><?= $alertes_stock ?> (<?= $pct_alerte ?>%)</strong>
                        </div>
                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar bg-warning" style="width: <?= $pct_alerte ?>%"></div>
                        </div>

                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-success"><i class="bi bi-check-circle-fill"></i> Stock OK</span>
                            <strong><?= $stock_ok ?> (<?= $pct_ok ?>%)</strong>
                        </div>
                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar bg-success" style="width: <?= $pct_ok ?>%"></div>
                        </div>
                    </div>
                    <div class="text-center">
                        <a href="<?= url_for('stock/alertes.php') ?>" class="btn btn-sm btn-outline-info">
                            <i class="bi bi-eye"></i> Voir détails stock
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- ACCÈS RAPIDE PAR MODULE -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <h2 class="h5 text-uppercase text-muted mb-3 border-bottom pb-2">
        <i class="bi bi-grid-3x3-gap"></i> Accès rapide aux modules
    </h2>

    <div class="row g-3 mb-4">

        <!-- COMMERCIAL -->
        <div class="col-lg-4 col-md-6">
            <div class="card kms-card-hover h-100 border-primary">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="bi bi-briefcase"></i> COMMERCIAL
                </div>
                <div class="card-body">
                    <a href="<?= url_for('clients/list.php') ?>" class="d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-people fs-5 me-2 text-primary"></i>
                        <span>Clients & prospects</span>
                    </a>
                    <a href="<?= url_for('devis/list.php') ?>" class="d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-file-earmark-text fs-5 me-2 text-primary"></i>
                        <span>Devis</span>
                    </a>
                    <a href="<?= url_for('ventes/list.php') ?>" class="d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-cart-check fs-5 me-2 text-primary"></i>
                        <span>Ventes</span>
                    </a>
                    <a href="<?= url_for('livraisons/list.php') ?>" class="d-flex align-items-center text-decoration-none">
                        <i class="bi bi-truck fs-5 me-2 text-primary"></i>
                        <span>Livraisons</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- ACQUISITION -->
        <div class="col-lg-4 col-md-6">
            <div class="card kms-card-hover h-100 border-success">
                <div class="card-header bg-success text-white fw-bold">
                    <i class="bi bi-funnel"></i> CANAUX ACQUISITION
                </div>
                <div class="card-body">
                    <a href="<?= url_for('showroom/visiteurs_list.php') ?>" class="d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-shop-window fs-5 me-2 text-success"></i>
                        <span>Showroom</span>
                    </a>
                    <a href="<?= url_for('terrain/prospections_list.php') ?>" class="d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-geo-alt fs-5 me-2 text-success"></i>
                        <span>Terrain</span>
                    </a>
                    <a href="<?= url_for('digital/leads_list.php') ?>" class="d-flex align-items-center text-decoration-none">
                        <i class="bi bi-megaphone fs-5 me-2 text-success"></i>
                        <span>Digital</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- PRODUITS & STOCK -->
        <div class="col-lg-4 col-md-6">
            <div class="card kms-card-hover h-100 border-info">
                <div class="card-header bg-info text-white fw-bold">
                    <i class="bi bi-box-seam"></i> PRODUITS & STOCK
                </div>
                <div class="card-body">
                    <a href="<?= url_for('produits/list.php') ?>" class="d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-box-seam fs-5 me-2 text-info"></i>
                        <span>Catalogue produits</span>
                    </a>
                    <a href="<?= url_for('achats/list.php') ?>" class="d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-basket fs-5 me-2 text-info"></i>
                        <span>Achats & approvisionnements</span>
                    </a>
                    <a href="<?= url_for('magasin/dashboard.php') ?>" class="d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-house-door fs-5 me-2 text-info"></i>
                        <span>Dashboard Magasinier</span>
                    </a>
                    <a href="<?= url_for('coordination/ordres_preparation.php') ?>" class="d-flex align-items-center text-decoration-none">
                        <i class="bi bi-list-check fs-5 me-2 text-info"></i>
                        <span>Ordres de préparation</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- FINANCE -->
        <div class="col-lg-4 col-md-6">
            <div class="card kms-card-hover h-100 border-warning">
                <div class="card-header bg-warning text-dark fw-bold">
                    <i class="bi bi-currency-exchange"></i> FINANCE
                </div>
                <div class="card-body">
                    <a href="<?= url_for('caisse/journal.php') ?>" class="d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-cash-coin fs-5 me-2 text-warning"></i>
                        <span>Caisse</span>
                    </a>
                    <a href="<?= url_for('compta/index.php') ?>" class="d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-calculator fs-5 me-2 text-warning"></i>
                        <span>Comptabilité</span>
                    </a>
                    <a href="<?= url_for('compta/balance.php') ?>" class="d-flex align-items-center text-decoration-none">
                        <i class="bi bi-graph-up fs-5 me-2 text-warning"></i>
                        <span>Balance & Bilan</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- SERVICES ANNEXES -->
        <div class="col-lg-4 col-md-6">
            <div class="card kms-card-hover h-100 border-secondary">
                <div class="card-header bg-secondary text-white fw-bold">
                    <i class="bi bi-star"></i> SERVICES ANNEXES
                </div>
                <div class="card-body">
                    <a href="<?= url_for('hotel/reservations.php') ?>" class="d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-building fs-5 me-2 text-secondary"></i>
                        <span>Réservations hôtel</span>
                    </a>
                    <a href="<?= url_for('hotel/chambres_list.php') ?>" class="d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-door-closed fs-5 me-2 text-secondary"></i>
                        <span>Chambres</span>
                    </a>
                    <a href="<?= url_for('formation/inscriptions.php') ?>" class="d-flex align-items-center text-decoration-none">
                        <i class="bi bi-mortarboard fs-5 me-2 text-secondary"></i>
                        <span>Formation</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- MARKETING & ANALYSE -->
        <div class="col-lg-4 col-md-6">
            <div class="card kms-card-hover h-100 border-danger">
                <div class="card-header bg-danger text-white fw-bold">
                    <i class="bi bi-graph-up-arrow"></i> MARKETING & ANALYSE
                </div>
                <div class="card-body">
                    <a href="<?= url_for('promotions/list.php') ?>" class="d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-megaphone fs-5 me-2 text-danger"></i>
                        <span>Promotions</span>
                    </a>
                    <a href="<?= url_for('satisfaction/list.php') ?>" class="d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-emoji-smile fs-5 me-2 text-danger"></i>
                        <span>Satisfaction clients</span>
                    </a>
                    <a href="<?= url_for('reporting/dashboard_marketing.php') ?>" class="d-flex align-items-center text-decoration-none">
                        <i class="bi bi-pie-chart fs-5 me-2 text-danger"></i>
                        <span>Dashboard Marketing</span>
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>

<?php
include __DIR__ . '/partials/footer.php';
?>
