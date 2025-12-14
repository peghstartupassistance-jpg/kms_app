<?php
// partials/header.php

// Forcer l'encodage UTF-8 pour toutes les pages
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

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
    <meta name="csrf-token" content="<?= getCsrfToken() ?>">
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
    <link rel="stylesheet" href="<?= ($appBaseUrl !== '' ? $appBaseUrl : '') ?>/assets/css/custom.css">
    <link rel="stylesheet" href="<?= ($appBaseUrl !== '' ? $appBaseUrl : '') ?>/assets/css/modern-lists.css">
    <link rel="stylesheet" href="<?= ($appBaseUrl !== '' ? $appBaseUrl : '') ?>/assets/css/modern-forms.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-kms sticky-top">
    <div class="container-fluid">
        <!-- Marque/Logo -->
        <a class="navbar-brand navbar-brand-kms" href="<?= htmlspecialchars($homeUrl) ?>">
            <img src="<?= ($appBaseUrl !== '' ? $appBaseUrl : '') ?>/assets/img/logo-kms.png" alt="KMS" height="36" class="brand-logo">
            <div class="brand-text">
                <div class="brand-name">Kenne Multi-Services</div>
                <div class="brand-subtext">Système de Gestion</div>
            </div>
        </a>

        <!-- Sidebar toggle (horizontal width) -->
        <button class="sidebar-toggle-btn ms-3" id="toggleSidebarBtn" title="Plier/Déplier la sidebar">
            <i class="bi bi-layout-sidebar"></i>
            <span class="d-none d-md-inline">Sidebar</span>
        </button>
        <!-- Sidebar toggle (vertical height) -->
        <button class="sidebar-toggle-vertical-btn ms-2" id="toggleSidebarVerticalBtn" title="Plier verticalement la sidebar">
            <i class="bi bi-arrows-collapse"></i>
            <span class="d-none d-md-inline">Vertical</span>
        </button>

        <!-- Spacer -->
        <div class="navbar-spacer"></div>

        <!-- Actions à droite -->
        <div class="navbar-actions">
            <?php if ($utilisateur): ?>
                <!-- Profil utilisateur -->
                <div class="navbar-profile">
                    <div class="profile-info">
                        <div class="profile-name"><?= htmlspecialchars($utilisateur['nom_complet'] ?? $utilisateur['login']) ?></div>
                        <div class="profile-role">
                            <?php
                            $roles = [];
                            if ($_SESSION['role'] ?? false) {
                                $roles[] = $_SESSION['role'];
                            }
                            echo count($roles) > 0 ? implode(' • ', $roles) : 'Utilisateur';
                            ?>
                        </div>
                    </div>
                    <div class="profile-avatar">
                        <i class="bi bi-person-circle"></i>
                    </div>
                </div>

                <!-- Boutons actions -->
                <div class="navbar-buttons">
                    <a href="<?= htmlspecialchars($logoutUrl) ?>" class="btn-logout" title="Déconnexion">
                        <i class="bi bi-box-arrow-right"></i>
                        <span class="btn-logout-text">Déconnexion</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="layout-with-sidebar" id="layoutRoot">
