<?php
// compta/saisie_ecritures.php - Saisie d'écritures style Sage
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('COMPTABILITE_ECRIRE');

global $pdo;

// Récupérer l'exercice actif (non clôturé)
$stmt = $pdo->query("SELECT * FROM compta_exercices WHERE est_clos = 0 ORDER BY annee DESC LIMIT 1");
$exercice = $stmt->fetch();

if (!$exercice) {
    die("Aucun exercice ouvert. Veuillez créer un exercice dans la section Exercices.");
}

// Récupérer les journaux
$stmt = $pdo->query("SELECT * FROM compta_journaux ORDER BY code");
$journaux = $stmt->fetchAll();

// Récupérer les comptes SYSCOHADA
$stmt = $pdo->query("SELECT id, numero_compte, libelle, classe FROM compta_comptes WHERE est_actif = 1 ORDER BY numero_compte");
$comptes = $stmt->fetchAll();

// Traitement du formulaire
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'saisie_piece') {
        $journal_id = (int)($_POST['journal_id'] ?? 0);
        $date_piece = $_POST['date_piece'] ?? '';
        $libelle_piece = trim($_POST['libelle_piece'] ?? '');
        
        $lignes = $_POST['lignes'] ?? [];
        
        // Validation
        if ($journal_id <= 0) $errors[] = "Journal obligatoire";
        if (empty($date_piece)) $errors[] = "Date obligatoire";
        if (empty($libelle_piece)) $errors[] = "Libellé obligatoire";
        if (count($lignes) < 2) $errors[] = "Minimum 2 lignes d'écriture";
        
        // Calculer totaux
        $total_debit = 0;
        $total_credit = 0;
        
        foreach ($lignes as $ligne) {
            if (!empty($ligne['compte_id'])) {
                $total_debit += (float)($ligne['debit'] ?? 0);
                $total_credit += (float)($ligne['credit'] ?? 0);
            }
        }
        
        // Vérifier équilibre
        if (abs($total_debit - $total_credit) > 0.01) {
            $errors[] = "Pièce non équilibrée : Débit = " . number_format($total_debit, 0) . " | Crédit = " . number_format($total_credit, 0);
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Générer numéro de pièce
                $stmt = $pdo->prepare("SELECT code FROM compta_journaux WHERE id = ?");
                $stmt->execute([$journal_id]);
                $journal = $stmt->fetch();
                $numero_piece = $journal['code'] . '-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Créer la pièce
                $stmt = $pdo->prepare("
                    INSERT INTO compta_pieces 
                    (numero_piece, journal_id, date_piece, libelle, exercice_id, est_validee, created_at)
                    VALUES (?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$numero_piece, $journal_id, $date_piece, $libelle_piece, $exercice['id']]);
                $piece_id = $pdo->lastInsertId();
                
                // Créer les écritures
                foreach ($lignes as $ligne) {
                    $compte_id = (int)($ligne['compte_id'] ?? 0);
                    $libelle_ligne = trim($ligne['libelle'] ?? $libelle_piece);
                    $debit = (float)($ligne['debit'] ?? 0);
                    $credit = (float)($ligne['credit'] ?? 0);
                    
                    if ($compte_id > 0 && ($debit > 0 || $credit > 0)) {
                        $stmt = $pdo->prepare("
                            INSERT INTO compta_ecritures 
                            (piece_id, compte_id, libelle, debit, credit, created_at)
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$piece_id, $compte_id, $libelle_ligne, $debit, $credit]);
                    }
                }
                
                $pdo->commit();
                $success = "Pièce $numero_piece enregistrée et validée avec succès !";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<style>
.sage-container {
    background: #f0f2f5;
    padding: 15px;
    border-radius: 8px;
}

.sage-header {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    padding: 20px;
    border-radius: 8px 8px 0 0;
    margin-bottom: 20px;
}

.sage-form-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.sage-table {
    width: 100%;
    background: white;
    border-collapse: collapse;
}

.sage-table thead {
    background: #34495e;
    color: white;
}

.sage-table th {
    padding: 12px 8px;
    text-align: left;
    font-weight: 600;
    border: 1px solid #2c3e50;
}

.sage-table td {
    padding: 8px;
    border: 1px solid #ddd;
}

.sage-table tbody tr:hover {
    background: #f8f9fa;
}

.sage-table input[type="text"],
.sage-table input[type="number"],
.sage-table select {
    width: 100%;
    padding: 6px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.sage-table input[type="number"] {
    text-align: right;
    font-family: monospace;
}

.sage-totaux {
    background: #ecf0f1;
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
    border: 2px solid #bdc3c7;
}

.sage-totaux-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 16px;
    font-weight: bold;
}

.sage-totaux-row.equilibre {
    color: #27ae60;
    font-size: 18px;
    border-top: 2px solid #27ae60;
    padding-top: 15px;
    margin-top: 10px;
}

.sage-totaux-row.non-equilibre {
    color: #e74c3c;
    font-size: 18px;
    border-top: 2px solid #e74c3c;
    padding-top: 15px;
    margin-top: 10px;
}

.btn-sage-primary {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 3px 6px rgba(0,0,0,0.2);
}

.btn-sage-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 10px rgba(0,0,0,0.3);
}

