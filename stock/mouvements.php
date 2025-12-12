<?php
// stock/mouvements.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('PRODUITS_MODIFIER');

require_once __DIR__ . '/../lib/stock.php';

global $pdo;

// --- Constantes / listes ---
$typesMouvement  = ['ENTREE', 'SORTIE', 'CORRECTION'];
$modulesSource   = ['VENTE', 'ACHAT', 'AUTRE'];

// --- Récupération des produits pour les listes déroulantes ---
$stmt = $pdo->query("
    SELECT id, code_produit, designation, stock_actuel
    FROM produits
    WHERE actif = 1
    ORDER BY designation
");
$produits = $stmt->fetchAll();
$produitsMap = [];
foreach ($produits as $p) {
    $produitsMap[(int)$p['id']] = $p;
}

// --- Traitement ajout d'un mouvement manuel ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf($_POST['csrf_token'] ?? '');

    $dateMouvement  = $_POST['date_mouvement'] ?? date('Y-m-d');
    $typeMouvement  = strtoupper(trim($_POST['type_mouvement'] ?? ''));
    $produitId      = isset($_POST['produit_id']) ? (int)$_POST['produit_id'] : 0;
    $quantiteRaw    = str_replace(',', '.', $_POST['quantite'] ?? '0');
    $quantite       = (int)round((float)$quantiteRaw);
    $sourceModule   = strtoupper(trim($_POST['source_module'] ?? 'AUTRE'));
    $sourceIdRaw    = trim($_POST['source_id'] ?? '');
    $sourceId       = ($sourceIdRaw === '') ? null : (int)$sourceIdRaw;
    $commentaire    = trim($_POST['commentaire'] ?? '');

    $errors = [];

    if ($dateMouvement === '') {
        $errors[] = "La date du mouvement est obligatoire.";
    }

    if (!in_array($typeMouvement, $typesMouvement, true)) {
        $errors[] = "Type de mouvement invalide.";
    }

    if ($produitId <= 0 || !isset($produitsMap[$produitId])) {
        $errors[] = "Veuillez sélectionner un produit valide.";
    }

    if ($quantite <= 0) {
        $errors[] = "La quantité doit être strictement positive.";
    }

    if (!in_array($sourceModule, $modulesSource, true)) {
        $sourceModule = 'AUTRE';
    }

    // Règles de traçabilité :
    // - ENTREE / SORTIE :
    //      * si module = VENTE ou ACHAT => source_id obligatoire
    //      * si module = AUTRE => commentaire obligatoire
    // - CORRECTION : commentaire obligatoire (explication de la correction)
    if (in_array($typeMouvement, ['ENTREE', 'SORTIE'], true)) {
        if (in_array($sourceModule, ['VENTE', 'ACHAT'], true) && ($sourceId === null || $sourceId <= 0)) {
            $errors[] = "Merci d'indiquer la référence liée (ID " .
                strtolower($sourceModule) . ") pour ce mouvement.";
        }
        if ($sourceModule === 'AUTRE' && $commentaire === '') {
            $errors[] = "Merci de préciser le motif dans le commentaire pour un mouvement AUTRE.";
        }
    } elseif ($typeMouvement === 'CORRECTION' && $commentaire === '') {
        $errors[] = "Pour un mouvement de correction, un commentaire explicatif est obligatoire.";
    }

    if (empty($errors)) {
        try {
            stock_enregistrer_mouvement(
                $pdo,
                $typeMouvement,
                $produitId,
                $quantite,
                $sourceModule,
                $sourceId,
                $commentaire,
                $dateMouvement
            );

            $_SESSION['flash_success'] = "Mouvement de stock enregistré avec succès.";
        } catch (Throwable $e) {
            // Ici tu peux loguer $e->getMessage() dans un fichier si besoin
            $_SESSION['flash_error'] = "Erreur lors de l'enregistrement du mouvement de stock.";
        }

        header('Location: ' . url_for('stock/mouvements.php'));
        exit;
    } else {
        $_SESSION['flash_error'] = implode(' ', $errors);
        header('Location: ' . url_for('stock/mouvements.php'));
        exit;
    }
}

// --- Filtres de la liste ---
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin   = $_GET['date_fin'] ?? date('Y-m-d');
$typeFiltre   = strtoupper(trim($_GET['type_mouvement'] ?? ''));
$produitFiltre= isset($_GET['produit_id']) ? (int)$_GET['produit_id'] : 0;

$where  = [];
$params = [];

