<?php
/**
 * Script pour créer des données de test de trésorerie
 */

require_once __DIR__ . '/../security.php';
global $pdo;

echo "=== CRÉATION DE DONNÉES DE TEST ===\n\n";

// Créer une vente de test
try {
    $stmt = $pdo->prepare("
        INSERT INTO ventes 
        (numero, client_id, date_vente, montant_total_ttc, statut, date_creation)
        VALUES (?, ?, CURDATE(), ?, 'LIVREE', NOW())
    ");
    
    // Récupérer un client existant
    $client_stmt = $pdo->query("SELECT id FROM clients LIMIT 1");
    $client = $client_stmt->fetch();
    $client_id = $client['id'] ?? 1;
    
    $numero = 'VTEST-' . date('YmdHis');
    $montant = 15000.00; // 15 000 F
    
    $stmt->execute([$numero, $client_id, $montant]);
    $vente_id = $pdo->lastInsertId();
    
    echo "✓ Vente créée: #$vente_id ($numero) - $montant F\n";
    
} catch (Exception $e) {
    echo "✗ Erreur création vente: " . $e->getMessage() . "\n";
    exit;
}

// Ajouter des lignes de vente
try {
    $stmt = $pdo->prepare("
        INSERT INTO ventes_lignes 
        (vente_id, produit_id, quantite, prix_unitaire, montant)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    // Récupérer un produit existant
    $prod_stmt = $pdo->query("SELECT id FROM produits WHERE actif = 1 LIMIT 1");
    $prod = $prod_stmt->fetch();
    $produit_id = $prod['id'] ?? 1;
    
    $quantite = 10;
    $prix = 1500.00;
    $montant = $quantite * $prix;
    
    $stmt->execute([$vente_id, $produit_id, $quantite, $prix, $montant]);
    
    echo "✓ Ligne vente créée: Produit #$produit_id x $quantite = $montant F\n";
    
} catch (Exception $e) {
    echo "✗ Erreur création ligne: " . $e->getMessage() . "\n";
}

// Créer l'encaissement dans journal_caisse
try {
    $stmt = $pdo->prepare("
        INSERT INTO journal_caisse 
        (date_operation, date_ecriture, sens, montant, type_operation, source_type, source_id, commentaire, utilisateur_id, vente_id)
        VALUES (NOW(), NOW(), ?, ?, 'VENTE', 'VENTE', ?, ?, ?, ?)
    ");
    
    $user_id = $_SESSION['user_id'] ?? 1;
    $stmt->execute(['RECETTE', $montant, $vente_id, "Encaissement vente $numero", $user_id, $vente_id]);
    
    echo "✓ Encaissement créé: $montant F (vente_id=$vente_id)\n";
    
} catch (Exception $e) {
    echo "✗ Erreur encaissement: " . $e->getMessage() . "\n";
}

// Vérifier les données
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(date_operation) as date,
            SUM(CASE WHEN sens = 'RECETTE' THEN montant ELSE 0 END) as recettes,
            SUM(CASE WHEN sens = 'DEPENSE' THEN montant ELSE 0 END) as depenses
        FROM journal_caisse
        WHERE DATE(date_operation) = CURDATE()
        GROUP BY DATE(date_operation)
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    
    echo "\n✓ RÉSULTATS:\n";
    echo "  Date: " . ($result['date'] ?? 'N/A') . "\n";
    echo "  Recettes: " . ($result['recettes'] ?? 0) . " F\n";
    echo "  Dépenses: " . ($result['depenses'] ?? 0) . " F\n";
    
} catch (Exception $e) {
    echo "✗ Erreur vérification: " . $e->getMessage() . "\n";
}

echo "\n✓ Données de test créées!\n";
echo "→ Recharge le dashboard (index.php) pour voir les nouveaux CA\n";
?>
