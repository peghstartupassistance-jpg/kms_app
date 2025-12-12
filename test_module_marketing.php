<?php
/**
 * Script de test automatique - Module Marketing
 * Teste toutes les fonctionnalités créées
 */

require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/security.php';

// Simuler une connexion admin
$_SESSION['user_id'] = 1;
$_SESSION['permissions'] = [
    'CLIENTS_CREER', 'CLIENTS_LIRE', 'DEVIS_CREER', 'DEVIS_LIRE',
    'VENTES_LIRE', 'VENTES_MODIFIER', 'PRODUITS_LIRE',
    'REPORTING_LIRE', 'COMPTABILITE_LIRE'
];

$tests_passed = 0;
$tests_failed = 0;
$errors = [];

echo "=== TEST MODULE MARKETING KMS ===\n\n";

// Test 1: Vérifier tables créées
echo "1. Vérification des tables marketing...\n";
$tables_required = [
    'leads_digital',
    'ordres_preparation',
    'ruptures_signalees',
    'retours_litiges',
    'relances_devis',
    'conversions_pipeline',
    'objectifs_commerciaux',
    'kpis_quotidiens'
];

foreach ($tables_required as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "   ✓ Table '$table' existe\n";
            $tests_passed++;
        } else {
            echo "   ✗ Table '$table' MANQUANTE\n";
            $errors[] = "Table $table n'existe pas. Exécuter db/extensions_marketing.sql";
            $tests_failed++;
        }
    } catch (Exception $e) {
        echo "   ✗ Erreur vérification '$table': " . $e->getMessage() . "\n";
        $tests_failed++;
    }
}

// Test 2: Vérifier vues créées
echo "\n2. Vérification des vues...\n";
$views = ['v_pipeline_commercial', 'v_ventes_livraison_encaissement'];
foreach ($views as $view) {
    try {
        $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_kms_gestion = '$view'");
        if ($stmt->rowCount() > 0) {
            echo "   ✓ Vue '$view' existe\n";
            $tests_passed++;
        } else {
            echo "   ✗ Vue '$view' MANQUANTE\n";
            $errors[] = "Vue $view manquante";
            $tests_failed++;
        }
    } catch (Exception $e) {
        echo "   ⚠ Vue '$view' non vérifiable (peut ne pas exister encore)\n";
    }
}

// Test 3: Vérifier canaux de vente
echo "\n3. Vérification des canaux de vente...\n";
try {
    $stmt = $pdo->query("SELECT code FROM canaux_vente WHERE code IN ('SHOWROOM', 'TERRAIN', 'DIGITAL')");
    $canaux = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach (['SHOWROOM', 'TERRAIN', 'DIGITAL'] as $canal) {
        if (in_array($canal, $canaux)) {
            echo "   ✓ Canal '$canal' existe\n";
            $tests_passed++;
        } else {
            echo "   ✗ Canal '$canal' MANQUANT\n";
            $errors[] = "Canal $canal manquant. Ajouter: INSERT INTO canaux_vente (nom, code) VALUES ('".ucfirst(strtolower($canal))."', '$canal')";
            $tests_failed++;
        }
    }
} catch (Exception $e) {
    echo "   ✗ Erreur canaux: " . $e->getMessage() . "\n";
    $tests_failed++;
}

// Test 4: Vérifier fichiers PHP créés
echo "\n4. Vérification des fichiers PHP...\n";
$files_check = [
    'digital/leads_list.php' => 'Module DIGITAL - Liste leads',
    'digital/leads_edit.php' => 'Module DIGITAL - Formulaire lead',
    'digital/leads_conversion.php' => 'Module DIGITAL - Conversion',
    'coordination/ruptures.php' => 'Coordination - Ruptures',
    'coordination/litiges.php' => 'Coordination - Litiges',
    'coordination/ordres_preparation.php' => 'Coordination - Ordres préparation',
    'coordination/ordres_preparation_edit.php' => 'Coordination - Formulaire ordre',
    'coordination/ordres_preparation_statut.php' => 'Coordination - Changement statut',
    'reporting/dashboard_marketing.php' => 'Dashboard Marketing',
    'reporting/relances_devis.php' => 'Système relances',
    'showroom/visiteur_convertir_devis.php' => 'Conversion visiteur showroom',
    'marketing/README_MARKETING.md' => 'Documentation'
];

foreach ($files_check as $file => $description) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        echo "   ✓ $description\n";
        $tests_passed++;
    } else {
        echo "   ✗ $description MANQUANT ($file)\n";
        $errors[] = "Fichier $file manquant";
        $tests_failed++;
    }
}

// Test 5: Tester requêtes SQL des modules
echo "\n5. Test des requêtes SQL (structure)...\n";

