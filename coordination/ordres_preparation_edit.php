<?php
// coordination/ordres_preparation_edit.php - Formulaire demande de préparation
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

$id = $_GET['id'] ?? null;
$mode = $id ? 'edition' : 'creation';

// Chargement de l'ordre si édition
if ($id) {
    $stmt = $pdo->prepare("
        SELECT op.*, v.numero as vente_numero, v.client_id,
               c.nom as client_nom
        FROM ordres_preparation op
        LEFT JOIN ventes v ON op.vente_id = v.id
        LEFT JOIN clients c ON v.client_id = c.id
        WHERE op.id = ?
    ");
    $stmt->execute([$id]);
    $ordre = $stmt->fetch();
    
    if (!$ordre) {
        $_SESSION['flash_error'] = "Ordre introuvable";
        header('Location: ' . url_for('coordination/ordres_preparation.php'));
        exit;
    }
    
    // Lignes de la vente
    $stmtLignes = $pdo->prepare("
        SELECT lv.*, p.designation as produit_nom, p.code_produit
        FROM ventes_lignes lv
        INNER JOIN produits p ON lv.produit_id = p.id
        WHERE lv.vente_id = ?
    ");
    $stmtLignes->execute([$ordre['vente_id']]);
    $lignes = $stmtLignes->fetchAll();
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    verifierCsrf($csrf);
    
    $vente_id = $_POST['vente_id'] ?? null;
    
    // Vérifier que la vente peut être préparée
    if ($vente_id) {
        $stmtCheck = $pdo->prepare("SELECT statut FROM ventes WHERE id = ?");
        $stmtCheck->execute([$vente_id]);
        $venteCheck = $stmtCheck->fetch();
        
        if ($venteCheck && $venteCheck['statut'] === 'LIVREE') {
            $_SESSION['flash_error'] = "Cette vente est déjà entièrement livrée. Impossible de créer un ordre de préparation.";
            header('Location: ' . url_for('coordination/ordres_preparation.php'));
            exit;
        }
        if ($venteCheck && $venteCheck['statut'] === 'ANNULEE') {
            $_SESSION['flash_error'] = "Cette vente est annulée. Impossible de créer un ordre de préparation.";
            header('Location: ' . url_for('coordination/ordres_preparation.php'));
            exit;
        }
    }
    
    $type_demande = $_POST['type_demande'] ?? 'NORMALE';
    $instructions = $_POST['instructions'] ?? null;
    $date_livraison_souhaitee = $_POST['date_livraison_souhaitee'] ?? null;
    $adresse_livraison = $_POST['adresse_livraison'] ?? null;
    $magasinier_id = $_POST['magasinier_id'] ?? null;
    
    // Validation
    if (empty($vente_id)) {
        $_SESSION['flash_error'] = "La vente est obligatoire";
    } else {
        try {
            if ($mode === 'creation') {
                // Récupérer le client_id de la vente
                $stmtVente = $pdo->prepare("SELECT client_id FROM ventes WHERE id = ?");
                $stmtVente->execute([$vente_id]);
                $vente = $stmtVente->fetch();
                
                if (!$vente) {
                    $_SESSION['flash_error'] = "Vente introuvable";
                    header('Location: ' . url_for('coordination/ordres_preparation_edit.php'));
                    exit;
                }
                
                // Générer numéro ordre
                $dateNow = date('Ymd');
                $stmtCount = $pdo->query("SELECT COUNT(*) FROM ordres_preparation WHERE DATE(date_ordre) = CURDATE()");
                $count = $stmtCount->fetchColumn() + 1;
                $numero_ordre = "OP-$dateNow-" . str_pad($count, 4, '0', STR_PAD_LEFT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO ordres_preparation 
                    (vente_id, client_id, numero_ordre, date_ordre, commercial_responsable_id, priorite,
                     statut, observations, date_preparation_demandee, magasinier_id, date_creation)
                    VALUES (?, ?, ?, CURDATE(), ?, ?, 'EN_ATTENTE', ?, ?, ?, NOW())
                ");
                
                $utilisateur = utilisateurConnecte();
                $stmt->execute([
                    $vente_id,
                    $vente['client_id'], 
                    $numero_ordre, 
                    $utilisateur['id'], 
                    $type_demande,
                    $instructions,
                    $date_livraison_souhaitee,
                    $magasinier_id
                ]);
                
                $_SESSION['flash_success'] = "Ordre de préparation $numero_ordre créé avec succès";
                header('Location: ' . url_for('coordination/ordres_preparation.php'));
                exit;
                
            } else {
                // Mise à jour (uniquement si pas encore livré)
                if ($ordre['statut'] !== 'LIVRE') {
                    $stmt = $pdo->prepare("
                        UPDATE ordres_preparation 
                        SET priorite = ?, observations = ?, 
                            date_preparation_demandee = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $type_demande,
                        $instructions,
                        $date_livraison_souhaitee,
                        $id
                    ]);
                    
                    $_SESSION['flash_success'] = "Ordre modifié avec succès";
                } else {
                    $_SESSION['flash_warning'] = "Impossible de modifier un ordre déjà livré";
                }
                
                header('Location: ' . url_for('coordination/ordres_preparation_edit.php?id=' . $id));
                exit;
            }
            
        } catch (Exception $e) {
            $_SESSION['flash_error'] = "Erreur : " . $e->getMessage();
        }
    }
}

$venteIdPreselect = isset($_GET['vente_id']) ? (int)$_GET['vente_id'] : null;

// Liste des ventes non préparées (pour création)
if ($mode === 'creation') {
    $stmtVentes = $pdo->query("
        SELECT v.id, v.numero, v.date_vente, v.montant_total_ttc,
               c.nom
        FROM ventes v
        INNER JOIN clients c ON v.client_id = c.id
        WHERE v.statut IN ('EN_ATTENTE_LIVRAISON', 'PARTIELLEMENT_LIVREE')
          AND v.id NOT IN (SELECT vente_id FROM ordres_preparation WHERE statut NOT IN ('LIVRE', 'ANNULE'))
        ORDER BY v.date_vente DESC
        LIMIT 100
    ");
    $ventes_disponibles = $stmtVentes->fetchAll();
}

// Liste des préparateurs (magasiniers/utilisateurs actifs)
$stmtPreparateurs = $pdo->query("SELECT id, nom_complet FROM utilisateurs WHERE actif = 1 ORDER BY nom_complet");
$preparateurs = $stmtPreparateurs->fetchAll();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="form-page-header">
        <h1 class="h4 mb-0">
            <i class="bi bi-box-seam"></i> 
            <?= $mode === 'creation' ? 'Nouvelle demande de préparation' : 'Ordre ' . htmlspecialchars($ordre['numero_ordre']) ?>
        </h1>
        
        <div class="d-flex gap-2">
            <?php if ($mode === 'edition' && $ordre['statut'] === 'PRET'): ?>
                <a href="<?= url_for('livraisons/create.php?ordre_id=' . (int)$ordre['id'] . '&vente_id=' . (int)$ordre['vente_id']) ?>" 
                   class="btn btn-success btn-sm">
                    <i class="bi bi-truck"></i> Créer bon de livraison
                </a>
            <?php endif; ?>
            <a href="<?= url_for('coordination/ordres_preparation.php') ?>" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
        </div>
    </div>

    <?php if ($mode === 'edition'): ?>
        <!-- Infos ordre -->
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card form-card">
                    <div class="card-body p-2">
                        <div class="text-muted small">Statut</div>
                        <div class="fs-5">
                            <?php
                            $badge = match($ordre['statut']) {
                                'EN_ATTENTE' => 'warning',
                                'EN_PREPARATION' => 'info',
                                'PRET' => 'success',
                                'LIVRE' => 'secondary',
                                default => 'light'
                            };
                            ?>
                            <span class="badge bg-<?= $badge ?>">
                                <?= htmlspecialchars($ordre['statut']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card form-card">
                    <div class="card-body p-2">
                        <div class="text-muted small">Date demande</div>
                        <div class="fw-bold"><?= date('d/m/Y', strtotime($ordre['date_ordre'])) ?></div>
                        <small class="text-muted"><?= date('H:i', strtotime($ordre['date_creation'])) ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card form-card">
                    <div class="card-body p-2">
                        <div class="text-muted small">Vente</div>
                        <div>
                            <a href="<?= url_for('ventes/edit.php?id=' . $ordre['vente_id']) ?>" class="fw-bold text-decoration-none">
                                <?= htmlspecialchars($ordre['vente_numero']) ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card form-card">
                    <div class="card-body p-2">
                        <div class="text-muted small">Client</div>
                        <div class="fw-bold"><?= htmlspecialchars($ordre['client_nom']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lignes à préparer -->
        <div class="card form-card">
            <div class="card-header">
                <i class="bi bi-cart-check"></i> Articles à préparer
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Référence</th>
                                <th>Produit</th>
                                <th class="text-end">Quantité</th>
                                <th class="text-end">Prix unitaire</th>
                                <th class="text-end">Total HT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lignes as $ligne): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($ligne['code_produit'] ?? 'N/A') ?></code></td>
                                    <td><?= htmlspecialchars($ligne['designation'] ?? $ligne['produit_nom'] ?? 'N/A') ?></td>
                                    <td class="text-end"><strong><?= $ligne['quantite'] ?></strong></td>
                                    <td class="text-end"><?= number_format($ligne['prix_unitaire'], 0, ',', ' ') ?> F</td>
                                    <td class="text-end"><?= number_format($ligne['quantite'] * $ligne['prix_unitaire'], 0, ',', ' ') ?> F</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Formulaire -->
    <div class="card form-card">
        <div class="card-header">
            <i class="bi bi-pencil-square"></i> Détails de la demande
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                
                <?php if ($mode === 'creation'): ?>
                    <div class="mb-3">
                        <label class="form-label">Vente concernée <span class="text-danger">*</span></label>
                        <select name="vente_id" class="form-select" required>
                            <option value="">-- Sélectionner une vente --</option>
                            <?php foreach ($ventes_disponibles as $v): ?>
                                <option value="<?= $v['id'] ?>" <?= $venteIdPreselect === (int)$v['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['numero']) ?> - 
                                    <?= htmlspecialchars($v['nom']) ?> - 
                                    <?= number_format($v['montant_total_ttc'], 0, ',', ' ') ?> FCFA - 
                                    <?= date('d/m/Y', strtotime($v['date_vente'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Seules les ventes validées sans ordre de préparation actif sont listées</small>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Type de demande</label>
                        <select name="type_demande" class="form-select">
                            <option value="NORMALE" <?= ($mode === 'edition' && $ordre['priorite'] === 'NORMALE') ? 'selected' : '' ?>>Normale</option>
                            <option value="URGENTE" <?= ($mode === 'edition' && $ordre['priorite'] === 'URGENTE') ? 'selected' : '' ?>>Urgente</option>
                            <option value="TRES_URGENTE" <?= ($mode === 'edition' && $ordre['priorite'] === 'TRES_URGENTE') ? 'selected' : '' ?>>Très urgente</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="bi bi-calendar"></i> Date préparation demandée</label>
                        <input type="date" name="date_livraison_souhaitee" class="form-control" 
                               value="<?= $mode === 'edition' ? ($ordre['date_preparation_demandee'] ?? '') : '' ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-person-badge"></i> Préparateur / Magasinier</label>
                    <select name="magasinier_id" class="form-select">
                        <option value="">-- Attribuer plus tard --</option>
                        <?php foreach ($preparateurs as $prep): ?>
                            <option value="<?= $prep['id'] ?>" 
                                <?= ($mode === 'edition' && isset($ordre['magasinier_id']) && $ordre['magasinier_id'] == $prep['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prep['nom_complet']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Personne qui effectuera la préparation</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Observations / Instructions</label>
                    <textarea name="instructions" class="form-control" rows="3" placeholder="Emballage spécial, précautions, adresse de livraison..."><?= $mode === 'edition' ? htmlspecialchars($ordre['observations'] ?? '') : '' ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> <?= $mode === 'creation' ? 'Créer la demande' : 'Enregistrer modifications' ?>
                    </button>
                    <a href="<?= url_for('coordination/ordres_preparation.php') ?>" class="btn btn-secondary">
                        Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
