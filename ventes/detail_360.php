<?php
// ventes/detail_360.php - Vue 360° d'une vente avec tous les éléments liés
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

$venteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($venteId === 0) {
    http_response_code(404);
    die('Vente non trouvée.');
}

// Récupérer la vente
$stmt = $pdo->prepare("
    SELECT v.*, c.nom as client_nom, c.adresse, c.telephone, cv.libelle as canal_nom, u.nom_complet as commercial_nom
    FROM ventes v
    JOIN clients c ON c.id = v.client_id
    JOIN canaux_vente cv ON cv.id = v.canal_vente_id
    JOIN utilisateurs u ON u.id = v.utilisateur_id
    WHERE v.id = ?
");
$stmt->execute([$venteId]);
$vente = $stmt->fetch();

if (!$vente) {
    http_response_code(404);
    die('Vente non trouvée.');
}

// Lignes de vente
$stmt = $pdo->prepare("
    SELECT vl.*, p.code_produit, p.designation, p.stock_actuel
    FROM ventes_lignes vl
    JOIN produits p ON p.id = vl.produit_id
    WHERE vl.vente_id = ?
    ORDER BY vl.id ASC
");
$stmt->execute([$venteId]);
$lignes = $stmt->fetchAll();

// Ordres de préparation liés
$stmt = $pdo->prepare("
    SELECT op.*, COUNT(opl.id) as nb_lignes, SUM(opl.quantite_preparee) as total_prepare
    FROM ordres_preparation op
    LEFT JOIN ordres_preparation_lignes opl ON opl.ordre_preparation_id = op.id
    WHERE op.vente_id = ?
    GROUP BY op.id
    ORDER BY op.date_ordre DESC
");
$stmt->execute([$venteId]);
$ordres = $stmt->fetchAll();

// Bons de livraison liés
$stmt = $pdo->prepare("
    SELECT bl.*, COUNT(bll.id) as nb_lignes, SUM(bll.quantite) as total_livre
    FROM bons_livraison bl
    LEFT JOIN bons_livraison_lignes bll ON bll.bon_livraison_id = bl.id
    WHERE bl.vente_id = ?
    GROUP BY bl.id
    ORDER BY bl.date_bl DESC
");
$stmt->execute([$venteId]);
$livraisons = $stmt->fetchAll();

// Retours et litiges liés
$stmt = $pdo->prepare("
    SELECT rl.*, p.code_produit, p.designation
    FROM retours_litiges rl
    JOIN produits p ON p.id = rl.produit_id
    WHERE rl.vente_id = ?
    ORDER BY rl.date_retour DESC
");
$stmt->execute([$venteId]);
$litiges = $stmt->fetchAll();

// Mouvements de stock liés à cette vente
$stmt = $pdo->prepare("
    SELECT sm.*, p.code_produit, p.designation, p.stock_actuel
    FROM stocks_mouvements sm
    JOIN produits p ON p.id = sm.produit_id
    WHERE (sm.source_id = ? AND sm.source_type = 'VENTE')
    ORDER BY sm.date_mouvement DESC, sm.id DESC
");
$stmt->execute([$venteId]);
$mouvementsStock = $stmt->fetchAll();

// Écritures comptables liées (via compta_pieces)
$stmt = $pdo->prepare("
    SELECT ce.*, cp.numero_piece, cp.date_piece
    FROM compta_ecritures ce
    JOIN compta_pieces cp ON cp.id = ce.piece_id
    WHERE (cp.reference_type = 'VENTE' AND cp.reference_id = ?)
    ORDER BY cp.date_piece DESC
");
$stmt->execute([$venteId]);
$ecritures = $stmt->fetchAll();

// Encaissements (caisse) - unified to journal_caisse
$stmt = $pdo->prepare("
    SELECT jc.*
    FROM journal_caisse jc
    WHERE (jc.vente_id = ? AND jc.sens = 'RECETTE') 
       OR (jc.source_id = ? AND jc.source_type = 'VENTE')
       OR jc.commentaire LIKE ?
       AND jc.est_annule = 0
    ORDER BY jc.date_operation DESC
");
$stmt->execute([$venteId, $venteId, '%V' . str_pad($venteId, 6, '0', STR_PAD_LEFT) . '%']);
$encaissements = $stmt->fetchAll();

// Calculs de synthèse
$totalLivree = array_sum(array_column($livraisons, 'total_livre')) ?: 0;
$tauxLivraison = ($vente['montant_total_ttc'] > 0) ? round(($totalLivree / $vente['montant_total_ttc']) * 100, 1) : 0;

$totalRetourne = array_sum(array_map(function($l) {
    return ($l['montant_rembourse'] ?? 0) + ($l['montant_avoir'] ?? 0);
}, $litiges));

$totalEncaisse = array_sum(array_column($encaissements, 'montant')) ?: 0;
$tauxEncaissement = ($vente['montant_total_ttc'] > 0) ? round(($totalEncaisse / $vente['montant_total_ttc']) * 100, 1) : 0;

// Vérifier la synchronisation : tous les BL générés + encaisse OK
$tousLesBLGeneresEtSignes = (count($livraisons) > 0) && array_reduce($livraisons, function($carry, $bl) {
    return $carry && ((int)($bl['signe_client'] ?? 0) === 1);
}, true);
$sync_ok = $tousLesBLGeneresEtSignes && ($totalEncaisse > 0 || $tauxLivraison === 100.0);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid p-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-1">Vente #<?= htmlspecialchars($vente['numero']) ?></h1>
            <p class="text-muted mb-0">
                <i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($vente['date_vente'])) ?>
                • <i class="bi bi-person"></i> <?= htmlspecialchars($vente['client_nom']) ?>
            </p>
        </div>
        <div class="col-md-4 text-end">
            <div class="kms-kpi-card" style="background: var(--bg-secondary); border: 1px solid var(--border);">
                <div class="kms-kpi-label">Montant TTC</div>
                <div class="kms-kpi-value"><?= number_format($vente['montant_total_ttc'], 0, ',', ' ') ?> FCFA</div>
            </div>
        </div>
    </div>

    <!-- Synthèse État Vente -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kms-kpi-card">
                <div class="kms-kpi-icon"><i class="bi bi-box-seam"></i></div>
                <div class="kms-kpi-label">État Livraison</div>
                <div class="kms-kpi-value text-success"><?= htmlspecialchars($vente['statut']) ?></div>
                <div class="kms-kpi-subtitle"><?= $tauxLivraison ?>% complète</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kms-kpi-card">
                <div class="kms-kpi-icon"><i class="bi bi-cash-coin"></i></div>
                <div class="kms-kpi-label">Encaissement</div>
                <div class="kms-kpi-value text-info"><?= number_format($totalEncaisse, 0, ',', ' ') ?></div>
                <div class="kms-kpi-subtitle"><?= $tauxEncaissement ?>% encaissé</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kms-kpi-card">
                <div class="kms-kpi-icon"><i class="bi bi-arrow-left-right"></i></div>
                <div class="kms-kpi-label">Retours/Litiges</div>
                <div class="kms-kpi-value text-warning"><?= count($litiges) ?></div>
                <div class="kms-kpi-subtitle"><?= number_format($totalRetourne, 0, ',', ' ') ?> FCFA</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kms-kpi-card">
                <div class="kms-kpi-icon"><i class="bi bi-check-circle"></i></div>
                <div class="kms-kpi-label">Synchronisation</div>
                <div class="kms-kpi-value">
                    <?php 
                    echo $sync_ok ? '<span class="text-success">✅</span>' : '<span class="text-danger">⚠️</span>';
                    ?>
                </div>
                <div class="kms-kpi-subtitle">Livrés & Encaissés</div>
            </div>
        </div>
    </div>

    <!-- Onglets principaux -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-infos" data-bs-toggle="tab" data-bs-target="#infos" type="button">
                <i class="bi bi-info-circle"></i> Informations
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-ordres" data-bs-toggle="tab" data-bs-target="#ordres" type="button">
                <i class="bi bi-list-check"></i> Ordres de préparation (<?= count($ordres) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-livraisons" data-bs-toggle="tab" data-bs-target="#livraisons" type="button">
                <i class="bi bi-truck"></i> Livraisons (<?= count($livraisons) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-litiges" data-bs-toggle="tab" data-bs-target="#litiges" type="button">
                <i class="bi bi-exclamation-triangle"></i> Retours/Litiges (<?= count($litiges) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-stock" data-bs-toggle="tab" data-bs-target="#stock" type="button">
                <i class="bi bi-boxes"></i> Mouvements Stock
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-tresorerie" data-bs-toggle="tab" data-bs-target="#tresorerie" type="button">
                <i class="bi bi-wallet2"></i> Trésorerie & Comptabilité
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- TAB: Informations -->
        <div class="tab-pane fade show active" id="infos" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <strong>Informations Vente</strong>
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-5">Numéro :</dt>
                                <dd class="col-sm-7"><code><?= htmlspecialchars($vente['numero']) ?></code></dd>
                                
                                <dt class="col-sm-5">Date :</dt>
                                <dd class="col-sm-7"><?= date('d/m/Y H:i', strtotime($vente['date_vente'])) ?></dd>
                                
                                <dt class="col-sm-5">Client :</dt>
                                <dd class="col-sm-7">
                                    <a href="<?= url_for('clients/detail.php?id=' . $vente['client_id']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($vente['client_nom']) ?>
                                    </a>
                                </dd>
                                
                                <dt class="col-sm-5">Commercial :</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($vente['commercial_nom']) ?></dd>
                                
                                <dt class="col-sm-5">Canal :</dt>
                                <dd class="col-sm-7"><?= htmlspecialchars($vente['canal_nom']) ?></dd>
                                
                                <dt class="col-sm-5">Statut :</dt>
                                <dd class="col-sm-7">
                                    <span class="badge bg-info"><?= htmlspecialchars($vente['statut']) ?></span>
                                </dd>
                                
                                <dt class="col-sm-5">Devis :</dt>
                                <dd class="col-sm-7">
                                    <?php if ($vente['devis_id']): ?>
                                        <a href="<?= url_for('devis/detail.php?id=' . $vente['devis_id']) ?>" class="text-decoration-none">
                                            Voir le devis
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Non associé</span>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <strong>Montants</strong>
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-5">Montant HT :</dt>
                                <dd class="col-sm-7 text-end"><?= number_format($vente['montant_total_ht'], 2, ',', ' ') ?> FCFA</dd>
                                
                                <dt class="col-sm-5">Montant TTC :</dt>
                                <dd class="col-sm-7 text-end"><strong><?= number_format($vente['montant_total_ttc'], 2, ',', ' ') ?> FCFA</strong></dd>
                                
                                <dt class="col-sm-5">Encaissé :</dt>
                                <dd class="col-sm-7 text-end">
                                    <span class="text-success"><?= number_format($totalEncaisse, 2, ',', ' ') ?> FCFA</span>
                                </dd>
                                
                                <dt class="col-sm-5">Retours/Avoirs :</dt>
                                <dd class="col-sm-7 text-end">
                                    <span class="text-danger">-<?= number_format($totalRetourne, 2, ',', ' ') ?> FCFA</span>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <strong>Lignes de Vente (<?= count($lignes) ?>)</strong>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Produit</th>
                                <th class="text-end">Qté</th>
                                <th class="text-end">PU HT</th>
                                <th class="text-end">Total HT</th>
                                <th>Stock Actual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lignes as $ligne): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url_for('produits/edit.php?id=' . $ligne['produit_id']) ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($ligne['code_produit']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($ligne['designation']) ?></td>
                                    <td class="text-end"><?= (int)$ligne['quantite'] ?></td>
                                    <td class="text-end"><?= number_format($ligne['prix_unitaire'], 2, ',', ' ') ?></td>
                                    <td class="text-end"><?= number_format($ligne['montant_ligne_ht'], 2, ',', ' ') ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= (int)$ligne['stock_actuel'] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB: Ordres de préparation -->
        <div class="tab-pane fade" id="ordres" role="tabpanel">
            <?php if (empty($ordres)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Aucun ordre de préparation pour cette vente.
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($ordres as $ordre): ?>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>Ordre #<?= htmlspecialchars($ordre['numero']) ?></strong>
                                        <span class="badge bg-warning"><?= htmlspecialchars($ordre['statut']) ?></span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="small text-muted mb-2">
                                        <i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($ordre['date_creation'])) ?>
                                    </p>
                                    <p class="small mb-2">
                                        <strong><?= $ordre['nb_lignes'] ?></strong> lignes |
                                        <strong><?= $ordre['total_prepare'] ?></strong> préparées
                                    </p>
                                    <a href="<?= url_for('coordination/ordres_preparation.php?id=' . $ordre['id']) ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Détails
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: Livraisons -->
        <div class="tab-pane fade" id="livraisons" role="tabpanel">
            <?php if (empty($livraisons)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Aucune livraison pour cette vente.
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($livraisons as $livraison): ?>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>BL #<?= htmlspecialchars($livraison['numero']) ?></strong>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="small text-muted mb-2">
                                        <i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($livraison['date_bl'])) ?>
                                    </p>
                                    <p class="small mb-2">
                                        <strong><?= $livraison['nb_lignes'] ?></strong> lignes |
                                        <strong><?= $livraison['total_livre'] ?></strong> livrées
                                    </p>
                                    <a href="<?= url_for('livraisons/detail.php?id=' . $livraison['id']) ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Détails
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: Retours et Litiges -->
        <div class="tab-pane fade" id="litiges" role="tabpanel">
            <?php if (empty($litiges)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> Aucun litige pour cette vente.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Produit</th>
                                <th>Type</th>
                                <th>Motif</th>
                                <th>Statut</th>
                                <th>Montant</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($litiges as $litige): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($litige['date_retour'])) ?></td>
                                    <td><?= htmlspecialchars($litige['code_produit'] ?? '?') ?> - <?= htmlspecialchars($litige['designation'] ?? '-') ?></td>
                                    <td><span class="badge bg-warning"><?= htmlspecialchars($litige['type_probleme']) ?></span></td>
                                    <td><?= htmlspecialchars(substr($litige['motif'], 0, 50)) ?>...</td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($litige['statut_traitement']) ?></span></td>
                                    <td class="text-end">
                                        <?php
                                        $montant = ($litige['montant_rembourse'] ?? 0) + ($litige['montant_avoir'] ?? 0);
                                        echo number_format($montant, 0, ',', ' ') . ' FCFA';
                                        ?>
                                    </td>
                                    <td>
                                        <a href="<?= url_for('coordination/litiges.php?id=' . $litige['id']) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: Mouvements de stock -->
        <div class="tab-pane fade" id="stock" role="tabpanel">
            <?php if (empty($mouvementsStock)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> Aucun mouvement de stock enregistré pour cette vente.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Produit</th>
                                <th>Type Mouvement</th>
                                <th class="text-end">Quantité</th>
                                <th>Commentaire</th>
                                <th class="text-end">Stock Actuel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mouvementsStock as $mvt): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($mvt['date_mouvement'])) ?></td>
                                    <td><?= htmlspecialchars($mvt['code_produit']) ?> - <?= htmlspecialchars($mvt['designation']) ?></td>
                                    <td><span class="badge bg-primary"><?= htmlspecialchars($mvt['type_mouvement']) ?></span></td>
                                    <td class="text-end"><?= (int)$mvt['quantite'] ?></td>
                                    <td><?= htmlspecialchars($mvt['commentaire'] ?? '-') ?></td>
                                    <td class="text-end"><?= number_format((float)($mvt['stock_actuel'] ?? 0), 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: Trésorerie et Comptabilité -->
        <div class="tab-pane fade" id="tresorerie" role="tabpanel">
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <strong>Encaissements</strong>
                        </div>
                        <?php if (empty($encaissements)): ?>
                            <div class="card-body text-muted">
                                Aucun encaissement enregistré.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Mode</th>
                                            <th class="text-end">Montant</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($encaissements as $enc): ?>
                                            <tr>
                                                <td><?= date('d/m/Y H:i', strtotime($enc['date_operation'])) ?></td>
                                                <td>
                                                    <?php 
                                                    $stmt_mode = $pdo->prepare("SELECT libelle FROM modes_paiement WHERE id = ?");
                                                    $stmt_mode->execute([$enc['mode_paiement_id'] ?? 1]);
                                                    $mode = $stmt_mode->fetch();
                                                    echo htmlspecialchars($mode['libelle'] ?? 'N/A');
                                                    ?>
                                                </td>
                                                <td class="text-end fw-bold"><?= number_format((float)$enc['montant'], 0, ',', ' ') ?> FCFA</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <strong>Écritures Comptables</strong>
                        </div>
                        <?php if (empty($ecritures)): ?>
                            <div class="card-body text-muted">
                                Aucune écriture comptable enregistrée.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Compte</th>
                                            <th class="text-end">Débit</th>
                                            <th class="text-end">Crédit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ecritures as $ecr): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($ecr['date_piece'])) ?></td>
                                                <td><?= htmlspecialchars($ecr['compte_id']) ?></td>
                                                <td class="text-end"><?= $ecr['debit'] > 0 ? number_format($ecr['debit'], 2, ',', ' ') : '-' ?></td>
                                                <td class="text-end"><?= $ecr['credit'] > 0 ? number_format($ecr['credit'], 2, ',', ' ') : '-' ?></td>
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
    </div>

    <!-- Actions disponibles -->
    <div class="mt-4 d-flex gap-2">
        <a href="<?= url_for('ventes/list.php') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Retour à la liste
        </a>
        <a href="<?= url_for('ventes/edit.php?id=' . $venteId) ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil"></i> Modifier
        </a>
        <a href="<?= url_for('coordination/ordres_preparation.php?vente_id=' . $venteId) ?>" class="btn btn-outline-success">
            <i class="bi bi-plus"></i> Nouvel ordre de préparation
        </a>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
