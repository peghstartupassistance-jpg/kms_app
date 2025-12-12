<?php
// ruptures/list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('PRODUITS_LIRE');

global $pdo;

$peutCreer = in_array('PRODUITS_MODIFIER', $_SESSION['permissions'] ?? [], true);

// Traitement POST : signaler une rupture
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $peutCreer) {
    try {
        verifierCsrf($_POST['csrf_token'] ?? '');

        $dateRapport   = trim($_POST['date_rapport'] ?? date('Y-m-d'));
        $produitId     = (int)($_POST['produit_id'] ?? 0);
        $seuilAlerte   = (int)($_POST['seuil_alerte'] ?? 0);
        $stockActuel   = (int)($_POST['stock_actuel'] ?? 0);
        $impact        = trim($_POST['impact_commercial'] ?? '');
        $action        = trim($_POST['action_proposee'] ?? '');

        $utilisateur   = utilisateurConnecte();
        $magasinierId  = $utilisateur['id'] ?? null;

        $erreurs = [];
        if ($dateRapport === '') {
            $erreurs[] = "La date du rapport est obligatoire.";
        }
        if ($produitId <= 0) {
            $erreurs[] = "Le produit est obligatoire.";
        }

        if (!empty($erreurs)) {
            $_SESSION['flash_error'] = implode(' ', $erreurs);
            header('Location: ' . url_for('ruptures/list.php'));
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO ruptures_stock
            (date_rapport, produit_id, seuil_alerte, stock_actuel, impact_commercial, action_proposee, magasinier_id)
            VALUES
            (:date_rapport, :produit_id, :seuil_alerte, :stock_actuel, :impact_commercial, :action_proposee, :magasinier_id)
        ");
        $stmt->execute([
            'date_rapport'      => $dateRapport,
            'produit_id'        => $produitId,
            'seuil_alerte'      => $seuilAlerte,
            'stock_actuel'      => $stockActuel,
            'impact_commercial' => $impact !== '' ? $impact : null,
            'action_proposee'   => $action !== '' ? $action : null,
            'magasinier_id'     => $magasinierId,
        ]);

        $_SESSION['flash_success'] = "Rupture de stock signalée avec succès.";
        header('Location: ' . url_for('ruptures/list.php'));
        exit;

    } catch (Throwable $e) {
        $_SESSION['flash_error'] = "Erreur lors du signalement de la rupture de stock.";
        header('Location: ' . url_for('ruptures/list.php'));
        exit;
    }
}

// Filtres liste
$today     = date('Y-m-d');
$dateDebut = $_GET['date_debut'] ?? $today;
$dateFin   = $_GET['date_fin'] ?? $today;
$produitId = isset($_GET['produit_id']) ? (int)$_GET['produit_id'] : 0;

// Produits pour filtres / formulaire
$stmt = $pdo->query("SELECT id, code_produit, designation, stock_actuel, seuil_alerte FROM produits ORDER BY code_produit");
$produits = $stmt->fetchAll();

$where  = [];
$params = [];

if ($dateDebut !== '') {
    $where[] = "r.date_rapport >= :date_debut";
    $params['date_debut'] = $dateDebut;
}
if ($dateFin !== '') {
    $where[] = "r.date_rapport <= :date_fin";
    $params['date_fin'] = $dateFin;
}
if ($produitId > 0) {
    $where[] = "r.produit_id = :produit_id";
    $params['produit_id'] = $produitId;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

$sql = "
    SELECT
        r.*,
        p.code_produit,
        p.designation AS produit_nom,
        u.nom_complet AS magasinier_nom
    FROM ruptures_stock r
    JOIN produits p ON p.id = r.produit_id
    LEFT JOIN utilisateurs u ON u.id = r.magasinier_id
    $whereSql
    ORDER BY r.date_rapport DESC, r.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ruptures = $stmt->fetchAll();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Ruptures de stock déclarées</h1>
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
                    <label class="form-label small">Produit</label>
                    <select name="produit_id" class="form-select">
                        <option value="0">Tous</option>
                        <?php foreach ($produits as $pr): ?>
                            <option value="<?= (int)$pr['id'] ?>"
                                <?= $produitId === (int)$pr['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pr['code_produit']) ?> – <?= htmlspecialchars($pr['designation']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary mt-4">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('ruptures/list.php') ?>" class="btn btn-outline-secondary mt-4">
                        Réinit.
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Formulaire de déclaration -->
    <?php if ($peutCreer): ?>
        <div class="card mb-3">
            <div class="card-header">
                <strong>Signaler une nouvelle rupture / alerte stock</strong>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small">Date rapport</label>
                            <input type="date" name="date_rapport" class="form-control"
                                   value="<?= htmlspecialchars($today) ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small">Produit</label>
                            <select name="produit_id" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($produits as $pr): ?>
                                    <option value="<?= (int)$pr['id'] ?>">
                                        <?= htmlspecialchars($pr['code_produit']) ?> – <?= htmlspecialchars($pr['designation']) ?>
                                        (Stock: <?= (int)$pr['stock_actuel'] ?> / Seuil: <?= (int)$pr['seuil_alerte'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Seuil alerte</label>
                            <input type="number" name="seuil_alerte" class="form-control"
                                   placeholder="Ex : 5">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Stock actuel</label>
                            <input type="number" name="stock_actuel" class="form-control"
                                   placeholder="Ex : 0">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small">Impact commercial</label>
                            <textarea name="impact_commercial" class="form-control" rows="2"
                                      placeholder="Devis perdus, ventes reportées, insatisfaction client, etc."></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small">Action proposée</label>
                            <textarea name="action_proposee" class="form-control" rows="2"
                                      placeholder="Réapprovisionnement, commande fournisseur urgente, substitution produit, etc."></textarea>
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-exclamation-triangle me-1"></i> Enregistrer la rupture
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Liste des ruptures -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($ruptures)): ?>
                <p class="text-muted mb-0">Aucune rupture de stock déclarée pour les filtres sélectionnés.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Date rapport</th>
                            <th>Produit</th>
                            <th>Seuil</th>
                            <th>Stock actuel</th>
                            <th>Magasinier</th>
                            <th>Impact commercial</th>
                            <th>Action proposée</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ruptures as $r): ?>
                            <?php
                            $alerte = (int)$r['stock_actuel'] <= (int)$r['seuil_alerte'];
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($r['date_rapport']) ?></td>
                                <td>
                                    <span class="fw-semibold"><?= htmlspecialchars($r['code_produit']) ?></span><br>
                                    <span class="text-muted small"><?= htmlspecialchars($r['produit_nom']) ?></span>
                                </td>
                                <td class="text-center"><?= (int)$r['seuil_alerte'] ?></td>
                                <td class="text-center">
                                    <span class="fw-semibold <?= $alerte ? 'text-danger' : '' ?>">
                                        <?= (int)$r['stock_actuel'] ?>
                                    </span>
                                    <?php if ($alerte): ?>
                                        <span class="badge bg-danger-subtle text-danger ms-1">
                                            Alerte
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($r['magasinier_nom'] ?? '') ?></td>
                                <td><?= nl2br(htmlspecialchars($r['impact_commercial'] ?? '')) ?></td>
                                <td><?= nl2br(htmlspecialchars($r['action_proposee'] ?? '')) ?></td>
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
