<?php
// produits/edit.php - édition / création produit (utilise lib/stock.php)

require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('PRODUITS_MODIFIER');

require_once __DIR__ . '/../lib/stock.php';

global $pdo;

// URL de redirection vers la liste (adapter si besoin)
$produitsListUrl = '/kms_app/produits/list.php';

$erreurs = [];

// Récupération des listes pour le formulaire
$stmt = $pdo->query("SELECT id, nom FROM familles_produits ORDER BY nom");
$familles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT id, nom FROM fournisseurs ORDER BY nom");
$fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT id, famille_id, nom FROM sous_categories_produits ORDER BY nom");
$sc = $stmt->fetchAll(PDO::FETCH_ASSOC);
$scParFamille = [];
foreach ($sc as $r) {
    $scParFamille[(int)$r['famille_id']][] = ['id' => (int)$r['id'], 'nom' => $r['nom']];
}

// Déterminer si édition
$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
$modeEdition = $id > 0;

// Valeurs par défaut
$code_produit = '';
$designation = '';
$caracteristiques = '';
$description = '';
$famille_id = 0;
$sous_categorie_id = 0;
$fournisseur_id = 0;
$localisation = '';
$prix_achat = 0;
$prix_vente = 0;
$seuil_alerte = 0;
$actif = 1;
$image_path = null;
$stock_theorique = 0;
$stock_initial = 0;

if ($modeEdition) {
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($prod) {
        $code_produit = $prod['code_produit'];
        $designation = $prod['designation'];
        $caracteristiques = $prod['caracteristiques'];
        $description = $prod['description'];
        $famille_id = (int)$prod['famille_id'];
        $sous_categorie_id = (int)$prod['sous_categorie_id'];
        $fournisseur_id = (int)$prod['fournisseur_id'];
        $localisation = $prod['localisation'];
        $prix_achat = $prod['prix_achat'];
        $prix_vente = $prod['prix_vente'];
        $seuil_alerte = $prod['seuil_alerte'];
        $actif = (int)$prod['actif'];
        $image_path = $prod['image_path'];
        $stock_theorique = stock_get_quantite_produit($pdo, $id);
    } else {
        $modeEdition = false; // produit introuvable
    }
}

