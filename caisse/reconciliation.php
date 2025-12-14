<?php
/**
 * caisse/reconciliation.php - Réconciliation et clôture de caisse quotidienne
 */

// Activer l'affichage des erreurs pour debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CAISSE_LIRE');

global $pdo;

$peutEcrire = in_array('CAISSE_ECRIRE', $_SESSION['permissions'] ?? [], true);
$utilisateur = utilisateurConnecte();

// Date de réconciliation (par défaut aujourd'hui)
$date = $_GET['date'] ?? date('Y-m-d');

// ============================================
// TRAITEMENT POST - Enregistrer clôture
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $peutEcrire) {
    verifierCsrf($_POST['csrf_token'] ?? '');
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'sauvegarder' || $action === 'valider') {
        $montant_especes = (float)str_replace(',', '.', $_POST['montant_especes_declare'] ?? '0');
        $montant_cheques = (float)str_replace(',', '.', $_POST['montant_cheques_declare'] ?? '0');
        $montant_virements = (float)str_replace(',', '.', $_POST['montant_virements_declare'] ?? '0');
        $montant_mobile = (float)str_replace(',', '.', $_POST['montant_mobile_declare'] ?? '0');
        $justification = trim($_POST['justification_ecart'] ?? '');
        $observations = trim($_POST['observations'] ?? '');
        
        $total_declare = $montant_especes + $montant_cheques + $montant_virements + $montant_mobile;
        
        // Calculer les stats du jour
        $stmtStats = $pdo->prepare("
            SELECT 
                COUNT(*) as nb_operations,
                COUNT(CASE WHEN vente_id IS NOT NULL THEN 1 END) as nb_ventes,
                COUNT(CASE WHEN est_annule = 1 THEN 1 END) as nb_annulations,
                COALESCE(SUM(CASE WHEN sens = 'RECETTE' AND est_annule = 0 THEN montant ELSE 0 END), 0) as total_recettes,
                COALESCE(SUM(CASE WHEN sens = 'DEPENSE' AND est_annule = 0 THEN montant ELSE 0 END), 0) as total_depenses
            FROM journal_caisse
            WHERE date_operation = ?
        ");
        $stmtStats->execute([$date]);
        $stats = $stmtStats->fetch();
        
        $solde_calcule = $stats['total_recettes'] - $stats['total_depenses'];
        $ecart = $total_declare - $solde_calcule;
        
        $statut = ($action === 'valider') ? 'VALIDE' : 'BROUILLON';
        
        // Vérifier si clôture existe déjà
        $stmtCheck = $pdo->prepare("SELECT id, statut FROM caisses_clotures WHERE date_cloture = ?");
        $stmtCheck->execute([$date]);
        $existing = $stmtCheck->fetch();
        
        if ($existing) {
            if ($existing['statut'] === 'VALIDE' && $action === 'valider') {
                $_SESSION['flash_error'] = "Cette clôture est déjà validée. Impossible de la modifier.";
                header('Location: ' . url_for('caisse/reconciliation.php?date=' . $date));
                exit;
            }
            
            // Mise à jour
            $stmt = $pdo->prepare("
                UPDATE caisses_clotures SET
                    total_recettes = ?, total_depenses = ?, solde_calcule = ?,
                    montant_especes_declare = ?, montant_cheques_declare = ?,
                    montant_virements_declare = ?, montant_mobile_declare = ?,
                    total_declare = ?, ecart = ?, justification_ecart = ?,
                    nb_operations = ?, nb_ventes = ?, nb_annulations = ?,
                    statut = ?, observations = ?,
                    validateur_id = CASE WHEN ? = 'VALIDE' THEN ? ELSE validateur_id END,
                    date_validation = CASE WHEN ? = 'VALIDE' THEN NOW() ELSE date_validation END
                WHERE id = ?
            ");
            $stmt->execute([
                $stats['total_recettes'], $stats['total_depenses'], $solde_calcule,
                $montant_especes, $montant_cheques, $montant_virements, $montant_mobile,
                $total_declare, $ecart, $justification,
                $stats['nb_operations'], $stats['nb_ventes'], $stats['nb_annulations'],
                $statut, $observations,
                $statut, $utilisateur['id'],
                $statut,
                $existing['id']
            ]);
        } else {
            // Insertion
            $stmt = $pdo->prepare("
                INSERT INTO caisses_clotures (
                    date_cloture, total_recettes, total_depenses, solde_calcule,
                    montant_especes_declare, montant_cheques_declare,
                    montant_virements_declare, montant_mobile_declare,
                    total_declare, ecart, justification_ecart,
                    nb_operations, nb_ventes, nb_annulations,
                    statut, caissier_id, validateur_id, date_validation, observations
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $date, $stats['total_recettes'], $stats['total_depenses'], $solde_calcule,
                $montant_especes, $montant_cheques, $montant_virements, $montant_mobile,
                $total_declare, $ecart, $justification,
                $stats['nb_operations'], $stats['nb_ventes'], $stats['nb_annulations'],
                $statut, $utilisateur['id'],
                ($statut === 'VALIDE' ? $utilisateur['id'] : null),
                ($statut === 'VALIDE' ? date('Y-m-d H:i:s') : null),
                $observations
            ]);
        }
        
        $_SESSION['flash_success'] = ($action === 'valider') 
            ? "Clôture validée avec succès!" 
            : "Brouillon enregistré.";
        
        header('Location: ' . url_for('caisse/reconciliation.php?date=' . $date));
        exit;
    }
}

// ============================================
// CHARGEMENT DONNÉES
// ============================================

// Stats du jour
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) as nb_operations,
        COUNT(CASE WHEN vente_id IS NOT NULL THEN 1 END) as nb_ventes,
        COUNT(CASE WHEN est_annule = 1 THEN 1 END) as nb_annulations,
        COALESCE(SUM(CASE WHEN sens = 'RECETTE' AND est_annule = 0 THEN montant ELSE 0 END), 0) as total_recettes,
        COALESCE(SUM(CASE WHEN sens = 'DEPENSE' AND est_annule = 0 THEN montant ELSE 0 END), 0) as total_depenses
    FROM journal_caisse
    WHERE date_operation = ?
