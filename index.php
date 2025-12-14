<?php
// index.php - Dashboard principal KMS Gestion
require_once __DIR__ . '/security.php';
exigerConnexion();

global $pdo;
$utilisateur = utilisateurConnecte();
$today = date('Y-m-d');
$permissions = $_SESSION['permissions'] ?? [];

// ═══════════════════════════════════════════════════════════════════════════════
// REQUÊTES KPIs PRINCIPAUX
// ═══════════════════════════════════════════════════════════════════════════════

// Visiteurs showroom du jour
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM visiteurs_showroom WHERE DATE(date_visite) = CURDATE()");
$stmt->execute();
$visiteurs_jour = $stmt->fetch()['total'] ?? 0;

// Devis du jour
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM devis WHERE DATE(date_devis) = CURDATE()");
$stmt->execute();
$devis_jour = $stmt->fetch()['total'] ?? 0;

// Ventes du jour
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ventes WHERE DATE(date_vente) = CURDATE()");
$stmt->execute();
$ventes_jour = $stmt->fetch()['total'] ?? 0;

// CA du jour (compatible avec journal_caisse ET caisse_journal)
$ca_jour = 0;
$ca_ventes = 0;
$ca_hotel = 0;
$ca_formation = 0;

// DEBUG: Vérifier les données brutes
error_log('[INDEX DEBUG] === CA DU JOUR ===');

try {
    // Vérifier ce qui existe dans journal_caisse
    $debug_stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM journal_caisse WHERE DATE(date_operation) = CURDATE()');
    $debug_stmt->execute();
    $debug_count = $debug_stmt->fetch()['cnt'];
    error_log('[INDEX DEBUG] Lignes journal_caisse aujourd\'hui: ' . $debug_count);
    
    if ($debug_count > 0) {
        $debug_stmt2 = $pdo->prepare('SELECT * FROM journal_caisse WHERE DATE(date_operation) = CURDATE()');
        $debug_stmt2->execute();
        $debug_rows = $debug_stmt2->fetchAll(PDO::FETCH_ASSOC);
        foreach ($debug_rows as $row) {
            error_log('[INDEX DEBUG] Ligne: id=' . $row['id'] . ' sens=' . $row['sens'] . ' montant=' . $row['montant'] . ' annulée=' . $row['est_annule']);
        }
    }
} catch (Exception $e) {
    error_log('[INDEX DEBUG] Erreur debug: ' . $e->getMessage());
}

try {
    // Essayer avec journal_caisse d'abord (nouvelle table unifiée)
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN sens IN ('RECETTE', 'ENTREE') THEN montant ELSE 0 END) as ca_total
        FROM journal_caisse 
        WHERE DATE(date_operation) = CURDATE() AND est_annule = 0
    ");
    $stmt->execute();
    $ca_data = $stmt->fetch();
    $ca_jour = (float)($ca_data['ca_total'] ?? 0);
    error_log('[INDEX DEBUG] Résultat journal_caisse: ' . $ca_jour);
} catch (Exception $e) {
    error_log('[INDEX DEBUG] Erreur journal_caisse: ' . $e->getMessage());
    // Fallback sur caisse_journal (ancienne table)
    try {
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN sens IN ('ENTREE', 'SORTIE') THEN montant ELSE 0 END) as ca_total
            FROM caisse_journal 
            WHERE DATE(date_ecriture) = CURDATE()
        ");
        $stmt->execute();
        $ca_data = $stmt->fetch();
        $ca_jour = (float)($ca_data['ca_total'] ?? 0);
        error_log('[INDEX DEBUG] Résultat caisse_journal: ' . $ca_jour);
    } catch (Exception $e2) {
        error_log('[INDEX DEBUG] Erreur caisse_journal: ' . $e2->getMessage());
        $ca_jour = 0;
    }
}

// Taux occupation hôtel
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT c.id) as total_chambres,
        COUNT(DISTINCT CASE WHEN r.statut = 'EN_COURS' AND CURDATE() BETWEEN r.date_debut AND r.date_fin THEN r.chambre_id END) as chambres_occupees
    FROM chambres c
    LEFT JOIN reservations_hotel r ON c.id = r.chambre_id
    WHERE c.actif = 1
");
$stmt->execute();
$occ = $stmt->fetch();
$taux_occupation = $occ['total_chambres'] > 0 ? round(($occ['chambres_occupees'] / $occ['total_chambres']) * 100, 1) : 0;