.btn-sage-secondary {
    background: #95a5a6;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
}

.compte-select {
    font-family: 'Courier New', monospace;
    font-size: 13px;
}

.ligne-numero {
    background: #ecf0f1;
    font-weight: bold;
    text-align: center;
    color: #7f8c8d;
}
</style>

<div class="container-fluid sage-container">
    <div class="sage-header">
        <h2 class="mb-0">
            <i class="bi bi-journal-text me-2"></i>
            Saisie d'Écritures Comptables (Mode Sage)
        </h2>
        <p class="mb-0 mt-2 opacity-75">
            Exercice <?= htmlspecialchars($exercice['annee']) ?> | Plan SYSCOHADA
        </p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i>
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Erreurs :</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" id="formSaisie">
        <input type="hidden" name="action" value="saisie_piece">
        
        <!-- En-tête de la pièce -->
        <div class="sage-form-section">
            <h5 class="mb-3">
                <i class="bi bi-file-earmark-text me-2"></i>
                Informations de la pièce
            </h5>
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Journal <span class="text-danger">*</span></label>
                    <select name="journal_id" class="form-select" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($journaux as $j): ?>
                            <option value="<?= $j['id'] ?>">
                                <?= htmlspecialchars($j['code']) ?> - <?= htmlspecialchars($j['libelle']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Date <span class="text-danger">*</span></label>
                    <input type="date" name="date_piece" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Libellé de la pièce <span class="text-danger">*</span></label>
                    <input type="text" name="libelle_piece" class="form-control" placeholder="Ex: Vente marchandises client X" required>
                </div>
            </div>
        </div>

        <!-- Tableau de saisie des écritures -->
        <div class="sage-form-section">
            <h5 class="mb-3">
                <i class="bi bi-list-ol me-2"></i>
                Lignes d'écritures (Débit = Crédit obligatoire)
            </h5>
            
            <table class="sage-table" id="tableLignes">
                <thead>
                    <tr>
                        <th style="width: 50px;">N°</th>
                        <th style="width: 35%;">Compte</th>
                        <th style="width: 30%;">Libellé</th>
                        <th style="width: 15%;">Débit</th>
                        <th style="width: 15%;">Crédit</th>
                        <th style="width: 80px;">Action</th>
                    </tr>
                </thead>
                <tbody id="lignesContainer">
                    <!-- Ligne 1 -->
                    <tr class="ligne-ecriture" data-index="1">
                        <td class="ligne-numero">1</td>
                        <td>
                            <select name="lignes[1][compte_id]" class="compte-select form-select form-select-sm" required>
                                <option value="">-- Sélectionner un compte --</option>
                                <?php foreach ($comptes as $c): ?>
                                    <option value="<?= $c['id'] ?>">
                                        <?= htmlspecialchars($c['numero_compte']) ?> - <?= htmlspecialchars($c['libelle']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="lignes[1][libelle]" class="form-control form-control-sm" placeholder="Libellé spécifique (optionnel)">
                        </td>
                        <td>
                            <input type="number" name="lignes[1][debit]" class="form-control form-control-sm montant-debit" step="0.01" min="0" value="0">
                        </td>
                        <td>
                            <input type="number" name="lignes[1][credit]" class="form-control form-control-sm montant-credit" step="0.01" min="0" value="0">
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-danger" onclick="supprimerLigne(1)" title="Supprimer">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <!-- Ligne 2 -->
                    <tr class="ligne-ecriture" data-index="2">
                        <td class="ligne-numero">2</td>
                        <td>
                            <select name="lignes[2][compte_id]" class="compte-select form-select form-select-sm" required>
                                <option value="">-- Sélectionner un compte --</option>
                                <?php foreach ($comptes as $c): ?>
                                    <option value="<?= $c['id'] ?>">
                                        <?= htmlspecialchars($c['numero_compte']) ?> - <?= htmlspecialchars($c['libelle']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="lignes[2][libelle]" class="form-control form-control-sm" placeholder="Libellé spécifique (optionnel)">
                        </td>
                        <td>
                            <input type="number" name="lignes[2][debit]" class="form-control form-control-sm montant-debit" step="0.01" min="0" value="0">
                        </td>
                        <td>
                            <input type="number" name="lignes[2][credit]" class="form-control form-control-sm montant-credit" step="0.01" min="0" value="0">
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-danger" onclick="supprimerLigne(2)" title="Supprimer">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <button type="button" class="btn btn-sage-secondary mt-3" onclick="ajouterLigne()">
                <i class="bi bi-plus-circle me-1"></i> Ajouter une ligne
            </button>
        </div>

        <!-- Totaux et équilibre -->
        <div class="sage-totaux">
            <div class="sage-totaux-row">
                <span>Total Débit :</span>
                <span id="totalDebit">0 FCFA</span>
            </div>
            <div class="sage-totaux-row">
                <span>Total Crédit :</span>
                <span id="totalCredit">0 FCFA</span>
            </div>
            <div class="sage-totaux-row equilibre" id="rowEquilibre" style="display:none;">
                <span>✓ Pièce équilibrée</span>
                <span id="totalEquilibre">0 FCFA</span>
            </div>
            <div class="sage-totaux-row non-equilibre" id="rowNonEquilibre" style="display:none;">
                <span>⚠ Écart (Non équilibré)</span>
                <span id="ecartEquilibre">0 FCFA</span>
            </div>
        </div>

        <!-- Boutons d'action -->
        <div class="text-center mt-4">
            <button type="submit" class="btn-sage-primary" id="btnValider">
                <i class="bi bi-check-circle me-2"></i>
                Enregistrer et valider la pièce
            </button>
            <button type="button" class="btn-sage-secondary ms-3" onclick="window.location.reload()">
                <i class="bi bi-x-circle me-2"></i>
                Annuler
            </button>
        </div>
    </form>
</div>

<script>
let ligneIndex = 2;

function ajouterLigne() {
    ligneIndex++;
    const tbody = document.getElementById('lignesContainer');
    const tr = document.createElement('tr');
    tr.className = 'ligne-ecriture';
    tr.setAttribute('data-index', ligneIndex);
    
    tr.innerHTML = `
        <td class="ligne-numero">${ligneIndex}</td>
        <td>
            <select name="lignes[${ligneIndex}][compte_id]" class="compte-select form-select form-select-sm">
                <option value="">-- Sélectionner un compte --</option>
                <?php foreach ($comptes as $c): ?>
                    <option value="<?= $c['id'] ?>">
                        <?= htmlspecialchars($c['numero_compte']) ?> - <?= htmlspecialchars($c['libelle']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="text" name="lignes[${ligneIndex}][libelle]" class="form-control form-control-sm" placeholder="Libellé spécifique (optionnel)">
        </td>
        <td>
            <input type="number" name="lignes[${ligneIndex}][debit]" class="form-control form-control-sm montant-debit" step="0.01" min="0" value="0">
        </td>
        <td>
            <input type="number" name="lignes[${ligneIndex}][credit]" class="form-control form-control-sm montant-credit" step="0.01" min="0" value="0">
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-danger" onclick="supprimerLigne(${ligneIndex})" title="Supprimer">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(tr);
    attachEventListeners();
    calculerTotaux();
}

function supprimerLigne(index) {
    const lignes = document.querySelectorAll('.ligne-ecriture');
    if (lignes.length <= 2) {
        alert('Minimum 2 lignes requises !');
        return;
    }
    
    const ligne = document.querySelector(`tr[data-index="${index}"]`);
    if (ligne) {
        ligne.remove();
        renumeroterLignes();
        calculerTotaux();
    }
}

function renumeroterLignes() {
    const lignes = document.querySelectorAll('.ligne-ecriture');
    lignes.forEach((ligne, idx) => {
        const numero = idx + 1;
        ligne.setAttribute('data-index', numero);
        ligne.querySelector('.ligne-numero').textContent = numero;
    });
    ligneIndex = lignes.length;
}

function calculerTotaux() {
    let totalDebit = 0;
    let totalCredit = 0;
    
    document.querySelectorAll('.montant-debit').forEach(input => {
        totalDebit += parseFloat(input.value) || 0;
    });
    
    document.querySelectorAll('.montant-credit').forEach(input => {
        totalCredit += parseFloat(input.value) || 0;
    });
    
    document.getElementById('totalDebit').textContent = formatMontant(totalDebit);
    document.getElementById('totalCredit').textContent = formatMontant(totalCredit);
    
    const ecart = Math.abs(totalDebit - totalCredit);
    
    if (ecart < 0.01 && totalDebit > 0) {
        document.getElementById('rowEquilibre').style.display = 'flex';
        document.getElementById('rowNonEquilibre').style.display = 'none';
        document.getElementById('totalEquilibre').textContent = formatMontant(totalDebit);
        document.getElementById('btnValider').disabled = false;
    } else {
        document.getElementById('rowEquilibre').style.display = 'none';
        document.getElementById('rowNonEquilibre').style.display = 'flex';
        document.getElementById('ecartEquilibre').textContent = formatMontant(ecart);
        document.getElementById('btnValider').disabled = totalDebit === 0 && totalCredit === 0;
    }
}

function formatMontant(montant) {
    return new Intl.NumberFormat('fr-FR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(montant) + ' FCFA';
}

function attachEventListeners() {
    document.querySelectorAll('.montant-debit, .montant-credit').forEach(input => {
        input.removeEventListener('input', calculerTotaux);
        input.addEventListener('input', calculerTotaux);
    });
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    attachEventListeners();
    calculerTotaux();
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
