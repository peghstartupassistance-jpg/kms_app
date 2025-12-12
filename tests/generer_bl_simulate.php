<?php
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../lib/stock.php';

echo "Début simulation génération BL (transactionnel, non-destructif)\n";

// Trouver une vente avec lignes et des quantités restantes à livrer
$stmt = $pdo->query("SELECT id FROM ventes WHERE statut <> 'ANNULEE'");
$candidate = null;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $vid = (int)$row['id'];
    // Récup lignes de vente
    $s = $pdo->prepare("SELECT vl.*, p.stock_actuel FROM ventes_lignes vl JOIN produits p ON p.id = vl.produit_id WHERE vl.vente_id = :id");
    $s->execute(['id' => $vid]);
    $lignes = $s->fetchAll(PDO::FETCH_ASSOC);
    if (empty($lignes)) continue;

    // Calcul livraisons existantes
    $s2 = $pdo->prepare("SELECT bll.produit_id, SUM(bll.quantite) AS qte_livree FROM bons_livraison_lignes bll JOIN bons_livraison bl ON bl.id = bll.bon_livraison_id WHERE bl.vente_id = :id GROUP BY bll.produit_id");
    $s2->execute(['id' => $vid]);
    $liv = $s2->fetchAll(PDO::FETCH_KEY_PAIR);

    $toDeliver = [];
    foreach ($lignes as $lv) {
        $pid = (int)$lv['produit_id'];
        $qteCmd = (float)$lv['quantite'];
        $qteLiv = isset($liv[$pid]) ? (float)$liv[$pid] : 0.0;
        $rest = max(0, $qteCmd - $qteLiv);
        if ($rest > 0) $toDeliver[] = ['produit_id' => $pid, 'quantite' => $rest];
    }

    if (!empty($toDeliver)) {
        $candidate = ['vente_id' => $vid, 'lignes' => $toDeliver];
        break;
    }
}

if (!$candidate) {
    echo "Aucune vente trouvée avec lignes à livrer. Test impossible.\n";
    exit(1);
}

$venteId = $candidate['vente_id'];
$lignesALivrer = $candidate['lignes'];

echo "Vente choisie: {$venteId}\n";

try {
    // Mesurer stocks avant
    $before = [];
    foreach ($lignesALivrer as $lg) {
        $pid = (int)$lg['produit_id'];
        $before[$pid] = stock_get_quantite_produit($pdo, $pid);
    }
    echo "Stocks avant:\n";
    foreach ($before as $pid => $q) echo " - produit {$pid}: {$q}\n";

    // Démarrer transaction et créer BL + lignes + mouvements
    $pdo->beginTransaction();

    $numeroBL = 'BL-TEST-' . date('Ymd-His');
    // Récupérer utilisateur courant si la fonction est disponible, sinon fallback à 1
    if (function_exists('utilisateurConnecte')) {
        $util = utilisateurConnecte();
        $magasinierId = $util['id'] ?? 1;
    } else {
        $magasinierId = 1;
    }

    // Insertion BL
    $stmt = $pdo->prepare("INSERT INTO bons_livraison (numero, date_bl, vente_id, client_id, transport_assure_par, observations, signe_client, magasinier_id) VALUES (:numero, :date_bl, :vente_id, :client_id, :transport, :obs, 0, :magasinier_id)");

    // Récup client pour la vente
    $s3 = $pdo->prepare("SELECT client_id FROM ventes WHERE id = :id");
    $s3->execute(['id' => $venteId]);
    $clientId = $s3->fetchColumn();

    $stmt->execute([
        'numero' => $numeroBL,
        'date_bl' => date('Y-m-d'),
        'vente_id' => $venteId,
        'client_id' => $clientId,
        'transport' => null,
        'obs' => null,
        'magasinier_id' => $magasinierId,
    ]);
    $blId = (int)$pdo->lastInsertId();

    $stmtBL = $pdo->prepare("INSERT INTO bons_livraison_lignes (bon_livraison_id, produit_id, quantite) VALUES (:bl_id, :produit_id, :quantite)");

    echo "BL temporaire créé: {$blId} (numero {$numeroBL})\n";

    foreach ($lignesALivrer as $lg) {
        $pid = (int)$lg['produit_id'];
        $qte = (float)$lg['quantite'];

        $stmtBL->execute(['bl_id' => $blId, 'produit_id' => $pid, 'quantite' => $qte]);

        // Enregistrer mouvement via API
        $mvtId = stock_enregistrer_mouvement($pdo, [
            'produit_id' => $pid,
            'date_mouvement' => date('Y-m-d H:i:s'),
            'type_mouvement' => 'SORTIE',
            'quantite' => $qte,
            'source_type' => 'VENTE',
            'source_id' => $venteId,
            'commentaire' => 'Test BL ' . $numeroBL,
            'utilisateur_id' => $magasinierId,
        ]);
        echo " Mouvement temporaire inséré: ID={$mvtId} pour produit {$pid} qte {$qte}\n";
    }

    // Mesurer stocks après insertion (dans la transaction)
    $after = [];
    foreach ($lignesALivrer as $lg) {
        $pid = (int)$lg['produit_id'];
        $after[$pid] = stock_get_quantite_produit($pdo, $pid);
    }
    echo "Stocks après (transaction ouverte):\n";
    foreach ($after as $pid => $q) echo " - produit {$pid}: {$q}\n";

    // Rollback
    $pdo->rollBack();
    echo "Rollback effectué.\n";

    // Vérifier qu'aucun BL ou mouvement n'est persisté
    $cntBl = (int)$pdo->prepare("SELECT COUNT(*) FROM bons_livraison WHERE id = :id")->execute(['id' => $blId]);
    // The above returns bool; better to query properly
    $cntBl = (int)$pdo->query("SELECT COUNT(*) FROM bons_livraison WHERE id = " . (int)$blId)->fetchColumn();
    $cntMvt = (int)$pdo->query("SELECT COUNT(*) FROM stocks_mouvements WHERE source_type = 'VENTE' AND source_id = " . (int)$venteId . " AND commentaire LIKE 'Test BL %'")->fetchColumn();

    echo "BL persistants (après rollback): {$cntBl}\n";
    echo "Mouvements persistants liés au test (après rollback): {$cntMvt}\n";

    // Vérifier stocks finaux
    $final = [];
    foreach ($lignesALivrer as $lg) {
        $pid = (int)$lg['produit_id'];
        $final[$pid] = stock_get_quantite_produit($pdo, $pid);
    }
    echo "Stocks finaux (après rollback):\n";
    foreach ($final as $pid => $q) echo " - produit {$pid}: {$q}\n";

    $ok = true;
    foreach ($before as $pid => $q) {
        if ($final[$pid] != $q) { $ok = false; break; }
    }

    if ($ok && $cntBl === 0 && $cntMvt === 0) {
        echo "TEST GENERER_BL OK: aucun effet persistant, stocks restaurés.\n";
        exit(0);
    } else {
        echo "TEST GENERER_BL FAIL: effets persistants ou stocks modifiés.\n";
        exit(2);
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Erreur pendant la simulation: " . $e->getMessage() . "\n";
    exit(3);
}