// ═══════════════════════════════════════════════════════════════════════════════
// ALERTES & NOTIFICATIONS
// ═══════════════════════════════════════════════════════════════════════════════

// Produits en rupture
$stmt = $pdo->query("SELECT COUNT(*) as total FROM produits WHERE stock_actuel = 0 AND actif = 1");
$ruptures = $stmt->fetch()['total'] ?? 0;

// Produits en alerte
$stmt = $pdo->query("SELECT COUNT(*) as total FROM produits WHERE stock_actuel > 0 AND stock_actuel <= seuil_alerte AND actif = 1");
$alertes_stock = $stmt->fetch()['total'] ?? 0;

// Ordres de préparation en attente
$stmt = $pdo->query("SELECT COUNT(*) as total FROM ordres_preparation WHERE statut = 'EN_ATTENTE'");
$ordres_attente = $stmt->fetch()['total'] ?? 0;

// Bons de livraison non signés
$stmt = $pdo->query("SELECT COUNT(*) as total FROM bons_livraison WHERE signe_client = 0");
$bl_non_signes = $stmt->fetch()['total'] ?? 0;

// Devis à relancer (date relance dépassée ou proche)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM devis 
    WHERE statut IN ('ENVOYE', 'EN_COURS') 
    AND (date_relance IS NULL OR date_relance <= DATE_ADD(CURDATE(), INTERVAL 3 DAY))
");
$stmt->execute();
$devis_relancer = $stmt->fetch()['total'] ?? 0;

// Pièces comptables à valider
$stmt = $pdo->query("SELECT COUNT(*) as total FROM compta_pieces WHERE est_validee = 0");
$pieces_valider = $stmt->fetch()['total'] ?? 0;

// ═══════════════════════════════════════════════════════════════════════════════
// STATISTIQUES PÉRIODE (7 JOURS)
// ═══════════════════════════════════════════════════════════════════════════════

// CA 7 derniers jours (compatible avec journal_caisse ET caisse_journal)
$ca_7j = 0;
$ca_7j_ventes = 0;
$ca_7j_hotel = 0;
$ca_7j_formation = 0;

try {
    // Essayer avec journal_caisse d'abord (nouvelle table unifiée)
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN sens IN ('RECETTE', 'ENTREE') THEN montant ELSE 0 END) as ca_total
        FROM journal_caisse 
        WHERE date_operation >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND est_annule = 0
    ");
    $stmt->execute();
    $ca_7j_data = $stmt->fetch();
    $ca_7j = (float)($ca_7j_data['ca_total'] ?? 0);
} catch (Exception $e) {
    // Fallback sur caisse_journal (ancienne table)
    try {
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN sens IN ('ENTREE', 'SORTIE') THEN montant ELSE 0 END) as ca_total
            FROM caisse_journal 
            WHERE date_ecriture >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        $ca_7j_data = $stmt->fetch();
        $ca_7j = (float)($ca_7j_data['ca_total'] ?? 0);
    } catch (Exception $e2) {
        $ca_7j = 0;
    }
}
$ca_7j_formation = 0; // TODO: Get from inscriptions_formation join after refactor

// Nouveaux clients (7j)
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM clients WHERE date_creation >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute();
$nouveaux_clients = $stmt->fetch()['total'] ?? 0;

// Mouvements stock (7j)
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM stocks_mouvements WHERE date_mouvement >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stmt->execute();
$mouvements_7j = $stmt->fetch()['total'] ?? 0;

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';
?>

<style>
/* Animations fluides et modernes */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

@keyframes shimmer {
    0% {
        background-position: -1000px 0;
    }
    100% {
        background-position: 1000px 0;
    }
}

/* Cards avec effet glassmorphism */
.kms-card {
    animation: fadeInUp 0.6s ease-out backwards;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 12px !important;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.kms-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
}

/* Stagger animation pour les cards */
.kms-card:nth-child(1) { animation-delay: 0.1s; }
.kms-card:nth-child(2) { animation-delay: 0.2s; }
.kms-card:nth-child(3) { animation-delay: 0.3s; }
.kms-card:nth-child(4) { animation-delay: 0.4s; }

/* KPI Cards améliorées */
.kpi-card {
    position: relative;
    overflow: hidden;
    border-radius: 16px !important;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}

