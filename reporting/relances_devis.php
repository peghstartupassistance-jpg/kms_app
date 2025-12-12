<?php
// reporting/relances_devis.php - Système de relances automatiques devis
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('DEVIS_LIRE');

global $pdo;

$today = date('Y-m-d');

// Devis en attente (non acceptés, non refusés, non expirés)
$stmtDevis = $pdo->prepare("
    SELECT 
        d.*,
        c.nom as client_nom,
        c.telephone as client_telephone,
        c.email as client_email,
        u.nom_complet as utilisateur_nom,
        cv.libelle as canal_nom,
        DATEDIFF(d.date_relance, CURDATE()) as jours_restants,
        (SELECT MAX(date_relance) FROM relances_devis WHERE devis_id = d.id) as derniere_relance,
        (SELECT COUNT(*) FROM relances_devis WHERE devis_id = d.id) as nb_relances
    FROM devis d
    INNER JOIN clients c ON d.client_id = c.id
    LEFT JOIN utilisateurs u ON d.utilisateur_id = u.id
    LEFT JOIN canaux_vente cv ON d.canal_vente_id = cv.id
    WHERE d.statut IN ('ENVOYE', 'EN_COURS')
      AND (d.date_relance IS NULL OR d.date_relance >= CURDATE())
    ORDER BY d.date_devis DESC
");
$stmtDevis->execute();
$devis_list = $stmtDevis->fetchAll();

// Statistiques
$devis_a_relancer_urgent = array_filter($devis_list, function($d) {
    return $d['jours_restants'] !== null && $d['jours_restants'] <= 3;
});

$devis_sans_relance = array_filter($devis_list, function($d) {
    return $d['nb_relances'] == 0;
});

$devis_relances_recentes = array_filter($devis_list, function($d) use ($today) {
    if (!$d['derniere_relance']) return false;
    $diff = (strtotime($today) - strtotime($d['derniere_relance'])) / 86400;
    return $diff < 7;
});

// Traitement POST (enregistrer relance)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    verifierCsrf($csrf);
    
    $devis_id = $_POST['devis_id'] ?? null;
    $type_relance = $_POST['type_relance'] ?? 'TELEPHONE';
    $commentaires = $_POST['commentaires'] ?? null;
    $prochaine_action = $_POST['prochaine_action'] ?? null;
    $date_prochaine_action = $_POST['date_prochaine_action'] ?? null;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO relances_devis 
            (devis_id, date_relance, type_relance, utilisateur_id, commentaires, 
             prochaine_action, date_prochaine_action)
            VALUES (?, CURDATE(), ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $devis_id,
            $type_relance,
            $_SESSION['user_id'],
            $commentaires,
            $prochaine_action,
            $date_prochaine_action
        ]);
        
        $_SESSION['flash_success'] = "Relance enregistrée avec succès";
        header('Location: ' . url_for('reporting/relances_devis.php'));
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Erreur : " . $e->getMessage();
    }
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            <i class="bi bi-clock-history"></i> Relances devis
        </h1>
    </div>

    <!-- Statistiques -->
    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <div class="card border-warning kms-card-hover">
                <div class="card-body p-2">
                    <div class="text-muted small">Total devis en attente</div>
                    <div class="fs-4 fw-bold"><?= count($devis_list) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger kms-card-hover">
                <div class="card-body p-2">
                    <div class="text-muted small">⚠️ Urgent (≤ 3 jours)</div>
                    <div class="fs-4 fw-bold text-danger"><?= count($devis_a_relancer_urgent) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-secondary kms-card-hover">
                <div class="card-body p-2">
                    <div class="text-muted small">Sans relance</div>
                    <div class="fs-4 fw-bold text-secondary"><?= count($devis_sans_relance) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success kms-card-hover">
                <div class="card-body p-2">
                    <div class="text-muted small">Relancés cette semaine</div>
                    <div class="fs-4 fw-bold text-success"><?= count($devis_relances_recentes) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des devis -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-list-ul"></i> Devis à relancer (<?= count($devis_list) ?>)
        </div>
        <div class="card-body p-0">
            <?php if (empty($devis_list)): ?>
                <div class="text-center p-4 text-muted">
                    <i class="bi bi-check-circle fs-1"></i>
                    <p class="mt-2">Tous les devis ont été traités !</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Devis</th>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Montant</th>
                                <th>Canal</th>
                                <th>Validité</th>
                                <th>Relances</th>
                                <th>Dernière relance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($devis_list as $d): ?>
                                <?php
                                $urgence = '';
                                $row_class = '';
                                if ($d['jours_restants'] !== null) {
                                    if ($d['jours_restants'] <= 3) {
                                        $urgence = 'danger';
                                        $row_class = 'table-danger';
                                    } elseif ($d['jours_restants'] <= 7) {
                                        $urgence = 'warning';
                                        $row_class = 'table-warning';
                                    }
                                }
                                ?>
                                <tr class="<?= $row_class ?>">
                                    <td>
                                        <a href="<?= url_for('devis/edit.php?id=' . $d['id']) ?>" class="fw-bold text-decoration-none">
                                            <?= htmlspecialchars($d['numero']) ?>
                                        </a>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($d['date_devis'])) ?></td>
                                    <td>
                                        <?= htmlspecialchars($d['client_nom']) ?>
                                        <div>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($d['client_telephone']) ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td class="text-end fw-bold">
                                        <?= number_format($d['montant_total_ttc'], 0, ',', ' ') ?> F
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($d['canal_nom']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($d['date_relance']): ?>
                                            <?= date('d/m/Y', strtotime($d['date_relance'])) ?>
                                            <?php if ($d['jours_restants'] !== null): ?>
                                                <div>
                                                    <span class="badge bg-<?= $urgence ?: 'secondary' ?>">
                                                        <?= $d['jours_restants'] ?> jour(s)
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?= $d['nb_relances'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($d['derniere_relance']): ?>
                                            <?= date('d/m/Y', strtotime($d['derniere_relance'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Jamais</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalRelance"
                                                data-devis-id="<?= $d['id'] ?>"
                                                data-devis-numero="<?= htmlspecialchars($d['numero']) ?>"
                                                data-client-nom="<?= htmlspecialchars($d['client_nom']) ?>">
                                            <i class="bi bi-telephone"></i> Relancer
                                        </button>
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

<!-- Modal Relance -->
<div class="modal fade" id="modalRelance" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= genererCsrf() ?>">
                <input type="hidden" name="devis_id" id="modal_devis_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Enregistrer une relance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="alert alert-light">
                        <strong>Devis :</strong> <span id="modal_devis_numero"></span><br>
                        <strong>Client :</strong> <span id="modal_client_nom"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Type de relance</label>
                        <select name="type_relance" class="form-select" required>
                            <option value="TELEPHONE">Téléphone</option>
                            <option value="EMAIL">Email</option>
                            <option value="SMS">SMS</option>
                            <option value="WHATSAPP">WhatsApp</option>
                            <option value="VISITE">Visite</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Commentaires</label>
                        <textarea name="commentaires" class="form-control" rows="3" 
                                  placeholder="Résumé de l'échange, retour client..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Prochaine action</label>
                        <input type="text" name="prochaine_action" class="form-control" 
                               placeholder="Ex: Rappeler pour confirmer">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Date prochaine action</label>
                        <input type="date" name="date_prochaine_action" class="form-control">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>

<script>
// Remplir le modal avec les données du devis
document.addEventListener('DOMContentLoaded', function() {
    const modalRelance = document.getElementById('modalRelance');
    if (modalRelance) {
        modalRelance.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('modal_devis_id').value = button.getAttribute('data-devis-id');
            document.getElementById('modal_devis_numero').textContent = button.getAttribute('data-devis-numero');
            document.getElementById('modal_client_nom').textContent = button.getAttribute('data-client-nom');
        });
    }
});
</script>
