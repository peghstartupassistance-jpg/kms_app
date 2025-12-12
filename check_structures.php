<?php
require_once __DIR__ . '/db/db.php';

echo "=== VÉRIFICATION STRUCTURES TABLES ===\n\n";

// 1. Vérifier les tables liées aux ventes
echo "1. Tables contenant 'vente' ou 'ligne':\n";
$stmt = $pdo->query("SHOW TABLES LIKE '%vente%'");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo "  ✓ {$row[0]}\n";
}
$stmt = $pdo->query("SHOW TABLES LIKE '%ligne%'");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo "  ✓ {$row[0]}\n";
}

// 2. Structure de la table ventes (si elle existe)
echo "\n2. Structure table 'ventes':\n";
try {
    $stmt = $pdo->query("DESCRIBE ventes");
    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
} catch (PDOException $e) {
    echo "  ✗ Table 'ventes' n'existe pas\n";
}

// 3. Chercher la table des lignes de ventes
echo "\n3. Recherche table lignes de ventes...\n";
$tables_possibles = ['lignes_ventes', 'ventes_lignes', 'vente_lignes', 'lignes_vente'];
foreach ($tables_possibles as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        echo "  ✓ Table '$table' existe!\n";
        while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "    - {$col['Field']}\n";
        }
        break;
    } catch (PDOException $e) {
        echo "  ✗ Table '$table' n'existe pas\n";
    }
}

// 4. Structure retours_litiges
echo "\n4. Structure table 'retours_litiges':\n";
try {
    $stmt = $pdo->query("DESCRIBE retours_litiges");
    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
} catch (PDOException $e) {
    echo "  ✗ Erreur: " . $e->getMessage() . "\n";
}

echo "\n=== FIN VÉRIFICATION ===\n";
