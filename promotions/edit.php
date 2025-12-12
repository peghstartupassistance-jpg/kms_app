<?php
// promotions/edit.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('PROMOTIONS_GERER');

global $pdo;

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

// Liste des produits pour la multi-sélection
$stmt = $pdo->query("
    SELECT id, code_produit, designation, prix_vente
    FROM produits
    WHERE actif = 1
    ORDER BY code_produit
");
$produits = $stmt->fetchAll();

// Valeurs par défaut
$data = [
    'nom'               => '',
    'description'       => '',
    'pourcentage_remise'=> '',
    'montant_remise'    => '',
    'date_debut'        => date('Y-m-d'),
    'date_fin'          => date('Y-m-d'),
    'actif'             => 1,
];

$produitsSelectionnes = [];

if ($isEdit) {
    // Promotion
    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        $_SESSION['flash_error'] = "Promotion introuvable.";
        header('Location: ' . url_for('promotions/list.php'));
        exit;
    }

    $data = [
        'nom'                => $row['nom'],
        'description'        => $row['description'] ?? '',
        'pourcentage_remise' => $row['pourcentage_remise'] !== null ? (string)$row['pourcentage_remise'] : '',
        'montant_remise'     => $row['montant_remise'] !== null ? (string)$row['montant_remise'] : '',
        'date_debut'         => $row['date_debut'],
        'date_fin'           => $row['date_fin'],
        'actif'              => (int)$row['actif'],
    ];

    // Produits associés
    $stmt = $pdo->prepare("SELECT produit_id FROM promotion_produit WHERE promotion_id = :pid");
    $stmt->execute(['pid' => $id]);
    $produitsSelectionnes = array_map('intval', array_column($stmt->fetchAll(), 'produit_id'));
}

