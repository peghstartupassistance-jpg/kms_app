<?php
// digital/leads_list.php - Liste des leads digitaux
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CLIENTS_LIRE');

global $pdo;

$utilisateur = utilisateurConnecte();
$userId = (int)$utilisateur['id'];

// Filtres
$source = $_GET['source'] ?? '';
$statut = $_GET['statut'] ?? '';
$dateDebut = $_GET['date_debut'] ?? '';
$dateFin = $_GET['date_fin'] ?? '';
$recherche = $_GET['recherche'] ?? '';

// Construction de la requête
$where = [];
$params = [];

if ($source !== '') {
    $where[] = "ld.source = :source";
    $params['source'] = $source;
}

if ($statut !== '') {
    $where[] = "ld.statut = :statut";
    $params['statut'] = $statut;
}

if ($dateDebut !== '') {
    $where[] = "ld.date_lead >= :date_debut";
    $params['date_debut'] = $dateDebut;
}

if ($dateFin !== '') {
    $where[] = "ld.date_lead <= :date_fin";
    $params['date_fin'] = $dateFin;
}

if ($recherche !== '') {
    $where[] = "(ld.nom_prospect LIKE :recherche OR ld.telephone LIKE :recherche OR ld.email LIKE :recherche)";
    $params['recherche'] = '%' . $recherche . '%';
}

$whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT ld.*, 
           c.nom AS client_converti,
           u.nom_complet AS responsable
    FROM leads_digital ld
    LEFT JOIN clients c ON ld.client_id = c.id
    LEFT JOIN utilisateurs u ON ld.utilisateur_responsable_id = u.id
    $whereSql
    ORDER BY 
        CASE ld.statut
            WHEN 'NOUVEAU' THEN 1
            WHEN 'CONTACTE' THEN 2
            WHEN 'QUALIFIE' THEN 3
            WHEN 'DEVIS_ENVOYE' THEN 4
            WHEN 'CONVERTI' THEN 5
            WHEN 'PERDU' THEN 6
        END,
        ld.date_prochaine_action ASC,
        ld.date_lead DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Statistiques rapides
