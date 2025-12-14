<?php
/**
 * Version simplifiée de reconciliation pour debug
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CAISSE_LIRE');

global $pdo;

$date = $_GET['date'] ?? date('Y-m-d');

echo "<!-- Debug: Début script -->\n";

// Stats simples
$stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM journal_caisse WHERE date_operation = ?");
$stmt->execute([$date]);
$stats = ['nb_operations' => $stmt->fetchColumn(), 'total_recettes' => 5882140, 'total_depenses' => 170000, 'nb_ventes' => 2, 'nb_annulations' => 0];
$solde_calcule = $stats['total_recettes'] - $stats['total_depenses'];

echo "<!-- Debug: Stats calculées -->\n";

$cloture = null;

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">🔧 Debug Réconciliation</h1>
    
    <div class="alert alert-info">
        <strong>Debug:</strong> Si vous voyez ce message, le PHP fonctionne jusqu'ici.
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6>Recettes</h6>
                    <h3><?= number_format($stats['total_recettes'], 0, ',', ' '); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-warning">
        <strong>Checkpoint 1:</strong> Si vous ne voyez pas le formulaire ci-dessous, il y a un problème.
    </div>

    <!-- FORMULAIRE TEST -->
    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="text-primary">Déclaration du caissier (TEST)</h5>
                </div>
                <div class="card-body">
                    <p><strong>✅ Ce formulaire est visible!</strong></p>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Espèces</label>
                            <input type="number" class="form-control" placeholder="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Chèques</label>
                            <input type="number" class="form-control" placeholder="0">
                        </div>
                        <button type="submit" class="btn btn-primary">Test Submit</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h5>Info Debug</h5>
                </div>
                <div class="card-body">
                    <p>Date: <?= $date; ?></p>
                    <p>Opérations: <?= $stats['nb_operations']; ?></p>
                    <p>Solde: <?= number_format($solde_calcule, 0, ',', ' '); ?> FCFA</p>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-success mt-4">
        <strong>Checkpoint 2:</strong> Fin du contenu. Si vous voyez ceci, le problème n'est pas dans le PHP.
    </div>
</div>

<script>
console.log('✅ JavaScript chargé');
console.log('Sidebar présente:', document.querySelector('.sidebar') !== null);
console.log('Toggle button présent:', document.querySelector('[data-bs-toggle="collapse"]') !== null);
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
