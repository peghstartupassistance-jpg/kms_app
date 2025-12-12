<?php
// clients/list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CLIENTS_LIRE');

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';

global $pdo;

// Récup types client pour filtre
$stmtTypes = $pdo->query("SELECT id, code, libelle FROM types_client ORDER BY code");
$typesClient = $stmtTypes->fetchAll();

// Filtres
$typeId = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;
$statut = trim($_GET['statut'] ?? '');
$q      = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

if ($typeId > 0) {
    $where[] = "c.type_client_id = :type_id";
    $params['type_id'] = $typeId;
}
if ($statut !== '') {
    $where[] = "c.statut = :statut";
    $params['statut'] = $statut;
}
if ($q !== '') {
    $where[] = "(c.nom LIKE :q OR c.telephone LIKE :q2 OR c.email LIKE :q3)";
    $params['q']  = '%' . $q . '%';
    $params['q2'] = '%' . $q . '%';
    $params['q3'] = '%' . $q . '%';
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

$sql = "
    SELECT 
        c.*,
        t.code AS type_code,
        t.libelle AS type_libelle
    FROM clients c
    JOIN types_client t ON t.id = c.type_client_id
    $whereSql
    ORDER BY c.date_creation DESC, c.nom ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$peutCreer = in_array('CLIENTS_CREER', $_SESSION['permissions'] ?? [], true);

$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Clients & prospects</h1>
        <?php if ($peutCreer): ?>
            <a href="<?= url_for('clients/edit.php') ?>" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i> Nouveau client
            </a>
        <?php endif; ?>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success py-2">
            <i class="bi bi-check-circle me-1"></i>
            <?= htmlspecialchars($flashSuccess) ?>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Recherche</label>
                    <input type="text" name="q" class="form-control"
                           placeholder="Nom, téléphone, email..."
                           value="<?= htmlspecialchars($q) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Type</label>
                    <select name="type_id" class="form-select">
                        <option value="0">Tous les types</option>
                        <?php foreach ($typesClient as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"
                                <?= $typeId === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['libelle']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="">Tous les statuts</option>
                        <?php
                        $statuts = ['PROSPECT','CLIENT','APPRENANT','HOTE'];
                        foreach ($statuts as $s):
                        ?>
                            <option value="<?= $s ?>" <?= $statut === $s ? 'selected' : '' ?>>
                                <?= $s ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary mt-4">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('clients/list.php') ?>" class="btn btn-outline-secondary mt-4">
                        Réinit.
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($clients)): ?>
                <p class="text-muted mb-0">Aucun client trouvé.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Téléphone</th>
                            <th>Email</th>
                            <th>Source</th>
                            <th>Date création</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($clients as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['nom']) ?></td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary">
                                        <?= htmlspecialchars($c['type_code']) ?>
                                    </span>
                                    <span class="text-muted small d-block">
                                        <?= htmlspecialchars($c['type_libelle']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = 'bg-secondary-subtle text-secondary';
                                    if ($c['statut'] === 'CLIENT') $badgeClass = 'bg-success-subtle text-success';
                                    elseif ($c['statut'] === 'PROSPECT') $badgeClass = 'bg-warning-subtle text-warning';
                                    elseif ($c['statut'] === 'APPRENANT') $badgeClass = 'bg-info-subtle text-info';
                                    elseif ($c['statut'] === 'HOTE') $badgeClass = 'bg-purple-subtle text-purple';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($c['statut']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($c['telephone'] ?? '') ?></td>
                                <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($c['source'] ?? '') ?></td>
                                <td><?= htmlspecialchars($c['date_creation']) ?></td>
                                <td class="text-end">
                                    <?php if ($peutCreer): ?>
                                        <a href="<?= url_for('clients/edit.php') . '?id=' . (int)$c['id'] ?>"
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil-square me-1"></i> Modifier
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
