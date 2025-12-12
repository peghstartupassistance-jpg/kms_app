<?php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('COMPTABILITE_LIRE');

$action = $_GET['action'] ?? 'list';

// Messages flash
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Types d'opérations possibles
$operation_types = ['VENTE', 'ACHAT', 'CAISSE', 'INSCRIPTIONS', 'RESERVATIONS'];

// === TRAITEMENT DU FORMULAIRE ===

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_form = $_POST['action_form'] ?? null;
    
    if ($action_form === 'add') {
        $source_type = trim($_POST['source_type'] ?? '');
        $code_operation = trim($_POST['code_operation'] ?? '');
        $journal_id = (int)($_POST['journal_id'] ?? 0);
        $compte_debit_id = (int)($_POST['compte_debit_id'] ?? 0);
        $compte_credit_id = (int)($_POST['compte_credit_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if (empty($source_type) || empty($code_operation) || $journal_id == 0 || $compte_debit_id == 0 || $compte_credit_id == 0) {
            $_SESSION['flash_error'] = 'Veuillez remplir tous les champs obligatoires.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO compta_mapping_operations 
                    (source_type, code_operation, journal_id, compte_debit_id, compte_credit_id, description, actif, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$source_type, $code_operation, $journal_id, $compte_debit_id, $compte_credit_id, $description]);
                $_SESSION['flash_success'] = 'Mapping créé avec succès.';
            } catch (Exception $e) {
                error_log("Erreur création mapping: " . $e->getMessage());
                $_SESSION['flash_error'] = 'Erreur lors de la création du mapping.';
            }
        }
        header('Location: parametrage_mappings.php');
        exit;
    }
    
    elseif ($action_form === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $source_type = trim($_POST['source_type'] ?? '');
        $code_operation = trim($_POST['code_operation'] ?? '');
        $journal_id = (int)($_POST['journal_id'] ?? 0);
        $compte_debit_id = (int)($_POST['compte_debit_id'] ?? 0);
        $compte_credit_id = (int)($_POST['compte_credit_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $actif = isset($_POST['actif']) ? 1 : 0;
        
        if ($id == 0) {
            $_SESSION['flash_error'] = 'ID du mapping invalide.';
        } elseif (empty($source_type) || empty($code_operation) || $journal_id == 0 || $compte_debit_id == 0 || $compte_credit_id == 0) {
            $_SESSION['flash_error'] = 'Veuillez remplir tous les champs obligatoires.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE compta_mapping_operations 
                    SET source_type = ?, code_operation = ?, journal_id = ?, 
                        compte_debit_id = ?, compte_credit_id = ?, description = ?, actif = ?
                    WHERE id = ?
                ");
                $stmt->execute([$source_type, $code_operation, $journal_id, $compte_debit_id, $compte_credit_id, $description, $actif, $id]);
                $_SESSION['flash_success'] = 'Mapping mis à jour avec succès.';
            } catch (Exception $e) {
                error_log("Erreur mise à jour mapping: " . $e->getMessage());
                $_SESSION['flash_error'] = 'Erreur lors de la mise à jour du mapping.';
            }
        }
        header('Location: parametrage_mappings.php');
        exit;
    }
    
    elseif ($action_form === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM compta_mapping_operations WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['flash_success'] = 'Mapping supprimé avec succès.';
            } catch (Exception $e) {
                error_log("Erreur suppression mapping: " . $e->getMessage());
                $_SESSION['flash_error'] = 'Erreur lors de la suppression du mapping.';
            }
        }
        header('Location: parametrage_mappings.php');
        exit;
    }
}

