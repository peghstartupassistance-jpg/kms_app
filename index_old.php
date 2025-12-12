<?php
// index.php
require_once __DIR__ . '/security.php';
exigerConnexion();

include __DIR__ . '/partials/header.php';
include __DIR__ . '/partials/sidebar.php';

$utilisateur = utilisateurConnecte();
?>

<div class="container-fluid">
    <h1 class="h4 mb-4">Tableau de bord KMS</h1>

    <!-- KPIs rapides (placeholders pour le moment) -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card kms-card-hover">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted text-uppercase small">Visiteurs showroom (jour)</div>
                            <div class="fs-4 fw-semibold">0</div>
                        </div>
                        <i class="bi bi-shop fs-2 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- tu pourras compléter plus tard avec de vrais chiffres -->
        <div class="col-md-3">
            <div class="card kms-card-hover">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Devis du jour</div>
                    <div class="fs-4 fw-semibold">0</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card kms-card-hover">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Ventes du jour</div>
                    <div class="fs-4 fw-semibold">0</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card kms-card-hover">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Taux d’occupation hôtel</div>
                    <div class="fs-4 fw-semibold">0 %</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Raccourcis d'accès rapide aux modules -->
    <h2 class="h6 text-uppercase text-muted mb-3">Accès rapide</h2>
    <div class="row g-3">

        <!-- Produits & stock -->
        <div class="col-md-3">
            <a href="<?= url_for('produits/list.php') ?>" class="text-decoration-none">
                <div class="card kms-card-hover h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-box-seam fs-2 text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Produits & stock</div>
                            <div class="text-muted small">Gérer le catalogue et les stocks</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Clients -->
        <div class="col-md-3">
            <a href="<?= url_for('clients/list.php') ?>" class="text-decoration-none">
                <div class="card kms-card-hover h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-people fs-2 text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Clients & prospects</div>
                            <div class="text-muted small">Base clients, pipeline commercial</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Ventes -->
        <div class="col-md-3">
            <a href="<?= url_for('ventes/list.php') ?>" class="text-decoration-none">
                <div class="card kms-card-hover h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-cart-check fs-2 text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Ventes</div>
                            <div class="text-muted small">Suivi des ventes & livraisons</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Réservations hôtel -->
        <div class="col-md-3">
            <a href="<?= url_for('hotel/reservations.php') ?>" class="text-decoration-none">
                <div class="card kms-card-hover h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-building fs-2 text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Réservations hôtel</div>
                            <div class="text-muted small">Séjours, montants et statut</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- 🔹 NOUVEAU : Gestion des chambres -->
        <div class="col-md-3">
            <a href="<?= url_for('hotel/chambres_list.php') ?>" class="text-decoration-none">
                <div class="card kms-card-hover h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-door-closed fs-2 text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Chambres</div>
                            <div class="text-muted small">Créer, modifier et activer les chambres</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- tu pourras ajouter d’autres raccourcis (Showroom, Terrain, Formation, etc.) au besoin -->

    </div>
</div>

<?php
include __DIR__ . '/partials/footer.php';
