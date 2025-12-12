<?php
// coordination/litiges.php - Gestion des retours et litiges clients
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

$utilisateur = utilisateurConnecte();

// Filtres
$statut = $_GET['statut'] ?? '';
$type = $_GET['type'] ?? '';
$dateDebut = $_GET['date_debut'] ?? '';
$dateFin = $_GET['date_fin'] ?? '';

$where = [];
$params = [];

if ($statut !== '') {
    $where[] = "rl.statut_traitement = :statut";
    $params['statut'] = $statut;
}

if ($type !== '') {
    $where[] = "rl.type_probleme = :type";
    $params['type'] = $type;
}

if ($dateDebut !== '') {
    $where[] = "rl.date_retour >= :date_debut";
    $params['date_debut'] = $dateDebut;
}

if ($dateFin !== '') {
    $where[] = "rl.date_retour <= :date_fin";
    $params['date_fin'] = $dateFin;
}

$whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT rl.*,
           c.nom AS client_nom,
           c.telephone AS client_telephone,
           v.numero AS numero_vente,
           p.code_produit,
           p.designation AS produit_designation,
           u.nom_complet AS responsable
    FROM retours_litiges rl
    INNER JOIN clients c ON rl.client_id = c.id
    LEFT JOIN ventes v ON rl.vente_id = v.id
    LEFT JOIN produits p ON rl.produit_id = p.id
    LEFT JOIN utilisateurs u ON rl.responsable_suivi_id = u.id
    $whereSql
    ORDER BY 
        CASE rl.statut_traitement
            WHEN 'EN_COURS' THEN 1
            WHEN 'RESOLU' THEN 2
            WHEN 'REMPLACEMENT_EFFECTUE' THEN 3
            WHEN 'REMBOURSEMENT_EFFECTUE' THEN 4
            WHEN 'ABANDONNE' THEN 5
        END,
        rl.date_retour DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$litiges = $stmt->fetchAll();

// Statistiques
$sqlStats = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut_traitement = 'EN_COURS' THEN 1 ELSE 0 END) as en_cours,
        SUM(CASE WHEN statut_traitement IN ('RESOLU', 'REMPLACEMENT_EFFECTUE', 'REMBOURSEMENT_EFFECTUE') THEN 1 ELSE 0 END) as resolus,
        SUM(montant_rembourse) as total_rembourse
    FROM retours_litiges
    $whereSql
