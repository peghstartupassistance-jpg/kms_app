<?php
// coordination/dashboard.php - Dashboard de coordination vente-livraison-litige-stock
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

require_once __DIR__ . '/../lib/navigation_helpers.php';

global $pdo;

// Statistiques globales
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_ventes,
        COUNT(CASE WHEN statut = 'LIVREE' THEN 1 END) as ventes_livrees,
        COUNT(CASE WHEN statut = 'PARTIELLEMENT_LIVREE' THEN 1 END) as ventes_partielles,
        SUM(montant_total_ttc) as montant_total
    FROM ventes
    WHERE DATE(date_vente) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute();
$statsVentes = $stmt->fetch();

// Litiges en cours
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM retours_litiges 
    WHERE statut_traitement = 'EN_COURS'
");
$stmt->execute();
$litigesEnCours = $stmt->fetch()['count'];

// Ventes sans livraison
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT v.id) as count FROM ventes v
    LEFT JOIN bons_livraison bl ON bl.vente_id = v.id
    WHERE bl.id IS NULL AND DATE(v.date_vente) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
$stmt->execute();
$ventessansLivraison = $stmt->fetch()['count'];

// Ventes avec problèmes de cohérence
$stmt = $pdo->prepare("
    SELECT v.id, v.numero, v.montant_total_ttc
    FROM ventes v
    WHERE DATE(v.date_vente) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    LIMIT 20
");
$stmt->execute();
$ventesRecentes = $stmt->fetchAll();

$ventesAvecProblemes = [];
foreach ($ventesRecentes as $v) {
    $verif = verify_vente_coherence($pdo, $v['id']);
    if (!$verif['ok']) {
        $ventesAvecProblemes[] = [
            'id' => $v['id'],
            'numero' => $v['numero'],
            'montant' => $v['montant_total_ttc'],
            'problemes' => $verif['problemes']
        ];
    }
}

// Récupérer les dernières ventes pour affichage
$stmt = $pdo->prepare("
    SELECT v.id, v.numero, v.date_vente, c.nom as client_nom, v.montant_total_ttc, v.statut
    FROM ventes v
    JOIN clients c ON c.id = v.client_id
    ORDER BY v.date_vente DESC
    LIMIT 10
");
$stmt->execute();
$dernieres = $stmt->fetchAll();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid p-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-1">
                <i class="bi bi-diagram-3"></i> Coordination Ventes-Livraisons-Litiges
            </h1>
            <p class="text-muted">Suivi complet du parcours vente jusqu'à la livraison et gestion des litiges</p>
        </div>
    </div>

    <!-- KPIs principaux -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kms-kpi-card">
                <div class="kms-kpi-icon"><i class="bi bi-receipt"></i></div>
                <div class="kms-kpi-label">Ventes (30j)</div>
                <div class="kms-kpi-value"><?= (int)$statsVentes['total_ventes'] ?></div>
                <div class="kms-kpi-subtitle"><?= number_format($statsVentes['montant_total'] ?? 0, 0, ',', ' ') ?> FCFA</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kms-kpi-card">
                <div class="kms-kpi-icon"><i class="bi bi-check-circle text-success"></i></div>
                <div class="kms-kpi-label">Livrées</div>
                <div class="kms-kpi-value"><?= (int)$statsVentes['ventes_livrees'] ?></div>
                <div class="kms-kpi-subtitle">
                    <?= $statsVentes['total_ventes'] > 0 
                        ? round(($statsVentes['ventes_livrees'] / $statsVentes['total_ventes']) * 100, 0) 
                        : 0 ?>%
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kms-kpi-card">
                <div class="kms-kpi-icon"><i class="bi bi-exclamation-triangle text-warning"></i></div>
                <div class="kms-kpi-label">Litiges EN_COURS</div>
                <div class="kms-kpi-value"><?= $litigesEnCours ?></div>
                <div class="kms-kpi-subtitle">À traiter</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kms-kpi-card">
                <div class="kms-kpi-icon"><i class="bi bi-exclamation-circle text-danger"></i></div>
                <div class="kms-kpi-label">Anomalies</div>
                <div class="kms-kpi-value"><?= count($ventesAvecProblemes) ?></div>
                <div class="kms-kpi-subtitle">Ventes problématiques</div>
            </div>
        </div>
    </div>

    <!-- Navigation rapide -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <a href="<?= url_for('ventes/detail_360.php') ?>" class="btn btn-outline-primary w-100 py-2">
                <i class="bi bi-arrow-right-circle"></i> Ventes 360°
            </a>
        </div>
        <div class="col-md-4">
            <a href="<?= url_for('coordination/verification_synchronisation.php') ?>" class="btn btn-outline-success w-100 py-2">
                <i class="bi bi-check-all"></i> Vérification Synchronisation
            </a>
        </div>
        <div class="col-md-4">
            <a href="<?= url_for('coordination/litiges.php') ?>" class="btn btn-outline-danger w-100 py-2">
                <i class="bi bi-exclamation-lg"></i> Gestion Litiges
            </a>
        </div>
    </div>

    <!-- Alertes critiques -->
    <?php if (!empty($ventesAvecProblemes)): ?>
        <div class="alert alert-danger mb-4">
            <h5 class="alert-heading">
                <i class="bi bi-exclamation-triangle"></i> <?= count($ventesAvecProblemes) ?> vente(s) avec anomalies détectées
            </h5>
            <div class="row g-2 mt-2">
                <?php foreach (array_slice($ventesAvecProblemes, 0, 3) as $v): ?>
                    <div class="col-md-6">
                        <div class="card card-sm">
                            <div class="card-body p-2">
                                <strong>#<?= htmlspecialchars($v['numero']) ?></strong>
                                <ul class="list-unstyled small text-danger mb-0">
                                    <?php foreach ($v['problemes'] as $p): ?>
                                        <li><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($p) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <a href="<?= url_for('ventes/detail_360.php?id=' . $v['id']) ?>" class="btn btn-sm btn-outline-danger mt-2">
                                    Vérifier
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-3">
                <a href="<?= url_for('coordination/verification_synchronisation.php') ?>" class="btn btn-sm btn-danger">
                    <i class="bi bi-search"></i> Voir toutes les anomalies
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($ventessansLivraison > 0): ?>
        <div class="alert alert-warning mb-4">
            <i class="bi bi-exclamation-circle"></i> 
            <strong><?= $ventessansLivraison ?> vente(s) sans livraison</strong> créée(s) dans les 7 derniers jours
        </div>
    <?php endif; ?>

    <!-- Onglets principaux -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-dernieres" data-bs-toggle="tab" data-bs-target="#dernieres" type="button">
                <i class="bi bi-list"></i> Dernières Ventes
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-workflow" data-bs-toggle="tab" data-bs-target="#workflow" type="button">
                <i class="bi bi-diagram-3"></i> Flux de Travail
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-guide" data-bs-toggle="tab" data-bs-target="#guide" type="button">
                <i class="bi bi-book"></i> Guide Rapide
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- TAB: Dernières ventes -->
        <div class="tab-pane fade show active" id="dernieres" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Numéro</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dernieres as $vente): ?>
                            <tr>
                                <td>
                                    <a href="<?= url_for('ventes/detail_360.php?id=' . $vente['id']) ?>" class="text-decoration-none fw-bold">
                                        #<?= htmlspecialchars($vente['numero']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($vente['client_nom']) ?></td>
                                <td><?= date('d/m/Y', strtotime($vente['date_vente'])) ?></td>
                                <td class="text-end"><?= number_format($vente['montant_total_ttc'], 0, ',', ' ') ?> FCFA</td>
                                <td>
                                    <span class="badge bg-info"><?= htmlspecialchars($vente['statut']) ?></span>
                                </td>
                                <td>
                                    <a href="<?= url_for('ventes/detail_360.php?id=' . $vente['id']) ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB: Flux de travail -->
        <div class="tab-pane fade" id="workflow" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <strong>Flux Standard Vente-Livraison</strong>
                        </div>
                        <div class="card-body">
                            <div class="step-flow">
                                <div class="step mb-3">
                                    <div class="step-number">1</div>
                                    <div class="step-content">
                                        <h6>Créer/Consulter Vente</h6>
                                        <p class="text-muted small">Accédez à la liste des ventes ou créez une nouvelle</p>
                                    </div>
                                </div>
                                <div class="arrow">↓</div>
                                <div class="step mb-3">
                                    <div class="step-number">2</div>
                                    <div class="step-content">
                                        <h6>Créer Ordre de Préparation</h6>
                                        <p class="text-muted small">Générez un ordre pour préparer les produits</p>
                                    </div>
                                </div>
                                <div class="arrow">↓</div>
                                <div class="step mb-3">
                                    <div class="step-number">3</div>
                                    <div class="step-content">
                                        <h6>Créer Bon de Livraison</h6>
                                        <p class="text-muted small">Enregistrez les produits livrés</p>
                                    </div>
                                </div>
                                <div class="arrow">↓</div>
                                <div class="step mb-3">
                                    <div class="step-number">4</div>
                                    <div class="step-content">
                                        <h6>Stock Automatique</h6>
                                        <p class="text-muted small">Les sorties stock se font automatiquement</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <strong>Gestion des Anomalies</strong>
                        </div>
                        <div class="card-body">
                            <div class="step-flow">
                                <div class="step mb-3">
                                    <div class="step-number">⚠️</div>
                                    <div class="step-content">
                                        <h6>Client retourne produit</h6>
                                        <p class="text-muted small">Litige constaté</p>
                                    </div>
                                </div>
                                <div class="arrow">↓</div>
                                <div class="step mb-3">
                                    <div class="step-number">📝</div>
                                    <div class="step-content">
                                        <h6>Créer Litige</h6>
                                        <p class="text-muted small">Documenter le problème et la solution</p>
                                    </div>
                                </div>
                                <div class="arrow">↓</div>
                                <div class="step mb-3">
                                    <div class="step-number">💰</div>
                                    <div class="step-content">
                                        <h6>Gérer Impact Financier</h6>
                                        <p class="text-muted small">Remboursement ou avoir commercial</p>
                                    </div>
                                </div>
                                <div class="arrow">↓</div>
                                <div class="step mb-3">
                                    <div class="step-number">✅</div>
                                    <div class="step-content">
                                        <h6>Clôturer Litige</h6>
                                        <p class="text-muted small">Marquer comme résolu</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: Guide rapide -->
        <div class="tab-pane fade" id="guide" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <strong>Pages de Navigation</strong>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Vue 360° Vente</strong></td>
                                    <td><a href="<?= url_for('ventes/detail_360.php') ?>" class="btn btn-xs btn-outline-primary">Accès</a></td>
                                </tr>
                                <tr>
                                    <td><strong>Détail Livraison</strong></td>
                                    <td><a href="<?= url_for('livraisons/detail_navigation.php') ?>" class="btn btn-xs btn-outline-primary">Accès</a></td>
                                </tr>
                                <tr>
                                    <td><strong>Détail Litige</strong></td>
                                    <td><a href="<?= url_for('coordination/litiges_navigation.php') ?>" class="btn btn-xs btn-outline-primary">Accès</a></td>
                                </tr>
                                <tr>
                                    <td><strong>Vérification Sync</strong></td>
                                    <td><a href="<?= url_for('coordination/verification_synchronisation.php') ?>" class="btn btn-xs btn-outline-success">Accès</a></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <strong>Documentation</strong>
                        </div>
                        <div class="card-body">
                            <p><strong>Guide complet d'interconnexion :</strong></p>
                            <p class="text-muted">Consultez le fichier <code>GUIDE_NAVIGATION_INTERCONNEXION.md</code> pour :</p>
                            <ul class="small text-muted">
                                <li>Parcours utilisateur détaillé</li>
                                <li>Schéma de synchronisation</li>
                                <li>Cas d'usage courants</li>
                                <li>Troubleshooting</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <style>
                .step-flow {
                    border-left: 3px solid var(--accent);
                    padding-left: 15px;
                }
                .step {
                    display: flex;
                    gap: 10px;
                }
                .step-number {
                    font-weight: bold;
                    color: var(--accent);
                    min-width: 25px;
                }
                .step-content h6 {
                    margin-bottom: 5px;
                }
                .arrow {
                    text-align: left;
                    color: var(--accent);
                    font-weight: bold;
                    padding-left: 7px;
                    margin: 5px 0;
                }
            </style>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
