<?php
// check_utilisateurs_perm.php - Vérifier qui a accès à la gestion des utilisateurs
require_once 'db/db.php';

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║     QUI A ACCÈS À LA GESTION DES UTILISATEURS ?                 ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// 0. Lister toutes les permissions existantes
echo "📋 PERMISSIONS EXISTANTES :\n";
$stmt = $pdo->query("SELECT code FROM permissions ORDER BY code");
$allPerms = $stmt->fetchAll();
foreach ($allPerms as $p) {
    echo "   - {$p['code']}\n";
}
echo "\n";

// 1. Vérifier la permission
$stmt = $pdo->query("SELECT id, code FROM permissions WHERE code = 'UTILISATEURS_GERER'");
$permission = $stmt->fetch();

if (!$permission) {
    echo "❌ La permission UTILISATEURS_GERER n'existe pas !\n";
    exit;
}

echo "✅ Permission : {$permission['code']}\n\n";

// 2. Rôles ayant cette permission
echo "📋 RÔLES AYANT CETTE PERMISSION :\n";
echo str_repeat("─", 70) . "\n";

$stmt = $pdo->prepare("
    SELECT r.id, r.code, r.nom
    FROM roles r
    JOIN role_permission rp ON r.id = rp.role_id
    WHERE rp.permission_id = ?
    ORDER BY r.nom
");
$stmt->execute([$permission['id']]);
$roles = $stmt->fetchAll();

if (empty($roles)) {
    echo "   ⚠️  Aucun rôle n'a cette permission\n\n";
} else {
    foreach ($roles as $role) {
        echo "   ✓ {$role['nom']} ({$role['code']})\n";
    }
    echo "\n";
}

// 3. Utilisateurs ayant accès
echo "👥 UTILISATEURS AYANT ACCÈS :\n";
echo str_repeat("─", 70) . "\n";

if (empty($roles)) {
    echo "   Aucun utilisateur (aucun rôle n'a la permission)\n\n";
} else {
    $roleIds = array_column($roles, 'id');
    $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.login, u.nom_complet, r.nom as role_nom, u.actif
        FROM utilisateurs u
        JOIN utilisateur_role ur ON u.id = ur.utilisateur_id
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.role_id IN ($placeholders)
        ORDER BY u.actif DESC, u.login
    ");
    $stmt->execute($roleIds);
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "   ⚠️  Aucun utilisateur assigné à ces rôles\n\n";
    } else {
        foreach ($users as $user) {
            $statut = $user['actif'] ? '🟢 Actif' : '🔴 Inactif';
            echo "   {$statut} - {$user['login']} ({$user['nom_complet']}) - Rôle: {$user['role_nom']}\n";
        }
        echo "\n";
    }
}

// 4. Accès exceptionnel ADMIN
echo "🔓 ACCÈS EXCEPTIONNEL :\n";
echo str_repeat("─", 70) . "\n";

$stmt = $pdo->query("
    SELECT u.login, u.nom_complet, r.code as role_code, u.actif
    FROM utilisateurs u
    LEFT JOIN utilisateur_role ur ON u.id = ur.utilisateur_id
    LEFT JOIN roles r ON ur.role_id = r.id
    WHERE u.login = 'admin' OR r.code = 'ADMIN'
    ORDER BY u.login
");
$admins = $stmt->fetchAll();

echo "   ℹ️  Les utilisateurs suivants ont TOUJOURS accès (bypass permissions) :\n\n";
foreach ($admins as $admin) {
    $statut = $admin['actif'] ? '🟢 Actif' : '🔴 Inactif';
    $roleInfo = $admin['role_code'] ? " - Rôle: {$admin['role_code']}" : '';
    echo "   {$statut} - {$admin['login']} ({$admin['nom_complet']}){$roleInfo}\n";
}

echo "\n";
echo "══════════════════════════════════════════════════════════════════\n";
echo "✅ Analyse terminée\n";
echo "══════════════════════════════════════════════════════════════════\n";
