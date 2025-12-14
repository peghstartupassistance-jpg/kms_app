<?php
/**
 * magasin/stock_mouvements.php - Liste des mouvements de stock
 */

require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('MAGASIN_LIRE');

global $pdo;

// Filtres
$date = $_GET['date'] ?? date('Y-m-d');
$type = $_GET['type'] ?? '';
$produit_id = isset($_GET['produit_id']) ? (int)$_GET['produit_id'] : 0;

// Construire WHERE
$where = ['DATE(sm.date_mouvement) = :date'];
$params = ['date' => $date];

if ($type !== '') {
    $where[] = "sm.type_mouvement = :type";
    $params['type'] = $type;
}

if ($produit_id > 0) {
    $where[] = "sm.produit_id = :produit_id";
    $params['produit_id'] = $produit_id;
}

$whereClause = implode(' AND ', $where);

// Récupérer mouvements
$stmt = $pdo->prepare("
    SELECT sm.*, 
           p.designation as produit_nom, p.code_produit,
           u.nom_complet as utilisateur_nom
    FROM stocks_mouvements sm
    LEFT JOIN produits p ON p.id = sm.produit_id
    LEFT JOIN utilisateurs u ON u.id = sm.utilisateur_id
    WHERE $whereClause
    ORDER BY sm.date_mouvement DESC, sm.id DESC
    LIMIT 200
");
$stmt->execute($params);
$mouvements = $stmt->fetchAll();

// Stats du jour
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN type_mouvement = 'ENTREE' THEN quantite ELSE 0 END) as total_entrees,
        SUM(CASE WHEN type_mouvement = 'SORTIE' THEN quantite ELSE 0 END) as total_sorties,
        SUM(CASE WHEN type_mouvement = 'AJUSTEMENT' THEN quantite ELSE 0 END) as total_ajustements
    FROM stocks_mouvements
    WHERE DATE(date_mouvement) = :date
");
$stmtStats->execute(['date' => $date]);
$stats = $stmtStats->fetch();

// Liste produits pour filtre
$stmtProduits = $pdo->query("SELECT id, designation FROM produits WHERE actif = 1 ORDER BY designation LIMIT 100");
$produits = $stmtProduits->fetchAll();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-arrow-left-right text-primary"></i> Mouvements de Stock</h1>
            <p class="text-muted small mb-0">Suivi des entrées, sorties et ajustements</p>
        </div>
        <div class="btn-group">
            <a href="<?= url_for('magasin/dashboard.php') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="<?= url_for('produits/list.php') ?>" class="btn btn-outline-info btn-sm"><i class="bi bi-box-seam"></i> Produits</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-info bg-opacity-10 p-3">
                                <i class="bi bi-list-ol fs-3 text-info"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Total mouvements</h6>
                            <h3 class="mb-0"><?= number_format($stats['total']); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3">
                                <i class="bi bi-arrow-down-circle fs-3 text-success"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Entrées</h6>
                            <h3 class="mb-0 text-success"><?= number_format($stats['total_entrees']); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                                <i class="bi bi-arrow-up-circle fs-3 text-danger"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Sorties</h6>
                            <h3 class="mb-0 text-danger"><?= number_format($stats['total_sorties']); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                                <i class="bi bi-pencil-square fs-3 text-warning"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Ajustements</h6>
                            <h3 class="mb-0 text-warning"><?= number_format($stats['total_ajustements']); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Type mouvement</label>
                    <select name="type" class="form-select">
                        <option value="">Tous les types</option>
                        <option value="ENTREE" <?= $type === 'ENTREE' ? 'selected' : ''; ?>>Entrées</option>
                        <option value="SORTIE" <?= $type === 'SORTIE' ? 'selected' : ''; ?>>Sorties</option>
                        <option value="AJUSTEMENT" <?= $type === 'AJUSTEMENT' ? 'selected' : ''; ?>>Ajustements</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Produit</label>
                    <select name="produit_id" class="form-select">
                        <option value="0">Tous les produits</option>
                        <?php foreach ($produits as $p): ?>
                        <option value="<?= $p['id']; ?>" <?= $produit_id == $p['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($p['designation']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Filtrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Table mouvements -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light border-bottom">
            <h6 class="mb-0">
                <i class="bi bi-list"></i> Mouvements du <?= date('d/m/Y', strtotime($date)); ?>
                <span class="badge bg-primary"><?= count($mouvements); ?> enregistrement(s)</span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Date/Heure</th>
                            <th>Produit</th>
                            <th>Type</th>
                            <th>Quantité</th>
                            <th>Source</th>
                            <th>Commentaire</th>
                            <th>Utilisateur</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mouvements as $mvt): ?>
                        <tr>
                            <td class="ps-4">
                                <small><?= date('d/m/Y H:i', strtotime($mvt['date_mouvement'])); ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($mvt['produit_nom']); ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($mvt['code_produit']); ?></small>
                            </td>
                            <td>
                                <?php 
                                $badgeClass = match($mvt['type_mouvement']) {
                                    'ENTREE' => 'bg-success',
                                    'SORTIE' => 'bg-danger',
                                    'AJUSTEMENT' => 'bg-warning',
                                    default => 'bg-secondary'
                                };
                                $icon = match($mvt['type_mouvement']) {
                                    'ENTREE' => 'arrow-down-circle',
                                    'SORTIE' => 'arrow-up-circle',
                                    'AJUSTEMENT' => 'pencil-square',
                                    default => 'circle'
                                };
                                ?>
                                <span class="badge <?= $badgeClass; ?>">
                                    <i class="bi bi-<?= $icon; ?>"></i> <?= $mvt['type_mouvement']; ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= $mvt['quantite'] > 0 ? '+' : ''; ?><?= number_format($mvt['quantite']); ?></strong>
                            </td>
                            <td>
                                <?php if ($mvt['source_type']): ?>
                                <small class="text-muted">
                                    <?= htmlspecialchars($mvt['source_type']); ?>
                                    <?php if ($mvt['source_id']): ?>
                                    #<?= $mvt['source_id']; ?>
                                    <?php endif; ?>
                                </small>
                                <?php else: ?>
                                <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($mvt['commentaire'] ?? '-'); ?></small>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($mvt['utilisateur_nom'] ?? '-'); ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($mouvements)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox"></i> Aucun mouvement trouvé pour cette date
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
