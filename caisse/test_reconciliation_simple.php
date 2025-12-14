<?php
/**
 * Test simplifié reconciliation
 */
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CAISSE_LIRE');
global $pdo;

$date = $_GET['date'] ?? date('Y-m-d');

// Stats du jour
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) as nb_operations,
        COALESCE(SUM(CASE WHEN sens = 'RECETTE' AND est_annule = 0 THEN montant ELSE 0 END), 0) as total_recettes,
        COALESCE(SUM(CASE WHEN sens = 'DEPENSE' AND est_annule = 0 THEN montant ELSE 0 END), 0) as total_depenses
    FROM journal_caisse
    WHERE date_operation = ?
");
$stmtStats->execute([$date]);
$stats = $stmtStats->fetch();
$solde_calcule = $stats['total_recettes'] - $stats['total_depenses'];

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Test Réconciliation - <?= $date; ?></h1>
    
    <!-- Test KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted small">Recettes</h6>
                    <h3 class="text-success"><?= number_format($stats['total_recettes'], 0, ',', ' '); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted small">Dépenses</h6>
                    <h3 class="text-danger"><?= number_format($stats['total_depenses'], 0, ',', ' '); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted small">Solde</h6>
                    <h3 class="text-primary"><?= number_format($solde_calcule, 0, ',', ' '); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted small">Opérations</h6>
                    <h3><?= $stats['nb_operations']; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Formulaire -->
    <div class="row">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">Déclaration du caissier</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Espèces</label>
                            <input type="number" class="form-control" placeholder="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Chèques</label>
                            <input type="number" class="form-control" placeholder="0">
                        </div>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Informations</h6>
                </div>
                <div class="card-body">
                    <p>Date: <?= $date; ?></p>
                    <p>Opérations: <?= $stats['nb_operations']; ?></p>
                    <p>Total recettes: <?= number_format($stats['total_recettes'], 0, ',', ' '); ?> FCFA</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