";
$stmtStats = $pdo->prepare($sqlStats);
$stmtStats->execute($params);
$stats = $stmtStats->fetch();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="h4 mb-4">
        <i class="bi bi-arrow-left-right"></i> Retours & Litiges Clients
    </h1>

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body p-3">
                    <div class="text-muted small">Total litiges</div>
                    <div class="fs-5 fw-bold"><?= number_format($stats['total']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body p-3">
                    <div class="text-muted small">En cours</div>
                    <div class="fs-5 fw-bold text-warning"><?= number_format($stats['en_cours']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body p-3">
                    <div class="text-muted small">Résolus</div>
                    <div class="fs-5 fw-bold text-success"><?= number_format($stats['resolus']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body p-3">
                    <div class="text-muted small">Total remboursé</div>
                    <div class="fs-5 fw-bold"><?= number_format($stats['total_rembourse'], 0, ',', ' ') ?> FCFA</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">Statut</label>
                    <select name="statut" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        <option value="EN_COURS" <?= $statut === 'EN_COURS' ? 'selected' : '' ?>>En cours</option>
                        <option value="RESOLU" <?= $statut === 'RESOLU' ? 'selected' : '' ?>>Résolu</option>
                        <option value="REMPLACEMENT_EFFECTUE" <?= $statut === 'REMPLACEMENT_EFFECTUE' ? 'selected' : '' ?>>Remplacement effectué</option>
                        <option value="REMBOURSEMENT_EFFECTUE" <?= $statut === 'REMBOURSEMENT_EFFECTUE' ? 'selected' : '' ?>>Remboursement effectué</option>
                        <option value="ABANDONNE" <?= $statut === 'ABANDONNE' ? 'selected' : '' ?>>Abandonné</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        <option value="DEFAUT_PRODUIT" <?= $type === 'DEFAUT_PRODUIT' ? 'selected' : '' ?>>Défaut produit</option>
                        <option value="LIVRAISON_NON_CONFORME" <?= $type === 'LIVRAISON_NON_CONFORME' ? 'selected' : '' ?>>Livraison non conforme</option>
                        <option value="RETARD_LIVRAISON" <?= $type === 'RETARD_LIVRAISON' ? 'selected' : '' ?>>Retard livraison</option>
                        <option value="ERREUR_COMMANDE" <?= $type === 'ERREUR_COMMANDE' ? 'selected' : '' ?>>Erreur commande</option>
                        <option value="INSATISFACTION_CLIENT" <?= $type === 'INSATISFACTION_CLIENT' ? 'selected' : '' ?>>Insatisfaction</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control form-control-sm" value="<?= htmlspecialchars($dateDebut) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFin) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-search"></i> Filtrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des litiges -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle small">
                    <thead class="table-light">
                        <tr>
                            <th>N° Litige</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Vente</th>
                            <th>Produit</th>
                            <th>Type problème</th>
                            <th>Motif</th>
                            <th>Solution</th>
                            <th>Statut</th>
                            <th>Responsable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($litiges)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                                    Aucun litige enregistré
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($litiges as $litige): ?>
                                <?php
                                $badges = [
                                    'EN_COURS' => 'warning',
                                    'RESOLU' => 'success',
                                    'REMPLACEMENT_EFFECTUE' => 'info',
                                    'REMBOURSEMENT_EFFECTUE' => 'primary',
                                    'ABANDONNE' => 'secondary'
                                ];
                                $badgeClass = $badges[$litige['statut_traitement']] ?? 'secondary';
                                
                                $typeBadges = [
                                    'DEFAUT_PRODUIT' => 'danger',
                                    'LIVRAISON_NON_CONFORME' => 'warning',
                                    'RETARD_LIVRAISON' => 'warning',
                                    'ERREUR_COMMANDE' => 'danger',
                                    'INSATISFACTION_CLIENT' => 'info'
                                ];
                                $typeBadgeClass = $typeBadges[$litige['type_probleme']] ?? 'secondary';
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($litige['numero_litige']) ?></td>
                                    <td class="text-nowrap"><?= date('d/m/Y', strtotime($litige['date_retour'])) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($litige['client_nom']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($litige['client_telephone'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <?php if ($litige['numero_vente']): ?>
                                            <a href="<?= url_for('ventes/detail.php?id=' . $litige['vente_id']) ?>">
                                                <?= htmlspecialchars($litige['numero_vente']) ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($litige['code_produit']): ?>
                                            <div><?= htmlspecialchars($litige['code_produit']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($litige['produit_designation']) ?></small>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $typeBadgeClass ?>">
                                            <?= str_replace('_', ' ', $litige['type_probleme']) ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 200px;">
                                        <small><?= htmlspecialchars(substr($litige['motif_detaille'], 0, 100)) ?><?= strlen($litige['motif_detaille']) > 100 ? '...' : '' ?></small>
                                    </td>
                                    <td style="max-width: 150px;">
                                        <?php if ($litige['solution_apportee']): ?>
                                            <small><?= htmlspecialchars(substr($litige['solution_apportee'], 0, 80)) ?></small>
                                        <?php endif; ?>
                                        <?php if ($litige['montant_rembourse'] > 0): ?>
                                            <div class="badge bg-primary mt-1">
                                                <?= number_format($litige['montant_rembourse'], 0, ',', ' ') ?> FCFA
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($litige['produit_remplace']): ?>
                                            <div class="badge bg-info mt-1">Produit remplacé</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $badgeClass ?>">
                                            <?= str_replace('_', ' ', $litige['statut_traitement']) ?>
                                        </span>
                                        <?php if ($litige['date_resolution']): ?>
                                            <small class="d-block text-muted">
                                                <?= date('d/m', strtotime($litige['date_resolution'])) ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if ($litige['satisfaction_client_finale']): ?>
                                            <div class="mt-1">
                                                <?php for ($i = 0; $i < $litige['satisfaction_client_finale']; $i++): ?>
                                                    <i class="bi bi-star-fill text-warning"></i>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($litige['responsable'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-warning mt-4">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>Important :</strong> Chaque litige doit être traité dans les <strong>48h maximum</strong>. 
        Le suivi de la satisfaction client finale permet de mesurer la qualité de nos solutions et d'améliorer nos processus.
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
