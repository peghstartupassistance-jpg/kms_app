<?php
// terrain/prospections_list.php - Version mobile-optimized avec géolocalisation
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CLIENTS_CREER');

global $pdo;

$utilisateur = utilisateurConnecte();
$userId      = (int)$utilisateur['id'];

$today      = date('Y-m-d');
$dateDebut  = $_GET['date_debut'] ?? $today;
$dateFin    = $_GET['date_fin'] ?? $today;

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
        header('Location: ' . url_for('terrain/prospections_list.php?date_debut=' . urlencode($dateDebut) . '&date_fin=' . urlencode($dateFin)));
        exit;
    }
}

// Charger les données après traitement POST
$where  = [];
$params = [];

$where[] = "pt.commercial_id = :cid";
$params['cid'] = $userId;

if ($dateDebut !== '') {
    $where[] = "pt.date_prospection >= :date_debut";
    $params['date_debut'] = $dateDebut;
}
if ($dateFin !== '') {
    $where[] = "pt.date_prospection <= :date_fin";
    $params['date_fin'] = $dateFin;
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
/* Optimisation mobile */
@media (max-width: 768px) {
    .container-fluid {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
    .card {
        margin-bottom: 1rem;
        border-radius: 0.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .form-label {
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    .form-control, .form-select {
        font-size: 1rem;
        padding: 0.75rem;
    }
    textarea.form-control {
        min-height: 80px;
    }
    .btn-lg-mobile {
        padding: 1rem;
        font-size: 1.1rem;
        font-weight: 600;
    }
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 1rem;
        border-radius: 0.75rem;
        margin-bottom: 1rem;
    }
    .stat-value {
        font-size: 2rem;
        font-weight: bold;
    }
    .prospect-card {
        border-left: 4px solid #667eea;
        margin-bottom: 1rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 0.5rem;
    }
    .prospect-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 0.75rem;
    }
    .prospect-name {
        font-weight: 600;
        font-size: 1.1rem;
        color: #2d3748;
    }
    .prospect-meta {
        font-size: 0.85rem;
        color: #718096;
    }
    .location-badge {
        background: #e6fffa;
        color: #047857;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
}

.geoloc-status {
    padding: 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}
.geoloc-loading {
    background: #fff3cd;
    color: #856404;
}
.geoloc-success {
    background: #d1e7dd;
    color: #0f5132;
}
.geoloc-error {
    background: #f8d7da;
    color: #842029;
}
</style>

<div class="container-fluid py-3">
    <!-- Header mobile-friendly -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">📍 Prospections terrain</h1>
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
    <div class="row g-2 mb-3">
        <div class="col-6">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_today'] ?></div>
                <div class="small">Prospections aujourd'hui</div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['prospects_unique'] ?></div>
                <div class="small">Prospects contactés</div>
            </div>
        </div>
    </div>

    <!-- Formulaire nouvelle prospection -->
    <div class="card mb-3">
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
    <div class="card mb-3">
        <div class="card-header">
            <strong><i class="bi bi-filter"></i> Filtrer les prospections</strong>
        </div>
        <div class="card-body">
            <form class="row g-2">
                <div class="col-6">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control"
                           value="<?= htmlspecialchars($dateDebut) ?>">
                </div>
                <div class="col-6">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control"
                           value="<?= htmlspecialchars($dateFin) ?>">
                </div>
                <div class="col-12">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filtrer
                        </button>
                        <a href="<?= url_for('terrain/prospections_list.php') ?>" class="btn btn-outline-secondary">
                            📅 Aujourd'hui
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des prospections -->
    <div class="mb-3">
        <h5 class="mb-3">📋 Historique (<?= count($prospections) ?>)</h5>
        <?php if (empty($prospections)): ?>
            <div class="card">
                <div class="card-body text-center text-muted py-4">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mb-0 mt-2">Aucune prospection pour cette période</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($prospections as $p): ?>
                <div class="prospect-card">
                    <div class="prospect-card-header">
                        <div>
                            <div class="prospect-name"><?= htmlspecialchars($p['prospect_nom']) ?></div>
                            <div class="prospect-meta">
                                <i class="bi bi-calendar"></i> <?= date('d/m/Y', strtotime($p['date_prospection'])) ?>
                                <?php if ($p['heure_prospection']): ?>
                                    <i class="bi bi-clock ms-2"></i> <?= date('H:i', strtotime($p['heure_prospection'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($p['latitude'] && $p['longitude']): ?>
                            <a href="https://www.google.com/maps?q=<?= $p['latitude'] ?>,<?= $p['longitude'] ?>" 
                               target="_blank" class="location-badge">
                                <i class="bi bi-geo-alt-fill"></i> GPS
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($p['secteur'] && $p['secteur'] != 'Non renseigné'): ?>
                        <div class="mb-2">
                            <strong class="small">📍 Secteur :</strong> 
                            <span class="small"><?= htmlspecialchars($p['secteur']) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($p['adresse_gps']): ?>
                        <div class="mb-2">
                            <strong class="small">🗺️ Adresse :</strong> 
                            <span class="small"><?= htmlspecialchars($p['adresse_gps']) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-2">
                        <strong class="small">💡 Besoin :</strong> 
                        <span class="small"><?= nl2br(htmlspecialchars($p['besoin_identifie'])) ?></span>
                    </div>
                    
                    <div class="mb-2">
                        <strong class="small">✅ Action :</strong> 
                        <span class="small"><?= nl2br(htmlspecialchars($p['action_menee'])) ?></span>
                    </div>
                    
                    <div class="mb-2">
                        <strong class="small">📊 Résultat :</strong> 
                        <span class="badge bg-info"><?= htmlspecialchars($p['resultat']) ?></span>
                    </div>
                    
                    <?php if ($p['prochaine_etape']): ?>
                        <div>
                            <strong class="small">➡️ Suite :</strong> 
                            <span class="small"><?= nl2br(htmlspecialchars($p['prochaine_etape'])) ?></span>
                        </div>
                    <?php endif; ?>
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
