<?php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('COMPTABILITE_LIRE');

$action = $_GET['action'] ?? 'list';
$compte_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ===== TRAITEMENT DES ACTIONS =====

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create' || $action === 'edit') {
            $numero = trim($_POST['numero_compte'] ?? '');
            $libelle = trim($_POST['libelle'] ?? '');
            $classe = trim($_POST['classe'] ?? '');
            $type = $_POST['type_compte'] ?? 'ACTIF';
            $nature = $_POST['nature'] ?? 'AUTRE';
            $est_actif = isset($_POST['est_actif']) ? 1 : 0;
            $parent_id = isset($_POST['compte_parent_id']) ? (int)$_POST['compte_parent_id'] : null;
            
            if (!$numero || !$libelle || !$classe) {
                throw new Exception("Numéro, libellé et classe sont obligatoires");
            }
            
            if ($action === 'create') {
                $stmt = $pdo->prepare("
                    INSERT INTO compta_comptes 
                    (numero_compte, libelle, classe, type_compte, nature, est_actif, compte_parent_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$numero, $libelle, $classe, $type, $nature, $est_actif, $parent_id]);
                $_SESSION['flash_success'] = "Compte créé avec succès";
            } else {
                $stmt = $pdo->prepare("
                    UPDATE compta_comptes 
                    SET numero_compte = ?, libelle = ?, classe = ?, 
                        type_compte = ?, nature = ?, est_actif = ?, compte_parent_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$numero, $libelle, $classe, $type, $nature, $est_actif, $parent_id, $compte_id]);
                $_SESSION['flash_success'] = "Compte modifié avec succès";
            }
            
            header('Location: plan_comptable.php');
            exit;
        }
        
        if ($action === 'delete' && $compte_id > 0) {
            $pdo->prepare("DELETE FROM compta_comptes WHERE id = ?")->execute([$compte_id]);
            $_SESSION['flash_success'] = "Compte supprimé";
            header('Location: plan_comptable.php');
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
}

// ===== AFFICHAGE =====

if ($action === 'edit' && $compte_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM compta_comptes WHERE id = ?");
    $stmt->execute([$compte_id]);
    $compte = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$compte) {
        http_response_code(404);
        exit("Compte non trouvé");
    }
}

