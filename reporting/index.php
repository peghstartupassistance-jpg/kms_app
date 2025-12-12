<?php
// reporting/index.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('REPORTING_LIRE');

global $pdo;

// --- Filtres période ---
$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin   = $_GET['date_fin'] ?? date('Y-m-d');

// Normalisation basique (pas de validation lourde ici)
$whereVentes = [];
$whereDevis  = [];
$params      = [];

if ($dateDebut !== '') {
    $whereVentes[] = "v.date_vente >= :date_debut";
    $whereDevis[]  = "d.date_devis >= :date_debut";
    $params['date_debut'] = $dateDebut;
}
if ($dateFin !== '') {
    $whereVentes[] = "v.date_vente <= :date_fin";
    $whereDevis[]  = "d.date_devis <= :date_fin";
    $params['date_fin'] = $dateFin;
}

$whereVentesSql = '';
$whereDevisSql  = '';

if (!empty($whereVentes)) {
    $whereVentesSql = 'WHERE ' . implode(' AND ', $whereVentes);
}
if (!empty($whereDevis)) {
    $whereDevisSql = 'WHERE ' . implode(' AND ', $whereDevis);
}

// --- 1. KPIs VENTES (CA, nombre de ventes) ---
$sqlVentes = "
    SELECT
        COUNT(*) AS nb_ventes,
        COALESCE(SUM(CASE WHEN v.statut <> 'ANNULEE'
                     THEN v.montant_total_ttc ELSE 0 END), 0) AS ca_total_ttc,
        COALESCE(SUM(CASE WHEN v.statut <> 'ANNULEE'
                     THEN v.montant_total_ht ELSE 0 END), 0) AS ca_total_ht
    FROM ventes v
    $whereVentesSql
";
$stmt = $pdo->prepare($sqlVentes);
$stmt->execute($params);
$statsVentes = $stmt->fetch() ?: [
    'nb_ventes'      => 0,
    'ca_total_ttc'   => 0,
    'ca_total_ht'    => 0,
];

// --- 2. KPIs DEVIS (nombre, acceptés, taux de conversion) ---
$sqlDevis = "
    SELECT
        COUNT(*) AS total_devis,
        SUM(CASE WHEN d.statut = 'ACCEPTE' THEN 1 ELSE 0 END) AS devis_acceptes,
        SUM(CASE WHEN d.statut = 'REFUSE' THEN 1 ELSE 0 END) AS devis_refuses
    FROM devis d
    $whereDevisSql
";
$stmt = $pdo->prepare($sqlDevis);
$stmt->execute($params);
$statsDevis = $stmt->fetch() ?: [
    'total_devis'    => 0,
    'devis_acceptes' => 0,
    'devis_refuses'  => 0,
];

$tauxConversion = 0;
if ((int)$statsDevis['total_devis'] > 0) {
    $tauxConversion = round(
        ((int)$statsDevis['devis_acceptes'] / (int)$statsDevis['total_devis']) * 100
    );
}

// --- 3. CA par canal de vente ---
$sqlCanaux = "
    SELECT
        cv.id,
        cv.code,
        cv.libelle,
        COALESCE(SUM(CASE WHEN v.statut <> 'ANNULEE'
                     THEN v.montant_total_ttc ELSE 0 END), 0) AS ca_canal
    FROM canaux_vente cv
    LEFT JOIN ventes v ON v.canal_vente_id = cv.id
        " . ($whereVentesSql ? str_replace('WHERE', 'AND', $whereVentesSql) : '') . "
    GROUP BY cv.id, cv.code, cv.libelle
    ORDER BY ca_canal DESC, cv.code
";
$stmt = $pdo->prepare($sqlCanaux);
$stmt->execute($params);
$caParCanal = $stmt->fetchAll();

// --- 4. Top produits (CA sur la période) ---
$sqlTopProduits = "
    SELECT
        p.id,
        p.code_produit,
        p.designation,
        COALESCE(SUM(vl.montant_ligne_ht), 0) AS ca_produit_ht
    FROM produits p
    JOIN ventes_lignes vl ON vl.produit_id = p.id
    JOIN ventes v ON v.id = vl.vente_id
    $whereVentesSql
    AND v.statut <> 'ANNULEE'
    GROUP BY p.id, p.code_produit, p.designation
    HAVING ca_produit_ht > 0
    ORDER BY ca_produit_ht DESC
    LIMIT 5
