<?php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CAISSE_LIRE');
global $pdo;

$date = $_GET['date'] ?? date('Y-m-d');

// Requêtes simples
$stats = $pdo->prepare("SELECT COUNT(*) as nb, COALESCE(SUM(CASE WHEN sens='RECETTE' AND est_annule=0 THEN montant ELSE 0 END),0) as rec, COALESCE(SUM(CASE WHEN sens='DEPENSE' AND est_annule=0 THEN montant ELSE 0 END),0) as dep FROM journal_caisse WHERE date_operation=?")->execute([$date]) ? $pdo->query("SELECT COUNT(*) as nb, COALESCE(SUM(CASE WHEN sens='RECETTE' AND est_annule=0 THEN montant ELSE 0 END),0) as rec, COALESCE(SUM(CASE WHEN sens='DEPENSE' AND est_annule=0 THEN montant ELSE 0 END),0) as dep FROM journal_caisse WHERE date_operation='$date'")->fetch() : ['nb'=>0,'rec'=>0,'dep'=>0];
$cloture = $pdo->query("SELECT * FROM caisses_clotures WHERE date_cloture='$date'")->fetch();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <h1>✅ Réconciliation Mini</h1>
    
    <div class="row g-3 mb-4">
        <div class="col-3">
            <div class="card">
                <div class="card-body"><h6>Recettes</h6><h3><?= number_format($stats['rec'], 0, ',', ' '); ?></h3></div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-6">
            <div class="card">
                <div class="card-header bg-light"><h5>✅ FORMULAIRE ICI</h5></div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label>Espèces</label>
                            <input type="number" class="form-control" name="especes" value="<?= $cloture['montant_especes_declare'] ?? '' ?>">
                        </div>
                        <button class="btn btn-primary">Tester</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
