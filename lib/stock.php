<?php
// lib/stock.php - FIXED VERSION with proper transaction handling

/**
 * Retourne la quantité théorique en stock pour un produit donné
 * en additionnant tous les mouvements de la table stocks_mouvements.
 */
function stock_get_quantite_produit(PDO $pdo, int $produitId): int
{
    $sql = "
        SELECT
            COALESCE(
                SUM(
                    CASE
                        WHEN type_mouvement = 'ENTREE'    THEN quantite
                        WHEN type_mouvement = 'SORTIE'    THEN -quantite
                        WHEN type_mouvement = 'AJUSTEMENT' THEN quantite
                        ELSE 0
                    END
                ),
                0
            ) AS qte
        FROM stocks_mouvements
        WHERE produit_id = :produit_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':produit_id' => $produitId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int)($row['qte'] ?? 0);
}

/**
 * Recalcule et stocke dans `produits.stock_actuel` la quantité théorique
 * pour le produit donné (d'après `stocks_mouvements`).
 */
function stock_recalculer_stock_produit(PDO $pdo, int $produitId): void
{
    $qte = stock_get_quantite_produit($pdo, $produitId);
    $stmt = $pdo->prepare("UPDATE produits SET stock_actuel = :qte WHERE id = :id");
    $stmt->execute([':qte' => $qte, ':id' => $produitId]);
}

/**
 * Enregistre un mouvement de stock simple.
 *
 * $data = [
 *   'date_mouvement'  => 'YYYY-MM-DD' (optionnel, défaut = aujourd'hui),
 *   'type_mouvement'  => 'ENTREE' | 'SORTIE' | 'CORRECTION',
 *   'produit_id'      => int (obligatoire),
 *   'quantite'        => float|int (obligatoire),
 *   'source_module'   => 'VENTE' | 'ACHAT' | 'AUTRE' | null,
 *   'source_id'       => int|null,
 *   'utilisateur_id'  => int|null,
 *   'commentaire'     => string|null,
 * ]
 */
function stock_enregistrer_mouvement(PDO $pdo, array $data): int
{
    $sql = "
        INSERT INTO stocks_mouvements (
            produit_id,
            date_mouvement,
            type_mouvement,
            quantite,
            source_type,
            source_id,
            commentaire,
            utilisateur_id
        ) VALUES (
            :produit_id,
            :date_mouvement,
            :type_mouvement,
            :quantite,
            :source_type,
            :source_id,
            :commentaire,
            :utilisateur_id
        )
    ";

    $stmt = $pdo->prepare($sql);

    // Compatibilité : mapper d'anciennes valeurs
    $type = $data['type_mouvement'] ?? 'AJUSTEMENT';
    if (strtoupper($type) === 'CORRECTION') {
        $type = 'AJUSTEMENT';
    }

    $stmt->execute([
        ':produit_id'     => $data['produit_id'],
        ':date_mouvement' => $data['date_mouvement'] ?? date('Y-m-d H:i:s'),
        ':type_mouvement' => $type,
        ':quantite'       => $data['quantite'],
        ':source_type'    => $data['source_type'] ?? ($data['source_module'] ?? null),
        ':source_id'      => $data['source_id'] ?? null,
        ':commentaire'    => $data['commentaire'] ?? null,
        ':utilisateur_id' => $data['utilisateur_id'] ?? 1,
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Mettre à jour le stock courant pour ce produit
    try {
        stock_recalculer_stock_produit($pdo, (int)$data['produit_id']);
    } catch (Exception $e) {
        // Ne pas interrompre le flux en production ; loger si besoin
    }

    return $newId;
}

/**
 * Supprime tous les mouvements liés à une source donnée
 * (ex : une vente ou un achat), pour pouvoir les recalculer proprement.
 */
function stock_supprimer_mouvements_source(PDO $pdo, string $sourceModule, int $sourceId): void
{
    // Récupérer les produits impactés pour recalculer ensuite
    $stmt = $pdo->prepare("SELECT DISTINCT produit_id FROM stocks_mouvements WHERE source_type = :s AND source_id = :id");
    $stmt->execute([':s' => $sourceModule, ':id' => $sourceId]);
    $produits = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $sql = "DELETE FROM stocks_mouvements WHERE source_type = :source_type AND source_id = :source_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':source_type' => $sourceModule,
        ':source_id'   => $sourceId,
    ]);

    if (!empty($produits)) {
        foreach ($produits as $pid) {
            stock_recalculer_stock_produit($pdo, (int)$pid);
        }
    }
}

/**
 * Re-calcul des mouvements de stock pour UNE vente.
 * 
 * PATTERN CORRIGÉ:
 * 1. Tous les contrôles/fetches AVANT beginTransaction()
 * 2. Transaction uniquement pour les opérations d'écriture
 * 3. Garantie de commit/rollBack avec try/catch/finally
 */
