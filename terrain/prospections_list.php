<?php
// terrain/prospections_list.php - Version mobile-optimized avec géolocalisation
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CLIENTS_CREER');

global $pdo;

$utilisateur = utilisateurConnecte();
$userId      = (int)$utilisateur['id'];

$today      = date('Y-m-d');
$dateDebut  = $_GET['date_debut'] ?? null;
$dateFin    = $_GET['date_fin'] ?? null;

// Formulaire POST - TRAITER AVANT TOUT OUTPUT
$erreurs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    verifierCsrf($csrf);

    $date_prospection = $_POST['date_prospection'] ?? date('Y-m-d');
    $heure_prospection = $_POST['heure_prospection'] ?? date('H:i:s');
    $prospect_nom     = trim($_POST['prospect_nom'] ?? '');
    $secteur          = trim($_POST['secteur'] ?? '');
    $latitude         = $_POST['latitude'] ?? null;
    $longitude        = $_POST['longitude'] ?? null;
    $adresse_gps      = trim($_POST['adresse_gps'] ?? '');
    $besoin_identifie = trim($_POST['besoin_identifie'] ?? '');
    $action_menee     = trim($_POST['action_menee'] ?? '');
    $resultat         = trim($_POST['resultat'] ?? '');
    $prochaine_etape  = trim($_POST['prochaine_etape'] ?? '');

    if ($prospect_nom === '') {
        $erreurs[] = "Le nom du prospect est obligatoire.";
    }
    if ($besoin_identifie === '') {
        $erreurs[] = "Le besoin identifié est obligatoire.";
    }
    if ($action_menee === '') {
        $erreurs[] = "L'action menée est obligatoire.";
    }
    if ($resultat === '') {
        $erreurs[] = "Le résultat de la visite est obligatoire.";
    }

    if (empty($erreurs)) {
        $stmtIns = $pdo->prepare("
            INSERT INTO prospections_terrain (
                date_prospection, heure_prospection, prospect_nom, secteur,
                latitude, longitude, adresse_gps,
                besoin_identifie, action_menee, resultat,
                prochaine_etape, client_id, commercial_id
            ) VALUES (
                :date_prospection, :heure_prospection, :prospect_nom, :secteur,
                :latitude, :longitude, :adresse_gps,
                :besoin_identifie, :action_menee, :resultat,
                :prochaine_etape, NULL, :commercial_id
            )
        ");
        $stmtIns->execute([
            'date_prospection'  => $date_prospection,
            'heure_prospection' => $heure_prospection,
            'prospect_nom'      => $prospect_nom,
            'secteur'           => $secteur ?: 'Non renseigné',
            'latitude'          => $latitude ?: null,
            'longitude'         => $longitude ?: null,
            'adresse_gps'       => $adresse_gps ?: null,
            'besoin_identifie'  => $besoin_identifie,
            'action_menee'      => $action_menee,
            'resultat'          => $resultat,
            'prochaine_etape'   => $prochaine_etape ?: null,
            'commercial_id'     => $userId
        ]);

        $_SESSION['flash_success'] = "✅ Prospection enregistrée avec succès !";
        
        // Redirection en conservant les filtres
        $redirectUrl = url_for('terrain/prospections_list.php');
        if ($dateDebut) {
            $redirectUrl .= '?date_debut=' . urlencode($dateDebut);
        }
        if ($dateFin) {
            $redirectUrl .= (strpos($redirectUrl, '?') !== false ? '&' : '?') . 'date_fin=' . urlencode($dateFin);
        }
        
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// Charger les données après traitement POST
$where  = [];
$params = [];

$where[] = "pt.commercial_id = ?";
$params[] = $userId;

// Les filtres de date sont OPTIONNELS (non appliqués par défaut)
if ($dateDebut !== null && $dateDebut !== '') {
    $where[] = "pt.date_prospection >= ?";
    $params[] = $dateDebut;
}
if ($dateFin !== null && $dateFin !== '') {
    $where[] = "pt.date_prospection <= ?";
    $params[] = $dateFin;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT pt.*
    FROM prospections_terrain pt
    $whereSql
    ORDER BY pt.date_prospection DESC, pt.heure_prospection DESC, pt.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prospections = $stmt->fetchAll();

$csrfToken = getCsrfToken();
$flashSuccess = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

// Stats rapides
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_today,
        COUNT(DISTINCT prospect_nom) as prospects_unique
    FROM prospections_terrain 
    WHERE commercial_id = ? AND date_prospection = CURDATE()
");
$stmt->execute([$userId]);
$stats = $stmt->fetch();

// Maintenant on peut inclure le header et sidebar
include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<style>
/* Global improvements */
.container-fluid {
    max-width: 1400px;
}

/* Stats cards */
.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 1.25rem;
    border-radius: 1rem;
    margin-bottom: 1rem;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: bold;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    opacity: 0.9;
    font-weight: 500;
}

/* Cards enhancement */
.filter-card {
    border: none;
    border-radius: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.filter-card .card-header {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    border: none;
    padding: 1rem 1.25rem;
    font-weight: 600;
}

.filter-card .card-body {
    padding: 1.5rem;
}

/* Form improvements */
.form-label {
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #334155;
}

.form-control, .form-select {
    border: 2px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    font-size: 0.9375rem;
    transition: all 0.2s;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

textarea.form-control {
    min-height: 90px;
}

/* Buttons */
.btn-lg-mobile {
    padding: 1rem 1.5rem;
    font-size: 1.0625rem;
    font-weight: 600;
    border-radius: 0.75rem;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    transition: all 0.2s;
}

.btn-lg-mobile:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

/* Prospect cards */
.prospect-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-left: 4px solid #667eea;
    margin-bottom: 1rem;
    padding: 1.25rem;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    transition: all 0.2s;
}

.prospect-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.prospect-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
}

.prospect-name {
    font-weight: 700;
    font-size: 1.125rem;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.prospect-meta {
    font-size: 0.8125rem;
    color: #64748b;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.prospect-meta i {
    color: #94a3b8;
}

.location-badge {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 0.375rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    text-decoration: none;
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
}

.location-badge:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(16, 185, 129, 0.4);
}

.prospect-field {
    margin-bottom: 0.875rem;
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 0.5rem;
}

.prospect-field:last-child {
    margin-bottom: 0;
}

.field-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    margin-bottom: 0.375rem;
}

.field-value {
    font-size: 0.9375rem;
    color: #334155;
    line-height: 1.5;
}

.result-badge {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.875rem;
    display: inline-block;
}

.badge.bg-info {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
}

/* Geolocation status */
.geoloc-status {
    padding: 1rem;
    border-radius: 0.75rem;
    font-size: 0.9375rem;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
}

.geoloc-loading {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
    border: 1px solid #fbbf24;
}

.geoloc-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    border: 1px solid #10b981;
}

