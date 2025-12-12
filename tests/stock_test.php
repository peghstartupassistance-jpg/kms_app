<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../lib/stock.php';

echo "Début test stock (transactionnel, non-destructif)\n";

// Récupérer un produit actif
$stmt = $pdo->query("SELECT id, code_produit FROM produits WHERE actif = 1 LIMIT 1");
$prod = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$prod) {
    echo "Aucun produit actif trouvé — test impossible\n";
    exit(1);
}

$pid = (int)$prod['id'];
$code = $prod['code_produit'];

echo "Produit choisi: ID={$pid}, code={$code}\n";

try {
    $pdo->beginTransaction();

    $q_before = stock_get_quantite_produit($pdo, $pid);
    echo "Quantité avant: {$q_before}\n";

    $mvtId = stock_enregistrer_mouvement($pdo, [
        'produit_id' => $pid,
        'date_mouvement' => date('Y-m-d H:i:s'),
        'type_mouvement' => 'ENTREE',
        'quantite' => 1,
        'source_type' => 'TEST',
        'source_id' => null,
        'commentaire' => 'Test transactionnel',
        'utilisateur_id' => 1,
    ]);

    echo "Mouvement inséré (ID temporaire): {$mvtId}\n";

    $q_after = stock_get_quantite_produit($pdo, $pid);
    echo "Quantité après insertion (transaction ouverte): {$q_after}\n";

    // Annuler les modifications
    $pdo->rollBack();
    echo "Rollback effectué\n";

    // Recalculer depuis une connexion fraîche pour vérifier l'état réel
    $stmt2 = $pdo->query("SELECT COUNT(*) as c FROM stocks_mouvements WHERE source_type = 'TEST' AND produit_id = " . $pid);
    $cnt = $stmt2->fetch(PDO::FETCH_ASSOC)['c'];
    echo "Nombre de mouvements TEST persistants (après rollback): {$cnt}\n";

    $q_final = stock_get_quantite_produit($pdo, $pid);
    echo "Quantité finale (après rollback): {$q_final}\n";

    if ($q_before === $q_final) {
        echo "TEST OK: rollback a annulé l'insertion et le recalcul.\n";
        exit(0);
    } else {
        echo "TEST FAIL: quantité différente après rollback (before={$q_before} final={$q_final}).\n";
        exit(2);
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Erreur pendant le test: " . $e->getMessage() . "\n";
    exit(3);
}