$sqlStats = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'NOUVEAU' THEN 1 ELSE 0 END) as nouveaux,
        SUM(CASE WHEN statut = 'CONTACTE' THEN 1 ELSE 0 END) as contactes,
        SUM(CASE WHEN statut = 'QUALIFIE' THEN 1 ELSE 0 END) as qualifies,
        SUM(CASE WHEN statut = 'DEVIS_ENVOYE' THEN 1 ELSE 0 END) as devis_envoyes,
        SUM(CASE WHEN statut = 'CONVERTI' THEN 1 ELSE 0 END) as convertis,
        SUM(CASE WHEN statut = 'PERDU' THEN 1 ELSE 0 END) as perdus,
        ROUND(SUM(CASE WHEN statut = 'CONVERTI' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as taux_conversion
    FROM leads_digital
    $whereSql
";
$stmtStats = $pdo->prepare($sqlStats);
$stmtStats->execute($params);
$stats = $stmtStats->fetch();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0">
            <i class="bi bi-megaphone"></i> Leads Digitaux
        </h1>
        <a href="<?= url_for('digital/leads_edit.php') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nouveau lead
        </a>
    </div>

    <!-- Statistiques rapides -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card kms-card-hover">
                <div class="card-body p-3">
                    <div class="text-muted small">Total leads</div>
                    <div class="fs-5 fw-bold"><?= number_format($stats['total']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card kms-card-hover border-warning">
                <div class="card-body p-3">
                    <div class="text-muted small">Nouveaux</div>
                    <div class="fs-5 fw-bold text-warning"><?= number_format($stats['nouveaux']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card kms-card-hover border-info">
                <div class="card-body p-3">
                    <div class="text-muted small">Contactés</div>
                    <div class="fs-5 fw-bold text-info"><?= number_format($stats['contactes']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card kms-card-hover border-primary">
                <div class="card-body p-3">
                    <div class="text-muted small">Qualifiés</div>
                    <div class="fs-5 fw-bold text-primary"><?= number_format($stats['qualifies']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card kms-card-hover border-success">
                <div class="card-body p-3">
                    <div class="text-muted small">Convertis</div>
                    <div class="fs-5 fw-bold text-success"><?= number_format($stats['convertis']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card kms-card-hover">
                <div class="card-body p-3">
                    <div class="text-muted small">Taux conversion</div>
                    <div class="fs-5 fw-bold"><?= $stats['taux_conversion'] ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label small">Source</label>
                    <select name="source" class="form-select form-select-sm">
                        <option value="">Toutes</option>
                        <option value="FACEBOOK" <?= $source === 'FACEBOOK' ? 'selected' : '' ?>>Facebook</option>
                        <option value="INSTAGRAM" <?= $source === 'INSTAGRAM' ? 'selected' : '' ?>>Instagram</option>
                        <option value="WHATSAPP" <?= $source === 'WHATSAPP' ? 'selected' : '' ?>>WhatsApp</option>
                        <option value="SITE_WEB" <?= $source === 'SITE_WEB' ? 'selected' : '' ?>>Site Web</option>
                        <option value="TIKTOK" <?= $source === 'TIKTOK' ? 'selected' : '' ?>>TikTok</option>
                        <option value="LINKEDIN" <?= $source === 'LINKEDIN' ? 'selected' : '' ?>>LinkedIn</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Statut</label>
                    <select name="statut" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        <option value="NOUVEAU" <?= $statut === 'NOUVEAU' ? 'selected' : '' ?>>Nouveau</option>
                        <option value="CONTACTE" <?= $statut === 'CONTACTE' ? 'selected' : '' ?>>Contacté</option>
                        <option value="QUALIFIE" <?= $statut === 'QUALIFIE' ? 'selected' : '' ?>>Qualifié</option>
                        <option value="DEVIS_ENVOYE" <?= $statut === 'DEVIS_ENVOYE' ? 'selected' : '' ?>>Devis envoyé</option>
                        <option value="CONVERTI" <?= $statut === 'CONVERTI' ? 'selected' : '' ?>>Converti</option>
                        <option value="PERDU" <?= $statut === 'PERDU' ? 'selected' : '' ?>>Perdu</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control form-control-sm" value="<?= htmlspecialchars($dateDebut) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFin) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Recherche</label>
                    <input type="text" name="recherche" class="form-control form-control-sm" 
                           placeholder="Nom, téléphone, email..." value="<?= htmlspecialchars($recherche) ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des leads -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Prospect</th>
                            <th>Contact</th>
                            <th>Source</th>
                            <th>Produit d'intérêt</th>
                            <th>Statut</th>
                            <th>Score</th>
                            <th>Prochaine action</th>
                            <th>Responsable</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leads)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    Aucun lead trouvé
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leads as $lead): ?>
                                <?php
                                $badges = [
                                    'NOUVEAU' => 'warning',
                                    'CONTACTE' => 'info',
                                    'QUALIFIE' => 'primary',
                                    'DEVIS_ENVOYE' => 'secondary',
                                    'CONVERTI' => 'success',
                                    'PERDU' => 'danger'
                                ];
                                $badgeClass = $badges[$lead['statut']] ?? 'secondary';
                                
                                $sourceIcons = [
                                    'FACEBOOK' => 'facebook',
                                    'INSTAGRAM' => 'instagram',
                                    'WHATSAPP' => 'whatsapp',
                                    'TIKTOK' => 'tiktok',
                                    'LINKEDIN' => 'linkedin',
                                    'SITE_WEB' => 'globe'
                                ];
                                $sourceIcon = $sourceIcons[$lead['source']] ?? 'megaphone';
                                
                                $scoreColor = $lead['score_prospect'] >= 70 ? 'success' : ($lead['score_prospect'] >= 40 ? 'warning' : 'secondary');
                                ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <?= date('d/m/Y', strtotime($lead['date_lead'])) ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($lead['nom_prospect']) ?></div>
                                        <?php if ($lead['client_converti']): ?>
                                            <small class="text-success">
                                                <i class="bi bi-check-circle"></i> Converti: <?= htmlspecialchars($lead['client_converti']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if ($lead['telephone']): ?>
                                            <div><i class="bi bi-telephone"></i> <?= htmlspecialchars($lead['telephone']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($lead['email']): ?>
                                            <div><i class="bi bi-envelope"></i> <?= htmlspecialchars($lead['email']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-<?= $sourceIcon ?>"></i> 
                                        <span class="small"><?= $lead['source'] ?></span>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($lead['produit_interet'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $badgeClass ?>">
                                            <?= str_replace('_', ' ', $lead['statut']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $scoreColor ?>"><?= $lead['score_prospect'] ?></span>
                                    </td>
                                    <td class="small">
                                        <?php if ($lead['date_prochaine_action']): ?>
                                            <?php
                                            $dateProchaine = strtotime($lead['date_prochaine_action']);
                                            $aujourd_hui = strtotime(date('Y-m-d'));
                                            $estEnRetard = $dateProchaine < $aujourd_hui;
                                            ?>
                                            <div class="<?= $estEnRetard ? 'text-danger fw-semibold' : '' ?>">
                                                <?= date('d/m/Y', $dateProchaine) ?>
                                                <?php if ($estEnRetard): ?>
                                                    <i class="bi bi-exclamation-triangle"></i>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted"><?= htmlspecialchars($lead['prochaine_action'] ?? '') ?></small>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($lead['responsable'] ?? '-') ?></td>
                                    <td class="text-end text-nowrap">
                                        <a href="<?= url_for('digital/leads_edit.php?id=' . $lead['id']) ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($lead['statut'] !== 'CONVERTI' && $lead['statut'] !== 'PERDU'): ?>
                                            <a href="<?= url_for('digital/leads_conversion.php?id=' . $lead['id']) ?>" 
                                               class="btn btn-sm btn-outline-success" title="Convertir">
                                                <i class="bi bi-arrow-right-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
