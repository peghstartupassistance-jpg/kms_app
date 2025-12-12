<?php
// promotions/list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('PROMOTIONS_GERER');

global $pdo;

$today      = date('Y-m-d');
$q          = trim($_GET['q'] ?? '');
$dateDebut  = $_GET['date_debut'] ?? '';
$dateFin    = $_GET['date_fin'] ?? '';
$actifFiltre = $_GET['actif'] ?? '';

// Construction WHERE
$where  = [];
$params = [];

if ($q !== '') {
    $where[] = "p.nom LIKE :q";
    $params['q'] = '%' . $q . '%';
}
if ($dateDebut !== '') {
    $where[] = "p.date_debut >= :date_debut";
    $params['date_debut'] = $dateDebut;
}
if ($dateFin !== '') {
    $where[] = "p.date_fin <= :date_fin";
    $params['date_fin'] = $dateFin;
}
if ($actifFiltre !== '' && in_array($actifFiltre, ['0','1'], true)) {
    $where[] = "p.actif = :actif";
    $params['actif'] = (int)$actifFiltre;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// Requête promotions + nb de produits liés
$sql = "
    SELECT
        p.*,
        COUNT(pp.produit_id) AS nb_produits
    FROM promotions p
    LEFT JOIN promotion_produit pp ON pp.promotion_id = p.id
    $whereSql
    GROUP BY p.id
    ORDER BY p.date_debut DESC, p.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$promotions = $stmt->fetchAll();

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Promotions commerciales</h1>
        <a href="<?= url_for('promotions/edit.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Nouvelle promotion
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

    <!-- Filtres -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Recherche</label>
                    <input type="text" name="q" class="form-control"
                           placeholder="Nom de la promotion..."
                           value="<?= htmlspecialchars($q) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Date début ≥</label>
                    <input type="date" name="date_debut" class="form-control"
                           value="<?= htmlspecialchars($dateDebut) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Date fin ≤</label>
                    <input type="date" name="date_fin" class="form-control"
                           value="<?= htmlspecialchars($dateFin) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Actif</label>
                    <select name="actif" class="form-select">
                        <option value="">Tous</option>
                        <option value="1" <?= $actifFiltre === '1' ? 'selected' : '' ?>>Actifs</option>
                        <option value="0" <?= $actifFiltre === '0' ? 'selected' : '' ?>>Inactifs</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary mt-4">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('promotions/list.php') ?>" class="btn btn-outline-secondary mt-4">
                        Réinit.
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste promotions -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($promotions)): ?>
                <p class="text-muted mb-0">Aucune promotion trouvée pour les filtres sélectionnés.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Nom</th>
                            <th>Période</th>
                            <th>Type remise</th>
                            <th class="text-center">Produits associés</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Actif</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($promotions as $p): ?>
                            <?php
                            $dateDeb = $p['date_debut'];
                            $dateFin = $p['date_fin'];
                            $enCours = false;
                            $statutLib = 'Programmée';
                            $badgeStatut = 'bg-secondary-subtle text-secondary';

                            if ($today < $dateDeb) {
                                $statutLib   = 'Programmée';
                                $badgeStatut = 'bg-info-subtle text-info';
                            } elseif ($today >= $dateDeb && $today <= $dateFin) {
                                $statutLib   = 'En cours';
                                $badgeStatut = 'bg-success-subtle text-success';
                                $enCours     = true;
                            } else {
                                $statutLib   = 'Terminée';
                                $badgeStatut = 'bg-dark-subtle text-dark';
                            }

                            $typeRemise = '—';
                            if (!is_null($p['pourcentage_remise']) && (float)$p['pourcentage_remise'] > 0) {
                                $typeRemise = number_format((float)$p['pourcentage_remise'], 2, ',', ' ') . ' %';
                            } elseif (!is_null($p['montant_remise']) && (float)$p['montant_remise'] > 0) {
                                $typeRemise = number_format((float)$p['montant_remise'], 0, ',', ' ') . ' FCFA';
                            }
                            ?>
                            <tr>
                                <td>
                                    <span class="fw-semibold"><?= htmlspecialchars($p['nom']) ?></span><br>
                                    <?php if (!empty($p['description'])): ?>
                                        <span class="text-muted small"><?= nl2br(htmlspecialchars($p['description'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    Du <?= htmlspecialchars($dateDeb) ?><br>
                                    au <?= htmlspecialchars($dateFin) ?>
                                </td>
                                <td><?= $typeRemise ?></td>
                                <td class="text-center">
                                    <span class="badge bg-primary-subtle text-primary">
                                        <?= (int)$p['nb_produits'] ?> produit(s)
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $badgeStatut ?>">
                                        <?= htmlspecialchars($statutLib) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)$p['actif'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url_for('promotions/edit.php') . '?id=' . (int)$p['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil-square me-1"></i> Modifier
                                    </a>
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