$errors = [];

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifierCsrf($_POST['csrf_token'] ?? '');

        $data['nom']          = trim($_POST['nom'] ?? '');
        $data['description']  = trim($_POST['description'] ?? '');
        $data['date_debut']   = trim($_POST['date_debut'] ?? '');
        $data['date_fin']     = trim($_POST['date_fin'] ?? '');
        $data['actif']        = isset($_POST['actif']) ? 1 : 0;

        // Remises
        $pourcentage          = str_replace(',', '.', trim($_POST['pourcentage_remise'] ?? ''));
        $montant              = str_replace(',', '.', trim($_POST['montant_remise'] ?? ''));

        $data['pourcentage_remise'] = $pourcentage;
        $data['montant_remise']     = $montant;

        $pourcentageVal = $pourcentage !== '' ? (float)$pourcentage : 0.0;
        $montantVal     = $montant !== '' ? (float)$montant : 0.0;

        // Produits sélectionnés
        $produitsSelectionnes = array_map('intval', $_POST['produits'] ?? []);

        // Validations
        if ($data['nom'] === '') {
            $errors[] = "Le nom de la promotion est obligatoire.";
        }
        if ($data['date_debut'] === '' || $data['date_fin'] === '') {
            $errors[] = "Les dates de début et de fin sont obligatoires.";
        } elseif ($data['date_debut'] > $data['date_fin']) {
            $errors[] = "La date de début ne peut pas être postérieure à la date de fin.";
        }

        // Au moins un type de remise
        if ($pourcentageVal <= 0 && $montantVal <= 0) {
            $errors[] = "Vous devez renseigner au moins un type de remise (pourcentage ou montant).";
        }
        // Éviter deux remises simultanées > 0
        if ($pourcentageVal > 0 && $montantVal > 0) {
            $errors[] = "Merci de choisir soit une remise en pourcentage, soit une remise en montant, mais pas les deux.";
        }

        if (empty($produitsSelectionnes)) {
            $errors[] = "Vous devez associer au moins un produit à la promotion.";
        }

        if (empty($errors)) {
            // Normalisation pour SQL (null si 0)
            $pourcentageSql = $pourcentageVal > 0 ? $pourcentageVal : null;
            $montantSql     = $montantVal > 0 ? $montantVal : null;

            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE promotions
                    SET nom = :nom,
                        description = :description,
                        pourcentage_remise = :pourcentage_remise,
                        montant_remise = :montant_remise,
                        date_debut = :date_debut,
                        date_fin = :date_fin,
                        actif = :actif
                    WHERE id = :id
                ");
                $stmt->execute([
                    'nom'                => $data['nom'],
                    'description'        => $data['description'] !== '' ? $data['description'] : null,
                    'pourcentage_remise' => $pourcentageSql,
                    'montant_remise'     => $montantSql,
                    'date_debut'         => $data['date_debut'],
                    'date_fin'           => $data['date_fin'],
                    'actif'              => $data['actif'],
                    'id'                 => $id,
                ]);

                // MAJ associations produits
                $stmt = $pdo->prepare("DELETE FROM promotion_produit WHERE promotion_id = :pid");
                $stmt->execute(['pid' => $id]);

                $stmtInsert = $pdo->prepare("
                    INSERT INTO promotion_produit (promotion_id, produit_id)
                    VALUES (:pid, :prod)
                ");
                foreach ($produitsSelectionnes as $pidProduit) {
                    $stmtInsert->execute([
                        'pid'  => $id,
                        'prod' => $pidProduit,
                    ]);
                }

                $_SESSION['flash_success'] = "Promotion mise à jour avec succès.";

            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO promotions
                    (nom, description, pourcentage_remise, montant_remise, date_debut, date_fin, actif)
                    VALUES
                    (:nom, :description, :pourcentage_remise, :montant_remise, :date_debut, :date_fin, :actif)
                ");
                $stmt->execute([
                    'nom'                => $data['nom'],
                    'description'        => $data['description'] !== '' ? $data['description'] : null,
                    'pourcentage_remise' => $pourcentageSql,
                    'montant_remise'     => $montantSql,
                    'date_debut'         => $data['date_debut'],
                    'date_fin'           => $data['date_fin'],
                    'actif'              => $data['actif'],
                ]);

                $newId = (int)$pdo->lastInsertId();

                $stmtInsert = $pdo->prepare("
                    INSERT INTO promotion_produit (promotion_id, produit_id)
                    VALUES (:pid, :prod)
                ");
                foreach ($produitsSelectionnes as $pidProduit) {
                    $stmtInsert->execute([
                        'pid'  => $newId,
                        'prod' => $pidProduit,
                    ]);
                }

                $_SESSION['flash_success'] = "Promotion créée avec succès.";
            }

            header('Location: ' . url_for('promotions/list.php'));
            exit;
        }

    } catch (Throwable $e) {
        $errors[] = "Erreur lors de l'enregistrement de la promotion.";
    }
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            <?= $isEdit ? 'Modifier une promotion' : 'Nouvelle promotion' ?>
        </h1>
        <a href="<?= url_for('promotions/list.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Retour à la liste
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Nom de la promotion</label>
                        <input type="text" name="nom" class="form-control"
                               value="<?= htmlspecialchars($data['nom']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Date début</label>
                        <input type="date" name="date_debut" class="form-control"
                               value="<?= htmlspecialchars($data['date_debut']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Date fin</label>
                        <input type="date" name="date_fin" class="form-control"
                               value="<?= htmlspecialchars($data['date_fin']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small">Description (facultatif)</label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Contexte commercial, conditions, cible..."><?= htmlspecialchars($data['description']) ?></textarea>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small">Remise en %</label>
                        <input type="number" step="0.01" name="pourcentage_remise" class="form-control"
                               value="<?= htmlspecialchars($data['pourcentage_remise']) ?>"
                               placeholder="Ex : 10 pour 10 %">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Remise en montant (FCFA)</label>
                        <input type="number" step="0.01" name="montant_remise" class="form-control"
                               value="<?= htmlspecialchars($data['montant_remise']) ?>"
                               placeholder="Ex : 5000">
                    </div>

                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="actif" id="prom_actif"
                                   value="1" <?= (int)$data['actif'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="prom_actif">
                                Promotion active
                            </label>
                        </div>
                    </div>

                    <div class="col-md-9">
                        <label class="form-label small">Produits concernés</label>
                        <select name="produits[]" class="form-select" multiple size="8" required>
                            <?php foreach ($produits as $pr): ?>
                                <?php
                                $idProd = (int)$pr['id'];
                                $selected = in_array($idProd, $produitsSelectionnes, true) ? 'selected' : '';
                                ?>
                                <option value="<?= $idProd ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($pr['code_produit']) ?> – <?= htmlspecialchars($pr['designation']) ?>
                                    (<?= number_format((float)$pr['prix_vente'], 0, ',', ' ') ?> FCFA)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">
                            Maintenez CTRL (ou CMD sur Mac) pour sélectionner plusieurs produits.
                        </small>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i>
                            <?= $isEdit ? 'Mettre à jour la promotion' : 'Créer la promotion' ?>
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
