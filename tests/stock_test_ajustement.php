<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../lib/stock.php';

echo "Début test ajustement (transactionnel, non-destructif)\n";

// Récupérer un produit actif
$stmt = $pdo->query("SELECT id, code_produit FROM produits WHERE actif = 1 LIMIT 1");
$prod = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$prod) {
    echo "Aucun produit actif trouvé — test impossible\n";
    exit(1);
}
$pid = (int)$prod['id'];

try {
    $pdo->beginTransaction();

    $q_before = stock_get_quantite_produit($pdo, $pid);
    echo "Quantité avant: {$q_before}\n";

    $mvtId = stock_enregistrer_mouvement($pdo, [
        'produit_id' => $pid,
        'date_mouvement' => date('Y-m-d H:i:s'),
        'type_mouvement' => 'AJUSTEMENT',
        'quantite' => -2,
        'source_type' => 'TEST_AJUST',
        'source_id' => null,
        'commentaire' => 'Test ajustement transactionnel',
        'utilisateur_id' => 1,
    ]);

    echo "Mouvement AJUSTEMENT inséré (ID temporaire): {$mvtId}\n";

    $q_after = stock_get_quantite_produit($pdo, $pid);
    echo "Quantité après insertion (transaction ouverte): {$q_after}\n";

    $pdo->rollBack();
    echo "Rollback effectué\n";

    $stmt2 = $pdo->prepare("SELECT COUNT(*) as c FROM stocks_mouvements WHERE source_type = 'TEST_AJUST' AND produit_id = ?");
    $stmt2->execute([$pid]);
    $cnt = $stmt2->fetch(PDO::FETCH_ASSOC)['c'];
    echo "Mouvements TEST_AJUST persistants (après rollback): {$cnt}\n";

    $q_final = stock_get_quantite_produit($pdo, $pid);
    echo "Quantité finale (après rollback): {$q_final}\n";

    if ($q_before === $q_final) {
        echo "TEST AJUSTEMENT OK: rollback a annulé l'insertion et le recalcul.\n";
        exit(0);
    } else {
        echo "TEST AJUSTEMENT FAIL: quantité différente après rollback.\n";
        exit(2);
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Erreur pendant le test: " . $e->getMessage() . "\n";
    exit(3);
}
