<?php
// achats/edit.php
require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../lib/stock.php';
require_once __DIR__ . '/../lib/caisse.php';

exigerConnexion();
exigerPermission('ACHATS_GERER');

global $pdo;

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

// Produits (pour les lignes)
$stmt = $pdo->query("
    SELECT id, code_produit, designation
    FROM produits
    WHERE actif = 1
    ORDER BY designation
");
$produits = $stmt->fetchAll();
$produitsById = [];
foreach ($produits as $p) {
    $produitsById[(int)$p['id']] = $p;
}

// Valeurs par défaut
$data = [
    'numero'              => '',
    'date_achat'          => date('Y-m-d'),
    'fournisseur_nom'     => '',
    'fournisseur_contact' => '',
    'statut'              => 'EN_COURS',
    'commentaires'        => '',
];

$lignes = [];
$montant_total_ht  = 0.0;
$montant_total_ttc = 0.0;

if ($isEdit && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Chargement de l'achat
    $stmt = $pdo->prepare("SELECT * FROM achats WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $achat = $stmt->fetch();

    if (!$achat) {
        $_SESSION['flash_error'] = "Achat introuvable.";
        header('Location: ' . url_for('achats/list.php'));
        exit;
    }

    $data = [
        'numero'              => $achat['numero'],
        'date_achat'          => $achat['date_achat'],
        'fournisseur_nom'     => $achat['fournisseur_nom'] ?? '',
        'fournisseur_contact' => $achat['fournisseur_contact'] ?? '',
        'statut'              => $achat['statut'],
        'commentaires'        => $achat['commentaires'] ?? '',
    ];
    $montant_total_ht  = (float)$achat['montant_total_ht'];
    $montant_total_ttc = (float)$achat['montant_total_ttc'];

    // Lignes d'achat
    $stmt = $pdo->prepare("
        SELECT al.*, p.code_produit, p.designation
        FROM achats_lignes al
        JOIN produits p ON p.id = al.produit_id
        WHERE al.achat_id = :id
        ORDER BY al.id
    ");
    $stmt->execute(['id' => $id]);
    $lignes = $stmt->fetchAll();
} elseif (!$isEdit) {
    // Au moins une ligne vide par défaut
    $lignes = [
        ['produit_id' => '', 'quantite' => '', 'prix_unitaire' => '', 'remise' => '', 'montant_ligne_ht' => ''],
    ];
}

$errors = [];

// POST : création / mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf($_POST['csrf_token'] ?? '');

    $id     = (int)($_POST['id'] ?? 0);
    $isEdit = $id > 0;

    $data['numero']              = trim($_POST['numero'] ?? '');
    $data['date_achat']          = $_POST['date_achat'] ?? date('Y-m-d');
    $data['fournisseur_nom']     = trim($_POST['fournisseur_nom'] ?? '');
    $data['fournisseur_contact'] = trim($_POST['fournisseur_contact'] ?? '');
    $data['statut']              = $_POST['statut'] ?? 'EN_COURS';
    $data['commentaires']        = trim($_POST['commentaires'] ?? '');

    // Lignes
    $produitIds = $_POST['produit_id'] ?? [];
    $qtes       = $_POST['quantite'] ?? [];
    $prixUnit   = $_POST['prix_unitaire'] ?? [];
    $remises    = $_POST['remise'] ?? [];

    $lignes = [];
    $montant_total_ht = 0.0;

    foreach ($produitIds as $idx => $prodIdRaw) {
        $prodId = (int)$prodIdRaw;
        $q      = (float)str_replace(',', '.', $qtes[$idx] ?? '0');
        $pu     = (float)str_replace(',', '.', $prixUnit[$idx] ?? '0');
        $rem    = (float)str_replace(',', '.', $remises[$idx] ?? '0');

        if ($prodId <= 0 || $q <= 0 || $pu <= 0) {
            continue;
        }

        $montantBrut = $pu * $q;
        $montantLigneHT = max(0, $montantBrut - $rem);

        $lignes[] = [
            'produit_id'       => $prodId,
            'quantite'         => $q,
            'prix_unitaire'    => $pu,
            'remise'           => $rem,
            'montant_ligne_ht' => $montantLigneHT,
        ];

        $montant_total_ht += $montantLigneHT;
    }

    // Validations
    if ($data['date_achat'] === '') {
        $errors[] = "La date d'achat est obligatoire.";
    }
    if ($data['fournisseur_nom'] === '') {
        $errors[] = "Le nom du fournisseur est obligatoire.";
    }
    if (!in_array($data['statut'], ['EN_COURS','VALIDE','ANNULE'], true)) {
        $errors[] = "Statut d'achat invalide.";
    }
    if (empty($lignes)) {
        $errors[] = "L'achat doit contenir au moins une ligne produit.";
    }

    // Ici on suppose pas de TVA détaillée : HT = TTC pour simplifier
    $montant_total_ttc = $montant_total_ht;

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $utilisateur = utilisateurConnecte();
            $userId = (int)($utilisateur['id'] ?? 0);

            if ($data['numero'] === '') {
                $data['numero'] = 'AC-' . date('Ymd-His');
            }

            if ($isEdit) {
                // UPDATE entête achat
                $stmt = $pdo->prepare("
                UPDATE achats
                SET numero = :numero,
                    date_achat = :date_achat,
                    fournisseur_nom = :fournisseur_nom,
                    fournisseur_contact = :fournisseur_contact,
                    montant_total_ht = :mtht,
                    montant_total_ttc = :mtttc,
                    statut = :statut,
                    utilisateur_id = :utilisateur_id,
                    commentaires = :commentaires
                WHERE id = :id
            ");
            $stmt->execute([
                'numero'              => $data['numero'],
                'date_achat'          => $data['date_achat'],
                'fournisseur_nom'     => $data['fournisseur_nom'] ?: null,
                'fournisseur_contact' => $data['fournisseur_contact'] ?: null,
                'mtht'                => $montant_total_ht,
                'mtttc'               => $montant_total_ttc,
                'statut'              => $data['statut'],
                'utilisateur_id'      => $userId ?: null,
                'commentaires'        => $data['commentaires'] ?: null,
                'id'                  => $id,
            ]);

            // Supprimer les anciennes lignes
            $stmt = $pdo->prepare("DELETE FROM achats_lignes WHERE achat_id = :id");
            $stmt->execute(['id' => $id]);

            $achatId = $id;

            } else {
                // INSERT entête achat
                $stmt = $pdo->prepare("
                INSERT INTO achats (
                    numero, date_achat, fournisseur_nom, fournisseur_contact,
                    montant_total_ht, montant_total_ttc, statut, utilisateur_id, commentaires
                ) VALUES (
                    :numero, :date_achat, :fournisseur_nom, :fournisseur_contact,
                    :mtht, :mtttc, :statut, :utilisateur_id, :commentaires
                )
            ");
                $stmt->execute([
                    'numero'              => $data['numero'],
                    'date_achat'          => $data['date_achat'],
                    'fournisseur_nom'     => $data['fournisseur_nom'] ?: null,
                    'fournisseur_contact' => $data['fournisseur_contact'] ?: null,
                    'mtht'                => $montant_total_ht,
                    'mtttc'               => $montant_total_ttc,
                    'statut'              => $data['statut'],
                    'utilisateur_id'      => $userId ?: null,
                    'commentaires'        => $data['commentaires'] ?: null,
                ]);

                $achatId = (int)$pdo->lastInsertId();
            }

            // INSERT lignes
                $stmtL = $pdo->prepare("
                INSERT INTO achats_lignes (
                    achat_id, produit_id, quantite,
                    prix_unitaire, remise, montant_ligne_ht
                ) VALUES (
                    :achat_id, :produit_id, :quantite,
                    :prix_unitaire, :remise, :montant_ligne_ht
                )
            ");
            foreach ($lignes as $lg) {
                $stmtL->execute([
                    'achat_id'         => $achatId,
                    'produit_id'       => $lg['produit_id'],
                    'quantite'         => $lg['quantite'],
                    'prix_unitaire'    => $lg['prix_unitaire'],
                    'remise'           => $lg['remise'],
                    'montant_ligne_ht' => $lg['montant_ligne_ht'],
                ]);
            }

            // 🔗 Synchronisation stock (entrées liées à cet achat)
            stock_synchroniser_achat($pdo, $achatId);

            // Enregistrer écriture en caisse (sortie de trésorerie pour achat)
            // Sens = 'SORTIE' car c'est un paiement / dépense
            try {
                caisse_enregistrer_ecriture(
                    $pdo,
                    'SORTIE',
                    (float)$montant_total_ttc,
                    'ACHAT',
                    $achatId,
                    'Achat ' . $data['numero'],
                    $userId ?: null
                );
            } catch (Throwable $e) {
                // Ne pas bloquer l'enregistrement d'achat si l'écriture caisse échoue,
                // mais loguer l'erreur si nécessaire.
            }

            $pdo->commit();

            $_SESSION['flash_success'] = $isEdit
                ? "Achat mis à jour avec succès."
                : "Achat créé avec succès.";

            header('Location: ' . url_for('achats/list.php'));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = "Erreur lors de l'enregistrement de l'achat : " . $e->getMessage();
        }
    }
}

$csrfToken = getCsrfToken();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            <?= $isEdit ? 'Modifier un achat' : 'Nouvel achat / approvisionnement' ?>
        </h1>
        <a href="<?= url_for('achats/list.php') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Retour à la liste
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <h2 class="h6 mb-2"><i class="bi bi-exclamation-triangle me-1"></i> Erreurs</h2>
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="card">
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int)$id ?>">

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label small">N° achat</label>
                    <input type="text" name="numero" class="form-control"
                           value="<?= htmlspecialchars($data['numero']) ?>"
                           placeholder="Auto si vide (AC-YYYYMMJJ-HHMMSS)">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Date d'achat *</label>
                    <input type="date" name="date_achat" class="form-control"
                           value="<?= htmlspecialchars($data['date_achat']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Statut *</label>
                    <select name="statut" class="form-select" required>
                        <?php foreach (['EN_COURS','VALIDE','ANNULE'] as $s): ?>
                            <option value="<?= $s ?>" <?= $data['statut'] === $s ? 'selected' : '' ?>>
                                <?= $s ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small">Fournisseur *</label>
                    <input type="text" name="fournisseur_nom" class="form-control" required
                           value="<?= htmlspecialchars($data['fournisseur_nom']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Contact fournisseur</label>
                    <input type="text" name="fournisseur_contact" class="form-control"
                           value="<?= htmlspecialchars($data['fournisseur_contact']) ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small">Commentaires (facultatif)</label>
                <textarea name="commentaires" class="form-control" rows="2"><?= htmlspecialchars($data['commentaires']) ?></textarea>
            </div>

            <hr>

            <h2 class="h6 mb-2">Lignes d'achat</h2>
            <p class="text-muted small mb-2">
                Saisis les produits, quantités et prix d'achat. Les montants HT sont calculés à l’enregistrement.
            </p>

            <div class="table-responsive mb-2">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 40%;">Produit</th>
                        <th style="width: 15%;">Qté</th>
                        <th style="width: 20%;">Prix unitaire (achat)</th>
                        <th style="width: 15%;">Remise</th>
                        <th style="width: 10%;">Montant HT (indicatif)</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $nbLignesAffichees = max(count($lignes), 3);
                    for ($i = 0; $i < $nbLignesAffichees; $i++):
                        $lg = $lignes[$i] ?? [
                            'produit_id' => '',
                            'quantite'   => '',
                            'prix_unitaire' => '',
                            'remise'     => '',
                            'montant_ligne_ht' => '',
                        ];
                        $prodId = (int)($lg['produit_id'] ?? 0);
                        $montLigne = $lg['montant_ligne_ht'] ?? '';
                    ?>
                        <tr>
                            <td>
                                <select name="produit_id[]" class="form-select">
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($produits as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>"
                                            <?= $prodId === (int)$p['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['code_produit']) ?> – <?= htmlspecialchars($p['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="quantite[]" class="form-control"
                                       value="<?= htmlspecialchars((string)$lg['quantite']) ?>">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="prix_unitaire[]" class="form-control"
                                       value="<?= htmlspecialchars((string)$lg['prix_unitaire']) ?>">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="remise[]" class="form-control"
                                       value="<?= htmlspecialchars((string)$lg['remise']) ?>">
                            </td>
                            <td>
                                <?php if ($montLigne !== ''): ?>
                                    <?= number_format((float)$montLigne, 0, ',', ' ') ?> FCFA
                                <?php else: ?>
                                    <span class="text-muted small">Calculé à l’enregistrement</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endfor; ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="4" class="text-end fw-semibold">Total HT indicatif</td>
                        <td class="text-end fw-bold">
                            <?= number_format($montant_total_ht, 0, ',', ' ') ?> FCFA
                        </td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= url_for('achats/list.php') ?>" class="btn btn-outline-secondary">Annuler</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>
                <?= $isEdit ? 'Enregistrer l\'achat' : 'Créer l\'achat' ?>
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
