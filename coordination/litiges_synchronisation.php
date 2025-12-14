<?php
// coordination/litiges_synchronisation.php
// Visualisation complète de la synchronisation stock + caisse + compta pour un litige

require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');
require_once __DIR__ . '/../lib/litiges.php';

global $pdo;

$litige_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$litige_id) {
    $_SESSION['flash_error'] = "ID litige requis";
    header('Location: ' . url_for('coordination/litiges.php'));
    exit;
}

$data = litiges_charger_complet($pdo, $litige_id);
if (!$data) {
    $_SESSION['flash_error'] = "Litige introuvable";
    header('Location: ' . url_for('coordination/litiges.php'));
    exit;
}

$litige = $data['litige'];
$mouvements_stock = $data['stock'];
$operations_caisse = $data['caisse'];
$ecritures_compta = $data['compta'];

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="mb-3">
        <a href="<?= url_for('coordination/litiges.php') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-chevron-left"></i> Retour
        </a>
    </div>

    <h1 class="h4 mb-4">
        <i class="bi bi-diagram-3"></i> Synchronisation litige #<?= $litige['id'] ?>
    </h1>

    <!-- Résumé du litige -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-light">
                    <strong>Informations litige</strong>
                </div>
                <div class="card-body small">
                    <div class="row mb-2">
                        <div class="col-4"><strong>Client :</strong></div>
                        <div class="col-8"><?= htmlspecialchars($litige['client_nom']) ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><strong>Produit :</strong></div>
                        <div class="col-8"><?= htmlspecialchars($litige['code_produit'] . ' - ' . $litige['designation']) ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><strong>Motif :</strong></div>
                        <div class="col-8"><?= htmlspecialchars($litige['motif']) ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><strong>Statut :</strong></div>
                        <div class="col-8">
                            <span class="badge bg-primary"><?= htmlspecialchars($litige['statut_traitement']) ?></span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-4"><strong>Date retour :</strong></div>
                        <div class="col-8"><?= date('d/m/Y', strtotime($litige['date_retour'])) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-success">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-check-circle"></i> Résolution</strong>
                </div>
                <div class="card-body small">
                    <div class="row mb-2">
                        <div class="col-5"><strong>Solution :</strong></div>
                        <div class="col-7"><?= htmlspecialchars($litige['solution'] ?? '(Non renseignée)') ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Remboursement :</strong></div>
                        <div class="col-7"><?= number_format($litige['montant_rembourse'] ?? 0, 0, ',', ' ') ?> FCFA</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5"><strong>Avoir :</strong></div>
                        <div class="col-7"><?= number_format($litige['montant_avoir'] ?? 0, 0, ',', ' ') ?> FCFA</div>
                    </div>
                    <div class="row">
                        <div class="col-5"><strong>Résolu le :</strong></div>
                        <div class="col-7">
                            <?= $litige['date_resolution'] ? date('d/m/Y H:i', strtotime($litige['date_resolution'])) : '-' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Synchronisation : Stock -->
    <div class="card mb-3">
        <div class="card-header bg-info text-white">
            <strong><i class="bi bi-box2-heart"></i> Synchronisation STOCK</strong>
        </div>
        <div class="card-body">
            <?php if (empty($mouvements_stock)): ?>
                <div class="alert alert-info m-0">
                    <i class="bi bi-info-circle"></i> Aucun mouvement de stock enregistré pour ce litige
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Quantité</th>
                                <th>Raison</th>
                                <th>Montant stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mouvements_stock as $mv): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($mv['date_mouvement'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $mv['type_mouvement'] === 'ENTREE' ? 'success' : 'danger' ?>">
                                            <?= $mv['type_mouvement'] ?>
                                        </span>
                                    </td>
                                    <td class="fw-semibold"><?= number_format($mv['quantite'], 0) ?></td>
                                    <td><?= htmlspecialchars(substr($mv['raison'], 0, 100)) ?></td>
                                    <td><?= number_format($mv['montant_mouvement'] ?? 0, 2, ',', ' ') ?> FCFA</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Synchronisation : Caisse -->
    <div class="card mb-3">
        <div class="card-header bg-success text-white">
            <strong><i class="bi bi-cash-coin"></i> Synchronisation CAISSE</strong>
        </div>
        <div class="card-body">
            <?php if (empty($operations_caisse)): ?>
                <div class="alert alert-info m-0">
                    <i class="bi bi-info-circle"></i> Aucune opération caisse enregistrée pour ce litige
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Type opération</th>
                                <th>Description</th>
                                <th class="text-end">Débit</th>
                                <th class="text-end">Crédit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($operations_caisse as $op): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($op['date_operation'])) ?></td>
                                    <td><?= htmlspecialchars($op['type_operation']) ?></td>
                                    <td><?= htmlspecialchars(substr($op['libelle'], 0, 80)) ?></td>
                                    <td class="text-end"><?= $op['sens'] === 'SORTIE' ? number_format($op['montant'], 2, ',', ' ') : '-' ?> FCFA</td>
                                    <td class="text-end"><?= $op['sens'] === 'ENTREE' ? number_format($op['montant'], 2, ',', ' ') : '-' ?> FCFA</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Synchronisation : Comptabilité -->
    <div class="card mb-3">
        <div class="card-header bg-warning text-dark">
            <strong><i class="bi bi-calculator"></i> Synchronisation COMPTABILITÉ</strong>
        </div>
        <div class="card-body">
            <?php if (empty($ecritures_compta)): ?>
                <div class="alert alert-info m-0">
                    <i class="bi bi-info-circle"></i> Aucune écriture comptable enregistrée pour ce litige
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Pièce</th>
                                <th>Date</th>
                                <th>Compte</th>
                                <th>Libellé</th>
                                <th class="text-end">Débit</th>
                                <th class="text-end">Crédit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ecritures_compta as $ec): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($ec['numero_piece']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($ec['date_ecriture'])) ?></td>
                                    <td><code><?= htmlspecialchars($ec['compte']) ?></code></td>
                                    <td><?= htmlspecialchars(substr($ec['libelle'], 0, 50)) ?></td>
                                    <td class="text-end"><?= $ec['sens'] === 'D' ? number_format($ec['montant'], 2, ',', ' ') : '-' ?> FCFA</td>
                                    <td class="text-end"><?= $ec['sens'] === 'C' ? number_format($ec['montant'], 2, ',', ' ') : '-' ?> FCFA</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Résumé de cohérence -->
    <div class="card border-secondary">
        <div class="card-header bg-secondary text-white">
            <strong><i class="bi bi-shield-check"></i> Vérification de cohérence</strong>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="d-flex align-items-start">
                        <?php 
                        $coherent_stock = !empty($mouvements_stock) || $litige['montant_rembourse'] == 0;
                        $icon = $coherent_stock ? 'bi-check-circle text-success' : 'bi-exclamation-circle text-warning';
                        ?>
                        <i class="bi <?= $icon ?> me-2 fs-5"></i>
                        <div>
                            <strong>Stock</strong>
                            <br>
                            <small class="text-muted">
                                <?php if (empty($mouvements_stock)): ?>
                                    Aucun mouvement (litige non retourné)
                                <?php else: ?>
                                    <?= count($mouvements_stock) ?> mouvement(s) enregistré(s)
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-start">
                        <?php 
                        $coherent_caisse = ($litige['montant_rembourse'] > 0 && !empty($operations_caisse)) || ($litige['montant_rembourse'] == 0);
                        $icon = $coherent_caisse ? 'bi-check-circle text-success' : 'bi-exclamation-circle text-warning';
                        ?>
                        <i class="bi <?= $icon ?> me-2 fs-5"></i>
                        <div>
                            <strong>Caisse</strong>
                            <br>
                            <small class="text-muted">
                                <?php if ($litige['montant_rembourse'] > 0): ?>
                                    Remboursement : <?= number_format($litige['montant_rembourse'], 0, ',', ' ') ?> FCFA
                                <?php else: ?>
                                    Aucun remboursement
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-start">
                        <?php 
                        $coherent_compta = ($litige['montant_rembourse'] > 0 && !empty($ecritures_compta)) || 
                                           ($litige['montant_avoir'] > 0 && !empty($ecritures_compta)) ||
                                           ($litige['montant_rembourse'] == 0 && $litige['montant_avoir'] == 0);
                        $icon = $coherent_compta ? 'bi-check-circle text-success' : 'bi-exclamation-circle text-warning';
                        ?>
                        <i class="bi <?= $icon ?> me-2 fs-5"></i>
                        <div>
                            <strong>Comptabilité</strong>
                            <br>
                            <small class="text-muted">
                                <?php if (!empty($ecritures_compta)): ?>
                                    <?= count($ecritures_compta) ?> écriture(s) enregistrée(s)
                                <?php else: ?>
                                    Aucune écriture
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
