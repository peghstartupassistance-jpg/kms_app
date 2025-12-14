<?php
// devis/list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('DEVIS_LIRE');

global $pdo;

// Base URL du dossier /devis, quel que soit le répertoire de l'app
$devisBasePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$today = date('Y-m-d');

$dateDebut = $_GET['date_debut'] ?? null;
$dateFin   = $_GET['date_fin'] ?? null;
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

// Les filtres de date sont OPTIONNELS (non appliqués par défaut)
if ($dateDebut !== null && $dateDebut !== '') {
    $where[] = "d.date_devis >= ?";
    $params[] = $dateDebut;
}
if ($dateFin !== null && $dateFin !== '') {
    $where[] = "d.date_devis <= ?";
    $params[] = $dateFin;
}
if ($statut !== '' && in_array($statut, ['EN_ATTENTE','ACCEPTE','REFUSE','ANNULE'], true)) {
    $where[] = "d.statut = ?";
    $params[] = $statut;
}
if ($clientId > 0) {
    $where[] = "d.client_id = ?";
    $params[] = $clientId;
}
if ($canalId > 0) {
    $where[] = "d.canal_vente_id = ?";
    $params[] = $canalId;
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
    <div class="list-page-header d-flex justify-content-between align-items-center">
        <h1 class="list-page-title h3">
            <i class="bi bi-file-earmark-text-fill"></i>
            Devis
            <span class="count-badge ms-2"><?= count($devis) ?></span>
        </h1>
        <?php if ($peutCreerDevis): ?>
            <a href="<?= htmlspecialchars($devisBasePath . '/edit.php') ?>" class="btn btn-warning btn-add-new">
                <i class="bi bi-plus-circle me-2"></i> Nouveau devis
            </a>
        <?php endif; ?>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success alert-modern">
            <i class="bi bi-check-circle-fill"></i>
            <span><?= htmlspecialchars($flashSuccess) ?></span>
        </div>
    <?php endif; ?>

    <div class="card filter-card">
        <div class="card-body">
            <form class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control"
                           value="<?= htmlspecialchars($dateDebut ?? '') ?>">
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

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-filter">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= htmlspecialchars($devisBasePath . '/list.php') ?>" class="btn btn-outline-secondary btn-filter">
                        <i class="bi bi-arrow-clockwise me-1"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card data-table-card">
        <div class="card-body">
            <?php if (empty($devis)): ?>
                <div class="empty-state">
                    <i class="bi bi-file-earmark-text"></i>
                    <h5>Aucun devis trouvé</h5>
                    <p>Aucun devis ne correspond aux filtres sélectionnés.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table modern-table">
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
                                <td>
                                    <span class="table-link">
                                        <i class="bi bi-file-text me-1"></i>
                                        <?= htmlspecialchars($d['numero']) ?>
                                    </span>
                                </td>
                                <td>
                                    <i class="bi bi-calendar3 me-1 text-muted"></i>
                                    <?= htmlspecialchars($d['date_devis']) ?>
                                </td>
                                <td>
                                    <i class="bi bi-person me-1 text-muted"></i>
                                    <?= htmlspecialchars($d['client_nom']) ?>
                                </td>
                                <td>
                                    <span class="modern-badge badge-status-primary">
                                        <i class="bi bi-megaphone"></i>
                                        <?= htmlspecialchars($d['canal_code']) ?>
                                    </span>
                                    <div class="text-muted small mt-1">
                                        <?= htmlspecialchars($d['canal_libelle']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($d['commercial_nom']) ?></td>
                                <td class="text-end fw-bold text-primary">
                                    <?= number_format((float)$d['montant_total_ht'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="text-center">
                                    <?php if ($d['est_converti']): ?>
                                        <span class="modern-badge badge-status-success">
                                            <i class="bi bi-cart-check-fill"></i>
                                            CONVERTI
                                        </span>
                                    <?php else: ?>
                                        <div data-statut-change 
                                             data-entite="devis" 
                                             data-id="<?= (int)$d['id'] ?>" 
                                             data-statut="<?= htmlspecialchars($d['statut']) ?>">
                                            <!-- Sera transformé en dropdown par tunnel-conversion.js -->
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="action-btn-group">
                                        <a href="<?= htmlspecialchars($devisBasePath . '/edit.php?id=' . (int)$d['id']) ?>"
                                           class="btn btn-sm btn-outline-primary btn-action"
                                           title="Ouvrir">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="<?= htmlspecialchars($devisBasePath . '/print.php?id=' . (int)$d['id']) ?>"
                                           class="btn btn-sm btn-outline-info btn-action"
                                           target="_blank"
                                           title="Imprimer">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <?php if (
                                            $peutCreerVente
                                            && $d['statut'] === 'ACCEPTE'
                                            && empty($d['est_converti'])
                                        ): ?>
                                            <a href="<?= htmlspecialchars($devisBasePath . '/convertir_en_vente.php?id=' . (int)$d['id']) ?>"
                                               class="btn btn-sm btn-success btn-action"
                                               title="Convertir en vente">
                                                <i class="bi bi-cart-check"></i> Convertir
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
