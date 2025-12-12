<?php
// stock/etat.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
// On considère que consulter l'état du stock nécessite au moins la permission PRODUITS_LIRE
exigerPermission('PRODUITS_LIRE');

global $pdo;

// --- Filtres ---
$q = trim($_GET['q'] ?? '');
$hideZero = isset($_GET['hide_zero']) && $_GET['hide_zero'] === '1';

$where = 'WHERE p.actif = 1';
$params = [];

if ($q !== '') {
    $where .= ' AND (p.code_produit LIKE :q OR p.designation LIKE :q)';
    $params['q'] = '%' . $q . '%';
}

// --- Récupération des produits + stock calculé à partir des mouvements ---
// On utilise la table `stocks_mouvements` : ENTREE => +quantite, SORTIE => -quantite, AJUSTEMENT => +quantite
$sql = "
    SELECT
        p.id,
        p.code_produit,
        p.designation,
        p.prix_vente,
        COALESCE(SUM(
            CASE
                WHEN m.type_mouvement = 'ENTREE' THEN m.quantite
                WHEN m.type_mouvement = 'SORTIE' THEN -m.quantite
                WHEN m.type_mouvement = 'AJUSTEMENT' THEN m.quantite
                ELSE 0
            END
        ), 0) AS stock_qte
    FROM produits p
    LEFT JOIN stocks_mouvements m ON m.produit_id = p.id
    $where
    GROUP BY p.id, p.code_produit, p.designation, p.prix_vente
    ORDER BY p.designation
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Calcul des totaux (en tenant compte éventuellement du filtre "cacher stock = 0")
$totalValeur = 0;
$totalArticles = 0;

$produitsAffiches = [];

foreach ($rows as $r) {
    $stock = (float)$r['stock_qte'];
    if ($hideZero && abs($stock) < 0.00001) {
        // On masque les produits à stock nul si demandé
        continue;
    }

    $prixVente = (float)$r['prix_vente'];
    $valeur = $stock * $prixVente;

    $r['stock_qte'] = $stock;
    $r['valeur_stock'] = $valeur;

    $produitsAffiches[] = $r;

    $totalArticles += $stock;
    $totalValeur += $valeur;
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">État du stock produits</h1>
    </div>

    <!-- Filtres -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Recherche produit</label>
                    <input type="text"
                           name="q"
                           class="form-control"
                           placeholder="Code produit ou désignation..."
                           value="<?= htmlspecialchars($q) ?>">
                </div>
                <div class="col-md-4">
                    <div class="form-check mt-4">
                        <input class="form-check-input"
                               type="checkbox"
                               value="1"
                               id="hide_zero"
                               name="hide_zero"
                            <?= $hideZero ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="hide_zero">
                            Masquer les produits avec stock = 0
                        </label>
                    </div>
                </div>
                <div class="col-md-4 d-flex gap-2 mt-3 mt-md-0">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('stock/etat.php') ?>" class="btn btn-outline-secondary">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Résumé global -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card border-start border-4 border-primary">
                <div class="card-body py-2">
                    <div class="small text-muted">Nombre de produits affichés</div>
                    <div class="h5 mb-0"><?= count($produitsAffiches) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-start border-4 border-success">
                <div class="card-body py-2">
                    <div class="small text-muted">Stock total (quantité)</div>
                    <div class="h5 mb-0"><?= number_format($totalArticles, 2, ',', ' ') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-start border-4 border-warning">
                <div class="card-body py-2">
                    <div class="small text-muted">Valeur théorique du stock</div>
                    <div class="h5 mb-0"><?= number_format($totalValeur, 0, ',', ' ') ?> FCFA</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste détaillée -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($produitsAffiches)): ?>
                <p class="text-muted mb-0">
                    Aucun produit trouvé pour les filtres sélectionnés.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 12%;">Code</th>
                            <th>Produit</th>
                            <th class="text-end" style="width: 12%;">Prix vente</th>
                            <th class="text-end" style="width: 12%;">Stock actuel</th>
                            <th class="text-end" style="width: 16%;">Valeur stock</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($produitsAffiches as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['code_produit']) ?></td>
                                <td><?= htmlspecialchars($p['designation']) ?></td>
                                <td class="text-end">
                                    <?= number_format((float)$p['prix_vente'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="text-end">
                                    <?= number_format((float)$p['stock_qte'], 2, ',', ' ') ?>
                                </td>
                                <td class="text-end">
                                    <?= number_format((float)$p['valeur_stock'], 0, ',', ' ') ?> FCFA
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <tr class="fw-semibold">
                            <td colspan="3" class="text-end">Total</td>
                            <td class="text-end">
                                <?= number_format($totalArticles, 2, ',', ' ') ?>
                            </td>
                            <td class="text-end">
                                <?= number_format($totalValeur, 0, ',', ' ') ?> FCFA
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
