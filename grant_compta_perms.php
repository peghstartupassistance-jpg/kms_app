<?php
$pdo = new PDO('mysql:host=localhost;dbname=kms_gestion;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$compta_perms = [
    ['code' => 'COMPTABILITE_LIRE', 'description' => 'Consulter le module comptabilité'],
    ['code' => 'COMPTABILITE_ECRIRE', 'description' => 'Enregistrer des écritures comptables']
];

$roleId = 1; // ADMIN

foreach ($compta_perms as $perm) {
    // Check existence
    $stmt = $pdo->prepare('SELECT id FROM permissions WHERE code = :code');
    $stmt->execute([':code' => $perm['code']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $permId = (int)$row['id'];
        echo "Permission {$perm['code']} exists (id={$permId}).\n";
    } else {
        $ins = $pdo->prepare('INSERT INTO permissions (code, description) VALUES (:code, :desc)');
        $ins->execute([':code' => $perm['code'], ':desc' => $perm['description']]);
        $permId = (int)$pdo->lastInsertId();
        echo "Inserted permission {$perm['code']} (id={$permId}).\n";
    }

    // Assign to role if not already
    $chk = $pdo->prepare('SELECT 1 FROM role_permission WHERE role_id = :role AND permission_id = :perm LIMIT 1');
    $chk->execute([':role' => $roleId, ':perm' => $permId]);
    if ($chk->fetchColumn()) {
        echo "Role {$roleId} already has permission {$perm['code']}.\n";
    } else {
        $insrp = $pdo->prepare('INSERT INTO role_permission (role_id, permission_id) VALUES (:role, :perm)');
        $insrp->execute([':role' => $roleId, ':perm' => $permId]);
        echo "Assigned permission {$perm['code']} to role {$roleId}.\n";
    }
}

echo "\nDone.\n";
