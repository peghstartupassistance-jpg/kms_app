<?php
// stock/ajustement.php - Ajustement manuel du stock (inventaire, correction)
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('STOCK_ECRIRE');

global $pdo;
require_once __DIR__ . '/../lib/stock.php';

$produit_id = $_GET['produit_id'] ?? null;
$produit = null;

if ($produit_id) {
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
    $stmt->execute([$produit_id]);
    $produit = $stmt->fetch();
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    verifierCsrf($csrf);
    
    $produit_id = $_POST['produit_id'] ?? null;
    $nouveau_stock = $_POST['nouveau_stock'] ?? null;
    $motif = $_POST['motif'] ?? '';
    
    if (empty($produit_id) || $nouveau_stock === null || $nouveau_stock === '') {
        $_SESSION['flash_error'] = "Tous les champs obligatoires doivent être remplis";
    } else {
        try {
            // Récupérer le produit
            $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
            $stmt->execute([$produit_id]);
            $produit = $stmt->fetch();
            
            if (!$produit) {
                throw new Exception("Produit introuvable");
            }
            
            $stock_actuel = $produit['stock_actuel'];
            $nouveau_stock = floatval($nouveau_stock);
            $ecart = $nouveau_stock - $stock_actuel;
            
            if ($ecart != 0) {
                // Enregistrer l'ajustement
                enregistrerMouvement(
                    $pdo,
                    $produit_id,
                    'AJUSTEMENT',
                    abs($ecart),
                    'AJUSTEMENT',
                    null,
                    $motif,
                    $_SESSION['user_id']
                );
                
                $_SESSION['flash_success'] = "Stock ajusté : {$stock_actuel} → {$nouveau_stock} (" . ($ecart > 0 ? '+' : '') . "{$ecart})";
            } else {
                $_SESSION['flash_info'] = "Aucun changement (stock déjà à {$nouveau_stock})";
            }
            
            header('Location: ' . url_for('stock/ajustement.php'));
            exit;
            
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Erreur : " . $e->getMessage();
        }
    }
}

