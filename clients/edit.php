<?php 
// clients/edit.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CLIENTS_CREER');

global $pdo;

// URL de base pour la liste des clients (inclut le répertoire du projet)
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$clientsListUrl = $basePath . '/list.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$modeEdition = $id > 0;

// Types client
$stmtTypes = $pdo->query("SELECT id, code, libelle FROM types_client ORDER BY code");
$typesClient = $stmtTypes->fetchAll();

$nom        = '';
$type_id    = $typesClient[0]['id'] ?? 0;
$telephone  = '';
$email      = '';
$adresse    = '';
$source     = '';
$statut     = 'PROSPECT';

$erreurs = [];

// Chargement en édition (GET)
if ($modeEdition && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("
        SELECT * FROM clients WHERE id = :id
    ");
    $stmt->execute(['id' => $id]);
    $c = $stmt->fetch();

    if (!$c) {
        http_response_code(404);
        echo "Client introuvable.";
        exit;
    }

    $nom       = $c['nom'];
    $type_id   = (int)$c['type_client_id'];
    $telephone = $c['telephone'] ?? '';
    $email     = $c['email'] ?? '';
    $adresse   = $c['adresse'] ?? '';
    $source    = $c['source'] ?? '';
    $statut    = $c['statut'];
}

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    verifierCsrf($csrf);

    $id          = (int)($_POST['id'] ?? 0);
    $modeEdition = $id > 0;

    $nom       = trim($_POST['nom'] ?? '');
    $type_id   = (int)($_POST['type_id'] ?? 0);
    $telephone = trim($_POST['telephone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $adresse   = trim($_POST['adresse'] ?? '');
    $source    = trim($_POST['source'] ?? '');
    $statut    = trim($_POST['statut'] ?? 'PROSPECT');

    if ($nom === '') {
        $erreurs[] = "Le nom du client est obligatoire.";
    }
    if ($type_id <= 0) {
        $erreurs[] = "Veuillez sélectionner un type de client.";
    }
    if (!in_array($statut, ['PROSPECT','CLIENT','APPRENANT','HOTE'], true)) {
        $erreurs[] = "Statut de client invalide.";
    }

    if (empty($erreurs)) {
        if ($modeEdition) {
            $sql = "
                UPDATE clients
                SET nom = :nom,
                    type_client_id = :type_id,
                    telephone = :telephone,
                    email = :email,
                    adresse = :adresse,
                    source = :source,
                    statut = :statut
                WHERE id = :id
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'nom'       => $nom,
                'type_id'   => $type_id,
                'telephone' => $telephone ?: null,
                'email'     => $email ?: null,
                'adresse'   => $adresse ?: null,
                'source'    => $source ?: null,
                'statut'    => $statut,
                'id'        => $id
            ]);
            $_SESSION['flash_success'] = "Le client a été mis à jour avec succès.";
        } else {
            $sql = "
                INSERT INTO clients (
                    nom, type_client_id, telephone, email, adresse, source, statut, date_creation
                )
                VALUES (
                    :nom, :type_id, :telephone, :email, :adresse, :source, :statut, NOW()
                )
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'nom'       => $nom,
                'type_id'   => $type_id,
                'telephone' => $telephone ?: null,
                'email'     => $email ?: null,
                'adresse'   => $adresse ?: null,
                'source'    => $source ?: null,
                'statut'    => $statut
            ]);

            $_SESSION['flash_success'] = "Le client a été créé avec succès.";
        }

        header('Location: ' . $clientsListUrl);
        exit;
    }
}

$csrfToken = getCsrfToken();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            <?= $modeEdition ? 'Modifier un client' : 'Nouveau client' ?>
        </h1>
        <a href="<?= $clientsListUrl ?>" class="btn btn-outline-secondary">
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

    <form method="post" class="card">
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int)$id ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small">Nom du client *</label>
                    <input type="text" name="nom" class="form-control" required
                           value="<?= htmlspecialchars($nom) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Type *</label>
                    <select name="type_id" class="form-select" required>
                        <option value="">Sélectionner...</option>
                        <?php foreach ($typesClient as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"
                                <?= $type_id === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['libelle']) ?>
                                (<?= htmlspecialchars($t['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Statut *</label>
                    <select name="statut" class="form-select" required>
                        <?php foreach (['PROSPECT','CLIENT','APPRENANT','HOTE'] as $s): ?>
                            <option value="<?= $s ?>" <?= $statut === $s ? 'selected' : '' ?>>
                                <?= $s ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Téléphone</label>
                    <input type="text" name="telephone" class="form-control"
                           value="<?= htmlspecialchars($telephone) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Email</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($email) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Source (Facebook, WhatsApp...)</label>
                    <input type="text" name="source" class="form-control"
                           value="<?= htmlspecialchars($source) ?>">
                </div>

                <div class="col-md-12">
                    <label class="form-label small">Adresse</label>
                    <textarea name="adresse" class="form-control" rows="2"><?= htmlspecialchars($adresse) ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= $clientsListUrl ?>" class="btn btn-outline-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>
                <?= $modeEdition ? 'Enregistrer' : 'Créer le client' ?>
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
