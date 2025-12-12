<?php
// coordination/ordres_preparation.php - Gestion des demandes de préparation (ordres de sortie)
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_LIRE');

global $pdo;

$filtre_statut = $_GET['statut'] ?? 'TOUS';
$filtre_type = $_GET['type'] ?? 'TOUS';

// Construction de la requête
$sql = "
    SELECT 
        op.*,
        v.numero as vente_numero,
        v.date_vente,
        c.nom as client_nom,
        c.telephone as client_telephone,
        u.nom_complet as demandeur_nom,
        m.nom_complet as preparateur_nom,
        (SELECT COUNT(*) FROM ventes_lignes WHERE vente_id = op.vente_id) as nb_lignes
    FROM ordres_preparation op
    LEFT JOIN ventes v ON op.vente_id = v.id
    LEFT JOIN clients c ON op.client_id = c.id
    LEFT JOIN utilisateurs u ON op.commercial_responsable_id = u.id
    LEFT JOIN utilisateurs m ON op.magasinier_id = m.id
    WHERE 1=1
";

$params = [];

if ($filtre_statut !== 'TOUS') {
    $sql .= " AND op.statut = :statut";
    $params['statut'] = $filtre_statut;
}

if ($filtre_type !== 'TOUS') {
    $sql .= " AND op.type_commande = :type";
    $params['type'] = $filtre_type;
}

$sql .= " ORDER BY op.date_ordre DESC, op.date_creation DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ordres = $stmt->fetchAll();

// Statistiques
$stmtStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'EN_ATTENTE' THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN statut = 'EN_PREPARATION' THEN 1 ELSE 0 END) as en_cours,
        SUM(CASE WHEN statut = 'PRET' THEN 1 ELSE 0 END) as prets,
        SUM(CASE WHEN statut = 'LIVRE' THEN 1 ELSE 0 END) as livres,
        SUM(CASE WHEN priorite = 'URGENTE' AND statut IN ('EN_ATTENTE', 'EN_PREPARATION') THEN 1 ELSE 0 END) as urgents
    FROM ordres_preparation
