<?php
// clients/list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CLIENTS_LIRE');

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';

global $pdo;

// Récup types client pour filtre
$stmtTypes = $pdo->query("SELECT id, code, libelle FROM types_client ORDER BY code");
$typesClient = $stmtTypes->fetchAll();

// Filtres
$typeId = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;
$statut = trim($_GET['statut'] ?? '');
$q      = trim($_GET['q'] ?? '');
$where  = [];
$params = [];

if ($typeId > 0) {
    $where[] = "c.type_client_id = :type_id";
    $params['type_id'] = $typeId;
}
if ($statut !== '') {
    $where[] = "c.statut = :statut";
    $params['statut'] = $statut;
}
if ($q !== '') {
    $where[] = "(c.nom LIKE :q OR c.telephone LIKE :q2 OR c.email LIKE :q3)";
    $params['q']  = '%' . $q . '%';
    $params['q2'] = '%' . $q . '%';
    $params['q3'] = '%' . $q . '%';
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

$sql = "
    SELECT 
        c.*,
        t.code AS type_code,
        t.libelle AS type_libelle
    FROM clients c
    JOIN types_client t ON t.id = c.type_client_id
    $whereSql
    ORDER BY c.date_creation DESC, c.nom ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$peutCreer = in_array('CLIENTS_CREER', $_SESSION['permissions'] ?? [], true);
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
?>

<div class="container-fluid">
    <div class="list-page-header d-flex justify-content-between align-items-center">
        <h1 class="list-page-title h3">
            <i class="bi bi-people-fill"></i>
            Clients & prospects
            <span class="count-badge ms-2"><?= count($clients) ?></span>
        </h1>
        <?php if ($peutCreer): ?>
            <a href="<?= url_for('clients/edit.php') ?>" class="btn btn-primary btn-add-new">
                <i class="bi bi-person-plus me-2"></i> Nouveau client
            </a>
        <?php endif; ?>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success alert-modern">
            <i class="bi bi-check-circle-fill"></i>
            <span><?= htmlspecialchars($flashSuccess) ?></span>
        </div>
    <?php endif; ?>

    <div class="card filter-card">
        <div class="card-body">
            <form class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Recherche</label>
                    <input type="text" name="q" class="form-control"
                           placeholder="Nom, téléphone, email..."
                           value="<?= htmlspecialchars($q) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Type</label>
                    <select name="type_id" class="form-select">
                        <option value="0">Tous les types</option>
                        <?php foreach ($typesClient as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"
                                <?= $typeId === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['libelle']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="">Tous les statuts</option>
                        <?php
                        $statuts = ['PROSPECT','CLIENT','APPRENANT','HOTE'];
                        foreach ($statuts as $s):
                        ?>
                            <option value="<?= $s ?>" <?= $statut === $s ? 'selected' : '' ?>>
                                <?= $s ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-filter">
                        <i class="bi bi-search me-1"></i> Filtrer
                    </button>
                    <a href="<?= url_for('clients/list.php') ?>" class="btn btn-outline-secondary btn-filter">
                        <i class="bi bi-arrow-clockwise me-1"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card data-table-card">
        <div class="card-body">
            <?php if (empty($clients)): ?>
                <div class="empty-state">
                    <i class="bi bi-people"></i>
                    <h5>Aucun client trouvé</h5>
                    <p>Aucun client ne correspond aux critères de recherche.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table modern-table">
                        <thead class="table-light">
                        <tr>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Téléphone</th>
                            <th>Email</th>
                            <th>Source</th>
                            <th>Date création</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($clients as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['nom']) ?></td>
                                <td>
                                    <span class="modern-badge badge-status-primary">
                                        <i class="bi bi-tag-fill"></i>
                                        <?= htmlspecialchars($c['type_code']) ?>
                                    </span>
                                    <div class="text-muted small mt-1">
                                        <?= htmlspecialchars($c['type_libelle']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $statutsClients = [
                                        'PROSPECT' => ['label' => 'Prospect', 'color' => 'warning', 'icon' => 'bi-person-plus'],
                                        'CLIENT' => ['label' => 'Client', 'color' => 'success', 'icon' => 'bi-person-check'],
                                        'APPRENANT' => ['label' => 'Apprenant', 'color' => 'info', 'icon' => 'bi-mortarboard'],
                                        'HOTE' => ['label' => 'Hôte', 'color' => 'primary', 'icon' => 'bi-house-door']
                                    ];
                                    $currentStatut = $statutsClients[$c['statut']] ?? ['label' => $c['statut'], 'color' => 'secondary', 'icon' => 'bi-circle'];
                                    ?>
                                    <button class="btn btn-sm btn-outline-<?= $currentStatut['color'] ?>" 
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalChangerStatut"
                                            data-client-id="<?= (int)$c['id'] ?>"
                                            data-client-statut="<?= htmlspecialchars($c['statut']) ?>"
                                            title="Changer le statut">
                                        <i class="<?= $currentStatut['icon'] ?> me-1"></i>
                                        <?= htmlspecialchars($currentStatut['label']) ?>
                                    </button>
                                </td>
                                <td><?= htmlspecialchars($c['telephone'] ?? '') ?></td>
                                <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($c['source'] ?? '') ?></td>
                                <td><?= htmlspecialchars($c['date_creation']) ?></td>
                                <td class="text-end">
                                    <div class="action-btn-group">
                                        <?php if ($peutCreer): ?>
                                            <a href="<?= url_for('clients/edit.php') . '?id=' . (int)$c['id'] ?>"
                                               class="btn btn-sm btn-outline-primary btn-action">
                                                <i class="bi bi-pencil-square"></i> Modifier
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

<style>
/* Fix pour les dropdowns dans les tables responsives */
.table-responsive {
    overflow: visible !important;
}

.card-body .table-responsive {
    overflow-x: auto;
    overflow-y: visible;
}

/* Assurer que les dropdowns sont au-dessus */
.dropdown-menu {
    z-index: 1050 !important;
}
</style>

<script>
// Test Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    console.log('Bootstrap version:', typeof bootstrap !== 'undefined' ? 'Loaded' : 'NOT LOADED');
    
    // Initialiser manuellement les dropdowns si nécessaire
    const dropdowns = document.querySelectorAll('[data-bs-toggle="dropdown"]');
    console.log('Dropdowns trouvés:', dropdowns.length);
    
    dropdowns.forEach(function(dropdown) {
        dropdown.addEventListener('click', function(e) {
            console.log('Dropdown cliqué', e.target);
        });
    });
});

async function changerStatutClient(clientId, nouveauStatut) {
    try {
        const response = await fetch('<?= url_for("api/changer_statut.php") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                entite: 'client',
                id: clientId,
                statut: nouveauStatut
            })
        });

        const result = await response.json();
        
        if (result.success) {
            // Afficher un message de succès
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
            alertDiv.style.zIndex = '9999';
            alertDiv.innerHTML = `
                <i class="bi bi-check-circle-fill me-2"></i>
                ${result.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            // Recharger après 1 seconde
            setTimeout(() => window.location.reload(), 1000);
        } else {
            alert('Erreur: ' + (result.message || 'Impossible de changer le statut'));
        }
    } catch (error) {
        console.error('Erreur:', error);
        alert('Erreur lors du changement de statut');
    }
}
</script>

<!-- Modal Changer Statut -->
<div class="modal fade" id="modalChangerStatut" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-gear me-2"></i>Changer le statut du client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="list-group" id="statutOptions">
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2" data-statut="PROSPECT">
                        <i class="bi bi-person-plus text-warning"></i>
                        <span>Prospect</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2" data-statut="CLIENT">
                        <i class="bi bi-person-check text-success"></i>
                        <span>Client</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2" data-statut="APPRENANT">
                        <i class="bi bi-mortarboard text-info"></i>
                        <span>Apprenant</span>
                    </button>
                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2" data-statut="HOTE">
                        <i class="bi bi-house-door text-primary"></i>
                        <span>Hôte</span>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            </div>
        </div>
    </div>
</div>

<script>
let clientIdSelection = null;
document.addEventListener('click', function(e){
    const trigger = e.target.closest('[data-bs-target="#modalChangerStatut"]');
    if (trigger) {
        clientIdSelection = parseInt(trigger.getAttribute('data-client-id'), 10);
        const current = trigger.getAttribute('data-client-statut');
        // Highlight current selection
        document.querySelectorAll('#statutOptions .list-group-item').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-statut') === current);
        });
    }
});

document.querySelectorAll('#statutOptions .list-group-item').forEach(btn => {
    btn.addEventListener('click', function(){
        const statut = this.getAttribute('data-statut');
        if (clientIdSelection) {
            changerStatutClient(clientIdSelection, statut);
            const modalEl = document.getElementById('modalChangerStatut');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.hide();
        }
    });
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
