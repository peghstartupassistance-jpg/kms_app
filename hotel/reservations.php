<?php
// hotel/reservations.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('HOTEL_GERER');

global $pdo;

$today   = date('Y-m-d');
$dateDeb = $_GET['date_debut'] ?? $today;
$dateFin = $_GET['date_fin'] ?? $today;
$statut  = $_GET['statut'] ?? '';
$clientId  = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$chambreId = isset($_GET['chambre_id']) ? (int)$_GET['chambre_id'] : 0;

// Clients
$stmt = $pdo->query("SELECT id, nom FROM clients ORDER BY nom");
$clients = $stmt->fetchAll();

// Chambres
$stmt = $pdo->query("SELECT id, code FROM chambres WHERE actif = 1 ORDER BY code");
$chambres = $stmt->fetchAll();

// Filtres
$where  = [];
$params = [];

if ($dateDeb !== '') {
    $where[] = "r.date_debut >= :date_debut";
    $params['date_debut'] = $dateDeb;
}
if ($dateFin !== '') {
    $where[] = "r.date_fin <= :date_fin";
    $params['date_fin'] = $dateFin;
}
if ($statut !== '' && in_array($statut, ['EN_COURS','TERMINEE','ANNULEE'], true)) {
    $where[] = "r.statut = :statut";
    $params['statut'] = $statut;
}
if ($clientId > 0) {
    $where[] = "r.client_id = :client_id";
    $params['client_id'] = $clientId;
}
if ($chambreId > 0) {
    $where[] = "r.chambre_id = :chambre_id";
    $params['chambre_id'] = $chambreId;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// Requête principale
$sql = "
    SELECT
        r.*,
        c.nom AS client_nom,
        ch.code AS chambre_code,
        mp.libelle AS mode_paiement_libelle
    FROM reservations_hotel r
    JOIN clients c ON c.id = r.client_id
    JOIN chambres ch ON ch.id = r.chambre_id
    LEFT JOIN modes_paiement mp ON mp.id = r.mode_paiement_id
    $whereSql
    ORDER BY r.date_debut DESC, r.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">

    <!-- EN-TÊTE AVEC RACCOURCIS -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Réservations hôtel</h1>

        <div class="d-flex gap-2">
            <a href="<?= url_for('hotel/chambres_list.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-door-closed me-1"></i> Chambres
            </a>
            <a href="<?= url_for('hotel/visiteurs_list.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-person-walking me-1"></i> Visiteurs
            </a>
            <a href="<?= url_for('hotel/upsell_list.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-plus-circle-dotted me-1"></i> Upsell
            </a>
            <a href="<?= url_for('hotel/reservation_edit.php') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Nouvelle réservation
            </a>
        </div>
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
                        <?php foreach (['EN_COURS','TERMINEE','ANNULEE'] as $s): ?>
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
                    <label class="form-label small">Chambre</label>
                    <select name="chambre_id" class="form-select">
                        <option value="0">Toutes</option>
                        <?php foreach ($chambres as $ch): ?>
                            <option value="<?= (int)$ch['id'] ?>"
                                <?= $chambreId === (int)$ch['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ch['code']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('hotel/reservations.php') ?>" class="btn btn-outline-secondary">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des réservations -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($reservations)): ?>
                <p class="text-muted mb-0">Aucune réservation trouvée pour les filtres sélectionnés.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Client</th>
                            <th>Chambre</th>
                            <th>Période</th>
                            <th class="text-center">Nuits</th>
                            <th>Statut</th>
                            <th>Mode de paiement</th>
                            <th class="text-end">Montant total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($reservations as $r): ?>
                            <?php
                            $badgeClass = 'bg-secondary-subtle text-secondary';
                            if ($r['statut'] === 'EN_COURS') $badgeClass = 'bg-warning-subtle text-warning';
                            elseif ($r['statut'] === 'TERMINEE') $badgeClass = 'bg-success-subtle text-success';
                            elseif ($r['statut'] === 'ANNULEE') $badgeClass = 'bg-dark-subtle text-dark';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($r['client_nom']) ?></td>
                                <td><?= htmlspecialchars($r['chambre_code']) ?></td>
                                <td>
                                    <?= htmlspecialchars($r['date_debut']) ?> → <?= htmlspecialchars($r['date_fin']) ?>
                                </td>
                                <td class="text-center"><?= (int)$r['nb_nuits'] ?></td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($r['statut']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($r['mode_paiement_libelle'] ?? 'Non renseigné') ?></td>
                                <td class="text-end">
                                    <?= number_format((float)$r['montant_total'], 0, ',', ' ') ?> FCFA
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
