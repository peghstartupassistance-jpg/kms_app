<?php
/**
 * Navigation Coordination - Composant Bootstrap
 * À inclure en haut de chaque page coordination
 * Usage: <?php include 'navigation.php'; ?>
 */

$currentPage = basename($_SERVER['PHP_SELF']);

// Configuration des menus coordination
$coordMenus = [
    'dashboard.php' => [
        'label' => 'Dashboard Général',
        'icon' => 'speedometer2',
        'badge' => null,
        'color' => 'primary'
    ],
    'dashboard_magasinier.php' => [
        'label' => 'Dashboard Magasinier',
        'icon' => 'box-seam',
        'badge' => null,
        'color' => 'info'
    ],
    'ordres_preparation.php' => [
        'label' => 'Ordres Préparation',
        'icon' => 'list-task',
        'badge' => null,
        'color' => 'primary'
    ],
    'livraisons.php' => [
        'label' => 'Bons Livraison',
        'icon' => 'truck',
        'badge' => null,
        'color' => 'primary'
    ],
    'litiges.php' => [
        'label' => 'Litiges & Anomalies',
        'icon' => 'exclamation-triangle',
        'badge' => null,
        'color' => 'danger',
        'badgeColor' => 'danger'
    ]
];

// Récupérer nombre de litiges ouverts pour badge (optionnel)
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as nb FROM retours_litiges WHERE statut_traitement = 'EN_COURS' LIMIT 1");
        $litiges = $stmt->fetch();
        if ($litiges['nb'] > 0) {
            $coordMenus['litiges.php']['badge'] = $litiges['nb'];
        }
    } catch (Exception $e) {
        // Silencieusement échouer si requête DB pas possible
    }
}

?>

<!-- Navigation Coordination - Sous-menus Bootstrap 5 -->
<div class="alert alert-light border-bottom border-primary mb-4">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <strong class="text-muted">Coordination:</strong>
        
        <div class="btn-group btn-group-sm flex-wrap" role="group">
            <?php foreach ($coordMenus as $file => $menu): ?>
                <?php 
                    $isActive = ($currentPage === $file);
                    $btnClass = $isActive ? 'btn-' . $menu['color'] : 'btn-outline-' . $menu['color'];
                ?>
                <a href="<?= url_for('coordination/' . $file) ?>" 
                   class="btn <?= $btnClass ?>"
                   title="<?= htmlspecialchars($menu['label']) ?>">
                    <i class="bi bi-<?= $menu['icon'] ?> me-1"></i>
                    <?= htmlspecialchars($menu['label']) ?>
                    
                    <?php if ($menu['badge']): ?>
                        <span class="badge bg-<?= $menu['badgeColor'] ?? 'primary' ?> ms-1">
                            <?= (int)$menu['badge'] ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
/* Styling amélioration pour navigation coordination */
.btn-group .btn {
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.btn-group .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-group .btn.active,
.btn-group .btn-primary {
    font-weight: 600;
}

/* Badge animations */
.btn .badge {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
</style>
