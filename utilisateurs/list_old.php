<?php
// utilisateurs/list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('UTILISATEURS_GERER');

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';

global $pdo;

// Flash message
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

// Récupération des utilisateurs + rôles
$sql = "
    SELECT 
        u.id,
        u.login,
        u.nom_complet,
        u.email,
        u.telephone,
        u.actif,
        u.date_creation,
        GROUP_CONCAT(r.nom ORDER BY r.code SEPARATOR ', ') AS roles
    FROM utilisateurs u
    LEFT JOIN utilisateur_role ur ON ur.utilisateur_id = u.id
    LEFT JOIN roles r ON r.id = ur.role_id
    GROUP BY u.id
    ORDER BY u.date_creation DESC, u.login ASC
";
$stmt = $pdo->query($sql);
$utilisateurs = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Utilisateurs internes</h1>
        <a href="edit.php" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i> Nouvel utilisateur
        </a>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success py-2">
            <i class="bi bi-check-circle me-1"></i>
            <?= htmlspecialchars($flashSuccess) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <?php if (empty($utilisateurs)): ?>
                <p class="text-muted mb-0">Aucun utilisateur pour le moment.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Login</th>
                            <th>Nom complet</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Rôles</th>
                            <th class="text-center">Actif</th>
                            <th>Date création</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($utilisateurs as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['login']) ?></td>
                                <td><?= htmlspecialchars($u['nom_complet']) ?></td>
                                <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($u['telephone'] ?? '') ?></td>
                                <td><?= htmlspecialchars($u['roles'] ?? '—') ?></td>
                                <td class="text-center">
                                    <?php if ((int)$u['actif'] === 1): ?>
                                        <span class="badge bg-success-subtle text-success">
                                            <i class="bi bi-check-circle me-1"></i> Actif
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary">
                                            <i class="bi bi-slash-circle me-1"></i> Inactif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($u['date_creation']) ?>
                                </td>
                                <td class="text-end">
                                    <a href="edit.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil-square me-1"></i> Modifier
                                    </a>
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

<?php
include __DIR__ . '/../partials/footer.php';
