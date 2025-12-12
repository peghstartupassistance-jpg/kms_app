<?php
// terrain/rendezvous_list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CLIENTS_CREER');

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';

global $pdo;

$utilisateur = utilisateurConnecte();
$userId      = (int)$utilisateur['id'];

$today      = date('Y-m-d');
$dateDebut  = $_GET['date_debut'] ?? $today;
$dateFin    = $_GET['date_fin'] ?? $today;
$statutFiltre = $_GET['statut'] ?? '';

$where  = [];
$params = [];

$where[] = "rt.commercial_id = :cid";
$params['cid'] = $userId;

if ($dateDebut !== '') {
    $where[] = "rt.date_rdv >= :date_debut";
    $params['date_debut'] = $dateDebut;
}
if ($dateFin !== '') {
    $where[] = "rt.date_rdv <= :date_fin";
    $params['date_fin'] = $dateFin;
}
if ($statutFiltre !== '' && in_array($statutFiltre, ['PLANIFIE','CONFIRME','ANNULE','HONORE'], true)) {
    $where[] = "rt.statut = :statut";
    $params['statut'] = $statutFiltre;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT rt.*
    FROM rendezvous_terrain rt
    $whereSql
    ORDER BY rt.date_rdv ASC, rt.heure_rdv ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rendezvous = $stmt->fetchAll();

// POST : création rapide de RDV
$erreurs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    verifierCsrf($csrf);

    $date_rdv  = $_POST['date_rdv'] ?? $today;
    $heure_rdv = $_POST['heure_rdv'] ?? '09:00';
    $client_prospect_nom = trim($_POST['client_prospect_nom'] ?? '');
    $lieu     = trim($_POST['lieu'] ?? '');
    $objectif = trim($_POST['objectif'] ?? '');
    $statut   = $_POST['statut'] ?? 'PLANIFIE';

    if ($client_prospect_nom === '') {
        $erreurs[] = "Le nom du client / prospect est obligatoire.";
    }
    if ($lieu === '') {
        $erreurs[] = "Le lieu du rendez-vous est obligatoire.";
    }
    if ($objectif === '') {
        $erreurs[] = "L’objectif du rendez-vous est obligatoire.";
    }
    if (!in_array($statut, ['PLANIFIE','CONFIRME','ANNULE','HONORE'], true)) {
        $erreurs[] = "Statut de rendez-vous invalide.";
    }

    if (empty($erreurs)) {
        $stmtIns = $pdo->prepare("
            INSERT INTO rendezvous_terrain (
                date_rdv, heure_rdv, client_prospect_nom,
                lieu, objectif, statut,
                client_id, commercial_id
            ) VALUES (
                :date_rdv, :heure_rdv, :client_prospect_nom,
                :lieu, :objectif, :statut,
                NULL, :commercial_id
            )
        ");
        $stmtIns->execute([
            'date_rdv'           => $date_rdv,
            'heure_rdv'          => $heure_rdv,
            'client_prospect_nom'=> $client_prospect_nom,
            'lieu'               => $lieu,
            'objectif'           => $objectif,
            'statut'             => $statut,
            'commercial_id'      => $userId
        ]);

        $_SESSION['flash_success'] = "Rendez-vous terrain enregistré.";
        header('Location: /terrain/rendezvous_list.php?date_debut=' . urlencode($dateDebut) . '&date_fin=' . urlencode($dateFin) . '&statut=' . urlencode($statutFiltre));
        exit;
    }
}

$csrfToken = getCsrfToken();
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Rendez-vous terrain</h1>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success py-2">
            <i class="bi bi-check-circle me-1"></i>
            <?= htmlspecialchars($flashSuccess) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger">
            <h2 class="h6 mb-2"><i class="bi bi-exclamation-triangle me-1"></i> Erreurs</h2>
            <ul class="mb-0">
                <?php foreach ($erreurs as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Saisie RDV -->
    <div class="card mb-3">
        <div class="card-header">
            <strong>Nouveau rendez-vous terrain</strong>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="col-md-3">
                    <label class="form-label small">Date *</label>
                    <input type="date" name="date_rdv" class="form-control"
                           value="<?= htmlspecialchars($today) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Heure *</label>
                    <input type="time" name="heure_rdv" class="form-control"
                           value="09:00" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Nom client / prospect *</label>
                    <input type="text" name="client_prospect_nom" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Lieu *</label>
                    <input type="text" name="lieu" class="form-control" required>
                </div>

                <div class="col-md-8">
                    <label class="form-label small">Objectif du rendez-vous *</label>
                    <textarea name="objectif" class="form-control" rows="2" required></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Statut</label>
                    <select name="statut" class="form-select">
                        <?php foreach (['PLANIFIE','CONFIRME','ANNULE','HONORE'] as $s): ?>
                            <option value="<?= $s ?>"><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Enregistrer le rendez-vous
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filtres + liste -->
    <div class="card">
        <div class="card-header">
            <form class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control"
                           value="<?= htmlspecialchars($dateDebut) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control"
                           value="<?= htmlspecialchars($dateFin) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="">Tous</option>
                        <?php foreach (['PLANIFIE','CONFIRME','ANNULE','HONORE'] as $s): ?>
                            <option value="<?= $s ?>" <?= $statutFiltre === $s ? 'selected' : '' ?>>
                                <?= $s ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary mt-4">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="/terrain/rendezvous_list.php" class="btn btn-outline-secondary mt-4">
                        Aujourd’hui
                    </a>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($rendezvous)): ?>
                <p class="text-muted mb-0">Aucun rendez-vous pour la période sélectionnée.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Heure</th>
                            <th>Client / prospect</th>
                            <th>Lieu</th>
                            <th>Objectif</th>
                            <th>Statut</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rendezvous as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['date_rdv']) ?></td>
                                <td><?= htmlspecialchars(substr($r['heure_rdv'], 0, 5)) ?></td>
                                <td><?= htmlspecialchars($r['client_prospect_nom']) ?></td>
                                <td><?= htmlspecialchars($r['lieu']) ?></td>
                                <td><?= nl2br(htmlspecialchars($r['objectif'])) ?></td>
                                <td>
                                    <?php
                                    $badgeClass = 'bg-secondary-subtle text-secondary';
                                    if ($r['statut'] === 'PLANIFIE') $badgeClass = 'bg-warning-subtle text-warning';
                                    elseif ($r['statut'] === 'CONFIRME') $badgeClass = 'bg-info-subtle text-info';
                                    elseif ($r['statut'] === 'HONORE') $badgeClass = 'bg-success-subtle text-success';
                                    elseif ($r['statut'] === 'ANNULE') $badgeClass = 'bg-danger-subtle text-danger';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($r['statut']) ?>
                                    </span>
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