";
$stmt = $pdo->prepare($sqlTopProduits);
$stmt->execute($params);
$topProduits = $stmt->fetchAll();

// --- 5. Ventilation statuts de ventes ---
$sqlStatutsVente = "
    SELECT
        v.statut,
        COUNT(*) AS nb
    FROM ventes v
    $whereVentesSql
    GROUP BY v.statut
";
$stmt = $pdo->prepare($sqlStatutsVente);
$stmt->execute($params);
$statutsVente = $stmt->fetchAll();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Reporting & tableaux de bord</h1>
    </div>

    <!-- Filtres période -->
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
                <div class="col-md-6 d-flex gap-2 mt-3 mt-md-0">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-search me-1"></i> Actualiser
                    </button>
                    <a href="<?= url_for('reporting/index.php') ?>" class="btn btn-outline-secondary">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Ligne de KPIs principaux -->
    <div class="row g-3 mb-3">
        <!-- CA total TTC -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">CA total TTC (ventes)</div>
                    <div class="h4 mb-0">
                        <?= number_format((float)$statsVentes['ca_total_ttc'], 0, ',', ' ') ?> FCFA
                    </div>
                    <div class="small text-muted mt-1">
                        Ventes non annulées
                    </div>
                </div>
            </div>
        </div>

        <!-- Nombre de ventes -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Nombre total de ventes</div>
                    <div class="h4 mb-0">
                        <?= (int)$statsVentes['nb_ventes'] ?>
                    </div>
                    <div class="small text-muted mt-1">
                        Tous statuts confondus
                    </div>
                </div>
            </div>
        </div>

        <!-- Taux de conversion devis -> acceptés -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Taux de conversion devis</div>
                    <div class="h4 mb-0">
                        <?php if ((int)$statsDevis['total_devis'] > 0): ?>
                            <?= $tauxConversion ?> %
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </div>
                    <div class="small text-muted mt-1">
                        Devis acceptés / devis totaux
                    </div>
                </div>
            </div>
        </div>

        <!-- Nombre de devis -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="text-muted small">Nombre de devis</div>
                    <div class="h4 mb-0">
                        <?= (int)$statsDevis['total_devis'] ?>
                    </div>
                    <div class="small text-muted mt-1">
                        Dont acceptés : <strong><?= (int)$statsDevis['devis_acceptes'] ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Deux colonnes : CA par canal / Top produits -->
    <div class="row g-3 mb-3">
        <!-- CA par canal -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">CA par canal de vente</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($caParCanal)): ?>
                        <p class="text-muted mb-0">Aucune vente sur la période sélectionnée.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Canal</th>
                                    <th class="text-end">CA TTC</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($caParCanal as $c): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold"><?= htmlspecialchars($c['code']) ?></span><br>
                                            <span class="text-muted small"><?= htmlspecialchars($c['libelle']) ?></span>
                                        </td>
                                        <td class="text-end">
                                            <?= number_format((float)$c['ca_canal'], 0, ',', ' ') ?> FCFA
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

        <!-- Top produits -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Top 5 produits par CA (HT)</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($topProduits)): ?>
                        <p class="text-muted mb-0">Aucun produit vendu sur la période sélectionnée.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Produit</th>
                                    <th class="text-end">CA HT</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($topProduits as $p): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold">
                                                <?= htmlspecialchars($p['code_produit']) ?>
                                            </span><br>
                                            <span class="text-muted small">
                                                <?= htmlspecialchars($p['designation']) ?>
                                            </span>
                                            <?php if (!empty($p['id'])): ?>
                                                <a href="../compta/journaux.php?action=detail&piece_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-info ms-2" target="_blank">
                                                    <i class="bi bi-eye"></i> Détail
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?= number_format((float)$p['ca_produit_ht'], 0, ',', ' ') ?> FCFA
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
    </div>

    <!-- Ventilation des statuts de ventes -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h2 class="h6 mb-0">Répartition des ventes par statut</h2>
        </div>
        <div class="card-body">
            <?php if (empty($statutsVente)): ?>
                <p class="text-muted mb-0">Aucune vente enregistrée sur la période sélectionnée.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Statut</th>
                            <th class="text-end">Nombre de ventes</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($statutsVente as $sv): ?>
                            <tr>
                                <td><?= htmlspecialchars($sv['statut']) ?></td>
                                <td class="text-end"><?= (int)$sv['nb'] ?></td>
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
