<?php
// hotel/visiteurs_list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('HOTEL_GERER');

global $pdo;

// -----------------------------
// 1. Traitement ajout visiteur
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf($_POST['csrf_token'] ?? '');

    $dateVisite  = $_POST['date_visite'] ?? date('Y-m-d');
    $nom         = trim($_POST['nom_visiteur'] ?? '');
    $telephone   = trim($_POST['telephone'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $service     = trim($_POST['service_solicite'] ?? '');
    $orientation = trim($_POST['orientation'] ?? '');
    $motif       = trim($_POST['motif'] ?? ''); // besoin / détails

    $errors = [];

    if ($nom === '') {
        $errors[] = "Le nom du visiteur est obligatoire.";
    }

    // Au moins un moyen de contact
    if ($telephone === '' && $email === '') {
        $errors[] = "Renseigne au moins un moyen de contact (téléphone ou email).";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO visiteurs_hotel
                (date_visite, nom_visiteur, telephone, email, motif,
                 service_solicite, orientation, concierge_id)
            VALUES
                (:date_visite, :nom, :telephone, :email, :motif,
                 :service, :orientation, :concierge_id)
        ");
        $stmt->execute([
            'date_visite'  => $dateVisite,
            'nom'          => $nom,
            'telephone'    => $telephone !== '' ? $telephone : null,
            'email'        => $email !== '' ? $email : null,
            'motif'        => $motif,
            'service'      => $service,
            'orientation'  => $orientation,
            'concierge_id' => utilisateurConnecte()['id'] ?? null,
        ]);

        $_SESSION['flash_success'] = "Visiteur enregistré avec succès.";
        header('Location: ' . url_for('hotel/visiteurs_list.php'));
        exit;
    } else {
        $_SESSION['flash_error'] = implode(' ', $errors);
        header('Location: ' . url_for('hotel/visiteurs_list.php'));
        exit;
    }
}

// -----------------------------
// 2. Filtres liste visiteurs
// -----------------------------
$dateDeb = $_GET['date_debut'] ?? date('Y-m-d');
$dateFin = $_GET['date_fin'] ?? date('Y-m-d');

$where  = [];
$params = [];

if ($dateDeb !== '') {
    $where[] = "vh.date_visite >= :date_debut";
    $params['date_debut'] = $dateDeb;
}
if ($dateFin !== '') {
    $where[] = "vh.date_visite <= :date_fin";
    $params['date_fin'] = $dateFin;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// Liste visiteurs
$sql = "
    SELECT
        vh.*,
        u.nom_complet AS concierge_nom
    FROM visiteurs_hotel vh
    LEFT JOIN utilisateurs u ON u.id = vh.concierge_id
    $whereSql
    ORDER BY vh.date_visite DESC, vh.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$visiteurs = $stmt->fetchAll();

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Visiteurs hôtel / conciergerie</h1>
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

    <!-- Formulaire rapide d'ajout -->
    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">Enregistrer un visiteur</h2>
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

                <div class="col-md-2">
                    <label class="form-label small">Date de visite</label>
                    <input type="date" name="date_visite" class="form-control"
                           value="<?= htmlspecialchars($dateDeb) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Nom du visiteur</label>
                    <input type="text" name="nom_visiteur" class="form-control" required>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Téléphone</label>
                    <input type="text" name="telephone" class="form-control"
                           placeholder="(+237) 6...">
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Email</label>
                    <input type="email" name="email" class="form-control"
                           placeholder="exemple@mail.com">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Service sollicité</label>
                    <input type="text" name="service_solicite" class="form-control"
                           placeholder="Hébergement, info, visite...">
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Orientation</label>
                    <input type="text" name="orientation" class="form-control"
                           placeholder="Booking, réservation, autre...">
                </div>

                <div class="col-12">
                    <label class="form-label small">Besoin / motif</label>
                    <textarea name="motif" rows="2" class="form-control"
                              placeholder="Ce que le visiteur recherche précisément, détails complémentaires..."></textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Enregistrer le visiteur
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
                <div class="col-md-6 d-flex gap-2 mt-3 mt-md-0">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('hotel/visiteurs_list.php') ?>" class="btn btn-outline-secondary">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des visiteurs -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($visiteurs)): ?>
                <p class="text-muted mb-0">Aucun visiteur trouvé pour la période sélectionnée.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Nom</th>
                            <th>Téléphone</th>
                            <th>Email</th>
                            <th>Service sollicité</th>
                            <th>Besoin / motif</th>
                            <th>Orientation</th>
                            <th>Concierge</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($visiteurs as $v): ?>
                            <tr>
                                <td><?= htmlspecialchars($v['date_visite']) ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($v['nom_visiteur']) ?></td>
                                <td><?= htmlspecialchars($v['telephone'] ?? '') ?></td>
                                <td><?= htmlspecialchars($v['email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($v['service_solicite'] ?? '') ?></td>
                                <td><?= nl2br(htmlspecialchars($v['motif'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($v['orientation'] ?? '') ?></td>
                                <td><?= htmlspecialchars($v['concierge_nom'] ?? '') ?></td>
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