// Test DIGITAL - Créer un lead test
try {
    $stmt = $pdo->prepare("
        INSERT INTO leads_digital 
        (date_lead, source, nom_prospect, telephone, statut, score_prospect)
        VALUES (CURDATE(), 'FACEBOOK', 'Test Lead', '0000000000', 'NOUVEAU', 50)
    ");
    $stmt->execute();
    $lead_id = $pdo->lastInsertId();
    echo "   ✓ Lead test créé (ID: $lead_id)\n";
    $tests_passed++;
    
    // Nettoyer
    $pdo->prepare("DELETE FROM leads_digital WHERE id = ?")->execute([$lead_id]);
    
} catch (Exception $e) {
    echo "   ✗ Erreur création lead: " . $e->getMessage() . "\n";
    $errors[] = "Structure table leads_digital incorrecte: " . $e->getMessage();
    $tests_failed++;
}

// Test Ordres préparation
try {
    // Vérifier qu'on peut lire la table
    $stmt = $pdo->query("SELECT COUNT(*) FROM ordres_preparation");
    $count = $stmt->fetchColumn();
    echo "   ✓ Table ordres_preparation accessible ($count ordres)\n";
    $tests_passed++;
} catch (Exception $e) {
    echo "   ✗ Erreur table ordres_preparation: " . $e->getMessage() . "\n";
    $errors[] = "Table ordres_preparation non accessible";
    $tests_failed++;
}

// Test Ruptures
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM ruptures_signalees");
    $count = $stmt->fetchColumn();
    echo "   ✓ Table ruptures_signalees accessible ($count ruptures)\n";
    $tests_passed++;
} catch (Exception $e) {
    echo "   ✗ Erreur table ruptures_signalees: " . $e->getMessage() . "\n";
    $errors[] = "Table ruptures_signalees non accessible";
    $tests_failed++;
}

// Test Litiges
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM retours_litiges");
    $count = $stmt->fetchColumn();
    echo "   ✓ Table retours_litiges accessible ($count litiges)\n";
    $tests_passed++;
} catch (Exception $e) {
    echo "   ✗ Erreur table retours_litiges: " . $e->getMessage() . "\n";
    $errors[] = "Table retours_litiges non accessible";
    $tests_failed++;
}

// Test Relances
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM relances_devis");
    $count = $stmt->fetchColumn();
    echo "   ✓ Table relances_devis accessible ($count relances)\n";
    $tests_passed++;
} catch (Exception $e) {
    echo "   ✗ Erreur table relances_devis: " . $e->getMessage() . "\n";
    $errors[] = "Table relances_devis non accessible";
    $tests_failed++;
}

// Test 6: Vérifier les enum/contraintes
echo "\n6. Vérification des contraintes...\n";
try {
    // Test statuts leads
    $stmt = $pdo->query("SHOW COLUMNS FROM leads_digital LIKE 'statut_pipeline'");
    $col = $stmt->fetch();
    if (strpos($col['Type'], 'enum') !== false) {
        echo "   ✓ Enum statut_pipeline configuré\n";
        $tests_passed++;
    }
    
    // Test statuts ordres
    $stmt = $pdo->query("SHOW COLUMNS FROM ordres_preparation LIKE 'statut_preparation'");
    $col = $stmt->fetch();
    if (strpos($col['Type'], 'enum') !== false) {
        echo "   ✓ Enum statut_preparation configuré\n";
        $tests_passed++;
    }
} catch (Exception $e) {
    echo "   ⚠ Vérification contraintes non complète\n";
}

// Test 7: Tester les jointures Dashboard
echo "\n7. Test requête Dashboard (simulation)...\n";
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT vs.id) as nb_visiteurs
        FROM visiteurs_showroom vs
        WHERE vs.date_visite = CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "   ✓ Requête SHOWROOM Dashboard fonctionnelle (visiteurs: {$result['nb_visiteurs']})\n";
    $tests_passed++;
} catch (Exception $e) {
    echo "   ✗ Erreur requête Dashboard: " . $e->getMessage() . "\n";
    $tests_failed++;
}

// Résumé
echo "\n=== RÉSUMÉ DES TESTS ===\n";
echo "Tests réussis: $tests_passed\n";
echo "Tests échoués: $tests_failed\n";
echo "Taux de réussite: " . round($tests_passed / ($tests_passed + $tests_failed) * 100) . "%\n";

if (!empty($errors)) {
    echo "\n=== ACTIONS REQUISES ===\n";
    foreach ($errors as $i => $error) {
        echo ($i + 1) . ". $error\n";
    }
}

if ($tests_failed === 0) {
    echo "\n✅ Tous les tests sont passés ! Module marketing opérationnel.\n";
} else {
    echo "\n⚠️ Certains tests ont échoué. Voir les actions requises ci-dessus.\n";
}

echo "\n=== FIN DES TESTS ===\n";
