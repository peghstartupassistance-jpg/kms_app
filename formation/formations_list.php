<?php
// formation/formations_list.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('FORMATION_GERER');

global $pdo;

// Récup liste des formations
$stmt = $pdo->query("
    SELECT *
    FROM formations
    ORDER BY nom ASC
");
$formations = $stmt->fetchAll();

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Formations (catalogue)</h1>
        <a href="<?= url_for('formation/formations_edit.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Nouvelle formation
        </a>
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

    <div class="card">
        <div class="card-body">
            <?php if (empty($formations)): ?>
                <p class="text-muted mb-0">Aucune formation définie pour le moment.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Nom</th>
                            <th>Description</th>
                            <th class="text-end">Tarif total</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($formations as $f): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($f['nom']) ?></td>
                                <td><?= nl2br(htmlspecialchars($f['description'] ?? '')) ?></td>
                                <td class="text-end">
                                    <?= number_format((float)$f['tarif_total'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="text-end">
                                    <a href="<?= url_for('formation/formations_edit.php') . '?id=' . (int)$f['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary">
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

<?php include __DIR__ . '/../partials/footer.php'; ?>
