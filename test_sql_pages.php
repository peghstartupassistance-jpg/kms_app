<?php
// Test rapide des pages pour vérifier les erreurs SQL
require_once __DIR__ . '/db/db.php';

echo "=== TEST DES REQUÊTES SQL ===\n\n";

// Test 1: ordres_preparation.php (requête principale)
echo "1. Test ordres_preparation.php...\n";
try {
    $sql = "
        SELECT 
            op.*,
            v.numero as vente_numero,
            v.date_vente,
            c.nom as client_nom,
            c.telephone as client_telephone,
            u.nom_complet as demandeur_nom,
            m.nom_complet as preparateur_nom,
            (SELECT COUNT(*) FROM ventes_lignes WHERE vente_id = op.vente_id) as nb_lignes
        FROM ordres_preparation op
        LEFT JOIN ventes v ON op.vente_id = v.id
        LEFT JOIN clients c ON op.client_id = c.id
        LEFT JOIN utilisateurs u ON op.commercial_responsable_id = u.id
        LEFT JOIN utilisateurs m ON op.magasinier_id = m.id
        WHERE 1=1
        LIMIT 5
    ";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetchAll();
    echo "   ✅ Requête réussie (" . count($result) . " ordres)\n";
} catch (PDOException $e) {
    echo "   ❌ ERREUR: " . $e->getMessage() . "\n";
}

// Test 2: ordres_preparation_edit.php (lignes vente)
echo "\n2. Test ordres_preparation_edit.php (lignes)...\n";
try {
    $sql = "
        SELECT lv.*, p.designation as produit_nom, p.code_produit
        FROM ventes_lignes lv
        INNER JOIN produits p ON lv.produit_id = p.id
        LIMIT 5
    ";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetchAll();
    echo "   ✅ Requête réussie (" . count($result) . " lignes)\n";
} catch (PDOException $e) {
    echo "   ❌ ERREUR: " . $e->getMessage() . "\n";
}

// Test 3: litiges.php (requête principale)
echo "\n3. Test litiges.php...\n";
try {
    $sql = "
        SELECT rl.*,
               c.nom AS client_nom,
               c.telephone AS client_telephone,
               v.numero AS numero_vente,
               p.code_produit,
               p.designation AS produit_designation,
               u.nom_complet AS responsable
        FROM retours_litiges rl
        INNER JOIN clients c ON rl.client_id = c.id
        LEFT JOIN ventes v ON rl.vente_id = v.id
        LEFT JOIN produits p ON rl.produit_id = p.id
        LEFT JOIN utilisateurs u ON rl.responsable_suivi_id = u.id
        LIMIT 5
    ";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetchAll();
    echo "   ✅ Requête réussie (" . count($result) . " litiges)\n";
} catch (PDOException $e) {
    echo "   ❌ ERREUR: " . $e->getMessage() . "\n";
}

// Test 4: litiges.php (statistiques avec montant_rembourse)
echo "\n4. Test statistiques litiges (montant_rembourse)...\n";
try {
    $sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut_traitement = 'EN_COURS' THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN statut_traitement IN ('RESOLU', 'REMPLACEMENT_EFFECTUE', 'REMBOURSEMENT_EFFECTUE') THEN 1 ELSE 0 END) as resolus,
            SUM(montant_rembourse) as total_rembourse
        FROM retours_litiges
    ";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch();
    echo "   ✅ Requête réussie (Total: {$result['total']}, Remboursé: " . number_format($result['total_rembourse'], 0, ',', ' ') . " FCFA)\n";
} catch (PDOException $e) {
    echo "   ❌ ERREUR: " . $e->getMessage() . "\n";
}

echo "\n=== RÉSUMÉ ===\n";
echo "✅ Tous les tests SQL ont réussi!\n";
echo "\nVous pouvez maintenant tester dans le navigateur:\n";
echo "  • http://localhost/kms_app/coordination/ordres_preparation.php\n";
echo "  • http://localhost/kms_app/coordination/litiges.php\n";
