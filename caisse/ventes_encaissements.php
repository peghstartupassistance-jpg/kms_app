<?php
// caisse/ventes_encaissements.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CAISSE_LIRE');

global $pdo;

$today     = date('Y-m-d');
$dateDebut = $_GET['date_debut'] ?? $today;
$dateFin   = $_GET['date_fin'] ?? $today;

// Optionnel : filtrer seulement les ventes livrées / partiellement livrées
$statut    = $_GET['statut'] ?? 'LIVREES';

// Construction WHERE ventes
$where  = [];
$params = [];

if ($dateDebut !== '') {
    $where[] = "v.date_vente >= :date_debut";
    $params['date_debut'] = $dateDebut;
}
if ($dateFin !== '') {
    $where[] = "v.date_vente <= :date_fin";
    $params['date_fin'] = $dateFin;
}

if ($statut === 'LIVREES') {
    $where[] = "v.statut IN ('LIVREE','PARTIELLEMENT_LIVREE')";
} elseif ($statut === 'TOUTES') {
    // rien
} else {
    // fallback simple
    $where[] = "v.statut IN ('LIVREE','PARTIELLEMENT_LIVREE')";
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// Récup ventes + encaissements
$sql = "
    SELECT
        v.id,
        v.numero,
        v.date_vente,
        v.montant_total_ttc,
        v.statut,
        c.nom AS client_nom,
        cv.code AS canal_code,
        cv.libelle AS canal_libelle,
        COALESCE(jc.total_encaisse, 0) AS montant_encaisse
    FROM ventes v
    JOIN clients c ON c.id = v.client_id
    JOIN canaux_vente cv ON cv.id = v.canal_vente_id
    LEFT JOIN (
        SELECT vente_id, SUM(montant) AS total_encaisse
        FROM journal_caisse
        WHERE sens = 'RECETTE' AND vente_id IS NOT NULL
        GROUP BY vente_id
    ) jc ON jc.vente_id = v.id
    $whereSql
    ORDER BY v.date_vente DESC, v.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ventes = $stmt->fetchAll();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Ventes livrées vs encaissements</h1>
        <a href="<?= url_for('caisse/journal.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Retour au journal de caisse
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-md-3">
                    <label class="form-label small">Date vente - du</label>
                    <input type="date" name="date_debut" class="form-control"
                           value="<?= htmlspecialchars($dateDebut) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">au</label>
                    <input type="date" name="date_fin" class="form-control"
                           value="<?= htmlspecialchars($dateFin) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Filtre statut</label>
                    <select name="statut" class="form-select">
                        <option value="LIVREES" <?= $statut === 'LIVREES' ? 'selected' : '' ?>>
                            Livrées / Partiellement livrées
                        </option>
                        <option value="TOUTES" <?= $statut === 'TOUTES' ? 'selected' : '' ?>>
                            Toutes les ventes
                        </option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary mt-4">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('caisse/ventes_encaissements.php') ?>" class="btn btn-outline-secondary mt-4">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($ventes)): ?>
                <p class="text-muted mb-0">
                    Aucune vente trouvée pour la période sélectionnée.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>N° vente</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Canal</th>
                            <th class="text-end">Montant TTC</th>
                            <th class="text-end">Montant encaissé</th>
                            <th class="text-center">Statut encaissement</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ventes as $v): ?>
                            <?php
                            $total   = (float)$v['montant_total_ttc'];
                            $enc     = (float)$v['montant_encaisse'];
                            $ratio   = ($total > 0) ? $enc / $total : 0;

                            if ($enc <= 0.0001) {
                                $encStatut = 'Aucun encaissement';
                                $badge     = 'bg-danger-subtle text-danger';
                            } elseif (abs($enc - $total) < 0.01) {
                                $encStatut = 'Encaissement complet';
                                $badge     = 'bg-success-subtle text-success';
                            } elseif ($enc > 0 && $enc < $total) {
                                $encStatut = 'Encaissement partiel';
                                $badge     = 'bg-warning-subtle text-warning';
                            } else {
                                $encStatut = 'Incohérence (à vérifier)';
                                $badge     = 'bg-dark-subtle text-dark';
                            }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($v['numero']) ?></td>
                                <td><?= htmlspecialchars($v['date_vente']) ?></td>
                                <td><?= htmlspecialchars($v['client_nom']) ?></td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary">
                                        <?= htmlspecialchars($v['canal_code']) ?>
                                    </span>
                                    <span class="text-muted small d-block">
                                        <?= htmlspecialchars($v['canal_libelle']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?= number_format($total, 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="text-end">
                                    <?= number_format($enc, 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $badge ?>">
                                        <?= htmlspecialchars($encStatut) ?>
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
