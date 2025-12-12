<?php
require __DIR__ . '/db/db.php';

echo "=== Vérification des permissions comptabilité ===\n\n";

// Vérifier les permissions comptabilité
$stmt = $pdo->query("SELECT id, code FROM permissions WHERE code LIKE '%COMPTABILITE%'");
$compta_perms = $stmt->fetchAll();

if (empty($compta_perms)) {
    echo "❌ Aucune permission comptabilité trouvée\n\n";
} else {
    echo "✓ Permissions comptabilité trouvées :\n";
    foreach ($compta_perms as $p) {
        echo "  - ID: {$p['id']}, CODE: {$p['code']}\n";
    }
    echo "\n";
}

// Vérifier les tables de permissions
echo "=== Tables de permissions ===\n";
$stmt = $pdo->query("SHOW TABLES LIKE '%permission%'");
$tables = $stmt->fetchAll();
foreach ($tables as $t) {
    echo "  ✓ Table: " . $t[0] . "\n";
}

echo "\n=== Vérifier votre utilisateur ===\n";
$stmt = $pdo->query("SELECT id, login, nom_complet FROM utilisateurs WHERE actif = 1 LIMIT 3");
$users = $stmt->fetchAll();
foreach ($users as $u) {
    echo "  - ID: {$u['id']}, Login: {$u['login']}, Nom: {$u['nom_complet']}\n";
}

echo "\n=== Vérifier les rôles admin ===\n";
$stmt = $pdo->query("SELECT id, code, libelle FROM roles WHERE code LIKE '%ADMIN%' OR code LIKE '%admin%' LIMIT 5");
$roles = $stmt->fetchAll();
if (empty($roles)) {
    echo "  ❌ Aucun rôle admin trouvé\n";
} else {
    foreach ($roles as $r) {
        echo "  - ID: {$r['id']}, CODE: {$r['code']}, LIBELLE: {$r['libelle']}\n";
    }
}
?>
