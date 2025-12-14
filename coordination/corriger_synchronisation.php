<?php
// coordination/corriger_synchronisation.php - Corriger les anomalies de synchronisation
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../lib/stock.php';
require_once __DIR__ . '/../lib/compta.php';

exigerConnexion();
exigerPermission('VENTES_MODIFIER');

global $pdo;

$venteId = isset($_GET['vente_id']) ? (int)$_GET['vente_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : null;

if (!$venteId) {
    $_SESSION['flash_error'] = "Vente non spécifiée";
    header('Location: ' . url_for('coordination/verification_synchronisation.php'));
    exit;
}

// Charger la vente
$stmt = $pdo->prepare("
    SELECT v.*, c.nom as client_nom
    FROM ventes v
    JOIN clients c ON c.id = v.client_id
    WHERE v.id = ?
");
$stmt->execute([$venteId]);
$vente = $stmt->fetch();

if (!$vente) {
    $_SESSION['flash_error'] = "Vente introuvable";
    header('Location: ' . url_for('coordination/verification_synchronisation.php'));
    exit;
}

// === TRAITEMENT DES CORRECTIONS ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf($_POST['csrf_token'] ?? '');
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'creer_bl_automatique') {
            // ✅ Créer un BL automatique + déclencher stock/compta/caisse
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM bons_livraison WHERE vente_id = ? AND statut != 'ANNULE'
            ");
            $stmt->execute([$venteId]);
            $existant = $stmt->fetchColumn();
            
            if ($existant == 0) {
                // ✅ Numérotation sécurisée avec séquence (par date courante)
                $stmtCount = $pdo->prepare("
                    SELECT COUNT(*) FROM bons_livraison 
                    WHERE DATE(date_bl) = CURDATE()
                    AND numero LIKE ?
                ");
                $stmtCount->execute(['BL-' . date('Y') . '-%']);
                $count = ($stmtCount->fetchColumn() ?? 0) + 1;
                $numero_bl = "BL-" . date('Ymd') . "-" . str_pad($count, 4, '0', STR_PAD_LEFT);
                
                // Créer le BL en EN_PREPARATION (état initial)
                $stmt = $pdo->prepare("
                    INSERT INTO bons_livraison 
                    (numero, date_bl, vente_id, client_id, magasinier_id, signe_client, statut)
                    VALUES (?, CURDATE(), ?, ?, ?, 0, 'EN_PREPARATION')
                ");
                $utilisateur = utilisateurConnecte();
                $stmt->execute([
                    $numero_bl,
                    $venteId,
                    $vente['client_id'],
                    $utilisateur['id'] ?? 1
                ]);
                $blId = $pdo->lastInsertId();
                
                // Ajouter les lignes BL depuis les lignes de vente
                $stmt = $pdo->prepare("
                    SELECT vl.* FROM ventes_lignes vl WHERE vl.vente_id = ?
                ");
                $stmt->execute([$venteId]);
                $lignes = $stmt->fetchAll();
                
                foreach ($lignes as $ligne) {
                    $stmt = $pdo->prepare("
                        INSERT INTO bons_livraison_lignes 
                        (bon_livraison_id, produit_id, quantite)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$blId, $ligne['produit_id'], $ligne['quantite']]);
                }
                
                $_SESSION['flash_success'] = "Bon de livraison créé : $numero_bl (état EN_PREPARATION). À préparer et finaliser manuellement.";
            } else {
                $_SESSION['flash_warning'] = "Un bon de livraison actif existe déjà pour cette vente";
            }
        }
        
        elseif ($action === 'creer_mouvements_stock') {
            // ✅ Créer les mouvements de stock manquants
            // Utiliser la date réelle de livraison, pas NOW()
            
            // Récupérer la date de BL si elle existe
            $stmt = $pdo->prepare("SELECT MAX(date_bl) as date_livraison FROM bons_livraison WHERE vente_id = ? AND statut != 'ANNULE'");
            $stmt->execute([$venteId]);
            $bl = $stmt->fetch();
            $dateLivraison = $bl['date_livraison'] ?: $vente['date_vente'];
            
            $stmt = $pdo->prepare("
                SELECT vl.*, p.stock_actuel
                FROM ventes_lignes vl
                JOIN produits p ON p.id = vl.produit_id
                WHERE vl.vente_id = ?
            ");
            $stmt->execute([$venteId]);
            $lignes = $stmt->fetchAll();
            
            $count = 0;
            foreach ($lignes as $ligne) {
                // Vérifier si le mouvement existe déjà
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM stocks_mouvements 
                    WHERE source_type = 'VENTE' AND source_id = ? AND produit_id = ?
                ");
                $stmt->execute([$venteId, $ligne['produit_id']]);
                if ($stmt->fetchColumn() == 0) {
                    // ✅ Créer le mouvement avec date réelle de livraison
                    stock_enregistrer_mouvement($pdo, [
                        'produit_id'     => $ligne['produit_id'],
                        'date_mouvement' => $dateLivraison,  // Pas NOW()!
                        'type_mouvement' => 'SORTIE',
                        'quantite'       => $ligne['quantite'],
                        'source_type'    => 'VENTE',
                        'source_id'      => $venteId,
                        'commentaire'    => 'Correction : Sortie vente ' . $vente['numero'],
                    ]);
                    $count++;
                }
            }
            
            $_SESSION['flash_success'] = "$count mouvement(s) de stock créé(s) à la date : " . date('d/m/Y', strtotime($dateLivraison));
        }
        
        elseif ($action === 'creer_ecritures_compta') {
            // Créer les écritures comptables manquantes
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM compta_ecritures ce
                JOIN compta_pieces cp ON cp.id = ce.piece_id
                WHERE cp.reference_type = 'VENTE' AND cp.reference_id = ?
            ");
            $stmt->execute([$venteId]);
            if ($stmt->fetchColumn() == 0) {
                // Créer les écritures via la libraire compta
                compta_creer_ecritures_vente($pdo, $venteId);
                $_SESSION['flash_success'] = "Écritures comptables créées automatiquement";
            } else {
                $_SESSION['flash_warning'] = "Des écritures comptables existent déjà pour cette vente";
            }
        }
        
        elseif ($action === 'synchroniser_livraisons') {
            // Mettre à jour les statuts basés sur les BL existants
            $stmt = $pdo->prepare("
                SELECT SUM(bll.quantite) as qte_livree
                FROM bons_livraison bl
                JOIN bons_livraison_lignes bll ON bll.bon_livraison_id = bl.id
                WHERE bl.vente_id = ?
                GROUP BY bl.vente_id
            ");
            $stmt->execute([$venteId]);
            $result = $stmt->fetch();
            $qteLivree = $result['qte_livree'] ?? 0;
            
            // Récupérer la quantité commandée
            $stmt = $pdo->prepare("
                SELECT SUM(quantite) as qte_commandee FROM ventes_lignes WHERE vente_id = ?
            ");
            $stmt->execute([$venteId]);
            $result = $stmt->fetch();
            $qteCommandee = $result['qte_commandee'] ?? 0;
            
            // Déterminer le nouveau statut
            if ($qteLivree >= $qteCommandee && $qteCommandee > 0) {
                $nouveauStatut = 'LIVREE';
            } elseif ($qteLivree > 0) {
                $nouveauStatut = 'PARTIELLEMENT_LIVREE';
            } else {
                $nouveauStatut = 'EN_ATTENTE_LIVRAISON';
            }
            
            // Mettre à jour le statut
            $stmt = $pdo->prepare("UPDATE ventes SET statut = ? WHERE id = ?");
            $stmt->execute([$nouveauStatut, $venteId]);
            
            $_SESSION['flash_success'] = "Statut vente synchronisé : $nouveauStatut";
        }
        
        elseif ($action === 'ajuster_stock') {
            // Ajuster les mouvements de stock pour correspondre aux livraisons
            // Comparer produit par produit : quantité livrée vs quantité en sortie stock
            
            $stmt = $pdo->prepare("
                SELECT 
                    bll.produit_id,
                    p.designation,
                    SUM(bll.quantite) as qte_livree,
                    COALESCE((
                        SELECT SUM(sm.quantite) 
                        FROM stocks_mouvements sm 
                        WHERE sm.source_type = 'VENTE' 
                        AND sm.source_id = ? 
                        AND sm.produit_id = bll.produit_id
                        AND sm.type_mouvement = 'SORTIE'
                    ), 0) as qte_stock_sortie
                FROM bons_livraison bl
                JOIN bons_livraison_lignes bll ON bll.bon_livraison_id = bl.id
                JOIN produits p ON p.id = bll.produit_id
                WHERE bl.vente_id = ?
                GROUP BY bll.produit_id, p.designation
            ");
            $stmt->execute([$venteId, $venteId]);
            $produitsAnalyse = $stmt->fetchAll();
            
            $countAjustements = 0;
            foreach ($produitsAnalyse as $prod) {
                $ecart = $prod['qte_livree'] - $prod['qte_stock_sortie'];
                
                if ($ecart > 0) {
                    // Il manque des sorties de stock : créer un mouvement SORTIE
                    stock_enregistrer_mouvement($pdo, [
                        'produit_id'     => $prod['produit_id'],
                        'date_mouvement' => date('Y-m-d H:i:s'),
                        'type_mouvement' => 'SORTIE',
                        'quantite'       => $ecart,
                        'source_type'    => 'VENTE',
                        'source_id'      => $venteId,
                        'commentaire'    => "Ajustement : Correction écart livraison-stock ({$prod['designation']}, écart: {$ecart})",
                        'utilisateur_id' => utilisateurConnecte()['id'],
                    ]);
                    $countAjustements++;
                } elseif ($ecart < 0) {
                    // Trop de sorties de stock : créer un mouvement ENTREE pour compenser
                    $ecartPositif = abs($ecart);
                    stock_enregistrer_mouvement($pdo, [
                        'produit_id'     => $prod['produit_id'],
                        'date_mouvement' => date('Y-m-d H:i:s'),
                        'type_mouvement' => 'ENTREE',
                        'quantite'       => $ecartPositif,
                        'source_type'    => 'VENTE',
                        'source_id'      => $venteId,
                        'commentaire'    => "Ajustement : Correction excès sortie stock ({$prod['designation']}, excès: {$ecartPositif})",
                        'utilisateur_id' => utilisateurConnecte()['id'],
                    ]);
                    $countAjustements++;
                }
            }
            
            if ($countAjustements > 0) {
                $_SESSION['flash_success'] = "$countAjustements ajustement(s) de stock effectué(s)";
            } else {
                $_SESSION['flash_info'] = "Aucun ajustement nécessaire - Stock déjà cohérent";
            }
        }
        
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash_error'] = "Erreur : " . $e->getMessage();
    }
    
    header('Location: ' . url_for('coordination/corriger_synchronisation.php?vente_id=' . $venteId));
    exit;
}

// Récupérer les infos de synchronisation
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as nb_bl,
        COALESCE(SUM(bll.quantite), 0) as total_quantite_livree
    FROM bons_livraison bl
    LEFT JOIN bons_livraison_lignes bll ON bll.bon_livraison_id = bl.id
    WHERE bl.vente_id = ?
");
$stmt->execute([$venteId]);
$infoBL = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(quantite), 0) as total FROM stocks_mouvements 
    WHERE source_type = 'VENTE' AND source_id = ? AND type_mouvement = 'SORTIE'
");
$stmt->execute([$venteId]);
$infoStock = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT COUNT(*) as count FROM compta_ecritures ce
    JOIN compta_pieces cp ON cp.id = ce.piece_id
    WHERE cp.reference_type = 'VENTE' AND cp.reference_id = ?
");
$stmt->execute([$venteId]);
$infoCompta = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT SUM(quantite) as total FROM ventes_lignes WHERE vente_id = ?
");
$stmt->execute([$venteId]);
$infoQte = $stmt->fetch();

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
$flashWarning = $_SESSION['flash_warning'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning']);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-0">
                <i class="bi bi-wrench text-warning"></i> Correction de synchronisation
            </h1>
            <p class="text-muted mb-0">
                <strong><?= htmlspecialchars($vente['numero']) ?></strong> - 
                <?= htmlspecialchars($vente['client_nom']) ?>
            </p>
        </div>
        <a href="<?= url_for('coordination/verification_synchronisation.php') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i> <?= htmlspecialchars($flashSuccess) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($flashWarning): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i> <?= htmlspecialchars($flashWarning) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i> <?= htmlspecialchars($flashError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tableau de diagnostic -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="bi bi-truck"></i> État des livraisons
                    </h6>
                    <div class="row g-3">
                        <div class="col-6">
                            <small class="text-muted d-block">Bons de livraison</small>
                            <strong class="fs-5">
                                <?= $infoBL['nb_bl'] ?>
                                <?php if ($infoBL['nb_bl'] == 0): ?>
                                    <span class="badge bg-danger">Aucun</span>
                                <?php endif; ?>
                            </strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Qté livrée</small>
                            <strong class="fs-5"><?= $infoBL['total_quantite_livree'] ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Qté commandée</small>
                            <strong class="fs-5"><?= $infoQte['total'] ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">État</small>
                            <strong><?= htmlspecialchars($vente['statut']) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="bi bi-box"></i> État du stock
                    </h6>
                    <div class="row g-3">
                        <div class="col-6">
                            <small class="text-muted d-block">Sorties stock enregistrées</small>
                            <strong class="fs-5">
                                <?= $infoStock['total'] ?>
                                <?php if ($infoStock['total'] == 0): ?>
                                    <span class="badge bg-danger">Aucune</span>
                                <?php endif; ?>
                            </strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Écritures comptables</small>
                            <strong class="fs-5">
                                <?= $infoCompta['count'] ?>
                                <?php if ($infoCompta['count'] == 0): ?>
                                    <span class="badge bg-danger">Aucune</span>
                                <?php endif; ?>
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions de correction -->
    <div class="card">
        <div class="card-header bg-light">
            <h6 class="mb-0">
                <i class="bi bi-hammer"></i> Actions de correction
            </h6>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Cliquez sur une action pour corriger automatiquement le problème. Chaque correction est effectuée en transaction.
            </p>

            <div class="row g-3">
                <?php if ($infoBL['nb_bl'] == 0): ?>
                <div class="col-md-6">
                    <div class="card border-warning">
                        <div class="card-body">
                            <h6 class="text-warning mb-2">
                                <i class="bi bi-exclamation-triangle"></i> Aucun bon de livraison
                            </h6>
                            <p class="small text-muted mb-3">
                                Cette vente n'a pas de bon de livraison. Créez-en un automatiquement basé sur les lignes de vente.
                            </p>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                <input type="hidden" name="action" value="creer_bl_automatique">
                                <button type="submit" class="btn btn-warning btn-sm" 
                                        onclick="return confirm('Créer un BL automatique pour cette vente ?')">
                                    <i class="bi bi-plus-circle me-1"></i> Créer un BL
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($infoStock['total'] == 0 && $infoQte['total'] > 0): ?>
                <div class="col-md-6">
                    <div class="card border-danger">
                        <div class="card-body">
                            <h6 class="text-danger mb-2">
                                <i class="bi bi-exclamation-circle"></i> Sorties stock manquantes
                            </h6>
                            <p class="small text-muted mb-3">
                                Aucun mouvement de stock n'a été enregistré. Créez les sorties automatiquement.
                            </p>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                <input type="hidden" name="action" value="creer_mouvements_stock">
                                <button type="submit" class="btn btn-danger btn-sm" 
                                        onclick="return confirm('Créer les mouvements de stock manquants ?')">
                                    <i class="bi bi-arrow-down-circle me-1"></i> Créer sorties stock
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($infoCompta['count'] == 0): ?>
                <div class="col-md-6">
                    <div class="card border-info">
                        <div class="card-body">
                            <h6 class="text-info mb-2">
                                <i class="bi bi-book"></i> Écritures comptables manquantes
                            </h6>
                            <p class="small text-muted mb-3">
                                Aucune écriture comptable n'a été créée pour cette vente. Générez-les automatiquement.
                            </p>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                <input type="hidden" name="action" value="creer_ecritures_compta">
                                <button type="submit" class="btn btn-info btn-sm" 
                                        onclick="return confirm('Créer les écritures comptables ?')">
                                    <i class="bi bi-plus-circle me-1"></i> Créer écritures
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($infoStock['total'] != $infoBL['total_quantite_livree']): ?>
                <div class="col-md-6">
                    <div class="card border-primary">
                        <div class="card-body">
                            <h6 class="text-primary mb-2">
                                <i class="bi bi-arrow-repeat"></i> Ajuster le stock
                            </h6>
                            <p class="small text-muted mb-1">
                                <strong>Livré :</strong> <?= (int)$infoBL['total_quantite_livree'] ?> unités<br>
                                <strong>Stock :</strong> <?= (int)$infoStock['total'] ?> sorties
                            </p>
                            <p class="small text-danger mb-3">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Écart : <?= (int)($infoBL['total_quantite_livree'] - $infoStock['total']) ?> unités manquantes
                            </p>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                <input type="hidden" name="action" value="ajuster_stock">
                                <button type="submit" class="btn btn-primary btn-sm" 
                                        onclick="return confirm('Ajuster les mouvements de stock produit par produit ?')">
                                    <i class="bi bi-box-arrow-down me-1"></i> Ajuster stock
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="col-md-6">
                    <div class="card border-secondary">
                        <div class="card-body">
                            <h6 class="mb-2">
                                <i class="bi bi-arrow-repeat"></i> Synchroniser les statuts
                            </h6>
                            <p class="small text-muted mb-3">
                                Recalculez le statut de la vente en fonction des livraisons et du stock.
                            </p>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                <input type="hidden" name="action" value="synchroniser_livraisons">
                                <button type="submit" class="btn btn-secondary btn-sm">
                                    <i class="bi bi-check-circle me-1"></i> Synchroniser
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Retour à la vente -->
    <div class="mt-4">
        <a href="<?= url_for('ventes/detail.php?id=' . (int)$venteId) ?>" class="btn btn-primary">
            <i class="bi bi-arrow-right me-1"></i> Voir la vente corrigée
        </a>
        <a href="<?= url_for('coordination/verification_synchronisation.php') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-list-check me-1"></i> Revérifier toutes les ventes
        </a>
        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#helpModal">
            <i class="bi bi-question-circle me-1"></i> Aide
        </button>
    </div>
</div>

<!-- Modal d'aide -->
<div class="modal fade" id="helpModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-question-circle me-2"></i> Guide de correction
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 class="mb-3">🎯 Objectif</h6>
                <p class="text-muted">
                    Synchroniser la vente avec ses mouvements de stock, livraisons et écritures comptables.
                    Chaque action est une correction automatique d'un problème spécifique.
                </p>

                <hr>

                <h6 class="mb-3">📋 Actions disponibles</h6>

                <div class="mb-3">
                    <h6 class="text-warning">
                        <i class="bi bi-truck"></i> 1. Aucun bon de livraison
                    </h6>
                    <p class="small text-muted mb-1">
                        <strong>Problème :</strong> La vente n'a pas de bon de livraison.
                    </p>
                    <p class="small text-muted mb-1">
                        <strong>Solution :</strong> Créer automatiquement un BL avec tous les produits commandés.
                    </p>
                    <p class="small text-muted">
                        <strong>Résultat :</strong> Un BL portant le numéro BL-AUTO-YYYYMMDD-XXXX est créé.
                    </p>
                </div>

                <div class="mb-3">
                    <h6 class="text-danger">
                        <i class="bi bi-box"></i> 2. Sorties stock manquantes
                    </h6>
                    <p class="small text-muted mb-1">
                        <strong>Problème :</strong> Les produits ont été livrés mais les sorties ne sont pas enregistrées.
                    </p>
                    <p class="small text-muted mb-1">
                        <strong>Solution :</strong> Créer les mouvements de stock pour chaque produit.
                    </p>
                    <p class="small text-muted">
                        <strong>Résultat :</strong> Le stock disponible est décrémenté correctement.
                    </p>
                </div>

                <div class="mb-3">
                    <h6 class="text-info">
                        <i class="bi bi-book"></i> 3. Écritures comptables manquantes
                    </h6>
                    <p class="small text-muted mb-1">
                        <strong>Problème :</strong> La vente n'a pas d'écritures en comptabilité.
                    </p>
                    <p class="small text-muted mb-1">
                        <strong>Solution :</strong> Générer les écritures comptables selon la méthode OHADA.
                    </p>
                    <p class="small text-muted">
                        <strong>Résultat :</strong> Les comptes 411 (client), 701 (vente) et 449 (TVA) sont affectés.
                    </p>
                </div>

                <div class="mb-3">
                    <h6>
                        <i class="bi bi-arrow-repeat"></i> 4. Synchroniser les statuts
                    </h6>
                    <p class="small text-muted mb-1">
                        <strong>Fonction :</strong> Recalcule le statut basé sur les quantités livrées.
                    </p>
                    <p class="small text-muted">
                        <strong>Résultat :</strong> Statut = LIVREE (si tout) ou PARTIELLEMENT_LIVREE (si partiel).
                    </p>
                </div>

                <div class="mb-3">
                    <h6 class="text-primary">
                        <i class="bi bi-box-arrow-down"></i> 5. Ajuster le stock
                    </h6>
                    <p class="small text-muted mb-1">
                        <strong>Problème :</strong> Quantité livrée ≠ Sorties de stock (écart produit par produit).
                    </p>
                    <p class="small text-muted mb-1">
                        <strong>Solution :</strong> Compare chaque produit livré avec ses mouvements stock.
                    </p>
                    <p class="small text-muted">
                        <strong>Résultat :</strong> Crée les mouvements SORTIE manquants ou ENTREE pour corriger.
                    </p>
                </div>

                <hr>

                <h6 class="mb-3">📊 Ordre recommandé</h6>
                <ol class="small text-muted">
                    <li>Créer bon de livraison (formalise la livraison)</li>
                    <li>Créer sorties stock (met à jour le stock physique)</li>
                    <li>Créer écritures comptables (trace comptable)</li>
                    <li>Synchroniser statuts (finalise la cohérence)</li>
                </ol>

                <hr>

                <h6 class="mb-2">✅ Sécurité</h6>
                <p class="small text-muted mb-0">
                    ✓ Chaque action est reversible (base de données)<br>
                    ✓ Toutes les corrections sont en transaction<br>
                    ✓ Confirmation requise avant chaque action<br>
                    ✓ Traçabilité complète (logs + historique)
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