// POST handling (create/update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer champs
    $code_produit = trim($_POST['code_produit'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $caracteristiques = trim($_POST['caracteristiques'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $famille_id = (int)($_POST['famille_id'] ?? 0);
    $sous_categorie_id = (int)($_POST['sous_categorie_id'] ?? 0);
    $fournisseur_id = (int)($_POST['fournisseur_id'] ?? 0);
    $localisation = trim($_POST['localisation'] ?? '');
    $prix_achat = (float)($_POST['prix_achat'] ?? 0);
    $prix_vente = (float)($_POST['prix_vente'] ?? 0);
    $seuil_alerte = (int)($_POST['seuil_alerte'] ?? 0);
    $actif = isset($_POST['actif']) ? 1 : 0;
    $ajustement_stock = isset($_POST['ajustement_stock']) ? (float)$_POST['ajustement_stock'] : 0;
    $stock_initial = isset($_POST['stock_initial']) ? (float)$_POST['stock_initial'] : 0;

    // Validation minimale
    if ($code_produit === '') $erreurs[] = 'Le code produit est requis.';
    if ($designation === '') $erreurs[] = 'La désignation est requise.';
    if ($famille_id <= 0) $erreurs[] = 'La famille doit être sélectionnée.';

    // Image upload (optionnel)
    $uploadPath = $image_path;
    if (!empty($_FILES['image']['tmp_name'])) {
        $file = $_FILES['image'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $code_produit) . '.' . $ext;
        $uploadDir = __DIR__ . '/../assets/img/produits';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $dest = $uploadDir . '/' . $filename;
        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            $erreurs[] = 'Impossible d\'enregistrer l\'image sur le serveur.';
        } else {
            $uploadPath = '/kms_app/assets/img/produits/' . $filename;
        }
    }

    if (empty($erreurs)) {
        try {
            $pdo->beginTransaction();
            $utilisateur = utilisateurConnecte();
            $userId = (int)$utilisateur['id'];

            if ($modeEdition) {
                // Mise à jour du produit
                $sql = "UPDATE produits SET
                    code_produit = :code_produit,
                    famille_id = :famille_id,
                    sous_categorie_id = :sous_categorie_id,
                    designation = :designation,
                    caracteristiques = :caracteristiques,
                    description = :description,
                    fournisseur_id = :fournisseur_id,
                    localisation = :localisation,
                    prix_achat = :prix_achat,
                    prix_vente = :prix_vente,
                    seuil_alerte = :seuil_alerte,
                    actif = :actif,
                    image_path = :image_path
                    WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':code_produit' => $code_produit,
                    ':famille_id' => $famille_id,
                    ':sous_categorie_id' => $sous_categorie_id ?: null,
                    ':designation' => $designation,
                    ':caracteristiques' => $caracteristiques ?: null,
                    ':description' => $description ?: null,
                    ':fournisseur_id' => $fournisseur_id ?: null,
                    ':localisation' => $localisation ?: null,
                    ':prix_achat' => $prix_achat,
                    ':prix_vente' => $prix_vente,
                    ':seuil_alerte' => $seuil_alerte,
                    ':actif' => $actif,
                    ':image_path' => $uploadPath,
                    ':id' => $id,
                ]);

                // Ajustement de stock (si demandé)
                if ($ajustement_stock != 0) {
                    $typeMvt = $ajustement_stock > 0 ? 'ENTREE' : 'SORTIE';
                    stock_enregistrer_mouvement($pdo, [
                        'produit_id' => $id,
                        'date_mouvement' => date('Y-m-d H:i:s'),
                        'type_mouvement' => $typeMvt,
                        'quantite' => abs($ajustement_stock),
                        'source_type' => 'AJUSTEMENT',
                        'source_id' => null,
                        'commentaire' => 'Ajustement manuel depuis fiche produit',
                        'utilisateur_id' => $userId,
                    ]);
                }

                $pdo->commit();
                $_SESSION['flash_success'] = 'Le produit a été mis à jour avec succès.';

            } else {
                // Création
                $sql = "INSERT INTO produits (
                    code_produit, famille_id, sous_categorie_id, designation,
                    caracteristiques, description, fournisseur_id, localisation,
                    prix_achat, prix_vente, stock_actuel, seuil_alerte, image_path, actif, date_creation
                    ) VALUES (
                    :code_produit, :famille_id, :sous_categorie_id, :designation,
                    :caracteristiques, :description, :fournisseur_id, :localisation,
                    :prix_achat, :prix_vente, :stock_actuel, :seuil_alerte, :image_path, :actif, NOW()
                )";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':code_produit' => $code_produit,
                    ':famille_id' => $famille_id,
                    ':sous_categorie_id' => $sous_categorie_id ?: null,
                    ':designation' => $designation,
                    ':caracteristiques' => $caracteristiques ?: null,
                    ':description' => $description ?: null,
                    ':fournisseur_id' => $fournisseur_id ?: null,
                    ':localisation' => $localisation ?: null,
                    ':prix_achat' => $prix_achat,
                    ':prix_vente' => $prix_vente,
                    ':stock_actuel' => 0,
                    ':seuil_alerte' => $seuil_alerte,
                    ':image_path' => $uploadPath,
                    ':actif' => $actif,
                ]);

                $newId = (int)$pdo->lastInsertId();

                // Stock initial (via librairie)
                if ($stock_initial > 0) {
                    stock_enregistrer_mouvement($pdo, [
                        'produit_id' => $newId,
                        'date_mouvement' => date('Y-m-d H:i:s'),
                        'type_mouvement' => 'ENTREE',
                        'quantite' => $stock_initial,
                        'source_type' => 'INVENTAIRE',
                        'source_id' => null,
                        'commentaire' => 'Stock initial à la création du produit',
                        'utilisateur_id' => $userId,
                    ]);
                }

                // Forcer recalcul
                stock_recalculer_stock_produit($pdo, $newId);

                $pdo->commit();
                $_SESSION['flash_success'] = 'Le produit a été créé avec succès.';
            }

            // Redirection vers la liste
            header('Location: ' . $produitsListUrl);
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $erreurs[] = 'Erreur lors de l\'enregistrement du produit : ' . $e->getMessage();
        }
    }
}

