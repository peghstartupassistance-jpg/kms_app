<?php
// ventes/detail.php
require_once __DIR__ . '/../security.php';
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
            <?php if ($peutCreerBL && in_array($vente['statut'], ['EN_ATTENTE_LIVRAISON','PARTIELLEMENT_LIVREE'], true)): ?>
                <form method="post" action="<?= htmlspecialchars($ventesGenererBlUrl) ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                    <input type="hidden" name="vente_id" value="<?= (int)$vente['id'] ?>">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-truck me-1"></i> Générer un bon de livraison
                    </button>
                </form>
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

    <!-- Bons de livraison associés -->
    <div class="card">
        <div class="card-body">
            <h2 class="h6 mb-3">Bons de livraison liés</h2>
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
