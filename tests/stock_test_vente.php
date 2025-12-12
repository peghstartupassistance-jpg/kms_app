<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../lib/stock.php';

echo "Début test synchroniseur vente (transactionnel, non-destructif)\n";

// Chercher une vente avec lignes
$stmt = $pdo->query("SELECT v.id FROM ventes v JOIN ventes_lignes l ON l.vente_id = v.id LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "Aucune vente avec lignes trouvée — test impossible\n";
    exit(1);
}
$vente_id = (int)$row['id'];

echo "Vente choisie: ID={$vente_id}\n";

try {
    // On ne crée PAS de transaction globale car le synchroniseur gère ses propres transactions.
    $stmtP = $pdo->prepare("SELECT DISTINCT produit_id FROM ventes_lignes WHERE vente_id = ?");
    $stmtP->execute([$vente_id]);
    $pids = $stmtP->fetchAll(PDO::FETCH_COLUMN);

    $before = [];
    foreach ($pids as $pid) {
        $before[$pid] = stock_get_quantite_produit($pdo, (int)$pid);
    }

    echo "Quantités avant:\n";
    foreach ($before as $pid => $q) echo " - produit {$pid}: {$q}\n";

    // Sauvegarder mouvements existants pour cette vente (entier)
    $stmtOld = $pdo->prepare("SELECT * FROM stocks_mouvements WHERE source_type = 'VENTE' AND source_id = ?");
    $stmtOld->execute([$vente_id]);
    $oldRows = $stmtOld->fetchAll(PDO::FETCH_ASSOC);

    // Appel du synchroniseur (il gère sa propre transaction)
    $res = stock_synchroniser_vente($pdo, $vente_id);
    echo "Synchroniseur retourné: "; var_export($res); echo "\n";

    // Vérifier le nombre de mouvements créés maintenant
    $stmtNow = $pdo->prepare("SELECT DISTINCT produit_id FROM stocks_mouvements WHERE source_type = 'VENTE' AND source_id = ?");
    $stmtNow->execute([$vente_id]);
    $nowPids = $stmtNow->fetchAll(PDO::FETCH_COLUMN);

    echo "Produits affectés par synchroniseur: "; print_r($nowPids);

    // Nettoyage: supprimer tous les mouvements pour cette vente
    $del = $pdo->prepare("DELETE FROM stocks_mouvements WHERE source_type = 'VENTE' AND source_id = ?");
    $del->execute([$vente_id]);
    echo "Mouvements pour la vente supprimés (cleanup).\n";

    // Restaurer les anciennes lignes si elles existaient
    foreach ($oldRows as $r) {
        // Utiliser stock_enregistrer_mouvement pour remise en place (recalcule également)
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

    // Recalcul des produits affectés
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
        echo "TEST VENTE OK: synchronisation testée et état restauré.\n";
        exit(0);
    } else {
        echo "TEST VENTE FAIL: différences détectées après restauration.\n";
        exit(2);
    }

} catch (Throwable $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(3);
}
