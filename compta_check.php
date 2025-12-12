<?php
session_start();
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/lib/compta.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Récupérer les statistiques
try {
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM compta_comptes");
    $nb_comptes = $stmt->fetch()['nb'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM compta_pieces");
    $nb_pieces = $stmt->fetch()['nb'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM compta_ecritures");
    $nb_ecritures = $stmt->fetch()['nb'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as nb FROM compta_journaux");
    $nb_journaux = $stmt->fetch()['nb'] ?? 0;
    
    $exercice = compta_get_exercice_actif($pdo);
    
    $db_ok = true;
} catch (Exception $e) {
    $db_ok = false;
    $error_msg = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comptabilité - Status</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.0/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0"><i class="bi bi-calculator"></i> Comptabilité - Vérification Installation</h2>
                    </div>
                    <div class="card-body">
                        <h5>📊 État du système</h5>
                        
                        <?php if (!$db_ok): ?>
                            <div class="alert alert-danger">
                                <strong>❌ Erreur de connexion :</strong> <?= htmlspecialchars($error_msg) ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <strong>✅ Système fonctionnel</strong>
                            </div>
                            
                            <?php if ($exercice): ?>
                                <div class="alert alert-info">
                                    <strong>Exercice actif :</strong> <?= htmlspecialchars($exercice['annee']) ?>
                                    (<?= htmlspecialchars($exercice['date_ouverture']) ?> - <?= htmlspecialchars($exercice['date_cloture'] ?? 'ouvert') ?>)
                                </div>
                            <?php endif; ?>
                            
                            <div class="row mt-4">
                                <div class="col-md-3 mb-3">
                                    <div class="card text-center">
                                        <div class="card-body">
                                            <h3 class="text-primary"><?= $nb_comptes ?></h3>
                                            <p class="text-muted">Comptes actifs</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card text-center">
                                        <div class="card-body">
                                            <h3 class="text-info"><?= $nb_journaux ?></h3>
                                            <p class="text-muted">Journaux</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card text-center">
                                        <div class="card-body">
                                            <h3 class="text-warning"><?= $nb_pieces ?></h3>
                                            <p class="text-muted">Pièces</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card text-center">
                                        <div class="card-body">
                                            <h3 class="text-secondary"><?= $nb_ecritures ?></h3>
                                            <p class="text-muted">Écritures</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <h5 class="mt-5">🚀 Accès rapide</h5>
                            <div class="list-group">
                                <a href="compta/" class="list-group-item list-group-item-action">
                                    <i class="bi bi-house-door"></i> Dashboard comptabilité
                                </a>
                                <a href="compta/plan_comptable.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-diagram-2"></i> Plan comptable
                                </a>
                                <a href="compta/journaux.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-book"></i> Journaux
                                </a>
                                <a href="compta/grand_livre.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-table"></i> Grand livre
                                </a>
                                <a href="compta/balance.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-graph-up"></i> Bilan
                                </a>
                                <a href="compta/parametrage_mappings.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-gear"></i> Configuration mappings
                                </a>
                            </div>
                            
                            <h5 class="mt-5">📚 Documentation</h5>
                            <div class="list-group">
                                <a href="compta/README.md" class="list-group-item list-group-item-action" target="_blank">
                                    <i class="bi bi-file-earmark-text"></i> Documentation technique
                                </a>
                                <a href="COMPTA_DEPLOYMENT_SUMMARY.md" class="list-group-item list-group-item-action" target="_blank">
                                    <i class="bi bi-file-earmark-check"></i> Résumé déploiement
                                </a>
                            </div>
                            
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-muted">
                        <small>✓ Module Comptabilité v1.0 - Installation complète</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
