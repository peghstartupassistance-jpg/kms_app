<?php
/**
 * Correction automatique des comptes pour conformité SYSCOHADA
 */

require_once __DIR__ . '/db/db.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Correction Comptes SYSCOHADA</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 30px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #27ae60; padding-bottom: 10px; }
        .step { background: #ecf0f1; padding: 15px; margin: 15px 0; border-left: 4px solid #3498db; border-radius: 4px; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #34495e; color: white; padding: 10px; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        .btn { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 Correction Comptes SYSCOHADA</h1>";

$errors = [];
$corrections = [];

try {
    $pdo->beginTransaction();
    
    // =========================================================================
    // ÉTAPE 1 : Identifier les comptes problématiques
    // =========================================================================
    echo "<div class='step'>";
    echo "<h2>📋 ÉTAPE 1 : Analyse des comptes</h2>";
    
    // Mapping des corrections à effectuer
    $mappings = [
        '100' => ['nouveau_numero' => '10', 'nouveau_libelle' => 'Capital', 'classe' => '1', 'type' => 'PASSIF'],
        '500' => ['nouveau_numero' => '11', 'nouveau_libelle' => 'Réserves', 'classe' => '1', 'type' => 'PASSIF'],
        '530' => ['nouveau_numero' => '571', 'nouveau_libelle' => 'Caisse siège social', 'classe' => '5', 'type' => 'ACTIF'],
    ];
    
    echo "<table>
            <tr>
                <th>Ancien</th>
                <th>Nouveau</th>
                <th>Libellé</th>
                <th>Classe</th>
                <th>Type</th>
                <th>Statut</th>
            </tr>";
    
    foreach ($mappings as $ancien => $nouveau) {
        // Vérifier si l'ancien compte existe
        $stmt = $pdo->prepare("SELECT id, numero_compte, libelle FROM compta_comptes WHERE numero_compte = ?");
        $stmt->execute([$ancien]);
        $compte_ancien = $stmt->fetch();
        
        if ($compte_ancien) {
            // Vérifier si le nouveau compte existe déjà
            $stmt = $pdo->prepare("SELECT id FROM compta_comptes WHERE numero_compte = ?");
            $stmt->execute([$nouveau['nouveau_numero']]);
            $compte_nouveau = $stmt->fetch();
            
            if ($compte_nouveau) {
                echo "<tr>
                        <td style='text-decoration: line-through;'>{$ancien}</td>
                        <td>{$nouveau['nouveau_numero']}</td>
                        <td>{$nouveau['nouveau_libelle']}</td>
                        <td>{$nouveau['classe']}</td>
                        <td>{$nouveau['type']}</td>
                        <td><span class='warning'>⚠️ Nouveau compte existe, migration écritures</span></td>
                      </tr>";
                
                $corrections[] = [
                    'action' => 'migrer',
                    'ancien_id' => $compte_ancien['id'],
                    'nouveau_id' => $compte_nouveau['id'],
                    'ancien_numero' => $ancien,
                    'nouveau_numero' => $nouveau['nouveau_numero']
                ];
            } else {
                echo "<tr>
                        <td style='text-decoration: line-through;'>{$ancien}</td>
                        <td style='color: #27ae60; font-weight: bold;'>{$nouveau['nouveau_numero']}</td>
                        <td>{$nouveau['nouveau_libelle']}</td>
                        <td>{$nouveau['classe']}</td>
                        <td>{$nouveau['type']}</td>
                        <td><span class='success'>✅ Renommer</span></td>
                      </tr>";
                
                $corrections[] = [
                    'action' => 'renommer',
                    'ancien_id' => $compte_ancien['id'],
                    'nouveau_numero' => $nouveau['nouveau_numero'],
                    'nouveau_libelle' => $nouveau['nouveau_libelle'],
                    'classe' => $nouveau['classe'],
                    'type' => $nouveau['type']
                ];
            }
        } else {
            echo "<tr>
                    <td>{$ancien}</td>
                    <td>{$nouveau['nouveau_numero']}</td>
                    <td>{$nouveau['nouveau_libelle']}</td>
                    <td>{$nouveau['classe']}</td>
                    <td>{$nouveau['type']}</td>
                    <td><span style='color: #999;'>⊘ N'existe pas</span></td>
                  </tr>";
        }
    }
    echo "</table>";
    echo "</div>";
    
    // =========================================================================
    // ÉTAPE 2 : Appliquer les corrections
    // =========================================================================
    echo "<div class='step'>";
    echo "<h2>🔨 ÉTAPE 2 : Application des corrections</h2>";
    
    foreach ($corrections as $correction) {
        if ($correction['action'] === 'renommer') {
            // Renommer le compte
            $stmt = $pdo->prepare("
                UPDATE compta_comptes 
                SET numero_compte = ?, 
                    libelle = ?,
                    classe = ?,
                    type_compte = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $correction['nouveau_numero'],
                $correction['nouveau_libelle'],
                $correction['classe'],
                $correction['type'],
                $correction['ancien_id']
            ]);
            
            echo "<p class='success'>✅ Compte {$correction['ancien_id']} renommé en {$correction['nouveau_numero']}</p>";
            
        } elseif ($correction['action'] === 'migrer') {
            // Migrer les écritures
            $stmt = $pdo->prepare("
                UPDATE compta_ecritures 
                SET compte_id = ? 
                WHERE compte_id = ?
            ");
            $stmt->execute([
                $correction['nouveau_id'],
                $correction['ancien_id']
            ]);
            
            $nb_ecritures = $stmt->rowCount();
            echo "<p class='success'>✅ {$nb_ecritures} écriture(s) migrée(s) de {$correction['ancien_numero']} vers {$correction['nouveau_numero']}</p>";
            
            // Supprimer l'ancien compte
            $stmt = $pdo->prepare("DELETE FROM compta_comptes WHERE id = ?");
            $stmt->execute([$correction['ancien_id']]);
            
            echo "<p class='success'>✅ Ancien compte {$correction['ancien_numero']} supprimé</p>";
        }
    }
    
    echo "</div>";
    
    // =========================================================================
    // ÉTAPE 3 : Vérification
    // =========================================================================
    echo "<div class='step'>";
    echo "<h2>✅ ÉTAPE 3 : Vérification finale</h2>";
    
    // Vérifier que les comptes sont corrects
    $stmt = $pdo->query("
        SELECT numero_compte, libelle, classe, type_compte
        FROM compta_comptes
        WHERE numero_compte IN ('10', '11', '571')
        ORDER BY numero_compte
    ");
    $comptes_corriges = $stmt->fetchAll();
    
    if (count($comptes_corriges) > 0) {
        echo "<table>
                <tr>
                    <th>N° Compte</th>
                    <th>Libellé</th>
                    <th>Classe</th>
                    <th>Type</th>
                </tr>";
        
        foreach ($comptes_corriges as $compte) {
            echo "<tr>
                    <td>{$compte['numero_compte']}</td>
                    <td>{$compte['libelle']}</td>
                    <td>{$compte['classe']}</td>
                    <td>{$compte['type_compte']}</td>
                  </tr>";
        }
        echo "</table>";
    }
    
    echo "<p class='success'><strong>✅ Corrections appliquées avec succès !</strong></p>";
    echo "</div>";
    
    $pdo->commit();
    
    echo "<a href='compta/balance.php' class='btn'>📊 Voir le bilan corrigé</a>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<div class='step' style='border-color: #e74c3c;'>";
    echo "<h2 class='error'>❌ Erreur lors de la correction</h2>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</div></body></html>";
