<?php
// litiges/list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';

$today      = date('Y-m-d');
$dateDebut  = $_GET['date_debut'] ?? $today;
$dateFin    = $_GET['date_fin'] ?? $today;
$statut     = $_GET['statut'] ?? '';
$clientId   = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$produitId  = isset($_GET['produit_id']) ? (int)$_GET['produit_id'] : 0;

// Clients pour filtre
$stmt = $pdo->query("SELECT id, nom FROM clients ORDER BY nom");
$clients = $stmt->fetchAll();

// Produits pour filtre
$stmt = $pdo->query("SELECT id, code_produit, designation FROM produits ORDER BY code_produit");
$produits = $stmt->fetchAll();

// WHERE
$where  = [];
$params = [];

if ($dateDebut !== '') {
    $where[] = "rl.date_retour >= :date_debut";
    $params['date_debut'] = $dateDebut;
}
if ($dateFin !== '') {
    $where[] = "rl.date_retour <= :date_fin";
    $params['date_fin'] = $dateFin;
}
if ($statut !== '' && in_array($statut, ['EN_COURS','RESOLU','ABANDONNE'], true)) {
    $where[] = "rl.statut_traitement = :statut";
    $params['statut'] = $statut;
}
if ($clientId > 0) {
    $where[] = "rl.client_id = :client_id";
    $params['client_id'] = $clientId;
}
if ($produitId > 0) {
    $where[] = "rl.produit_id = :produit_id";
    $params['produit_id'] = $produitId;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

$sql = "
    SELECT
        rl.*,
        c.nom AS client_nom,
        p.code_produit,
        p.designation AS produit_nom,
        v.numero AS vente_numero,
        u.nom_complet AS responsable_nom
    FROM retours_litiges rl
    JOIN clients c ON c.id = rl.client_id
    JOIN produits p ON p.id = rl.produit_id
    LEFT JOIN ventes v ON v.id = rl.vente_id
    LEFT JOIN utilisateurs u ON u.id = rl.responsable_suivi_id
    $whereSql
    ORDER BY rl.date_retour DESC, rl.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$litiges = $stmt->fetchAll();

$peutEditer = in_array('VENTES_CREER', $_SESSION['permissions'] ?? [], true);

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Retours & litiges clients</h1>
        <?php if ($peutEditer): ?>
            <a href="<?= url_for('litiges/edit.php') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Nouveau litige
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
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control"
                           value="<?= htmlspecialchars($dateDebut) ?>">
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
                        <?php foreach (['EN_COURS','RESOLU','ABANDONNE'] as $s): ?>
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
                    <label class="form-label small">Produit</label>
                    <select name="produit_id" class="form-select">
                        <option value="0">Tous</option>
                        <?php foreach ($produits as $pr): ?>
                            <option value="<?= (int)$pr['id'] ?>"
                                <?= $produitId === (int)$pr['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pr['code_produit']) ?> – <?= htmlspecialchars($pr['designation']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('litiges/list.php') ?>" class="btn btn-outline-secondary">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($litiges)): ?>
                <p class="text-muted mb-0">Aucun litige trouvé pour les filtres sélectionnés.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Date retour</th>
                            <th>Client</th>
                            <th>Produit</th>
                            <th>Vente</th>
                            <th>Motif</th>
                            <th>Responsable</th>
                            <th class="text-center">Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($litiges as $l): ?>
                            <?php
                            $badgeClass = 'bg-secondary-subtle text-secondary';
                            if ($l['statut_traitement'] === 'EN_COURS') $badgeClass = 'bg-warning-subtle text-warning';
                            elseif ($l['statut_traitement'] === 'RESOLU') $badgeClass = 'bg-success-subtle text-success';
                            elseif ($l['statut_traitement'] === 'ABANDONNE') $badgeClass = 'bg-dark-subtle text-dark';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($l['date_retour']) ?></td>
                                <td><?= htmlspecialchars($l['client_nom']) ?></td>
                                <td>
                                    <span class="fw-semibold"><?= htmlspecialchars($l['code_produit']) ?></span><br>
                                    <span class="text-muted small"><?= htmlspecialchars($l['produit_nom']) ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($l['vente_id'])): ?>
                                        <span class="small">Vente n° <?= htmlspecialchars($l['vente_numero'] ?? $l['vente_id']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= nl2br(htmlspecialchars($l['motif'])) ?></td>
                                <td><?= htmlspecialchars($l['responsable_nom'] ?? '') ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($l['statut_traitement']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php if ($peutEditer): ?>
                                        <a href="<?= url_for('litiges/edit.php') . '?id=' . (int)$l['id'] ?>"
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil-square me-1"></i> Suivre
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
