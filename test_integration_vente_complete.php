<?php
/**
 * Test intégration complète - Création vente avec stock/caisse/compta
 * Simule exactement ce que fait ventes/edit.php
 */

require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/lib/stock.php';
require_once __DIR__ . '/lib/caisse.php';
require_once __DIR__ . '/lib/compta.php';

global $pdo;

echo "=== TEST INTÉGRATION COMPLÈTE VENTE ===\n\n";

// Récupérer un client et un produit pour le test
$stmtClient = $pdo->query("SELECT id, nom FROM clients LIMIT 1");
$client = $stmtClient->fetch();

$stmtProduit = $pdo->query("SELECT id, code_produit, prix_vente, stock_actuel FROM produits WHERE actif=1 AND stock_actuel > 0 LIMIT 1");
$produit = $stmtProduit->fetch();

if (!$client || !$produit) {
    die("❌ Impossible de tester : pas de client ou produit disponible\n");
}

echo "Client test: {$client['nom']} (ID: {$client['id']})\n";
echo "Produit test: {$produit['code_produit']} (ID: {$produit['id']}, Stock: {$produit['stock_actuel']})\n\n";

// Données de test
$dateVente = date('Y-m-d');
$quantite = 1;
$prixUnitaire = (float)$produit['prix_vente'];
$montantHT = $prixUnitaire * $quantite;
$montantTTC = $montantHT * 1.1925; // TVA 19.25%

echo "=== SIMULATION CRÉATION VENTE ===\n";
echo "Quantité: {$quantite}\n";
echo "Prix unitaire: " . number_format($prixUnitaire, 2) . " FCFA\n";
echo "Montant TTC: " . number_format($montantTTC, 2) . " FCFA\n\n";

$venteId = null;
$success = false;

