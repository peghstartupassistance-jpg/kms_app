<?php
// formation/prospects_list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('FORMATION_GERER');

global $pdo;

// Formations pour select
$stmtForm = $pdo->query("SELECT id, nom FROM formations ORDER BY nom");
$formations = $stmtForm->fetchAll();

// Traitement ajout prospect
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf($_POST['csrf_token'] ?? '');

    $dateProspect = $_POST['date_prospect'] ?? date('Y-m-d');
    $nom          = trim($_POST['nom_prospect'] ?? '');
    $contact      = trim($_POST['contact'] ?? '');
    $source       = trim($_POST['source'] ?? '');
    $statut       = trim($_POST['statut_actuel'] ?? '');
    $clientId     = isset($_POST['client_id']) ? (int)$_POST['client_id'] : null;

    $errors = [];

    if ($nom === '') {
        $errors[] = "Le nom du prospect est obligatoire.";
    }
    if ($contact === '') {
        $errors[] = "Le contact est recommandé (téléphone, email...).";
    }
    if ($statut === '') {
        $statut = 'Nouveau';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO prospects_formation
                (date_prospect, nom_prospect, contact, source, statut_actuel, client_id, utilisateur_id)
            VALUES
                (:date_prospect, :nom, :contact, :source, :statut, :client_id, :user_id)
        ");
        $stmt->execute([
            'date_prospect' => $dateProspect,
            'nom'           => $nom,
            'contact'       => $contact,
            'source'        => $source,
            'statut'        => $statut,
            'client_id'     => $clientId ?: null,
            'user_id'       => utilisateurConnecte()['id'] ?? null,
        ]);

        $_SESSION['flash_success'] = "Prospect formation enregistré.";
        header('Location: ' . url_for('formation/prospects_list.php'));
        exit;
    } else {
        $_SESSION['flash_error'] = implode(' ', $errors);
        header('Location: ' . url_for('formation/prospects_list.php'));
        exit;
    }
}

// Filtres liste
$today      = date('Y-m-d');
$firstMonth = date('Y-m-01');

$dateDeb = $_GET['date_debut'] ?? $firstMonth;
$dateFin = $_GET['date_fin'] ?? $today;
$statut  = trim($_GET['statut'] ?? '');

$where  = [];
$params = [];

if ($dateDeb !== '') {
    $where[] = "p.date_prospect >= :date_debut";
    $params['date_debut'] = $dateDeb;
}
if ($dateFin !== '') {
    $where[] = "p.date_prospect <= :date_fin";
    $params['date_fin'] = $dateFin;
}
if ($statut !== '') {
    $where[] = "p.statut_actuel = :statut";
    $params['statut'] = $statut;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

$sql = "
    SELECT
        p.*,
        u.nom_complet AS commercial_nom
    FROM prospects_formation p
    LEFT JOIN utilisateurs u ON u.id = p.utilisateur_id
    $whereSql
    ORDER BY p.date_prospect DESC, p.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prospects = $stmt->fetchAll();

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Prospects formation</h1>
        <a href="<?= url_for('formation/inscriptions.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-person-check me-1"></i> Aller aux inscriptions
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

    <!-- Formulaire ajout prospect -->
    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">Enregistrer un prospect formation</h2>
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

                <div class="col-md-2">
                    <label class="form-label small">Date</label>
                    <input type="date" name="date_prospect" class="form-control"
                           value="<?= htmlspecialchars($dateDeb) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Nom du prospect</label>
                    <input type="text" name="nom_prospect" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Contact</label>
                    <input type="text" name="contact" class="form-control"
                           placeholder="Téléphone, email...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Source</label>
                    <input type="text" name="source" class="form-control"
                           placeholder="Facebook, appel, visite...">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Statut actuel</label>
                    <select name="statut_actuel" class="form-select">
                        <?php
                        $statuts = ['Nouveau','En cours','Relancé','Inscrit','Perdu'];
                        foreach ($statuts as $s):
                        ?>
                            <option value="<?= $s ?>"><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Enregistrer le prospect
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filtres liste -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
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
                    <label class="form-label small">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="">Tous</option>
                        <?php foreach (['Nouveau','En cours','Relancé','Inscrit','Perdu'] as $s): ?>
                            <option value="<?= $s ?>" <?= $statut === $s ? 'selected' : '' ?>>
                                <?= $s ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2 mt-3 mt-md-0">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('formation/prospects_list.php') ?>" class="btn btn-outline-secondary">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste prospects -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($prospects)): ?>
                <p class="text-muted mb-0">Aucun prospect trouvé pour la période sélectionnée.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Nom</th>
                            <th>Contact</th>
                            <th>Source</th>
                            <th>Statut</th>
                            <th>Commercial</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($prospects as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['date_prospect']) ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($p['nom_prospect']) ?></td>
                                <td><?= htmlspecialchars($p['contact'] ?? '') ?></td>
                                <td><?= htmlspecialchars($p['source'] ?? '') ?></td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary">
                                        <?= htmlspecialchars($p['statut_actuel']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($p['commercial_nom'] ?? '') ?></td>
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
