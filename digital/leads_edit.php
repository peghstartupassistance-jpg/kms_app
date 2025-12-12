<?php
// digital/leads_edit.php - Formulaire d'ajout/édition de lead digital
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CLIENTS_CREER');

global $pdo;

$utilisateur = utilisateurConnecte();
$userId = (int)$utilisateur['id'];

$leadId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $leadId > 0;

$erreurs = [];
$lead = null;

// Charger le lead en mode édition
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM leads_digital WHERE id = ?");
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();
    
    if (!$lead) {
        $_SESSION['flash_error'] = "Lead introuvable.";
        header('Location: ' . url_for('digital/leads_list.php'));
        exit;
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    verifierCsrf($csrf);
    
    $date_lead = $_POST['date_lead'] ?? date('Y-m-d');
    $nom_prospect = trim($_POST['nom_prospect'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $source = $_POST['source'] ?? 'FACEBOOK';
    $message_initial = trim($_POST['message_initial'] ?? '');
    $produit_interet = trim($_POST['produit_interet'] ?? '');
    $statut = $_POST['statut'] ?? 'NOUVEAU';
    $score_prospect = (int)($_POST['score_prospect'] ?? 0);
    $prochaine_action = trim($_POST['prochaine_action'] ?? '');
    $date_prochaine_action = $_POST['date_prochaine_action'] ?? null;
    $campagne = trim($_POST['campagne'] ?? '');
    $cout_acquisition = floatval($_POST['cout_acquisition'] ?? 0);
    $observations = trim($_POST['observations'] ?? '');
    $utilisateur_responsable_id = (int)($_POST['utilisateur_responsable_id'] ?? $userId);
    
    // Validation
    if ($nom_prospect === '') {
        $erreurs[] = "Le nom du prospect est obligatoire.";
    }
    
    if ($telephone === '' && $email === '') {
        $erreurs[] = "Au moins un moyen de contact (téléphone ou email) est requis.";
    }
    
    if ($score_prospect < 0 || $score_prospect > 100) {
        $erreurs[] = "Le score doit être entre 0 et 100.";
    }
    
    if (empty($erreurs)) {
        try {
            if ($isEdit) {
                // Mise à jour
                $sql = "UPDATE leads_digital SET
                    date_lead = :date_lead,
                    nom_prospect = :nom_prospect,
                    telephone = :telephone,
                    email = :email,
                    source = :source,
                    message_initial = :message_initial,
                    produit_interet = :produit_interet,
                    statut = :statut,
                    score_prospect = :score_prospect,
                    prochaine_action = :prochaine_action,
                    date_prochaine_action = :date_prochaine_action,
                    campagne = :campagne,
                    cout_acquisition = :cout_acquisition,
                    observations = :observations,
                    utilisateur_responsable_id = :utilisateur_responsable_id,
                    date_dernier_contact = CURRENT_TIMESTAMP
                WHERE id = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'date_lead' => $date_lead,
                    'nom_prospect' => $nom_prospect,
                    'telephone' => $telephone ?: null,
                    'email' => $email ?: null,
                    'source' => $source,
                    'message_initial' => $message_initial ?: null,
                    'produit_interet' => $produit_interet ?: null,
                    'statut' => $statut,
                    'score_prospect' => $score_prospect,
                    'prochaine_action' => $prochaine_action ?: null,
                    'date_prochaine_action' => $date_prochaine_action ?: null,
                    'campagne' => $campagne ?: null,
                    'cout_acquisition' => $cout_acquisition,
                    'observations' => $observations ?: null,
                    'utilisateur_responsable_id' => $utilisateur_responsable_id,
                    'id' => $leadId
                ]);
                
                $_SESSION['flash_success'] = "Lead mis à jour avec succès.";
            } else {
                // Création
                $sql = "INSERT INTO leads_digital (
                    date_lead, nom_prospect, telephone, email, source, message_initial,
                    produit_interet, statut, score_prospect, prochaine_action, date_prochaine_action,
                    campagne, cout_acquisition, observations, utilisateur_responsable_id,
                    date_dernier_contact
                ) VALUES (
                    :date_lead, :nom_prospect, :telephone, :email, :source, :message_initial,
                    :produit_interet, :statut, :score_prospect, :prochaine_action, :date_prochaine_action,
                    :campagne, :cout_acquisition, :observations, :utilisateur_responsable_id,
                    CURRENT_TIMESTAMP
                )";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'date_lead' => $date_lead,
                    'nom_prospect' => $nom_prospect,
                    'telephone' => $telephone ?: null,
                    'email' => $email ?: null,
                    'source' => $source,
                    'message_initial' => $message_initial ?: null,
                    'produit_interet' => $produit_interet ?: null,
                    'statut' => $statut,
                    'score_prospect' => $score_prospect,
                    'prochaine_action' => $prochaine_action ?: null,
                    'date_prochaine_action' => $date_prochaine_action ?: null,
                    'campagne' => $campagne ?: null,
                    'cout_acquisition' => $cout_acquisition,
                    'observations' => $observations ?: null,
                    'utilisateur_responsable_id' => $utilisateur_responsable_id
                ]);
                
                $_SESSION['flash_success'] = "Lead créé avec succès.";
            }
            
            header('Location: ' . url_for('digital/leads_list.php'));
            exit;
            
        } catch (PDOException $e) {
            $erreurs[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
}

// Charger les utilisateurs (commerciaux)
$stmtUsers = $pdo->query("
    SELECT u.id, u.nom_complet 
    FROM utilisateurs u
    WHERE u.actif = 1
    ORDER BY u.nom_complet
");
$utilisateurs = $stmtUsers->fetchAll();

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url_for('digital/leads_list.php') ?>">Leads digitaux</a></li>
            <li class="breadcrumb-item active"><?= $isEdit ? 'Modifier' : 'Nouveau lead' ?></li>
        </ol>
    </nav>

    <h1 class="h4 mb-4">
        <i class="bi bi-megaphone"></i>
        <?= $isEdit ? 'Modifier le lead' : 'Nouveau lead digital' ?>
    </h1>

    <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($erreurs as $erreur): ?>
                    <li><?= htmlspecialchars($erreur) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                        <div class="row g-3">
                            <!-- Date du lead -->
                            <div class="col-md-4">
                                <label class="form-label">Date du lead <span class="text-danger">*</span></label>
                                <input type="date" name="date_lead" class="form-control" 
                                       value="<?= $lead['date_lead'] ?? date('Y-m-d') ?>" required>
                            </div>

                            <!-- Source -->
                            <div class="col-md-4">
                                <label class="form-label">Source <span class="text-danger">*</span></label>
                                <select name="source" class="form-select" required>
                                    <option value="FACEBOOK" <?= ($lead['source'] ?? '') === 'FACEBOOK' ? 'selected' : '' ?>>
                                        Facebook
                                    </option>
                                    <option value="INSTAGRAM" <?= ($lead['source'] ?? '') === 'INSTAGRAM' ? 'selected' : '' ?>>
                                        Instagram
                                    </option>
                                    <option value="WHATSAPP" <?= ($lead['source'] ?? '') === 'WHATSAPP' ? 'selected' : '' ?>>
                                        WhatsApp
                                    </option>
                                    <option value="SITE_WEB" <?= ($lead['source'] ?? '') === 'SITE_WEB' ? 'selected' : '' ?>>
                                        Site Web
                                    </option>
                                    <option value="TIKTOK" <?= ($lead['source'] ?? '') === 'TIKTOK' ? 'selected' : '' ?>>
                                        TikTok
                                    </option>
                                    <option value="LINKEDIN" <?= ($lead['source'] ?? '') === 'LINKEDIN' ? 'selected' : '' ?>>
                                        LinkedIn
                                    </option>
                                    <option value="AUTRE" <?= ($lead['source'] ?? '') === 'AUTRE' ? 'selected' : '' ?>>
                                        Autre
                                    </option>
                                </select>
                            </div>

                            <!-- Statut -->
                            <div class="col-md-4">
                                <label class="form-label">Statut <span class="text-danger">*</span></label>
                                <select name="statut" class="form-select" required>
                                    <option value="NOUVEAU" <?= ($lead['statut'] ?? 'NOUVEAU') === 'NOUVEAU' ? 'selected' : '' ?>>
                                        Nouveau
                                    </option>
                                    <option value="CONTACTE" <?= ($lead['statut'] ?? '') === 'CONTACTE' ? 'selected' : '' ?>>
                                        Contacté
                                    </option>
                                    <option value="QUALIFIE" <?= ($lead['statut'] ?? '') === 'QUALIFIE' ? 'selected' : '' ?>>
                                        Qualifié
                                    </option>
                                    <option value="DEVIS_ENVOYE" <?= ($lead['statut'] ?? '') === 'DEVIS_ENVOYE' ? 'selected' : '' ?>>
                                        Devis envoyé
                                    </option>
                                    <option value="CONVERTI" <?= ($lead['statut'] ?? '') === 'CONVERTI' ? 'selected' : '' ?>>
                                        Converti
                                    </option>
                                    <option value="PERDU" <?= ($lead['statut'] ?? '') === 'PERDU' ? 'selected' : '' ?>>
                                        Perdu
                                    </option>
                                </select>
                            </div>

                            <!-- Nom du prospect -->
                            <div class="col-md-6">
                                <label class="form-label">Nom du prospect <span class="text-danger">*</span></label>
                                <input type="text" name="nom_prospect" class="form-control" 
                                       value="<?= htmlspecialchars($lead['nom_prospect'] ?? '') ?>" required>
                            </div>

                            <!-- Téléphone -->
                            <div class="col-md-3">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" name="telephone" class="form-control" 
                                       value="<?= htmlspecialchars($lead['telephone'] ?? '') ?>" 
                                       placeholder="+237699123456">
                            </div>

                            <!-- Email -->
                            <div class="col-md-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($lead['email'] ?? '') ?>">
                            </div>

                            <!-- Produit d'intérêt -->
                            <div class="col-md-8">
                                <label class="form-label">Produit d'intérêt</label>
                                <input type="text" name="produit_interet" class="form-control" 
                                       value="<?= htmlspecialchars($lead['produit_interet'] ?? '') ?>"
                                       placeholder="Ex: Table à manger, Chambre à coucher, Panneaux...">
                            </div>

                            <!-- Score prospect -->
                            <div class="col-md-4">
                                <label class="form-label">Score prospect (0-100)</label>
                                <input type="number" name="score_prospect" class="form-control" 
                                       value="<?= $lead['score_prospect'] ?? 0 ?>" min="0" max="100">
                                <small class="text-muted">0=Froid, 50=Moyen, 100=Très chaud</small>
                            </div>

                            <!-- Message initial -->
                            <div class="col-12">
                                <label class="form-label">Message initial / Premier contact</label>
                                <textarea name="message_initial" class="form-control" rows="3"><?= htmlspecialchars($lead['message_initial'] ?? '') ?></textarea>
                            </div>

                            <!-- Prochaine action -->
                            <div class="col-md-8">
                                <label class="form-label">Prochaine action</label>
                                <input type="text" name="prochaine_action" class="form-control" 
                                       value="<?= htmlspecialchars($lead['prochaine_action'] ?? '') ?>"
                                       placeholder="Ex: Rappeler le client, Envoyer devis, Rendez-vous...">
                            </div>

                            <!-- Date prochaine action -->
                            <div class="col-md-4">
                                <label class="form-label">Date prochaine action</label>
                                <input type="date" name="date_prochaine_action" class="form-control" 
                                       value="<?= $lead['date_prochaine_action'] ?? '' ?>">
                            </div>

                            <!-- Campagne -->
                            <div class="col-md-8">
                                <label class="form-label">Campagne publicitaire</label>
                                <input type="text" name="campagne" class="form-control" 
                                       value="<?= htmlspecialchars($lead['campagne'] ?? '') ?>"
                                       placeholder="Ex: Pub Facebook Meubles Novembre, Promo Black Friday...">
                            </div>

                            <!-- Coût acquisition -->
                            <div class="col-md-4">
                                <label class="form-label">Coût d'acquisition (FCFA)</label>
                                <input type="number" name="cout_acquisition" class="form-control" 
                                       value="<?= $lead['cout_acquisition'] ?? 0 ?>" min="0" step="0.01">
                            </div>

                            <!-- Responsable -->
                            <div class="col-md-6">
                                <label class="form-label">Responsable du suivi</label>
                                <select name="utilisateur_responsable_id" class="form-select">
                                    <?php foreach ($utilisateurs as $u): ?>
                                        <option value="<?= $u['id'] ?>" 
                                            <?= ($lead['utilisateur_responsable_id'] ?? $userId) == $u['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['nom_complet']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Observations -->
                            <div class="col-12">
                                <label class="form-label">Observations / Notes</label>
                                <textarea name="observations" class="form-control" rows="3"><?= htmlspecialchars($lead['observations'] ?? '') ?></textarea>
                            </div>

                            <!-- Boutons -->
                            <div class="col-12">
                                <hr>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer
                                </button>
                                <a href="<?= url_for('digital/leads_list.php') ?>" class="btn btn-secondary">
                                    Annuler
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-info-circle"></i> Conseils
                </div>
                <div class="card-body">
                    <h6>Score prospect</h6>
                    <ul class="small">
                        <li><strong>0-30</strong> : Lead froid (curiosité)</li>
                        <li><strong>31-60</strong> : Lead tiède (intéressé)</li>
                        <li><strong>61-100</strong> : Lead chaud (prêt à acheter)</li>
                    </ul>
                    
                    <hr>
                    
                    <h6>Bonnes pratiques</h6>
                    <ul class="small">
                        <li>Contacter le lead dans les <strong>24h</strong></li>
                        <li>Qualifier précisément le besoin</li>
                        <li>Planifier systématiquement la prochaine action</li>
                        <li>Tenir à jour le statut et les notes</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
