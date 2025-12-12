<?php
// ventes/list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

$today    = date('Y-m-d');
$dateDeb  = $_GET['date_debut'] ?? $today;
$dateFin  = $_GET['date_fin'] ?? $today;
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

if ($dateDeb !== '') {
    $where[] = "v.date_vente >= :date_debut";
    $params['date_debut'] = $dateDeb;
}
if ($dateFin !== '') {
    $where[] = "v.date_vente <= :date_fin";
    $params['date_fin'] = $dateFin;
}
if ($statut !== '' && in_array($statut, ['EN_ATTENTE_LIVRAISON','LIVREE','ANNULEE','PARTIELLEMENT_LIVREE'], true)) {
    $where[] = "v.statut = :statut";
    $params['statut'] = $statut;
}
if ($clientId > 0) {
    $where[] = "v.client_id = :client_id";
    $params['client_id'] = $clientId;
}
if ($canalId > 0) {
    $where[] = "v.canal_vente_id = :canal_id";
    $params['canal_id'] = $canalId;
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
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Ventes</h1>
        <?php if ($peutCreerVente): ?>
            <a href="<?= url_for('ventes/edit.php') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Nouvelle vente
            </a>
        <?php endif; ?>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success py-2">
            <i class="bi bi-check-circle me-1"></i>
            <?= htmlspecialchars($flashSuccess) ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger py-2">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?= htmlspecialchars($flashError) ?>
        </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-md-2">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control"
                           value="<?= htmlspecialchars($dateDeb) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control"
                           value="<?= htmlspecialchars($dateFin) ?>">
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

                <div class="col-12 d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('ventes/list.php') ?>" class="btn btn-outline-secondary">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des ventes -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($ventes)): ?>
                <p class="text-muted mb-0">Aucune vente trouvée pour les filtres sélectionnés.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
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
                            $badgeClass = 'bg-secondary-subtle text-secondary';
                            if ($v['statut'] === 'EN_ATTENTE_LIVRAISON') $badgeClass = 'bg-warning-subtle text-warning';
                            elseif ($v['statut'] === 'PARTIELLEMENT_LIVREE') $badgeClass = 'bg-info-subtle text-info';
                            elseif ($v['statut'] === 'LIVREE') $badgeClass = 'bg-success-subtle text-success';
                            elseif ($v['statut'] === 'ANNULEE') $badgeClass = 'bg-dark-subtle text-dark';
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= url_for('ventes/detail.php') . '?id=' . (int)$v['id'] ?>"
                                       class="link-primary text-decoration-none">
                                        <?= htmlspecialchars($v['numero']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($v['date_vente']) ?></td>
                                <td><?= htmlspecialchars($v['client_nom']) ?></td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary">
                                        <?= htmlspecialchars($v['canal_code']) ?>
                                    </span>
                                    <span class="text-muted small d-block">
                                        <?= htmlspecialchars($v['canal_libelle']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($v['commercial_nom']) ?></td>
                                <td class="text-end">
                                    <?= number_format((float)$v['montant_total_ht'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="text-end">
                                    <?= number_format((float)$v['montant_total_ttc'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($v['statut']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url_for('ventes/detail.php') . '?id=' . (int)$v['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye me-1"></i> Détails
                                    </a>
                                    <a href="<?= url_for('ventes/print.php') . '?id=' . (int)$v['id'] ?>"
                                       class="btn btn-sm btn-outline-info"
                                       target="_blank"
                                       title="Imprimer la facture">
                                        <i class="bi bi-printer me-1"></i>
                                    </a>
                                    <?php if ($peutCreerVente): ?>
                                        <a href="<?= url_for('ventes/edit.php') . '?id=' . (int)$v['id'] ?>"
                                           class="btn btn-sm btn-outline-secondary ms-1">
                                            <i class="bi bi-pencil-square me-1"></i> Modifier
                                        </a>
                                    <?php endif; ?>
                                    <!-- Bouton 'Générer BL' géré depuis la page détail -->
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
