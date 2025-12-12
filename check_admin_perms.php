<?php
$pdo = new PDO('mysql:host=localhost;dbname=kms_gestion;charset=utf8mb4', 'root', '');

echo "=== Permissions for ADMIN role (ID 1) ===\n";
$result = $pdo->query("
    SELECT rp.*, p.code, p.description 
    FROM role_permission rp 
    JOIN permissions p ON p.id = rp.permission_id 
    WHERE rp.role_id = 1
    ORDER BY p.code
")->fetchAll(PDO::FETCH_ASSOC);

echo "Total permissions: " . count($result) . "\n";
foreach ($result as $perm) {
    echo "  - " . $perm['code'] . ": " . $perm['description'] . "\n";
}

echo "\n=== All Permissions in Database ===\n";
$all = $pdo->query("SELECT code FROM permissions ORDER BY code")->fetchAll(PDO::FETCH_COLUMN);
echo implode("\n", $all);
