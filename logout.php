<?php
// logout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// On nettoie la session
$_SESSION = [];
session_unset();
session_destroy();

// Optionnel : supprimer cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

/**
 * Calcul dynamique de l'URL de login
 * Exemple en local :
 *  - DocumentRoot : C:/xampp/htdocs
 *  - App root FS  : C:/xampp/htdocs/kms_app
 *  => /kms_app/login.php
 */
$docRoot   = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$appRootFs = str_replace('\\', '/', __DIR__); // dossier racine de l'app

if (strpos($appRootFs, $docRoot) === 0) {
    $relative   = substr($appRootFs, strlen($docRoot)); // ex : '/kms_app' ou ''
    $appBaseUrl = rtrim($relative, '/');
} else {
    // fallback si jamais les chemins ne matchent pas
    $appBaseUrl = '';
}

$loginUrl = ($appBaseUrl !== '' ? $appBaseUrl : '') . '/login.php';

// Redirection vers la page de login
header('Location: ' . $loginUrl);
exit;