$csrfToken = getCsrfToken();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            <?= $modeEdition ? 'Modifier un produit' : 'Nouveau produit' ?>
        </h1>
        <a href="<?= $produitsListUrl ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Retour à la liste
        </a>
    </div>

    <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger">
            <h2 class="h6 mb-2"><i class="bi bi-exclamation-triangle me-1"></i> Erreurs</h2>
            <ul class="mb-0">
                <?php foreach ($erreurs as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="card">
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int)$id ?>">

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">Code produit *</label>
                    <input type="text" name="code_produit" class="form-control" required
                           value="<?= htmlspecialchars($code_produit) ?>">
                </div>

                <div class="col-md-5">
                    <label class="form-label small">Désignation *</label>
                    <input type="text" name="designation" class="form-control" required
                           value="<?= htmlspecialchars($designation) ?>">
                </div>

                <div class="col-md-2 d-flex align-items-center">
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="actif" id="actif"
                               <?= $actif ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="actif">
                            Produit actif
                        </label>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Seuil alerte</label>
                    <input type="number" name="seuil_alerte" class="form-control"
                           value="<?= (int)$seuil_alerte ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Famille *</label>
                    <select name="famille_id" id="famille_id" class="form-select" required>
                        <option value="">Sélectionner...</option>
                        <?php foreach ($familles as $fam): ?>
                            <option value="<?= (int)$fam['id'] ?>"
                                <?= $famille_id === (int)$fam['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fam['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Sous-catégorie</label>
                    <select name="sous_categorie_id" id="sous_categorie_id" class="form-select">
                            <option value="">—</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Fournisseur</label>
                    <select name="fournisseur_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($fournisseurs as $f): ?>
                            <option value="<?= (int)$f['id'] ?>"
                                <?= $fournisseur_id === (int)$f['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Prix d’achat (FCFA)</label>
                    <input type="number" step="0.01" name="prix_achat" class="form-control"
                           value="<?= htmlspecialchars($prix_achat) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Prix de vente (FCFA)</label>
                    <input type="number" step="0.01" name="prix_vente" class="form-control"
                           value="<?= htmlspecialchars($prix_vente) ?>">
                </div>

                <?php if (!$modeEdition): ?>
                    <div class="col-md-3">
                        <label class="form-label small">Stock initial</label>
                        <input type="number" name="stock_initial" class="form-control"
                               value="0" min="0">
                        <div class="form-text small">
                            Génère une entrée de stock (INVENTAIRE) à la création du produit.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="col-md-3">
                        <label class="form-label small">
                            Ajustement de stock (peut être négatif)
                        </label>
                        <input type="number" name="ajustement_stock" class="form-control"
                               value="0">
                        <div class="form-text small">
                            Stock actuel théorique :
                            <strong><?= (int)$stock_theorique ?></strong><br>
                            &gt; 0 = entrée, &lt; 0 = sortie (mouvement d’ajustement).
                        </div>
                    </div>
                <?php endif; ?>

                <div class="col-md-3">
                    <label class="form-label small">Localisation (magasin, showroom...)</label>
                    <input type="text" name="localisation" class="form-control"
                           value="<?= htmlspecialchars($localisation) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Image produit</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <?php if ($image_path): ?>
                        <div class="mt-2">
                            <div class="card border-light bg-light p-2" style="max-width: 120px;">
                                <?php 
                                    // Determiner le chemin correct
                                    $displayPath = $image_path;
                                    // Si le chemin ne commence pas par /kms_app, l'ajouter (migration)
                                    if (strpos($image_path, '/kms_app') === false && strpos($image_path, '/assets') === 0) {
                                        $displayPath = '/kms_app' . $image_path;
                                    }
                                ?>
                                <img src="<?= htmlspecialchars($displayPath) ?>" 
                                     alt="Image produit"
                                     style="max-height: 100px; object-fit: contain; width: 100%;"
                                     onerror="this.parentElement.innerHTML='<small class=\"text-muted\">Image non trouvee</small>';">
                            </div>
                            <small class="text-muted d-block mt-1">Fichier : <?= htmlspecialchars(basename($image_path)) ?></small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-12">
                    <label class="form-label small">Caractéristiques techniques</label>
                    <textarea name="caracteristiques" class="form-control" rows="2"><?= htmlspecialchars($caracteristiques) ?></textarea>
                </div>

                <div class="col-md-12">
                    <label class="form-label small">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($description) ?></textarea>
                </div>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= $produitsListUrl ?>" class="btn btn-outline-secondary">
                Annuler
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>
                <?= $modeEdition ? 'Enregistrer les modifications' : 'Créer le produit' ?>
            </button>
        </div>
    </form>
</div>

<script>
    // Sous-catégories par famille (injecté depuis PHP)
    const scParFamille = <?= json_encode($scParFamille, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const famSelect    = document.getElementById('famille_id');
    const scSelect     = document.getElementById('sous_categorie_id');
    const currentScId  = <?= $sous_categorie_id ? (int)$sous_categorie_id : 0 ?>;

    function majSousCategories(selectedId) {
        const fid = parseInt(famSelect.value || '0', 10);
        scSelect.innerHTML = '<option value="">—</option>';
        if (!fid || !scParFamille[fid]) return;

        scParFamille[fid].forEach(sc => {
            const opt = document.createElement('option');
            opt.value = sc.id;
            opt.textContent = sc.nom;
            if (selectedId && sc.id === selectedId) {
                opt.selected = true;
            }
            scSelect.appendChild(opt);
        });
    }

    if (famSelect && scSelect) {
        famSelect.addEventListener('change', () => {
            majSousCategories(0);
        });
        majSousCategories(currentScId);
    }
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
