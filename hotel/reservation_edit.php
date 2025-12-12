<?php
// hotel/reservation_edit.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('HOTEL_GERER');

global $pdo;

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

$errors = [];

// Listes
$stmt = $pdo->query("SELECT id, nom FROM clients ORDER BY nom");
$clients = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, code, tarif_nuite FROM chambres WHERE actif = 1 ORDER BY code");
$chambres = $stmt->fetchAll();

$stmt = $pdo->query("SELECT id, libelle FROM modes_paiement ORDER BY libelle");
$modesPaiement = $stmt->fetchAll();

$reservation = [
    'client_id'        => '',
    'chambre_id'       => '',
    'date_debut'       => date('Y-m-d'),
    'date_fin'         => date('Y-m-d'),
    'nb_nuits'         => 1,
    'montant_total'    => 0,
    'statut'           => 'EN_COURS',
    'mode_paiement_id' => null,
];

// Charge la réservation si édition
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM reservations_hotel WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        $_SESSION['flash_error'] = "Réservation introuvable.";
        header('Location: ' . url_for('hotel/reservations.php'));
        exit;
    }
}

// Traitement formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifierCsrf($_POST['csrf_token'] ?? '');

    $clientId  = (int)($_POST['client_id'] ?? 0);
    $chambreId = (int)($_POST['chambre_id'] ?? 0);
    $dateDeb   = $_POST['date_debut'] ?? '';
    $dateFin   = $_POST['date_fin'] ?? '';
    $statut    = $_POST['statut'] ?? 'EN_COURS';
    $modePaiementId = isset($_POST['mode_paiement_id']) && $_POST['mode_paiement_id'] !== ''
        ? (int)$_POST['mode_paiement_id']
        : null;
    $enregistrerCaisse = isset($_POST['enregistrer_caisse']);

    if ($clientId <= 0) {
        $errors[] = "Le client est obligatoire.";
    }
    if ($chambreId <= 0) {
        $errors[] = "La chambre est obligatoire.";
    }
    if ($dateDeb === '' || $dateFin === '') {
        $errors[] = "Les dates de début et de fin sont obligatoires.";
    }

    $nbNuits = 1;
    if ($dateDeb && $dateFin) {
        try {
            $d1 = new DateTime($dateDeb);
            $d2 = new DateTime($dateFin);
            if ($d2 < $d1) {
                $errors[] = "La date de fin doit être supérieure ou égale à la date de début.";
            } else {
                $nbNuits = (int)$d1->diff($d2)->days;
                if ($nbNuits <= 0) {
                    $nbNuits = 1;
                }
            }
        } catch (Exception $e) {
            $errors[] = "Format de date invalide.";
        }
    }

    // Récup tarif nuité de la chambre
    $tarifNuite = 0;
    if ($chambreId > 0) {
        $stmt = $pdo->prepare("SELECT tarif_nuite FROM chambres WHERE id = :id");
        $stmt->execute(['id' => $chambreId]);
        $rowTarif = $stmt->fetch();
        if ($rowTarif) {
            $tarifNuite = (float)$rowTarif['tarif_nuite'];
        }
    }
    $montantTotal = $tarifNuite * $nbNuits;

    if (empty($errors)) {
        if ($isEdit) {
            $stmt = $pdo->prepare("
                UPDATE reservations_hotel
                SET client_id = :client_id,
                    chambre_id = :chambre_id,
                    date_debut = :date_debut,
                    date_fin = :date_fin,
                    nb_nuits = :nb_nuits,
                    montant_total = :montant_total,
                    statut = :statut,
                    mode_paiement_id = :mode_paiement_id,
                    concierge_id = :concierge_id
                WHERE id = :id
            ");
            $stmt->execute([
                'client_id'        => $clientId,
                'chambre_id'       => $chambreId,
                'date_debut'       => $dateDeb,
                'date_fin'         => $dateFin,
                'nb_nuits'         => $nbNuits,
                'montant_total'    => $montantTotal,
                'statut'           => $statut,
                'mode_paiement_id' => $modePaiementId,
                'concierge_id'     => utilisateurConnecte()['id'] ?? null,
                'id'               => $id,
            ]);
            $reservationId = $id;
            $_SESSION['flash_success'] = "Réservation mise à jour avec succès.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO reservations_hotel
                    (date_reservation, client_id, chambre_id, date_debut, date_fin,
                     nb_nuits, montant_total, statut, mode_paiement_id, concierge_id)
                VALUES
                    (NOW(), :client_id, :chambre_id, :date_debut, :date_fin,
                     :nb_nuits, :montant_total, :statut, :mode_paiement_id, :concierge_id)
            ");
            $stmt->execute([
                'client_id'        => $clientId,
                'chambre_id'       => $chambreId,
                'date_debut'       => $dateDeb,
                'date_fin'         => $dateFin,
                'nb_nuits'         => $nbNuits,
                'montant_total'    => $montantTotal,
                'statut'           => $statut,
                'mode_paiement_id' => $modePaiementId,
                'concierge_id'     => utilisateurConnecte()['id'] ?? null,
            ]);
            $reservationId = (int)$pdo->lastInsertId();
            $_SESSION['flash_success'] = "Réservation créée avec succès.";
        }

        // Enregistrement caisse optionnel
        if ($enregistrerCaisse && $modePaiementId && $montantTotal > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO journal_caisse
                    (date_operation, numero_piece, nature_operation, sens,
                     montant, mode_paiement_id, reservation_id,
                     responsable_encaissement_id, observations)
                VALUES
                    (CURDATE(), :num_piece, :nature, 'RECETTE',
                     :montant, :mode_paiement_id, :reservation_id,
                     :resp_id, :obs)
            ");
            $stmt->execute([
                'num_piece'        => 'RES-' . $reservationId,
                'nature'           => 'Encaissement réservation hôtel',
                'montant'          => $montantTotal,
                'mode_paiement_id' => $modePaiementId,
                'reservation_id'   => $reservationId,
                'resp_id'          => utilisateurConnecte()['id'] ?? null,
                'obs'              => '',
            ]);
        }

        header('Location: ' . url_for('hotel/reservations.php'));
        exit;
    }

    // Réinjecter dans $reservation pour réaffichage
    $reservation['client_id']        = $clientId;
    $reservation['chambre_id']       = $chambreId;
    $reservation['date_debut']       = $dateDeb;
    $reservation['date_fin']         = $dateFin;
    $reservation['nb_nuits']         = $nbNuits;
    $reservation['montant_total']    = $montantTotal;
    $reservation['statut']           = $statut;
    $reservation['mode_paiement_id'] = $modePaiementId;
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            <?= $isEdit ? 'Modifier la réservation' : 'Nouvelle réservation' ?>
        </h1>
        <a href="<?= url_for('hotel/reservations.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Retour à la liste
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2">
            <ul class="mb-0 small">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Client</label>
                        <select name="client_id" class="form-select" required>
                            <option value="">Sélectionner...</option>
                            <?php foreach ($clients as $cl): ?>
                                <option value="<?= (int)$cl['id'] ?>"
                                    <?= (int)$reservation['client_id'] === (int)$cl['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cl['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Chambre</label>
                        <select name="chambre_id" class="form-select" required>
                            <option value="">Sélectionner...</option>
                            <?php foreach ($chambres as $ch): ?>
                                <option value="<?= (int)$ch['id'] ?>"
                                    <?= (int)$reservation['chambre_id'] === (int)$ch['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ch['code']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Du</label>
                        <input type="date" name="date_debut" class="form-control"
                               value="<?= htmlspecialchars($reservation['date_debut']) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Au</label>
                        <input type="date" name="date_fin" class="form-control"
                               value="<?= htmlspecialchars($reservation['date_fin']) ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Statut</label>
                        <select name="statut" class="form-select">
                            <?php foreach (['EN_COURS','TERMINEE','ANNULEE'] as $s): ?>
                                <option value="<?= $s ?>" <?= $reservation['statut'] === $s ? 'selected' : '' ?>>
                                    <?= $s ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Mode de paiement</label>
                        <select name="mode_paiement_id" class="form-select">
                            <option value="">Non renseigné</option>
                            <?php foreach ($modesPaiement as $mp): ?>
                                <option value="<?= (int)$mp['id'] ?>"
                                    <?= (int)($reservation['mode_paiement_id'] ?? 0) === (int)$mp['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mp['libelle']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">
                            Utilisé si tu enregistres l’encaissement dans la caisse ci-dessous.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Nombre de nuits</label>
                        <input type="number" class="form-control"
                               id="nb_nuits_display"
                               value="<?= (int)$reservation['nb_nuits'] ?>"
                               disabled>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Montant total (estimé)</label>
                        <input type="text" class="form-control"
                               id="montant_total_display"
                               value="<?= number_format((float)$reservation['montant_total'], 0, ',', ' ') ?> FCFA"
                               disabled>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="enregistrer_caisse" name="enregistrer_caisse">
                            <label class="form-check-label" for="enregistrer_caisse">
                                Enregistrer immédiatement l’encaissement dans la caisse
                            </label>
                        </div>
                        <div class="form-text small">
                            Si cochée, une ligne de recette sera ajoutée au journal de caisse pour le montant total.
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-3">
                    <i class="bi bi-save me-1"></i> Enregistrer
                </button>
            </form>
        </div>
    </div>
</div>

<?php
// Construction d’un tableau [id_chambre => tarif_nuite] pour le JS
$tarifsParChambre = [];
foreach ($chambres as $ch) {
    $tarifsParChambre[(int)$ch['id']] = (float)$ch['tarif_nuite'];
}
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chambresTarifs = <?= json_encode($tarifsParChambre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const dateDebInput   = document.querySelector('input[name="date_debut"]');
    const dateFinInput   = document.querySelector('input[name="date_fin"]');
    const chambreSelect  = document.querySelector('select[name="chambre_id"]');
    const nbNuitsInput   = document.getElementById('nb_nuits_display');
    const montantInput   = document.getElementById('montant_total_display');

    function parseDate(str) {
        if (!str) return null;
        const parts = str.split('-');
        if (parts.length !== 3) return null;
        const year  = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1;
        const day   = parseInt(parts[2], 10);
        return new Date(year, month, day);
    }

    function recalc() {
        const d1 = parseDate(dateDebInput.value);
        const d2 = parseDate(dateFinInput.value);

        let nbNuits = 1;
        if (d1 && d2) {
            const diffMs = d2 - d1;
            if (diffMs > 0) {
                nbNuits = Math.round(diffMs / (1000 * 60 * 60 * 24));
            }
        }
        if (!Number.isFinite(nbNuits) || nbNuits < 1) {
            nbNuits = 1;
        }
        if (nbNuitsInput) {
            nbNuitsInput.value = nbNuits;
        }

        const chambreId = chambreSelect.value;
        const tarif     = chambresTarifs[chambreId] ? parseFloat(chambresTarifs[chambreId]) : 0;
        const montant   = tarif * nbNuits;

        if (montantInput) {
            const formatted = new Intl.NumberFormat('fr-FR', {
                maximumFractionDigits: 0
            }).format(isNaN(montant) ? 0 : montant) + ' FCFA';
            montantInput.value = formatted;
        }
    }

    if (dateDebInput)  dateDebInput.addEventListener('change', recalc);
    if (dateFinInput)  dateFinInput.addEventListener('change', recalc);
    if (chambreSelect) chambreSelect.addEventListener('change', recalc);

    // Initialisation au chargement
    recalc();
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
