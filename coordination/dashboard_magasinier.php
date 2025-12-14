<?php
/**
 * coordination/dashboard_magasinier.php - Dashboard optimisé magasinier
 */

require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('MAGASIN_LIRE');

global $pdo;

// ============================================
// 1. ORDRES DE PRÉPARATION EN ATTENTE
// ============================================
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM ordres_preparation 
    WHERE statut IN ('EN_ATTENTE', 'EN_PREPARATION', 'PRET')
");
$ordres_attente = $stmt->fetch()['total'];

// ============================================
// 2. BONS LIVRAISON SANS SIGNATURE
// ============================================
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM bons_livraison 
    WHERE statut != 'ANNULE' AND signe_client = 0
");
$bls_non_signes = $stmt->fetch()['total'];

// ============================================
// 3. STOCKS CRITIQUES (seuil bas)
// ============================================
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM produits 
    WHERE stock_actuel < seuil_alerte AND seuil_alerte > 0 AND actif = 1
");
$stocks_critiques = $stmt->fetch()['total'];

// ============================================
// 4. MOUVEMENTS DE STOCK AUJOURD'HUI
// ============================================
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM stocks_mouvements 
    WHERE DATE(date_mouvement) = CURDATE()
");
$mouvements_jour = $stmt->fetch()['total'];

