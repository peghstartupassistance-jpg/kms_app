<?php
// ventes/list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

$today    = date('Y-m-d');
$dateDeb  = $_GET['date_debut'] ?? null;
$dateFin  = $_GET['date_fin'] ?? null;
$statut   = $_GET['statut'] ?? '';
$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$canalId  = isset($_GET['canal_id']) ? (int)$_GET['canal_id'] : 0;

// Clients pour filtre
$stmt = $pdo->query("SELECT id, nom FROM clients ORDER BY nom");
$clients = $stmt->fetchAll();

// Canaux de vente pour filtre
$stmt = $pdo->query("SELECT id, code, libelle FROM canaux_vente ORDER BY code");
$canaux = $stmt->fetchAll();

$where  = [];
$params = [];

// Les filtres de date sont OPTIONNELS (non appliqués par défaut)
if ($dateDeb !== null && $dateDeb !== '') {
    $where[] = "v.date_vente >= ?";
    $params[] = $dateDeb;
}
if ($dateFin !== null && $dateFin !== '') {
    $where[] = "v.date_vente <= ?";
    $params[] = $dateFin;
}
if ($statut !== '' && in_array($statut, ['EN_ATTENTE_LIVRAISON','LIVREE','ANNULEE','PARTIELLEMENT_LIVREE'], true)) {
    $where[] = "v.statut = ?";
    $params[] = $statut;
}
if ($clientId > 0) {
    $where[] = "v.client_id = ?";
    $params[] = $clientId;
}
if ($canalId > 0) {
    $where[] = "v.canal_vente_id = ?";
    $params[] = $canalId;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

$sql = "
    SELECT
        v.*,
        c.nom AS client_nom,
        cv.code AS canal_code,
        cv.libelle AS canal_libelle,
        u.nom_complet AS commercial_nom
    FROM ventes v
    JOIN clients c ON c.id = v.client_id
    JOIN canaux_vente cv ON cv.id = v.canal_vente_id
    JOIN utilisateurs u ON u.id = v.utilisateur_id
    $whereSql
    ORDER BY v.date_vente DESC, v.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ventes = $stmt->fetchAll();

$peutCreerVente = in_array('VENTES_CREER', $_SESSION['permissions'] ?? [], true);

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="list-page-header d-flex justify-content-between align-items-center">
        <h1 class="list-page-title h3">
            <i class="bi bi-cart-check-fill"></i>
            Ventes
            <span class="count-badge ms-2"><?= count($ventes) ?></span>
        </h1>
        <?php if ($peutCreerVente): ?>
            <a href="<?= url_for('ventes/edit.php') ?>" class="btn btn-success btn-add-new">
                <i class="bi bi-plus-circle me-2"></i> Nouvelle vente
            </a>
        <?php endif; ?>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success alert-modern">
            <i class="bi bi-check-circle-fill"></i>
            <span><?= htmlspecialchars($flashSuccess) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger alert-modern">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span><?= htmlspecialchars($flashError) ?></span>
        </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="card filter-card">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="get">
                <div class="col-md-2">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control"
                           value="<?= htmlspecialchars($dateDeb ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control"
                           value="<?= htmlspecialchars($dateFin ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="">Tous</option>
                        <?php foreach (['EN_ATTENTE_LIVRAISON','PARTIELLEMENT_LIVREE','LIVREE','ANNULEE'] as $s): ?>
                            <option value="<?= $s ?>" <?= $statut === $s ? 'selected' : '' ?>>
                                <?= $s ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Client</label>
                    <select name="client_id" class="form-select">
                        <option value="0">Tous</option>
                        <?php foreach ($clients as $cl): ?>
                            <option value="<?= (int)$cl['id'] ?>"
                                <?= $clientId === (int)$cl['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cl['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Canal</label>
                    <select name="canal_id" class="form-select">
                        <option value="0">Tous</option>
                        <?php foreach ($canaux as $cv): ?>
                            <option value="<?= (int)$cv['id'] ?>"
                                <?= $canalId === (int)$cv['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cv['code']) ?> – <?= htmlspecialchars($cv['libelle']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-filter">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('ventes/list.php') ?>" class="btn btn-outline-secondary btn-filter">
                        <i class="bi bi-arrow-clockwise me-1"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des ventes -->
    <div class="card data-table-card">
        <div class="card-body">
            <?php if (empty($ventes)): ?>
                <div class="empty-state">
                    <i class="bi bi-cart-x"></i>
                    <h5>Aucune vente trouvée</h5>
                    <p>Aucune vente ne correspond aux filtres sélectionnés.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table modern-table">
                        <thead class="table-light">
                        <tr>
                            <th>N° vente</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Canal</th>
                            <th>Commercial</th>
                            <th class="text-end">Montant HT</th>
                            <th class="text-end">Montant TTC</th>
                            <th class="text-center">Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ventes as $v): ?>
                            <?php
                            $badgeClass = 'badge-status-secondary';
                            $badgeIcon = 'bi-hourglass';
                            if ($v['statut'] === 'EN_ATTENTE_LIVRAISON') {
                                $badgeClass = 'badge-status-warning';
                                $badgeIcon = 'bi-hourglass-split';
                            } elseif ($v['statut'] === 'PARTIELLEMENT_LIVREE') {
                                $badgeClass = 'badge-status-info';
                                $badgeIcon = 'bi-truck';
                            } elseif ($v['statut'] === 'LIVREE') {
                                $badgeClass = 'badge-status-success';
                                $badgeIcon = 'bi-check-circle-fill';
                            } elseif ($v['statut'] === 'ANNULEE') {
                                $badgeClass = 'badge-status-danger';
                                $badgeIcon = 'bi-x-circle-fill';
                            }
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= url_for('ventes/detail.php') . '?id=' . (int)$v['id'] ?>"
                                       class="table-link">
                                        <i class="bi bi-receipt me-1"></i>
                                        <?= htmlspecialchars($v['numero']) ?>
                                    </a>
                                </td>
                                <td>
                                    <i class="bi bi-calendar3 me-1 text-muted"></i>
                                    <?= htmlspecialchars($v['date_vente']) ?>
                                </td>
                                <td>
                                    <i class="bi bi-person me-1 text-muted"></i>
                                    <?= htmlspecialchars($v['client_nom']) ?>
                                </td>
                                <td>
                                    <span class="modern-badge badge-status-primary">
                                        <i class="bi bi-megaphone"></i>
                                        <?= htmlspecialchars($v['canal_code']) ?>
                                    </span>
                                    <div class="text-muted small mt-1">
                                        <?= htmlspecialchars($v['canal_libelle']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($v['commercial_nom']) ?></td>
                                <td class="text-end fw-semibold">
                                    <?= number_format((float)$v['montant_total_ht'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="text-end fw-bold text-success">
                                    <?= number_format((float)$v['montant_total_ttc'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="text-center">
                                    <span class="modern-badge <?= $badgeClass ?>">
                                        <i class="<?= $badgeIcon ?>"></i>
                                        <?= htmlspecialchars($v['statut']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="action-btn-group">
                                        <a href="<?= url_for('ventes/detail.php') . '?id=' . (int)$v['id'] ?>"
                                           class="btn btn-sm btn-outline-primary btn-action"
                                           title="Voir détails">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?= url_for('ventes/print.php') . '?id=' . (int)$v['id'] ?>"
                                           class="btn btn-sm btn-outline-info btn-action"
                                           target="_blank"
                                           title="Imprimer facture">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <?php if ($peutCreerVente && in_array($v['statut'], ['EN_ATTENTE_LIVRAISON', 'PARTIELLEMENT_LIVREE'], true)): ?>
                                            <a href="<?= url_for('coordination/ordres_preparation_edit.php?vente_id=' . (int)$v['id']) ?>"
                                               class="btn btn-sm btn-outline-warning btn-action"
                                               title="Créer ordre de préparation">
                                                <i class="bi bi-clipboard-check"></i>
                                            </a>
                                            <a href="<?= url_for('livraisons/create.php') . '?vente_id=' . (int)$v['id'] ?>"
                                               class="btn btn-sm btn-outline-success btn-action"
                                               title="Créer bon de livraison">
                                                <i class="bi bi-truck"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($peutCreerVente): ?>
                                            <a href="<?= url_for('ventes/edit.php') . '?id=' . (int)$v['id'] ?>"
                                               class="btn btn-sm btn-outline-secondary btn-action"
                                               title="Modifier">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
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

<?php include __DIR__ . '/../partials/footer.php'; ?>
