<?php
/**
 * Correction des classes des comptes 401 et 411
 */

require_once __DIR__ . '/db/db.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Correction Classes Comptes</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #27ae60; padding-bottom: 10px; }
        .step { background: #ecf0f1; padding: 15px; margin: 15px 0; border-left: 4px solid #3498db; border-radius: 4px; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #34495e; color: white; padding: 10px; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        .btn { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn-success { background: #27ae60; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 Correction Classes Comptes 401 & 411</h1>";

try {
    $pdo->beginTransaction();
    
    echo "<div class='step'>";
    echo "<h2>📋 Avant correction</h2>";
    
    $stmt = $pdo->query("
        SELECT id, numero_compte, libelle, classe, type_compte 
        FROM compta_comptes 
        WHERE numero_compte IN ('401', '411')
        ORDER BY numero_compte
    ");
    $comptes_avant = $stmt->fetchAll();
    
    echo "<table>
            <tr><th>N° Compte</th><th>Libellé</th><th>Classe actuelle</th><th>Type actuel</th></tr>";
    
    foreach ($comptes_avant as $c) {
        echo "<tr>
                <td>{$c['numero_compte']}</td>
                <td>{$c['libelle']}</td>
                <td style='background: #ffebee; font-weight: bold;'>{$c['classe']}</td>
                <td>{$c['type_compte']}</td>
              </tr>";
    }
    echo "</table>";
    echo "</div>";
    
    // Correction du compte 401 (Fournisseurs)
    echo "<div class='step'>";
    echo "<h2>🔨 Correction du compte 401 (Fournisseurs)</h2>";
    
    $stmt = $pdo->prepare("
        UPDATE compta_comptes 
        SET classe = '4'
        WHERE numero_compte = '401'
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✅ Compte 401 : Classe 3 → Classe 4 (TIERS - PASSIF)</p>";
    } else {
        echo "<p class='error'>⚠️ Compte 401 non trouvé ou déjà correct</p>";
    }
    echo "</div>";
    
    // Correction du compte 411 (Clients)
    echo "<div class='step'>";
    echo "<h2>🔨 Correction du compte 411 (Clients)</h2>";
    
    $stmt = $pdo->prepare("
        UPDATE compta_comptes 
        SET classe = '4'
        WHERE numero_compte = '411'
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✅ Compte 411 : Classe 3 → Classe 4 (TIERS - ACTIF)</p>";
    } else {
        echo "<p class='error'>⚠️ Compte 411 non trouvé ou déjà correct</p>";
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>✅ Après correction</h2>";
    
    $stmt = $pdo->query("
        SELECT id, numero_compte, libelle, classe, type_compte 
        FROM compta_comptes 
        WHERE numero_compte IN ('401', '411')
        ORDER BY numero_compte
    ");
    $comptes_apres = $stmt->fetchAll();
    
    echo "<table>
            <tr><th>N° Compte</th><th>Libellé</th><th>Classe corrigée</th><th>Type</th></tr>";
    
    foreach ($comptes_apres as $c) {
        echo "<tr>
                <td>{$c['numero_compte']}</td>
                <td>{$c['libelle']}</td>
                <td style='background: #e8f5e9; font-weight: bold;'>{$c['classe']}</td>
                <td>{$c['type_compte']}</td>
              </tr>";
    }
    echo "</table>";
    echo "</div>";
    
    $pdo->commit();
    
    echo "<div class='step' style='background: #d4edda; border-color: #27ae60;'>";
    echo "<h2 class='success'>🎉 Correction réussie !</h2>";
    echo "<p><strong>Impact attendu sur le bilan :</strong></p>";
    echo "<ul>
            <li>Actif : +5 202 117,85 € (411 Clients maintenant en Classe 4)</li>
            <li>Passif : +7 418 000,00 € (401 Fournisseurs maintenant en Classe 4)</li>
            <li><strong>Le bilan devrait maintenant être équilibré !</strong></li>
          </ul>";
    echo "</div>";
    
    echo "<a href='compta/balance.php' class='btn btn-success'>📊 Voir le bilan corrigé</a>";
    echo "<a href='analyser_bilan.php' class='btn'>🔍 Re-diagnostiquer</a>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<div class='step' style='border-color: #e74c3c;'>";
    echo "<h2 class='error'>❌ Erreur</h2>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "</div></body></html>";