.kpi-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
    transition: left 0.5s;
}

.kpi-card:hover::before {
    left: 100%;
}

.kpi-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
}

/* Icones avec animation */
.kpi-icon {
    transition: transform 0.3s ease;
}

.kpi-card:hover .kpi-icon {
    transform: scale(1.1) rotate(5deg);
    animation: pulse 1s infinite;
}

/* Chiffres animés */
.kpi-value {
    font-size: 2.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--kpi-color, #0d6efd) 0%, var(--kpi-color-light, #6ea8fe) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: inline-block;
}

/* Progress bars modernisées */
.modern-progress {
    height: 10px;
    border-radius: 10px;
    background: #e9ecef;
    overflow: hidden;
    position: relative;
}

.modern-progress .progress-bar {
    border-radius: 10px;
    transition: width 1s ease-out;
    position: relative;
    overflow: hidden;
}

.modern-progress .progress-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    animation: shimmer 2s infinite;
}

/* Alertes avec effet de brillance */
.alert-modern {
    border-radius: 12px;
    border-left: 4px solid;
    animation: slideInRight 0.5s ease-out;
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 193, 7, 0.05) 100%);
}

/* Boutons avec effet ripple */
.btn-modern {
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
    border-radius: 8px;
}

.btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.btn-modern::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.5s, height 0.5s;
}

.btn-modern:active::before {
    width: 300px;
    height: 300px;
}

/* Module cards avec effet 3D */
.module-card {
    border-radius: 16px;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    border: none !important;
    background: white;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
}

.module-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--module-color, #0d6efd), var(--module-color-light, #6ea8fe));
    border-radius: 16px 16px 0 0;
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.module-card:hover::after {
    transform: scaleX(1);
}

.module-card:hover {
    transform: translateY(-10px) perspective(1000px) rotateX(2deg);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.module-card .card-header {
    border-radius: 16px 16px 0 0 !important;
    font-weight: 600;
    letter-spacing: 0.5px;
}

/* Links avec effet underline animé */
.module-link {
    position: relative;
    display: flex;
    align-items: center;
    padding: 10px 12px;
    border-radius: 8px;
    transition: all 0.3s ease;
    color: #495057;
    text-decoration: none;
}

.module-link::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: currentColor;
    transition: width 0.3s ease;
}

.module-link:hover {
    background: rgba(0, 0, 0, 0.03);
    color: inherit;
    padding-left: 20px;
}

.module-link:hover::before {
    width: 100%;
}

.module-link i {
    transition: transform 0.3s ease;
}

.module-link:hover i {
    transform: translateX(5px);
}

/* Badge avec effet glow */
.badge-glow {
    animation: fadeInUp 0.8s ease-out;
    box-shadow: 0 0 10px currentColor;
}

/* Loading skeleton pour les statistiques */
.stat-skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: 8px;
}

/* Header section modernisé */
.dashboard-header {
    animation: fadeInUp 0.5s ease-out;
}

/* Responsive améliorations */
@media (max-width: 768px) {
    .kpi-value {
        font-size: 2rem;
    }
    
    .module-card:hover {
        transform: translateY(-5px);
    }
}

/* Print styles */
@media print {
    .kpi-card, .module-card {
        animation: none;
        box-shadow: none;
        page-break-inside: avoid;
    }
}
</style>

