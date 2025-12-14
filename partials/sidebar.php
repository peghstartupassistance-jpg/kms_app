<?php
// partials/sidebar.php
require_once __DIR__ . '/../security.php';
exigerConnexion();

$permissions = $_SESSION['permissions'] ?? [];
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

function peut(string $code): bool {
    global $permissions;
    return in_array($code, $permissions, true);
}

function is_active($page, $dir = null): bool {
    global $current_page, $current_dir;
    if ($dir && $current_dir === $dir) return true;
    if ($current_page === $page) return true;
    return false;
}
?>
<aside class="kms-sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <i class="bi bi-speedometer me-2"></i>
            <span>Dashboard</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        
        <!-- ACCUEIL -->
        <a href="<?= url_for('index.php') ?>" class="sidebar-item <?= is_active('index.php') ? 'active' : '' ?>">
            <i class="bi bi-house-door"></i>
            <span>Accueil</span>
        </a>

        <!-- COMMERCIAL -->
        <?php if (peut('VENTES_LIRE') || peut('CLIENTS_LIRE') || peut('DEVIS_LIRE')): ?>
            <div class="sidebar-section" data-section-key="commercial">
                <div class="sidebar-section-title button" role="button" tabindex="0">
                    <span>Commercial</span>
                    <i class="bi bi-chevron-down chevron"></i>
                </div>
                <div class="section-content">
                <?php if (peut('VENTES_LIRE')): ?>
                    <a href="<?= url_for('commercial/dashboard.php') ?>" class="sidebar-item <?= is_active('dashboard.php', 'commercial') ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard Commercial</span>
                    </a>
                <?php endif; ?>
                <?php if (peut('CLIENTS_LIRE')): ?>
                    <a href="<?= url_for('clients/list.php') ?>" class="sidebar-item <?= is_active('list.php', 'clients') ? 'active' : '' ?>">
                        <i class="bi bi-people"></i>
                        <span>Clients</span>
                    </a>
                <?php endif; ?>
                <?php if (peut('DEVIS_LIRE')): ?>
                    <a href="<?= url_for('devis/list.php') ?>" class="sidebar-item <?= is_active('list.php', 'devis') ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Devis</span>
                    </a>
                <?php endif; ?>
                <?php if (peut('VENTES_LIRE')): ?>
                    <a href="<?= url_for('ventes/list.php') ?>" class="sidebar-item <?= is_active('list.php', 'ventes') ? 'active' : '' ?>">
                        <i class="bi bi-cart-check"></i>
                        <span>Ventes</span>
                    </a>
                    <a href="<?= url_for('livraisons/list.php') ?>" class="sidebar-item sidebar-item-nested <?= is_active('list.php', 'livraisons') ? 'active' : '' ?>">
                        <i class="bi bi-truck"></i>
                        <span>Bons de livraison</span>
                    </a>
                <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- CANAUX ACQUISITION -->
        <?php if (peut('CLIENTS_LIRE')): ?>
            <div class="sidebar-section" data-section-key="acquisition">
                <div class="sidebar-section-title button" role="button" tabindex="0">
                    <span>Canaux acquisition</span>
                    <i class="bi bi-chevron-down chevron"></i>
                </div>
                <div class="section-content">
                <a href="<?= url_for('showroom/visiteurs_list.php') ?>" class="sidebar-item <?= is_active('visiteurs_list.php', 'showroom') ? 'active' : '' ?>">
                    <i class="bi bi-shop-window"></i>
                    <span>Showroom</span>
                </a>
                <a href="<?= url_for('terrain/prospections_list.php') ?>" class="sidebar-item <?= is_active('prospections_list.php', 'terrain') ? 'active' : '' ?>">
                    <i class="bi bi-geo-alt"></i>
                    <span>Terrain</span>
                </a>
                <a href="<?= url_for('digital/leads_list.php') ?>" class="sidebar-item <?= is_active('leads_list.php', 'digital') ? 'active' : '' ?>">
                    <i class="bi bi-megaphone"></i>
                    <span>Digital</span>
                </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- PRODUITS & STOCK -->
        <?php if (peut('PRODUITS_LIRE') || peut('ACHATS_GERER') || peut('STOCK_LIRE')): ?>
            <div class="sidebar-section" data-section-key="produits-stock">
                <div class="sidebar-section-title button" role="button" tabindex="0">
                    <span>Produits & Stock</span>
                    <i class="bi bi-chevron-down chevron"></i>
                </div>
                <div class="section-content">
                <?php if (peut('PRODUITS_LIRE')): ?>
                    <a href="<?= url_for('catalogue/index.php') ?>" class="sidebar-item <?= is_active('index.php', 'catalogue') ? 'active' : '' ?>">
                        <i class="bi bi-diagram-3"></i>
                        <span>Catalogue produits</span>
                    </a>
                <?php endif; ?>
                <?php if (peut('ACHATS_GERER')): ?>
                    <a href="<?= url_for('achats/list.php') ?>" class="sidebar-item <?= is_active('list.php', 'achats') ? 'active' : '' ?>">
                        <i class="bi bi-basket"></i>
                        <span>Achats & appro.</span>
                    </a>
                <?php endif; ?>
                <?php if (peut('STOCK_LIRE')): ?>
                    <a href="<?= url_for('magasin/dashboard.php') ?>" class="sidebar-item <?= is_active('dashboard.php', 'magasin') ? 'active' : '' ?>">
                        <i class="bi bi-box-seam"></i>
                        <span>Magasinier</span>
                    </a>
                    <a href="<?= url_for('stock/alertes.php') ?>" class="sidebar-item sidebar-item-nested <?= is_active('alertes.php', 'stock') ? 'active' : '' ?>">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span>Alertes stock</span>
                    </a>
                    <a href="<?= url_for('stock/ajustement.php') ?>" class="sidebar-item sidebar-item-nested <?= is_active('ajustement.php', 'stock') ? 'active' : '' ?>">
                        <i class="bi bi-pencil-square"></i>
                        <span>Ajustement stock</span>
                    </a>
                <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- COORDINATION VENTES-LIVRAISONS -->
        <?php if (peut('VENTES_LIRE')): ?>
            <div class="sidebar-section" data-section-key="coordination">
                <div class="sidebar-section-title button" role="button" tabindex="0">
                    <span>Coordination</span>
                    <i class="bi bi-chevron-down chevron"></i>
                </div>
                <div class="section-content">
                <a href="<?= url_for('coordination/dashboard.php') ?>" class="sidebar-item <?= is_active('dashboard.php', 'coordination') ? 'active' : '' ?>">
                    <i class="bi bi-diagram-3-fill"></i>
                    <span>Dashboard coordination</span>
                </a>
                <a href="<?= url_for('coordination/ordres_preparation.php') ?>" class="sidebar-item sidebar-item-nested <?= is_active('ordres_preparation.php', 'coordination') ? 'active' : '' ?>">
                    <i class="bi bi-list-check"></i>
                    <span>Ordres de préparation</span>
                </a>
                <a href="<?= url_for('coordination/litiges.php') ?>" class="sidebar-item sidebar-item-nested <?= is_active('litiges.php', 'coordination') ? 'active' : '' ?>">
                    <i class="bi bi-arrow-left-right"></i>
                    <span>Retours & litiges</span>
                </a>
                <?php if (peut('PRODUITS_LIRE')): ?>
                    <a href="<?= url_for('coordination/ruptures.php') ?>" class="sidebar-item sidebar-item-nested <?= is_active('ruptures.php', 'coordination') ? 'active' : '' ?>">
                        <i class="bi bi-exclamation-circle"></i>
                        <span>Ruptures signalées</span>
                    </a>
                <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- FINANCE -->
        <?php if (peut('CAISSE_LIRE') || peut('COMPTABILITE_LIRE')): ?>
            <div class="sidebar-section" data-section-key="finance">
                <div class="sidebar-section-title button" role="button" tabindex="0">
                    <span>Finance</span>
                    <i class="bi bi-chevron-down chevron"></i>
                </div>
                <div class="section-content">
                <?php if (peut('CAISSE_LIRE')): ?>
                    <a href="<?= url_for('caisse/journal.php') ?>" class="sidebar-item <?= is_active('journal.php', 'caisse') ? 'active' : '' ?>">
                        <i class="bi bi-cash-coin"></i>
                        <span>Caisse</span>
                    </a>
                <?php endif; ?>
                <?php if (peut('COMPTABILITE_LIRE')): ?>
                    <a href="<?= url_for('compta/index.php') ?>" class="sidebar-item <?= is_active('index.php', 'compta') ? 'active' : '' ?>">
                        <i class="bi bi-calculator"></i>
                        <span>Comptabilité</span>
                    </a>
                    <a href="<?= url_for('compta/plan_comptable.php') ?>" class="sidebar-item sidebar-item-nested <?= is_active('plan_comptable.php', 'compta') ? 'active' : '' ?>">
                        <i class="bi bi-diagram-2"></i>
                        <span>Plan comptable</span>
                    </a>
                    <a href="<?= url_for('compta/balance.php') ?>" class="sidebar-item sidebar-item-nested <?= is_active('balance.php', 'compta') ? 'active' : '' ?>">
                        <i class="bi bi-graph-up"></i>
                        <span>Balance & Bilan</span>
                    </a>
                    <a href="<?= url_for('compta/valider_corrections.php') ?>" class="sidebar-item sidebar-item-nested <?= is_active('valider_corrections.php', 'compta') ? 'active' : '' ?>">
                        <i class="bi bi-pencil-square text-warning"></i>
                        <span>Corrections en attente</span>
                    </a>
                <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- SERVICES ANNEXES -->
        <?php if (peut('HOTEL_GERER') || peut('FORMATION_GERER')): ?>
            <div class="sidebar-section" data-section-key="services">
                <div class="sidebar-section-title button" role="button" tabindex="0">
                    <span>Services annexes</span>
                    <i class="bi bi-chevron-down chevron"></i>
                </div>
                <div class="section-content">
                <?php if (peut('HOTEL_GERER')): ?>
                    <a href="<?= url_for('hotel/reservations.php') ?>" class="sidebar-item <?= is_active('reservations.php', 'hotel') ? 'active' : '' ?>">
                        <i class="bi bi-building"></i>
                        <span>Hôtel</span>
                    </a>
                <?php endif; ?>
                <?php if (peut('FORMATION_GERER')): ?>
                    <a href="<?= url_for('formation/inscriptions.php') ?>" class="sidebar-item <?= is_active('inscriptions.php', 'formation') ? 'active' : '' ?>">
                        <i class="bi bi-mortarboard"></i>
                        <span>Formation</span>
                    </a>
                <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- MARKETING -->
        <?php if (peut('PROMOTIONS_GERER') || peut('SATISFACTION_GERER') || peut('REPORTING_LIRE')): ?>
            <div class="sidebar-section" data-section-key="marketing">
                <div class="sidebar-section-title button" role="button" tabindex="0">
                    <span>Marketing & Analyse</span>
                    <i class="bi bi-chevron-down chevron"></i>
                </div>
                <div class="section-content">
                <?php if (peut('REPORTING_LIRE')): ?>
                    <a href="<?= url_for('reporting/tunnel_conversion.php') ?>" class="sidebar-item <?= is_active('tunnel_conversion.php', 'reporting') ? 'active' : '' ?>">
                        <i class="bi bi-funnel"></i>
                        <span>Tunnel de conversion</span>
                    </a>
                <?php endif; ?>
                <?php if (peut('PROMOTIONS_GERER')): ?>
                    <a href="<?= url_for('promotions/list.php') ?>" class="sidebar-item <?= is_active('list.php', 'promotions') ? 'active' : '' ?>">
                        <i class="bi bi-megaphone"></i>
                        <span>Promotions</span>
                    </a>
                <?php endif; ?>
                <?php if (peut('REPORTING_LIRE')): ?>
                    <a href="<?= url_for('reporting/dashboard_marketing.php') ?>" class="sidebar-item <?= is_active('dashboard_marketing.php', 'reporting') ? 'active' : '' ?>">
                        <i class="bi bi-pie-chart"></i>
                        <span>Dashboard Marketing</span>
                    </a>
                <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ADMINISTRATION -->
        <?php if (peut('UTILISATEURS_GERER')): ?>
            <div class="sidebar-section" data-section-key="admin">
                <div class="sidebar-section-title button" role="button" tabindex="0">
                    <span>Administration</span>
                    <i class="bi bi-chevron-down chevron"></i>
                </div>
                <div class="section-content">
                <a href="<?= url_for('utilisateurs/list.php') ?>" class="sidebar-item <?= is_active('list.php', 'utilisateurs') ? 'active' : '' ?>">
                    <i class="bi bi-people-gear"></i>
                    <span>Utilisateurs</span>
                </a>
                </div>
            </div>
        <?php endif; ?>

    </nav>

    <!-- FOOTER SIDEBAR -->
    <div class="sidebar-footer">
        <div class="sidebar-user-section">
            <a href="<?= url_for('utilisateurs/2fa.php') ?>" class="sidebar-item" title="Sécurité & 2FA">
                <i class="bi bi-shield-lock"></i>
                <span>Sécurité 2FA</span>
            </a>
        </div>
        <a href="<?= url_for('logout.php') ?>" class="sidebar-logout">
            <i class="bi bi-box-arrow-right"></i>
            <span>Déconnexion</span>
        </a>
    </div>

</aside>

<main class="flex-grow-1 kms-main">
