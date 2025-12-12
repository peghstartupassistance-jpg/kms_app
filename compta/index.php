<?php
// compta/index.php - Dashboard Comptabilité
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('COMPTABILITE_LIRE');

require_once __DIR__ . '/../lib/compta.php';

global $pdo;

// Filtres
$today = date('Y-m-d');
$dateDebut = $_GET['date_debut'] ?? $today;
$dateFin = $_GET['date_fin'] ?? $today;
$exerciceId = isset($_GET['exercice_id']) ? (int)$_GET['exercice_id'] : 0;

// Récupérer la liste des exercices
$stmt = $pdo->query("SELECT id, annee FROM compta_exercices ORDER BY annee DESC");
$exercices = $stmt->fetchAll();

// Déterminer l'exercice courant
$exercice = null;
if ($exerciceId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM compta_exercices WHERE id = ?");
    $stmt->execute([$exerciceId]);
    $exercice = $stmt->fetch();
} else {
    $exercice = compta_get_exercice_actif($pdo);
    if ($exercice) {
        $exerciceId = $exercice['id'];
    }
}

// Statistiques générales
$stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM compta_journaux");
$stmt->execute();
$nb_journaux = $stmt->fetch()['nb'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM compta_comptes WHERE est_actif = 1");
$stmt->execute();
$nb_comptes = $stmt->fetch()['nb'] ?? 0;

// Pièces non validées (filtrées par exercice)
$where_pieces = [];
$params_pieces = [];
if ($exercice) {
    $where_pieces[] = "exercice_id = ?";
    $params_pieces[] = $exercice['id'];
}
$where_pieces[] = "est_validee = 0";

$where_sql = implode(" AND ", $where_pieces);
$stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM compta_pieces WHERE $where_sql");
$stmt->execute($params_pieces);
$pieces_non_validees = $stmt->fetch()['nb'] ?? 0;

// Mappings actifs
$stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM compta_mapping_operations WHERE actif = 1");
$stmt->execute();
$nb_mappings = $stmt->fetch()['nb'] ?? 0;

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Comptabilité</h1>
        <a href="<?= url_for('compta/saisie_ecritures.php') ?>" class="btn btn-success">
            <i class="bi bi-journal-plus me-1"></i> Saisie mode Sage
        </a>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success py-2">
            <i class="bi bi-check-circle me-1"></i>
            <?= htmlspecialchars($flashSuccess) ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger py-2">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?= htmlspecialchars($flashError) ?>
        </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-md-3">
                    <label class="form-label small">Exercice comptable</label>
                    <select name="exercice_id" class="form-select">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($exercices as $ex): ?>
                            <option value="<?= (int)$ex['id'] ?>"
                                <?= $exerciceId === (int)$ex['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ex['annee']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control"
                           value="<?= htmlspecialchars($dateDebut) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control"
                           value="<?= htmlspecialchars($dateFin) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel me-1"></i> Filtrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Exercice actif -->
    <?php if ($exercice): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Exercice actif :</strong> <?= htmlspecialchars($exercice['annee']) ?>
            (Du <?= htmlspecialchars($exercice['date_ouverture']) ?> au <?= htmlspecialchars($exercice['date_cloture'] ?? 'en cours') ?>)
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Attention :</strong> Aucun exercice comptable actif. Veuillez en créer un dans la configuration.
        </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="text-primary"><?= $pieces_non_validees ?></h2>
                    <p class="text-muted mb-3">Pièces non validées</p>
                    <a href="<?= url_for('compta/journaux.php') ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-arrow-right me-1"></i> Consulter
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="text-success"><?= $nb_comptes ?></h2>
                    <p class="text-muted mb-3">Comptes actifs</p>
                    <a href="<?= url_for('compta/plan_comptable.php') ?>" class="btn btn-success btn-sm">
                        <i class="bi bi-arrow-right me-1"></i> Gérer
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="text-info"><?= $nb_journaux ?></h2>
                    <p class="text-muted mb-3">Journaux</p>
                    <a href="<?= url_for('compta/journaux.php') ?>" class="btn btn-info btn-sm">
                        <i class="bi bi-arrow-right me-1"></i> Consulter
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="text-warning"><?= $nb_mappings ?></h2>
                    <p class="text-muted mb-3">Règles de génération</p>
                    <a href="<?= url_for('compta/parametrage_mappings.php') ?>" class="btn btn-warning btn-sm">
                        <i class="bi bi-arrow-right me-1"></i> Paramétrer
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modules comptables -->
    <h5 class="mb-3">
        <i class="bi bi-grid-3x3 me-1"></i> Modules Comptables
    </h5>
    <div class="row">
        <!-- Plan Comptable -->
        <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-diagram-2 me-1"></i> Plan Comptable
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Gérez le plan comptable : création, modification et suppression des comptes comptables classés par nature.</p>
                    <ul class="list-unstyled text-muted small">
                        <li><i class="bi bi-check text-success me-1"></i> 8 classes de comptes (OHADA)</li>
                        <li><i class="bi bi-check text-success me-1"></i> Hiérarchie parent/enfant</li>
                        <li><i class="bi bi-check text-success me-1"></i> Activation/désactivation</li>
                    </ul>
                </div>
                <div class="card-footer bg-light">
                    <a href="<?= url_for('compta/plan_comptable.php') ?>" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-arrow-right me-1"></i> Accéder
                    </a>
                </div>
            </div>
        </div>

        <!-- Journaux -->
        <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-book me-1"></i> Journaux
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Consultez les journaux comptables : ventes, achats, trésorerie, opérations diverses et paie.</p>
                    <ul class="list-unstyled text-muted small">
                        <li><i class="bi bi-check text-success me-1"></i> Liste des pièces par journal</li>
                        <li><i class="bi bi-check text-success me-1"></i> Détail des écritures</li>
                        <li><i class="bi bi-check text-success me-1"></i> Validation des pièces</li>
                    </ul>
                </div>
                <div class="card-footer bg-light">
                    <a href="<?= url_for('compta/journaux.php') ?>" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-arrow-right me-1"></i> Accéder
                    </a>
                </div>
            </div>
        </div>

        <!-- Grand Livre -->
        <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-table me-1"></i> Grand Livre
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Consultez le grand livre : tous les mouvements débit/crédit par compte comptable avec solde courant.</p>
                    <ul class="list-unstyled text-muted small">
                        <li><i class="bi bi-check text-success me-1"></i> Listing par classe de compte</li>
                        <li><i class="bi bi-check text-success me-1"></i> Détail chronologique</li>
                        <li><i class="bi bi-check text-success me-1"></i> Calcul du solde</li>
                    </ul>
                </div>
                <div class="card-footer bg-light">
                    <a href="<?= url_for('compta/grand_livre.php') ?>" class="btn btn-info btn-sm w-100">
                        <i class="bi bi-arrow-right me-1"></i> Accéder
                    </a>
                </div>
            </div>
        </div>

        <!-- Bilan & Résultat -->
        <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-graph-up me-1"></i> Bilan & Résultat
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Générez le bilan comptable et le compte de résultat : actif, passif, charges et produits.</p>
                    <ul class="list-unstyled text-muted small">
                        <li><i class="bi bi-check text-success me-1"></i> Bilan actif/passif</li>
                        <li><i class="bi bi-check text-success me-1"></i> Compte de résultat</li>
                        <li><i class="bi bi-check text-success me-1"></i> Vérification d'équilibre</li>
                    </ul>
                </div>
                <div class="card-footer bg-light">
                    <a href="<?= url_for('compta/balance.php') ?>" class="btn btn-success btn-sm w-100">
                        <i class="bi bi-arrow-right me-1"></i> Accéder
                    </a>
                </div>
            </div>
        </div>

        <!-- Configuration / Mappings -->
        <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0">
                        <i class="bi bi-gear me-1"></i> Configuration
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Configurez les règles de génération automatique d'écritures comptables pour les opérations.</p>
                    <ul class="list-unstyled text-muted small">
                        <li><i class="bi bi-check text-success me-1"></i> Mappings VENTE/ACHAT/CAISSE</li>
                        <li><i class="bi bi-check text-success me-1"></i> Génération automatique</li>
                        <li><i class="bi bi-check text-success me-1"></i> Édition/suppression</li>
                    </ul>
                </div>
                <div class="card-footer bg-light">
                    <a href="<?= url_for('compta/parametrage_mappings.php') ?>" class="btn btn-warning btn-sm w-100">
                        <i class="bi bi-arrow-right me-1"></i> Accéder
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Section Exportations -->
    <h5 class="mb-3 mt-4">
        <i class="bi bi-download me-1"></i> Exportations & Impressions
    </h5>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm border-success">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="bi bi-file-earmark-excel text-success me-1"></i> Balance Comptable
                    </h6>
                    <p class="text-muted small mb-3">Export Excel de la balance complète avec totaux par classe</p>
                    <a href="<?= url_for('compta/export_balance.php?exercice_id=' . $exerciceId) ?>" 
                       class="btn btn-success btn-sm w-100">
                        <i class="bi bi-download me-1"></i> Télécharger Excel
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm border-success">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="bi bi-file-earmark-excel text-success me-1"></i> Bilan Comptable
                    </h6>
                    <p class="text-muted small mb-3">Export Excel du bilan actif/passif (OHADA)</p>
                    <a href="<?= url_for('compta/export_bilan.php?exercice_id=' . $exerciceId) ?>" 
                       class="btn btn-success btn-sm w-100">
                        <i class="bi bi-download me-1"></i> Télécharger Excel
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card h-100 shadow-sm border-success">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="bi bi-file-earmark-excel text-success me-1"></i> Grand Livre
                    </h6>
                    <p class="text-muted small mb-3">Export Excel du grand livre général ou par compte</p>
                    <a href="<?= url_for('compta/export_grand_livre.php?exercice_id=' . $exerciceId) ?>" 
                       class="btn btn-success btn-sm w-100">
                        <i class="bi bi-download me-1"></i> Télécharger Excel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
