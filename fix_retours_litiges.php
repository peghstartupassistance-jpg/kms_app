<?php
require_once __DIR__ . '/db/db.php';

echo "=== VÉRIFICATION ET CORRECTION retours_litiges ===\n\n";

// Vérifier structure actuelle
$stmt = $pdo->query("DESCRIBE retours_litiges");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Colonnes actuelles:\n";
foreach ($columns as $col) {
    echo "  - $col\n";
}

// Vérifier si montant_rembourse existe
if (!in_array('montant_rembourse', $columns)) {
    echo "\n⚠️ Colonne 'montant_rembourse' manquante\n";
    echo "Ajout de la colonne...\n";
    
    try {
        $pdo->exec("ALTER TABLE retours_litiges ADD COLUMN montant_rembourse DECIMAL(15,2) DEFAULT 0.00 AFTER solution");
        echo "✅ Colonne ajoutée avec succès!\n";
    } catch (PDOException $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n✓ Colonne 'montant_rembourse' existe déjà\n";
}

// Vérifier si montant_avoir existe
if (!in_array('montant_avoir', $columns)) {
    echo "\n⚠️ Colonne 'montant_avoir' manquante\n";
    echo "Ajout de la colonne...\n";
    
    try {
        $pdo->exec("ALTER TABLE retours_litiges ADD COLUMN montant_avoir DECIMAL(15,2) DEFAULT 0.00 AFTER montant_rembourse");
        echo "✅ Colonne ajoutée avec succès!\n";
    } catch (PDOException $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n✓ Colonne 'montant_avoir' existe déjà\n";
}

// Vérifier si date_resolution existe
if (!in_array('date_resolution', $columns)) {
    echo "\n⚠️ Colonne 'date_resolution' manquante\n";
    echo "Ajout de la colonne...\n";
    
    try {
        $pdo->exec("ALTER TABLE retours_litiges ADD COLUMN date_resolution DATETIME DEFAULT NULL AFTER montant_avoir");
        echo "✅ Colonne ajoutée avec succès!\n";
    } catch (PDOException $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n✓ Colonne 'date_resolution' existe déjà\n";
}

// Vérifier le type_probleme existe
if (!in_array('type_probleme', $columns)) {
    echo "\n⚠️ Colonne 'type_probleme' manquante\n";
    echo "Ajout de la colonne...\n";
    
    try {
        $pdo->exec("ALTER TABLE retours_litiges ADD COLUMN type_probleme ENUM('DEFAUT_PRODUIT','ERREUR_LIVRAISON','INSATISFACTION_CLIENT','AUTRE') DEFAULT 'AUTRE' AFTER motif");
        echo "✅ Colonne ajoutée avec succès!\n";
    } catch (PDOException $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n✓ Colonne 'type_probleme' existe déjà\n";
}

// Structure finale
echo "\n=== STRUCTURE FINALE ===\n";
$stmt = $pdo->query("DESCRIBE retours_litiges");
while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  ✓ {$col['Field']} ({$col['Type']})\n";
}

echo "\n✅ Table retours_litiges mise à jour!\n";
