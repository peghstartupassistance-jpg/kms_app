<?php
// hotel/chambres_edit.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('HOTEL_GERER');

global $pdo;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$errors = [];
$chambre = [
    'code'        => '',
    'description' => '',
    'tarif_nuite' => '',
    'actif'       => 1,
];

// Chargement pour édition
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM chambres WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $chambre = $stmt->fetch();

    if (!$chambre) {
        $_SESSION['flash_error'] = "Chambre introuvable.";
        header('Location: ' . url_for('hotel/chambres_list.php'));
        exit;
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf($_POST['csrf_token'] ?? '');

    $code        = trim($_POST['code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $tarif       = str_replace([' ', ','], ['', '.'], $_POST['tarif_nuite'] ?? '');
    $actif       = isset($_POST['actif']) ? 1 : 0;

    if ($code === '') {
        $errors[] = "Le code de la chambre est obligatoire.";
    }
    if ($tarif === '' || !is_numeric($tarif) || $tarif < 0) {
        $errors[] = "Le tarif de la nuité est invalide.";
    }

    if (empty($errors)) {
        if ($isEdit) {
            $stmt = $pdo->prepare("
                UPDATE chambres
                SET code = :code,
                    description = :descr,
                    tarif_nuite = :tarif,
                    actif = :actif
                WHERE id = :id
            ");
            $stmt->execute([
                'code'  => $code,
                'descr' => $description,
                'tarif' => $tarif,
                'actif' => $actif,
                'id'    => $id,
            ]);
            $_SESSION['flash_success'] = "Chambre mise à jour avec succès.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO chambres (code, description, tarif_nuite, actif)
                VALUES (:code, :descr, :tarif, :actif)
            ");
            $stmt->execute([
                'code'  => $code,
                'descr' => $description,
                'tarif' => $tarif,
                'actif' => $actif,
            ]);
            $_SESSION['flash_success'] = "Chambre créée avec succès.";
        }

        header('Location: ' . url_for('hotel/chambres_list.php'));
        exit;
    } else {
        // Réinjecter valeurs
        $chambre['code']        = $code;
        $chambre['description'] = $description;
        $chambre['tarif_nuite'] = $tarif;
        $chambre['actif']       = $actif;
    }
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            <?= $isEdit ? 'Modifier la chambre' : 'Nouvelle chambre' ?>
        </h1>
        <a href="<?= url_for('hotel/chambres_list.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Retour à la liste
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2">
            <ul class="mb-0 small">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

                <div class="mb-3">
                    <label class="form-label">Code de la chambre</label>
                    <input type="text" name="code" class="form-control"
                           value="<?= htmlspecialchars($chambre['code']) ?>" required>
                    <div class="form-text">Ex : CH-01, APPART-2B, SUITE-3, ...</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($chambre['description'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Tarif nuité (FCFA)</label>
                    <input type="number" step="0.01" min="0" name="tarif_nuite" class="form-control"
                           value="<?= htmlspecialchars($chambre['tarif_nuite']) ?>" required>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="actif" name="actif"
                           <?= (int)$chambre['actif'] === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="actif">
                        Chambre active (réservable)
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Enregistrer
                </button>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