// ============================================
// 5. ORDRES DE PRÉPARATION DÉTAILLÉES
// ============================================
$stmt = $pdo->query("
    SELECT op.id, op.numero_ordre, op.date_ordre, op.statut,
           COUNT(opl.id) as nb_lignes,
           v.numero, c.nom
    FROM ordres_preparation op
    LEFT JOIN ordres_preparation_lignes opl ON opl.ordre_preparation_id = op.id
    LEFT JOIN ventes v ON v.id = op.vente_id
    LEFT JOIN clients c ON c.id = op.client_id
    WHERE op.statut IN ('EN_ATTENTE', 'EN_PREPARATION', 'PRET')
    GROUP BY op.id
    ORDER BY op.date_ordre ASC
    LIMIT 10
");
$ordres = $stmt->fetchAll();

// ============================================
// 6. PRODUITS À STOCK FAIBLE
// ============================================
$stmt = $pdo->query("
    SELECT id, designation, stock_actuel, seuil_alerte, famille_id
    FROM produits 
    WHERE stock_actuel < seuil_alerte AND seuil_alerte > 0 AND actif = 1
    ORDER BY (seuil_alerte - stock_actuel) DESC
    LIMIT 10
");
$produits_critiques = $stmt->fetchAll();

// ============================================
// 7. BONS LIVRAISON EN ATTENTE DE SIGNATURE
// ============================================
$stmt = $pdo->query("
    SELECT bl.id, bl.numero, bl.date_bl, bl.statut,
           c.nom, u.nom_complet as livreur
    FROM bons_livraison bl
    LEFT JOIN clients c ON c.id = bl.client_id
    LEFT JOIN utilisateurs u ON u.id = bl.livreur_id
    WHERE bl.signe_client = 0 AND bl.statut != 'ANNULE'
    ORDER BY bl.date_bl ASC
    LIMIT 10
");
$bls_signature = $stmt->fetchAll();

// ============================================
// 8. PERFORMANCE JOUR (% complétées)
// ============================================
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'LIVRE' THEN 1 ELSE 0 END) as terminees
    FROM ordres_preparation 
    WHERE DATE(date_ordre) = CURDATE()
");
$perf = $stmt->fetch();
$perf_percent = $perf['total'] > 0 ? round(($perf['terminees'] / $perf['total']) * 100) : 0;

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-box-seam text-primary"></i> Dashboard Magasinier</h1>
            <p class="text-muted small mb-0">Vue d'ensemble de vos tâches prioritaires</p>
        </div>
        <div class="btn-group">
            <a href="<?= url_for('coordination/dashboard_magasinier.php') ?>" class="btn btn-primary btn-sm active"><i class="bi bi-speedometer2"></i> Vue Magasinier</a>
            <a href="<?= url_for('coordination/dashboard.php') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-diagram-3"></i> Vue Générale</a>
        </div>
    </div>

    <!-- KPIs Principaux -->
    <div class="row g-3 mb-4">
        <!-- Ordres en attente -->
        <div class="col-xl-3 col-md-6">
            <a href="<?= url_for('coordination/ordres_preparation.php?statut=EN_ATTENTE') ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 hover-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                                    <i class="bi bi-list-task fs-2 text-warning"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-0 small">Ordres en attente</h6>
                                <h3 class="mb-0 text-warning"><?= $ordres_attente; ?></h3>
                                <small class="text-muted">Cliquez pour voir <i class="bi bi-arrow-right"></i></small>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- BLs non signés -->
        <div class="col-xl-3 col-md-6">
            <a href="<?= url_for('coordination/livraisons.php') ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 hover-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                                    <i class="bi bi-pen fs-2 text-danger"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-0 small">BLs non signés</h6>
                                <h3 class="mb-0 text-danger"><?= $bls_non_signes; ?></h3>
                                <small class="text-muted">Cliquez pour voir <i class="bi bi-arrow-right"></i></small>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Stocks critiques -->
        <div class="col-xl-3 col-md-6">
            <a href="#stocks-critiques" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 hover-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                                    <i class="bi bi-exclamation-triangle fs-2 text-danger"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-0 small">Stocks critiques</h6>
                                <h3 class="mb-0 text-danger"><?= $stocks_critiques; ?></h3>
                                <small class="text-muted">Voir détails ci-dessous <i class="bi bi-arrow-down"></i></small>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Mouvements jour -->
        <div class="col-xl-3 col-md-6">
            <a href="<?= url_for('magasin/stock_mouvements.php?date=' . date('Y-m-d')) ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 hover-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-success bg-opacity-10 p-3">
                                    <i class="bi bi-arrow-left-right fs-2 text-success"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-0 small">Mouvements (aujourd'hui)</h6>
                                <h3 class="mb-0 text-success"><?= $mouvements_jour; ?></h3>
                                <small class="text-muted">Cliquez pour voir <i class="bi bi-arrow-right"></i></small>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <style>
        .hover-card {
            transition: all 0.3s ease;
        }
        .hover-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
        }
    </style>

    <!-- Performance Jour -->
    <div class="row mb-4">
        <div class="col-xl-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title mb-0"><i class="bi bi-graph-up"></i> Performance Aujourd'hui</h6>
                        <small class="text-muted"><?= $perf['total']; ?> ordres du jour</small>
                    </div>
                    <div class="progress" style="height: 40px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $perf_percent; ?>%;">
                            <strong><?= $perf_percent; ?>% - <?= $perf['terminees']; ?>/<?= $perf['total']; ?> livrées</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ordres de Préparation -->
    <div class="row mb-4">
        <div class="col-xl-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-bottom">
                    <h6 class="mb-0"><i class="bi bi-box-seam"></i> Ordres de Préparation en Cours</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">N° Ordre</th>
                                    <th>Vente</th>
                                    <th>Client</th>
                                    <th>Lignes</th>
                                    <th>Créé le</th>
                                    <th>Statut</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ordres as $ordre): ?>
                                <tr>
                                    <td class="ps-4"><strong><?= htmlspecialchars($ordre['numero_ordre']); ?></strong></td>
                                    <td><?= htmlspecialchars($ordre['numero'] ?? 'N/A'); ?></td>
                                    <td><?= htmlspecialchars($ordre['nom'] ?? 'N/A'); ?></td>
                                    <td><span class="badge bg-light text-dark"><?= $ordre['nb_lignes']; ?></span></td>
                                    <td><?= date('d/m/Y', strtotime($ordre['date_ordre'])); ?></td>
                                    <td>
                                        <?php 
                                        $class = match($ordre['statut']) {
                                            'EN_ATTENTE' => 'bg-warning bg-opacity-10 text-warning',
                                            'EN_PREPARATION' => 'bg-info bg-opacity-10 text-info',
                                            'PRET' => 'bg-success bg-opacity-10 text-success',
                                            default => 'bg-secondary bg-opacity-10 text-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $class; ?>"><?= ucfirst(str_replace('_', ' ', $ordre['statut'])); ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="<?= url_for('coordination/ordres_preparation_edit.php?id=' . $ordre['id']); ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Voir
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($ordres)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="bi bi-check-circle"></i> Aucune ordre en attente - Excellent!
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stocks Critiques + BLs à Signer -->
    <div class="row g-3" id="stocks-critiques">
        <!-- Stocks Critiques -->
        <?php if (!empty($produits_critiques)): ?>
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-light border-bottom">
                    <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-danger"></i> Stocks Critiques</h6>
                </div>
                <div class="card-body p-0">
                    <div class="alert alert-warning m-3 mb-0" role="alert">
                        <small><i class="bi bi-exclamation-triangle"></i> <?= count($produits_critiques); ?> produit(s) sous seuil</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Produit</th>
                                    <th>Actuel</th>
                                    <th>Seuil</th>
                                    <th>Manque</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produits_critiques as $prod): ?>
                                <tr>
                                    <td class="ps-3"><small><?= htmlspecialchars(substr($prod['designation'], 0, 30)); ?></small></td>
                                    <td><span class="badge bg-danger"><?= $prod['stock_actuel']; ?></span></td>
                                    <td><small><?= $prod['seuil_alerte']; ?></small></td>
                                    <td><small><strong><?= $prod['seuil_alerte'] - $prod['stock_actuel']; ?></strong></small></td>
                                    <td class="text-end pe-3">
                                        <a href="<?= url_for('produits/edit.php?id=' . $prod['id']); ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i>
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
        <?php endif; ?>

        <!-- BLs à Signer -->
        <?php if (!empty($bls_signature)): ?>
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-light border-bottom">
                    <h6 class="mb-0"><i class="bi bi-pen"></i> BLs Attente Signature</h6>
                </div>
                <div class="card-body p-0">
                    <div class="alert alert-danger m-3 mb-0" role="alert">
                        <small><i class="bi bi-exclamation-triangle"></i> <?= count($bls_signature); ?> BL(s) à signer</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">N° BL</th>
                                    <th>Client</th>
                                    <th>Livreur</th>
                                    <th>Date</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bls_signature as $bl): ?>
                                <tr>
                                    <td class="ps-3"><strong><?= htmlspecialchars($bl['numero']); ?></strong></td>
                                    <td><small><?= htmlspecialchars(substr($bl['nom'] ?? 'N/A', 0, 20)); ?></small></td>
                                    <td><small><?= htmlspecialchars(substr($bl['livreur'] ?? 'N/A', 0, 15)); ?></small></td>
                                    <td><small><?= date('d/m', strtotime($bl['date_bl'])); ?></small></td>
                                    <td class="text-end pe-3">
                                        <a href="<?= url_for('livraisons/detail.php?id=' . $bl['id']); ?>" class="btn btn-sm btn-danger">
                                            <i class="bi bi-pen"></i>
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
        <?php endif; ?>
    </div>

</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
