<?php
// satisfaction/list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('SATISFACTION_GERER');

global $pdo;

// --- Services possibles (utilisé partout) ---
$servicesPossibles = ['SHOWROOM','HOTEL','FORMATION','TERRAIN','DIGITAL'];

// --- Traitement ajout d'une satisfaction ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf($_POST['csrf_token'] ?? '');

    $dateSatisfaction = $_POST['date_satisfaction'] ?? date('Y-m-d');
    $serviceUtilise    = $_POST['service_utilise'] ?? '';
    $note              = (int)($_POST['note'] ?? 0);
    $nomClient         = trim($_POST['nom_client'] ?? '');
    $clientId          = isset($_POST['client_id']) && $_POST['client_id'] !== ''
        ? (int)$_POST['client_id']
        : null;
    $commentaire       = trim($_POST['commentaire'] ?? '');

    $errors = [];

    // Validation service
    if (!in_array($serviceUtilise, $servicesPossibles, true)) {
        $errors[] = "Service utilisé invalide.";
    }

    // Validation note
    if ($note < 1 || $note > 5) {
        $errors[] = "La note doit être comprise entre 1 et 5.";
    }

    // Au moins nom ou client_id
    if ($nomClient === '' && $clientId === null) {
        $errors[] = "Veuillez renseigner au moins le nom du client ou sélectionner un client existant.";
    }

    if (empty($errors)) {
        // Si client_id renseigné mais nom_client vide, on recopie depuis la table clients
        if ($clientId !== null && $nomClient === '') {
            $stmt = $pdo->prepare("SELECT nom FROM clients WHERE id = :id");
            $stmt->execute(['id' => $clientId]);
            $cli = $stmt->fetch();
            if ($cli) {
                $nomClient = $cli['nom'];
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO satisfaction_clients
                (date_satisfaction, client_id, nom_client, service_utilise, note, commentaire, utilisateur_id)
            VALUES
                (:date_satisfaction, :client_id, :nom_client, :service_utilise, :note, :commentaire, :utilisateur_id)
        ");

        $stmt->execute([
            'date_satisfaction' => $dateSatisfaction,
            'client_id'         => $clientId,
            'nom_client'        => $nomClient,
            'service_utilise'   => $serviceUtilise,
            'note'              => $note,
            'commentaire'       => $commentaire,
            'utilisateur_id'    => utilisateurConnecte()['id'] ?? null,
        ]);

        $_SESSION['flash_success'] = "Enquête de satisfaction enregistrée avec succès.";
        header('Location: ' . url_for('satisfaction/list.php'));
        exit;
    } else {
        $_SESSION['flash_error'] = implode(' ', $errors);
        header('Location: ' . url_for('satisfaction/list.php'));
        exit;
    }
}

// --- Filtres liste ---
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin   = $_GET['date_fin'] ?? date('Y-m-d');
$service   = $_GET['service'] ?? '';

$where  = [];
$params = [];

if ($dateDebut !== '') {
    $where[] = "s.date_satisfaction >= :date_debut";
    $params['date_debut'] = $dateDebut;
}
if ($dateFin !== '') {
    $where[] = "s.date_satisfaction <= :date_fin";
    $params['date_fin'] = $dateFin;
}
if ($service !== '' && in_array($service, $servicesPossibles, true)) {
    $where[] = "s.service_utilise = :service";
    $params['service'] = $service;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// --- Récupération des enquêtes ---
$sql = "
    SELECT
        s.*,
        u.nom_complet AS utilisateur_nom
    FROM satisfaction_clients s
    LEFT JOIN utilisateurs u ON u.id = s.utilisateur_id
    $whereSql
    ORDER BY s.date_satisfaction DESC, s.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$enquetes = $stmt->fetchAll();

// --- Statistiques globales sur la satisfaction ---
$sqlStats = "
    SELECT
        COUNT(*) AS total_enquetes,
        AVG(s.note) AS moyenne_globale,
        SUM(CASE WHEN s.note >= 4 THEN 1 ELSE 0 END) AS nb_satisfaits,
        SUM(CASE WHEN s.service_utilise = 'SHOWROOM' THEN 1 ELSE 0 END) AS nb_showroom,
        SUM(CASE WHEN s.service_utilise = 'HOTEL' THEN 1 ELSE 0 END) AS nb_hotel,
        SUM(CASE WHEN s.service_utilise = 'FORMATION' THEN 1 ELSE 0 END) AS nb_formation,
        SUM(CASE WHEN s.service_utilise = 'TERRAIN' THEN 1 ELSE 0 END) AS nb_terrain,
        SUM(CASE WHEN s.service_utilise = 'DIGITAL' THEN 1 ELSE 0 END) AS nb_digital
    FROM satisfaction_clients s
    $whereSql
";

$stmtStats = $pdo->prepare($sqlStats);
$stmtStats->execute($params);
$stats = $stmtStats->fetch() ?: [
    'total_enquetes'   => 0,
    'moyenne_globale'  => null,
    'nb_satisfaits'    => 0,
    'nb_showroom'      => 0,
    'nb_hotel'         => 0,
    'nb_formation'     => 0,
    'nb_terrain'       => 0,
    'nb_digital'       => 0,
];

$pourcentageSatisfaits = 0;
if ((int)$stats['total_enquetes'] > 0) {
    $pourcentageSatisfaits = round(
        ((int)$stats['nb_satisfaits'] / (int)$stats['total_enquetes']) * 100
    );
}

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Satisfaction clients</h1>
    </div>

    <!-- Bloc de synthèse KPI Satisfaction -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Nombre d’enquêtes</div>
                    <div class="h4 mb-0">
                        <?= (int)$stats['total_enquetes'] ?>
                    </div>
                    <div class="small text-muted mt-1">
                        Sur la période filtrée
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Note moyenne globale</div>
                    <div class="h4 mb-0">
                        <?php if ($stats['moyenne_globale'] !== null): ?>
                            <?= number_format((float)$stats['moyenne_globale'], 2, ',', ' ') ?>/5
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                    <div class="small text-muted mt-1">
                        Tous services confondus
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">% clients satisfaits (4–5)</div>
                    <div class="h4 mb-0">
                        <?php if ((int)$stats['total_enquetes'] > 0): ?>
                            <?= $pourcentageSatisfaits ?> %
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                    <div class="small text-muted mt-1">
                        Objectif à suivre par la direction
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small mb-1">Répartition par service</div>
                    <div class="small">
                        <div>Showroom : <strong><?= (int)$stats['nb_showroom'] ?></strong></div>
                        <div>Hôtel : <strong><?= (int)$stats['nb_hotel'] ?></strong></div>
                        <div>Formation : <strong><?= (int)$stats['nb_formation'] ?></strong></div>
                        <div>Terrain : <strong><?= (int)$stats['nb_terrain'] ?></strong></div>
                        <div>Digital : <strong><?= (int)$stats['nb_digital'] ?></strong></div>
                    </div>
                </div>
            </div>
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

    <!-- Formulaire de saisie d'une satisfaction -->
    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">Enregistrer une satisfaction client</h2>
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

                <div class="col-md-3">
                    <label class="form-label small">Date</label>
                    <input type="date"
                           name="date_satisfaction"
                           class="form-control"
                           value="<?= htmlspecialchars($dateFin) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Service utilisé</label>
                    <select name="service_utilise" class="form-select" required>
                        <option value="">Sélectionner...</option>
                        <?php foreach ($servicesPossibles as $svc): ?>
                            <option value="<?= $svc ?>"><?= $svc ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Note (1 à 5)</label>
                    <select name="note" class="form-select" required>
                        <option value="">Note...</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Nom du client (libre)</label>
                    <input type="text" name="nom_client" class="form-control"
                           placeholder="Nom du client (si pas dans la base)">
                    <div class="form-text">
                        Tu peux remplir ce champ même si le client n'existe pas encore dans la base.
                    </div>
                </div>

                <!-- Recherche AJAX client -->
                <div class="col-md-6 position-relative">
                    <label class="form-label small">Client (BD KMS - facultatif)</label>
                    <input type="hidden" name="client_id" id="client_id" value="">
                    <input type="text"
                           id="client_search"
                           class="form-control"
                           placeholder="Rechercher un client par nom, téléphone ou email...">
                    <div id="client_search_results"
                         class="list-group position-absolute w-100 shadow-sm"
                         style="z-index: 1050; max-height: 250px; overflow-y: auto; display:none;"></div>
                    <div class="form-text">
                        Tape quelques lettres puis sélectionne un client dans la liste.
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label small">Commentaire</label>
                    <textarea name="commentaire" rows="2" class="form-control"
                              placeholder="Remarques du client, points positifs, axes d'amélioration..."></textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Enregistrer
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
                           value="<?= htmlspecialchars($dateDebut) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control"
                           value="<?= htmlspecialchars($dateFin) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Service</label>
                    <select name="service" class="form-select">
                        <option value="">Tous</option>
                        <?php foreach ($servicesPossibles as $svc): ?>
                            <option value="<?= $svc ?>" <?= $service === $svc ? 'selected' : '' ?>>
                                <?= $svc ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2 mt-3 mt-md-0">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('satisfaction/list.php') ?>" class="btn btn-outline-secondary">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des enquêtes -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($enquetes)): ?>
                <p class="text-muted mb-0">Aucune enquête de satisfaction trouvée pour les filtres sélectionnés.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Client (texte)</th>
                            <th>Service</th>
                            <th class="text-center">Note</th>
                            <th>Commentaire</th>
                            <th>Saisi par</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($enquetes as $e): ?>
                            <tr>
                                <td><?= htmlspecialchars($e['date_satisfaction']) ?></td>
                                <td>
                                    <?= htmlspecialchars($e['nom_client']) ?>
                                    <?php if (!empty($e['client_id'])): ?>
                                        <span class="badge bg-primary-subtle text-primary ms-1">
                                            ID #<?= (int)$e['client_id'] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($e['service_utilise']) ?></td>
                                <td class="text-center">
                                    <span class="fw-semibold"><?= (int)$e['note'] ?>/5</span>
                                </td>
                                <td><?= nl2br(htmlspecialchars($e['commentaire'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($e['utilisateur_nom'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Script de recherche AJAX client -->
<script>
(function() {
    const input      = document.getElementById('client_search');
    const hiddenId   = document.getElementById('client_id');
    const resultsBox = document.getElementById('client_search_results');

    if (!input || !hiddenId || !resultsBox) {
        return;
    }

    let timer = null;

    function clearResults() {
        resultsBox.innerHTML = '';
        resultsBox.style.display = 'none';
    }

    function selectClient(button) {
        hiddenId.value = button.dataset.id;
        input.value    = button.dataset.label;
        clearResults();
    }

    input.addEventListener('input', function() {
        const q = this.value.trim();
        hiddenId.value = ''; // reset de l'ID quand on modifie le texte

        if (timer) {
            clearTimeout(timer);
        }

        if (q.length < 2) {
            clearResults();
            return;
        }

        timer = setTimeout(function() {
            fetch('<?= url_for('ajax/clients_search.php') ?>?q=' + encodeURIComponent(q), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    resultsBox.innerHTML = '';

                    if (!Array.isArray(data) || data.length === 0) {
                        clearResults();
                        return;
                    }

                    data.forEach(function(row) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action py-1';
                        btn.textContent = row.label;
                        btn.dataset.id = row.id;
                        btn.dataset.label = row.label;
                        btn.addEventListener('click', function() {
                            selectClient(btn);
                        });
                        resultsBox.appendChild(btn);
                    });

                    resultsBox.style.display = 'block';
                })
                .catch(function() {
                    clearResults();
                });
        }, 300); // debounce
    });

    document.addEventListener('click', function(e) {
        if (!resultsBox.contains(e.target) && e.target !== input) {
            clearResults();
        }
    });
})();
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
