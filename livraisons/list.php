<?php
// livraisons/list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

$dateDeb  = $_GET['date_debut'] ?? '';
$dateFin  = $_GET['date_fin'] ?? '';
$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$signe    = $_GET['signe'] ?? ''; // '', '0', '1'

// Clients
$stmt = $pdo->query("SELECT id, nom FROM clients ORDER BY nom");
$clients = $stmt->fetchAll();

$where  = [];
$params = [];

if ($dateDeb !== '') {
    $where[] = "b.date_bl >= :date_debut";
    $params['date_debut'] = $dateDeb;
}
if ($dateFin !== '') {
    $where[] = "b.date_bl <= :date_fin";
    $params['date_fin'] = $dateFin;
}
if ($clientId > 0) {
    $where[] = "b.client_id = :client_id";
    $params['client_id'] = $clientId;
}
if ($signe !== '' && in_array($signe, ['0','1'], true)) {
    $where[] = "b.signe_client = :signe";
    $params['signe'] = (int)$signe;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

$sql = "
    SELECT
        b.*,
        c.nom AS client_nom,
        v.numero AS vente_numero
    FROM bons_livraison b
    JOIN clients c ON c.id = b.client_id
    LEFT JOIN ventes v ON v.id = b.vente_id
    $whereSql
    ORDER BY b.date_bl DESC, b.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bons = $stmt->fetchAll();

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Bons de livraison</h1>
        <a href="<?= url_for('ventes/list.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Retour aux ventes
        </a>
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

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Date BL ≥</label>
                    <input type="date" name="date_debut" class="form-control"
                           value="<?= htmlspecialchars($dateDeb) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Date BL ≤</label>
                    <input type="date" name="date_fin" class="form-control"
                           value="<?= htmlspecialchars($dateFin) ?>">
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
                <div class="col-md-2">
                    <label class="form-label small">Signature client</label>
                    <select name="signe" class="form-select">
                        <option value="">Tous</option>
                        <option value="1" <?= $signe === '1' ? 'selected' : '' ?>>Signés</option>
                        <option value="0" <?= $signe === '0' ? 'selected' : '' ?>>Non signés</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary mt-4">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('livraisons/list.php') ?>" class="btn btn-outline-secondary mt-4">
                        Réinit.
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($bons)): ?>
                <p class="text-muted mb-0">Aucun bon de livraison trouvé.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>N° BL</th>
                            <th>Date BL</th>
                            <th>Vente</th>
                            <th>Client</th>
                            <th>Transport</th>
                            <th>Signé client</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($bons as $b): ?>
                            <tr>
                                <td><?= htmlspecialchars($b['numero']) ?></td>
                                <td><?= htmlspecialchars($b['date_bl']) ?></td>
                                <td><?= htmlspecialchars($b['vente_numero'] ?? '') ?></td>
                                <td><?= htmlspecialchars($b['client_nom']) ?></td>
                                <td><?= htmlspecialchars($b['transport_assure_par'] ?? '') ?></td>
                                <td>
                                    <?php if ((int)$b['signe_client'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success">
                                            <i class="bi bi-check2-circle me-1"></i> Signé
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning">
                                            Non signé
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ((int)$b['signe_client'] === 0): ?>
                                        <form method="post" action="<?= url_for('livraisons/marquer_signe.php') ?>" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                                            <input type="hidden" name="bl_id" value="<?= (int)$b['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-pen me-1"></i> Marquer signé
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($b['vente_id']): ?>
                                        <a href="<?= url_for('ventes/detail.php') . '?id=' . (int)$b['vente_id'] ?>"
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye me-1"></i> Voir vente
                                        </a>
                                    <?php endif; ?>
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
