<?php
/**
 * Script : Attribuer TOUTES les permissions au rôle ADMIN
 * 
 * Ce script garantit que le rôle ADMIN (code='ADMIN') dispose
 * de toutes les permissions existantes dans la base de données.
 * 
 * Usage : Exécuter une seule fois ou à chaque ajout de nouvelles permissions
 */

require_once __DIR__ . '/db/db.php';

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║     ATTRIBUTION COMPLÈTE DES PERMISSIONS AU RÔLE ADMIN             ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

try {
    $pdo->beginTransaction();

    // 1. Récupérer l'ID du rôle ADMIN
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE code = 'ADMIN' LIMIT 1");
    $stmt->execute();
    $role = $stmt->fetch();

    if (!$role) {
        throw new Exception("❌ Erreur : Rôle ADMIN introuvable dans la table 'roles'");
    }

    $roleAdminId = $role['id'];
    echo "✓ Rôle ADMIN trouvé (ID: {$roleAdminId})\n\n";

    // 2. Récupérer toutes les permissions existantes
    $stmt = $pdo->query("SELECT id, code, description FROM permissions ORDER BY code");
    $permissions = $stmt->fetchAll();

    if (empty($permissions)) {
        throw new Exception("❌ Erreur : Aucune permission trouvée dans la table 'permissions'");
    }

    $totalPermissions = count($permissions);
    echo "📋 {$totalPermissions} permissions trouvées dans la base\n\n";

    // 3. Supprimer les anciennes associations (pour repartir à zéro)
    $stmt = $pdo->prepare("DELETE FROM role_permission WHERE role_id = :role_id");
    $stmt->execute(['role_id' => $roleAdminId]);
    echo "🗑️  Anciennes associations supprimées\n\n";

    // 4. Attribuer TOUTES les permissions au rôle ADMIN
    $stmt = $pdo->prepare("
        INSERT INTO role_permission (role_id, permission_id)
        VALUES (:role_id, :permission_id)
    ");

    $ajoutees = 0;
    $modules = [];

    echo "📝 Attribution des permissions...\n";
    echo str_repeat("─", 70) . "\n";

    foreach ($permissions as $perm) {
        $stmt->execute([
            'role_id' => $roleAdminId,
            'permission_id' => $perm['id']
        ]);

        // Grouper par module pour l'affichage
        $module = explode('_', $perm['code'])[0] ?? 'AUTRE';
        if (!isset($modules[$module])) {
            $modules[$module] = [];
        }
        $modules[$module][] = $perm['code'];

        $ajoutees++;
    }

    echo "\n✅ {$ajoutees} permissions attribuées avec succès !\n\n";

    // Affichage par module
    echo "📦 PERMISSIONS PAR MODULE :\n";
    echo str_repeat("─", 70) . "\n";

    foreach ($modules as $module => $perms) {
        $count = count($perms);
        echo sprintf("  %-20s : %2d permissions\n", $module, $count);
    }

    $pdo->commit();

    echo "\n" . str_repeat("═", 70) . "\n";
    echo "✅ SUCCÈS : Le rôle ADMIN dispose désormais de TOUTES les permissions\n";
    echo str_repeat("═", 70) . "\n\n";

    // 5. Vérification finale
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM role_permission
        WHERE role_id = :role_id
    ");
    $stmt->execute(['role_id' => $roleAdminId]);
    $result = $stmt->fetch();

    echo "🔍 Vérification : {$result['total']} permissions actives pour ADMIN\n";

    // 6. Liste des utilisateurs ADMIN
    echo "\n👤 UTILISATEURS AVEC RÔLE ADMIN :\n";
    echo str_repeat("─", 70) . "\n";

    $stmt = $pdo->prepare("
        SELECT u.id, u.login, u.nom_complet, u.email, u.actif
        FROM utilisateurs u
        JOIN utilisateur_role ur ON ur.utilisateur_id = u.id
        WHERE ur.role_id = :role_id
        ORDER BY u.nom_complet
    ");
    $stmt->execute(['role_id' => $roleAdminId]);
    $admins = $stmt->fetchAll();

    if (empty($admins)) {
        echo "⚠️  Aucun utilisateur n'a le rôle ADMIN actuellement\n";
    } else {
        foreach ($admins as $admin) {
            $statut = $admin['actif'] ? '✓ Actif' : '✗ Inactif';
            echo sprintf(
                "  • %-20s (%-15s) - %s - %s\n",
                $admin['nom_complet'],
                $admin['login'],
                $admin['email'],
                $statut
            );
        }
    }

    echo "\n💡 CONSEIL : Reconnectez-vous pour que les permissions soient effectives\n";
    echo "   (Déconnexion → Connexion pour recharger les permissions en session)\n\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ ERREUR : " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "═══════════════════════════════════════════════════════════════════\n";
echo "✅ Script terminé avec succès\n";
echo "═══════════════════════════════════════════════════════════════════\n";
