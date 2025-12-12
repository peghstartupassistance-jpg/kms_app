<?php
// hotel/upsell_list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('HOTEL_GERER');

global $pdo;

// Récup des réservations pour le sélecteur
$stmt = $pdo->query("
    SELECT r.id, r.date_debut, r.date_fin, c.nom AS client_nom, ch.code AS chambre_code
    FROM reservations_hotel r
    JOIN clients c ON c.id = r.client_id
    JOIN chambres ch ON ch.id = r.chambre_id
    ORDER BY r.date_debut DESC, r.id DESC
");
$reservations = $stmt->fetchAll();

// Traitement ajout upsell
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf($_POST['csrf_token'] ?? '');

    $reservationId = (int)($_POST['reservation_id'] ?? 0);
    $service       = trim($_POST['service_additionnel'] ?? '');
    $montant       = (float)str_replace(' ', '', $_POST['montant'] ?? '0');

    $errors = [];
    if ($reservationId <= 0) {
        $errors[] = "La réservation est obligatoire.";
    }
    if ($service === '') {
        $errors[] = "Le service additionnel est obligatoire.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO upsell_hotel (reservation_id, service_additionnel, montant)
            VALUES (:reservation_id, :service, :montant)
        ");
        $stmt->execute([
            'reservation_id' => $reservationId,
            'service'        => $service,
            'montant'        => $montant,
        ]);

        $_SESSION['flash_success'] = "Service additionnel enregistré.";
        header('Location: ' . url_for('hotel/upsell_list.php'));
        exit;
    } else {
        $_SESSION['flash_error'] = implode(' ', $errors);
        header('Location: ' . url_for('hotel/upsell_list.php'));
        exit;
    }
}

// Filtres
$dateDeb = $_GET['date_debut'] ?? '';
$dateFin = $_GET['date_fin'] ?? '';
$resId   = isset($_GET['reservation_id']) ? (int)$_GET['reservation_id'] : 0;

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
if ($resId > 0) {
    $where[] = "u.reservation_id = :res_id";
    $params['res_id'] = $resId;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// Liste upsell
$sql = "
    SELECT
        u.*,
        r.date_debut,
        r.date_fin,
        c.nom AS client_nom,
        ch.code AS chambre_code
    FROM upsell_hotel u
    JOIN reservations_hotel r ON r.id = u.reservation_id
    JOIN clients c ON c.id = r.client_id
    JOIN chambres ch ON ch.id = r.chambre_id
    $whereSql
    ORDER BY r.date_debut DESC, u.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$upsells = $stmt->fetchAll();

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Upsell hôtel (services additionnels)</h1>
        <a href="<?= url_for('hotel/reservations.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-building me-1"></i> Retour aux réservations
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

    <!-- Formulaire ajout upsell -->
    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">Ajouter un service additionnel</h2>
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

                <div class="col-md-6">
                    <label class="form-label small">Réservation</label>
                    <select name="reservation_id" class="form-select" required>
                        <option value="">Sélectionner...</option>
                        <?php foreach ($reservations as $r): ?>
                            <option value="<?= (int)$r['id'] ?>">
                                <?= 'RES-' . (int)$r['id'] ?> |
                                <?= htmlspecialchars($r['client_nom']) ?> |
                                Chambre <?= htmlspecialchars($r['chambre_code']) ?> |
                                <?= htmlspecialchars($r['date_debut']) ?> → <?= htmlspecialchars($r['date_fin']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Service additionnel</label>
                    <input type="text" name="service_additionnel" class="form-control"
                           placeholder="Petit-déjeuner, taxi, etc." required>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Montant (FCFA)</label>
                    <input type="number" name="montant" class="form-control" min="0" step="1" required>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Enregistrer l’upsell
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filtres liste -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-md-3">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control"
                           value="<?= htmlspecialchars($dateDeb) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control"
                           value="<?= htmlspecialchars($dateFin) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Réservation</label>
                    <select name="reservation_id" class="form-select">
                        <option value="0">Toutes</option>
                        <?php foreach ($reservations as $r): ?>
                            <option value="<?= (int)$r['id'] ?>"
                                <?= $resId === (int)$r['id'] ? 'selected' : '' ?>>
                                <?= 'RES-' . (int)$r['id'] ?> – <?= htmlspecialchars($r['client_nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2 mt-3 mt-md-0">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('hotel/upsell_list.php') ?>" class="btn btn-outline-secondary">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste upsell -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($upsells)): ?>
                <p class="text-muted mb-0">Aucun service additionnel enregistré.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Réservation</th>
                            <th>Client</th>
                            <th>Chambre</th>
                            <th>Période</th>
                            <th>Service additionnel</th>
                            <th class="text-end">Montant</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($upsells as $u): ?>
                            <tr>
                                <td>RES-<?= (int)$u['reservation_id'] ?></td>
                                <td><?= htmlspecialchars($u['client_nom']) ?></td>
                                <td><?= htmlspecialchars($u['chambre_code']) ?></td>
                                <td><?= htmlspecialchars($u['date_debut']) ?> → <?= htmlspecialchars($u['date_fin']) ?></td>
                                <td><?= htmlspecialchars($u['service_additionnel']) ?></td>
                                <td class="text-end">
                                    <?= number_format((float)$u['montant'], 0, ',', ' ') ?> FCFA
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
