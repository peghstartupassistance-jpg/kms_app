<?php
// login.php
require_once __DIR__ . '/security.php';

// Calcule dynamiquement l'URL du dashboard (index.php) en tenant compte du répertoire de l'app
$scriptPath   = $_SERVER['SCRIPT_NAME'];               // ex : /kms_app/login.php ou /login.php
$basePath     = rtrim(dirname($scriptPath), '/\\');    // ex : /kms_app ou /
$dashboardUrl = $basePath . '/index.php';              // ex : /kms_app/index.php ou /index.php

// Si déjà connecté, on renvoie au dashboard
if (utilisateurConnecte()) {
    header('Location: ' . $dashboardUrl);
    exit;
}

$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    verifierCsrf($csrf);

    $login       = trim($_POST['login'] ?? '');
    $motDePasse  = $_POST['mot_de_passe'] ?? '';

    if ($login === '' || $motDePasse === '') {
        $erreur = "Veuillez saisir votre identifiant et votre mot de passe.";
    } else {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT id, login, mot_de_passe_hash, actif
            FROM utilisateurs
            WHERE login = :login
            LIMIT 1
        ");
        $stmt->execute(['login' => $login]);
        $user = $stmt->fetch();

        if ($user && (int)$user['actif'] === 1) {
            $userId = (int)$user['id'];

            if (password_verify($motDePasse, $user['mot_de_passe_hash'])) {
                // Login OK
                // Mettre à jour date_derniere_connexion
                $upd = $pdo->prepare("
                    UPDATE utilisateurs
                    SET date_derniere_connexion = NOW()
                    WHERE id = :id
                ");
                $upd->execute(['id' => $userId]);

                // Charger permissions
                chargerPermissionsUtilisateur($userId);

                // Journal connexion OK
                enregistrerConnexion($userId, 1);

                // Redirection dashboard
                header('Location: ' . $dashboardUrl);
                exit;
            } else {
                // Mot de passe incorrect
                enregistrerConnexion($userId, 0);
                $erreur = "Identifiants incorrects.";
            }
        } else {
            // Login inconnu ou utilisateur inactif
            // (pas de journal car on n'a pas d'ID valide dans la FK)
            $erreur = "Identifiants incorrects.";
        }
    }
}

$csrfToken = getCsrfToken();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Connexion – KMS Back-office</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    >
    <link rel="stylesheet" href="/assets/css/custom.css">
    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(circle at top left, #1f2933, #020617 60%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .kms-login-card {
            max-width: 420px;
            width: 100%;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(15,23,42,0.45);
        }
        .kms-login-header {
            background: linear-gradient(135deg, #0f172a, #1d4ed8);
            color: #f9fafb;
        }
        .kms-login-logo {
            width: 52px;
            height: 52px;
            border-radius: 999px;
            background: rgba(15,23,42,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .kms-login-logo img {
            max-height: 36px;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(37,99,235,0.35);
            border-color: #2563eb;
        }
    </style>
</head>
<body>

<div class="kms-login-card bg-white">
    <div class="kms-login-header p-4 d-flex gap-3 align-items-center">
        <div class="kms-login-logo">
            <img src="/assets/img/logo-kms.png" alt="KMS">
        </div>
        <div>
            <h1 class="h5 mb-1">Kenne Multi-Services</h1>
            <p class="mb-0 small text-light opacity-75">
                Back-office marketing & commercial
            </p>
        </div>
    </div>

    <div class="p-4">
        <?php if ($erreur): ?>
            <div class="alert alert-danger py-2 mb-3">
                <i class="bi bi-exclamation-circle me-1"></i>
                <?= htmlspecialchars($erreur) ?>
            </div>
        <?php else: ?>
            <p class="small text-muted mb-3">
                Connectez-vous pour accéder au tableau de bord et aux modules internes KMS.
            </p>
        <?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="mb-3">
                <label for="login" class="form-label small">Identifiant</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-person"></i>
                    </span>
                    <input
                        type="text"
                        class="form-control"
                        id="login"
                        name="login"
                        required
                        autocomplete="username"
                        value="<?= isset($login) ? htmlspecialchars($login) : '' ?>"
                    >
                </div>
            </div>

            <div class="mb-3">
                <label for="mot_de_passe" class="form-label small">Mot de passe</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input
                        type="password"
                        class="form-control"
                        id="mot_de_passe"
                        name="mot_de_passe"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="btn btn-outline-secondary" id="togglePassword" tabindex="-1">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-2">
                <i class="bi bi-box-arrow-in-right me-1"></i>
                Se connecter
            </button>

            <p class="mt-3 mb-0 text-muted small">
                Premier accès : utilisez <code>admin</code> / <code>admin123</code>,
                puis créez de nouveaux utilisateurs dans l’interface d’administration.
            </p>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
<script>
    // Afficher / masquer le mot de passe
    const togglePasswordBtn = document.getElementById('togglePassword');
    const pwdInput = document.getElementById('mot_de_passe');

    if (togglePasswordBtn && pwdInput) {
        togglePasswordBtn.addEventListener('click', () => {
            const type = pwdInput.getAttribute('type') === 'password' ? 'text' : 'password';
            pwdInput.setAttribute('type', type);
            togglePasswordBtn.querySelector('i').classList.toggle('bi-eye');
            togglePasswordBtn.querySelector('i').classList.toggle('bi-eye-slash');
        });
    }
</script>
</body>
</html>
