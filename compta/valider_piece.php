<?php
// compta/valider_piece.php - Validation des pièces comptables
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('COMPTABILITE_ECRIRE');

require_once __DIR__ . '/../lib/compta.php';

global $pdo;

$action = $_GET['action'] ?? 'list';
$piece_id = isset($_GET['piece_id']) ? (int)$_GET['piece_id'] : 0;
$exercice_id = isset($_GET['exercice_id']) ? (int)$_GET['exercice_id'] : 0;

// Messages flash
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ===== ACTIONS POST =====

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf($_POST['csrf_token'] ?? '');
    
    $action_form = $_POST['action_form'] ?? '';
    
    if ($action_form === 'valider_une') {
        $piece_id = (int)($_POST['piece_id'] ?? 0);
        if ($piece_id > 0) {
            // ✅ Vérifications avant validation
            $stmtPiece = $pdo->prepare("SELECT * FROM compta_pieces WHERE id = ?");
            $stmtPiece->execute([$piece_id]);
            $piece = $stmtPiece->fetch();
            
            if (!$piece) {
                $_SESSION['flash_error'] = "Pièce introuvable";
            } elseif ($piece['est_validee']) {
                $_SESSION['flash_error'] = "Cette pièce est déjà validée";
            } else {
                // Vérifier que l'exercice est ouvert
                $stmtExo = $pdo->prepare("SELECT est_clos FROM compta_exercices WHERE id = ?");
                $stmtExo->execute([$piece['exercice_id']]);
                $exo = $stmtExo->fetch();
                
                if ($exo && $exo['est_clos']) {
                    $_SESSION['flash_error'] = "Impossible : exercice clôturé";
                } else {
                    // Vérifier équilibre
                    $ecritures = compta_get_ecritures_piece($pdo, $piece_id);
                    $total_debit = 0;
                    $total_credit = 0;
                    foreach ($ecritures as $e) {
                        $total_debit += (float)($e['debit'] ?? 0);
                        $total_credit += (float)($e['credit'] ?? 0);
                    }
                    
                    if (abs($total_debit - $total_credit) > 0.01) {
                        $_SESSION['flash_error'] = "Impossible : la pièce n'est pas équilibrée (débit ≠ crédit). Écart : " . number_format(abs($total_debit - $total_credit), 2);
                    } else {
                        // Valider avec traçabilité
                        $utilisateur_id = $_SESSION['user_id'] ?? 1;
                        $stmt = $pdo->prepare("
                            UPDATE compta_pieces 
                            SET est_validee = 1, 
                                validee_par_id = ?, 
                                date_validation = NOW(),
                                updated_at = NOW() 
                            WHERE id = ?
                        ");
                        if ($stmt->execute([$utilisateur_id, $piece_id])) {
                            $_SESSION['flash_success'] = "Pièce #" . htmlspecialchars($piece['numero_piece']) . " validée";
                        } else {
                            $_SESSION['flash_error'] = "Erreur lors de la validation";
                        }
                    }
                }
            }
        }
        header('Location: valider_piece.php');
        exit;
    }
    
    if ($action_form === 'valider_masse') {
        $pieces = $_POST['pieces_ids'] ?? [];
        if (empty($pieces)) {
            $_SESSION['flash_error'] = "Sélectionnez au moins une pièce";
        } else {
            $nb_ok = 0;
            $nb_erreur = 0;
            $utilisateur_id = $_SESSION['user_id'] ?? 1;
            
            foreach ($pieces as $pid) {
                $pid = (int)$pid;
                
                // Vérifier exercice ouvert
                $stmtPiece = $pdo->prepare("SELECT exercice_id FROM compta_pieces WHERE id = ?");
                $stmtPiece->execute([$pid]);
                $p = $stmtPiece->fetch();
                if (!$p) continue;
                
                $stmtExo = $pdo->prepare("SELECT est_clos FROM compta_exercices WHERE id = ?");
                $stmtExo->execute([$p['exercice_id']]);
                $exo = $stmtExo->fetch();
                if ($exo && $exo['est_clos']) {
                    $nb_erreur++;
                    continue;
                }
                
                // Vérifier équilibre
                $ecritures = compta_get_ecritures_piece($pdo, $pid);
                $total_debit = 0;
                $total_credit = 0;
                foreach ($ecritures as $e) {
                    $total_debit += (float)($e['debit'] ?? 0);
                    $total_credit += (float)($e['credit'] ?? 0);
                }
                
                if (abs($total_debit - $total_credit) < 0.01) {
                    $stmt = $pdo->prepare("
                        UPDATE compta_pieces 
                        SET est_validee = 1,
                            validee_par_id = ?,
                            date_validation = NOW(),
                            updated_at = NOW() 
                        WHERE id = ?
                    ");
                    if ($stmt->execute([$utilisateur_id, $pid])) {
                        $nb_ok++;
                    } else {
                        $nb_erreur++;
                    }
                } else {
                    $nb_erreur++;
                }
            }
            
            $_SESSION['flash_success'] = "$nb_ok pièce(s) validée(s)";
            if ($nb_erreur > 0) {
                $_SESSION['flash_error'] = "$nb_erreur pièce(s) non validée(s) (déséquilibrée ou exercice clos)";
            }
        }
        header('Location: valider_piece.php');
        exit;
    }
}

// ===== RÉCUPÉRATION DES DONNÉES =====

// Exercices
$stmt = $pdo->prepare("SELECT * FROM compta_exercices WHERE est_clos = 0 ORDER BY annee DESC");
$stmt->execute();
$exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$exercice_actif = compta_get_exercice_actif($pdo);
if ($exercice_id === 0 && $exercice_actif) {
    $exercice_id = $exercice_actif['id'];
}

// Filtres
$journal_id = isset($_GET['journal_id']) ? (int)$_GET['journal_id'] : 0;
$filtre_statut = $_GET['statut'] ?? 'non_validees';

// Récupérer les pièces à valider
$sql = "
    SELECT 
        p.id, p.numero_piece, p.date_piece, j.code as journal_code, j.libelle as journal_libelle,
        p.est_validee,
        COUNT(e.id) as nb_ecritures,
        SUM(e.debit) as total_debit,
        SUM(e.credit) as total_credit
    FROM compta_pieces p
    JOIN compta_journaux j ON j.id = p.journal_id
    LEFT JOIN compta_ecritures e ON e.piece_id = p.id
    WHERE 1=1
";

if ($exercice_id > 0) {
    $sql .= " AND p.exercice_id = $exercice_id";
}

if ($journal_id > 0) {
    $sql .= " AND p.journal_id = $journal_id";
}

if ($filtre_statut === 'non_validees') {
    $sql .= " AND p.est_validee = 0";
} elseif ($filtre_statut === 'validees') {
    $sql .= " AND p.est_validee = 1";
}

$sql .= " GROUP BY p.id ORDER BY p.date_piece DESC, p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$pieces = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Journaux
$stmt = $pdo->prepare("SELECT * FROM compta_journaux ORDER BY code");
$stmt->execute();
$journaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valider les Pièces Comptables</title>
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
                    <i class="bi bi-check-circle"></i> Validation des Pièces
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
                
                <!-- Filtres -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Exercice</label>
                                <select name="exercice_id" class="form-select" onchange="this.form.submit()">
                                    <?php foreach ($exercices as $ex): ?>
                                        <option value="<?= $ex['id'] ?>" <?= $exercice_id === $ex['id'] ? 'selected' : '' ?>>
                                            <?= (int)$ex['annee'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Journal</label>
                                <select name="journal_id" class="form-select" onchange="this.form.submit()">
                                    <option value="0">Tous les journaux</option>
                                    <?php foreach ($journaux as $j): ?>
                                        <option value="<?= $j['id'] ?>" <?= $journal_id === $j['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($j['code']) ?> - <?= htmlspecialchars($j['libelle']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Statut</label>
                                <select name="statut" class="form-select" onchange="this.form.submit()">
                                    <option value="non_validees" <?= $filtre_statut === 'non_validees' ? 'selected' : '' ?>>
                                        À valider
                                    </option>
                                    <option value="validees" <?= $filtre_statut === 'validees' ? 'selected' : '' ?>>
                                        Validées
                                    </option>
                                    <option value="">Toutes</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="reset" class="btn btn-outline-secondary w-100">Réinitialiser</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Liste des pièces -->
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <span>Pièces comptables</span>
                        <span class="badge bg-light text-dark"><?= count($pieces) ?> pièce(s)</span>
                    </div>
                    
                    <?php if (empty($pieces)): ?>
                        <div class="card-body">
                            <p class="text-muted text-center">Aucune pièce trouvée</p>
                        </div>
                    <?php else: ?>
                        <form method="POST" id="formValidation">
                            <input type="hidden" name="action_form" value="valider_masse">
                            
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="40">
                                                <input type="checkbox" id="checkAll" class="form-check-input">
                                            </th>
                                            <th>N° Pièce</th>
                                            <th>Date</th>
                                            <th>Journal</th>
                                            <th class="text-end">Débit</th>
                                            <th class="text-end">Crédit</th>
                                            <th class="text-center">Équilibre</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pieces as $p): ?>
                                            <?php 
                                                $debit = (float)($p['total_debit'] ?? 0);
                                                $credit = (float)($p['total_credit'] ?? 0);
                                                $est_equilibree = abs($debit - $credit) < 0.01;
                                            ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="pieces_ids[]" value="<?= $p['id'] ?>" class="form-check-input piece-checkbox">
                                                </td>
                                                <td><strong><?= htmlspecialchars($p['numero_piece']) ?></strong></td>
                                                <td><?= htmlspecialchars($p['date_piece']) ?></td>
                                                <td><?= htmlspecialchars($p['journal_code']) ?></td>
                                                <td class="text-end"><?= number_format($debit, 2, ',', ' ') ?></td>
                                                <td class="text-end"><?= number_format($credit, 2, ',', ' ') ?></td>
                                                <td class="text-center">
                                                    <?php if ($est_equilibree): ?>
                                                        <i class="bi bi-check-circle text-success"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-x-circle text-danger"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($p['est_validee']): ?>
                                                        <span class="badge bg-success">Validée</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Brouillon</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!$p['est_validee'] && $est_equilibree): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action_form" value="valider_une">
                                                            <input type="hidden" name="piece_id" value="<?= $p['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-success" title="Valider">
                                                                <i class="bi bi-check"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <a href="<?= '../compta/journaux.php?action=detail&piece_id=' . $p['id'] ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary" id="btnValiderMasse" disabled>
                                    <i class="bi bi-check-all"></i> Valider la sélection
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php require_once __DIR__ . '/../partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gestion des checkboxes
        const checkAll = document.getElementById('checkAll');
        const piecesCheckboxes = document.querySelectorAll('.piece-checkbox');
        const btnValider = document.getElementById('btnValiderMasse');
        
        function updateButton() {
            const checked = Array.from(piecesCheckboxes).some(cb => cb.checked);
            btnValider.disabled = !checked;
        }
        
        checkAll?.addEventListener('change', function() {
            piecesCheckboxes.forEach(cb => cb.checked = this.checked);
            updateButton();
        });
        
        piecesCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateButton);
        });
    </script>
</body>
</html>