");
$stmtStats->execute([$date]);
$stats = $stmtStats->fetch();

if (!$stats) {
    $stats = [
        'nb_operations' => 0,
        'nb_ventes' => 0,
        'nb_annulations' => 0,
        'total_recettes' => 0,
        'total_depenses' => 0
    ];
}

$solde_calcule = $stats['total_recettes'] - $stats['total_depenses'];

// Stats par mode de paiement
$stmtModes = $pdo->prepare("
    SELECT mp.libelle, mp.code,
           COALESCE(SUM(CASE WHEN jc.sens = 'RECETTE' AND jc.est_annule = 0 THEN jc.montant ELSE 0 END), 0) as total
    FROM modes_paiement mp
    LEFT JOIN journal_caisse jc ON jc.mode_paiement_id = mp.id AND jc.date_operation = ?
    GROUP BY mp.id, mp.libelle, mp.code
    ORDER BY mp.id
");
$stmtModes->execute([$date]);
$modes = $stmtModes->fetchAll();

if (!$modes) {
    $modes = [];
}

// Clôture existante ?
$stmtCloture = $pdo->prepare("SELECT * FROM caisses_clotures WHERE date_cloture = ?");
$stmtCloture->execute([$date]);
$cloture = $stmtCloture->fetch();

// Dernières opérations du jour
$stmtOps = $pdo->prepare("
    SELECT jc.*, mp.libelle as mode_libelle, c.nom as client_nom
    FROM journal_caisse jc
    LEFT JOIN modes_paiement mp ON mp.id = jc.mode_paiement_id
    LEFT JOIN clients c ON c.id = jc.client_id
    WHERE jc.date_operation = ?
    ORDER BY jc.id DESC
    LIMIT 20
");
$stmtOps->execute([$date]);
$operations = $stmtOps->fetchAll();

if (!$operations) {
    $operations = [];
}

// Historique des clôtures
$stmtHistorique = $pdo->query("
    SELECT cc.*, u.nom_complet as caissier_nom
    FROM caisses_clotures cc
    LEFT JOIN utilisateurs u ON u.id = cc.caissier_id
    ORDER BY cc.date_cloture DESC
    LIMIT 10
");
$historique = $stmtHistorique->fetchAll();

if (!$historique) {
    $historique = [];
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-cash-coin text-primary"></i> Réconciliation Caisse</h1>
            <p class="text-muted small mb-0">Clôture et vérification quotidienne</p>
        </div>
        <div class="btn-group">
            <a href="<?= url_for('caisse/journal.php') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-journal-text"></i> Journal</a>
            <a href="<?= url_for('caisse/reconciliation.php?date=' . date('Y-m-d', strtotime($date . ' -1 day'))) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-left"></i> Jour précédent</a>
            <a href="<?= url_for('caisse/reconciliation.php?date=' . date('Y-m-d')) ?>" class="btn btn-primary btn-sm"><i class="bi bi-calendar-check"></i> Aujourd'hui</a>
        </div>
    </div>

    <!-- Sélecteur de date -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-auto">
                    <label class="form-label mb-0 fw-bold">Date de réconciliation:</label>
                </div>
                <div class="col-auto">
                    <input type="date" name="date" class="form-control form-control-lg" value="<?= htmlspecialchars($date); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-search"></i> Charger</button>
                </div>
                <?php if ($cloture && $cloture['statut'] === 'VALIDE'): ?>
                <div class="col-auto">
                    <span class="badge bg-success fs-5 px-3 py-2"><i class="bi bi-check-circle"></i> Clôture validée</span>
                </div>
                <?php elseif ($cloture && $cloture['statut'] === 'BROUILLON'): ?>
                <div class="col-auto">
                    <span class="badge bg-warning text-dark fs-5 px-3 py-2"><i class="bi bi-pencil"></i> Brouillon en cours</span>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- KPI Stats Calculées -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3">
                                <i class="bi bi-arrow-down-circle fs-2 text-success"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Recettes</h6>
                            <h3 class="mb-0 text-success"><?= number_format($stats['total_recettes'], 0, ',', ' '); ?></h3>
                            <small class="text-muted">FCFA</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                                <i class="bi bi-arrow-up-circle fs-2 text-danger"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Dépenses</h6>
                            <h3 class="mb-0 text-danger"><?= number_format($stats['total_depenses'], 0, ',', ' '); ?></h3>
                            <small class="text-muted">FCFA</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                                <i class="bi bi-wallet2 fs-2 text-primary"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Solde attendu</h6>
                            <h3 class="mb-0 text-primary"><?= number_format($solde_calcule, 0, ',', ' '); ?></h3>
                            <small class="text-muted">FCFA</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-info bg-opacity-10 p-3">
                                <i class="bi bi-receipt fs-2 text-info"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0 small">Opérations</h6>
                            <h3 class="mb-0"><?= $stats['nb_operations']; ?></h3>
                            <small class="text-muted"><?= $stats['nb_ventes']; ?> ventes | <?= $stats['nb_annulations']; ?> annul.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4" style="display: block;">
        <!-- Formulaire de réconciliation -->
        <div class="col-xl-6" style="width: 100%; max-width: 600px; margin-bottom: 30px;">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-bottom">
                    <h5 class="mb-0 text-primary"><i class="bi bi-calculator"></i> Déclaration du caissier</h5>
                </div>
                <div class="card-body p-4" style="display: block; min-height: 300px;">
                    <form method="POST" style="display: block;">
                        <input type="hidden" name="csrf_token" value="<?= genererCsrf(); ?>">
                        
                        <p class="text-muted small">Saisissez les montants réellement comptés par mode de paiement:</p>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-cash"></i> Espèces</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="montant_especes_declare" class="form-control form-control-lg" 
                                       value="<?= $cloture['montant_especes_declare'] ?? ''; ?>"
                                       placeholder="0" <?= ($cloture && $cloture['statut'] === 'VALIDE') ? 'readonly' : ''; ?>>
                                <span class="input-group-text">FCFA</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-credit-card-2-front"></i> Chèques</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="montant_cheques_declare" class="form-control" 
                                       value="<?= $cloture['montant_cheques_declare'] ?? ''; ?>"
                                       placeholder="0" <?= ($cloture && $cloture['statut'] === 'VALIDE') ? 'readonly' : ''; ?>>
                                <span class="input-group-text">FCFA</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-bank"></i> Virements bancaires</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="montant_virements_declare" class="form-control" 
                                       value="<?= $cloture['montant_virements_declare'] ?? ''; ?>"
                                       placeholder="0" <?= ($cloture && $cloture['statut'] === 'VALIDE') ? 'readonly' : ''; ?>>
                                <span class="input-group-text">FCFA</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="bi bi-phone"></i> Mobile Money</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="montant_mobile_declare" class="form-control" 
                                       value="<?= $cloture['montant_mobile_declare'] ?? ''; ?>"
                                       placeholder="0" <?= ($cloture && $cloture['statut'] === 'VALIDE') ? 'readonly' : ''; ?>>
                                <span class="input-group-text">FCFA</span>
                            </div>
                        </div>
                        
                        <?php if ($cloture && abs($cloture['ecart']) > 0): ?>
                        <div class="alert <?= $cloture['ecart'] > 0 ? 'alert-success' : 'alert-danger'; ?>">
                            <strong>Écart détecté:</strong> 
                            <?= $cloture['ecart'] > 0 ? '+' : ''; ?><?= number_format($cloture['ecart'], 0, ',', ' '); ?> FCFA
                            <?php if ($cloture['ecart'] > 0): ?>
                            (excédent)
                            <?php else: ?>
                            (déficit)
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Justification de l'écart</label>
                            <textarea name="justification_ecart" class="form-control" rows="2" 
                                      <?= ($cloture && $cloture['statut'] === 'VALIDE') ? 'readonly' : ''; ?>
                                      placeholder="Expliquez l'écart..."><?= htmlspecialchars($cloture['justification_ecart'] ?? ''); ?></textarea>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Observations</label>
                            <textarea name="observations" class="form-control" rows="2"
                                      <?= ($cloture && $cloture['statut'] === 'VALIDE') ? 'readonly' : ''; ?>
                                      placeholder="Notes ou remarques..."><?= htmlspecialchars($cloture['observations'] ?? ''); ?></textarea>
                        </div>
                        
                        <?php if ($peutEcrire && (!$cloture || $cloture['statut'] !== 'VALIDE')): ?>
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" name="action" value="sauvegarder" class="btn btn-lg btn-secondary">
                                <i class="bi bi-save"></i> Sauvegarder brouillon
                            </button>
                            <button type="submit" name="action" value="valider" class="btn btn-lg btn-success" 
                                    onclick="return confirm('Êtes-vous sûr de vouloir valider cette clôture? Cette action est définitive.');">
                                <i class="bi bi-check-circle"></i> Valider la clôture définitivement
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info mb-0 mt-3">
                            <i class="bi bi-info-circle"></i> Cette clôture est validée et ne peut plus être modifiée.
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Détail par mode de paiement -->
        <div class="col-xl-6" style="width: 100%; max-width: 600px; margin-bottom: 30px;">
            <div class="card border-0 shadow-sm mb-4" style="display: block;">
                <div class="card-header bg-light border-bottom">
                    <h5 class="mb-0 text-primary"><i class="bi bi-pie-chart"></i> Répartition par mode de paiement</h5>
                </div>
                <div class="card-body p-0" style="display: block;">
                    <table class="table table-sm mb-0" style="width: 100%;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Mode</th>
                                <th class="text-end pe-3">Montant (calculé)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($modes as $mode): ?>
                            <tr>
                                <td class="ps-3"><?= htmlspecialchars($mode['libelle']); ?></td>
                                <td class="text-end pe-3">
                                    <strong><?= number_format($mode['total'], 0, ',', ' '); ?></strong> FCFA
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-primary">
                                <td class="ps-3"><strong>TOTAL</strong></td>
                                <td class="text-end pe-3">
                                    <strong><?= number_format($stats['total_recettes'], 0, ',', ' '); ?></strong> FCFA
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Historique clôtures -->
            <div class="card border-0 shadow-sm" style="display: block;">
                <div class="card-header bg-light border-bottom">
                    <h5 class="mb-0 text-primary"><i class="bi bi-clock-history"></i> Historique des clôtures</h5>
                </div>
                <div class="card-body p-0" style="display: block;">
                    <table class="table table-sm table-hover mb-0" style="width: 100%;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Date</th>
                                <th>Caissier</th>
                                <th class="text-end">Solde</th>
                                <th class="text-end">Écart</th>
                                <th class="text-end pe-3">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historique as $h): ?>
                            <tr>
                                <td class="ps-3">
                                    <a href="<?= url_for('caisse/reconciliation.php?date=' . $h['date_cloture']); ?>">
                                        <?= date('d/m/Y', strtotime($h['date_cloture'])); ?>
                                    </a>
                                </td>
                                <td><small><?= htmlspecialchars($h['caissier_nom'] ?? '-'); ?></small></td>
                                <td class="text-end"><?= number_format($h['solde_calcule'], 0, ',', ' '); ?></td>
                                <td class="text-end">
                                    <?php if (abs($h['ecart']) > 0): ?>
                                    <span class="badge <?= $h['ecart'] > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                        <?= $h['ecart'] > 0 ? '+' : ''; ?><?= number_format($h['ecart'], 0, ',', ' '); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <?php 
                                    $badgeClass = match($h['statut']) {
                                        'VALIDE' => 'bg-success',
                                        'BROUILLON' => 'bg-warning',
                                        'ANNULE' => 'bg-secondary',
                                        default => 'bg-light text-dark'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass; ?>"><?= $h['statut']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($historique)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    Aucune clôture enregistrée
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Dernières opérations du jour -->
    <div class="card border-0 shadow-sm mt-4" style="display: block; width: 100%;">
        <div class="card-header bg-light border-bottom">
            <h5 class="mb-0 text-primary"><i class="bi bi-list"></i> Dernières opérations du <?= date('d/m/Y', strtotime($date)); ?></h5>
        </div>
        <div class="card-body p-0" style="display: block; overflow-x: auto;">
            <table class="table table-hover mb-0" style="width: 100%;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">N° Pièce</th>
                        <th>Nature</th>
                        <th>Client</th>
                        <th>Mode</th>
                        <th class="text-end">Montant</th>
                        <th class="text-end pe-4">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($operations as $op): ?>
                    <tr class="<?= $op['est_annule'] ? 'text-muted text-decoration-line-through' : ''; ?>">
                        <td class="ps-4"><strong><?= htmlspecialchars($op['numero_piece']); ?></strong></td>
                        <td><small><?= htmlspecialchars(substr($op['nature_operation'], 0, 40)); ?>...</small></td>
                        <td><small><?= htmlspecialchars($op['client_nom'] ?? '-'); ?></small></td>
                        <td><small><?= htmlspecialchars($op['mode_libelle'] ?? '-'); ?></small></td>
                        <td class="text-end">
                            <span class="<?= $op['sens'] === 'RECETTE' ? 'text-success' : 'text-danger'; ?>">
                                <?= $op['sens'] === 'RECETTE' ? '+' : '-'; ?>
                                <?= number_format($op['montant'], 0, ',', ' '); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <?php if ($op['est_annule']): ?>
                            <span class="badge bg-secondary">Annulée</span>
                            <?php else: ?>
                            <span class="badge <?= $op['sens'] === 'RECETTE' ? 'bg-success' : 'bg-danger'; ?>">
                                <?= $op['sens']; ?>
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($operations)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-inbox"></i> Aucune opération ce jour
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
// Calcul automatique de l'écart en temps réel
document.addEventListener('DOMContentLoaded', function() {
    const soldeCalcule = <?= $solde_calcule; ?>;
    const inputs = [
        document.querySelector('input[name="montant_especes_declare"]'),
        document.querySelector('input[name="montant_cheques_declare"]'),
        document.querySelector('input[name="montant_virements_declare"]'),
        document.querySelector('input[name="montant_mobile_declare"]')
    ];
    
    // Zone d'affichage de l'écart dynamique
    const form = document.querySelector('form[method="POST"]');
    if (form && inputs.every(i => i)) {
        // Créer la div d'alerte pour l'écart
        const alertDiv = document.createElement('div');
        alertDiv.id = 'ecart-alert';
        alertDiv.className = 'd-none alert mb-3';
        alertDiv.innerHTML = '<strong>Écart prévu:</strong> <span id="ecart-montant"></span> <span id="ecart-type"></span>';
        
        // Insérer avant les boutons
        const btnContainer = form.querySelector('.d-grid');
        if (btnContainer) {
            btnContainer.parentNode.insertBefore(alertDiv, btnContainer);
        }
        
        // Fonction de calcul
        function calculateEcart() {
            const especes = parseFloat(inputs[0].value) || 0;
            const cheques = parseFloat(inputs[1].value) || 0;
            const virements = parseFloat(inputs[2].value) || 0;
            const mobile = parseFloat(inputs[3].value) || 0;
            
            const totalDeclare = especes + cheques + virements + mobile;
            const ecart = totalDeclare - soldeCalcule;
            
            const alertDiv = document.getElementById('ecart-alert');
            const ecartMontant = document.getElementById('ecart-montant');
            const ecartType = document.getElementById('ecart-type');
            
            if (totalDeclare > 0) {
                alertDiv.classList.remove('d-none');
                ecartMontant.textContent = new Intl.NumberFormat('fr-FR').format(Math.abs(ecart)) + ' FCFA';
                
                if (Math.abs(ecart) < 1) {
                    alertDiv.className = 'alert alert-success mb-3';
                    ecartType.innerHTML = '<i class="bi bi-check-circle"></i> (aucun écart)';
                } else if (ecart > 0) {
                    alertDiv.className = 'alert alert-success mb-3';
                    ecartType.innerHTML = '<i class="bi bi-arrow-up"></i> (excédent)';
                } else {
                    alertDiv.className = 'alert alert-danger mb-3';
                    ecartType.innerHTML = '<i class="bi bi-arrow-down"></i> (déficit)';
                }
            } else {
                alertDiv.classList.add('d-none');
            }
        }
        
        // Écouter les changements
        inputs.forEach(input => {
            input.addEventListener('input', calculateEcart);
            input.addEventListener('change', calculateEcart);
        });
        
        // Calcul initial si des valeurs existent
        calculateEcart();
    }
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