");
$stats = $stmtStats->fetch();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            <i class="bi bi-box-seam"></i> Ordres de Préparation (Demandes magasin)
        </h1>
        
        <?php if (peut('VENTES_CREER')): ?>
            <a href="ordres_preparation_edit.php" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle"></i> Nouvelle demande
            </a>
        <?php endif; ?>
    </div>

    <!-- Statistiques -->
    <div class="row g-2 mb-3">
        <div class="col-md-2">
            <div class="card border-primary kms-card-hover">
                <div class="card-body p-2">
                    <div class="text-muted small">Total</div>
                    <div class="fs-5 fw-bold"><?= $stats['total'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-warning kms-card-hover">
                <div class="card-body p-2">
                    <div class="text-muted small">En attente</div>
                    <div class="fs-5 fw-bold text-warning"><?= $stats['en_attente'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-info kms-card-hover">
                <div class="card-body p-2">
                    <div class="text-muted small">En préparation</div>
                    <div class="fs-5 fw-bold text-info"><?= $stats['en_cours'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-success kms-card-hover">
                <div class="card-body p-2">
                    <div class="text-muted small">Prêts</div>
                    <div class="fs-5 fw-bold text-success"><?= $stats['prets'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card kms-card-hover">
                <div class="card-body p-2">
                    <div class="text-muted small">Livrés</div>
                    <div class="fs-5 fw-bold"><?= $stats['livres'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-danger kms-card-hover">
                <div class="card-body p-2">
                    <div class="text-muted small">⚠️ Urgents</div>
                    <div class="fs-5 fw-bold text-danger"><?= $stats['urgents'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-3">
        <div class="card-body p-2">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-0">Statut</label>
                    <select name="statut" class="form-select form-select-sm">
                        <option value="TOUS" <?= $filtre_statut === 'TOUS' ? 'selected' : '' ?>>Tous</option>
                        <option value="EN_ATTENTE" <?= $filtre_statut === 'EN_ATTENTE' ? 'selected' : '' ?>>En attente</option>
                        <option value="EN_PREPARATION" <?= $filtre_statut === 'EN_PREPARATION' ? 'selected' : '' ?>>En préparation</option>
                        <option value="PRET" <?= $filtre_statut === 'PRET' ? 'selected' : '' ?>>Prêt</option>
                        <option value="LIVRE" <?= $filtre_statut === 'LIVRE' ? 'selected' : '' ?>>Livré</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label small mb-0">Type demande</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="TOUS" <?= $filtre_type === 'TOUS' ? 'selected' : '' ?>>Tous</option>
                        <option value="NORMALE" <?= $filtre_type === 'NORMALE' ? 'selected' : '' ?>>Normale</option>
                        <option value="URGENTE" <?= $filtre_type === 'URGENTE' ? 'selected' : '' ?>>Urgente</option>
                        <option value="LIVRAISON" <?= $filtre_type === 'LIVRAISON' ? 'selected' : '' ?>>Livraison</option>
                        <option value="ENLEVER" <?= $filtre_type === 'ENLEVER' ? 'selected' : '' ?>>À enlever</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-funnel"></i> Filtrer
                    </button>
                </div>
                
                <div class="col-md-2">
                    <a href="ordres_preparation.php" class="btn btn-secondary btn-sm w-100">
                        <i class="bi bi-x-circle"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-list-ul"></i> Ordres de préparation (<?= count($ordres) ?>)
        </div>
        <div class="card-body p-0">
            <?php if (empty($ordres)): ?>
                <div class="text-center p-4 text-muted">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2">Aucun ordre de préparation trouvé</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Numéro ordre</th>
                                <th>Date/Heure</th>
                                <th>Type</th>
                                <th>Vente</th>
                                <th>Client</th>
                                <th>Demandeur</th>
                                <th>Préparateur</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ordres as $ordre): ?>
                                <?php
                                // Badge statut
                                $badge_statut = match($ordre['statut']) {
                                    'EN_ATTENTE' => 'warning',
                                    'EN_PREPARATION' => 'info',
                                    'PRET' => 'success',
                                    'LIVRE' => 'secondary',
                                    default => 'light'
                                };
                                
                                // Badge type
                                $badge_type = match($ordre['priorite']) {
                                    'URGENTE' => 'danger',
                                    'LIVRAISON' => 'primary',
                                    'ENLEVER' => 'warning',
                                    default => 'light'
                                };
                                ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?= htmlspecialchars($ordre['numero_ordre']) ?></strong>
                                        <?php if ($ordre['priorite'] === 'URGENTE'): ?>
                                            <i class="bi bi-exclamation-triangle-fill text-danger" title="Urgent"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= date('d/m/Y', strtotime($ordre['date_ordre'])) ?></div>
                                        <small class="text-muted"><?= date('H:i', strtotime($ordre['date_creation'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $badge_type ?>">
                                            <?= htmlspecialchars($ordre['priorite']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= url_for('ventes/edit.php?id=' . $ordre['vente_id']) ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($ordre['vente_numero']) ?>
                                        </a>
                                        <div>
                                            <small class="text-muted"><?= $ordre['nb_lignes'] ?> ligne(s)</small>
                                        </div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($ordre['client_nom']) ?>
                                        <div><small class="text-muted"><?= htmlspecialchars($ordre['client_telephone'] ?? '') ?></small></div>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($ordre['demandeur_nom']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($ordre['magasinier_id']): ?>
                                            <small><?= htmlspecialchars($ordre['preparateur_nom']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $badge_statut ?>">
                                            <?= htmlspecialchars($ordre['statut']) ?>
                                        </span>
                                        <?php if (!empty($ordre['date_preparation_effectuee'])): ?>
                                            <div><small class="text-muted">Prêt le <?= date('d/m/Y', strtotime($ordre['date_preparation_effectuee'])) ?></small></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="ordres_preparation_edit.php?id=<?= $ordre['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="Voir détails">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            
                                            <?php if (peut('VENTES_MODIFIER') && $ordre['statut'] !== 'LIVRE'): ?>
                                                <a href="ordres_preparation_statut.php?id=<?= $ordre['id'] ?>&action=suivant" 
                                                   class="btn btn-sm btn-outline-success"
                                                   onclick="return confirm('Passer au statut suivant ?')"
                                                   title="Passer au statut suivant">
                                                    <i class="bi bi-arrow-right-circle"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
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