// Recherche produits
$recherche = $_GET['recherche'] ?? '';
$produits = [];
if ($recherche) {
    $stmt = $pdo->prepare("
        SELECT p.*, f.nom as famille_nom 
        FROM produits p
        LEFT JOIN familles_produits f ON p.famille_id = f.id
        WHERE p.actif = 1 
          AND (p.code_produit LIKE ? OR p.designation LIKE ?)
        ORDER BY p.designation
        LIMIT 20
    ");
    $stmt->execute(["%$recherche%", "%$recherche%"]);
    $produits = $stmt->fetchAll();
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            <i class="bi bi-pencil-square"></i> Ajustement Stock (Inventaire)
        </h1>
        <div class="btn-group">
            <a href="<?= url_for('magasin/dashboard.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
            <a href="<?= url_for('stock/mouvements.php') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-list"></i> Mouvements
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <!-- Recherche produit -->
            <div class="card mb-3">
                <div class="card-header">
                    <i class="bi bi-search"></i> Rechercher un produit
                </div>
                <div class="card-body">
                    <form method="get" class="mb-3">
                        <div class="input-group">
                            <input type="text" name="recherche" class="form-control" 
                                   placeholder="Code produit ou désignation..." 
                                   value="<?= htmlspecialchars($recherche) ?>"
                                   autofocus>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Chercher
                            </button>
                        </div>
                    </form>

                    <?php if ($recherche && !empty($produits)): ?>
                        <div class="list-group">
                            <?php foreach ($produits as $p): ?>
                                <a href="?produit_id=<?= $p['id'] ?>" class="list-group-item list-group-item-action <?= $produit_id == $p['id'] ? 'active' : '' ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= htmlspecialchars($p['code_produit']) ?></strong> - 
                                            <?= htmlspecialchars($p['designation']) ?>
                                            <div><small class="<?= $produit_id == $p['id'] ? 'text-white-50' : 'text-muted' ?>"><?= htmlspecialchars($p['famille_nom'] ?? '-') ?></small></div>
                                        </div>
                                        <span class="badge bg-<?= $p['stock_actuel'] <= $p['seuil_alerte'] ? 'warning' : 'success' ?>">
                                            Stock: <?= number_format($p['stock_actuel'], 0, ',', ' ') ?>
                                        </span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($recherche): ?>
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-info-circle"></i> Aucun produit trouvé
                        </div>
                    <?php else: ?>
                        <div class="text-muted text-center py-3">
                            <i class="bi bi-arrow-up"></i> Entrez un code ou une désignation
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <?php if ($produit): ?>
                <!-- Formulaire ajustement -->
                <div class="card">
                    <div class="card-header bg-warning text-white">
                        <i class="bi bi-pencil-square"></i> Ajuster le stock
                    </div>
                    <div class="card-body">
                        <!-- Infos produit -->
                        <div class="mb-3 p-3 bg-light rounded">
                            <h5><?= htmlspecialchars($produit['code_produit']) ?></h5>
                            <p class="mb-2"><?= htmlspecialchars($produit['designation']) ?></p>
                            <div class="row">
                                <div class="col-6">
                                    <strong>Stock actuel:</strong>
                                </div>
                                <div class="col-6 text-end">
                                    <span class="badge bg-primary fs-6">
                                        <?= number_format($produit['stock_actuel'], 2, ',', ' ') ?>
                                    </span>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-6">
                                    <strong>Seuil alerte:</strong>
                                </div>
                                <div class="col-6 text-end">
                                    <?= number_format($produit['seuil_alerte'], 0, ',', ' ') ?>
                                </div>
                            </div>
                        </div>

                        <form method="post" id="formAjustement">
                            <input type="hidden" name="csrf_token" value="<?= genererCsrf() ?>">
                            <input type="hidden" name="produit_id" value="<?= $produit['id'] ?>">

                            <div class="mb-3">
                                <label class="form-label">Nouveau stock <span class="text-danger">*</span></label>
                                <input type="number" name="nouveau_stock" class="form-control form-control-lg" 
                                       step="0.01" required autofocus
                                       placeholder="Ex: <?= $produit['stock_actuel'] ?>">
                                <small class="text-muted">Stock actuel: <?= number_format($produit['stock_actuel'], 2, ',', ' ') ?></small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Motif de l'ajustement <span class="text-danger">*</span></label>
                                <select name="motif" class="form-select" required onchange="if(this.value==='AUTRE') document.getElementById('motifAutre').style.display='block'; else document.getElementById('motifAutre').style.display='none';">
                                    <option value="">-- Sélectionner --</option>
                                    <option value="INVENTAIRE">Inventaire physique</option>
                                    <option value="CORRECTION_ERREUR">Correction d'erreur</option>
                                    <option value="CASSE">Produit cassé/endommagé</option>
                                    <option value="PERTE">Perte/Vol</option>
                                    <option value="PEREMPTION">Péremption</option>
                                    <option value="AUTRE">Autre motif</option>
                                </select>
                            </div>

                            <div class="mb-3" id="motifAutre" style="display:none;">
                                <label class="form-label">Précisez le motif</label>
                                <textarea name="motif_autre" class="form-control" rows="2" placeholder="Détails..."></textarea>
                            </div>

                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Attention:</strong> Cet ajustement sera tracé et visible dans l'historique des mouvements.
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-warning flex-fill">
                                    <i class="bi bi-check-circle"></i> Ajuster le stock
                                </button>
                                <a href="<?= url_for('stock/ajustement.php') ?>" class="btn btn-secondary">
                                    Annuler
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-box-seam fs-1 text-muted"></i>
                        <p class="text-muted mt-3">Sélectionnez un produit pour ajuster son stock</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Calcul automatique de l'écart
document.addEventListener('DOMContentLoaded', function() {
    const input = document.querySelector('input[name="nouveau_stock"]');
    const stockActuel = <?= $produit ? $produit['stock_actuel'] : 0 ?>;
    
    if (input) {
        input.addEventListener('input', function() {
            const nouveau = parseFloat(this.value) || 0;
            const ecart = nouveau - stockActuel;
            
            // Afficher l'écart
            let ecartDiv = document.getElementById('ecartInfo');
            if (!ecartDiv) {
                ecartDiv = document.createElement('div');
                ecartDiv.id = 'ecartInfo';
                ecartDiv.className = 'alert mt-2';
                this.parentElement.appendChild(ecartDiv);
            }
            
            if (ecart > 0) {
                ecartDiv.className = 'alert alert-success mt-2';
                ecartDiv.innerHTML = '<strong>Écart: +' + ecart.toFixed(2) + '</strong> (ajout au stock)';
            } else if (ecart < 0) {
                ecartDiv.className = 'alert alert-danger mt-2';
                ecartDiv.innerHTML = '<strong>Écart: ' + ecart.toFixed(2) + '</strong> (retrait du stock)';
            } else {
                ecartDiv.style.display = 'none';
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
