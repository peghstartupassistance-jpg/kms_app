<?php
// ventes/detail.php
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../lib/stock.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

// Base URL pour le module ventes (prend en compte le sous-dossier du projet)
$ventesBasePath       = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // ex: /kms_app/ventes
$ventesListUrl        = $ventesBasePath . '/list.php';
$ventesEditUrl        = $ventesBasePath . '/edit.php';
$ventesGenererBlUrl   = $ventesBasePath . '/generer_bl.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['flash_error'] = "Vente inconnue.";
    header('Location: ' . $ventesListUrl);
    exit;
}

// Récupération de la vente
$stmt = $pdo->prepare("
    SELECT
        v.*,
        c.nom AS client_nom,
        cv.code AS canal_code,
        cv.libelle AS canal_libelle,
        u.nom_complet AS commercial_nom
    FROM ventes v
    JOIN clients c ON c.id = v.client_id
    JOIN canaux_vente cv ON cv.id = v.canal_vente_id
    JOIN utilisateurs u ON u.id = v.utilisateur_id
    WHERE v.id = :id
");
$stmt->execute(['id' => $id]);
$vente = $stmt->fetch();

if (!$vente) {
    $_SESSION['flash_error'] = "Vente introuvable.";
    header('Location: ' . $ventesListUrl);
    exit;
}

// Lignes de vente
$stmt = $pdo->prepare("
    SELECT vl.*, p.code_produit, p.designation
    FROM ventes_lignes vl
    JOIN produits p ON p.id = vl.produit_id
    WHERE vl.vente_id = :id
    ORDER BY vl.id
");
$stmt->execute(['id' => $id]);
$lignes = $stmt->fetchAll();

// BL déjà générés pour cette vente
$stmt = $pdo->prepare("
    SELECT b.*
    FROM bons_livraison b
    WHERE b.vente_id = :id
    ORDER BY b.date_bl DESC, b.id DESC
");
$stmt->execute(['id' => $id]);
$bonsLivraison = $stmt->fetchAll();
// Ordres de préparation liés
$stmt = $pdo->prepare("
    SELECT op.*, u.nom_complet as commercial_nom, m.nom_complet as magasinier_nom
    FROM ordres_preparation op
    LEFT JOIN utilisateurs u ON op.commercial_responsable_id = u.id
    LEFT JOIN utilisateurs m ON op.magasinier_id = m.id
    WHERE op.vente_id = :id
    ORDER BY op.date_ordre DESC, op.id DESC
");
$stmt->execute(['id' => $id]);
$ordresPreparation = $stmt->fetchAll();
$peutModifierVente = in_array('VENTES_CREER', $_SESSION['permissions'] ?? [], true);
$peutCreerBL       = $peutModifierVente;

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Détail de la vente <?= htmlspecialchars($vente['numero']) ?></h1>
            <div class="text-muted small">
                Client : <strong><?= htmlspecialchars($vente['client_nom']) ?></strong> •
                Canal : <?= htmlspecialchars($vente['canal_code']) ?> – <?= htmlspecialchars($vente['canal_libelle']) ?> •
                Commercial : <?= htmlspecialchars($vente['commercial_nom']) ?>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars($ventesListUrl) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Retour à la liste
            </a>
            <?php if ($peutModifierVente): ?>
                <a href="<?= htmlspecialchars($ventesEditUrl . '?id=' . (int)$vente['id']) ?>"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pencil-square me-1"></i> Modifier
                </a>
            <?php endif; ?>
            <?php if ($peutModifierVente && in_array($vente['statut'], ['EN_ATTENTE_LIVRAISON','PARTIELLEMENT_LIVREE'], true)): ?>
                <a href="<?= url_for('coordination/ordres_preparation_edit.php?vente_id=' . (int)$vente['id']) ?>"
                   class="btn btn-warning btn-sm"
                   title="Créer un ordre de préparation">
                    <i class="bi bi-clipboard-check me-1"></i> Créer ordre de préparation
                </a>
            <?php elseif ($peutModifierVente && $vente['statut'] === 'LIVREE'): ?>
                <button class="btn btn-warning btn-sm" disabled title="Vente déjà livrée">
                    <i class="bi bi-clipboard-check me-1"></i> Créer ordre de préparation
                </button>
            <?php endif; ?>
            <?php if ($peutCreerBL && in_array($vente['statut'], ['EN_ATTENTE_LIVRAISON','PARTIELLEMENT_LIVREE'], true)): ?>
                <a href="<?= url_for('livraisons/create.php?vente_id=' . (int)$vente['id']) ?>"
                   class="btn btn-primary btn-sm"
                   title="Créer un bon de livraison">
                    <i class="bi bi-truck me-1"></i> Créer bon de livraison
                </a>
            <?php elseif ($peutCreerBL && $vente['statut'] === 'LIVREE'): ?>
                <button class="btn btn-primary btn-sm" disabled title="Vente déjà entièrement livrée">
                    <i class="bi bi-truck me-1"></i> Créer bon de livraison
                </button>
            <?php endif; ?>
        </div>
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

    <!-- Workflow d'aide -->
    <?php if (empty($ordresPreparation) && empty($bonsLivraison) && in_array($vente['statut'], ['EN_ATTENTE_LIVRAISON','PARTIELLEMENT_LIVREE'], true)): ?>
    <div class="alert alert-info">
        <h6 class="alert-heading">
            <i class="bi bi-info-circle"></i> Workflow de livraison
        </h6>
        <p class="mb-2"><strong>2 options pour livrer cette vente :</strong></p>
        <div class="row g-2">
            <div class="col-md-6">
                <div class="card border-warning">
                    <div class="card-body p-3">
                        <h6 class="text-warning">
                            <i class="bi bi-1-circle-fill"></i> Processus complet (recommandé)
                        </h6>
                        <ol class="mb-0 small">
                            <li>Créer un <strong>ordre de préparation</strong> (coordination avec magasin)</li>
                            <li>Le magasinier prépare les articles → Statut PRET</li>
                            <li>Créer le <strong>bon de livraison</strong> depuis l'ordre</li>
                            <li>Client signe le BL → Stock mis à jour automatiquement</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-success">
                    <div class="card-body p-3">
                        <h6 class="text-success">
                            <i class="bi bi-2-circle-fill"></i> Processus rapide
                        </h6>
                        <ol class="mb-0 small">
                            <li>Créer directement un <strong>bon de livraison</strong></li>
                            <li>Livraison physique au client</li>
                            <li>Client signe le BL → Stock mis à jour</li>
                        </ol>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-lightbulb"></i> Utilisez cette option pour les petites ventes
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Infos vente -->
    <div class="card mb-3">
        <div class="card-body row">
            <div class="col-md-3">
                <div class="text-muted small">Date de vente</div>
                <div class="fw-semibold"><?= htmlspecialchars($vente['date_vente']) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Statut</div>
                <?php
                $badgeClass = 'bg-secondary-subtle text-secondary';
                if ($vente['statut'] === 'EN_ATTENTE_LIVRAISON') $badgeClass = 'bg-warning-subtle text-warning';
                elseif ($vente['statut'] === 'PARTIELLEMENT_LIVREE') $badgeClass = 'bg-info-subtle text-info';
                elseif ($vente['statut'] === 'LIVREE') $badgeClass = 'bg-success-subtle text-success';
                elseif ($vente['statut'] === 'ANNULEE') $badgeClass = 'bg-dark-subtle text-dark';
                ?>
                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($vente['statut']) ?></span>
            </div>
            <div class="col-md-3 text-end">
                <div class="text-muted small">Montant HT</div>
                <div class="fw-semibold">
                    <?= number_format((float)$vente['montant_total_ht'], 0, ',', ' ') ?> FCFA
                </div>
            </div>
            <div class="col-md-3 text-end">
                <div class="text-muted small">Montant TTC</div>
                <div class="fw-semibold">
                    <?= number_format((float)$vente['montant_total_ttc'], 0, ',', ' ') ?> FCFA
                </div>
            </div>
            <?php if (!empty($vente['commentaires'])): ?>
                <div class="col-12 mt-2">
                    <div class="text-muted small">Commentaires</div>
                    <div><?= nl2br(htmlspecialchars($vente['commentaires'])) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lignes de vente -->
    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">Lignes de vente</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>Produit</th>
                        <th class="text-center">Qté</th>
                        <th class="text-end">PU HT</th>
                        <th class="text-end">Remise</th>
                        <th class="text-end">Montant ligne HT</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lignes as $lg): ?>
                        <tr>
                            <td>
                                <span class="fw-semibold"><?= htmlspecialchars($lg['code_produit']) ?></span><br>
                                <span class="text-muted small"><?= htmlspecialchars($lg['designation']) ?></span>
                            </td>
                            <td class="text-center"><?= (int)$lg['quantite'] ?></td>
                            <td class="text-end">
                                <?= number_format((float)$lg['prix_unitaire'], 0, ',', ' ') ?> FCFA
                            </td>
                            <td class="text-end">
                                <?= number_format((float)$lg['remise'], 0, ',', ' ') ?> FCFA
                            </td>
                            <td class="text-end">
                                <?= number_format((float)$lg['montant_ligne_ht'], 0, ',', ' ') ?> FCFA
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Ordres de préparation -->
    <?php if (!empty($ordresPreparation)): ?>
    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6 mb-3">
                <i class="bi bi-clipboard-check text-warning"></i> Ordres de préparation
                <span class="badge bg-secondary"><?= count($ordresPreparation) ?></span>
            </h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>N° Ordre</th>
                        <th>Date</th>
                        <th>Commercial</th>
                        <th>Magasinier</th>
                        <th>Priorité</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ordresPreparation as $op): ?>
                        <?php
                        $badgeClass = 'bg-secondary';
                        if ($op['statut'] === 'EN_ATTENTE') $badgeClass = 'bg-warning';
                        elseif ($op['statut'] === 'EN_PREPARATION') $badgeClass = 'bg-info';
                        elseif ($op['statut'] === 'PRET') $badgeClass = 'bg-success';
                        elseif ($op['statut'] === 'LIVRE') $badgeClass = 'bg-primary';
                        elseif ($op['statut'] === 'ANNULE') $badgeClass = 'bg-danger';
                        ?>
                        <tr>
                            <td>
                                <a href="<?= url_for('coordination/ordres_preparation_edit.php?id=' . (int)$op['id']) ?>">
                                    <strong><?= htmlspecialchars($op['numero_ordre']) ?></strong>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($op['date_ordre']) ?></td>
                            <td><?= htmlspecialchars($op['commercial_nom'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($op['magasinier_nom'] ?? 'Non assigné') ?></td>
                            <td>
                                <span class="badge bg-<?= $op['priorite'] === 'TRES_URGENTE' ? 'danger' : ($op['priorite'] === 'URGENTE' ? 'warning' : 'secondary') ?>">
                                    <?= htmlspecialchars($op['priorite']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($op['statut']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if ($op['statut'] === 'PRET' && $peutCreerBL): ?>
                                    <a href="<?= url_for('livraisons/create.php?ordre_id=' . (int)$op['id'] . '&vente_id=' . (int)$vente['id']) ?>"
                                       class="btn btn-sm btn-success"
                                       title="Créer BL depuis cet ordre">
                                        <i class="bi bi-truck"></i> Créer BL
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bons de livraison associés -->
    <div class="card">
        <div class="card-body">
            <h2 class="h6 mb-3">
                <i class="bi bi-truck text-primary"></i> Bons de livraison liés
                <span class="badge bg-secondary"><?= count($bonsLivraison) ?></span>
            </h2>
            <?php if (empty($bonsLivraison)): ?>
                <p class="text-muted mb-0">Aucun bon de livraison généré pour cette vente.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>N° BL</th>
                            <th>Date</th>
                            <th>Transport</th>
                            <th>Signé client</th>
                            <th>Magasinier</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($bonsLivraison as $bl): ?>
                            <tr>
                                <td><?= htmlspecialchars($bl['numero']) ?></td>
                                <td><?= htmlspecialchars($bl['date_bl']) ?></td>
                                <td><?= htmlspecialchars($bl['transport_assure_par'] ?? '') ?></td>
                                <td>
                                    <?php if ((int)$bl['signe_client'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success">
                                            <i class="bi bi-check2-circle me-1"></i> Signé
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning">
                                            Non signé
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$bl['magasinier_id'] ?></td>
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
