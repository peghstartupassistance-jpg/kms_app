<?php
// caisse/journal.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CAISSE_LIRE');

global $pdo;

$peutEcrire = in_array('CAISSE_ECRIRE', $_SESSION['permissions'] ?? [], true);

// --- Traitement POST : ajout / annulation d'une opération de caisse ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $peutEcrire) {
    try {
        verifierCsrf($_POST['csrf_token'] ?? '');

        // 1) Annulation d'une opération existante
        if (isset($_POST['annuler_operation_id'])) {
            $opId = (int)$_POST['annuler_operation_id'];

            if ($opId > 0) {
                $utilisateur  = utilisateurConnecte();
                $annuleParId  = $utilisateur['id'] ?? null;

                $stmt = $pdo->prepare("
                    UPDATE journal_caisse
                    SET est_annule = 1,
                        date_annulation = NOW(),
                        annule_par_id = :annule_par_id
                    WHERE id = :id
                      AND est_annule = 0
                ");
                $stmt->execute([
                    'id'            => $opId,
                    'annule_par_id' => $annuleParId,
                ]);

                $_SESSION['flash_success'] = "L'opération de caisse a été annulée.";
            }

            header('Location: ' . url_for('caisse/journal.php'));
            exit;
        }

        // 2) Création d'une nouvelle opération de caisse
        $dateOperation = trim($_POST['date_operation'] ?? '');
        $sens          = $_POST['sens'] ?? '';
        $nature        = trim($_POST['nature_operation'] ?? '');
        $numeroPiece   = trim($_POST['numero_piece'] ?? '');
        $montant       = (float)str_replace(',', '.', $_POST['montant'] ?? '0');
        $modeId        = (int)($_POST['mode_paiement_id'] ?? 0);

        $venteId       = isset($_POST['vente_id']) ? (int)$_POST['vente_id'] : null;
        $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : null;
        $inscriptionId = isset($_POST['inscription_formation_id']) ? (int)$_POST['inscription_formation_id'] : null;

        $observations  = trim($_POST['observations'] ?? '');

        // Validations simples
        $erreurs = [];
        if ($dateOperation === '') {
            $erreurs[] = "La date d'opération est obligatoire.";
        }
        if (!in_array($sens, ['RECETTE','DEPENSE'], true)) {
            $erreurs[] = "Le sens de l'opération est invalide.";
        }
        if ($montant <= 0) {
            $erreurs[] = "Le montant doit être supérieur à 0.";
        }
        if ($modeId <= 0) {
            $erreurs[] = "Le mode de paiement est obligatoire.";
        }

        if (!empty($erreurs)) {
            $_SESSION['flash_error'] = implode(' ', $erreurs);
            header('Location: ' . url_for('caisse/journal.php'));
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO journal_caisse 
            (date_operation, numero_piece, nature_operation, sens, montant, 
             mode_paiement_id, vente_id, reservation_id, inscription_formation_id,
             responsable_encaissement_id, observations)
            VALUES 
            (:date_operation, :numero_piece, :nature_operation, :sens, :montant,
             :mode_paiement_id, :vente_id, :reservation_id, :inscription_formation_id,
             :resp_id, :observations)
        ");

        $utilisateur = utilisateurConnecte();
        $respId = $utilisateur['id'] ?? null;

        // IMPORTANT : si le champ est vide, on envoie une chaîne vide (et pas NULL)
        $numeroPieceValue = ($numeroPiece !== '') ? $numeroPiece : '';

        $stmt->execute([
            'date_operation'            => $dateOperation,
            'numero_piece'             => $numeroPieceValue,
            'nature_operation'         => $nature,
            'sens'                     => $sens,
            'montant'                  => $montant,
            'mode_paiement_id'         => $modeId,
            'vente_id'                 => $venteId ?: null,
            'reservation_id'           => $reservationId ?: null,
            'inscription_formation_id' => $inscriptionId ?: null,
            'resp_id'                  => $respId,
            'observations'             => $observations !== '' ? $observations : null,
        ]);

        $_SESSION['flash_success'] = "Opération de caisse enregistrée avec succès.";
        header('Location: ' . url_for('caisse/journal.php'));
        exit;

    } catch (Throwable $e) {
        // Tu peux décommenter la ligne suivante pour debug si besoin :
        // error_log('Erreur journal_caisse: ' . $e->getMessage());
        $_SESSION['flash_error'] = "Erreur lors de l'enregistrement de l'opération de caisse.";
        header('Location: ' . url_for('caisse/journal.php'));
        exit;
    }
}

// --- Export CSV ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $today      = date('Y-m-d');
    $dateDebut  = $_GET['date_debut'] ?? $today;
    $dateFin    = $_GET['date_fin'] ?? $today;
    $modeFiltre = isset($_GET['mode_id']) ? (int)$_GET['mode_id'] : 0;

    $where  = [];
    $params = [];

    if ($dateDebut !== '') {
        $where[] = "j.date_operation >= :date_debut";
        $params['date_debut'] = $dateDebut;
    }
    if ($dateFin !== '') {
        $where[] = "j.date_operation <= :date_fin";
        $params['date_fin'] = $dateFin;
    }
    if ($modeFiltre > 0) {
        $where[] = "j.mode_paiement_id = :mode_id";
        $params['mode_id'] = $modeFiltre;
    }

    $whereSql = '';
    if (!empty($where)) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

    $sql = "
        SELECT
            j.id,
            j.date_operation,
            j.numero_piece,
            j.nature_operation,
            j.sens,
            j.montant,
            mp.code AS mode_code,
            mp.libelle AS mode_libelle,
            v.numero AS vente_numero,
            c.nom   AS client_nom,
            rh.id   AS reservation_numero,
            f.nom   AS formation_nom,
            u.nom_complet AS responsable_nom,
            j.observations,
            j.est_annule
        FROM journal_caisse j
        JOIN modes_paiement mp ON mp.id = j.mode_paiement_id
        LEFT JOIN ventes v ON v.id = j.vente_id
        LEFT JOIN clients c ON c.id = v.client_id
        LEFT JOIN reservations_hotel rh ON rh.id = j.reservation_id
        LEFT JOIN inscriptions_formation inf ON inf.id = j.inscription_formation_id
        LEFT JOIN formations f ON f.id = inf.formation_id
        LEFT JOIN utilisateurs u ON u.id = j.responsable_encaissement_id
        $whereSql
        ORDER BY j.date_operation DESC, j.id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $operations = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8-sig');
    header('Content-Disposition: attachment; filename="journal_caisse_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    
    fputcsv($output, [
        'Date Opération',
        'Numéro Pièce',
        'Nature',
        'Sens',
        'Montant (FCFA)',
        'Mode de paiement',
        'Libellé mode',
        'Vente n°',
        'Client',
        'Réservation hôtel',
        'Formation',
        'Caissier',
        'Observations',
        'Statut'
    ], ';');
    
    foreach ($operations as $op) {
        $statut = (int)($op['est_annule'] ?? 0) === 1 ? 'Annulée' : 'Valide';
        fputcsv($output, [
            $op['date_operation'] ?? '',
            $op['numero_piece'] ?? '',
            $op['nature_operation'] ?? '',
            $op['sens'] ?? '',
            number_format((float)($op['montant'] ?? 0), 2, '.', ''),
            $op['mode_code'] ?? '',
            $op['mode_libelle'] ?? '',
            $op['vente_numero'] ?? '',
            $op['client_nom'] ?? '',
            $op['reservation_numero'] ?? '',
            $op['formation_nom'] ?? '',
            $op['responsable_nom'] ?? '',
            $op['observations'] ?? '',
            $statut
        ], ';');
    }
    
    fclose($output);
    exit;
}

// --- Filtres liste (GET) ---
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$today      = date('Y-m-d');
$dateDebut  = $_GET['date_debut'] ?? $today;
$dateFin    = $_GET['date_fin'] ?? $today;
$modeFiltre = isset($_GET['mode_id']) ? (int)$_GET['mode_id'] : 0;

// Modes de paiement
$stmt = $pdo->query("SELECT id, code, libelle FROM modes_paiement ORDER BY code");
$modesPaiement = $stmt->fetchAll();

// Construction WHERE
$where  = [];
$params = [];

if ($dateDebut !== '') {
    $where[] = "j.date_operation >= :date_debut";
    $params['date_debut'] = $dateDebut;
}
if ($dateFin !== '') {
    $where[] = "j.date_operation <= :date_fin";
    $params['date_fin'] = $dateFin;
}
if ($modeFiltre > 0) {
    $where[] = "j.mode_paiement_id = :mode_id";
    $params['mode_id'] = $modeFiltre;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// Journal de caisse
$sql = "
    SELECT
        j.*,
        mp.code AS mode_code,
        mp.libelle AS mode_libelle,
        v.numero AS vente_numero,
        c.nom   AS client_nom,
        rh.id   AS reservation_numero,
        f.nom   AS formation_nom,
        u.nom_complet AS responsable_nom
    FROM journal_caisse j
    JOIN modes_paiement mp ON mp.id = j.mode_paiement_id
    LEFT JOIN ventes v ON v.id = j.vente_id
    LEFT JOIN clients c ON c.id = v.client_id
    LEFT JOIN reservations_hotel rh ON rh.id = j.reservation_id
    LEFT JOIN inscriptions_formation inf ON inf.id = j.inscription_formation_id
    LEFT JOIN formations f ON f.id = inf.formation_id
    LEFT JOIN utilisateurs u ON u.id = j.responsable_encaissement_id
    $whereSql
    ORDER BY j.date_operation DESC, j.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$operations = $stmt->fetchAll();

// Totaux (on ignore les opérations annulées)
$totalRecettes = 0;
$totalDepenses = 0;

foreach ($operations as $op) {
    if (!empty($op['est_annule']) && (int)$op['est_annule'] === 1) {
        continue;
    }
    if ($op['sens'] === 'RECETTE') {
        $totalRecettes += (float)$op['montant'];
    } elseif ($op['sens'] === 'DEPENSE') {
        $totalDepenses += (float)$op['montant'];
    }
}
$solde = $totalRecettes - $totalDepenses;

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Journal de caisse</h1>
        <div class="d-flex gap-2">
            <a href="<?= url_for('caisse/reconciliation.php') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-clipboard-check me-1"></i> Réconciliation
            </a>
            <a href="<?= url_for('caisse/export_excel.php?date_debut=' . urlencode($dateDebut) . '&date_fin=' . urlencode($dateFin)) ?>" 
               class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Exporter Excel
            </a>
            <a href="<?= url_for('caisse/ventes_encaissements.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-check me-1"></i> Ventes vs encaissements
            </a>
        </div>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success py-2">
            <i class="bi bi-check-circle me-1"></i>
            <?= htmlspecialchars($flashSuccess) ?>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger py-2">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?= htmlspecialchars($flashError) ?>
        </div>
    <?php endif; ?>

    <!-- Résumé journalier (hors opérations annulées) -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-success-subtle">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Total recettes</div>
                    <div class="fs-4 fw-semibold">
                        <?= number_format($totalRecettes, 0, ',', ' ') ?> FCFA
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger-subtle">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Total dépenses</div>
                    <div class="fs-4 fw-semibold">
                        <?= number_format($totalDepenses, 0, ',', ' ') ?> FCFA
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-primary-subtle">
                <div class="card-body">
                    <div class="text-muted small text-uppercase mb-1">Solde</div>
                    <div class="fs-4 fw-semibold">
                        <?= number_format($solde, 0, ',', ' ') ?> FCFA
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-md-3">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control"
                           value="<?= htmlspecialchars($dateDebut) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control"
                           value="<?= htmlspecialchars($dateFin) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Mode de paiement</label>
                    <select name="mode_id" class="form-select">
                        <option value="0">Tous</option>
                        <?php foreach ($modesPaiement as $mp): ?>
                            <option value="<?= (int)$mp['id'] ?>"
                                <?= $modeFiltre === (int)$mp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mp['code']) ?> – <?= htmlspecialchars($mp['libelle']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary mt-4">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('caisse/journal.php') ?>" class="btn btn-outline-secondary mt-4">
                        Réinitialiser
                    </a>
                    <a href="?export=csv&date_debut=<?= htmlspecialchars($dateDebut) ?>&date_fin=<?= htmlspecialchars($dateFin) ?>&mode_id=<?= (int)$modeFiltre ?>" 
                       class="btn btn-outline-success mt-4">
                        <i class="bi bi-download me-1"></i> Export CSV
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Formulaire ajout opération -->
    <?php if ($peutEcrire): ?>
        <div class="card mb-3">
            <div class="card-header">
                <strong>Nouvelle opération de caisse</strong>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small">Date opération</label>
                            <input type="date" name="date_operation" class="form-control"
                                   value="<?= htmlspecialchars($today) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Sens</label>
                            <select name="sens" class="form-select" required>
                                <option value="RECETTE">RECETTE</option>
                                <option value="DEPENSE">DEPENSE</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Montant (FCFA)</label>
                            <input type="number" step="0.01" name="montant" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Mode de paiement</label>
                            <select name="mode_paiement_id" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($modesPaiement as $mp): ?>
                                    <option value="<?= (int)$mp['id'] ?>">
                                        <?= htmlspecialchars($mp['code']) ?> – <?= htmlspecialchars($mp['libelle']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small">Nature / Libellé</label>
                            <input type="text" name="nature_operation" class="form-control"
                                   placeholder="Ex : Encaissement facture, Règlement fournisseur...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">N° pièce (facultatif)</label>
                            <input type="text" name="numero_piece" class="form-control"
                                   placeholder="N° facture, reçu...">
                        </div>

                        <div class="col-md-5">
                            <label class="form-label small">Lien (optionnel)</label>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <input type="number" name="vente_id" class="form-control"
                                           placeholder="ID vente">
                                </div>
                                <div class="col-md-4">
                                    <input type="number" name="reservation_id" class="form-control"
                                           placeholder="ID réservation hôtel">
                                </div>
                                <div class="col-md-4">
                                    <input type="number" name="inscription_formation_id" class="form-control"
                                           placeholder="ID inscription formation">
                                </div>
                            </div>
                            <small class="text-muted">
                                Renseigner l’ID correspondant si l'opération est liée à une vente, une réservation ou une inscription.
                            </small>
                        </div>

                        <div class="col-12">
                            <label class="form-label small">Observations</label>
                            <textarea name="observations" class="form-control" rows="2"
                                      placeholder="Note interne (facultatif)"></textarea>
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btnSuccess">
                                <i class="bi bi-check-circle me-1"></i> Enregistrer l'opération
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Liste des opérations -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($operations)): ?>
                <p class="text-muted mb-0">Aucune opération trouvée pour les filtres sélectionnés.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Pièce</th>
                            <th>Nature</th>
                            <th>Mode</th>
                            <th class="text-center">Sens</th>
                            <th class="text-end">Montant</th>
                            <th>Caissier</th>
                            <th>Lien</th>
                            <th class="text-center">Statut</th>
                            <th>Observations</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($operations as $op): ?>
                            <tr>
                                <td><?= htmlspecialchars($op['date_operation']) ?></td>
                                <td><?= htmlspecialchars($op['numero_piece'] ?? '') ?></td>
                                <td><?= htmlspecialchars($op['nature_operation'] ?? '') ?></td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary">
                                        <?= htmlspecialchars($op['mode_code']) ?>
                                    </span>
                                    <span class="text-muted small d-block">
                                        <?= htmlspecialchars($op['mode_libelle']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($op['sens'] === 'RECETTE'): ?>
                                        <span class="badge bg-success-subtle text-success">RECETTE</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger">DEPENSE</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?= number_format((float)$op['montant'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td>
                                    <?= htmlspecialchars($op['responsable_nom'] ?? '') ?>
                                </td>
                                <td>
                                    <?php if (!empty($op['vente_id'])): ?>
                                        <div class="small">
                                            Vente n° <?= htmlspecialchars($op['vente_numero'] ?? $op['vente_id']) ?>
                                            <?php if (!empty($op['client_nom'])): ?>
                                                <br><span class="text-muted">Client : <?= htmlspecialchars($op['client_nom']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif (!empty($op['reservation_id'])): ?>
                                        <div class="small">
                                            Réservation hôtel #<?= (int)$op['reservation_id'] ?>
                                        </div>
                                    <?php elseif (!empty($op['inscription_formation_id'])): ?>
                                        <div class="small">
                                            Inscription formation #<?= (int)$op['inscription_formation_id'] ?>
                                            <?php if (!empty($op['formation_nom'])): ?>
                                                <br><span class="text-muted"><?= htmlspecialchars($op['formation_nom']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($op['est_annule']) && (int)$op['est_annule'] === 1): ?>
                                        <span class="badge bg-danger-subtle text-danger">Annulée</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success">Valide</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= nl2br(htmlspecialchars($op['observations'] ?? '')) ?></td>
                                <td class="text-end">
                                    <?php if ($peutEcrire && (empty($op['est_annule']) || (int)$op['est_annule'] === 0)): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                                            <input type="hidden" name="annuler_operation_id" value="<?= (int)$op['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Confirmer l\'annulation de cette opération ?');">
                                                <i class="bi bi-x-circle me-1"></i> Annuler
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
