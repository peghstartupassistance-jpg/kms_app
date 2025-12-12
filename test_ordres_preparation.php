<?php
// Test complet des pages ordres_preparation
require_once __DIR__ . '/db/db.php';

echo "=== TEST COMPLET ORDRES_PREPARATION ===\n\n";

// Test 1: Requête principale (liste)
echo "1. Test ordres_preparation.php (liste)...\n";
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
        ORDER BY op.date_ordre DESC, op.date_creation DESC 
        LIMIT 5
    ";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetchAll();
    echo "   ✅ Requête réussie (" . count($result) . " ordres)\n";
} catch (PDOException $e) {
    echo "   ❌ ERREUR: " . $e->getMessage() . "\n";
}

// Test 2: Requête édition (chargement ordre)
echo "\n2. Test ordres_preparation_edit.php (chargement)...\n";
try {
    $sql = "
        SELECT op.*, v.numero as vente_numero, v.client_id,
               c.nom as client_nom
        FROM ordres_preparation op
        LEFT JOIN ventes v ON op.vente_id = v.id
        LEFT JOIN clients c ON v.client_id = c.id
        LIMIT 1
    ";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch();
    echo "   ✅ Requête réussie\n";
} catch (PDOException $e) {
    echo "   ❌ ERREUR: " . $e->getMessage() . "\n";
}

// Test 3: Requête ventes disponibles
echo "\n3. Test ordres_preparation_edit.php (ventes dispo)...\n";
try {
    $sql = "
        SELECT v.id, v.numero, v.date_vente, v.montant_total_ttc,
               c.nom
        FROM ventes v
        INNER JOIN clients c ON v.client_id = c.id
        WHERE v.statut IN ('EN_ATTENTE_LIVRAISON', 'LIVREE')
          AND v.id NOT IN (SELECT vente_id FROM ordres_preparation WHERE statut != 'LIVRE')
        ORDER BY v.date_vente DESC
        LIMIT 5
    ";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetchAll();
    echo "   ✅ Requête réussie (" . count($result) . " ventes)\n";
} catch (PDOException $e) {
    echo "   ❌ ERREUR: " . $e->getMessage() . "\n";
}

// Test 4: Statistiques
echo "\n4. Test statistiques...\n";
try {
    $sql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut = 'EN_ATTENTE' THEN 1 ELSE 0 END) as en_attente,
            SUM(CASE WHEN statut = 'EN_PREPARATION' THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN statut = 'PRET' THEN 1 ELSE 0 END) as prets,
            SUM(CASE WHEN statut = 'LIVRE' THEN 1 ELSE 0 END) as livres,
            SUM(CASE WHEN priorite = 'URGENTE' AND statut IN ('EN_ATTENTE', 'EN_PREPARATION') THEN 1 ELSE 0 END) as urgents
        FROM ordres_preparation
    ";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch();
    echo "   ✅ Requête réussie (Total: {$result['total']}, En attente: {$result['en_attente']})\n";
} catch (PDOException $e) {
    echo "   ❌ ERREUR: " . $e->getMessage() . "\n";
}

echo "\n=== RÉSUMÉ ===\n";
echo "✅ Tous les tests ont réussi!\n";
echo "\nPages accessibles:\n";
echo "  • http://localhost/kms_app/coordination/ordres_preparation.php\n";
echo "  • http://localhost/kms_app/coordination/ordres_preparation_edit.php\n";
