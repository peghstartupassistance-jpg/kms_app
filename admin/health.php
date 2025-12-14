<?php
// admin/health.php - Diagnostic de santé du système
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('ADMIN'); // Restreint aux admins

global $pdo;

$checks = [];
$all_ok = true;

// 1️⃣ Connexion BDD
try {
    $pdo->query("SELECT 1");
    $checks['db_connection'] = ['status' => 'OK', 'message' => 'Connexion à la base de données fonctionnelle'];
} catch (Exception $e) {
    $checks['db_connection'] = ['status' => 'ERROR', 'message' => 'Connexion BDD échouée: ' . $e->getMessage()];
    $all_ok = false;
}

// 2️⃣ Transaction résiduelle (BLOCAGE CRITIQUE)
try {
    $in_transaction = $pdo->inTransaction();
    if ($in_transaction) {
        $checks['transaction_state'] = ['status' => 'ERROR', 'message' => '⚠️ TRANSACTION OUVERTE ! Rollback immédiat pour déverrouiller'];
        $pdo->rollBack();
        $all_ok = false;
    } else {
        $checks['transaction_state'] = ['status' => 'OK', 'message' => 'Aucune transaction résiduelle'];
    }
} catch (Exception $e) {
    $checks['transaction_state'] = ['status' => 'ERROR', 'message' => 'Erreur vérification transaction: ' . $e->getMessage()];
    $all_ok = false;
}

// 3️⃣ Tables essentielles
$tables_critiques = [
    'produits',
    'stocks_mouvements',
    'ventes',
    'ventes_lignes',
    'bons_livraison',
    'bons_livraison_lignes',
    'journal_caisse',
    'caisse_journal',
    'compta_pieces',
    'compta_ecritures',
    'compta_journaux',
    'compta_comptes',
    'clients',
    'ordres_preparation',
    'retours_litiges'
];

$tables_manquantes = [];
foreach ($tables_critiques as $table) {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM $table LIMIT 1");
        $stmt->execute();
    } catch (Exception $e) {
        $tables_manquantes[] = $table;
    }
}

if (empty($tables_manquantes)) {
    $checks['tables'] = ['status' => 'OK', 'message' => 'Toutes les tables essentielles existent'];
} else {
    $checks['tables'] = ['status' => 'ERROR', 'message' => 'Tables manquantes: ' . implode(', ', $tables_manquantes)];
    $all_ok = false;
}

// 4️⃣ Schéma caisse (détection incohérence)
$caisse_schema_ok = true;
$caisse_info = [];
try {
    $stmt = $pdo->query("DESCRIBE journal_caisse");
    $columns_jc = [];
    foreach ($stmt->fetchAll() as $col) {
        $columns_jc[$col['Field']] = $col['Type'];
    }
    $caisse_info['journal_caisse'] = $columns_jc;
    
    if (!isset($columns_jc['sens']) || !isset($columns_jc['montant'])) {
        $caisse_schema_ok = false;
    }
} catch (Exception $e) {
    $caisse_info['journal_caisse_error'] = $e->getMessage();
    $caisse_schema_ok = false;
}

try {
    $stmt = $pdo->query("DESCRIBE caisse_journal");
    $columns_cj = [];
    foreach ($stmt->fetchAll() as $col) {
        $columns_cj[$col['Field']] = $col['Type'];
    }
    $caisse_info['caisse_journal'] = $columns_cj;
    
    if (!isset($columns_cj['date_ecriture']) && !isset($columns_cj['date_operation'])) {
        $caisse_schema_ok = false;
    }
} catch (Exception $e) {
    $caisse_info['caisse_journal_error'] = $e->getMessage();
    $caisse_schema_ok = false;
}

if ($caisse_schema_ok) {
    $checks['caisse_schema'] = ['status' => 'OK', 'message' => 'Tables caisse/trésorerie détectées'];
} else {
    $checks['caisse_schema'] = ['status' => 'WARNING', 'message' => 'Incohérence détectée entre journal_caisse et caisse_journal - À unifier'];
    $all_ok = false;
}