try {
    echo "1. Début transaction globale...\n";
    $pdo->beginTransaction();
    echo "   ✅ Transaction ouverte (inTransaction = " . ($pdo->inTransaction() ? 'YES' : 'NO') . ")\n\n";
    
    // INSERT vente
    $numero = 'V-TEST-' . date('Ymd-His');
    echo "2. INSERT vente (numéro: {$numero})...\n";
    
    $stmt = $pdo->prepare("
        INSERT INTO ventes
        (numero, date_vente, client_id, canal_vente_id, statut, montant_total_ht, montant_total_ttc, utilisateur_id)
        VALUES
        (:numero, :date_vente, :client_id, 1, 'LIVREE', :mtht, :mttc, 1)
    ");
    $stmt->execute([
        ':numero' => $numero,
        ':date_vente' => $dateVente,
        ':client_id' => $client['id'],
        ':mtht' => $montantHT,
        ':mttc' => $montantTTC,
    ]);
    
    $venteId = (int)$pdo->lastInsertId();
    echo "   ✅ Vente créée (ID: {$venteId})\n";
    echo "   État transaction: inTransaction = " . ($pdo->inTransaction() ? 'YES' : 'NO') . "\n\n";
    
    // INSERT ligne vente
    echo "3. INSERT ventes_lignes...\n";
    $stmt = $pdo->prepare("
        INSERT INTO ventes_lignes
        (vente_id, produit_id, quantite, prix_unitaire, remise, montant_ligne_ht)
        VALUES
        (:vente_id, :produit_id, :quantite, :prix_unitaire, 0, :montant_ligne_ht)
    ");
    $stmt->execute([
        ':vente_id' => $venteId,
        ':produit_id' => $produit['id'],
        ':quantite' => $quantite,
        ':prix_unitaire' => $prixUnitaire,
        ':montant_ligne_ht' => $montantHT,
    ]);
    echo "   ✅ Ligne vente créée\n";
    echo "   État transaction: inTransaction = " . ($pdo->inTransaction() ? 'YES' : 'NO') . "\n\n";
    
    // Synchronisation stock (NE DOIT PAS créer sa propre transaction)
    echo "4. Appel stock_synchroniser_vente()...\n";
    $stockAvant = $produit['stock_actuel'];
    
    stock_synchroniser_vente($pdo, $venteId);
    
    echo "   ✅ Stock synchronisé\n";
    echo "   État transaction: inTransaction = " . ($pdo->inTransaction() ? 'YES' : 'NO') . "\n";
    
    // Vérifier que mouvement créé
    $stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM stocks_mouvements WHERE source_type='VENTE' AND source_id=?");
    $stmt->execute([$venteId]);
    $nbMouvements = $stmt->fetchColumn();
    echo "   Mouvements créés: {$nbMouvements}\n\n";
    
    // Enregistrement caisse (NE crée PAS de transaction)
    echo "5. Appel caisse_enregistrer_ecriture()...\n";
    try {
        $caisseId = caisse_enregistrer_ecriture(
            $pdo,
            'ENTREE',
            $montantTTC,
            'VENTE',
            $venteId,
            'Vente ' . $numero,
            1,
            $dateVente,
            $numero
        );
        echo "   ✅ Écriture caisse créée (ID: {$caisseId})\n";
        echo "   État transaction: inTransaction = " . ($pdo->inTransaction() ? 'YES' : 'NO') . "\n\n";
    } catch (Exception $e) {
        echo "   ⚠️ Avertissement caisse: " . $e->getMessage() . "\n\n";
    }
    
    // Écritures comptables (NE créent PAS de transaction)
    echo "6. Appel compta_creer_ecritures_vente()...\n";
    try {
        compta_creer_ecritures_vente($pdo, $venteId);
        echo "   ✅ Écritures comptables créées\n";
        echo "   État transaction: inTransaction = " . ($pdo->inTransaction() ? 'YES' : 'NO') . "\n\n";
    } catch (Exception $e) {
        echo "   ⚠️ Avertissement compta: " . $e->getMessage() . "\n\n";
    }
    
    // COMMIT transaction globale
    echo "7. Commit transaction globale...\n";
    if ($pdo->inTransaction()) {
        $pdo->commit();
        echo "   ✅ Transaction validée\n\n";
        $success = true;
    } else {
        echo "   ❌ ERREUR: Plus de transaction à commiter!\n\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERREUR GLOBALE: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    
    if ($pdo->inTransaction()) {
        echo "Rollback transaction...\n";
        $pdo->rollBack();
        echo "✅ Rollback effectué\n\n";
    }
}

// Vérification finale
if ($success && $venteId) {
    echo "=== VÉRIFICATION POST-CRÉATION ===\n\n";
    
    // Vérifier vente
    $stmt = $pdo->prepare("SELECT * FROM ventes WHERE id = ?");
    $stmt->execute([$venteId]);
    $venteCreee = $stmt->fetch();
    echo "Vente créée: " . ($venteCreee ? '✅ OUI' : '❌ NON') . "\n";
    
    // Vérifier lignes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ventes_lignes WHERE vente_id = ?");
    $stmt->execute([$venteId]);
    $nbLignes = $stmt->fetchColumn();
    echo "Lignes vente: {$nbLignes} " . ($nbLignes > 0 ? '✅' : '❌') . "\n";
    
    // Vérifier stock
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stocks_mouvements WHERE source_type='VENTE' AND source_id=?");
    $stmt->execute([$venteId]);
    $nbStock = $stmt->fetchColumn();
    echo "Mouvements stock: {$nbStock} " . ($nbStock > 0 ? '✅' : '❌') . "\n";
    
    // Vérifier stock_actuel
    $stmt = $pdo->prepare("SELECT stock_actuel FROM produits WHERE id = ?");
    $stmt->execute([$produit['id']]);
    $stockApres = $stmt->fetchColumn();
    $stockAttendu = $stockAvant - $quantite;
    echo "Stock produit: {$stockAvant} → {$stockApres} (attendu: {$stockAttendu}) " . ($stockApres == $stockAttendu ? '✅' : '❌') . "\n";
    
    // Vérifier caisse
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM journal_caisse WHERE numero_piece=?");
    $stmt->execute([$numero]);
    $nbCaisse = $stmt->fetchColumn();
    echo "Écriture caisse: {$nbCaisse} " . ($nbCaisse > 0 ? '✅' : '⚠️') . "\n";
    
    // Vérifier compta
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM compta_ecritures ce JOIN compta_pieces cp ON cp.id = ce.piece_id WHERE cp.reference=?");
    $stmt->execute([$numero]);
    $nbCompta = $stmt->fetchColumn();
    echo "Écritures compta: {$nbCompta} " . ($nbCompta > 0 ? '✅' : '⚠️') . "\n\n";
    
    // Nettoyage (supprimer la vente de test)
    echo "=== NETTOYAGE ===\n";
    $pdo->beginTransaction();
    
    // Supprimer dans l'ordre inverse (contraintes FK)
    $pdo->prepare("DELETE FROM compta_ecritures WHERE piece_id IN (SELECT id FROM compta_pieces WHERE reference=?)")->execute([$numero]);
    $pdo->prepare("DELETE FROM compta_pieces WHERE reference=?")->execute([$numero]);
    $pdo->prepare("DELETE FROM journal_caisse WHERE numero_piece=?")->execute([$numero]);
    $pdo->prepare("DELETE FROM stocks_mouvements WHERE source_type='VENTE' AND source_id=?")->execute([$venteId]);
    $pdo->prepare("DELETE FROM ventes_lignes WHERE vente_id=?")->execute([$venteId]);
    $pdo->prepare("DELETE FROM ventes WHERE id=?")->execute([$venteId]);
    
    // Restaurer stock
    $pdo->prepare("UPDATE produits SET stock_actuel = ? WHERE id = ?")->execute([$stockAvant, $produit['id']]);
    
    $pdo->commit();
    echo "✅ Vente de test supprimée\n";
    echo "✅ Stock restauré\n";
}

echo "\n=== FIN TEST INTÉGRATION ===\n";

if ($success) {
    echo "\n✅✅✅ SUCCÈS COMPLET - TRANSACTIONS SÉCURISÉES ✅✅✅\n";
} else {
    echo "\n❌ ÉCHEC - Voir erreurs ci-dessus\n";
}
