<?php
// security.php

// 0. Forcer UTF-8 pour TOUTES les pages
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
mb_internal_encoding('UTF-8');

// 1. Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Chemin racine de l'application (adapter si tu changes le dossier)
if (!defined('BASE_PATH')) {
    // Ton projet est dans http://localhost/kms_app/
    define('BASE_PATH', '/kms_app');
}

/**
 * Génère une URL interne correcte
 * Ex : url_for('produits/list.php') → /kms_app/produits/list.php
 */
function url_for(string $path): string
{
    $path = ltrim($path, '/');
    return BASE_PATH . '/' . $path;
}

// 3. Connexion PDO
require_once __DIR__ . '/db/db.php';

/**
 * Retourne l'utilisateur connecté ou null.
 */
function utilisateurConnecte(): ?array
{
    return $_SESSION['utilisateur'] ?? null;
}

/**
 * Exige qu'un utilisateur soit connecté, sinon redirige vers login.
 */
function exigerConnexion(): void
{
    if (!utilisateurConnecte()) {
        header('Location: ' . url_for('login.php'));
        exit;
    }
}

/**
 * Exige une permission.
 * EXCEPTION : L'utilisateur "admin" ou avec rôle ADMIN a TOUJOURS accès à tout.
 */
function exigerPermission(string $codePermission): void
{
    exigerConnexion();

    $utilisateur = utilisateurConnecte();
    
    // ADMIN a accès à tout
    if ($utilisateur['login'] === 'admin') {
        return;
    }

    // Vérifier si l'utilisateur a le rôle ADMIN
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT r.code 
        FROM roles r
        JOIN utilisateur_role ur ON r.id = ur.role_id
        WHERE ur.utilisateur_id = ? AND r.code = 'ADMIN'
    ");
    $stmt->execute([$utilisateur['id']]);
    if ($stmt->fetch()) {
        return; // Utilisateur a le rôle ADMIN
    }

    // Vérification normale des permissions
    $permissions = $_SESSION['permissions'] ?? [];
    if (!in_array($codePermission, $permissions, true)) {
        http_response_code(403);
        echo "Accès refusé.";
        exit;
    }
}

/**
 * CSRF : génération token.
 */
function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF : vérification token.
 */
function verifierCsrf(string $tokenFormulaire): void
{
    $tokenSession = $_SESSION['csrf_token'] ?? '';
    if (!$tokenSession || !hash_equals($tokenSession, $tokenFormulaire)) {
        http_response_code(400);
        echo "Requête invalide (CSRF).";
        exit;
    }
}

/**
 * Charge les infos & permissions en session après login.
 */
function chargerPermissionsUtilisateur(int $utilisateurId): void
{
    global $pdo;

    // Utilisateur
    $stmt = $pdo->prepare("
        SELECT id, login, nom_complet, email
        FROM utilisateurs
        WHERE id = :id AND actif = 1
    ");
    $stmt->execute(['id' => $utilisateurId]);
    $utilisateur = $stmt->fetch();

    if (!$utilisateur) {
        $_SESSION['utilisateur'] = null;
        $_SESSION['permissions'] = [];
        return;
    }

    // Permissions
    $sql = "
        SELECT DISTINCT p.code
        FROM permissions p
        JOIN role_permission rp ON rp.permission_id = p.id
        JOIN utilisateur_role ur ON ur.role_id = rp.role_id
        WHERE ur.utilisateur_id = :uid
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['uid' => $utilisateurId]);
    $permissions = array_column($stmt->fetchAll(), 'code');

    $_SESSION['utilisateur'] = $utilisateur;
    $_SESSION['permissions'] = $permissions;
}

/**
 * IP client pour le journal de connexions.
 */
function getClientIp(): string
{
    $keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR'
    ];

    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ipList = explode(',', $_SERVER[$key]);
            return trim($ipList[0]);
        }
    }

    return 'UNKNOWN';
}

/**
 * Enregistre une ligne dans connexions_utilisateur.
 */
function enregistrerConnexion(int $utilisateurId, int $succes): void
{
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO connexions_utilisateur (utilisateur_id, date_connexion, adresse_ip, user_agent, succes)
        VALUES (:uid, NOW(), :ip, :ua, :succes)
    ");

    $stmt->execute([
        'uid'    => $utilisateurId,
        'ip'     => getClientIp(),
        'ua'     => substr($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN', 0, 255),
        'succes' => $succes ? 1 : 0,
    ]);
}
