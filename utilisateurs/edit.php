<?php
// utilisateurs/edit.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('UTILISATEURS_GERER');

global $pdo;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$modeEdition = $id > 0;

// Récupérer la liste des rôles
$stmt = $pdo->query("SELECT id, code, nom FROM roles ORDER BY code");
$roles = $stmt->fetchAll();

// Initialisation des champs
$login        = '';
$nom_complet  = '';
$email        = '';
$telephone    = '';
$actif        = 1;
$rolesActuels = []; // IDs de rôle

$erreurs = [];

// Charger utilisateur en édition
if ($modeEdition && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("
        SELECT id, login, nom_complet, email, telephone, actif
        FROM utilisateurs
        WHERE id = :id
    ");
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo "Utilisateur introuvable.";
        exit;
    }

    $login       = $user['login'];
    $nom_complet = $user['nom_complet'];
    $email       = $user['email'];
    $telephone   = $user['telephone'];
    $actif       = (int)$user['actif'];

    // Rôles existants
    $stmt = $pdo->prepare("
        SELECT role_id
        FROM utilisateur_role
        WHERE utilisateur_id = :id
    ");
    $stmt->execute(['id' => $id]);
    $rolesActuels = array_map('intval', array_column($stmt->fetchAll(), 'role_id'));
}

// Traitement formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    verifierCsrf($csrf);

    $id            = (int)($_POST['id'] ?? 0);
    $modeEdition   = $id > 0;
    $login         = trim($_POST['login'] ?? '');
    $nom_complet   = trim($_POST['nom_complet'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $telephone     = trim($_POST['telephone'] ?? '');
    $actif         = isset($_POST['actif']) ? 1 : 0;
    $rolesSelectionnes = isset($_POST['roles']) && is_array($_POST['roles'])
        ? array_map('intval', $_POST['roles'])
        : [];

    $motDePasse        = $_POST['mot_de_passe'] ?? '';
    $motDePasseConfirm = $_POST['mot_de_passe_confirm'] ?? '';

    // Validations de base
    if ($login === '') {
        $erreurs[] = "Le login est obligatoire.";
    }
    if ($nom_complet === '') {
        $erreurs[] = "Le nom complet est obligatoire.";
    }

    if (!$modeEdition && $motDePasse === '') {
        $erreurs[] = "Le mot de passe est obligatoire pour un nouvel utilisateur.";
    }

    if ($motDePasse !== '' && $motDePasse !== $motDePasseConfirm) {
        $erreurs[] = "Les mots de passe ne correspondent pas.";
    }

    // Vérifier unicité du login
    $sqlLogin = "
        SELECT id FROM utilisateurs
        WHERE login = :login
        " . ($modeEdition ? "AND id <> :id" : "") . "
        LIMIT 1
    ";
    $paramsLogin = ['login' => $login];
    if ($modeEdition) {
        $paramsLogin['id'] = $id;
    }
    $stmt = $pdo->prepare($sqlLogin);
    $stmt->execute($paramsLogin);
    if ($stmt->fetch()) {
        $erreurs[] = "Ce login est déjà utilisé par un autre utilisateur.";
    }

    if (empty($erreurs)) {
        if ($modeEdition) {
            // UPDATE utilisateur
            $sql = "
                UPDATE utilisateurs
                SET login = :login,
                    nom_complet = :nom_complet,
                    email = :email,
                    telephone = :telephone,
                    actif = :actif
                WHERE id = :id
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'login'       => $login,
                'nom_complet' => $nom_complet,
                'email'       => $email ?: null,
                'telephone'   => $telephone ?: null,
                'actif'       => $actif,
                'id'          => $id
            ]);

            // Mise à jour mot de passe si fourni
            if ($motDePasse !== '') {
                $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE utilisateurs
                    SET mot_de_passe_hash = :hash
                    WHERE id = :id
                ");
                $stmt->execute([
                    'hash' => $hash,
                    'id'   => $id
                ]);
            }

        } else {
            // INSERT nouvel utilisateur
            $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
            $sql = "
                INSERT INTO utilisateurs (login, mot_de_passe_hash, nom_complet, email, telephone, actif, date_creation)
                VALUES (:login, :hash, :nom_complet, :email, :telephone, :actif, NOW())
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'login'       => $login,
                'hash'        => $hash,
                'nom_complet' => $nom_complet,
                'email'       => $email ?: null,
                'telephone'   => $telephone ?: null,
                'actif'       => $actif
            ]);
            $id = (int)$pdo->lastInsertId();
            $modeEdition = true;
        }

        // Mise à jour des rôles
        // On supprime d’abord les rôles existants
        $stmtDel = $pdo->prepare("DELETE FROM utilisateur_role WHERE utilisateur_id = :id");
        $stmtDel->execute(['id' => $id]);

        // Puis on insère ceux sélectionnés
        if (!empty($rolesSelectionnes)) {
            $stmtIns = $pdo->prepare("
                INSERT INTO utilisateur_role (utilisateur_id, role_id)
                VALUES (:uid, :rid)
            ");

            foreach ($rolesSelectionnes as $rid) {
                $stmtIns->execute([
                    'uid' => $id,
                    'rid' => $rid
                ]);
            }
        }

        $_SESSION['flash_success'] = $modeEdition
            ? "L’utilisateur a été mis à jour avec succès."
            : "L’utilisateur a été créé avec succès.";

        header('Location: ' . url_for('utilisateurs/list.php'));
        exit;
    } else {
        // En cas d’erreur, on conserve les rôles sélectionnés pour réafficher le formulaire
        $rolesActuels = $rolesSelectionnes;
    }
}

