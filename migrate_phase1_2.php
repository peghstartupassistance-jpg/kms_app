<?php
/**
 * Migration BD pour Phase 1.2 - Signature BL Électronique
 * Ajoute 3 colonnes à bons_livraison
 */

require_once __DIR__ . '/security.php';
exigerConnexion();
exigerPermission('ADMIN');

global $pdo;

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Migration BD - Phase 1.2</h2>";

try {
    // Vérifier si colonnes existent déjà
    $stmt = $pdo->query("DESCRIBE bons_livraison");
    $columns = array_column($stmt->fetchAll(), 'Field');
    
    $colonnesAjouter = [];
    
    if (!in_array('signature', $columns)) {
        $colonnesAjouter[] = 'signature LONGBLOB DEFAULT NULL AFTER date_bl';
    }
    if (!in_array('signature_date', $columns)) {
        $colonnesAjouter[] = 'signature_date DATETIME DEFAULT NULL AFTER signature';
    }
    if (!in_array('signature_client_nom', $columns)) {
        $colonnesAjouter[] = 'signature_client_nom VARCHAR(255) DEFAULT NULL AFTER signature_date';
    }
    
    if (empty($colonnesAjouter)) {
        echo "<div class='alert alert-info'>✅ Colonnes déjà présentes</div>";
    } else {
        $sql = "ALTER TABLE bons_livraison ADD COLUMN " . implode(", ADD COLUMN ", $colonnesAjouter);
        
        echo "<p>Exécution:</p>";
        echo "<pre>" . htmlspecialchars($sql) . "</pre>";
        
        $pdo->exec($sql);
        
        echo "<div class='alert alert-success'>";
        echo "✅ Colonnes ajoutées avec succès:<br>";
        foreach ($colonnesAjouter as $col) {
            echo "  • " . htmlspecialchars($col) . "<br>";
        }
        echo "</div>";
    }
    
    // Vérifier structure
    echo "<h3>Structure bons_livraison après migration:</h3>";
    $stmt = $pdo->query("DESCRIBE bons_livraison");
    $structure = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    foreach ($structure as $row) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div class='alert alert-success mt-4'>";
    echo "✅ Migration Phase 1.2 réussie!<br>";
    echo "<a href='" . url_for('index.php') . "'>Retour dashboard</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "❌ Erreur: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
