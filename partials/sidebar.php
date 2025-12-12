<?php
// partials/sidebar.php
require_once __DIR__ . '/../security.php';
exigerConnexion();

$permissions = $_SESSION['permissions'] ?? [];

function peut(string $code): bool {
    global $permissions;
    return in_array($code, $permissions, true);
}
?>
<aside class="kms-sidebar">
    <div class="list-group list-group-flush">
        
        <!-- ACCUEIL -->
        <a href="<?= url_for('index.php') ?>" class="list-group-item list-group-item-action border-0">
            <i class="bi bi-house-door me-2"></i> Accueil
        </a>

        <!-- COMMERCIAL -->
        <?php if (peut('VENTES_LIRE') || peut('CLIENTS_LIRE') || peut('DEVIS_LIRE')): ?>
            <div class="sidebar-section-title">Commercial</div>
            <?php if (peut('VENTES_LIRE')): ?>
                <a href="<?= url_for('commercial/dashboard.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard Commercial
                </a>
            <?php endif; ?>
            <?php if (peut('CLIENTS_LIRE')): ?>
                <a href="<?= url_for('clients/list.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-people me-2"></i> Clients
                </a>
            <?php endif; ?>
            <?php if (peut('DEVIS_LIRE')): ?>
                <a href="<?= url_for('devis/list.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-file-earmark-text me-2"></i> Devis
                </a>
            <?php endif; ?>
            <?php if (peut('VENTES_LIRE')): ?>
                <a href="<?= url_for('ventes/list.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-cart-check me-2"></i> Ventes
                </a>
                <a href="<?= url_for('livraisons/list.php') ?>" class="list-group-item list-group-item-action border-0 ps-4">
                    <i class="bi bi-truck me-2"></i> Bons de livraison
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <!-- CANAUX ACQUISITION -->
        <?php if (peut('CLIENTS_LIRE')): ?>
            <div class="sidebar-section-title">Canaux acquisition</div>
            <a href="<?= url_for('showroom/visiteurs_list.php') ?>" class="list-group-item list-group-item-action border-0">
                <i class="bi bi-shop-window me-2"></i> Showroom
            </a>
            <a href="<?= url_for('terrain/prospections_list.php') ?>" class="list-group-item list-group-item-action border-0">
                <i class="bi bi-geo-alt me-2"></i> Terrain
            </a>
            <a href="<?= url_for('digital/leads_list.php') ?>" class="list-group-item list-group-item-action border-0">
                <i class="bi bi-megaphone me-2"></i> Digital
            </a>
        <?php endif; ?>

        <!-- PRODUITS & STOCK -->
        <?php if (peut('PRODUITS_LIRE') || peut('ACHATS_GERER') || peut('STOCK_LIRE')): ?>
            <div class="sidebar-section-title">Produits & Stock</div>
            <?php if (peut('PRODUITS_LIRE')): ?>
                <a href="<?= url_for('produits/list.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-box-seam me-2"></i> Catalogue produits
                </a>
            <?php endif; ?>
            <?php if (peut('ACHATS_GERER')): ?>
                <a href="<?= url_for('achats/list.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-basket me-2"></i> Achats & appro.
                </a>
            <?php endif; ?>
            <?php if (peut('STOCK_LIRE')): ?>
                <a href="<?= url_for('magasin/dashboard.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-house-door me-2"></i> Dashboard Magasinier
                </a>
                <a href="<?= url_for('stock/alertes.php') ?>" class="list-group-item list-group-item-action border-0 ps-4">
                    <i class="bi bi-exclamation-triangle me-2"></i> Alertes stock
                </a>
                <a href="<?= url_for('stock/ajustement.php') ?>" class="list-group-item list-group-item-action border-0 ps-4">
                    <i class="bi bi-pencil-square me-2"></i> Ajustement stock
                </a>
            <?php endif; ?>
            <?php if (peut('VENTES_LIRE')): ?>
                <a href="<?= url_for('coordination/ordres_preparation.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-list-check me-2"></i> Ordres de préparation
                </a>
                <a href="<?= url_for('coordination/litiges.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-arrow-left-right me-2"></i> Retours & litiges
                </a>
            <?php endif; ?>
            <?php if (peut('PRODUITS_LIRE')): ?>
                <a href="<?= url_for('coordination/ruptures.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-exclamation-circle me-2"></i> Ruptures signalées
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <!-- FINANCE -->
        <?php if (peut('CAISSE_LIRE') || peut('COMPTABILITE_LIRE')): ?>
            <div class="sidebar-section-title">Finance</div>
            <?php if (peut('CAISSE_LIRE')): ?>
                <a href="<?= url_for('caisse/journal.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-cash-coin me-2"></i> Caisse
                </a>
            <?php endif; ?>
            <?php if (peut('COMPTABILITE_LIRE')): ?>
                <a href="<?= url_for('compta/index.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-calculator me-2"></i> Comptabilité
                </a>
                <a href="<?= url_for('compta/plan_comptable.php') ?>" class="list-group-item list-group-item-action border-0 ps-4">
                    <i class="bi bi-diagram-2 me-2"></i> Plan comptable
                </a>
                <a href="<?= url_for('compta/balance.php') ?>" class="list-group-item list-group-item-action border-0 ps-4">
                    <i class="bi bi-graph-up me-2"></i> Balance & Bilan
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <!-- SERVICES ANNEXES -->
        <?php if (peut('HOTEL_GERER') || peut('FORMATION_GERER')): ?>
            <div class="sidebar-section-title">Services annexes</div>
            <?php if (peut('HOTEL_GERER')): ?>
                <a href="<?= url_for('hotel/reservations.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-building me-2"></i> Hôtel
                </a>
            <?php endif; ?>
            <?php if (peut('FORMATION_GERER')): ?>
                <a href="<?= url_for('formation/inscriptions.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-mortarboard me-2"></i> Formation
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <!-- MARKETING -->
        <?php if (peut('PROMOTIONS_GERER') || peut('SATISFACTION_GERER') || peut('REPORTING_LIRE')): ?>
            <div class="sidebar-section-title">Marketing & Analyse</div>
            <?php if (peut('PROMOTIONS_GERER')): ?>
                <a href="<?= url_for('promotions/list.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-megaphone me-2"></i> Promotions
                </a>
            <?php endif; ?>
            <?php if (peut('REPORTING_LIRE')): ?>
                <a href="<?= url_for('reporting/dashboard_marketing.php') ?>" class="list-group-item list-group-item-action border-0">
                    <i class="bi bi-pie-chart me-2"></i> Dashboard Marketing
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ADMINISTRATION -->
        <?php if (peut('UTILISATEURS_GERER')): ?>
            <div class="sidebar-section-title">Administration</div>
            <a href="<?= url_for('utilisateurs/list.php') ?>" class="list-group-item list-group-item-action border-0">
                <i class="bi bi-people-gear me-2"></i> Utilisateurs
            </a>
        <?php endif; ?>

        <!-- DÉCONNEXION -->
        <div class="mt-3 pt-3 border-top"></div>
        <a href="<?= url_for('logout.php') ?>" class="list-group-item list-group-item-action border-0 text-danger">
            <i class="bi bi-box-arrow-right me-2"></i> Déconnexion
        </a>

    </div>
</aside>

<main class="flex-grow-1 p-3 kms-main">
