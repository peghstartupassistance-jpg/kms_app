<?php
// formation/formations_edit.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('FORMATION_GERER');

global $pdo;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$formation = [
    'nom'         => '',
    'description' => '',
    'tarif_total' => 0,
];

// Chargement si édition
if ($editing) {
    $stmt = $pdo->prepare("SELECT * FROM formations WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $formation = $stmt->fetch();

    if (!$formation) {
        $_SESSION['flash_error'] = "Formation introuvable.";
        header('Location: ' . url_for('formation/formations_list.php'));
        exit;
    }
}

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf($_POST['csrf_token'] ?? '');

    $nom    = trim($_POST['nom'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $tarif  = str_replace(',', '.', $_POST['tarif_total'] ?? '0');
    $tarif  = (float)$tarif;

    $errors = [];

    if ($nom === '') {
        $errors[] = "Le nom de la formation est obligatoire.";
    }
    if ($tarif <= 0) {
        $errors[] = "Le tarif doit être strictement positif.";
    }

    if (empty($errors)) {
        if ($editing) {
            $stmt = $pdo->prepare("
                UPDATE formations
                SET nom = :nom, description = :description, tarif_total = :tarif
                WHERE id = :id
            ");
            $stmt->execute([
                'nom'         => $nom,
                'description' => $desc,
                'tarif'       => $tarif,
                'id'          => $id,
            ]);

            $_SESSION['flash_success'] = "Formation mise à jour avec succès.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO formations (nom, description, tarif_total)
                VALUES (:nom, :description, :tarif)
            ");
            $stmt->execute([
                'nom'         => $nom,
                'description' => $desc,
                'tarif'       => $tarif,
            ]);

            $_SESSION['flash_success'] = "Formation créée avec succès.";
        }

        header('Location: ' . url_for('formation/formations_list.php'));
        exit;
    } else {
        $_SESSION['flash_error'] = implode(' ', $errors);
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            <?= $editing ? 'Modifier la formation' : 'Nouvelle formation' ?>
        </h1>
        <a href="<?= url_for('formation/formations_list.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Retour au catalogue
        </a>
    </div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger py-2">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?= htmlspecialchars($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

                <div class="col-md-6">
                    <label class="form-label small">Nom de la formation</label>
                    <input type="text" name="nom" class="form-control"
                           value="<?= htmlspecialchars($formation['nom']) ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Tarif total</label>
                    <div class="input-group">
                        <input type="number" step="100" min="0" name="tarif_total" class="form-control"
                               value="<?= htmlspecialchars((float)$formation['tarif_total']) ?>" required>
                        <span class="input-group-text">FCFA</span>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label small">Description</label>
                    <textarea name="description" rows="4" class="form-control"
                              placeholder="Contenu, objectifs, durée, prérequis..."><?= htmlspecialchars($formation['description']) ?></textarea>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