.geoloc-error {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border: 1px solid #ef4444;
}

/* Section titles */
.section-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.count-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 3rem 1.5rem;
    color: #94a3b8;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Mobile optimizations */
@media (max-width: 768px) {
    .container-fluid {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
    
    .stat-value {
        font-size: 2rem;
    }
    
    .prospect-name {
        font-size: 1rem;
    }
    
    .prospect-meta {
        font-size: 0.75rem;
    }
}
</style>

<div class="container-fluid py-3">
    <!-- Header mobile-friendly -->
    <div class="list-page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="list-page-title h3">
                <i class="bi bi-geo-alt-fill me-2"></i>
                Prospections terrain
                <span class="count-badge ms-2"><?= count($prospections) ?></span>
            </h1>
            <p class="text-muted small mb-0">Commercial : <?= htmlspecialchars($utilisateur['nom_complet']) ?></p>
        </div>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($flashSuccess) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <strong>⚠️ Erreurs :</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($erreurs as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats du jour -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_today'] ?></div>
                <div class="stat-label">Prospections aujourd'hui</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <div class="stat-value"><?= $stats['prospects_unique'] ?></div>
                <div class="stat-label">Prospects contactés</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <div class="stat-value"><?= count($prospections) ?></div>
                <div class="stat-label">Total période</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <div class="stat-value">
                    <?php 
                    $taux = $stats['total_today'] > 0 ? round(($stats['prospects_unique'] / $stats['total_today']) * 100) : 0;
                    echo $taux . '%';
                    ?>
                </div>
                <div class="stat-label">Taux de conversion</div>
            </div>
        </div>
    </div>

    <!-- Formulaire nouvelle prospection -->
    <div class="card filter-card">
        <div class="card-header bg-primary text-white">
            <strong><i class="bi bi-plus-circle"></i> Nouvelle prospection</strong>
        </div>
        <div class="card-body">
            <!-- Statut géolocalisation -->
            <div id="geolocStatus" class="geoloc-status geoloc-loading" style="display: none;">
                <i class="bi bi-hourglass-split"></i> Récupération de votre position...
            </div>

            <form method="post" id="prospectionForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="date_prospection" id="date_prospection" value="">
                <input type="hidden" name="heure_prospection" id="heure_prospection" value="">
                <input type="hidden" name="latitude" id="latitude" value="">
                <input type="hidden" name="longitude" id="longitude" value="">
                <input type="hidden" name="adresse_gps" id="adresse_gps" value="">

                <div class="mb-3">
                    <label class="form-label">Nom du prospect *</label>
                    <input type="text" name="prospect_nom" class="form-control" required 
                           placeholder="Ex: M. Kouassi Jean">
                </div>

                <div class="mb-3">
                    <label class="form-label">Secteur / Zone</label>
                    <input type="text" name="secteur" id="secteur" class="form-control" 
                           placeholder="Ex: Cocody, Yopougon..." value="">
                </div>

                <div class="mb-3">
                    <label class="form-label">Besoin identifié *</label>
                    <textarea name="besoin_identifie" class="form-control" required 
                              placeholder="Quel est le besoin du prospect ?"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Action menée *</label>
                    <textarea name="action_menee" class="form-control" required 
                              placeholder="Qu'avez-vous fait ou proposé ?"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Résultat *</label>
                    <select name="resultat" class="form-select" required>
                        <option value="">-- Sélectionner --</option>
                        <option value="Intéressé - À recontacter">✅ Intéressé - À recontacter</option>
                        <option value="Devis demandé">💰 Devis demandé</option>
                        <option value="Vente conclue">🎉 Vente conclue</option>
                        <option value="Pas intéressé">❌ Pas intéressé</option>
                        <option value="À rappeler plus tard">⏰ À rappeler plus tard</option>
                        <option value="Absent - Repasser">🚪 Absent - Repasser</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Prochaine étape</label>
                    <textarea name="prochaine_etape" class="form-control" 
                              placeholder="Quelle est la suite à donner ? (optionnel)"></textarea>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg-mobile">
                        <i class="bi bi-save"></i> Enregistrer la prospection
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card filter-card">
        <div class="card-header">
            <strong><i class="bi bi-filter"></i> Filtrer les prospections</strong>
        </div>
        <div class="card-body">
            <form class="row g-2">
                <div class="col-6">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control"
                           value="<?= htmlspecialchars($dateDebut ?? '') ?>">
                </div>
                <div class="col-6">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control"
                           value="<?= htmlspecialchars($dateFin ?? '') ?>">
                </div>
                <div class="col-12">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-add-new">
                            <i class="bi bi-search"></i> Filtrer
                        </button>
                        <a href="<?= url_for('terrain/prospections_list.php') ?>" class="btn btn-outline-secondary btn-filter">
                            📅 Aujourd'hui
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des prospections -->
    <div class="mb-3">
        <h5 class="section-title">
            <i class="bi bi-list-ul"></i> Historique
            <span class="count-badge"><?= count($prospections) ?></span>
        </h5>
        <?php if (empty($prospections)): ?>
            <div class="card filter-card">
                <div class="card-body empty-state">
                    <i class="bi bi-inbox"></i>
                    <p class="mb-0">Aucune prospection pour cette période</p>
                    <small class="text-muted">Commencez par enregistrer une nouvelle prospection</small>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($prospections as $p): ?>
                <div class="prospect-card">
                    <div class="prospect-card-header">
                        <div style="flex: 1;">
                            <div class="prospect-name">
                                <i class="bi bi-person-circle me-2" style="color: #667eea;"></i>
                                <?= htmlspecialchars($p['prospect_nom']) ?>
                            </div>
                            <div class="prospect-meta">
                                <span><i class="bi bi-calendar3"></i> <?= date('d/m/Y', strtotime($p['date_prospection'])) ?></span>
                                <?php if ($p['heure_prospection']): ?>
                                    <span><i class="bi bi-clock"></i> <?= date('H:i', strtotime($p['heure_prospection'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($p['latitude'] && $p['longitude']): ?>
                            <a href="https://www.google.com/maps?q=<?= $p['latitude'] ?>,<?= $p['longitude'] ?>" 
                               target="_blank" class="location-badge">
                                <i class="bi bi-geo-alt-fill"></i> Voir carte
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row g-2">
                        <?php if ($p['secteur'] && $p['secteur'] != 'Non renseigné'): ?>
                            <div class="col-md-6">
                                <div class="prospect-field">
                                    <div class="field-label"><i class="bi bi-geo me-1"></i> Secteur</div>
                                    <div class="field-value"><?= htmlspecialchars($p['secteur']) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($p['adresse_gps']): ?>
                            <div class="col-md-6">
                                <div class="prospect-field">
                                    <div class="field-label"><i class="bi bi-map me-1"></i> Localisation</div>
                                    <div class="field-value small"><?= htmlspecialchars($p['adresse_gps']) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="col-12">
                            <div class="prospect-field">
                                <div class="field-label"><i class="bi bi-lightbulb me-1"></i> Besoin identifié</div>
                                <div class="field-value"><?= nl2br(htmlspecialchars($p['besoin_identifie'])) ?></div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="prospect-field">
                                <div class="field-label"><i class="bi bi-check-circle me-1"></i> Action menée</div>
                                <div class="field-value"><?= nl2br(htmlspecialchars($p['action_menee'])) ?></div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="prospect-field">
                                <div class="field-label"><i class="bi bi-clipboard-data me-1"></i> Résultat</div>
                                <div class="field-value">
                                    <div data-statut-change 
                                         data-entite="prospection" 
                                         data-id="<?= (int)$p['id'] ?>" 
                                         data-statut="<?= htmlspecialchars($p['resultat']) ?>">
                                        <!-- Sera transformé en dropdown par tunnel-conversion.js -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($p['prochaine_etape']): ?>
                            <div class="col-md-6">
                                <div class="prospect-field">
                                    <div class="field-label"><i class="bi bi-arrow-right-circle me-1"></i> Prochaine étape</div>
                                    <div class="field-value"><?= nl2br(htmlspecialchars($p['prochaine_etape'])) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Géolocalisation automatique au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Date et heure automatiques
    const now = new Date();
    document.getElementById('date_prospection').value = now.toISOString().split('T')[0];
    document.getElementById('heure_prospection').value = now.toTimeString().split(' ')[0];
    
    // Géolocalisation
    if (navigator.geolocation) {
        const geoStatus = document.getElementById('geolocStatus');
        geoStatus.style.display = 'block';
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                // Succès - Coordonnées récupérées
                document.getElementById('latitude').value = position.coords.latitude;
                document.getElementById('longitude').value = position.coords.longitude;
                
                // Géocodage inversé pour obtenir l'adresse
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${position.coords.latitude}&lon=${position.coords.longitude}`)
                    .then(response => response.json())
                    .then(data => {
                        const adresse = data.display_name || '';
                        document.getElementById('adresse_gps').value = adresse;
                        
                        // Extraire le quartier/secteur si possible
                        if (data.address) {
                            const secteur = data.address.suburb || data.address.neighbourhood || data.address.city_district || data.address.city || '';
                            if (secteur && !document.getElementById('secteur').value) {
                                document.getElementById('secteur').value = secteur;
                            }
                        }
                        
                        geoStatus.className = 'geoloc-status geoloc-success';
                        geoStatus.innerHTML = '<i class="bi bi-check-circle"></i> Position enregistrée : ' + 
                                            (data.address?.suburb || data.address?.neighbourhood || 'Localisation capturée');
                    })
                    .catch(err => {
                        console.error('Erreur géocodage:', err);
                        geoStatus.className = 'geoloc-status geoloc-success';
                        geoStatus.innerHTML = '<i class="bi bi-check-circle"></i> Position GPS enregistrée';
                    });
            },
            function(error) {
                // Erreur de géolocalisation
                let message = '';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        message = "⚠️ Veuillez autoriser l'accès à votre position pour une géolocalisation précise.";
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message = "⚠️ Position non disponible. Continuez sans géolocalisation.";
                        break;
                    case error.TIMEOUT:
                        message = "⚠️ Délai dépassé. Continuez sans géolocalisation.";
                        break;
                    default:
                        message = "⚠️ Géolocalisation non disponible.";
                }
                geoStatus.className = 'geoloc-status geoloc-error';
                geoStatus.innerHTML = message;
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    } else {
        document.getElementById('geolocStatus').style.display = 'none';
    }
    
    // Mise à jour de l'heure toutes les secondes
    setInterval(function() {
        const now = new Date();
        document.getElementById('heure_prospection').value = now.toTimeString().split(' ')[0];
    }, 1000);
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
