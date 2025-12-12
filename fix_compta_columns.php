<?php
/**
 * Script de correction des colonnes comptabilité
 * À exécuter une seule fois pour mettre à jour la structure des tables
 */

require_once __DIR__ . '/db/db.php';

echo "<h2>🔧 Correction des colonnes de comptabilité</h2>\n";

try {
    // 1. Vérifier compta_comptes.est_actif
    echo "<h3>1. Table compta_comptes</h3>\n";
    $columns = $pdo->query("SHOW COLUMNS FROM compta_comptes")->fetchAll(PDO::FETCH_ASSOC);
    $hasEstActif = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'est_actif') {
            $hasEstActif = true;
            break;
        }
    }
    
    if (!$hasEstActif) {
        echo "❌ Colonne est_actif manquante. Ajout en cours...<br>\n";
        $pdo->exec("ALTER TABLE compta_comptes ADD COLUMN est_actif TINYINT(1) DEFAULT 1 AFTER nature");
        echo "✅ Colonne est_actif ajoutée<br>\n";
    } else {
        echo "✅ Colonne est_actif existe déjà<br>\n";
    }
    
    // 2. Vérifier compta_exercices
    echo "<h3>2. Table compta_exercices</h3>\n";
    $columns = $pdo->query("SHOW COLUMNS FROM compta_exercices")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<strong>Colonnes existantes :</strong><br>\n<ul>\n";
    foreach ($columns as $col) {
        echo "<li>{$col['Field']} ({$col['Type']})</li>\n";
    }
    echo "</ul>\n";
    
    // Vérifier si au moins un exercice existe
    $count = $pdo->query("SELECT COUNT(*) as nb FROM compta_exercices")->fetch()['nb'];
    echo "<p><strong>Nombre d'exercices :</strong> {$count}</p>\n";
    
    if ($count == 0) {
        echo "⚠️ Aucun exercice trouvé. Création d'un exercice par défaut...<br>\n";
        $annee = date('Y');
        $stmt = $pdo->prepare("
            INSERT INTO compta_exercices (annee, date_ouverture, est_clos) 
            VALUES (?, ?, 0)
        ");
        $stmt->execute([$annee, "$annee-01-01"]);
        echo "✅ Exercice {$annee} créé<br>\n";
    }
    
    // 3. Vérifier compta_journaux
    echo "<h3>3. Table compta_journaux</h3>\n";
    $count = $pdo->query("SELECT COUNT(*) as nb FROM compta_journaux")->fetch()['nb'];
    echo "<p><strong>Nombre de journaux :</strong> {$count}</p>\n";
    
    if ($count == 0) {
        echo "⚠️ Aucun journal trouvé. Création des journaux par défaut...<br>\n";
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
            echo "✅ Journal {$j[0]} créé<br>\n";
        }
    }
    
    // 4. Vérifier compta_comptes (comptes de base)
    echo "<h3>4. Table compta_comptes</h3>\n";
    $count = $pdo->query("SELECT COUNT(*) as nb FROM compta_comptes WHERE est_actif = 1")->fetch()['nb'];
    echo "<p><strong>Nombre de comptes actifs :</strong> {$count}</p>\n";
    
    if ($count < 10) {
        echo "⚠️ Peu de comptes trouvés. Pensez à exécuter db/import_plan_syscohada.sql<br>\n";
        echo "<p><strong>Commande :</strong> <code>mysql -u root kms_gestion < db/import_plan_syscohada.sql</code></p>\n";
    }
    
    echo "<hr>\n";
    echo "<h3>✅ Diagnostic terminé</h3>\n";
    echo "<p><a href='compta/saisie_ecritures.php'>Tester l'interface de saisie →</a></p>\n";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
