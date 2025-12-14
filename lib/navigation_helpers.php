<?php
/**
 * Navigation Helpers - Fonctions pour faciliter l'accès aux éléments liés
 * Utilisé pour créer des liens croisés entre ventes, livraisons, ordres, litiges, stock
 */

/**
 * Récupérer tous les litiges d'une vente
 * @param PDO $pdo
 * @param int $venteId
 * @return array
 */
function get_litiges_by_vente(PDO $pdo, int $venteId): array {
    $stmt = $pdo->prepare("
        SELECT rl.id, rl.date_retour, rl.type_probleme, rl.statut_traitement,
               rl.montant_rembourse, rl.montant_avoir, p.code_produit, p.designation
        FROM retours_litiges rl
        JOIN produits p ON p.id = rl.produit_id
        WHERE rl.vente_id = ?
        ORDER BY rl.date_retour DESC
    ");
    $stmt->execute([$venteId]);
    return $stmt->fetchAll();
}

/**
 * Récupérer tous les bons de livraison d'une vente
 * @param PDO $pdo
 * @param int $venteId
 * @return array
 */
function get_livraisons_by_vente(PDO $pdo, int $venteId): array {
    $stmt = $pdo->prepare("
        SELECT bl.id, bl.numero, bl.date_bl, 
               COUNT(bll.id) as nb_lignes, SUM(bll.quantite) as total_livre
        FROM bons_livraison bl
        LEFT JOIN bons_livraison_lignes bll ON bll.bon_livraison_id = bl.id
        WHERE bl.vente_id = ?
        GROUP BY bl.id
        ORDER BY bl.date_bl DESC
    ");
    $stmt->execute([$venteId]);
    return $stmt->fetchAll();
}

/**
 * Récupérer tous les ordres de préparation d'une vente
 * @param PDO $pdo
 * @param int $venteId
 * @return array
 */
function get_ordres_by_vente(PDO $pdo, int $venteId): array {
    $stmt = $pdo->prepare("
        SELECT op.id, op.numero_ordre as numero, op.date_ordre as date_creation, op.statut,
               COUNT(opl.id) as nb_lignes, SUM(opl.quantite_preparee) as total_prepare
        FROM ordres_preparation op
        LEFT JOIN ordres_preparation_lignes opl ON opl.ordre_preparation_id = op.id
        WHERE op.vente_id = ?
        GROUP BY op.id
        ORDER BY op.date_ordre DESC
    ");
    $stmt->execute([$venteId]);
    return $stmt->fetchAll();
}

/**
 * Récupérer le montant total encaissé pour une vente (unified to journal_caisse)
 * @param PDO $pdo
 * @param int $venteId
 * @return float
 */
function get_montant_encaisse(PDO $pdo, int $venteId): float {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(montant), 0) as total
        FROM journal_caisse
        WHERE (vente_id = ? OR (source_id = ? AND source_type = 'VENTE'))
          AND sens = 'RECETTE' AND est_annule = 0
    ");
    $stmt->execute([$venteId, $venteId]);
    return (float)$stmt->fetch()['total'];
}

/**
 * Récupérer le montant total des retours pour une vente
 * @param PDO $pdo
 * @param int $venteId
 * @return float
 */
function get_montant_retours(PDO $pdo, int $venteId): float {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(montant_rembourse + montant_avoir), 0) as total
        FROM retours_litiges
        WHERE vente_id = ?
    ");
    $stmt->execute([$venteId]);
    return (float)$stmt->fetch()['total'];
}

/**
 * Vérifier la cohérence d'une vente
 * @param PDO $pdo
 * @param int $venteId
 * @return array ['ok' => bool, 'problemes' => array]
 */
function verify_vente_coherence(PDO $pdo, int $venteId): array {
    $problemes = [];
    
    // Récupérer les données de vente
    $stmt = $pdo->prepare("
        SELECT montant_total_ttc FROM ventes WHERE id = ?
    ");
    $stmt->execute([$venteId]);
    $vente = $stmt->fetch();
    
    if (!$vente) {
        return ['ok' => false, 'problemes' => ['Vente non trouvée']];
    }
    
    $montantVente = $vente['montant_total_ttc'];
    
    // Vérif 1: Montant livraisons (basé sur les lignes de vente)
    // bons_livraison n'a pas de montant, on l'estime par les quantités livrées
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(vl.prix_unitaire * bll.quantite), 0) as total 
        FROM bons_livraison bl
        JOIN bons_livraison_lignes bll ON bll.bon_livraison_id = bl.id
        JOIN ventes_lignes vl ON vl.vente_id = bl.vente_id AND vl.produit_id = bll.produit_id
        WHERE bl.vente_id = ?
    ");
    $stmt->execute([$venteId]);
    $montantLivraison = $stmt->fetch()['total'];
    
    $tolerance = 100;
    if (abs($montantLivraison - $montantVente) > $tolerance) {
        $problemes[] = "Montants livraisons (" . number_format($montantLivraison, 0, ',', ' ') . 
                       ") ≠ Montant vente (" . number_format($montantVente, 0, ',', ' ') . ")";
    }
    
    // Vérif 2: Quantités livrées
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(vl.quantite), 0) as total FROM ventes_lignes vl WHERE vl.vente_id = ?
    ");
    $stmt->execute([$venteId]);
    $qteCommandee = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(bll.quantite), 0) as total 
        FROM bons_livraison_lignes bll 
        JOIN bons_livraison bl ON bl.id = bll.bon_livraison_id 
        WHERE bl.vente_id = ?
    ");
    $stmt->execute([$venteId]);
    $qteLivree = $stmt->fetch()['total'];
    
    if ($qteLivree > $qteCommandee) {
        $problemes[] = "Quantités livrées (" . $qteLivree . ") > Commandées (" . $qteCommandee . ")";
    }
    
    // Vérif 3: Mouvements stock
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantite), 0) as total 
        FROM stocks_mouvements 
        WHERE (source_id = ? AND source_type = 'VENTE') AND type_mouvement = 'SORTIE'
    ");
    $stmt->execute([$venteId]);
    $qteSortieStock = $stmt->fetch()['total'];
    
    if ($qteSortieStock != $qteLivree) {
        $problemes[] = "Sorties stock (" . $qteSortieStock . ") ≠ Quantités livrées (" . $qteLivree . ")";
    }
    
    // Vérif 4: Écritures comptables
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM compta_ecritures ce
        JOIN compta_pieces cp ON cp.id = ce.piece_id
        WHERE (cp.reference_type = 'VENTE' AND cp.reference_id = ?)
    ");
    $stmt->execute([$venteId]);
    $nbEcritures = $stmt->fetch()['count'];
    
    if ($nbEcritures == 0) {
        $problemes[] = "Aucune écriture comptable détectée";
    }
    
    return [
        'ok' => empty($problemes),
        'problemes' => $problemes
    ];
}

