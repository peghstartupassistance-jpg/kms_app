<?php
// livraisons/detail.php - Détail d'un bon de livraison avec navigation
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

$id = $_GET['id'] ?? null;

if (!$id) {
    $_SESSION['flash_error'] = "Bon de livraison non spécifié";
    header('Location: ' . url_for('livraisons/list.php'));
    exit;
}

// Charger le BL
$stmt = $pdo->prepare("
    SELECT bl.*, 
           c.nom as client_nom, c.adresse as client_adresse, c.telephone as client_telephone,
           v.numero as vente_numero, v.date_vente, v.montant_total_ttc as vente_montant,
           op.numero_ordre as ordre_numero, op.id as ordre_id,
           u_mag.nom_complet as magasinier_nom,
           u_liv.nom_complet as livreur_nom
    FROM bons_livraison bl
    JOIN clients c ON c.id = bl.client_id
    LEFT JOIN ventes v ON v.id = bl.vente_id
    LEFT JOIN ordres_preparation op ON op.id = bl.ordre_preparation_id
    LEFT JOIN utilisateurs u_mag ON u_mag.id = bl.magasinier_id
    LEFT JOIN utilisateurs u_liv ON u_liv.id = bl.livreur_id
    WHERE bl.id = ?
");
$stmt->execute([$id]);
$bl = $stmt->fetch();

if (!$bl) {
    $_SESSION['flash_error'] = "Bon de livraison introuvable";
    header('Location: ' . url_for('livraisons/list.php'));
    exit;
}

// Charger les lignes
$stmt = $pdo->prepare("
    SELECT bll.*, p.code_produit, p.stock_actuel, p.designation, p.prix_vente as prix_unitaire
    FROM bons_livraison_lignes bll
    LEFT JOIN produits p ON p.id = bll.produit_id
    WHERE bll.bon_livraison_id = ?
");
$stmt->execute([$id]);
$lignes = $stmt->fetchAll();

// Charger les mouvements de stock associés (via la vente liée au BL)
$stmt = $pdo->prepare("
    SELECT sm.*, p.designation as produit_nom, p.code_produit, u.nom_complet as utilisateur_nom
    FROM stocks_mouvements sm
    LEFT JOIN produits p ON p.id = sm.produit_id
    LEFT JOIN utilisateurs u ON u.id = sm.utilisateur_id
    WHERE sm.source_type = 'VENTE' 
      AND sm.source_id = ?
      AND sm.commentaire LIKE CONCAT('%BL-', ?)
    ORDER BY sm.date_mouvement DESC
");
$stmt->execute([$bl['vente_id'], $bl['numero']]);
$mouvements_stock = $stmt->fetchAll();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid p-4">
    <!-- Header avec navigation -->
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="bi bi-truck"></i> Bon de livraison <?= htmlspecialchars($bl['numero']) ?>
            </h1>
            <div class="d-flex gap-2 flex-wrap mt-2">
                <?php if ($bl['vente_id']): ?>
                    <a href="<?= url_for('ventes/edit.php?id=' . $bl['vente_id']) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-receipt"></i> Voir la vente <?= htmlspecialchars($bl['vente_numero']) ?>
                    </a>
                <?php endif; ?>
                
                <?php if ($bl['ordre_id']): ?>
                    <a href="<?= url_for('coordination/ordres_preparation_edit.php?id=' . $bl['ordre_id']) ?>" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-box-seam"></i> Voir l'ordre <?= htmlspecialchars($bl['ordre_numero']) ?>
                    </a>
                <?php endif; ?>
                
                <a href="<?= url_for('stock/mouvements.php?bon_livraison_id=' . $id) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left-right"></i> Voir mouvements stock
                </a>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if ($bl['statut'] !== 'ANNULE' && !$bl['signe_client']): ?>
                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalSignatureBL" title="Obtenir la signature du client">
                    <i class="bi bi-pen-fill"></i> Obtenir signature
                </button>
            <?php endif; ?>
            <a href="<?= url_for('livraisons/print.php?id=' . $id) ?>" class="btn btn-primary btn-sm" target="_blank">
                <i class="bi bi-printer"></i> Imprimer
            </a>
            <a href="<?= url_for('livraisons/list.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Retour liste
            </a>
        </div>
    </div>

    <!-- Informations principales -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <small class="text-muted">Date BL</small>
                    <div class="fw-bold"><?= date('d/m/Y', strtotime($bl['date_bl'])) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <small class="text-muted">Statut</small>
                    <div>
                        <?php
                        $badge = match($bl['statut']) {
                            'EN_PREPARATION' => 'secondary',
                            'PRET' => 'info',
                            'EN_COURS_LIVRAISON' => 'warning',
                            'LIVRE' => 'success',
                            'ANNULE' => 'danger',
                            default => 'light'
                        };
                        ?>
                        <span class="badge bg-<?= $badge ?>"><?= $bl['statut'] ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <small class="text-muted">Livreur</small>
                    <div><?= htmlspecialchars($bl['livreur_nom'] ?? 'Non assigné') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <small class="text-muted">Transport</small>
                    <div><?= htmlspecialchars($bl['transport_assure_par'] ?: 'Non précisé') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Client -->
    <div class="card mb-3">
        <div class="card-header">
            <i class="bi bi-person"></i> Client
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <strong>Nom :</strong> <?= htmlspecialchars($bl['client_nom']) ?>
                </div>
                <div class="col-md-4">
                    <strong>Téléphone :</strong> <?= htmlspecialchars($bl['client_telephone'] ?: 'N/A') ?>
                </div>
                <div class="col-md-4">
                    <strong>Adresse :</strong> <?= htmlspecialchars($bl['client_adresse'] ?: 'N/A') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Articles livrés -->
    <div class="card mb-3">
        <div class="card-header">
            <i class="bi bi-box"></i> Articles livrés
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Désignation</th>
                            <th class="text-end">Qté livrée</th>
                            <th class="text-end">Qté commandée</th>
                            <th class="text-end">Reste à livrer</th>
                            <th class="text-end">Prix unitaire</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_ht = 0;
                        foreach ($lignes as $ligne): 
                            $ligne_total = $ligne['quantite'] * $ligne['prix_unitaire'];
                            $total_ht += $ligne_total;
                        ?>
                            <tr>
                                <td><code><?= htmlspecialchars($ligne['code_produit'] ?? 'N/A') ?></code></td>
                                <td><?= htmlspecialchars($ligne['designation']) ?></td>
                                <td class="text-end"><strong><?= number_format($ligne['quantite'], 2) ?></strong></td>
                                <td class="text-end"><?= number_format($ligne['quantite_commandee'], 2) ?></td>
                                <td class="text-end">
                                    <?php if ($ligne['quantite_restante'] > 0): ?>
                                        <span class="badge bg-warning"><?= number_format($ligne['quantite_restante'], 2) ?></span>
                                    <?php else: ?>
                                        <span class="text-success">✓ Complet</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= number_format($ligne['prix_unitaire'], 0, ',', ' ') ?> F</td>
                                <td class="text-end"><?= number_format($ligne_total, 0, ',', ' ') ?> F</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="6" class="text-end">Total HT</th>
                            <th class="text-end"><?= number_format($total_ht, 0, ',', ' ') ?> FCFA</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Mouvements de stock associés -->
    <?php if (count($mouvements_stock) > 0): ?>
        <div class="card mb-3">
            <div class="card-header">
                <i class="bi bi-arrow-left-right"></i> Mouvements de stock liés
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Produit</th>
                                <th>Type</th>
                                <th class="text-end">Quantité</th>
                                <th>Utilisateur</th>
                                <th>Commentaire</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mouvements_stock as $mvt): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($mvt['date_mouvement'])) ?></td>
                                    <td>
                                        <code><?= htmlspecialchars($mvt['code_produit'] ?? 'N/A') ?></code>
                                        <?= htmlspecialchars($mvt['produit_nom']) ?>
                                    </td>
                                    <td><span class="badge bg-danger"><?= $mvt['type_mouvement'] ?></span></td>
                                    <td class="text-end text-danger"><?= number_format($mvt['quantite'], 2) ?></td>
                                    <td><?= htmlspecialchars($mvt['utilisateur_nom'] ?? 'Système') ?></td>
                                    <td class="small"><?= htmlspecialchars($mvt['commentaire'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Observations -->
    <?php if ($bl['observations']): ?>
        <div class="card">
            <div class="card-header">
                <i class="bi bi-chat-left-text"></i> Observations
            </div>
            <div class="card-body">
                <?= nl2br(htmlspecialchars($bl['observations'])) ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- Section Signature Client -->
<?php if ($bl['signe_client']): ?>
    <div class="container-fluid p-4">
        <div class="card mt-4 border-success">
            <div class="card-header bg-success text-white">
                <i class="bi bi-check-circle-fill"></i> Statut Signature Client
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <span class="badge bg-success me-2"><i class="bi bi-check-circle-fill"></i> Document signé par le client</span>
                        <p class="text-muted small mt-2">Cette livraison a été acceptée et signée par le client.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modal Signature -->
<?php include __DIR__ . '/modal_signature.php'; ?>

<!-- Script Signature Handler -->
<script src="<?= url_for('assets/js/signature-handler.js') ?>"></script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