// Récupérer tous les comptes pour l'arborescence
$stmt = $pdo->prepare("
    SELECT id, numero_compte, libelle, classe, type_compte, est_actif, compte_parent_id
    FROM compta_comptes
    ORDER BY classe, numero_compte
");
$stmt->execute();
$tous_comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construire l'arborescence par classe
$comptes_par_classe = [];
foreach ($tous_comptes as $c) {
    $comptes_par_classe[$c['classe']][] = $c;
}

// Récupérer les messages flash
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan Comptable</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.0/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <style>
        .classe-header { background-color: #f0f0f0; font-weight: bold; padding: 10px; margin-top: 15px; }
        .compte-row { padding: 8px 10px; border-bottom: 1px solid #eee; }
        .compte-row:hover { background-color: #fafafa; }
        .compte-actif { color: #333; }
        .compte-inactif { color: #999; opacity: 0.7; }
        .sous-compte { margin-left: 30px; font-size: 0.95em; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../partials/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3">
                <?php require_once __DIR__ . '/../partials/sidebar.php'; ?>
            </div>
            
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Plan Comptable</h2>
                    <?php if ($action !== 'create' && $action !== 'edit'): ?>
                        <a href="?action=create" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Nouveau Compte
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php if ($flash_success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
                <?php endif; ?>
                
                <?php if ($flash_error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($flash_error) ?></div>
                <?php endif; ?>
                
                <!-- FORMULAIRE CRÉATION/ÉDITION -->
                <?php if ($action === 'create' || $action === 'edit'): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><?= $action === 'create' ? 'Nouveau Compte' : 'Modifier le Compte' ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">Numéro de Compte</label>
                                    <input type="text" name="numero_compte" class="form-control" required
                                        value="<?= htmlspecialchars($compte['numero_compte'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Libellé</label>
                                    <input type="text" name="libelle" class="form-control" required
                                        value="<?= htmlspecialchars($compte['libelle'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Classe (1-8)</label>
                                    <input type="text" name="classe" class="form-control" maxlength="1" required
                                        value="<?= htmlspecialchars($compte['classe'] ?? '') ?>">
                                    <small class="text-muted">1-5: Bilan, 6-7: Résultat</small>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-3">
                                    <label class="form-label">Type</label>
                                    <select name="type_compte" class="form-select">
                                        <option value="ACTIF" <?= ($compte['type_compte'] ?? '') === 'ACTIF' ? 'selected' : '' ?>>Actif</option>
                                        <option value="PASSIF" <?= ($compte['type_compte'] ?? '') === 'PASSIF' ? 'selected' : '' ?>>Passif</option>
                                        <option value="CHARGE" <?= ($compte['type_compte'] ?? '') === 'CHARGE' ? 'selected' : '' ?>>Charge</option>
                                        <option value="PRODUIT" <?= ($compte['type_compte'] ?? '') === 'PRODUIT' ? 'selected' : '' ?>>Produit</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Nature</label>
                                    <select name="nature" class="form-select">
                                        <option value="AUTRE">Autre</option>
                                        <option value="CREANCE" <?= ($compte['nature'] ?? '') === 'CREANCE' ? 'selected' : '' ?>>Créance</option>
                                        <option value="DETTE" <?= ($compte['nature'] ?? '') === 'DETTE' ? 'selected' : '' ?>>Dette</option>
                                        <option value="TRESORERIE" <?= ($compte['nature'] ?? '') === 'TRESORERIE' ? 'selected' : '' ?>>Trésorerie</option>
                                        <option value="VENTE" <?= ($compte['nature'] ?? '') === 'VENTE' ? 'selected' : '' ?>>Vente</option>
                                        <option value="CHARGE_VARIABLE" <?= ($compte['nature'] ?? '') === 'CHARGE_VARIABLE' ? 'selected' : '' ?>>Charge Variable</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Compte Parent</label>
                                    <select name="compte_parent_id" class="form-select">
                                        <option value="">Aucun (compte principal)</option>
                                        <?php foreach ($tous_comptes as $c): ?>
                                            <?php if ($c['id'] !== $compte_id): ?>
                                                <option value="<?= $c['id'] ?>" 
                                                    <?= ($compte['compte_parent_id'] ?? 0) === $c['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($c['numero_compte'] . ' - ' . $c['libelle']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <div class="form-check">
                                        <input type="checkbox" name="est_actif" class="form-check-input" 
                                            id="est_actif" <?= ($compte['est_actif'] ?? 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="est_actif">Actif</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Enregistrer
                                </button>
                                <a href="plan_comptable.php" class="btn btn-secondary">Annuler</a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- LISTE DES COMPTES PAR CLASSE -->
                <?php if ($action === 'list'): ?>
                <div class="card">
                    <div class="card-body">
                        <?php foreach (['1', '2', '3', '4', '5', '6', '7', '8'] as $classe): ?>
                            <?php if (!empty($comptes_par_classe[$classe])): ?>
                                <div class="classe-header">
                                    Classe <?= $classe ?> 
                                    <?php 
                                        $classe_labels = [
                                            '1' => 'Immobilisations',
                                            '2' => 'Stock et Encours',
                                            '3' => 'Tiers',
                                            '4' => 'Compte Financiers',
                                            '5' => 'Comptes de Gestion',
                                            '6' => 'Charges',
                                            '7' => 'Produits',
                                            '8' => 'Comptes Spéciaux'
                                        ];
                                        echo '(' . ($classe_labels[$classe] ?? '') . ')';
                                    ?>
                                </div>
                                
                                <?php foreach ($comptes_par_classe[$classe] as $c): ?>
                                    <div class="compte-row <?= $c['est_actif'] ? 'compte-actif' : 'compte-inactif' ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($c['numero_compte']) ?></strong>
                                                - <?= htmlspecialchars($c['libelle']) ?>
                                                <span class="badge bg-info"><?= htmlspecialchars($c['type_compte']) ?></span>
                                            </div>
                                            <div>
                                                <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="?action=delete&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                                    onclick="return confirm('Confirmer la suppression ?');">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
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
