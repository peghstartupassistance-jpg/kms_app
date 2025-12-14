<?php
// livraisons/create.php - Créer un bon de livraison (partiel ou total)
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_MODIFIER');

global $pdo;

$vente_id = $_GET['vente_id'] ?? null;
$ordre_id = $_GET['ordre_id'] ?? null;

if (!$vente_id) {
    $_SESSION['flash_error'] = "Vente non spécifiée";
    header('Location: ' . url_for('ventes/list.php'));
    exit;
}

// Charger la vente
$stmt = $pdo->prepare("
    SELECT v.*, c.nom as client_nom, c.adresse as client_adresse, c.telephone as client_telephone
    FROM ventes v
    JOIN clients c ON c.id = v.client_id
    WHERE v.id = ?
");
$stmt->execute([$vente_id]);
$vente = $stmt->fetch();

if (!$vente) {
    $_SESSION['flash_error'] = "Vente introuvable";
    header('Location: ' . url_for('ventes/list.php'));
    exit;
}

// Vérifier que la vente peut encore être livrée
if ($vente['statut'] === 'LIVREE') {
    $_SESSION['flash_error'] = "Cette vente est déjà entièrement livrée. Impossible de créer un nouveau bon de livraison.";
    header('Location: ' . url_for('ventes/detail.php?id=' . (int)$vente_id));
    exit;
}

if ($vente['statut'] === 'ANNULEE') {
    $_SESSION['flash_error'] = "Cette vente est annulée. Impossible de créer un bon de livraison.";
    header('Location: ' . url_for('ventes/detail.php?id=' . (int)$vente_id));
    exit;
}

// Charger les lignes de la vente
$stmt = $pdo->prepare("
    SELECT vl.*, p.designation, p.code_produit, p.stock_actuel
    FROM ventes_lignes vl
    JOIN produits p ON p.id = vl.produit_id
    WHERE vl.vente_id = ?
");
$stmt->execute([$vente_id]);
$lignes = $stmt->fetchAll();

// Calculer les quantités déjà livrées
foreach ($lignes as &$ligne) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(bll.quantite), 0) as qte_livree
        FROM bons_livraison_lignes bll
        JOIN bons_livraison bl ON bl.id = bll.bon_livraison_id
        WHERE bll.produit_id = ? AND bl.vente_id = ?
    ");
    $stmt->execute([$ligne['produit_id'], $vente_id]);
    $result = $stmt->fetch();
    $ligne['qte_livree'] = $result['qte_livree'];
    $ligne['qte_restante'] = $ligne['quantite'] - $ligne['qte_livree'];
}
unset($ligne);

// Charger l'ordre de préparation si fourni
$ordre = null;
if ($ordre_id) {
    $stmt = $pdo->prepare("SELECT * FROM ordres_preparation WHERE id = ?");
    $stmt->execute([$ordre_id]);
    $ordre = $stmt->fetch();
}

