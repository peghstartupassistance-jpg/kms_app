<?php
// devis/list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('DEVIS_LIRE');

global $pdo;

// Base URL du dossier /devis, quel que soit le répertoire de l'app
$devisBasePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$today = date('Y-m-d');

$dateDebut = $_GET['date_debut'] ?? $today;
$dateFin   = $_GET['date_fin'] ?? $today;
$statut    = $_GET['statut'] ?? '';
$clientId  = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$canalId   = isset($_GET['canal_id']) ? (int)$_GET['canal_id'] : 0;

// Clients pour filtre
$stmt = $pdo->query("SELECT id, nom FROM clients ORDER BY nom");
$clients = $stmt->fetchAll();

// Canaux de vente pour filtre
$stmt = $pdo->query("SELECT id, code, libelle FROM canaux_vente ORDER BY code");
$canaux = $stmt->fetchAll();

$where  = [];
$params = [];

if ($dateDebut !== '') {
    $where[] = "d.date_devis >= :date_debut";
    $params['date_debut'] = $dateDebut;
}
if ($dateFin !== '') {
    $where[] = "d.date_devis <= :date_fin";
    $params['date_fin'] = $dateFin;
}
if ($statut !== '' && in_array($statut, ['EN_ATTENTE','ACCEPTE','REFUSE','ANNULE'], true)) {
    $where[] = "d.statut = :statut";
    $params['statut'] = $statut;
}
if ($clientId > 0) {
    $where[] = "d.client_id = :client_id";
    $params['client_id'] = $clientId;
}
if ($canalId > 0) {
    $where[] = "d.canal_vente_id = :canal_id";
    $params['canal_id'] = $canalId;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

$sql = "
    SELECT
        d.*,
        c.nom AS client_nom,
        cv.code AS canal_code,
        cv.libelle AS canal_libelle,
        u.nom_complet AS commercial_nom
    FROM devis d
    JOIN clients c ON c.id = d.client_id
    JOIN canaux_vente cv ON cv.id = d.canal_vente_id
    JOIN utilisateurs u ON u.id = d.utilisateur_id
    $whereSql
    ORDER BY d.date_devis DESC, d.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$devis = $stmt->fetchAll();

$peutCreerDevis = in_array('DEVIS_CREER', $_SESSION['permissions'] ?? [], true);
$peutCreerVente = in_array('VENTES_CREER', $_SESSION['permissions'] ?? [], true);

$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Devis</h1>
        <?php if ($peutCreerDevis): ?>
            <a href="<?= htmlspecialchars($devisBasePath . '/edit.php') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Nouveau devis
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
                        <?php foreach (['EN_ATTENTE','ACCEPTE','REFUSE','ANNULE'] as $s): ?>
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
                    <a href="<?= htmlspecialchars($devisBasePath . '/list.php') ?>" class="btn btn-outline-secondary">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($devis)): ?>
                <p class="text-muted mb-0">Aucun devis trouvé pour les filtres sélectionnés.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>N° devis</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Canal</th>
                            <th>Commercial</th>
                            <th class="text-end">Montant HT</th>
                            <th class="text-center">Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($devis as $d): ?>
                            <tr>
                                <td><?= htmlspecialchars($d['numero']) ?></td>
                                <td><?= htmlspecialchars($d['date_devis']) ?></td>
                                <td><?= htmlspecialchars($d['client_nom']) ?></td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary">
                                        <?= htmlspecialchars($d['canal_code']) ?>
                                    </span>
                                    <span class="text-muted small d-block">
                                        <?= htmlspecialchars($d['canal_libelle']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($d['commercial_nom']) ?></td>
                                <td class="text-end">
                                    <?= number_format((float)$d['montant_total_ht'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="text-center">
                                    <?php
                                    $badgeClass = 'bg-secondary-subtle text-secondary';
                                    if ($d['statut'] === 'EN_ATTENTE') {
                                        $badgeClass = 'bg-warning-subtle text-warning';
                                    } elseif ($d['statut'] === 'ACCEPTE') {
                                        $badgeClass = 'bg-success-subtle text-success';
                                    } elseif ($d['statut'] === 'REFUSE') {
                                        $badgeClass = 'bg-danger-subtle text-danger';
                                    } elseif ($d['statut'] === 'ANNULE') {
                                        $badgeClass = 'bg-dark-subtle text-dark';
                                    }

                                    $libelleStatut = $d['statut'];
                                    if ($d['statut'] === 'ACCEPTE' && !empty($d['est_converti'])) {
                                        $libelleStatut = 'ACCEPTE (converti)';
                                    }
                                    ?>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($libelleStatut) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= htmlspecialchars($devisBasePath . '/edit.php?id=' . (int)$d['id']) ?>"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil-square me-1"></i> Ouvrir
                                    </a>
                                    <a href="<?= htmlspecialchars($devisBasePath . '/print.php?id=' . (int)$d['id']) ?>"
                                       class="btn btn-sm btn-outline-info"
                                       target="_blank"
                                       title="Imprimer le devis">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                    <?php if (
                                        $peutCreerVente
                                        && $d['statut'] === 'ACCEPTE'
                                        && empty($d['est_converti'])
                                    ): ?>
                                        <a href="<?= htmlspecialchars($devisBasePath . '/convertir_en_vente.php?id=' . (int)$d['id']) ?>"
                                           class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-cart-check me-1"></i> Convertir en vente
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
