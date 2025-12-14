<?php
/**
 * coordination/livraisons.php - Liste bons de livraison avec filtres
 */

require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

// Paramètres de filtre
$statut = $_GET['statut'] ?? '';
$livreur_id = isset($_GET['livreur_id']) ? (int)$_GET['livreur_id'] : 0;
$date_debut = $_GET['date_debut'] ?? null;
$date_fin = $_GET['date_fin'] ?? null;

// Charger livreurs pour filtre
$stmt = $pdo->query("SELECT DISTINCT u.id, u.nom_complet FROM utilisateurs u 
                     JOIN bons_livraison bl ON bl.livreur_id = u.id 
                     JOIN utilisateur_role ur ON ur.utilisateur_id = u.id
                     JOIN roles r ON r.id = ur.role_id
                     WHERE r.code IN ('MAGASINIER', 'LIVREUR') ORDER BY u.nom_complet");
$livreurs = $stmt->fetchAll();

// Construire WHERE clause
$where = ['1=1'];
$params = [];

if ($statut !== '' && in_array($statut, ['EN_PREPARATION','PRET','EN_COURS_LIVRAISON','LIVRE','ANNULE'])) {
    $where[] = "bl.statut = ?";
    $params[] = $statut;
}
if ($livreur_id > 0) {
    $where[] = "bl.livreur_id = ?";
    $params[] = $livreur_id;
}
if ($date_debut) {
    $where[] = "bl.date_bl >= ?";
    $params[] = $date_debut;
}
if ($date_fin) {
    $where[] = "bl.date_bl <= ?";
    $params[] = $date_fin;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// Query bons de livraison
$sql = "
    SELECT bl.*, 
           c.nom as client_nom,
           v.numero as vente_numero,
           u.nom_complet as livreur_nom,
           COUNT(bll.id) as nb_lignes
    FROM bons_livraison bl
    JOIN clients c ON c.id = bl.client_id
    LEFT JOIN ventes v ON v.id = bl.vente_id
    LEFT JOIN utilisateurs u ON u.id = bl.livreur_id
    LEFT JOIN bons_livraison_lignes bll ON bll.bon_livraison_id = bl.id
    $whereSql
    GROUP BY bl.id
    ORDER BY bl.date_bl DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bls = $stmt->fetchAll();

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
include __DIR__ . '/navigation.php';  // Navigation coordination
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="bi bi-truck"></i> Bons de Livraison
        </h1>
        <a href="<?= url_for('livraisons/create.php') ?>" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Nouveau BL
        </a>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($flashSuccess) ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($flashError) ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="">Tous</option>
                        <?php foreach (['EN_PREPARATION','PRET','EN_COURS_LIVRAISON','LIVRE','ANNULE'] as $s): ?>
                            <option value="<?= $s ?>" <?= $statut === $s ? 'selected' : '' ?>>
                                <?= $s ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Livreur</label>
                    <select name="livreur_id" class="form-select">
                        <option value="0">Tous</option>
                        <?php foreach ($livreurs as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= $livreur_id === (int)$l['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['nom_complet']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control" value="<?= htmlspecialchars($date_debut ?? '') ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control" value="<?= htmlspecialchars($date_fin ?? '') ?>">
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Filtrer
                    </button>
                </div>
                
                <div class="col-12">
                    <a href="<?= url_for('coordination/livraisons.php') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-clockwise"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tableau BLs -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($bls)): ?>
                <div class="alert alert-info m-4">
                    <i class="bi bi-info-circle"></i> Aucun bon de livraison ne correspond aux filtres
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>N° BL</th>
                                <th>Vente</th>
                                <th>Client</th>
                                <th>Livreur</th>
                                <th>Date BL</th>
                                <th class="text-center">Lignes</th>
                                <th class="text-center">Statut</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($bls as $bl): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($bl['numero']) ?></strong>
                                </td>
                                <td>
                                    <?php if ($bl['vente_numero']): ?>
                                        <a href="<?= url_for('ventes/edit.php?id=' . $bl['vente_id']) ?>" class="table-link">
                                            <?= htmlspecialchars($bl['vente_numero']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($bl['client_nom']) ?></td>
                                <td><?= htmlspecialchars($bl['livreur_nom'] ?? 'Non assigné') ?></td>
                                <td><?= date('d/m/Y', strtotime($bl['date_bl'])) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?= (int)$bl['nb_lignes'] ?></span>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $badgeClass = match($bl['statut']) {
                                        'EN_PREPARATION' => 'secondary',
                                        'PRET' => 'info',
                                        'EN_COURS_LIVRAISON' => 'warning',
                                        'LIVRE' => 'success',
                                        'ANNULE' => 'danger',
                                        default => 'light'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $badgeClass ?>">
                                        <?= $bl['statut'] ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url_for('livraisons/detail.php?id=' . $bl['id']) ?>" class="btn btn-sm btn-outline-primary" title="Détails">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= url_for('livraisons/print.php?id=' . $bl['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Imprimer">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row g-3 mt-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5><?= count($bls) ?></h5>
                    <p class="text-muted mb-0">BL trouvés</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-success">
                        <?php 
                        $livres = array_filter($bls, fn($b) => $b['statut'] === 'LIVRE');
                        echo count($livres);
                        ?>
                    </h5>
                    <p class="text-muted mb-0">Livrés</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="text-warning">
                        <?php 
                        $enCours = array_filter($bls, fn($b) => in_array($b['statut'], ['EN_PREPARATION','PRET','EN_COURS_LIVRAISON']));
                        echo count($enCours);
                        ?>
                    </h5>
                    <p class="text-muted mb-0">En cours</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
