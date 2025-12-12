<?php
// showroom/visiteurs_list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CLIENTS_CREER'); // showrooms = gestion prospects / clients

global $pdo;

$utilisateur = utilisateurConnecte();
$userId      = (int)$utilisateur['id'];

// URL du script courant (inclut le répertoire du projet, ex : /kms_app/showroom/visiteurs_list.php)
$scriptPath = $_SERVER['SCRIPT_NAME'];

// Filtres date
$today      = date('Y-m-d');
$dateDebut  = $_GET['date_debut'] ?? $today;
$dateFin    = $_GET['date_fin'] ?? $today;

$erreurs = [];

// Traitement formulaire ajout rapide (AVANT tout output HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    verifierCsrf($csrf);

    $date_visite     = $_POST['date_visite'] ?? $today;
    $client_nom      = trim($_POST['client_nom'] ?? '');
    $contact         = trim($_POST['contact'] ?? '');
    $produit_interet = trim($_POST['produit_interet'] ?? '');
    $orientation     = trim($_POST['orientation'] ?? '');

    if ($client_nom === '') {
        $erreurs[] = "Le nom du visiteur est obligatoire.";
    }
    if ($contact === '') {
        $erreurs[] = "Un moyen de contact (téléphone) est obligatoire.";
    }

    if (empty($erreurs)) {
        $stmtIns = $pdo->prepare("
            INSERT INTO visiteurs_showroom (
                date_visite, client_nom, contact, produit_interet, orientation,
                client_id, utilisateur_id
            ) VALUES (
                :date_visite, :client_nom, :contact, :produit_interet, :orientation,
                NULL, :utilisateur_id
            )
        ");
        $stmtIns->execute([
            'date_visite'    => $date_visite,
            'client_nom'     => $client_nom,
            'contact'        => $contact,
            'produit_interet'=> $produit_interet ?: null,
            'orientation'    => $orientation ?: null,
            'utilisateur_id' => $userId
        ]);

        $_SESSION['flash_success'] = "Le visiteur showroom a été enregistré.";

        // Redirection vers la même page en conservant les filtres
        $redirectUrl = $scriptPath
            . '?date_debut=' . urlencode($dateDebut)
            . '&date_fin=' . urlencode($dateFin);

        header('Location: ' . $redirectUrl);
        exit;
    }
}

// Construction du WHERE pour le listing
$where  = [];
$params = [];

if ($dateDebut !== '') {
    $where[] = "vs.date_visite >= :date_debut";
    $params['date_debut'] = $dateDebut;
}
if ($dateFin !== '') {
    $where[] = "vs.date_visite <= :date_fin";
    $params['date_fin'] = $dateFin;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// Liste visiteurs
$sql = "
    SELECT 
        vs.*,
        u.nom_complet AS utilisateur_nom,
        c.nom AS client_nom_lie
    FROM visiteurs_showroom vs
    JOIN utilisateurs u ON u.id = vs.utilisateur_id
    LEFT JOIN clients c ON c.id = vs.client_id
    $whereSql
    ORDER BY vs.date_visite DESC, vs.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$visiteurs = $stmt->fetchAll();

$csrfToken    = getCsrfToken();
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

// INCLUDES APRÈS la logique PHP / redirections
include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Visiteurs showroom</h1>
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

    <!-- Formulaire rapide d'enregistrement -->
    <div class="card mb-3">
        <div class="card-header">
            <strong>Saisie rapide d’un visiteur</strong>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="col-md-3">
                    <label class="form-label small">Date de visite</label>
                    <input type="date" name="date_visite" class="form-control"
                           value="<?= htmlspecialchars($today) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Nom du visiteur *</label>
                    <input type="text" name="client_nom" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Contact (téléphone) *</label>
                    <input type="text" name="contact" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Orientation</label>
                    <select name="orientation" class="form-select">
                        <option value="">—</option>
                        <option value="Devis">Devis</option>
                        <option value="Hôtel">Hôtel</option>
                        <option value="Formation">Formation</option>
                        <option value="Autre">Autre</option>
                    </select>
                </div>

                <div class="col-md-9">
                    <label class="form-label small">Produit / besoin exprimé</label>
                    <input type="text" name="produit_interet" class="form-control">
                </div>

                <div class="col-md-3 d-flex align-items-end justify-content-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle me-1"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filtres & listing -->
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
                    <a href="<?= htmlspecialchars($scriptPath) ?>" class="btn btn-outline-secondary mt-4">
                        Aujourd’hui
                    </a>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if (empty($visiteurs)): ?>
                <p class="text-muted mb-0">Aucun visiteur pour la période sélectionnée.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Nom</th>
                            <th>Contact</th>
                            <th>Produit / besoin</th>
                            <th>Orientation</th>
                            <th>Converti</th>
                            <th>Enregistré par</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($visiteurs as $v): ?>
                            <tr>
                                <td><?= htmlspecialchars($v['date_visite']) ?></td>
                                <td><?= htmlspecialchars($v['client_nom']) ?></td>
                                <td><?= htmlspecialchars($v['contact']) ?></td>
                                <td><?= htmlspecialchars($v['produit_interet'] ?? '') ?></td>
                                <td><?= htmlspecialchars($v['orientation'] ?? '') ?></td>
                                <td>
                                    <?php if ($v['converti_en_devis']): ?>
                                        <span class="badge bg-success">✓ Devis</span>
                                    <?php elseif ($v['converti_en_vente']): ?>
                                        <span class="badge bg-primary">✓ Vente</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($v['utilisateur_nom']) ?></td>
                                <td>
                                    <?php if (!$v['converti_en_devis'] && !$v['converti_en_vente'] && peut('DEVIS_CREER')): ?>
                                        <a href="<?= url_for('showroom/visiteur_convertir_devis.php?visiteur_id=' . $v['id']) ?>" 
                                           class="btn btn-sm btn-outline-success" 
                                           title="Convertir en devis">
                                            <i class="bi bi-arrow-right-circle"></i> Devis
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
