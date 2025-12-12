<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../lib/stock.php';

echo "Début test synchroniseur achat (transactionnel, non-destructif)\n";

// Chercher un achat avec lignes
$stmt = $pdo->query("SELECT a.id FROM achats a JOIN achats_lignes l ON l.achat_id = a.id LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "Aucun achat avec lignes trouvé — test impossible\n";
    exit(1);
}
$achat_id = (int)$row['id'];

echo "Achat choisi: ID={$achat_id}\n";

try {
    // Pas de transaction globale: on sauvegarde/restaure l'état manuellement
    $stmtP = $pdo->prepare("SELECT DISTINCT produit_id FROM achats_lignes WHERE achat_id = ?");
    $stmtP->execute([$achat_id]);
    $pids = $stmtP->fetchAll(PDO::FETCH_COLUMN);

    $before = [];
    foreach ($pids as $pid) {
        $before[$pid] = stock_get_quantite_produit($pdo, (int)$pid);
    }

    echo "Quantités avant:\n";
    foreach ($before as $pid => $q) echo " - produit {$pid}: {$q}\n";

    // Sauvegarde des mouvements pré-existants pour cet achat
    $stmtOld = $pdo->prepare("SELECT * FROM stocks_mouvements WHERE source_type = 'ACHAT' AND source_id = ?");
    $stmtOld->execute([$achat_id]);
    $oldRows = $stmtOld->fetchAll(PDO::FETCH_ASSOC);

    // Appel du synchroniseur
    $res = stock_synchroniser_achat($pdo, $achat_id);
    echo "Synchroniseur retourné: "; var_export($res); echo "\n";

    $stmtNow = $pdo->prepare("SELECT DISTINCT produit_id FROM stocks_mouvements WHERE source_type = 'ACHAT' AND source_id = ?");
    $stmtNow->execute([$achat_id]);
    $nowPids = $stmtNow->fetchAll(PDO::FETCH_COLUMN);
    echo "Produits affectés par synchroniseur: "; print_r($nowPids);

    // Suppression des mouvements ajoutés
    $del = $pdo->prepare("DELETE FROM stocks_mouvements WHERE source_type = 'ACHAT' AND source_id = ?");
    $del->execute([$achat_id]);
    echo "Mouvements pour l'achat supprimés (cleanup).\n";

    // Restaurer les anciennes lignes
    foreach ($oldRows as $r) {
        stock_enregistrer_mouvement($pdo, [
            'produit_id' => $r['produit_id'],
            'date_mouvement' => $r['date_mouvement'],
            'type_mouvement' => $r['type_mouvement'],
            'quantite' => $r['quantite'],
            'source_type' => $r['source_type'],
            'source_id' => $r['source_id'],
            'commentaire' => $r['commentaire'],
            'utilisateur_id' => $r['utilisateur_id'],
        ]);
    }

    // Recalculer et vérifier
    $final = [];
    foreach ($pids as $pid) {
        stock_recalculer_stock_produit($pdo, (int)$pid);
        $final[$pid] = stock_get_quantite_produit($pdo, (int)$pid);
    }

    echo "Quantités finales (après cleanup/restauration):\n";
    foreach ($final as $pid => $q) echo " - produit {$pid}: {$q}\n";

    $ok = true;
    foreach ($before as $pid => $q) {
        if ($final[$pid] != $q) { $ok = false; break; }
    }

    if ($ok) {
        echo "TEST ACHAT OK: synchronisation testée et état restauré.\n";
        exit(0);
    } else {
        echo "TEST ACHAT FAIL: différences détectées après restauration.\n";
        exit(2);
    }

} catch (Throwable $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(3);
}