function stock_synchroniser_vente(PDO $pdo, int $venteId): void
{
    // ✅ PHASE 1 : Validations et fetches AVANT transaction
    
    // 1) On récupère la vente
    $stmt = $pdo->prepare("
        SELECT id, numero, date_vente, statut
        FROM ventes
        WHERE id = :id
    ");
    $stmt->execute([':id' => $venteId]);
    $vente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vente) {
        return; // Vente inexistante -> rien à faire
    }

    // 2) Si la vente n'est pas livrée, on ne touche pas au stock
    if ($vente['statut'] !== 'LIVREE') {
        return;
    }

    // 3) On récupère les lignes de la vente (agrégées par produit)
    $stmt = $pdo->prepare("
        SELECT
            produit_id,
            SUM(quantite) AS qte
        FROM ventes_lignes
        WHERE vente_id = :vente_id
          AND produit_id IS NOT NULL
        GROUP BY produit_id
    ");
    $stmt->execute([':vente_id' => $venteId]);
    $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$lignes) {
        return; // aucune ligne -> pas de mouvement
    }

    // ✅ PHASE 2 : Transaction garanti de fermeture
    try {
        $pdo->beginTransaction();
        
        // Suppression des anciens mouvements
        stock_supprimer_mouvements_source($pdo, 'VENTE', $venteId);

        // Insertion des nouveaux mouvements
        $dateMvt = $vente['date_vente'] ?: date('Y-m-d');
        $comment = 'Sortie suite à la vente ' . $vente['numero'];

        foreach ($lignes as $ligne) {
            $produitId = (int)$ligne['produit_id'];
            $qte       = (float)$ligne['qte'];

            if ($produitId <= 0 || $qte <= 0) {
                continue;
            }

            stock_enregistrer_mouvement($pdo, [
                'date_mouvement' => $dateMvt,
                'type_mouvement' => 'SORTIE',
                'produit_id'     => $produitId,
                'quantite'       => $qte,
                'source_type'    => 'VENTE',
                'source_id'      => $venteId,
                'commentaire'    => $comment,
            ]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[STOCK] Erreur synchronisation vente ' . $venteId . ': ' . $e->getMessage());
    }
}

/**
 * Re-calcul des mouvements de stock pour UN achat.
 * 
 * PATTERN CORRIGÉ:
 * 1. Tous les contrôles/fetches AVANT beginTransaction()
 * 2. Transaction uniquement pour les opérations d'écriture
 * 3. Garantie de commit/rollBack avec try/catch/finally
 */
function stock_synchroniser_achat(PDO $pdo, int $achatId): void
{
    // ✅ PHASE 1 : Validations et fetches AVANT transaction
    
    // 1) On récupère l'achat
    $stmt = $pdo->prepare("
        SELECT id, numero, date_achat, statut
        FROM achats
        WHERE id = :id
    ");
    $stmt->execute([':id' => $achatId]);
    $achat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$achat) {
        return; // Achat inexistant
    }

    // 2) On récupère les lignes d'achat agrégées par produit
    $stmt = $pdo->prepare("
        SELECT
            produit_id,
            SUM(quantite) AS qte
        FROM achats_lignes
        WHERE achat_id = :achat_id
          AND produit_id IS NOT NULL
        GROUP BY produit_id
    ");
    $stmt->execute([':achat_id' => $achatId]);
    $lignes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$lignes) {
        return; // aucune ligne -> pas de mouvement
    }

    // ✅ PHASE 2 : Transaction garanti de fermeture
    try {
        $pdo->beginTransaction();
        
        // Suppression des anciens mouvements
        stock_supprimer_mouvements_source($pdo, 'ACHAT', $achatId);

        // Insertion des nouveaux mouvements
        $dateMvt = $achat['date_achat'] ?: date('Y-m-d');
        $comment = 'Entrée suite à l\'achat ' . $achat['numero'];

        foreach ($lignes as $ligne) {
            $produitId = (int)$ligne['produit_id'];
            $qte       = (float)$ligne['qte'];

            if ($produitId <= 0 || $qte <= 0) {
                continue;
            }

            stock_enregistrer_mouvement($pdo, [
                'date_mouvement' => $dateMvt,
                'type_mouvement' => 'ENTREE',
                'produit_id'     => $produitId,
                'quantite'       => $qte,
                'source_type'    => 'ACHAT',
                'source_id'      => $achatId,
                'commentaire'    => $comment,
            ]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[STOCK] Erreur synchronisation achat ' . $achatId . ': ' . $e->getMessage());
    }
}
