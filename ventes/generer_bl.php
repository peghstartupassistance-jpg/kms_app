<?php
// ventes/generer_bl.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_CREER');

global $pdo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url_for('ventes/list.php'));
    exit;
}

try {
    verifierCsrf($_POST['csrf_token'] ?? '');

    $venteId = isset($_POST['vente_id']) ? (int)$_POST['vente_id'] : 0;
    if ($venteId <= 0) {
        throw new RuntimeException("Vente invalide.");
    }

    // Récup vente
    $stmt = $pdo->prepare("
        SELECT v.*, c.id AS client_id, c.nom AS client_nom
        FROM ventes v
        JOIN clients c ON c.id = v.client_id
        WHERE v.id = :id
    ");
    $stmt->execute(['id' => $venteId]);
    $vente = $stmt->fetch();

    if (!$vente) {
        throw new RuntimeException("Vente introuvable.");
    }
    if ($vente['statut'] === 'ANNULEE') {
        throw new RuntimeException("Impossible de générer un BL pour une vente annulée.");
    }

    // Récup lignes de vente
    $stmt = $pdo->prepare("
        SELECT vl.*, p.stock_actuel, p.designation
        FROM ventes_lignes vl
        JOIN produits p ON p.id = vl.produit_id
        WHERE vl.vente_id = :id
    ");
    $stmt->execute(['id' => $venteId]);
    $lignesVente = $stmt->fetchAll();

    if (empty($lignesVente)) {
        throw new RuntimeException("Aucune ligne de vente pour cette vente.");
    }

    // Quantités déjà livrées par produit
    $stmt = $pdo->prepare("
        SELECT bl.vente_id, bll.produit_id, SUM(bll.quantite) AS qte_livree
        FROM bons_livraison_lignes bll
        JOIN bons_livraison bl ON bl.id = bll.bon_livraison_id
        WHERE bl.vente_id = :vente_id
        GROUP BY bl.vente_id, bll.produit_id
    ");
    $stmt->execute(['vente_id' => $venteId]);
    $livraisonsExistantes = $stmt->fetchAll();

    $qteLivreeParProduit = [];
    foreach ($livraisonsExistantes as $row) {
        $qteLivreeParProduit[(int)$row['produit_id']] = (float)$row['qte_livree'];
    }

    // Calcul des quantités restantes + vérifier s'il reste quelque chose à livrer
    $lignesALivrer = [];
    foreach ($lignesVente as $lv) {
        $prodId = (int)$lv['produit_id'];
        $qteCommandee = (float)$lv['quantite'];
        $qteLivree = $qteLivreeParProduit[$prodId] ?? 0.0;
        $qteRestante = max(0, $qteCommandee - $qteLivree);

        if ($qteRestante > 0) {
            $lignesALivrer[] = [
                'produit_id' => $prodId,
                'quantite'   => $qteRestante,
            ];
        }
    }

    if (empty($lignesALivrer)) {
        throw new RuntimeException("Toute la vente est déjà livrée. Aucun BL à générer.");
    }

    // Création du BL + mouvements de stock en transaction
    $pdo->beginTransaction();

    $numeroBL = 'BL-' . date('Ymd-His');

    $utilisateur = utilisateurConnecte();
    $magasinierId = $utilisateur['id'] ?? null;

    // Insert BL
    $stmt = $pdo->prepare("
        INSERT INTO bons_livraison
        (numero, date_bl, vente_id, client_id, transport_assure_par, observations, signe_client, magasinier_id)
        VALUES
        (:numero, :date_bl, :vente_id, :client_id, :transport, :obs, 0, :magasinier_id)
    ");
    $stmt->execute([
        'numero'        => $numeroBL,
        'date_bl'       => date('Y-m-d'),
        'vente_id'      => $venteId,
        'client_id'     => $vente['client_id'],
        'transport'     => null,
        'obs'           => null,
        'magasinier_id' => $magasinierId,
    ]);
    $blId = (int)$pdo->lastInsertId();

    // Insert lignes BL + mouvements stock
    $stmtBL = $pdo->prepare("
        INSERT INTO bons_livraison_lignes (bon_livraison_id, produit_id, quantite)
        VALUES (:bl_id, :produit_id, :quantite)
    ");

    // On utilisera la librairie de stock pour enregistrer les mouvements
    // (stock_enregistrer_mouvement) afin que le recalcul et la logique
    // métier soient centralisés dans `lib/stock.php`.

    foreach ($lignesALivrer as $lg) {
        $prodId = $lg['produit_id'];
        $qte    = $lg['quantite'];

        // Ligne BL
        $stmtBL->execute([
            'bl_id'      => $blId,
            'produit_id' => $prodId,
            'quantite'   => $qte,
        ]);

        // Enregistrer le mouvement via l'API centralisée
        stock_enregistrer_mouvement($pdo, [
            'produit_id'     => $prodId,
            'date_mouvement' => date('Y-m-d H:i:s'),
            'type_mouvement' => 'SORTIE',
            'quantite'       => $qte,
            'source_type'    => 'VENTE',
            'source_id'      => $venteId,
            'commentaire'    => 'Sortie via BL ' . $numeroBL,
            'utilisateur_id' => $magasinierId,
        ]);
    }

    // Recalcul du statut de la vente après cette livraison
    $stmt = $pdo->prepare("
        SELECT vl.produit_id, vl.quantite,
               COALESCE(SUM(bll.quantite), 0) AS qte_livree
        FROM ventes_lignes vl
        LEFT JOIN bons_livraison b ON b.vente_id = vl.vente_id
        LEFT JOIN bons_livraison_lignes bll ON bll.bon_livraison_id = b.id
            AND bll.produit_id = vl.produit_id
        WHERE vl.vente_id = :vente_id
        GROUP BY vl.produit_id, vl.quantite
    ");
    $stmt->execute(['vente_id' => $venteId]);
    $rows = $stmt->fetchAll();

    $toutLivre = true;
    foreach ($rows as $r) {
        if ((float)$r['qte_livree'] < (float)$r['quantite']) {
            $toutLivre = false;
            break;
        }
    }

    $nouveauStatut = $toutLivre ? 'LIVREE' : 'PARTIELLEMENT_LIVREE';

    $stmt = $pdo->prepare("
        UPDATE ventes
        SET statut = :statut
        WHERE id = :id
    ");
    $stmt->execute([
        'statut' => $nouveauStatut,
        'id'     => $venteId,
    ]);

    $pdo->commit();

    $_SESSION['flash_success'] = "Bon de livraison $numeroBL généré avec succès.";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['flash_error'] = $e->getMessage();
}

header('Location: ' . url_for('ventes/detail.php') . '?id=' . $venteId);
exit;
