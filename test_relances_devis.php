<?php
/**
 * Test : reporting/relances_devis.php
 * Vérifie que la requête SQL s'exécute sans erreur
 */

require_once __DIR__ . '/db/db.php';

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║               TEST REPORTING - RELANCES DEVIS                      ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

try {
    // Test de la requête principale
    echo "1. Test requête principale relances_devis...\n";
    
    $stmtDevis = $pdo->prepare("
        SELECT 
            d.*,
            c.nom as client_nom,
            c.telephone as client_telephone,
            c.email as client_email,
            u.nom_complet as utilisateur_nom,
            cv.libelle as canal_nom,
            DATEDIFF(d.date_relance, CURDATE()) as jours_restants,
            (SELECT MAX(date_relance) FROM relances_devis WHERE devis_id = d.id) as derniere_relance,
            (SELECT COUNT(*) FROM relances_devis WHERE devis_id = d.id) as nb_relances
        FROM devis d
        INNER JOIN clients c ON d.client_id = c.id
        LEFT JOIN utilisateurs u ON d.utilisateur_id = u.id
        LEFT JOIN canaux_vente cv ON d.canal_vente_id = cv.id
        WHERE d.statut IN ('ENVOYE', 'EN_COURS')
          AND (d.date_relance IS NULL OR d.date_relance >= CURDATE())
        ORDER BY d.date_devis DESC
    ");
    $stmtDevis->execute();
    $devis_list = $stmtDevis->fetchAll();
    
    echo "   ✅ Requête réussie (" . count($devis_list) . " devis en attente)\n\n";
    
    // Afficher quelques exemples
    if (!empty($devis_list)) {
        echo "📋 Aperçu des devis en attente :\n";
        echo str_repeat("─", 70) . "\n";
        
        foreach (array_slice($devis_list, 0, 5) as $d) {
            $jours = $d['jours_restants'] !== null ? $d['jours_restants'] . ' jours' : 'Indéfini';
            $relances = $d['nb_relances'] ?? 0;
            $montant = number_format($d['montant_total_ttc'], 0, ',', ' ');
            
            echo sprintf(
                "  • %s - %s - %s F - Validité: %s - Relances: %d\n",
                $d['numero'],
                $d['client_nom'],
                $montant,
                $jours,
                $relances
            );
        }
        
        if (count($devis_list) > 5) {
            echo "  ... et " . (count($devis_list) - 5) . " autre(s)\n";
        }
        
        echo "\n";
    }
    
    // Test des statistiques
    echo "2. Test calcul statistiques...\n";
    
    $devis_a_relancer_urgent = array_filter($devis_list, function($d) {
        return $d['jours_restants'] !== null && $d['jours_restants'] <= 3;
    });
    
    $devis_sans_relance = array_filter($devis_list, function($d) {
        return $d['nb_relances'] == 0;
    });
    
    $today = date('Y-m-d');
    $devis_relances_recentes = array_filter($devis_list, function($d) use ($today) {
        if (!$d['derniere_relance']) return false;
        $diff = (strtotime($today) - strtotime($d['derniere_relance'])) / 86400;
        return $diff < 7;
    });
    
    echo "   ✅ Statistiques calculées\n";
    echo "      • Total devis en attente : " . count($devis_list) . "\n";
    echo "      • ⚠️ Urgent (≤ 3 jours)   : " . count($devis_a_relancer_urgent) . "\n";
    echo "      • Sans relance            : " . count($devis_sans_relance) . "\n";
    echo "      • Relancés cette semaine  : " . count($devis_relances_recentes) . "\n\n";
    
    // Test de la structure de la table relances_devis
    echo "3. Test structure table relances_devis...\n";
    
    $stmt = $pdo->query("DESCRIBE relances_devis");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['id', 'devis_id', 'date_relance', 'type_relance', 'utilisateur_id'];
    $missing = array_diff($required_columns, $columns);
    
    if (empty($missing)) {
        echo "   ✅ Table relances_devis OK (" . count($columns) . " colonnes)\n\n";
    } else {
        echo "   ⚠️ Colonnes manquantes : " . implode(', ', $missing) . "\n\n";
    }
    
    // Test compte relances existantes
    echo "4. Test compte relances existantes...\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM relances_devis");
    $result = $stmt->fetch();
    
    echo "   ✅ Total relances enregistrées : " . $result['total'] . "\n\n";
    
    echo str_repeat("═", 70) . "\n";
    echo "✅ TOUS LES TESTS ONT RÉUSSI !\n";
    echo str_repeat("═", 70) . "\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR : " . $e->getMessage() . "\n";
    echo "   Fichier : " . $e->getFile() . "\n";
    echo "   Ligne   : " . $e->getLine() . "\n\n";
    exit(1);
}
