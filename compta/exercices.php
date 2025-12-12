<?php
// compta/exercices.php - Gestion des exercices comptables
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('COMPTABILITE_ECRIRE');

require_once __DIR__ . '/../lib/compta.php';

global $pdo;

$action = $_GET['action'] ?? 'list';
$exercice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Messages flash
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ===== ACTIONS POST =====

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_form = $_POST['action_form'] ?? '';
    
    if ($action_form === 'creer') {
        $annee = (int)($_POST['annee'] ?? 0);
        $date_ouverture = $_POST['date_ouverture'] ?? '';
        
        if (!$annee || !$date_ouverture) {
            $_SESSION['flash_error'] = "Année et date d'ouverture obligatoires";
        } else {
            // Vérifier qu'on n'existe pas déjà
            $stmt = $pdo->prepare("SELECT id FROM compta_exercices WHERE annee = ?");
            $stmt->execute([$annee]);
            if ($stmt->fetch()) {
                $_SESSION['flash_error'] = "Exercice $annee existe déjà";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO compta_exercices (annee, date_ouverture, est_clos, created_at)
                    VALUES (?, ?, 0, NOW())
                ");
                if ($stmt->execute([$annee, $date_ouverture])) {
                    $_SESSION['flash_success'] = "Exercice $annee créé avec succès";
                } else {
                    $_SESSION['flash_error'] = "Erreur lors de la création";
                }
            }
        }
        header('Location: exercices.php');
        exit;
    }
    
    if ($action_form === 'activer') {
        $exercice_id = (int)($_POST['exercice_id'] ?? 0);
        if ($exercice_id > 0) {
            // Désactiver tous les autres
            $pdo->prepare("UPDATE compta_exercices SET est_actif = 0")->execute();
            // Activer celui-ci
            $stmt = $pdo->prepare("UPDATE compta_exercices SET est_actif = 1 WHERE id = ?");
            if ($stmt->execute([$exercice_id])) {
                $_SESSION['flash_success'] = "Exercice activé";
            }
        }
        header('Location: exercices.php');
        exit;
    }
    
    if ($action_form === 'cloturer') {
        $exercice_id = (int)($_POST['exercice_id'] ?? 0);
        if ($exercice_id > 0) {
            $stmt = $pdo->prepare("
                UPDATE compta_exercices 
                SET est_clos = 1, date_cloture = NOW()
                WHERE id = ? AND est_clos = 0
            ");
            if ($stmt->execute([$exercice_id])) {
                $_SESSION['flash_success'] = "Exercice clôturé";
            } else {
                $_SESSION['flash_error'] = "Erreur lors de la clôture";
            }
        }
        header('Location: exercices.php');
        exit;
    }
}

// ===== RÉCUPÉRATION DES DONNÉES =====

$stmt = $pdo->prepare("
    SELECT 
        e.*,
        COUNT(DISTINCT cp.id) as nb_pieces,
        COUNT(DISTINCT CASE WHEN cp.est_validee = 0 THEN cp.id END) as pieces_a_valider
    FROM compta_exercices e
    LEFT JOIN compta_pieces cp ON cp.exercice_id = e.id
    GROUP BY e.id
    ORDER BY e.annee DESC
");
$stmt->execute();
$exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$exercice_actif = compta_get_exercice_actif($pdo);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exercices Comptables</title>
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
                <h2 class="mb-4">
                    <i class="bi bi-calendar-year"></i> Exercices Comptables
                </h2>
                
                <?php if ($flash_success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($flash_success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($flash_error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($flash_error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Bouton créer exercice -->
                <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modalCreerExercice">
                    <i class="bi bi-plus-circle"></i> Nouvel Exercice
                </button>
                
                <!-- Liste des exercices -->
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Année</th>
                                    <th>Ouverture</th>
                                    <th>Clôture</th>
                                    <th>Statut</th>
                                    <th class="text-center">Pièces</th>
                                    <th class="text-center">À Valider</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exercices as $ex): ?>
                                    <tr>
                                        <td>
                                            <strong><?= (int)$ex['annee'] ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($ex['date_ouverture']) ?></td>
                                        <td><?= $ex['date_cloture'] ? htmlspecialchars($ex['date_cloture']) : '—' ?></td>
                                        <td>
                                            <?php if ($ex['est_clos']): ?>
                                                <span class="badge bg-secondary">Clôturé</span>
                                            <?php elseif ($ex['est_actif'] ?? false): ?>
                                                <span class="badge bg-success">Actif</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?= (int)$ex['nb_pieces'] ?></td>
                                        <td class="text-center">
                                            <?php if ((int)$ex['pieces_a_valider'] > 0): ?>
                                                <span class="badge bg-warning"><?= (int)$ex['pieces_a_valider'] ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$ex['est_clos']): ?>
                                                <?php if (!($ex['est_actif'] ?? false)): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action_form" value="activer">
                                                        <input type="hidden" name="exercice_id" value="<?= $ex['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Activer cet exercice ?')">
                                                            <i class="bi bi-arrow-counterclockwise"></i> Activer
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action_form" value="cloturer">
                                                    <input type="hidden" name="exercice_id" value="<?= $ex['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Clôturer cet exercice ? (irréversible)')">
                                                        <i class="bi bi-lock"></i> Clôturer
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal création exercice -->
    <div class="modal fade" id="modalCreerExercice" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Créer un nouvel exercice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action_form" value="creer">
                        
                        <div class="mb-3">
                            <label for="annee" class="form-label">Année <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="annee" name="annee" required min="2000" max="2099">
                        </div>
                        
                        <div class="mb-3">
                            <label for="date_ouverture" class="form-label">Date d'ouverture <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_ouverture" name="date_ouverture" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php require_once __DIR__ . '/../partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
