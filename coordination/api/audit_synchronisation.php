<?php
/**
 * API d'audit de synchronisation complète
 * Vérifie que TOUTES les opérations métier (litiges, retours, remboursements, etc.)
 * sont correctement synchronisées avec stock + caisse + compta
 */

require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

if (ob_get_level()) ob_clean();
header('Content-Type: application/json; charset=utf-8');

/**
 * AUDIT 1 : Litiges sans trace de synchronisation stock
 */
$audit = [];

// Litiges ayant un statut de REMBOURSEMENT ou REMPLACEMENT sans mouvements de stock
$stmt = $pdo->query("
    SELECT rl.id, rl.statut_traitement, rl.motif
    FROM retours_litiges rl
    WHERE rl.statut_traitement IN ('REMBOURSEMENT_EFFECTUE', 'REMPLACEMENT_EFFECTUE')
    AND NOT EXISTS (
        SELECT 1 FROM stocks_mouvements sm
        WHERE sm.raison LIKE CONCAT('%Litige #', rl.id, '%')
    )
    LIMIT 10
");
$audit['litiges_sans_stock'] = $stmt->fetchAll();

/**
 * AUDIT 2 : Litiges avec remboursement mais sans trace caisse
 */
$stmt = $pdo->query("
    SELECT rl.id, rl.montant_rembourse, rl.date_resolution
    FROM retours_litiges rl
    WHERE rl.montant_rembourse > 0
    AND rl.statut_traitement = 'REMBOURSEMENT_EFFECTUE'
    AND NOT EXISTS (
        SELECT 1 FROM journal_caisse jc
        WHERE jc.libelle LIKE CONCAT('%litige #', rl.id, '%')
    )
    LIMIT 10
");
$audit['litiges_sans_caisse'] = $stmt->fetchAll();

/**
 * AUDIT 3 : Litiges avec avoir mais sans trace compta
 */
$stmt = $pdo->query("
    SELECT rl.id, rl.montant_avoir, rl.date_resolution
    FROM retours_litiges rl
    WHERE rl.montant_avoir > 0
    AND NOT EXISTS (
        SELECT 1 FROM compta_pieces cp
        WHERE cp.numero_piece LIKE CONCAT('%AVOIR-%')
           OR cp.libelle LIKE CONCAT('%litige #', rl.id, '%')
    )
    LIMIT 10
");
$audit['litiges_sans_compta'] = $stmt->fetchAll();

/**
 * AUDIT 4 : Retours en stock sans lien à un litige ou vente
 */
$stmt = $pdo->query("
    SELECT sm.id, sm.type_mouvement, sm.quantite, sm.raison, sm.date_mouvement
    FROM stocks_mouvements sm
    WHERE sm.type_mouvement = 'ENTREE'
    AND (sm.raison LIKE '%Retour%' OR sm.raison LIKE '%retour%')
    AND sm.raison NOT LIKE '%Litige%'
    AND sm.raison NOT LIKE '%vente%'
    LIMIT 10
");
$audit['stocks_orphelins'] = $stmt->fetchAll();

/**
 * AUDIT 5 : Remboursements en caisse sans litige
 */
$stmt = $pdo->query("
    SELECT jc.id, jc.type_operation, jc.montant, jc.libelle, jc.date_operation
    FROM journal_caisse jc
    WHERE jc.type_operation = 'REMBOURSEMENT_CLIENT_LITIGE'
    AND NOT EXISTS (
        SELECT 1 FROM retours_litiges rl
        WHERE jc.libelle LIKE CONCAT('%litige #', rl.id, '%')
    )
    LIMIT 10
");
$audit['remboursements_orphelins'] = $stmt->fetchAll();

/**
 * AUDIT 6 : Écritures RRR/remboursement sans pièce comptable
 */
$stmt = $pdo->query("
    SELECT ce.id, ce.compte, ce.montant, ce.libelle
    FROM compta_ecritures ce
    WHERE ce.compte IN ('701001', '411001', '512001')
    AND (ce.libelle LIKE '%litige%' OR ce.libelle LIKE '%RRR%' OR ce.libelle LIKE '%remboursement%')
    AND NOT EXISTS (
        SELECT 1 FROM compta_pieces cp
        WHERE cp.id = ce.piece_id
        AND (cp.numero_piece LIKE '%REMB-%' OR cp.numero_piece LIKE '%AVOIR-%')
    )
    LIMIT 10
");
$audit['compta_orpheline'] = $stmt->fetchAll();

/**
 * AUDIT 7 : Statistiques globales de synchronisation
 */
$stats = [];

// Total litiges par statut
$stmt = $pdo->query("
    SELECT statut_traitement, COUNT(*) as total, SUM(montant_rembourse) as total_remboursé
    FROM retours_litiges
    GROUP BY statut_traitement
");
$stats['litiges_par_statut'] = $stmt->fetchAll();

// Total mouvements stock "retour"
$stmt = $pdo->query("
    SELECT COUNT(*) as total_mouvements, SUM(quantite) as total_quantite
    FROM stocks_mouvements
    WHERE raison LIKE '%Retour%' OR raison LIKE '%Litige%'
");
$stats['mouvements_stock'] = $stmt->fetch();

// Total remboursements caisse
$stmt = $pdo->query("
    SELECT COUNT(*) as total_remboursements, SUM(montant) as total_montant
    FROM journal_caisse
    WHERE type_operation = 'REMBOURSEMENT_CLIENT_LITIGE'
");
$stats['remboursements_caisse'] = $stmt->fetch();

// Total écritures RRR
$stmt = $pdo->query("
    SELECT COUNT(*) as total_ecritures, SUM(montant) as total_montant
    FROM compta_ecritures
    WHERE compte IN ('701001', '411001')
    AND (libelle LIKE '%RRR%' OR libelle LIKE '%litige%')
");
$stats['ecritures_rrr'] = $stmt->fetch();

echo json_encode([
    'timestamp'     => date('Y-m-d H:i:s'),
    'audit'         => $audit,
    'statistiques'  => $stats,
    'message'       => 'Audit complet de synchronisation des opérations métier'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
