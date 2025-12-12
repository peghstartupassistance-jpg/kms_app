<?php
// stock/alertes.php - Gestion des alertes stock pour magasinier
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('STOCK_LIRE');

global $pdo;

$filtre = $_GET['filtre'] ?? 'alerte'; // alerte, rupture, tous

$where = "WHERE p.actif = 1";
if ($filtre === 'rupture') {
    $where .= " AND p.stock_actuel = 0";
} elseif ($filtre === 'alerte') {
    $where .= " AND p.stock_actuel > 0 AND p.stock_actuel <= p.seuil_alerte";
}

$sql = "
    SELECT p.*, 
           f.nom as famille_nom,
           (SELECT SUM(quantite) FROM stocks_mouvements WHERE produit_id = p.id AND type_mouvement = 'SORTIE' AND date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as sorties_semaine,
           (SELECT SUM(quantite) FROM stocks_mouvements WHERE produit_id = p.id AND type_mouvement = 'SORTIE' AND date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as sorties_mois,
           (SELECT SUM(quantite) FROM stocks_mouvements WHERE produit_id = p.id AND type_mouvement = 'ENTREE' AND date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as entrees_mois
    FROM produits p
    LEFT JOIN familles_produits f ON p.famille_id = f.id
    $where
    ORDER BY 
        CASE WHEN p.stock_actuel = 0 THEN 0 ELSE 1 END,
        (p.stock_actuel / NULLIF(p.seuil_alerte, 0)) ASC,
        p.designation
";

$produits = $pdo->query($sql)->fetchAll();

// Statistiques
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_produits,
        SUM(CASE WHEN stock_actuel = 0 THEN 1 ELSE 0 END) as ruptures,
        SUM(CASE WHEN stock_actuel > 0 AND stock_actuel <= seuil_alerte THEN 1 ELSE 0 END) as alertes,
        SUM(CASE WHEN stock_actuel > seuil_alerte THEN 1 ELSE 0 END) as ok
    FROM produits
    WHERE actif = 1
")->fetch();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            <i class="bi bi-exclamation-triangle text-warning"></i> Alertes Stock
        </h1>
        <div class="btn-group">
            <a href="<?= url_for('magasin/dashboard.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
            <?php if (peut('STOCK_ECRIRE')): ?>
                <a href="<?= url_for('stock/ajustement.php') ?>" class="btn btn-warning btn-sm">
                    <i class="bi bi-pencil-square"></i> Ajustement stock
                </a>
                <a href="<?= url_for('achats/edit.php') ?>" class="btn btn-success btn-sm">
                    <i class="bi bi-cart-plus"></i> Commander
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body p-3">
                    <h6 class="text-muted small mb-1">Ruptures</h6>
                    <h3 class="mb-0 text-danger"><?= $stats['ruptures'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body p-3">
                    <h6 class="text-muted small mb-1">En alerte</h6>
                    <h3 class="mb-0 text-warning"><?= $stats['alertes'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body p-3">
                    <h6 class="text-muted small mb-1">Stock OK</h6>
                    <h3 class="mb-0 text-success"><?= $stats['ok'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body p-3">
                    <h6 class="text-muted small mb-1">Total produits</h6>
                    <h3 class="mb-0"><?= $stats['total_produits'] ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-3">
        <div class="card-body p-2">
            <div class="btn-group w-100" role="group">
                <a href="?filtre=rupture" class="btn btn-sm <?= $filtre === 'rupture' ? 'btn-danger' : 'btn-outline-danger' ?>">
                    <i class="bi bi-x-circle"></i> Ruptures (<?= $stats['ruptures'] ?>)
                </a>
                <a href="?filtre=alerte" class="btn btn-sm <?= $filtre === 'alerte' ? 'btn-warning' : 'btn-outline-warning' ?>">
                    <i class="bi bi-exclamation-triangle"></i> Alertes (<?= $stats['alertes'] ?>)
                </a>
                <a href="?filtre=tous" class="btn btn-sm <?= $filtre === 'tous' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    <i class="bi bi-list"></i> Tous les problèmes (<?= $stats['ruptures'] + $stats['alertes'] ?>)
                </a>
            </div>
        </div>
    </div>

    <!-- Liste produits -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Désignation</th>
                            <th>Famille</th>
                            <th class="text-end">Stock actuel</th>
                            <th class="text-end">Seuil alerte</th>
                            <th class="text-end">% restant</th>
                            <th class="text-end">Sorties/semaine</th>
                            <th class="text-end">Sorties/mois</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produits as $prod): ?>
                            <?php 
                            $pourcentage = $prod['seuil_alerte'] > 0 ? ($prod['stock_actuel'] / $prod['seuil_alerte']) * 100 : 0;
                            $badge_stock = $prod['stock_actuel'] == 0 ? 'danger' : ($prod['stock_actuel'] <= $prod['seuil_alerte'] ? 'warning' : 'success');
                            ?>
                            <tr class="<?= $prod['stock_actuel'] == 0 ? 'table-danger' : '' ?>">
                                <td>
                                    <strong><?= htmlspecialchars($prod['code_produit']) ?></strong>
                                    <?php if ($prod['stock_actuel'] == 0): ?>
                                        <span class="badge bg-danger badge-sm ms-1">RUPTURE</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($prod['designation']) ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars($prod['famille_nom'] ?? '-') ?></small></td>
                                <td class="text-end">
                                    <span class="badge bg-<?= $badge_stock ?>">
                                        <?= number_format($prod['stock_actuel'], 0, ',', ' ') ?>
                                    </span>
                                </td>
                                <td class="text-end"><?= number_format($prod['seuil_alerte'], 0, ',', ' ') ?></td>
                                <td class="text-end">
                                    <small class="text-<?= $pourcentage < 50 ? 'danger' : 'warning' ?>">
                                        <?= number_format($pourcentage, 0) ?>%
                                    </small>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-danger">-<?= number_format($prod['sorties_semaine'] ?? 0, 0, ',', ' ') ?></span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-secondary">-<?= number_format($prod['sorties_mois'] ?? 0, 0, ',', ' ') ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= url_for('produits/edit.php?id=' . $prod['id']) ?>" class="btn btn-outline-primary" title="Détails">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (peut('STOCK_ECRIRE')): ?>
                                            <a href="<?= url_for('achats/edit.php?produit_id=' . $prod['id']) ?>" class="btn btn-outline-success" title="Commander">
                                                <i class="bi bi-cart-plus"></i>
                                            </a>
                                            <a href="<?= url_for('coordination/ruptures_signalees_list.php?action=signaler&produit_id=' . $prod['id']) ?>" class="btn btn-outline-danger" title="Signaler rupture">
                                                <i class="bi bi-exclamation-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($produits)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="bi bi-check-circle text-success fs-1"></i>
                                    <div class="text-muted mt-2">Aucun produit en alerte</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
