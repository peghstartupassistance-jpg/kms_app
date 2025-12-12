<?php
/**
 * Installation automatique du module SYSCOHADA
 * Exécute tous les scripts nécessaires dans l'ordre
 */

require_once __DIR__ . '/db/db.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>Installation SYSCOHADA</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; }
        .step { background: #ecf0f1; padding: 15px; margin: 15px 0; border-left: 4px solid #3498db; border-radius: 4px; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .info { color: #3498db; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .btn { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .btn:hover { background: #2980b9; }
        ul { line-height: 1.8; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🚀 Installation du Module SYSCOHADA</h1>";
echo "<p>Installation automatique en cours...</p>";

$errors = [];
$success = [];

try {
    // =====================================================
    // ÉTAPE 1 : Mise à jour du schéma
    // =====================================================
    echo "<div class='step'>";
    echo "<h2>📋 ÉTAPE 1 : Mise à jour du schéma</h2>";
    
    $sql = "ALTER TABLE compta_comptes 
            MODIFY COLUMN type_compte ENUM('ACTIF', 'PASSIF', 'CHARGE', 'PRODUIT', 'MIXTE', 'ANALYTIQUE') DEFAULT 'ACTIF'";
    
    try {
        $pdo->exec($sql);
        echo "<p class='success'>✅ Schéma mis à jour : types MIXTE et ANALYTIQUE ajoutés</p>";
        $success[] = "Schéma mis à jour";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "<p class='info'>ℹ️ Schéma déjà à jour</p>";
            $success[] = "Schéma déjà à jour";
        } else {
            throw $e;
        }
    }
    echo "</div>";
    
    // =====================================================
    // ÉTAPE 2 : Vérification de la colonne est_actif
    // =====================================================
    echo "<div class='step'>";
    echo "<h2>🔍 ÉTAPE 2 : Vérification de la colonne est_actif</h2>";
    
    $columns = $pdo->query("SHOW COLUMNS FROM compta_comptes")->fetchAll(PDO::FETCH_ASSOC);
    $hasEstActif = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'est_actif') {
            $hasEstActif = true;
            break;
        }
    }
    
    if (!$hasEstActif) {
        echo "<p class='warning'>⚠️ Colonne est_actif manquante. Ajout en cours...</p>";
        $pdo->exec("ALTER TABLE compta_comptes ADD COLUMN est_actif TINYINT(1) DEFAULT 1 AFTER nature");
        echo "<p class='success'>✅ Colonne est_actif ajoutée</p>";
        $success[] = "Colonne est_actif créée";
    } else {
        echo "<p class='info'>ℹ️ Colonne est_actif existe déjà</p>";
        $success[] = "Colonne est_actif OK";
    }
    echo "</div>";
    
    // =====================================================
    // ÉTAPE 3 : Import du plan comptable SYSCOHADA
    // =====================================================
    echo "<div class='step'>";
    echo "<h2>📥 ÉTAPE 3 : Import du plan comptable SYSCOHADA</h2>";
    
    // Vérifier si des comptes existent déjà
    $count = $pdo->query("SELECT COUNT(*) as nb FROM compta_comptes WHERE numero_compte LIKE '1%' OR numero_compte LIKE '2%'")->fetch()['nb'];
    
    if ($count > 10) {
        echo "<p class='warning'>⚠️ {$count} comptes SYSCOHADA déjà présents. Import annulé pour éviter les doublons.</p>";
        echo "<p>Si vous voulez réimporter, exécutez d'abord : <code>TRUNCATE TABLE compta_comptes;</code></p>";
        $success[] = "Comptes déjà importés";
    } else {
        echo "<p class='info'>Import en cours...</p>";
        
        // Lire et exécuter le script SQL
        $sqlFile = __DIR__ . '/db/import_plan_syscohada.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("Fichier import_plan_syscohada.sql introuvable");
        }
        
        $sql = file_get_contents($sqlFile);
        
        // Enlever les commentaires et diviser en requêtes
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = preg_replace('/^\s*$/m', '', $sql);
        
        // Exécuter chaque INSERT séparément
        $statements = explode(';', $sql);
        $inserted = 0;
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || strpos($statement, 'SELECT') === 0) continue;
            
            try {
                $pdo->exec($statement);
                if (strpos($statement, 'INSERT') === 0) {
                    $inserted++;
                }
            } catch (PDOException $e) {
                // Ignorer les erreurs de doublons
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    echo "<p class='warning'>⚠️ Erreur sur une requête : " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
        }
        
        $totalComptes = $pdo->query("SELECT COUNT(*) as nb FROM compta_comptes")->fetch()['nb'];
        echo "<p class='success'>✅ Plan comptable SYSCOHADA importé : {$totalComptes} comptes au total</p>";
        $success[] = "{$totalComptes} comptes importés";
    }
    echo "</div>";
    
    // =====================================================
    // ÉTAPE 4 : Vérification des exercices
    // =====================================================
    echo "<div class='step'>";
    echo "<h2>📅 ÉTAPE 4 : Vérification des exercices</h2>";
    
    $countExercices = $pdo->query("SELECT COUNT(*) as nb FROM compta_exercices")->fetch()['nb'];
    
    if ($countExercices == 0) {
        echo "<p class='warning'>⚠️ Aucun exercice trouvé. Création de l'exercice 2025...</p>";
        $annee = 2025;
        $stmt = $pdo->prepare("INSERT INTO compta_exercices (annee, date_ouverture, est_clos) VALUES (?, ?, 0)");
        $stmt->execute([$annee, "$annee-01-01"]);
        echo "<p class='success'>✅ Exercice {$annee} créé</p>";
        $success[] = "Exercice 2025 créé";
    } else {
        $exercice = $pdo->query("SELECT * FROM compta_exercices WHERE est_clos = 0 ORDER BY annee DESC LIMIT 1")->fetch();
        if ($exercice) {
            echo "<p class='success'>✅ Exercice actif : {$exercice['annee']}</p>";
            $success[] = "Exercice {$exercice['annee']} actif";
        } else {
            echo "<p class='warning'>⚠️ Tous les exercices sont clos. Créez-en un nouveau.</p>";
        }
    }
    echo "</div>";
    
    // =====================================================
    // ÉTAPE 5 : Vérification des journaux
    // =====================================================
    echo "<div class='step'>";
    echo "<h2>📖 ÉTAPE 5 : Vérification des journaux</h2>";
    
    $countJournaux = $pdo->query("SELECT COUNT(*) as nb FROM compta_journaux")->fetch()['nb'];
    
    if ($countJournaux == 0) {
        echo "<p class='warning'>⚠️ Aucun journal trouvé. Création des journaux par défaut...</p>";
        $journaux = [
            ['VE', 'Journal des ventes', 'VENTE'],
            ['AC', 'Journal des achats', 'ACHAT'],
            ['BQ', 'Journal de banque', 'TRESORERIE'],
            ['CA', 'Journal de caisse', 'TRESORERIE'],
            ['OD', 'Opérations diverses', 'OPERATION_DIVERSE'],
        ];
        
        foreach ($journaux as $j) {
            $stmt = $pdo->prepare("INSERT INTO compta_journaux (code, libelle, type) VALUES (?, ?, ?)");
            $stmt->execute($j);
            echo "<p class='info'>→ Journal {$j[0]} créé</p>";
        }
        echo "<p class='success'>✅ 5 journaux créés</p>";
        $success[] = "5 journaux créés";
    } else {
        echo "<p class='success'>✅ {$countJournaux} journaux trouvés</p>";
        $success[] = "{$countJournaux} journaux OK";
    }
    echo "</div>";
    
    // =====================================================
    // RÉSUMÉ FINAL
    // =====================================================
    echo "<div class='step' style='background: #d5f4e6; border-color: #27ae60;'>";
    echo "<h2>✅ Installation terminée avec succès !</h2>";
    echo "<ul>";
    foreach ($success as $msg) {
        echo "<li>{$msg}</li>";
    }
    echo "</ul>";
    echo "<p><strong>Vous pouvez maintenant utiliser l'interface de saisie SYSCOHADA !</strong></p>";
    echo "<a href='compta/saisie_ecritures.php' class='btn'>🎯 Accéder à la saisie</a>";
    echo "<a href='compta/plan_comptable.php' class='btn' style='background: #9b59b6; margin-left: 10px;'>📋 Voir le plan comptable</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='step' style='background: #fadbd8; border-color: #e74c3c;'>";
    echo "<h2>❌ Erreur lors de l'installation</h2>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</div></body></html>";
