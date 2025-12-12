<?php
// formation/inscriptions.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('FORMATION_GERER');

global $pdo;

// --- Récupération des formations (schéma réel : nom, tarif_total) ---
$stmt = $pdo->query("
    SELECT id, nom, description, tarif_total
    FROM formations
    ORDER BY nom
");
$formations = $stmt->fetchAll();
$formationsMap = [];
foreach ($formations as $f) {
    $formationsMap[(int)$f['id']] = $f;
}

// --- Récupération des clients (simple select pour l'instant) ---
$stmt = $pdo->query("
    SELECT id, nom
    FROM clients
    ORDER BY nom
");
$clients = $stmt->fetchAll();

// --- Traitement ajout / enregistrement d'une inscription ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf($_POST['csrf_token'] ?? '');

    $dateInscription = $_POST['date_inscription'] ?? date('Y-m-d');
    $formationId     = isset($_POST['formation_id']) ? (int)$_POST['formation_id'] : 0;
    $apprenantNom    = trim($_POST['apprenant_nom'] ?? '');
    $clientId        = isset($_POST['client_id']) && $_POST['client_id'] !== ''
        ? (int)$_POST['client_id']
        : null;
    $montantPayeRaw  = str_replace(',', '.', $_POST['montant_paye'] ?? '0');
    $soldeDuRaw      = str_replace(',', '.', $_POST['solde_du'] ?? '');

    $montantPaye = (float)$montantPayeRaw;
    $soldeDu     = ($soldeDuRaw === '') ? null : (float)$soldeDuRaw;

    $errors = [];

    if ($dateInscription === '') {
        $errors[] = "La date d'inscription est obligatoire.";
    }

    if ($formationId <= 0 || !isset($formationsMap[$formationId])) {
        $errors[] = "Veuillez sélectionner une formation valide.";
    }

    if ($apprenantNom === '' && $clientId === null) {
        $errors[] = "Veuillez renseigner au moins le nom de l'apprenant ou sélectionner un client existant.";
    }

    if ($montantPaye < 0) {
        $errors[] = "Le montant payé ne peut pas être négatif.";
    }

    // Si la formation existe, on peut calculer un solde par défaut à partir de tarif_total
    if ($soldeDu === null && isset($formationsMap[$formationId])) {
        $tarif = (float)$formationsMap[$formationId]['tarif_total'];
        $soldeDu = max(0, $tarif - $montantPaye);
    }

    if ($soldeDu < 0) {
        $errors[] = "Le solde dû ne peut pas être négatif.";
    }

    if (empty($errors)) {
        // Si client_id renseigné mais pas de nom apprenant, on recopie le nom du client
        if ($clientId !== null && $apprenantNom === '') {
            $stmt = $pdo->prepare("SELECT nom FROM clients WHERE id = :id");
            $stmt->execute(['id' => $clientId]);
            $cli = $stmt->fetch();
            if ($cli) {
                $apprenantNom = $cli['nom'];
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO inscriptions_formation (
                date_inscription,
                apprenant_nom,
                client_id,
                formation_id,
                montant_paye,
                solde_du
            ) VALUES (
                :date_inscription,
                :apprenant_nom,
                :client_id,
                :formation_id,
                :montant_paye,
                :solde_du
            )
        ");
        $stmt->execute([
            'date_inscription' => $dateInscription,
            'apprenant_nom'    => $apprenantNom,
            'client_id'        => $clientId,
            'formation_id'     => $formationId,
            'montant_paye'     => $montantPaye,
            'solde_du'         => $soldeDu,
        ]);

        $_SESSION['flash_success'] = "Inscription à la formation enregistrée avec succès.";
        header('Location: ' . url_for('formation/inscriptions.php'));
        exit;
    } else {
        $_SESSION['flash_error'] = implode(' ', $errors);
        header('Location: ' . url_for('formation/inscriptions.php'));
        exit;
    }
}

// --- Filtres liste ---
$dateDebut       = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin         = $_GET['date_fin'] ?? date('Y-m-d');
$formationFilter = isset($_GET['formation_id']) ? (int)$_GET['formation_id'] : 0;

$where  = [];
$params = [];

if ($dateDebut !== '') {
    $where[] = "i.date_inscription >= :date_debut";
    $params['date_debut'] = $dateDebut;
}
if ($dateFin !== '') {
    $where[] = "i.date_inscription <= :date_fin";
    $params['date_fin'] = $dateFin;
}
if ($formationFilter > 0) {
    $where[] = "i.formation_id = :formation_id";
    $params['formation_id'] = $formationFilter;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// --- Récupération des inscriptions ---
// Schéma réel : formations.nom, formations.tarif_total
$sql = "
    SELECT
        i.*,
        f.nom         AS formation_nom,
        f.tarif_total AS formation_tarif_total,
        c.nom         AS client_nom
    FROM inscriptions_formation i
    JOIN formations f ON f.id = i.formation_id
    LEFT JOIN clients c ON c.id = i.client_id
    $whereSql
    ORDER BY i.date_inscription DESC, i.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inscriptions = $stmt->fetchAll();

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$csrfToken = getCsrfToken();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Inscriptions aux formations</h1>
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

    <!-- Formulaire rapide d'inscription -->
    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">Enregistrer une nouvelle inscription</h2>
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="col-md-3">
                    <label class="form-label small">Date d'inscription</label>
                    <input type="date"
                           name="date_inscription"
                           class="form-control"
                           value="<?= htmlspecialchars($dateFin) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Formation *</label>
                    <select name="formation_id" class="form-select" required>
                        <option value="">Sélectionner une formation...</option>
                        <?php foreach ($formations as $f): ?>
                            <option value="<?= (int)$f['id'] ?>">
                                <?= htmlspecialchars($f['nom']) ?>
                                – <?= number_format((float)$f['tarif_total'], 0, ',', ' ') ?> FCFA
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        La liste reprend toutes les formations enregistrées.
                    </div>
                </div>

                <div class="col-md-5">
                    <label class="form-label small">Apprenant (nom libre)</label>
                    <input type="text" name="apprenant_nom" class="form-control"
                           placeholder="Nom de l'apprenant (si différent ou pas dans la base)">
                    <div class="form-text">
                        Tu peux laisser vide si tu sélectionnes un client ci-dessous.
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label small">Client (BD KMS - facultatif)</label>
                    <select name="client_id" class="form-select">
                        <option value="">Aucun / non existant</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= (int)$c['id'] ?>">
                                <?= htmlspecialchars($c['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        Pour l'instant sélection simple ; on uniformisera plus tard avec la recherche.
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Montant payé (FCFA)</label>
                    <input type="number" step="0.01" min="0"
                           name="montant_paye"
                           class="form-control"
                           value="0">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Solde dû (FCFA)</label>
                    <input type="number" step="0.01" min="0"
                           name="solde_du"
                           class="form-control"
                           placeholder="Auto selon tarif si laissé vide">
                    <div class="form-text">
                        Si tu laisses vide, le solde sera calculé à partir du tarif de la formation.
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Enregistrer l'inscription
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
                           value="<?= htmlspecialchars($dateDebut) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control"
                           value="<?= htmlspecialchars($dateFin) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Formation</label>
                    <select name="formation_id" class="form-select">
                        <option value="0">Toutes</option>
                        <?php foreach ($formations as $f): ?>
                            <option value="<?= (int)$f['id'] ?>"
                                <?= $formationFilter === (int)$f['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2 mt-3 mt-md-0">
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('formation/inscriptions.php') ?>" class="btn btn-outline-secondary w-100">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des inscriptions -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($inscriptions)): ?>
                <p class="text-muted mb-0">
                    Aucune inscription trouvée pour les filtres sélectionnés.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Apprenant</th>
                            <th>Client (BD)</th>
                            <th>Formation</th>
                            <th class="text-end">Montant payé</th>
                            <th class="text-end">Solde dû</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($inscriptions as $i): ?>
                            <tr>
                                <td><?= htmlspecialchars($i['date_inscription']) ?></td>
                                <td class="fw-semibold">
                                    <?= htmlspecialchars($i['apprenant_nom']) ?>
                                </td>
                                <td>
                                    <?php if (!empty($i['client_id'])): ?>
                                        <span class="badge bg-primary-subtle text-primary">
                                            <?= htmlspecialchars($i['client_nom'] ?? ('ID #' . (int)$i['client_id'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">Non rattaché</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-semibold">
                                        <?= htmlspecialchars($i['formation_nom']) ?>
                                    </span><br>
                                    <span class="text-muted small">
                                        Tarif : <?= number_format((float)$i['formation_tarif_total'], 0, ',', ' ') ?> FCFA
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?= number_format((float)$i['montant_paye'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="text-end">
                                    <?= number_format((float)$i['solde_du'], 0, ',', ' ') ?> FCFA
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