// 5️⃣ Stock et mouvements
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM stocks_mouvements");
    $count = $stmt->fetch()['count'];
    $checks['stock_mouvements'] = ['status' => 'OK', 'message' => "$count mouvements de stock enregistrés"];
} catch (Exception $e) {
    $checks['stock_mouvements'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
    $all_ok = false;
}

// 6️⃣ Ventes et lignes
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ventes");
    $count_v = $stmt->fetch()['count'];
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ventes_lignes");
    $count_vl = $stmt->fetch()['count'];
    $checks['ventes'] = ['status' => 'OK', 'message' => "$count_v ventes, $count_vl lignes"];
} catch (Exception $e) {
    $checks['ventes'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
    $all_ok = false;
}

// 7️⃣ Comptabilité
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM compta_pieces");
    $count_p = $stmt->fetch()['count'];
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM compta_ecritures");
    $count_e = $stmt->fetch()['count'];
    $checks['compta'] = ['status' => 'OK', 'message' => "$count_p pièces, $count_e écritures"];
} catch (Exception $e) {
    $checks['compta'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
    $all_ok = false;
}

// 8️⃣ Exercice actif
try {
    $stmt = $pdo->query("SELECT * FROM compta_exercices WHERE est_clos = 0 ORDER BY annee DESC LIMIT 1");
    $exercice = $stmt->fetch();
    if ($exercice) {
        $checks['exercice'] = ['status' => 'OK', 'message' => 'Exercice actif: ' . $exercice['annee']];
    } else {
        $checks['exercice'] = ['status' => 'WARNING', 'message' => 'Aucun exercice ouvert'];
    }
} catch (Exception $e) {
    $checks['exercice'] = ['status' => 'WARNING', 'message' => 'Impossible de vérifier l\'exercice'];
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="bi bi-heart-pulse"></i> Diagnostic de Santé Système
        </h1>
        <span class="badge <?= $all_ok ? 'bg-success' : 'bg-danger' ?>">
            <?= $all_ok ? '✅ SYSTÈME OK' : '⚠️ PROBLÈMES DÉTECTÉS' ?>
        </span>
    </div>

    <div class="row">
        <?php foreach ($checks as $key => $check): ?>
            <?php
                $badge_class = match($check['status']) {
                    'OK' => 'bg-success-subtle text-success',
                    'ERROR' => 'bg-danger-subtle text-danger',
                    'WARNING' => 'bg-warning-subtle text-warning',
                    default => 'bg-secondary-subtle text-secondary'
                };
                $icon = match($check['status']) {
                    'OK' => 'bi-check-circle',
                    'ERROR' => 'bi-exclamation-triangle',
                    'WARNING' => 'bi-exclamation-circle',
                    default => 'bi-info-circle'
                };
            ?>
            <div class="col-md-6 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <i class="bi <?= $icon ?> fs-5 me-3 <?= $badge_class ?> p-2 rounded"></i>
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-1">
                                    <?= ucfirst(str_replace('_', ' ', $key)) ?>
                                </h6>
                                <p class="card-text small mb-0">
                                    <?= htmlspecialchars($check['message']) ?>
                                </p>
                                <?php if ($check['status'] === 'ERROR'): ?>
                                    <small class="text-danger fw-bold">🔴 Action requise</small>
                                <?php endif; ?>
                            </div>
                            <span class="badge <?= $badge_class ?> ms-2">
                                <?= $check['status'] ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Section caisse détaillée -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">📊 Détail Schéma Trésorerie</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>journal_caisse</h6>
                    <?php if (isset($caisse_info['journal_caisse'])): ?>
                        <pre style="font-size: 0.8em; max-height: 300px; overflow-y: auto;">
<?php foreach ($caisse_info['journal_caisse'] as $col => $type): ?>
<?= "$col: $type\n" ?>
<?php endforeach; ?>
                        </pre>
                    <?php else: ?>
                        <p class="text-danger">Erreur: <?= htmlspecialchars($caisse_info['journal_caisse_error'] ?? 'Inconnue') ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h6>caisse_journal</h6>
                    <?php if (isset($caisse_info['caisse_journal'])): ?>
                        <pre style="font-size: 0.8em; max-height: 300px; overflow-y: auto;">
<?php foreach ($caisse_info['caisse_journal'] as $col => $type): ?>
<?= "$col: $type\n" ?>
<?php endforeach; ?>
                        </pre>
                    <?php else: ?>
                        <p class="text-danger">Erreur: <?= htmlspecialchars($caisse_info['caisse_journal_error'] ?? 'Inconnue') ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="alert alert-warning mt-3">
                <strong>⚠️ À faire :</strong> Vérifier que les deux tables ne créent pas de doublons ou d'incohérences.
                Une seule table de référence devrait être utilisée pour la trésorerie.
            </div>
        </div>
    </div>

    <!-- Recommendations -->
    <?php if (!$all_ok): ?>
        <div class="card mt-4 border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">🔧 Recommandations</h5>
            </div>
            <div class="card-body">
                <ul>
                    <?php foreach ($checks as $key => $check): ?>
                        <?php if ($check['status'] !== 'OK'): ?>
                            <li><strong><?= ucfirst(str_replace('_', ' ', $key)) ?></strong> : <?= htmlspecialchars($check['message']) ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
