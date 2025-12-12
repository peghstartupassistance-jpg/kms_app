<?php
// litiges/edit.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_CREER');

global $pdo;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

// Charger listes de référence
$stmt = $pdo->query("SELECT id, nom FROM clients ORDER BY nom");
$clients = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, code_produit, designation FROM produits ORDER BY code_produit");
$produits = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, nom_complet FROM utilisateurs ORDER BY nom_complet");
$utilisateurs = $stmt->fetchAll();

// Valeurs par défaut
$data = [
    'date_retour'        => date('Y-m-d'),
    'client_id'          => '',
    'produit_id'         => '',
    'vente_id'           => '',
    'motif'              => '',
    'responsable_suivi_id' => '',
    'statut_traitement'  => 'EN_COURS',
    'solution'           => '',
];

// Chargement en édition
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM retours_litiges WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        $_SESSION['flash_error'] = "Litige introuvable.";
        header('Location: ' . url_for('litiges/list.php'));
        exit;
    }

    $data = [
        'date_retour'        => $row['date_retour'],
        'client_id'          => $row['client_id'],
        'produit_id'         => $row['produit_id'],
        'vente_id'           => $row['vente_id'],
        'motif'              => $row['motif'],
        'responsable_suivi_id' => $row['responsable_suivi_id'],
        'statut_traitement'  => $row['statut_traitement'],
        'solution'           => $row['solution'],
    ];
}

$errors = [];

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifierCsrf($_POST['csrf_token'] ?? '');

        $data['date_retour']        = trim($_POST['date_retour'] ?? '');
        $data['client_id']          = (int)($_POST['client_id'] ?? 0);
        $data['produit_id']         = (int)($_POST['produit_id'] ?? 0);
        $data['vente_id']           = (int)($_POST['vente_id'] ?? 0) ?: null;
        $data['motif']              = trim($_POST['motif'] ?? '');
        $data['responsable_suivi_id'] = (int)($_POST['responsable_suivi_id'] ?? 0) ?: null;
        $data['statut_traitement']  = $_POST['statut_traitement'] ?? 'EN_COURS';
        $data['solution']           = trim($_POST['solution'] ?? '');

        // Validations simples
        if ($data['date_retour'] === '') {
            $errors[] = "La date de retour est obligatoire.";
        }
        if ($data['client_id'] <= 0) {
            $errors[] = "Le client est obligatoire.";
        }
        if ($data['produit_id'] <= 0) {
            $errors[] = "Le produit est obligatoire.";
        }
        if ($data['motif'] === '') {
            $errors[] = "Le motif du litige est obligatoire.";
        }
        if (!in_array($data['statut_traitement'], ['EN_COURS','RESOLU','ABANDONNE'], true)) {
            $errors[] = "Le statut de traitement est invalide.";
        }

        if (empty($errors)) {
            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE retours_litiges
                    SET date_retour = :date_retour,
                        client_id   = :client_id,
                        produit_id  = :produit_id,
                        vente_id    = :vente_id,
                        motif       = :motif,
                        responsable_suivi_id = :responsable_suivi_id,
                        statut_traitement    = :statut_traitement,
                        solution    = :solution
                    WHERE id = :id
                ");
                $stmt->execute([
                    'date_retour'        => $data['date_retour'],
                    'client_id'          => $data['client_id'],
                    'produit_id'         => $data['produit_id'],
                    'vente_id'           => $data['vente_id'],
                    'motif'              => $data['motif'],
                    'responsable_suivi_id' => $data['responsable_suivi_id'],
                    'statut_traitement'  => $data['statut_traitement'],
                    'solution'           => $data['solution'] !== '' ? $data['solution'] : null,
                    'id'                 => $id,
                ]);

                $_SESSION['flash_success'] = "Litige mis à jour avec succès.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO retours_litiges
                    (date_retour, client_id, produit_id, vente_id, motif,
                     responsable_suivi_id, statut_traitement, solution)
                    VALUES
                    (:date_retour, :client_id, :produit_id, :vente_id, :motif,
                     :responsable_suivi_id, :statut_traitement, :solution)
                ");
                $stmt->execute([
                    'date_retour'        => $data['date_retour'],
                    'client_id'          => $data['client_id'],
                    'produit_id'         => $data['produit_id'],
                    'vente_id'           => $data['vente_id'],
                    'motif'              => $data['motif'],
                    'responsable_suivi_id' => $data['responsable_suivi_id'],
                    'statut_traitement'  => $data['statut_traitement'],
                    'solution'           => $data['solution'] !== '' ? $data['solution'] : null,
                ]);

                $_SESSION['flash_success'] = "Litige créé avec succès.";
            }

            header('Location: ' . url_for('litiges/list.php'));
            exit;
        }

    } catch (Throwable $e) {
        $errors[] = "Erreur lors de l'enregistrement du litige.";
    }
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            <?= $isEdit ? 'Suivi du litige' : 'Nouveau litige / retour' ?>
        </h1>
        <a href="<?= url_for('litiges/list.php') ?>" class="btn btn-outline-secondary btn-sm">
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
                    <div class="col-md-3">
                        <label class="form-label small">Date de retour</label>
                        <input type="date" name="date_retour" class="form-control"
                               value="<?= htmlspecialchars($data['date_retour']) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small">Client</label>
                        <select name="client_id" class="form-select" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($clients as $cl): ?>
                                <option value="<?= (int)$cl['id'] ?>"
                                    <?= (int)$data['client_id'] === (int)$cl['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cl['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label small">Produit</label>
                        <select name="produit_id" class="form-select" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($produits as $pr): ?>
                                <option value="<?= (int)$pr['id'] ?>"
                                    <?= (int)$data['produit_id'] === (int)$pr['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pr['code_produit']) ?> – <?= htmlspecialchars($pr['designation']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small">Vente liée (ID ou n° interne)</label>
                        <input type="number" name="vente_id" class="form-control"
                               value="<?= htmlspecialchars((string)($data['vente_id'] ?? '')) ?>"
                               placeholder="ID vente (facultatif)">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small">Responsable du suivi</label>
                        <select name="responsable_suivi_id" class="form-select">
                            <option value="">-- Non défini --</option>
                            <?php foreach ($utilisateurs as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"
                                    <?= (int)($data['responsable_suivi_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['nom_complet']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label small">Statut du traitement</label>
                        <select name="statut_traitement" class="form-select" required>
                            <?php foreach (['EN_COURS','RESOLU','ABANDONNE'] as $s): ?>
                                <option value="<?= $s ?>" <?= $data['statut_traitement'] === $s ? 'selected' : '' ?>>
                                    <?= $s ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label small">Motif du litige</label>
                        <textarea name="motif" class="form-control" rows="3" required
                                  placeholder="Décrire le problème, la non-conformité, etc."><?= htmlspecialchars($data['motif']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label small">Solution / Actions menées</label>
                        <textarea name="solution" class="form-control" rows="3"
                                  placeholder="Échanges avec le client, réparation, remboursement, remise, etc."><?= htmlspecialchars($data['solution'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i>
                            <?= $isEdit ? 'Mettre à jour le litige' : 'Enregistrer le litige' ?>
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