// Liste des utilisateurs pour livreur
$stmt = $pdo->query("SELECT id, nom_complet FROM utilisateurs WHERE actif = 1 ORDER BY nom_complet");
$utilisateurs = $stmt->fetchAll();

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf($_POST['csrf_token'] ?? '');
    
    $livreur_id = $_POST['livreur_id'] ?? null;
    $date_livraison = $_POST['date_livraison'] ?? date('Y-m-d');
    $transport = $_POST['transport_assure_par'] ?? '';
    $observations = $_POST['observations'] ?? '';
    $quantites = $_POST['quantites'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Générer numéro BL
        $dateNow = date('Ymd');
        $stmtCount = $pdo->query("SELECT COUNT(*) FROM bons_livraison WHERE DATE(date_bl) = CURDATE()");
        $count = $stmtCount->fetchColumn() + 1;
        $numero_bl = "BL-$dateNow-" . str_pad($count, 4, '0', STR_PAD_LEFT);
        
        // Créer le bon de livraison
        $stmt = $pdo->prepare("
            INSERT INTO bons_livraison 
            (numero, date_bl, vente_id, ordre_preparation_id, client_id, transport_assure_par, 
             observations, magasinier_id, livreur_id, date_livraison_effective, statut, signe_client)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'EN_COURS_LIVRAISON', 0)
        ");
        
        $utilisateur = utilisateurConnecte();
        $stmt->execute([
            $numero_bl,
            $date_livraison,
            $vente_id,
            $ordre_id,
            $vente['client_id'],
            $transport,
            $observations,
            $utilisateur['id'],
            $livreur_id,
            date('Y-m-d H:i:s')
        ]);
        
        $bl_id = $pdo->lastInsertId();
        
        // Ajouter les lignes livrées
        $total_lignes = 0;
        $livraison_partielle = false;
        
        foreach ($quantites as $produit_id => $qte_livree) {
            $qte_livree = (float)$qte_livree;
            if ($qte_livree <= 0) continue;
            
            // Trouver la ligne correspondante
            $ligneVente = null;
            foreach ($lignes as $l) {
                if ($l['produit_id'] == $produit_id) {
                    $ligneVente = $l;
                    break;
                }
            }
            
            if (!$ligneVente) continue;
            
            // Vérifier qu'on ne livre pas plus que commandé
            if ($qte_livree > $ligneVente['qte_restante']) {
                throw new Exception("Quantité à livrer supérieure à la quantité restante pour " . $ligneVente['designation']);
            }
            
            // Insérer ligne BL
            $stmt = $pdo->prepare("
                INSERT INTO bons_livraison_lignes 
                (bon_livraison_id, produit_id, designation, quantite, quantite_commandee, quantite_restante, prix_unitaire)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $bl_id,
                $produit_id,
                $ligneVente['designation'],
                $qte_livree,
                $ligneVente['quantite'],
                $ligneVente['qte_restante'] - $qte_livree,
                $ligneVente['prix_unitaire']
            ]);
            
            $total_lignes++;
            
            // Vérifier si livraison partielle
            if ($qte_livree < $ligneVente['qte_restante']) {
                $livraison_partielle = true;
            }
            
            // Déstockage (sortie magasin)
            require_once __DIR__ . '/../lib/stock.php';
            ajouterMouvement(
                $pdo,
                $produit_id,
                'SORTIE_VENTE',
                -$qte_livree,
                "Livraison $numero_bl (Vente " . $vente['numero'] . ")",
                $utilisateur['id'],
                null,
                $bl_id
            );
        }
        
        if ($total_lignes === 0) {
            throw new Exception("Aucun article à livrer");
        }
        
        // Mettre à jour le statut de la vente
        $nouveauStatut = $livraison_partielle ? 'PARTIELLEMENT_LIVREE' : 'LIVREE';
        
        $stmt = $pdo->prepare("UPDATE ventes SET statut = ? WHERE id = ?");
        $stmt->execute([$nouveauStatut, $vente_id]);
        
        // Mettre à jour le statut de l'ordre si présent
        if ($ordre_id) {
            $stmt = $pdo->prepare("UPDATE ordres_preparation SET statut = 'LIVRE', date_preparation_effectuee = NOW() WHERE id = ?");
            $stmt->execute([$ordre_id]);
        }
        
        $pdo->commit();
        
        $_SESSION['flash_success'] = "Bon de livraison $numero_bl créé avec succès" . ($livraison_partielle ? " (livraison partielle)" : "");
        header('Location: ' . url_for('livraisons/detail.php?id=' . $bl_id));
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = "Erreur : " . $e->getMessage();
    }
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid p-4">
    <div class="form-page-header">
        <h1 class="h4 mb-0">
            <i class="bi bi-truck"></i> Nouveau bon de livraison
        </h1>
        <a href="<?= url_for('ventes/edit.php?id=' . $vente_id) ?>" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Retour à la vente
        </a>
    </div>

    <!-- Infos vente -->
    <div class="row g-3 mb-3">
        <?php if ($ordre): ?>
        <div class="col-12">
            <div class="alert alert-success d-flex align-items-center">
                <i class="bi bi-clipboard-check fs-4 me-3"></i>
                <div>
                    <strong>Ordre de préparation :</strong> 
                    <a href="<?= url_for('coordination/ordres_preparation_edit.php?id=' . (int)$ordre['id']) ?>" class="alert-link">
                        <?= htmlspecialchars($ordre['numero_ordre']) ?>
                    </a>
                    <span class="badge bg-success ms-2"><?= htmlspecialchars($ordre['statut']) ?></span>
                    <br>
                    <small class="text-muted">Ce bon de livraison est créé depuis cet ordre de préparation</small>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <small class="text-muted">Vente</small>
                    <div class="fw-bold">
                        <a href="<?= url_for('ventes/detail.php?id=' . (int)$vente_id) ?>">
                            <?= htmlspecialchars($vente['numero']) ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <small class="text-muted">Client</small>
                    <div class="fw-bold"><?= htmlspecialchars($vente['client_nom']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <small class="text-muted">Date vente</small>
                    <div><?= date('d/m/Y', strtotime($vente['date_vente'])) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <small class="text-muted">Statut</small>
                    <div><span class="badge bg-info"><?= $vente['statut'] ?></span></div>
                </div>
            </div>
        </div>
    </div>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
        
        <div class="card mb-3">
            <div class="card-header">
                <i class="bi bi-info-circle"></i> Informations livraison
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Date de livraison <span class="text-danger">*</span></label>
                        <input type="date" name="date_livraison" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Livreur</label>
                        <select name="livreur_id" class="form-select">
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($utilisateurs as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nom_complet']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Transport assuré par</label>
                        <input type="text" name="transport_assure_par" class="form-control" placeholder="Ex: KMS, Client, Transporteur X">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observations</label>
                        <textarea name="observations" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="bi bi-box-seam"></i> Articles à livrer
                <small class="text-muted">(Saisissez les quantités à livrer. Laissez 0 pour ne pas livrer un article.)</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Produit</th>
                                <th class="text-end">Qté commandée</th>
                                <th class="text-end">Déjà livrée</th>
                                <th class="text-end">Reste à livrer</th>
                                <th class="text-end">Stock dispo</th>
                                <th class="text-end" style="width: 150px;">Qté à livrer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lignes as $ligne): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($ligne['code_produit'] ?? 'N/A') ?></code></td>
                                    <td><?= htmlspecialchars($ligne['designation']) ?></td>
                                    <td class="text-end"><?= number_format($ligne['quantite'], 2) ?></td>
                                    <td class="text-end"><?= number_format($ligne['qte_livree'], 2) ?></td>
                                    <td class="text-end"><strong><?= number_format($ligne['qte_restante'], 2) ?></strong></td>
                                    <td class="text-end">
                                        <span class="badge bg-<?= $ligne['stock_actuel'] >= $ligne['qte_restante'] ? 'success' : 'danger' ?>">
                                            <?= number_format($ligne['stock_actuel'], 2) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <input 
                                            type="number" 
                                            name="quantites[<?= $ligne['produit_id'] ?>]" 
                                            class="form-control form-control-sm text-end"
                                            value="<?= $ligne['qte_restante'] ?>"
                                            min="0"
                                            max="<?= $ligne['qte_restante'] ?>"
                                            step="0.01"
                                        >
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Créer le bon de livraison
            </button>
            <a href="<?= url_for('ventes/edit.php?id=' . $vente_id) ?>" class="btn btn-secondary">
                Annuler
            </a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
