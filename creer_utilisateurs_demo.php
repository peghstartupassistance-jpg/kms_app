<?php
/**
 * Script : Créer 2 utilisateurs pour chaque rôle
 * 
 * Génère des utilisateurs de démonstration avec leurs rôles
 */

require_once __DIR__ . '/db/db.php';

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║         CRÉATION UTILISATEURS DÉMO (2 par rôle)                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

try {
    $pdo->beginTransaction();

    // Récupérer tous les rôles
    $stmt = $pdo->query("SELECT id, code, nom FROM roles ORDER BY id");
    $roles = $stmt->fetchAll();

    $utilisateurs_data = [
        'ADMIN' => [
            ['login' => 'admin', 'nom_complet' => 'Administrateur KMS', 'email' => 'admin@kms.local'],
            ['login' => 'admin2', 'nom_complet' => 'Administrateur Système', 'email' => 'admin2@kms.local']
        ],
        'SHOWROOM' => [
            ['login' => 'showroom1', 'nom_complet' => 'Marie Kouadio', 'email' => 'marie.kouadio@kms.local'],
            ['login' => 'showroom2', 'nom_complet' => 'Yao Kouassi', 'email' => 'yao.kouassi@kms.local']
        ],
        'TERRAIN' => [
            ['login' => 'terrain1', 'nom_complet' => 'Konan Yao', 'email' => 'konan.yao@kms.local'],
            ['login' => 'terrain2', 'nom_complet' => 'Aya N\'Guessan', 'email' => 'aya.nguessan@kms.local']
        ],
        'MAGASINIER' => [
            ['login' => 'magasin1', 'nom_complet' => 'Ibrahim Traoré', 'email' => 'ibrahim.traore@kms.local'],
            ['login' => 'magasin2', 'nom_complet' => 'Moussa Diallo', 'email' => 'moussa.diallo@kms.local']
        ],
        'CAISSIER' => [
            ['login' => 'caisse1', 'nom_complet' => 'Aminata Koné', 'email' => 'aminata.kone@kms.local'],
            ['login' => 'caisse2', 'nom_complet' => 'Fatou Camara', 'email' => 'fatou.camara@kms.local']
        ],
        'DIRECTION' => [
            ['login' => 'direction1', 'nom_complet' => 'Directeur Général', 'email' => 'dg@kms.local'],
            ['login' => 'direction2', 'nom_complet' => 'Directeur Adjoint', 'email' => 'da@kms.local']
        ]
    ];

    $password_hash = password_hash('kms2025', PASSWORD_DEFAULT);
    $created = 0;
    $updated = 0;

    echo "📝 Création des utilisateurs...\n";
    echo str_repeat("─", 70) . "\n\n";

    foreach ($roles as $role) {
        $role_code = $role['code'];
        $role_nom = $role['nom'];
        
        if (!isset($utilisateurs_data[$role_code])) {
            continue;
        }

        echo "🔹 Rôle : {$role_nom} ({$role_code})\n";

        foreach ($utilisateurs_data[$role_code] as $user_data) {
            // Vérifier si l'utilisateur existe déjà
            $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE login = ?");
            $stmt->execute([$user_data['login']]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Mettre à jour
                $stmt = $pdo->prepare("
                    UPDATE utilisateurs 
                    SET nom_complet = ?, email = ?, actif = 1
                    WHERE id = ?
                ");
                $stmt->execute([
                    $user_data['nom_complet'],
                    $user_data['email'],
                    $existing['id']
                ]);
                $user_id = $existing['id'];
                echo "   ✓ Mis à jour : {$user_data['login']} - {$user_data['nom_complet']}\n";
                $updated++;
            } else {
                // Créer nouvel utilisateur
                $stmt = $pdo->prepare("
                    INSERT INTO utilisateurs (login, mot_de_passe_hash, nom_complet, email, actif, date_creation)
                    VALUES (?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([
                    $user_data['login'],
                    $password_hash,
                    $user_data['nom_complet'],
                    $user_data['email']
                ]);
                $user_id = $pdo->lastInsertId();
                echo "   ✓ Créé : {$user_data['login']} - {$user_data['nom_complet']}\n";
                $created++;
            }

            // Attribuer le rôle (supprimer les anciens rôles d'abord)
            $stmt = $pdo->prepare("DELETE FROM utilisateur_role WHERE utilisateur_id = ?");
            $stmt->execute([$user_id]);

            $stmt = $pdo->prepare("INSERT INTO utilisateur_role (utilisateur_id, role_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $role['id']]);
        }

        echo "\n";
    }

    $pdo->commit();

    echo str_repeat("═", 70) . "\n";
    echo "✅ SUCCÈS : {$created} utilisateurs créés, {$updated} mis à jour\n";
    echo str_repeat("═", 70) . "\n\n";

    // Afficher le récapitulatif
    echo "📋 RÉCAPITULATIF DES COMPTES\n";
    echo str_repeat("─", 70) . "\n";
    echo sprintf("%-15s | %-25s | %-30s\n", "Login", "Nom complet", "Email");
    echo str_repeat("─", 70) . "\n";

    $stmt = $pdo->query("
        SELECT u.login, u.nom_complet, u.email, r.nom as role_nom
        FROM utilisateurs u
        LEFT JOIN utilisateur_role ur ON u.id = ur.utilisateur_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.actif = 1
        ORDER BY r.id, u.login
    ");

    $current_role = '';
    while ($user = $stmt->fetch()) {
        if ($user['role_nom'] != $current_role) {
            $current_role = $user['role_nom'];
            echo "\n🔹 " . strtoupper($current_role) . "\n";
        }
        echo sprintf("%-15s | %-25s | %-30s\n", 
            $user['login'], 
            $user['nom_complet'], 
            $user['email']
        );
    }

    echo "\n" . str_repeat("═", 70) . "\n";
    echo "🔑 CONNEXION\n";
    echo str_repeat("═", 70) . "\n";
    echo "Login    : Utilisez l'un des logins ci-dessus\n";
    echo "Password : kms2025\n";
    echo str_repeat("═", 70) . "\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ ERREUR : " . $e->getMessage() . "\n\n";
    exit(1);
}
