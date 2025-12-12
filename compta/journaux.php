<?php
// compta/journaux.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('COMPTABILITE_LIRE');

require_once __DIR__ . '/../lib/compta.php';

$action = $_GET['action'] ?? 'list';
$journal_id = isset($_GET['journal_id']) ? (int)$_GET['journal_id'] : 0;
$piece_id = isset($_GET['piece_id']) ? (int)$_GET['piece_id'] : 0;

// Récupérer les journaux
$stmt = $pdo->prepare("SELECT * FROM compta_journaux ORDER BY code");
$stmt->execute();
$journaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'exercice actif
$exercice = compta_get_exercice_actif($pdo);

// Récupérer les messages flash
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ===== AFFICHAGE =====

if ($action === 'detail' && $piece_id > 0) {
    // Détail d'une pièce
    $stmt = $pdo->prepare("
        SELECT cp.*, j.code as journal_code, j.libelle as journal_libelle,
               c.nom as client_nom, f.nom as fournisseur_nom
        FROM compta_pieces cp
        JOIN compta_journaux j ON j.id = cp.journal_id
        LEFT JOIN clients c ON c.id = cp.tiers_client_id
        LEFT JOIN fournisseurs f ON f.id = cp.tiers_fournisseur_id
        WHERE cp.id = ?
    ");
    $stmt->execute([$piece_id]);
    $piece = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$piece) {
        http_response_code(404);
        exit("Pièce non trouvée");
    }
    
    $ecritures = compta_get_ecritures_piece($pdo, $piece_id);
    
    // Vérifier l'équilibre débit/crédit
    $total_debit = 0;
    $total_credit = 0;
    foreach ($ecritures as $e) {
        $total_debit += (float)($e['debit'] ?? 0);
        $total_credit += (float)($e['credit'] ?? 0);
    }
    $est_equilibree = abs($total_debit - $total_credit) < 0.01;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journaux Comptables</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.0/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
</head>
<body>
    <?php require_once __DIR__ . '/../partials/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3">
                <?php require_once __DIR__ . '/../partials/sidebar.php'; ?>
            </div>
            
            <div class="col-md-9">
                <h2 class="mb-4">Journaux Comptables</h2>
                
                <?php if ($flash_success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
                <?php endif; ?>
                
                <?php if ($flash_error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($flash_error) ?></div>
                <?php endif; ?>
                
                <!-- LISTE DES JOURNAUX OU DÉTAIL D'UNE PIÈCE -->
                <?php if ($action === 'list' || $journal_id === 0): ?>
                    <!-- Liste des journaux -->
                    <div class="row">
                        <?php foreach ($journaux as $j): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h5><?= htmlspecialchars($j['code']) ?> - <?= htmlspecialchars($j['libelle']) ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted"><?= htmlspecialchars($j['type']) ?></p>
                                        <?php
                                            $stmt = $pdo->prepare("
                                                SELECT COUNT(*) as nb FROM compta_pieces 
                                                WHERE journal_id = ? AND est_validee = 0
                                            ");
                                            $stmt->execute([$j['id']]);
                                            $count = $stmt->fetch()['nb'];
                                        ?>
                                        <p>
                                            <strong><?= $count ?></strong> pièce(s) à valider
                                        </p>
                                        <a href="?action=pieces&journal_id=<?= $j['id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-list"></i> Consulter
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                
                <?php elseif ($action === 'pieces'): ?>
                    <!-- Liste des pièces d'un journal -->
                    <?php
                        $stmt = $pdo->prepare("SELECT * FROM compta_journaux WHERE id = ?");
                        $stmt->execute([$journal_id]);
                        $journal = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $pieces = compta_get_pieces_journal($pdo, $journal_id, $exercice['id'] ?? null, null, null, 200);
                    ?>
                    
                    <div class="mb-3">
                        <a href="journaux.php" class="btn btn-secondary btn-sm">← Retour</a>
                        <h4><?= htmlspecialchars($journal['code']) ?> - <?= htmlspecialchars($journal['libelle']) ?></h4>
                    </div>
                    
                    <div class="card">
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>N° Pièce</th>
                                        <th>Date</th>
                                        <th>Référence</th>
                                        <th>Client/Fournisseur</th>
                                        <th>Écritures</th>
                                        <th class="text-center">Validée</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pieces as $p): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($p['numero_piece']) ?></strong></td>
                                            <td><?= htmlspecialchars($p['date_piece']) ?></td>
                                            <td>
                                                <?= htmlspecialchars($p['reference_type'] ?? '') ?>
                                                <?php if ($p['reference_id']): ?>
                                                    #<?= (int)$p['reference_id'] ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($p['client_nom'] ?? $p['fournisseur_nom'] ?? '—') ?>
                                            </td>
                                            <td><?= (int)($p['nb_ecritures'] ?? 0) ?></td>
                                            <td class="text-center">
                                                <?php if ($p['est_validee']): ?>
                                                    <span class="badge bg-success">Oui</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Non</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?action=detail&piece_id=<?= $p['id'] ?>&journal_id=<?= $journal_id ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i> Détail
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                
                <?php elseif ($action === 'detail' && $piece): ?>
                    <!-- Détail d'une pièce -->
                    <div class="mb-3">
                        <a href="?action=pieces&journal_id=<?= $piece['journal_id'] ?>" class="btn btn-secondary btn-sm">
                            ← Retour au journal
                        </a>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5>Pièce <?= htmlspecialchars($piece['numero_piece']) ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <small class="text-muted">Journal</small>
                                    <p><strong><?= htmlspecialchars($piece['journal_code']) ?></strong></p>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Date</small>
                                    <p><strong><?= htmlspecialchars($piece['date_piece']) ?></strong></p>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Statut</small>
                                    <p>
                                        <?php if ($piece['est_validee']): ?>
                                            <span class="badge bg-success">Validée</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Brouillon</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Équilibre</small>
                                    <p>
                                        <?php if ($est_equilibree): ?>
                                            <span class="badge bg-success">✓ OK</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">✗ Erreur</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <?php if ($piece['client_nom']): ?>
                                <p><strong>Client :</strong> <?= htmlspecialchars($piece['client_nom']) ?></p>
                            <?php endif; ?>
                            
                            <?php if ($piece['fournisseur_nom']): ?>
                                <p><strong>Fournisseur :</strong> <?= htmlspecialchars($piece['fournisseur_nom']) ?></p>
                            <?php endif; ?>
                            
                            <?php if ($piece['observations']): ?>
                                <p><strong>Observations :</strong> <?= htmlspecialchars($piece['observations']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5>Écritures</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Compte</th>
                                        <th>Libellé</th>
                                        <th class="text-end">Débit</th>
                                        <th class="text-end">Crédit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ecritures as $e): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($e['numero_compte']) ?></strong></td>
                                            <td><?= htmlspecialchars($e['libelle_ecriture'] ?? $e['compte_libelle']) ?></td>
                                            <td class="text-end">
                                                <?php if ((float)$e['debit'] > 0): ?>
                                                    <?= number_format($e['debit'], 2, ',', ' ') ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ((float)$e['credit'] > 0): ?>
                                                    <?= number_format($e['credit'], 2, ',', ' ') ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-dark">
                                        <td colspan="2"><strong>TOTAUX</strong></td>
                                        <td class="text-end"><strong><?= number_format($total_debit, 2, ',', ' ') ?></strong></td>
                                        <td class="text-end"><strong><?= number_format($total_credit, 2, ',', ' ') ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php require_once __DIR__ . '/../partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
