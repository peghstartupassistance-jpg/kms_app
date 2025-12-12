<?php
// partials/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$utilisateur = $_SESSION['utilisateur'] ?? null;

/**
 * Calcul dynamique de la base URL de l'application
 * Exemple :
 *  - DocumentRoot : C:/xampp/htdocs
 *  - App root FS : C:/xampp/htdocs/kms_app
 *  => $appBaseUrl = '/kms_app'
 *
 * Si l'app est à la racine (htdocs directement) :
 *  - App root FS = DocumentRoot
 *  => $appBaseUrl = ''
 */
$docRoot   = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$appRootFs = str_replace('\\', '/', dirname(__DIR__)); // remonte d'un niveau depuis /partials

if (strpos($appRootFs, $docRoot) === 0) {
    $relative   = substr($appRootFs, strlen($docRoot)); // ex : '/kms_app' ou ''
    $appBaseUrl = rtrim($relative, '/');
} else {
    // Fallback : on considère que l'app est à la racine
    $appBaseUrl = '';
}

// URLs dynamiques
$homeUrl   = ($appBaseUrl !== '' ? $appBaseUrl : '') . '/index.php';
$logoutUrl = ($appBaseUrl !== '' ? $appBaseUrl : '') . '/logout.php';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>KMS – Back-office commercial</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >

    <!-- Icônes (Bootstrap Icons) -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    >

    <!-- Styles personnalisés -->
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="<?= htmlspecialchars($homeUrl) ?>">
            <img src="/kms_app/assets/img/logo-kms.png" alt="KMS" height="32" class="me-2">
            <span class="fw-semibold">Kenne Multi-Services</span>
        </a>

        <div class="d-flex align-items-center">
            <?php if ($utilisateur): ?>
                <span class="text-light small me-3">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($utilisateur['nom_complet'] ?? $utilisateur['login']) ?>
                </span>
                <a href="<?= htmlspecialchars($logoutUrl) ?>" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-box-arrow-right me-1"></i> Déconnexion
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="d-flex">
