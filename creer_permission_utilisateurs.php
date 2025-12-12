<?php
// creer_permission_utilisateurs.php - Créer la permission UTILISATEURS_GERER
require_once 'db/db.php';

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║     CRÉATION PERMISSION UTILISATEURS_GERER                       ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

try {
    $pdo->beginTransaction();
    
    // 1. Créer la permission
    echo "📝 Création de la permission UTILISATEURS_GERER...\n";
    $stmt = $pdo->prepare("
        INSERT INTO permissions (code) 
        VALUES ('UTILISATEURS_GERER')
        ON DUPLICATE KEY UPDATE code = code
    ");
    $stmt->execute();
    
    $stmt = $pdo->query("SELECT id FROM permissions WHERE code = 'UTILISATEURS_GERER'");
    $permissionId = $stmt->fetchColumn();
    
    echo "   ✅ Permission créée (ID: $permissionId)\n\n";
    
    // 2. Attribuer au rôle ADMIN
    echo "🔐 Attribution de la permission aux rôles...\n";
    
    $stmt = $pdo->query("SELECT id, code, nom FROM roles WHERE code IN ('ADMIN', 'DIRECTION') ORDER BY code");
    $roles = $stmt->fetchAll();
    
    foreach ($roles as $role) {
        $stmt = $pdo->prepare("
            INSERT INTO role_permission (role_id, permission_id)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE role_id = role_id
        ");
        $stmt->execute([$role['id'], $permissionId]);
        echo "   ✅ {$role['nom']} ({$role['code']})\n";
    }
    
    echo "\n";
    
    // 3. Vérification
    echo "📊 VÉRIFICATION - Qui a accès maintenant :\n";
    echo str_repeat("─", 70) . "\n";
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.login, u.nom_complet, r.nom as role_nom, u.actif
        FROM utilisateurs u
        JOIN utilisateur_role ur ON u.id = ur.utilisateur_id
        JOIN roles r ON ur.role_id = r.id
        JOIN role_permission rp ON r.id = rp.role_id
        WHERE rp.permission_id = ?
        ORDER BY u.actif DESC, r.nom, u.login
    ");
    $stmt->execute([$permissionId]);
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "   ⚠️  Aucun utilisateur (normal si aucun utilisateur n'a ces rôles)\n";
    } else {
        foreach ($users as $user) {
            $statut = $user['actif'] ? '🟢 Actif' : '🔴 Inactif';
            echo "   {$statut} - {$user['login']} ({$user['nom_complet']}) - {$user['role_nom']}\n";
        }
    }
    
    echo "\n";
    echo "🔓 RAPPEL : L'utilisateur 'admin' a TOUJOURS accès (bypass)\n";
    
    $pdo->commit();
    
    echo "\n";
    echo "══════════════════════════════════════════════════════════════════\n";
    echo "✅ Permission UTILISATEURS_GERER créée et attribuée avec succès !\n";
    echo "══════════════════════════════════════════════════════════════════\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ ERREUR : " . $e->getMessage() . "\n";
    exit(1);
}
