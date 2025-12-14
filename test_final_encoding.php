<?php
// test_final_encoding.php - Test final de l'encodage UTF-8
require_once 'db/db.php';

echo "🎯 TEST FINAL D'ENCODAGE UTF-8\n";
echo str_repeat("=", 70) . "\n\n";

echo "📋 CLIENTS:\n";
$result = $pdo->query("SELECT nom FROM clients WHERE nom LIKE '%é%' LIMIT 5");
foreach ($result as $row) {
    echo "   ✓ " . $row['nom'] . "\n";
}

echo "\n📊 COMPTES COMPTABLES:\n";
$result = $pdo->query("SELECT numero_compte, libelle FROM compta_comptes WHERE libelle LIKE '%é%' LIMIT 5");
foreach ($result as $row) {
    echo "   ✓ " . $row['numero_compte'] . " - " . $row['libelle'] . "\n";
}

echo "\n🎓 FORMATIONS:\n";
$result = $pdo->query("SELECT nom FROM formations WHERE nom LIKE '%é%' OR nom LIKE '%è%' LIMIT 3");
foreach ($result as $row) {
    echo "   ✓ " . $row['nom'] . "\n";
}

echo "\n🛒 PRODUITS:\n";
$result = $pdo->query("SELECT designation FROM catalogue_produits WHERE description LIKE '%é%' LIMIT 5");
foreach ($result as $row) {
    echo "   ✓ " . $row['designation'] . "\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "✅ Si tous les accents s'affichent correctement ci-dessus,\n";
echo "   l'encodage UTF-8 est parfaitement configuré !\n";
?>
