<?php
// terrain/prospections_list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CLIENTS_CREER'); // les commerciaux terrain ont déjà cette permission

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';

global $pdo;

$utilisateur = utilisateurConnecte();
$userId      = (int)$utilisateur['id'];

$today      = date('Y-m-d');
$dateDebut  = $_GET['date_debut'] ?? $today;
$dateFin    = $_GET['date_fin'] ?? $today;

$where  = [];
$params = [];

$where[] = "pt.commercial_id = :cid";
$params['cid'] = $userId;

if ($dateDebut !== '') {
    $where[] = "pt.date_prospection >= :date_debut";
    $params['date_debut'] = $dateDebut;
}
if ($dateFin !== '') {
    $where[] = "pt.date_prospection <= :date_fin";
    $params['date_fin'] = $dateFin;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT pt.*
    FROM prospections_terrain pt
    $whereSql
    ORDER BY pt.date_prospection DESC, pt.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prospections = $stmt->fetchAll();

// Formulaire POST
$erreurs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    verifierCsrf($csrf);

    $date_prospection = $_POST['date_prospection'] ?? $today;
    $prospect_nom     = trim($_POST['prospect_nom'] ?? '');
    $secteur          = trim($_POST['secteur'] ?? '');
    $besoin_identifie = trim($_POST['besoin_identifie'] ?? '');
    $action_menee     = trim($_POST['action_menee'] ?? '');
    $resultat         = trim($_POST['resultat'] ?? '');
    $prochaine_etape  = trim($_POST['prochaine_etape'] ?? '');

    if ($prospect_nom === '') {
        $erreurs[] = "Le nom du prospect est obligatoire.";
    }
    if ($secteur === '') {
        $erreurs[] = "Le secteur (zone, quartier...) est obligatoire.";
    }
    if ($besoin_identifie === '') {
        $erreurs[] = "Le besoin identifié est obligatoire.";
    }
    if ($action_menee === '') {
        $erreurs[] = "L’action menée est obligatoire.";
    }
    if ($resultat === '') {
        $erreurs[] = "Le résultat de la visite est obligatoire.";
    }

    if (empty($erreurs)) {
        $stmtIns = $pdo->prepare("
            INSERT INTO prospections_terrain (
                date_prospection, prospect_nom, secteur,
                besoin_identifie, action_menee, resultat,
                prochaine_etape, client_id, commercial_id
            ) VALUES (
                :date_prospection, :prospect_nom, :secteur,
                :besoin_identifie, :action_menee, :resultat,
                :prochaine_etape, NULL, :commercial_id
            )
        ");
        $stmtIns->execute([
            'date_prospection' => $date_prospection,
            'prospect_nom'     => $prospect_nom,
            'secteur'          => $secteur,
            'besoin_identifie' => $besoin_identifie,
            'action_menee'     => $action_menee,
            'resultat'         => $resultat,
            'prochaine_etape'  => $prochaine_etape ?: null,
            'commercial_id'    => $userId
        ]);

        $_SESSION['flash_success'] = "Prospection terrain enregistrée.";
        header('Location: /terrain/prospections_list.php?date_debut=' . urlencode($dateDebut) . '&date_fin=' . urlencode($dateFin));
        exit;
    }
}

$csrfToken = getCsrfToken();
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Prospections terrain</h1>
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

    <!-- Saisie rapide -->
    <div class="card mb-3">
        <div class="card-header">
            <strong>Nouvelle prospection terrain</strong>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="col-md-3">
                    <label class="form-label small">Date prospection</label>
                    <input type="date" name="date_prospection" class="form-control"
                           value="<?= htmlspecialchars($today) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Nom du prospect *</label>
                    <input type="text" name="prospect_nom" class="form-control" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Secteur / zone *</label>
                    <input type="text" name="secteur" class="form-control" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label small">Besoin identifié *</label>
                    <textarea name="besoin_identifie" class="form-control" rows="2" required></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Action menée *</label>
                    <textarea name="action_menee" class="form-control" rows="2" required></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label small">Résultat *</label>
                    <textarea name="resultat" class="form-control" rows="2" required></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Prochaine étape</label>
                    <textarea name="prochaine_etape" class="form-control" rows="2"></textarea>
                </div>

                <div class="col-md-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Enregistrer la prospection
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
                <div class="col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary mt-4">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="/terrain/prospections_list.php" class="btn btn-outline-secondary mt-4">
                        Aujourd’hui
                    </a>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($prospections)): ?>
                <p class="text-muted mb-0">Aucune prospection pour la période sélectionnée.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Prospect</th>
                            <th>Secteur</th>
                            <th>Besoin identifié</th>
                            <th>Action menée</th>
                            <th>Résultat</th>
                            <th>Prochaine étape</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($prospections as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['date_prospection']) ?></td>
                                <td><?= htmlspecialchars($p['prospect_nom']) ?></td>
                                <td><?= htmlspecialchars($p['secteur']) ?></td>
                                <td><?= nl2br(htmlspecialchars($p['besoin_identifie'])) ?></td>
                                <td><?= nl2br(htmlspecialchars($p['action_menee'])) ?></td>
                                <td><?= nl2br(htmlspecialchars($p['resultat'])) ?></td>
                                <td><?= nl2br(htmlspecialchars($p['prochaine_etape'] ?? '')) ?></td>
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
