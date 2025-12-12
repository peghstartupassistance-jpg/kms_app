<?php
// magasin/dashboard.php - Dashboard Magasinier
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('STOCK_LIRE');

global $pdo;

// Statistiques stock
$statsStock = $pdo->query("
    SELECT 
        COUNT(*) as total_produits,
        SUM(stock_actuel) as stock_total,
        SUM(CASE WHEN stock_actuel <= seuil_alerte THEN 1 ELSE 0 END) as produits_alerte,
        SUM(CASE WHEN stock_actuel = 0 THEN 1 ELSE 0 END) as produits_rupture
    FROM produits 
    WHERE actif = 1
")->fetch();

// Ordres de préparation en attente
$ordresEnAttente = $pdo->query("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN priorite = 'URGENTE' THEN 1 ELSE 0 END) as urgents,
           SUM(CASE WHEN priorite = 'TRES_URGENTE' THEN 1 ELSE 0 END) as tres_urgents
    FROM ordres_preparation
    WHERE statut IN ('EN_ATTENTE', 'EN_PREPARATION')
")->fetch();

// Livraisons en cours
$livraisons = $pdo->query("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN signe_client = 0 THEN 1 ELSE 0 END) as non_signees
    FROM bons_livraison
    WHERE DATE(date_bl) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch();

// Litiges actifs
$litiges = $pdo->query("
    SELECT COUNT(*) as total
    FROM retours_litiges
    WHERE statut_traitement = 'EN_COURS'
")->fetch();

// Mouvements récents
$mouvementsRecents = $pdo->query("
    SELECT sm.*, p.code_produit, p.designation, u.nom_complet
    FROM stocks_mouvements sm
    LEFT JOIN produits p ON sm.produit_id = p.id
    LEFT JOIN utilisateurs u ON sm.utilisateur_id = u.id
    ORDER BY sm.date_mouvement DESC, sm.id DESC
    LIMIT 10
")->fetchAll();

// Produits en alerte
$produitsAlerte = $pdo->query("
    SELECT p.*, f.nom as famille_nom,
           (SELECT SUM(quantite) FROM stocks_mouvements WHERE produit_id = p.id AND type_mouvement = 'SORTIE' AND date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as sorties_mois
    FROM produits p
    LEFT JOIN familles_produits f ON p.famille_id = f.id
    WHERE p.actif = 1 AND p.stock_actuel <= p.seuil_alerte
    ORDER BY (p.stock_actuel / NULLIF(p.seuil_alerte, 0)) ASC
    LIMIT 10
")->fetchAll();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">
        <i class="bi bi-house-door"></i> Dashboard Magasinier
    </h1>

    <!-- Statistiques principales -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Ordres en attente</h6>
                            <h2 class="mb-0"><?= $ordresEnAttente['total'] ?></h2>
                            <?php if ($ordresEnAttente['urgents'] > 0): ?>
                                <small class="text-danger">
                                    <i class="bi bi-exclamation-triangle-fill"></i> <?= $ordresEnAttente['urgents'] ?> urgent(s)
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="fs-1 text-primary">
                            <i class="bi bi-box-seam"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="<?= url_for('coordination/ordres_preparation.php') ?>" class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-arrow-right"></i> Gérer les ordres
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Produits en alerte</h6>
                            <h2 class="mb-0 text-warning"><?= $statsStock['produits_alerte'] ?></h2>
                            <small class="text-danger">Dont <?= $statsStock['produits_rupture'] ?> en rupture</small>
                        </div>
                        <div class="fs-1 text-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="<?= url_for('stock/alertes.php') ?>" class="btn btn-sm btn-warning w-100">
                            <i class="bi bi-arrow-right"></i> Voir les alertes
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Livraisons récentes</h6>
                            <h2 class="mb-0"><?= $livraisons['total'] ?></h2>
                            <small class="text-muted"><?= $livraisons['non_signees'] ?> non signées</small>
                        </div>
                        <div class="fs-1 text-info">
                            <i class="bi bi-truck"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="<?= url_for('livraisons/list.php') ?>" class="btn btn-sm btn-info w-100">
                            <i class="bi bi-arrow-right"></i> Gérer livraisons
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Litiges actifs</h6>
                            <h2 class="mb-0 text-danger"><?= $litiges['total'] ?></h2>
                            <small class="text-muted">À traiter</small>
                        </div>
                        <div class="fs-1 text-danger">
                            <i class="bi bi-arrow-left-right"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="<?= url_for('coordination/litiges.php') ?>" class="btn btn-sm btn-danger w-100">
                            <i class="bi bi-arrow-right"></i> Gérer litiges
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions rapides -->
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-lightning-charge"></i> Actions rapides
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <a href="<?= url_for('stock/ajustement.php') ?>" class="btn btn-outline-primary w-100">
                                <i class="bi bi-pencil-square"></i> Ajustement stock
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="<?= url_for('achats/edit.php') ?>" class="btn btn-outline-success w-100">
                                <i class="bi bi-cart-plus"></i> Nouvelle réception
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="<?= url_for('coordination/ruptures_signalees_list.php') ?>" class="btn btn-outline-warning w-100">
                                <i class="bi bi-exclamation-circle"></i> Signaler rupture
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="<?= url_for('stock/inventaire.php') ?>" class="btn btn-outline-info w-100">
                                <i class="bi bi-clipboard-check"></i> Inventaire
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Produits en alerte -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-exclamation-triangle text-warning"></i> Produits en alerte stock</span>
                    <a href="<?= url_for('stock/alertes.php') ?>" class="btn btn-sm btn-outline-warning">Voir tout</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Produit</th>
                                    <th class="text-end">Stock</th>
                                    <th class="text-end">Seuil</th>
                                    <th class="text-end">Sorties/mois</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produitsAlerte as $prod): ?>
                                    <tr>
                                        <td>
                                            <div><strong><?= htmlspecialchars($prod['code_produit']) ?></strong></div>
                                            <small class="text-muted"><?= htmlspecialchars($prod['designation']) ?></small>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge bg-<?= $prod['stock_actuel'] == 0 ? 'danger' : 'warning' ?>">
                                                <?= number_format($prod['stock_actuel'], 0, ',', ' ') ?>
                                            </span>
                                        </td>
                                        <td class="text-end"><?= number_format($prod['seuil_alerte'], 0, ',', ' ') ?></td>
                                        <td class="text-end text-danger"><?= number_format($prod['sorties_mois'] ?? 0, 0, ',', ' ') ?></td>
                                        <td>
                                            <a href="<?= url_for('achats/edit.php?produit_id=' . $prod['id']) ?>" class="btn btn-sm btn-outline-success" title="Commander">
                                                <i class="bi bi-cart-plus"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($produitsAlerte)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">
                                            <i class="bi bi-check-circle text-success"></i> Aucun produit en alerte
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mouvements récents -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-arrow-repeat"></i> Mouvements récents</span>
                    <a href="<?= url_for('stock/mouvements.php') ?>" class="btn btn-sm btn-outline-primary">Voir tout</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Produit</th>
                                    <th>Type</th>
                                    <th class="text-end">Qté</th>
                                    <th>Utilisateur</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mouvementsRecents as $mvt): ?>
                                    <tr>
                                        <td><small><?= date('d/m H:i', strtotime($mvt['date_mouvement'])) ?></small></td>
                                        <td>
                                            <small><strong><?= htmlspecialchars($mvt['code_produit']) ?></strong></small>
                                        </td>
                                        <td>
                                            <?php
                                            $badge = match($mvt['type_mouvement']) {
                                                'ENTREE' => 'success',
                                                'SORTIE' => 'danger',
                                                'AJUSTEMENT' => 'warning',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?= $badge ?> badge-sm">
                                                <?= $mvt['type_mouvement'] ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <strong class="text-<?= $mvt['type_mouvement'] === 'ENTREE' ? 'success' : 'danger' ?>">
                                                <?= $mvt['type_mouvement'] === 'ENTREE' ? '+' : '-' ?><?= number_format($mvt['quantite'], 0, ',', ' ') ?>
                                            </strong>
                                        </td>
                                        <td><small><?= htmlspecialchars($mvt['nom_complet'] ?? 'Système') ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
