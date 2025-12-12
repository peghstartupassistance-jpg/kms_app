<?php
// coordination/ruptures.php - Gestion des ruptures de stock signalées
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('PRODUITS_LIRE');

global $pdo;

$utilisateur = utilisateurConnecte();

// Filtres
$statut = $_GET['statut'] ?? '';
$dateDebut = $_GET['date_debut'] ?? '';
$dateFin = $_GET['date_fin'] ?? '';

$where = [];
$params = [];

if ($statut !== '') {
    $where[] = "r.statut_traitement = :statut";
    $params['statut'] = $statut;
}

if ($dateDebut !== '') {
    $where[] = "r.date_signalement >= :date_debut";
    $params['date_debut'] = $dateDebut;
}

if ($dateFin !== '') {
    $where[] = "r.date_signalement <= :date_fin";
    $params['date_fin'] = $dateFin;
}

$whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT r.*,
           p.code_produit,
           p.designation,
           p.stock_actuel AS stock_reel_actuel,
           f.nom AS famille,
           u.nom_complet AS magasinier
    FROM ruptures_signalees r
    INNER JOIN produits p ON r.produit_id = p.id
    LEFT JOIN familles_produits f ON p.famille_id = f.id
    LEFT JOIN utilisateurs u ON r.magasinier_id = u.id
    $whereSql
    ORDER BY 
        CASE r.statut_traitement
            WHEN 'SIGNALE' THEN 1
            WHEN 'EN_COURS' THEN 2
            WHEN 'RESOLU' THEN 3
            WHEN 'ABANDONNE' THEN 4
        END,
        r.date_signalement DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ruptures = $stmt->fetchAll();

// Statistiques
$sqlStats = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut_traitement = 'SIGNALE' THEN 1 ELSE 0 END) as signales,
        SUM(CASE WHEN statut_traitement = 'EN_COURS' THEN 1 ELSE 0 END) as en_cours,
        SUM(CASE WHEN statut_traitement = 'RESOLU' THEN 1 ELSE 0 END) as resolus
    FROM ruptures_signalees
    $whereSql
";
$stmtStats = $pdo->prepare($sqlStats);
$stmtStats->execute($params);
$stats = $stmtStats->fetch();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="h4 mb-4">
        <i class="bi bi-exclamation-triangle"></i> Ruptures de Stock Signalées
    </h1>

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body p-3">
                    <div class="text-muted small">Total ruptures</div>
                    <div class="fs-5 fw-bold"><?= number_format($stats['total']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body p-3">
                    <div class="text-muted small">Signalées</div>
                    <div class="fs-5 fw-bold text-danger"><?= number_format($stats['signales']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body p-3">
                    <div class="text-muted small">En cours</div>
                    <div class="fs-5 fw-bold text-warning"><?= number_format($stats['en_cours']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body p-3">
                    <div class="text-muted small">Résolus</div>
                    <div class="fs-5 fw-bold text-success"><?= number_format($stats['resolus']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">Statut</label>
                    <select name="statut" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        <option value="SIGNALE" <?= $statut === 'SIGNALE' ? 'selected' : '' ?>>Signalé</option>
                        <option value="EN_COURS" <?= $statut === 'EN_COURS' ? 'selected' : '' ?>>En cours</option>
                        <option value="RESOLU" <?= $statut === 'RESOLU' ? 'selected' : '' ?>>Résolu</option>
                        <option value="ABANDONNE" <?= $statut === 'ABANDONNE' ? 'selected' : '' ?>>Abandonné</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control form-control-sm" value="<?= htmlspecialchars($dateDebut) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFin) ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-search"></i> Filtrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des ruptures -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Produit</th>
                            <th>Famille</th>
                            <th>Seuil alerte</th>
                            <th>Stock actuel</th>
                            <th>Impact commercial</th>
                            <th>Action proposée</th>
                            <th>Statut</th>
                            <th>Magasinier</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ruptures)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                                    Aucune rupture signalée
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ruptures as $rupture): ?>
                                <?php
                                $badges = [
                                    'SIGNALE' => 'danger',
                                    'EN_COURS' => 'warning',
                                    'RESOLU' => 'success',
                                    'ABANDONNE' => 'secondary'
                                ];
                                $badgeClass = $badges[$rupture['statut_traitement']] ?? 'secondary';
                                ?>
                                <tr>
                                    <td class="text-nowrap"><?= date('d/m/Y', strtotime($rupture['date_signalement'])) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($rupture['code_produit']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($rupture['designation']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($rupture['famille'] ?? '-') ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?= number_format($rupture['seuil_alerte'], 0) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger"><?= number_format($rupture['stock_actuel'], 0) ?></span>
                                        <small class="d-block text-muted">(Actuel: <?= number_format($rupture['stock_reel_actuel'], 0) ?>)</small>
                                    </td>
                                    <td class="small">
                                        <?= htmlspecialchars($rupture['impact_commercial'] ?? '-') ?>
                                    </td>
                                    <td class="small">
                                        <?= htmlspecialchars($rupture['action_proposee'] ?? '-') ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $badgeClass ?>">
                                            <?= str_replace('_', ' ', $rupture['statut_traitement']) ?>
                                        </span>
                                        <?php if ($rupture['date_resolution']): ?>
                                            <small class="d-block text-muted">
                                                <?= date('d/m/Y', strtotime($rupture['date_resolution'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($rupture['magasinier'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-4">
        <i class="bi bi-info-circle"></i>
        <strong>Note :</strong> Les ruptures de stock sont signalées automatiquement par le magasin lorsqu'un produit 
        atteint son seuil d'alerte. Cela permet à l'équipe commerciale d'ajuster sa communication et d'éviter de 
        promouvoir des produits indisponibles.
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
