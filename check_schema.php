<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=kms_gestion;charset=utf8mb4', 'root', '');

// Vérifier le schéma de la table ventes
echo "📊 STRUCTURE TABLE VENTES:\n";
echo "════════════════════════════════════════════\n\n";

$stmt = $pdo->query("DESCRIBE ventes");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    $field = str_pad($col['Field'], 25);
    $type = str_pad($col['Type'], 20);
    $null = str_pad($col['Null'], 5);
    $default = str_pad($col['Default'] ?? 'NULL', 20);
    echo "$field | $type | $null | $default\n";
}

echo "\n\n✓ Vérification colonnes clés:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM ventes LIKE 'statut_encaissement'");
if ($stmt->rowCount() > 0) {
    echo "  ✅ statut_encaissement existe\n";
} else {
    echo "  ❌ statut_encaissement N'EXISTE PAS\n";
}

$stmt = $pdo->query("SHOW COLUMNS FROM ventes LIKE 'journal_caisse_id'");
if ($stmt->rowCount() > 0) {
    echo "  ✅ journal_caisse_id existe\n";
} else {
    echo "  ❌ journal_caisse_id N'EXISTE PAS\n";
}

echo "\n\nÉchantillon data vente #90:\n";
$stmt = $pdo->prepare("SELECT id, numero, montant_total_ttc, statut, statut_encaissement, journal_caisse_id FROM ventes WHERE id = 90");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
foreach ($row as $k => $v) {
    echo "  $k = " . var_export($v, true) . "\n";
}
?>
