<?php
// utilisateurs/list.php - Gestion des utilisateurs et rôles
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('UTILISATEURS_GERER');

global $pdo;

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

// Récupérer tous les utilisateurs avec leurs rôles et permissions
$stmt = $pdo->query("
    SELECT 
        u.*,
        r.id as role_id,
        r.nom as role_nom,
        r.code as role_code,
        COUNT(DISTINCT rp.permission_id) as nb_permissions,
        GROUP_CONCAT(DISTINCT p.code ORDER BY p.code SEPARATOR ', ') as permissions_list
    FROM utilisateurs u
    LEFT JOIN utilisateur_role ur ON u.id = ur.utilisateur_id
    LEFT JOIN roles r ON ur.role_id = r.id
    LEFT JOIN role_permission rp ON r.id = rp.role_id
    LEFT JOIN permissions p ON rp.permission_id = p.id
    GROUP BY u.id, r.id
    ORDER BY u.actif DESC, r.id, u.nom_complet
");
$utilisateurs = $stmt->fetchAll();

// Statistiques
$stmt = $pdo->query("SELECT COUNT(*) as total FROM utilisateurs WHERE actif = 1");
$total_actifs = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM utilisateurs WHERE actif = 0");
$total_inactifs = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(DISTINCT r.id) as total FROM roles r JOIN utilisateur_role ur ON r.id = ur.role_id");
$roles_utilises = $stmt->fetch()['total'];

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">
                <i class="bi bi-people-gear"></i> Gestion des utilisateurs
            </h1>
            <p class="text-muted mb-0">Gérer les comptes utilisateurs, rôles et permissions</p>
        </div>
        <div>
            <a href="<?= url_for('utilisateurs/edit.php') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nouvel utilisateur
            </a>
        </div>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($flashSuccess) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-muted small">Utilisateurs actifs</div>
                            <div class="fs-3 fw-bold text-success"><?= $total_actifs ?></div>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded p-3">
                            <i class="bi bi-person-check fs-2 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-start border-danger border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-muted small">Utilisateurs inactifs</div>
                            <div class="fs-3 fw-bold text-danger"><?= $total_inactifs ?></div>
                        </div>
                        <div class="bg-danger bg-opacity-10 rounded p-3">
                            <i class="bi bi-person-x fs-2 text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="text-muted small">Rôles utilisés</div>
                            <div class="fs-3 fw-bold text-primary"><?= $roles_utilises ?> / 6</div>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded p-3">
                            <i class="bi bi-shield-check fs-2 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des utilisateurs -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list"></i> Liste complète (<?= count($utilisateurs) ?> utilisateurs)</span>
            <div>
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#filtres">
                    <i class="bi bi-funnel"></i> Filtres
                </button>
            </div>
        </div>
        
        <!-- Filtres -->
        <div class="collapse" id="filtres">
            <div class="card-body border-bottom bg-light">
                <div class="row g-2">
                    <div class="col-md-3">
                        <input type="text" id="search" class="form-control form-control-sm" placeholder="🔍 Rechercher...">
                    </div>
                    <div class="col-md-3">
                        <select id="filterRole" class="form-select form-select-sm">
                            <option value="">Tous les rôles</option>
                            <?php
                            $stmt = $pdo->query("SELECT id, nom FROM roles ORDER BY nom");
                            while ($role = $stmt->fetch()):
                            ?>
                                <option value="<?= $role['nom'] ?>"><?= htmlspecialchars($role['nom']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="filterStatut" class="form-select form-select-sm">
                            <option value="">Tous les statuts</option>
                            <option value="1">Actifs uniquement</option>
                            <option value="0">Inactifs uniquement</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-sm btn-outline-secondary w-100" onclick="resetFilters()">
                            <i class="bi bi-x-circle"></i> Réinitialiser
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0" id="usersTable">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Statut</th>
                            <th>Login</th>
                            <th>Nom complet</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Permissions</th>
                            <th>Dernière connexion</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $user): ?>
                        <tr data-role="<?= htmlspecialchars($user['role_nom']) ?>" data-statut="<?= $user['actif'] ?>">
                            <td>
                                <?php if ($user['actif']): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> Actif
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger">
                                        <i class="bi bi-x-circle"></i> Inactif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($user['login']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($user['nom_complet']) ?></td>
                            <td>
                                <small class="text-muted">
                                    <i class="bi bi-envelope"></i> <?= htmlspecialchars($user['email']) ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($user['role_nom']): ?>
                                    <span class="badge bg-primary">
                                        <?= htmlspecialchars($user['role_nom']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Aucun rôle</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#permissionsModal"
                                        data-user-id="<?= $user['id'] ?>"
                                        data-user-nom="<?= htmlspecialchars($user['nom_complet']) ?>"
                                        data-permissions="<?= htmlspecialchars($user['permissions_list']) ?>">
                                    <i class="bi bi-shield-check"></i> <?= $user['nb_permissions'] ?> permissions
                                </button>
                            </td>
                            <td>
                                <?php if ($user['date_derniere_connexion']): ?>
                                    <small class="text-muted">
                                        <?= date('d/m/Y H:i', strtotime($user['date_derniere_connexion'])) ?>
                                    </small>
                                <?php else: ?>
                                    <small class="text-muted">Jamais</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= url_for('utilisateurs/edit.php?id=' . $user['id']) ?>" 
                                       class="btn btn-outline-primary" title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($user['login'] != 'admin'): ?>
                                        <?php if ($user['actif']): ?>
                                            <button class="btn btn-outline-danger" 
                                                    onclick="toggleActif(<?= $user['id'] ?>, 0)"
                                                    title="Désactiver">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-success" 
                                                    onclick="toggleActif(<?= $user['id'] ?>, 1)"
                                                    title="Activer">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal : Détail des permissions -->
<div class="modal fade" id="permissionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-shield-lock"></i> Permissions de <span id="modalUserNom"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="permissionsList" class="row g-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
// Filtrage en temps réel
document.getElementById('search').addEventListener('keyup', filterTable);
document.getElementById('filterRole').addEventListener('change', filterTable);
document.getElementById('filterStatut').addEventListener('change', filterTable);

function filterTable() {
    const searchValue = document.getElementById('search').value.toLowerCase();
    const roleValue = document.getElementById('filterRole').value;
    const statutValue = document.getElementById('filterStatut').value;
    
    const rows = document.querySelectorAll('#usersTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const role = row.dataset.role;
        const statut = row.dataset.statut;
        
        const matchSearch = text.includes(searchValue);
        const matchRole = !roleValue || role === roleValue;
        const matchStatut = !statutValue || statut === statutValue;
        
        row.style.display = (matchSearch && matchRole && matchStatut) ? '' : 'none';
    });
}

function resetFilters() {
    document.getElementById('search').value = '';
    document.getElementById('filterRole').value = '';
    document.getElementById('filterStatut').value = '';
    filterTable();
}

// Modal permissions
const permissionsModal = document.getElementById('permissionsModal');
permissionsModal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const userNom = button.dataset.userNom;
    const permissions = button.dataset.permissions;
    
    document.getElementById('modalUserNom').textContent = userNom;
    
    const permissionsList = document.getElementById('permissionsList');
    permissionsList.innerHTML = '';
    
    if (permissions) {
        const permsArray = permissions.split(', ');
        permsArray.forEach(perm => {
            const div = document.createElement('div');
            div.className = 'col-md-6';
            div.innerHTML = `<span class="badge bg-secondary w-100 text-start"><i class="bi bi-check"></i> ${perm}</span>`;
            permissionsList.appendChild(div);
        });
    } else {
        permissionsList.innerHTML = '<p class="text-muted">Aucune permission</p>';
    }
});

// Activer/Désactiver utilisateur
function toggleActif(userId, newStatut) {
    if (!confirm('Êtes-vous sûr de vouloir ' + (newStatut ? 'activer' : 'désactiver') + ' cet utilisateur ?')) {
        return;
    }
    
    fetch('<?= url_for('utilisateurs/toggle_actif.php') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            user_id: userId,
            actif: newStatut,
            csrf_token: '<?= getCsrfToken() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur : ' + data.message);
        }
    });
}
</script>

<?php
include __DIR__ . '/../partials/footer.php';
?>
