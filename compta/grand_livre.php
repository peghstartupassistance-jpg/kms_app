<?php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('COMPTABILITE_LIRE');

require_once __DIR__ . '/../lib/compta.php';

// Récupérer les paramètres
$action = $_GET['action'] ?? 'list';
$compte_id = isset($_GET['compte_id']) ? (int)$_GET['compte_id'] : 0;
$classe = isset($_GET['classe']) ? (int)$_GET['classe'] : 0;

// Récupérer l'exercice actif
$exercice = compta_get_exercice_actif($pdo);

// Les 8 classes comptables
$classes = [
    1 => 'Immobilisations',
    2 => 'Stocks',
    3 => 'Tiers',
    4 => 'Financier',
    5 => 'Gestion',
    6 => 'Charges',
    7 => 'Produits',
    8 => 'Spéciaux'
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grand Livre Comptable</title>
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
                <h2 class="mb-4">Grand Livre Comptable</h2>
                
                <?php if ($action === 'list' || $compte_id === 0): ?>
                    <!-- Liste des comptes par classe -->
                    
                    <div class="row">
                        <?php foreach ($classes as $num_classe => $nom_classe): ?>
                            <?php
                                $stmt = $pdo->prepare(
                                    "SELECT id, numero_compte, libelle, classe
                                    FROM compta_comptes
                                    WHERE classe = ? AND est_actif = 1
                                    ORDER BY numero_compte"
                                );
                                $stmt->execute([$num_classe]);
                                $comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-header bg-secondary text-white">
                                        <h6>Classe <?= $num_classe ?> - <?= htmlspecialchars($nom_classe) ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($comptes)): ?>
                                            <p class="text-muted">Aucun compte</p>
                                        <?php else: ?>
                                            <div class="list-group">
                                                <?php foreach ($comptes as $c): ?>
                                                    <a href="?action=detail&compte_id=<?= $c['id'] ?>" class="list-group-item list-group-item-action">
                                                        <span class="badge bg-info me-2"><?= htmlspecialchars($c['numero_compte']) ?></span>
                                                        <?= htmlspecialchars($c['libelle']) ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                
                <?php elseif ($action === 'detail' && $compte_id > 0): ?>
                    <!-- Détail du grand livre d'un compte -->
                    <?php
                        // Récupérer le compte
                        $stmt = $pdo->prepare("SELECT * FROM compta_comptes WHERE id = ?");
                        $stmt->execute([$compte_id]);
                        $compte = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$compte) {
                            http_response_code(404);
                            exit("Compte non trouvé");
                        }
                        
                        // Récupérer les mouvements
                        $mouvements = compta_get_grand_livre_compte($pdo, $compte_id, $exercice['id'] ?? null);
                        
                        // Calculer les totaux
                        $total_debit = 0;
                        $total_credit = 0;
                        $solde = 0;
                        
                        foreach ($mouvements as $m) {
                            $total_debit += (float)($m['debit'] ?? 0);
                            $total_credit += (float)($m['credit'] ?? 0);
                        }
                        
                        // Solde = débit - crédit (convention créancier = solde négatif)
                        $solde = $total_debit - $total_credit;
                    ?>
                    
                    <div class="mb-3">
                        <a href="grand_livre.php" class="btn btn-secondary btn-sm">← Retour</a>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5>Grand Livre - <?= htmlspecialchars($compte['numero_compte']) ?> - <?= htmlspecialchars($compte['libelle']) ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <small class="text-muted">Classe</small>
                                    <p><strong><?= (int)$compte['classe'] ?> - <?= htmlspecialchars($classes[(int)$compte['classe']] ?? '') ?></strong></p>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Nature</small>
                                    <p><strong><?= htmlspecialchars($compte['nature'] ?? 'N/A') ?></strong></p>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Type</small>
                                    <p><strong><?= htmlspecialchars($compte['type_compte'] ?? 'Normal') ?></strong></p>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Solde</small>
                                    <p>
                                        <strong class="<?= $solde >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= number_format($solde, 2, ',', ' ') ?>
                                        </strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5>Mouvements (<?= count($mouvements) ?> écritures)</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>N° Pièce</th>
                                        <th>Journal</th>
                                        <th>Libellé</th>
                                        <th class="text-end">Débit</th>
                                        <th class="text-end">Crédit</th>
                                        <th class="text-end">Solde</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        $solde_courant = 0;
                                        foreach ($mouvements as $m):
                                            $solde_courant += (float)($m['debit'] ?? 0) - (float)($m['credit'] ?? 0);
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($m['date_piece']) ?></td>
                                            <td><small><?= htmlspecialchars($m['numero_piece'] ?? '—') ?></small></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($m['journal_code'] ?? '—') ?></span></td>
                                            <td><?= htmlspecialchars($m['libelle_ecriture'] ?? $m['libelle'] ?? '') ?></td>
                                            <td class="text-end">
                                                <?php if ((float)($m['debit'] ?? 0) > 0): ?>
                                                    <?= number_format($m['debit'], 2, ',', ' ') ?>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ((float)($m['credit'] ?? 0) > 0): ?>
                                                    <?= number_format($m['credit'], 2, ',', ' ') ?>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end font-monospace <?= $solde_courant >= 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= number_format($solde_courant, 2, ',', ' ') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <tr class="table-dark">
                                        <td colspan="4"><strong>TOTAUX</strong></td>
                                        <td class="text-end"><strong><?= number_format($total_debit, 2, ',', ' ') ?></strong></td>
                                        <td class="text-end"><strong><?= number_format($total_credit, 2, ',', ' ') ?></strong></td>
                                        <td class="text-end"><strong class="<?= $solde >= 0 ? 'text-success' : 'text-danger' ?>"><?= number_format($solde, 2, ',', ' ') ?></strong></td>
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