$csrfToken = getCsrfToken();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            <?= $modeEdition ? 'Modifier un utilisateur' : 'Nouvel utilisateur' ?>
        </h1>
        <a href="<?= url_for('utilisateurs/list.php') ?>" class="btn btn-outline-secondary">
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
                <div class="col-md-4">
                    <label class="form-label small">Login *</label>
                    <input type="text" name="login" class="form-control" required
                           value="<?= htmlspecialchars($login) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Nom complet *</label>
                    <input type="text" name="nom_complet" class="form-control" required
                           value="<?= htmlspecialchars($nom_complet) ?>">
                </div>

                <div class="col-md-4 d-flex align-items-center">
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="actif" id="actif"
                               <?= $actif ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="actif">
                            Utilisateur actif
                        </label>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Email</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($email) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Téléphone</label>
                    <input type="text" name="telephone" class="form-control"
                           value="<?= htmlspecialchars($telephone) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label small">
                        Mot de passe <?= $modeEdition ? '(laisser vide pour ne pas modifier)' : '*' ?>
                    </label>
                    <input type="password" name="mot_de_passe" class="form-control"
                           autocomplete="new-password">
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Confirmation mot de passe</label>
                    <input type="password" name="mot_de_passe_confirm" class="form-control"
                           autocomplete="new-password">
                </div>

                <div class="col-md-8">
                    <label class="form-label small">Rôles</label>
                    <div class="row g-2">
                        <?php foreach ($roles as $role): ?>
                            <?php $checked = in_array((int)$role['id'], $rolesActuels, true); ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="roles[]"
                                        id="role_<?= (int)$role['id'] ?>"
                                        value="<?= (int)$role['id'] ?>"
                                        <?= $checked ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label small" for="role_<?= (int)$role['id'] ?>">
                                        <strong><?= htmlspecialchars($role['code']) ?></strong><br>
                                        <span class="text-muted">
                                            <?= htmlspecialchars($role['nom']) ?>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>

        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="/utilisateurs/list.php" class="btn btn-outline-secondary">
                Annuler
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>
                <?= $modeEdition ? 'Enregistrer les modifications' : 'Créer l’utilisateur' ?>
            </button>
        </div>
    </form>
</div>

<?php
include __DIR__ . '/../partials/footer.php';