// Récupérer les mappings
$stmt = $pdo->prepare("
    SELECT m.*, j.code as journal_code, j.libelle as journal_libelle,
           cd.numero_compte as debit_numero, cd.libelle as debit_libelle,
           cc.numero_compte as credit_numero, cc.libelle as credit_libelle
    FROM compta_mapping_operations m
    JOIN compta_journaux j ON j.id = m.journal_id
    JOIN compta_comptes cd ON cd.id = m.compte_debit_id
    JOIN compta_comptes cc ON cc.id = m.compte_credit_id
    ORDER BY m.source_type, m.code_operation
");
$stmt->execute();
$mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les journaux
$stmt = $pdo->prepare("SELECT * FROM compta_journaux ORDER BY code");
$stmt->execute();
$journaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les comptes (colonne `est_actif` dans la table)
$stmt = $pdo->prepare("SELECT * FROM compta_comptes WHERE est_actif = 1 ORDER BY numero_compte");
$stmt->execute();
$comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// GET action=edit
$mapping_edit = null;
if ($action === 'edit') {
    $edit_id = (int)($_GET['id'] ?? 0);
    if ($edit_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM compta_mapping_operations WHERE id = ?");
        $stmt->execute([$edit_id]);
        $mapping_edit = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramétrage Mappings</title>
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
                <h2 class="mb-4">Paramétrage des Mappings Comptables</h2>
                
                <?php if ($flash_success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($flash_success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($flash_error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($flash_error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- FORMULAIRE D'AJOUT / ÉDITION -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5>
                            <?php if ($mapping_edit): ?>
                                <i class="bi bi-pencil"></i> Modifier un mapping
                            <?php else: ?>
                                <i class="bi bi-plus-circle"></i> Créer un nouveau mapping
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action_form" value="<?= $mapping_edit ? 'edit' : 'add' ?>">
                            <?php if ($mapping_edit): ?>
                                <input type="hidden" name="id" value="<?= $mapping_edit['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="source_type" class="form-label">Type d'opération <span class="text-danger">*</span></label>
                                    <select name="source_type" id="source_type" class="form-select" required>
                                        <option value="">-- Sélectionner --</option>
                                        <?php foreach ($operation_types as $op_type): ?>
                                            <option value="<?= $op_type ?>" <?= ($mapping_edit && $mapping_edit['source_type'] === $op_type) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($op_type) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="code_operation" class="form-label">Code opération <span class="text-danger">*</span></label>
                                    <input type="text" name="code_operation" id="code_operation" class="form-control" 
                                           value="<?= htmlspecialchars($mapping_edit['code_operation'] ?? '') ?>" 
                                           placeholder="ex: VENTE_PRODUITS" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="journal_id" class="form-label">Journal <span class="text-danger">*</span></label>
                                    <select name="journal_id" id="journal_id" class="form-select" required>
                                        <option value="">-- Sélectionner --</option>
                                        <?php foreach ($journaux as $j): ?>
                                            <option value="<?= $j['id'] ?>" <?= ($mapping_edit && $mapping_edit['journal_id'] == $j['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($j['code'] . ' - ' . $j['libelle']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="compte_debit_id" class="form-label">Compte Débit <span class="text-danger">*</span></label>
                                    <select name="compte_debit_id" id="compte_debit_id" class="form-select" required>
                                        <option value="">-- Sélectionner --</option>
                                        <?php foreach ($comptes as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= ($mapping_edit && $mapping_edit['compte_debit_id'] == $c['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['numero_compte'] . ' - ' . $c['libelle']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="compte_credit_id" class="form-label">Compte Crédit <span class="text-danger">*</span></label>
                                    <select name="compte_credit_id" id="compte_credit_id" class="form-select" required>
                                        <option value="">-- Sélectionner --</option>
                                        <?php foreach ($comptes as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= ($mapping_edit && $mapping_edit['compte_credit_id'] == $c['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['numero_compte'] . ' - ' . $c['libelle']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea name="description" id="description" class="form-control" rows="2" 
                                          placeholder="ex: Génération automatique des écritures de vente"><?= htmlspecialchars($mapping_edit['description'] ?? '') ?></textarea>
                            </div>
                            
                            <?php if ($mapping_edit): ?>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" name="actif" id="actif" class="form-check-input" 
                                           <?= ($mapping_edit['actif'] == 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="actif">
                                        Actif
                                    </label>
                                </div>
                            <?php endif; ?>
                            
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> 
                                    <?= $mapping_edit ? 'Mettre à jour' : 'Créer' ?>
                                </button>
                                <?php if ($mapping_edit): ?>
                                    <a href="parametrage_mappings.php" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i> Annuler
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- LISTE DES MAPPINGS -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5>Mappings existants (<?= count($mappings) ?>)</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Type</th>
                                    <th>Code</th>
                                    <th>Journal</th>
                                    <th>Compte Débit</th>
                                    <th>Compte Crédit</th>
                                    <th>Description</th>
                                    <th class="text-center">Actif</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mappings as $m): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-info"><?= htmlspecialchars($m['source_type']) ?></span>
                                        </td>
                                        <td><strong><?= htmlspecialchars($m['code_operation']) ?></strong></td>
                                        <td><?= htmlspecialchars($m['journal_code']) ?></td>
                                        <td>
                                            <small><?= htmlspecialchars($m['debit_numero']) ?></small><br>
                                            <small class="text-muted"><?= htmlspecialchars($m['debit_libelle']) ?></small>
                                        </td>
                                        <td>
                                            <small><?= htmlspecialchars($m['credit_numero']) ?></small><br>
                                            <small class="text-muted"><?= htmlspecialchars($m['credit_libelle']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($m['description'] ?? '—') ?></td>
                                        <td class="text-center">
                                            <?php if ($m['actif']): ?>
                                                <span class="badge bg-success">Oui</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Non</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?action=edit&id=<?= $m['id'] ?>" class="btn btn-sm btn-warning">
                                                <i class="bi bi-pencil"></i> Éditer
                                            </a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action_form" value="delete">
                                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr ?')">
                                                    <i class="bi bi-trash"></i> Supprimer
                                                </button>
                                            </form>
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
    
    <?php require_once __DIR__ . '/../partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