if ($dateDebut !== '') {
    $where[] = "m.date_mouvement >= :date_debut";
    $params['date_debut'] = $dateDebut;
}
if ($dateFin !== '') {
    $where[] = "m.date_mouvement <= :date_fin";
    $params['date_fin'] = $dateFin;
}
if ($typeFiltre !== '' && in_array($typeFiltre, $typesMouvement, true)) {
    $where[] = "m.type_mouvement = :type_mouvement";
    $params['type_mouvement'] = $typeFiltre;
}
if ($produitFiltre > 0) {
    $where[] = "m.produit_id = :produit_id";
    $params['produit_id'] = $produitFiltre;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// --- Récupération des mouvements ---
$sql = "
    SELECT
        m.*,
        p.code_produit,
        p.designation,
        p.stock_actuel
    FROM stocks_mouvements m
    JOIN produits p ON p.id = m.produit_id
    $whereSql
    ORDER BY m.date_mouvement DESC, m.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mouvements = $stmt->fetchAll();

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$csrfToken = getCsrfToken();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Mouvements de stock</h1>
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

    <!-- Formulaire de saisie d'un mouvement -->
    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">Enregistrer un mouvement manuel</h2>
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="col-md-3">
                    <label class="form-label small">Date du mouvement</label>
                    <input type="date"
                           name="date_mouvement"
                           class="form-control"
                           value="<?= htmlspecialchars($dateFin) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Type de mouvement</label>
                    <select name="type_mouvement" class="form-select" required>
                        <option value="">Sélectionner...</option>
                        <?php foreach ($typesMouvement as $t): ?>
                            <option value="<?= $t ?>"><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label small">Produit</label>
                    <select name="produit_id" class="form-select" required>
                        <option value="">Sélectionner un produit...</option>
                        <?php foreach ($produits as $p): ?>
                            <option value="<?= (int)$p['id'] ?>">
                                <?= htmlspecialchars($p['code_produit']) ?> – <?= htmlspecialchars($p['designation']) ?>
                                (Stock : <?= (int)$p['stock_actuel'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Quantité</label>
                    <input type="number" name="quantite" class="form-control" min="1" step="1" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Module / motif principal</label>
                    <select name="source_module" class="form-select" required>
                        <?php foreach ($modulesSource as $m): ?>
                            <option value="<?= $m ?>"><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        VENTE = liée à une vente, ACHAT = liée à un achat, AUTRE = usage interne, casse, etc.
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Référence liée (ID)</label>
                    <input type="number" name="source_id" class="form-control" min="0"
                           placeholder="ID vente / achat / autre">
                    <div class="form-text">
                        Obligatoire si module = VENTE ou ACHAT. Peut rester vide pour AUTRE.
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Commentaire / motif détaillé</label>
                    <textarea name="commentaire" rows="2" class="form-control"
                              placeholder="Motif de la sortie/entrée ou explication de la correction..."></textarea>
                    <div class="form-text">
                        Obligatoire pour CORRECTION et pour les mouvements AUTRE.
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Enregistrer le mouvement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filtres -->
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
                <div class="col-md-3">
                    <label class="form-label small">Type</label>
                    <select name="type_mouvement" class="form-select">
                        <option value="">Tous</option>
                        <?php foreach ($typesMouvement as $t): ?>
                            <option value="<?= $t ?>" <?= $typeFiltre === $t ? 'selected' : '' ?>>
                                <?= $t ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Produit</label>
                    <select name="produit_id" class="form-select">
                        <option value="0">Tous</option>
                        <?php foreach ($produits as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= $produitFiltre === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['code_produit']) ?> – <?= htmlspecialchars($p['designation']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mt-2">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('stock/mouvements.php') ?>" class="btn btn-outline-secondary ms-2">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des mouvements -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($mouvements)): ?>
                <p class="text-muted mb-0">
                    Aucun mouvement de stock trouvé pour les filtres sélectionnés.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Produit</th>
                            <th class="text-center">Type</th>
                            <th class="text-end">Quantité</th>
                            <th>Module / réf.</th>
                            <th>Commentaire</th>
                            <th class="text-end">Stock après (indicatif)</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($mouvements as $m): ?>
                            <?php
                            $sens = $m['type_mouvement'] === 'SORTIE' ? -1 : 1;
                            $q = (int)$m['quantite'];
                            $affichageQte = ($sens < 0 ? '-' : '+') . $q;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($m['date_mouvement']) ?></td>
                                <td>
                                    <span class="fw-semibold">
                                        <?= htmlspecialchars($m['code_produit']) ?>
                                    </span><br>
                                    <span class="text-muted small">
                                        <?= htmlspecialchars($m['designation']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge
                                        <?= $m['type_mouvement'] === 'ENTREE' ? 'bg-success-subtle text-success' : '' ?>
                                        <?= $m['type_mouvement'] === 'SORTIE' ? 'bg-danger-subtle text-danger' : '' ?>
                                        <?= $m['type_mouvement'] === 'CORRECTION' ? 'bg-warning-subtle text-warning' : '' ?>
                                    ">
                                        <?= htmlspecialchars($m['type_mouvement']) ?>
                                    </span>
                                </td>
                                <td class="text-end"><?= htmlspecialchars($affichageQte) ?></td>
                                <td>
                                    <span class="fw-semibold"><?= htmlspecialchars($m['source_module'] ?? 'N/A') ?></span>
                                    <?php if (!empty($m['source_id'])): ?>
                                        <br><span class="text-muted small">Réf. ID #<?= (int)$m['source_id'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= nl2br(htmlspecialchars($m['commentaire'] ?? '')) ?></td>
                                <td class="text-end">
                                    <?= (int)$m['stock_actuel'] ?>
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