/**
 * Générer un résumé statistique d'une vente
 * @param PDO $pdo
 * @param int $venteId
 * @return array
 */
function get_vente_summary(PDO $pdo, int $venteId): array {
    $stmt = $pdo->prepare("
        SELECT id, numero, montant_total_ttc, statut FROM ventes WHERE id = ?
    ");
    $stmt->execute([$venteId]);
    $vente = $stmt->fetch();
    
    if (!$vente) return [];
    
    $summary = [
        'id' => $vente['id'],
        'numero' => $vente['numero'],
        'montant_ttc' => $vente['montant_total_ttc'],
        'statut' => $vente['statut'],
        'nb_ordres' => count(get_ordres_by_vente($pdo, $venteId)),
        'nb_livraisons' => count(get_livraisons_by_vente($pdo, $venteId)),
        'nb_litiges' => count(get_litiges_by_vente($pdo, $venteId)),
        'montant_encaisse' => get_montant_encaisse($pdo, $venteId),
        'montant_retours' => get_montant_retours($pdo, $venteId),
    ];
    
    $summary['taux_encaissement'] = ($vente['montant_total_ttc'] > 0) 
        ? round(($summary['montant_encaisse'] / $vente['montant_total_ttc']) * 100, 1)
        : 0;
    
    $summary['taux_retours'] = ($vente['montant_total_ttc'] > 0)
        ? round(($summary['montant_retours'] / $vente['montant_total_ttc']) * 100, 1)
        : 0;
    
    $verif = verify_vente_coherence($pdo, $venteId);
    $summary['coherence_ok'] = $verif['ok'];
    $summary['problemes'] = $verif['problemes'];
    
    return $summary;
}

/**
 * Récupérer la vente associée à une livraison
 * @param PDO $pdo
 * @param int $bonId
 * @return array|null
 */
function get_vente_by_livraison(PDO $pdo, int $bonId): ?array {
    $stmt = $pdo->prepare("
        SELECT v.* FROM ventes v
        JOIN bons_livraison bl ON bl.vente_id = v.id
        WHERE bl.id = ?
    ");
    $stmt->execute([$bonId]);
    return $stmt->fetch() ?: null;
}

/**
 * Récupérer la vente associée à un litige
 * @param PDO $pdo
 * @param int $litigeId
 * @return array|null
 */
function get_vente_by_litige(PDO $pdo, int $litigeId): ?array {
    $stmt = $pdo->prepare("
        SELECT v.* FROM ventes v
        JOIN retours_litiges rl ON rl.vente_id = v.id
        WHERE rl.id = ?
    ");
    $stmt->execute([$litigeId]);
    return $stmt->fetch() ?: null;
}

/**
 * Récupérer tous les litiges d'une livraison
 * @param PDO $pdo
 * @param int $bonId
 * @return array
 */
function get_litiges_by_livraison(PDO $pdo, int $bonId): array {
    $stmt = $pdo->prepare("
        SELECT rl.id, rl.date_retour, rl.type_probleme, rl.statut_traitement,
               rl.montant_rembourse, rl.montant_avoir
        FROM retours_litiges rl
        JOIN bons_livraison bl ON bl.vente_id = rl.vente_id
        WHERE bl.id = ?
        ORDER BY rl.date_retour DESC
    ");
    $stmt->execute([$bonId]);
    return $stmt->fetchAll();
}

/**
 * Générer une mini-carte de navigation pour une vente
 * @param PDO $pdo
 * @param int $venteId
 * @return string HTML
 */
function generate_vente_nav_card(PDO $pdo, int $venteId): string {
    $summary = get_vente_summary($pdo, $venteId);
    if (empty($summary)) return '';
    
    $html = '<div class="card card-sm" style="border-left: 4px solid var(--accent);">';
    $html .= '<div class="card-body p-3">';
    $html .= '<div class="d-flex justify-content-between align-items-start mb-2">';
    $html .= '<div>';
    $html .= '<h6 class="mb-0">Vente #' . htmlspecialchars($summary['numero']) . '</h6>';
    $html .= '<small class="text-muted">' . number_format($summary['montant_ttc'], 0, ',', ' ') . ' FCFA</small>';
    $html .= '</div>';
    $html .= '<span class="badge ' . ($summary['coherence_ok'] ? 'bg-success' : 'bg-danger') . '">';
    $html .= $summary['coherence_ok'] ? '✅ OK' : '⚠️ Erreur';
    $html .= '</span>';
    $html .= '</div>';
    
    $html .= '<div class="row g-2 text-center text-sm">';
    $html .= '<div class="col-4"><small>' . $summary['nb_ordres'] . ' ordres</small></div>';
    $html .= '<div class="col-4"><small>' . $summary['nb_livraisons'] . ' livraisons</small></div>';
    $html .= '<div class="col-4"><small>' . $summary['nb_litiges'] . ' litiges</small></div>';
    $html .= '</div>';
    
    $html .= '<a href="' . url_for('ventes/detail_360.php?id=' . $summary['id']) . '" class="btn btn-sm btn-outline-primary w-100 mt-3">';
    $html .= '<i class="bi bi-arrow-right"></i> Voir détails</a>';
    $html .= '</div></div>';
    
    return $html;
}