<div class="container-fluid">
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- EN-TÊTE -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    
    <?php
    // Vérifier si l'utilisateur a activé le 2FA
    $stmt2fa = $pdo->prepare("SELECT actif FROM utilisateurs_2fa WHERE utilisateur_id = ? AND actif = 1");
    $stmt2fa->execute([$utilisateur['id']]);
    $has2FA = $stmt2fa->fetchColumn() === 1;
    ?>
    
    <div class="dashboard-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-2 fw-bold">
                <i class="bi bi-speedometer2 text-primary me-2"></i>
                Tableau de bord KMS
                <?php if ($has2FA): ?>
                    <span class="badge bg-success badge-glow ms-2" title="Authentification 2FA activée">
                        <i class="bi bi-shield-check"></i> Sécurisé
                    </span>
                <?php endif; ?>
            </h1>
            <p class="text-muted mb-0 d-flex align-items-center flex-wrap">
                <span class="me-3">
                    <i class="bi bi-calendar3 me-1"></i> 
                    <?= strftime('%A %d %B %Y', strtotime($today)) ?>
                </span>
                <span class="me-3">
                    <i class="bi bi-person-circle me-1"></i> 
                    <?= htmlspecialchars($utilisateur['nom_complet']) ?>
                </span>
                <span class="badge bg-light text-dark">
                    <i class="bi bi-clock me-1"></i>
                    <span id="currentTime"></span>
                </span>
            </p>
        </div>
        <div>
            <span class="badge bg-success fs-6 px-3 py-2" style="animation: fadeInUp 0.8s ease-out;">
                <i class="bi bi-circle-fill" style="animation: pulse 2s infinite;"></i> 
                Système opérationnel
            </span>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- KPIs PRINCIPAUX -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card kpi-card kms-card" style="--kpi-color: #0d6efd; --kpi-color-light: #6ea8fe;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="text-muted text-uppercase small fw-semibold mb-2" style="letter-spacing: 0.5px;">
                                <i class="bi bi-shop me-1"></i>
                                Visiteurs showroom
                            </div>
                            <div class="kpi-value mb-1"><?= $visiteurs_jour ?></div>
                            <small class="text-muted d-flex align-items-center">
                                <i class="bi bi-clock-history me-1"></i> 
                                Aujourd'hui
                            </small>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-3 p-3 kpi-icon">
                            <i class="bi bi-shop fs-1 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card kpi-card kms-card" style="--kpi-color: #198754; --kpi-color-light: #75b798;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="text-muted text-uppercase small fw-semibold mb-2" style="letter-spacing: 0.5px;">
                                <i class="bi bi-cash-coin me-1"></i>
                                CA Total du jour
                            </div>
                            <div class="kpi-value mb-1"><?= number_format($ca_jour, 0, ',', ' ') ?> F</div>
                            <small class="text-success fw-semibold d-flex align-items-center">
                                <i class="bi bi-graph-up me-1"></i> 
                                <?= $ventes_jour ?> ventes
                                <?php if ($ca_hotel > 0): ?> • <?= number_format($ca_hotel, 0, ',', ' ') ?> F hôtel<?php endif; ?>
                                <?php if ($ca_formation > 0): ?> • <?= number_format($ca_formation, 0, ',', ' ') ?> F formation<?php endif; ?>
                            </small>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded-3 p-3 kpi-icon">
                            <i class="bi bi-cash-coin fs-1 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card kpi-card kms-card" style="--kpi-color: #ffc107; --kpi-color-light: #ffda6a;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="text-muted text-uppercase small fw-semibold mb-2" style="letter-spacing: 0.5px;">
                                <i class="bi bi-file-earmark-text me-1"></i>
                                Devis du jour
                            </div>
                            <div class="kpi-value mb-1"><?= $devis_jour ?></div>
                            <small class="text-muted d-flex align-items-center">
                                <i class="bi bi-hourglass-split me-1"></i> 
                                En cours
                            </small>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded-3 p-3 kpi-icon">
                            <i class="bi bi-file-earmark-text fs-1 text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card kpi-card kms-card" style="--kpi-color: #0dcaf0; --kpi-color-light: #76e3f7;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="text-muted text-uppercase small fw-semibold mb-2" style="letter-spacing: 0.5px;">
                                <i class="bi bi-building me-1"></i>
                                Occupation hôtel
                            </div>
                            <div class="kpi-value mb-1"><?= $taux_occupation ?>%</div>
                            <small class="text-muted d-flex align-items-center">
                                <i class="bi bi-door-closed me-1"></i> 
                                <?= $occ['chambres_occupees'] ?> / <?= $occ['total_chambres'] ?> chambres
                            </small>
                        </div>
                        <div class="bg-info bg-opacity-10 rounded-3 p-3 kpi-icon">
                            <i class="bi bi-building fs-1 text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- ALERTES & ACTIONS URGENTES -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <?php if ($ruptures > 0 || $alertes_stock > 0 || $ordres_attente > 0 || $devis_relancer > 0 || $bl_non_signes > 0 || $pieces_valider > 0): ?>
    <div class="alert alert-modern alert-warning border-warning d-flex align-items-start mb-4 shadow-sm" role="alert">
        <div class="me-3">
            <i class="bi bi-exclamation-triangle-fill fs-2 text-warning" style="animation: pulse 2s infinite;"></i>
        </div>
        <div class="flex-grow-1">
            <h5 class="alert-heading mb-3 fw-bold">
                <i class="bi bi-bell-fill me-2"></i>
                Actions urgentes requises
            </h5>
            <div class="row g-2">
                <?php if ($ruptures > 0): ?>
                <div class="col-md-4">
                    <a href="<?= url_for('stock/alertes.php') ?>" class="btn btn-sm btn-danger btn-modern w-100 shadow-sm">
                        <i class="bi bi-exclamation-circle me-1"></i> <?= $ruptures ?> rupture(s) stock
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($alertes_stock > 0): ?>
                <div class="col-md-4">
                    <a href="<?= url_for('stock/alertes.php') ?>" class="btn btn-sm btn-warning btn-modern w-100 shadow-sm">
                        <i class="bi bi-exclamation-triangle me-1"></i> <?= $alertes_stock ?> alerte(s) stock
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($ordres_attente > 0): ?>
                <div class="col-md-4">
                    <a href="<?= url_for('coordination/ordres_preparation.php') ?>" class="btn btn-sm btn-primary btn-modern w-100 shadow-sm">
                        <i class="bi bi-list-check me-1"></i> <?= $ordres_attente ?> ordre(s) à préparer
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($bl_non_signes > 0): ?>
                <div class="col-md-4">
                    <a href="<?= url_for('livraisons/list.php') ?>" class="btn btn-sm btn-secondary btn-modern w-100 shadow-sm">
                        <i class="bi bi-truck me-1"></i> <?= $bl_non_signes ?> BL non signé(s)
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($devis_relancer > 0): ?>
                <div class="col-md-4">
                    <a href="<?= url_for('reporting/relances_devis.php') ?>" class="btn btn-sm btn-info btn-modern w-100 shadow-sm">
                        <i class="bi bi-clock-history me-1"></i> <?= $devis_relancer ?> devis à relancer
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($pieces_valider > 0): ?>
                <div class="col-md-4">
                    <a href="<?= url_for('compta/index.php') ?>" class="btn btn-sm btn-success btn-modern w-100 shadow-sm">
                        <i class="bi bi-check-circle me-1"></i> <?= $pieces_valider ?> pièce(s) comptable(s)
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- WIDGETS PRINCIPAUX -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <div class="row g-3 mb-4">
        
        <!-- WIDGET : Performance commerciale -->
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-graph-up-arrow"></i> Performance commerciale (7 jours)
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="text-muted small">Chiffre d'affaires</div>
                                <div class="fs-4 fw-bold text-success"><?= number_format($ca_7j, 0, ',', ' ') ?> F</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="text-muted small">Nouveaux clients</div>
                                <div class="fs-4 fw-bold text-primary"><?= $nouveaux_clients ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="text-muted small">Mouvements stock</div>
                                <div class="fs-4 fw-bold text-info"><?= $mouvements_7j ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3 bg-light rounded">
                                <div class="text-muted small">Taux de conversion</div>
                                <div class="fs-4 fw-bold text-warning">
                                    <?php
                                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM devis WHERE date_devis >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
                                    $total_devis_7j = $stmt->fetch()['total'] ?? 1;
                                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ventes WHERE date_vente >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
                                    $total_ventes_7j = $stmt->fetch()['total'] ?? 0;
                                    $taux_conv = $total_devis_7j > 0 ? round(($total_ventes_7j / $total_devis_7j) * 100, 1) : 0;
                                    echo $taux_conv . '%';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- WIDGET : État du stock -->
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-box-seam"></i> État du stock
                </div>
                <div class="card-body">
                    <?php
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produits WHERE actif = 1");
                    $total_produits = $stmt->fetch()['total'] ?? 0;
                    $stock_ok = $total_produits - $ruptures - $alertes_stock;
                    $pct_rupture = $total_produits > 0 ? round(($ruptures / $total_produits) * 100, 1) : 0;
                    $pct_alerte = $total_produits > 0 ? round(($alertes_stock / $total_produits) * 100, 1) : 0;
                    $pct_ok = 100 - $pct_rupture - $pct_alerte;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-danger fw-semibold"><i class="bi bi-exclamation-circle-fill me-1"></i> Ruptures</span>
                            <strong class="badge bg-danger"><?= $ruptures ?> (<?= $pct_rupture ?>%)</strong>
                        </div>
                        <div class="modern-progress mb-3" style="height: 20px; border-radius: 10px; overflow: hidden;">
                            <div class="progress-bar bg-danger" style="width: <?= $pct_rupture ?>%"></div>
                        </div>

                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Alertes</span>
                            <strong class="badge bg-warning"><?= $alertes_stock ?> (<?= $pct_alerte ?>%)</strong>
                        </div>
                        <div class="modern-progress mb-3" style="height: 20px; border-radius: 10px; overflow: hidden;">
                            <div class="progress-bar bg-warning" style="width: <?= $pct_alerte ?>%"></div>
                        </div>

                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i> Stock OK</span>
                            <strong class="badge bg-success"><?= $stock_ok ?> (<?= $pct_ok ?>%)</strong>
                        </div>
                        <div class="modern-progress mb-3" style="height: 20px; border-radius: 10px; overflow: hidden;">
                            <div class="progress-bar bg-success" style="width: <?= $pct_ok ?>%"></div>
                        </div>
                    </div>
                    <div class="text-center">
                        <a href="<?= url_for('stock/alertes.php') ?>" class="btn btn-sm btn-outline-info">
                            <i class="bi bi-eye"></i> Voir détails stock
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <!-- ACCÈS RAPIDE PAR MODULE -->
    <!-- ═══════════════════════════════════════════════════════════════════════ -->
    <h2 class="h5 text-uppercase text-muted mb-3 border-bottom pb-2">
        <i class="bi bi-grid-3x3-gap"></i> Accès rapide aux modules
    </h2>

    <div class="row g-3 mb-4">

        <!-- COMMERCIAL -->
        <div class="col-lg-4 col-md-6">
            <div class="card module-card kms-card h-100" style="--module-color: #0d6efd; --module-color-light: #6ea8fe;">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="bi bi-briefcase"></i> COMMERCIAL
                </div>
                <div class="card-body">
                    <a href="<?= url_for('clients/list.php') ?>" class="module-link d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-people fs-5 me-2 text-primary"></i>
                        <span>Clients & prospects</span>
                    </a>
                    <a href="<?= url_for('devis/list.php') ?>" class="module-link d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-file-earmark-text fs-5 me-2 text-primary"></i>
                        <span>Devis</span>
                    </a>
                    <a href="<?= url_for('ventes/list.php') ?>" class="module-link d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-cart-check fs-5 me-2 text-primary"></i>
                        <span>Ventes</span>
                    </a>
                    <a href="<?= url_for('livraisons/list.php') ?>" class="module-link d-flex align-items-center text-decoration-none">
                        <i class="bi bi-truck fs-5 me-2 text-primary"></i>
                        <span>Livraisons</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- ACQUISITION -->
        <div class="col-lg-4 col-md-6">
            <div class="card module-card kms-card h-100" style="--module-color: #198754; --module-color-light: #75b798;">
                <div class="card-header bg-success text-white fw-bold">
                    <i class="bi bi-funnel"></i> CANAUX ACQUISITION
                </div>
                <div class="card-body">
                    <a href="<?= url_for('showroom/visiteurs_list.php') ?>" class="module-link d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-shop-window fs-5 me-2 text-success"></i>
                        <span>Showroom</span>
                    </a>
                    <a href="<?= url_for('terrain/prospections_list.php') ?>" class="module-link d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-geo-alt fs-5 me-2 text-success"></i>
                        <span>Terrain</span>
                    </a>
                    <a href="<?= url_for('digital/leads_list.php') ?>" class="module-link d-flex align-items-center text-decoration-none">
                        <i class="bi bi-megaphone fs-5 me-2 text-success"></i>
                        <span>Digital</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- PRODUITS & STOCK -->
        <div class="col-lg-4 col-md-6">
            <div class="card module-card kms-card h-100" style="--module-color: #0dcaf0; --module-color-light: #76e3f7;">
                <div class="card-header bg-info text-white fw-bold">
                    <i class="bi bi-box-seam"></i> PRODUITS & STOCK
                </div>
                <div class="card-body">
                    <a href="<?= url_for('produits/list.php') ?>" class="module-link d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-box-seam fs-5 me-2 text-info"></i>
                        <span>Catalogue produits</span>
                    </a>
                    <a href="<?= url_for('achats/list.php') ?>" class="module-link d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-basket fs-5 me-2 text-info"></i>
                        <span>Achats & approvisionnements</span>
                    </a>
                    <a href="<?= url_for('magasin/dashboard.php') ?>" class="module-link d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-house-door fs-5 me-2 text-info"></i>
                        <span>Dashboard Magasinier</span>
                    </a>
                    <a href="<?= url_for('coordination/ordres_preparation.php') ?>" class="module-link d-flex align-items-center text-decoration-none">
                        <i class="bi bi-list-check fs-5 me-2 text-info"></i>
                        <span>Ordres de préparation</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- FINANCE -->
        <div class="col-lg-4 col-md-6">
            <div class="card module-card kms-card h-100" style="--module-color: #ffc107; --module-color-light: #ffda6a;">
                <div class="card-header bg-warning text-dark fw-bold">
                    <i class="bi bi-currency-exchange"></i> FINANCE
                </div>
                <div class="card-body">
                    <a href="<?= url_for('caisse/journal.php') ?>" class="module-link d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-cash-coin fs-5 me-2 text-warning"></i>
                        <span>Caisse</span>
                    </a>
                    <a href="<?= url_for('compta/index.php') ?>" class="module-link d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-calculator fs-5 me-2 text-warning"></i>
                        <span>Comptabilité</span>
                    </a>
                    <a href="<?= url_for('compta/balance.php') ?>" class="module-link d-flex align-items-center text-decoration-none">
                        <i class="bi bi-graph-up fs-5 me-2 text-warning"></i>
                        <span>Balance & Bilan</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- SERVICES ANNEXES -->
        <div class="col-lg-4 col-md-6">
            <div class="card module-card kms-card h-100" style="--module-color: #6c757d; --module-color-light: #adb5bd;">
                <div class="card-header bg-secondary text-white fw-bold">
                    <i class="bi bi-star"></i> SERVICES ANNEXES
                </div>
                <div class="card-body">
                    <a href="<?= url_for('hotel/reservations.php') ?>" class="module-link d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-building fs-5 me-2 text-secondary"></i>
                        <span>Réservations hôtel</span>
                    </a>
                    <a href="<?= url_for('hotel/chambres_list.php') ?>" class="module-link d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-door-closed fs-5 me-2 text-secondary"></i>
                        <span>Chambres</span>
                    </a>
                    <a href="<?= url_for('formation/inscriptions.php') ?>" class="module-link d-flex align-items-center text-decoration-none">
                        <i class="bi bi-mortarboard fs-5 me-2 text-secondary"></i>
                        <span>Formation</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- MARKETING & ANALYSE -->
        <div class="col-lg-4 col-md-6">
            <div class="card module-card kms-card h-100" style="--module-color: #dc3545; --module-color-light: #ea868f;">
                <div class="card-header bg-danger text-white fw-bold">
                    <i class="bi bi-graph-up-arrow"></i> MARKETING & ANALYSE
                </div>
                <div class="card-body">
                    <a href="<?= url_for('promotions/list.php') ?>" class="module-link d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-megaphone fs-5 me-2 text-danger"></i>
                        <span>Promotions</span>
                    </a>
                    <a href="<?= url_for('satisfaction/list.php') ?>" class="module-link d-flex align-items-center mb-2 text-decoration-none">
                        <i class="bi bi-emoji-smile fs-5 me-2 text-danger"></i>
                        <span>Satisfaction clients</span>
                    </a>
                    <a href="<?= url_for('reporting/dashboard_marketing.php') ?>" class="module-link d-flex align-items-center text-decoration-none">
                        <i class="bi bi-pie-chart fs-5 me-2 text-danger"></i>
                        <span>Dashboard Marketing</span>
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
// ═══════════════════════════════════════════════════════════════════════
// Real-time Clock Update
// ═══════════════════════════════════════════════════════════════════════
function updateTime() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const timeString = `${hours}:${minutes}:${seconds}`;
    
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        timeElement.textContent = timeString;
    }
}

// Update time immediately and then every second
updateTime();
setInterval(updateTime, 1000);

// ═══════════════════════════════════════════════════════════════════════
// Card Animation Observer
// ═══════════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function() {
    // Add staggered animation to cards
    const cards = document.querySelectorAll('.kms-card, .kpi-card, .module-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.05}s`;
    });
    
    // Add hover sound effect (optional, subtle feedback)
    const moduleLinks = document.querySelectorAll('.module-link');
    moduleLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
        });
        link.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
});
</script>

<?php
include __DIR__ . '/partials/footer.php';
?>
