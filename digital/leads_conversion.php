<?php
// digital/leads_conversion.php - Conversion d'un lead en client/prospect
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('CLIENTS_CREER');

global $pdo;

$leadId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($leadId === 0) {
    $_SESSION['flash_error'] = "Lead introuvable.";
    header('Location: ' . url_for('digital/leads_list.php'));
    exit;
}

// Charger le lead
$stmt = $pdo->prepare("SELECT * FROM leads_digital WHERE id = ?");
$stmt->execute([$leadId]);
$lead = $stmt->fetch();

if (!$lead) {
    $_SESSION['flash_error'] = "Lead introuvable.";
    header('Location: ' . url_for('digital/leads_list.php'));
    exit;
}

if ($lead['statut'] === 'CONVERTI') {
    $_SESSION['flash_info'] = "Ce lead a déjà été converti.";
    header('Location: ' . url_for('digital/leads_list.php'));
    exit;
}

$erreurs = [];

// Charger les types de clients
$stmtTypes = $pdo->query("SELECT * FROM types_client ORDER BY libelle");
$typesClient = $stmtTypes->fetchAll();

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    verifierCsrf($csrf);
    
    $nom = trim($_POST['nom'] ?? $lead['nom_prospect']);
    $type_client_id = (int)($_POST['type_client_id'] ?? 3); // 3 = DIGITAL par défaut
    $telephone = trim($_POST['telephone'] ?? $lead['telephone']);
    $email = trim($_POST['email'] ?? $lead['email']);
    $adresse = trim($_POST['adresse'] ?? '');
    $source = 'Digital - ' . $lead['source'];
    $statut = $_POST['statut'] ?? 'PROSPECT';
    
    // Créer le devis ?
    $creer_devis = isset($_POST['creer_devis']) && $_POST['creer_devis'] === '1';
    $canal_vente_id = (int)($_POST['canal_vente_id'] ?? 3); // 3 = DIGITAL
    
    // Validation
    if ($nom === '') {
        $erreurs[] = "Le nom est obligatoire.";
    }
    
    if ($telephone === '' && $email === '') {
        $erreurs[] = "Au moins un moyen de contact est requis.";
    }
    
    if (empty($erreurs)) {
        try {
            $pdo->beginTransaction();
            
            // 1. Créer le client
            $sqlClient = "INSERT INTO clients (
                nom, type_client_id, telephone, email, adresse, source, statut, date_creation
            ) VALUES (
                :nom, :type_client_id, :telephone, :email, :adresse, :source, :statut, NOW()
            )";
            
            $stmtClient = $pdo->prepare($sqlClient);
            $stmtClient->execute([
                'nom' => $nom,
                'type_client_id' => $type_client_id,
                'telephone' => $telephone ?: null,
                'email' => $email ?: null,
                'adresse' => $adresse ?: null,
                'source' => $source,
                'statut' => $statut
            ]);
            
            $clientId = $pdo->lastInsertId();
            
            // 2. Mettre à jour le lead
            $sqlUpdateLead = "UPDATE leads_digital SET
                statut = 'CONVERTI',
                client_id = :client_id,
                date_conversion = NOW()
            WHERE id = :id";
            
            $stmtUpdateLead = $pdo->prepare($sqlUpdateLead);
            $stmtUpdateLead->execute([
                'client_id' => $clientId,
                'id' => $leadId
            ]);
            
            // 3. Créer un devis si demandé
            $devisId = null;
            if ($creer_devis) {
                // Générer numéro de devis
                $stmtNumero = $pdo->query("SELECT MAX(CAST(SUBSTRING(numero, 3) AS UNSIGNED)) as dernier FROM devis WHERE numero LIKE 'D-%'");
                $dernier = $stmtNumero->fetch();
                $prochain = ($dernier['dernier'] ?? 0) + 1;
                $numeroDevis = 'D-' . str_pad($prochain, 6, '0', STR_PAD_LEFT);
                
                $sqlDevis = "INSERT INTO devis (
                    numero, date_devis, client_id, canal_vente_id, statut,
                    utilisateur_id, montant_total_ht, montant_total_ttc, commentaires
                ) VALUES (
                    :numero, CURDATE(), :client_id, :canal_vente_id, 'EN_ATTENTE',
                    :utilisateur_id, 0, 0, :commentaires
                )";
                
                $stmtDevis = $pdo->prepare($sqlDevis);
                $stmtDevis->execute([
                    'numero' => $numeroDevis,
                    'client_id' => $clientId,
                    'canal_vente_id' => $canal_vente_id,
                    'utilisateur_id' => $_SESSION['utilisateur']['id'],
                    'commentaires' => "Devis créé suite à conversion lead digital #$leadId\nProduit d'intérêt: " . ($lead['produit_interet'] ?? 'Non spécifié')
                ]);
                
                $devisId = $pdo->lastInsertId();
            }
            
            // 4. Enregistrer dans le pipeline de conversion
            $sqlPipeline = "INSERT INTO conversions_pipeline (
                client_id, canal, etape_initiale, date_etape_initiale,
                etape_actuelle, date_etape_actuelle, devis_id, commercial_responsable_id,
                duree_conversion_jours, observations
            ) VALUES (
                :client_id, 'DIGITAL', 'LEAD', :date_lead,
                :etape_actuelle, NOW(), :devis_id, :commercial_id,
                DATEDIFF(NOW(), :date_lead2), :observations
            )";
            
            $stmtPipeline = $pdo->prepare($sqlPipeline);
            $stmtPipeline->execute([
                'client_id' => $clientId,
                'date_lead' => $lead['date_lead'],
                'etape_actuelle' => $creer_devis ? 'DEVIS' : 'PROSPECT',
                'devis_id' => $devisId,
                'commercial_id' => $lead['utilisateur_responsable_id'],
                'date_lead2' => $lead['date_lead'],
                'observations' => "Conversion lead digital source: {$lead['source']}"
            ]);
            
            $pdo->commit();
            
            $_SESSION['flash_success'] = "Lead converti avec succès en " . ($statut === 'CLIENT' ? 'client' : 'prospect') . " !";
            
            if ($creer_devis) {
                header('Location: ' . url_for('devis/edit.php?id=' . $devisId));
            } else {
                header('Location: ' . url_for('clients/edit.php?id=' . $clientId));
            }
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $erreurs[] = "Erreur lors de la conversion : " . $e->getMessage();
        }
    }
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url_for('digital/leads_list.php') ?>">Leads digitaux</a></li>
            <li class="breadcrumb-item active">Conversion</li>
        </ol>
    </nav>

    <h1 class="h4 mb-4">
        <i class="bi bi-arrow-right-circle"></i>
        Convertir le lead en client/prospect
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
            <!-- Informations du lead -->
            <div class="card mb-4 border-primary">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-info-circle"></i> Informations du lead
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Nom :</strong> <?= htmlspecialchars($lead['nom_prospect']) ?></p>
                            <p><strong>Téléphone :</strong> <?= htmlspecialchars($lead['telephone'] ?? '-') ?></p>
                            <p><strong>Email :</strong> <?= htmlspecialchars($lead['email'] ?? '-') ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Source :</strong> <?= htmlspecialchars($lead['source']) ?></p>
                            <p><strong>Date lead :</strong> <?= date('d/m/Y', strtotime($lead['date_lead'])) ?></p>
                            <p><strong>Produit d'intérêt :</strong> <?= htmlspecialchars($lead['produit_interet'] ?? '-') ?></p>
                        </div>
                    </div>
                    <?php if ($lead['message_initial']): ?>
                        <hr>
                        <p class="mb-0"><strong>Message initial :</strong></p>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($lead['message_initial'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulaire de conversion -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-person-plus"></i> Créer le client/prospect
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                        <div class="row g-3">
                            <!-- Nom -->
                            <div class="col-md-8">
                                <label class="form-label">Nom complet <span class="text-danger">*</span></label>
                                <input type="text" name="nom" class="form-control" 
                                       value="<?= htmlspecialchars($lead['nom_prospect']) ?>" required>
                            </div>

                            <!-- Statut -->
                            <div class="col-md-4">
                                <label class="form-label">Statut <span class="text-danger">*</span></label>
                                <select name="statut" class="form-select" required>
                                    <option value="PROSPECT" selected>Prospect</option>
                                    <option value="CLIENT">Client</option>
                                </select>
                            </div>

                            <!-- Type de client -->
                            <div class="col-md-6">
                                <label class="form-label">Type de client <span class="text-danger">*</span></label>
                                <select name="type_client_id" class="form-select" required>
                                    <?php foreach ($typesClient as $type): ?>
                                        <option value="<?= $type['id'] ?>" 
                                            <?= $type['code'] === 'DIGITAL' ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type['libelle']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Téléphone -->
                            <div class="col-md-3">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" name="telephone" class="form-control" 
                                       value="<?= htmlspecialchars($lead['telephone'] ?? '') ?>">
                            </div>

                            <!-- Email -->
                            <div class="col-md-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($lead['email'] ?? '') ?>">
                            </div>

                            <!-- Adresse -->
                            <div class="col-12">
                                <label class="form-label">Adresse complète</label>
                                <textarea name="adresse" class="form-control" rows="2" 
                                          placeholder="Quartier, ville, détails..."></textarea>
                            </div>

                            <!-- Créer un devis ? -->
                            <div class="col-12">
                                <hr>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="creer_devis" 
                                           value="1" id="creer_devis" checked>
                                    <label class="form-check-label" for="creer_devis">
                                        <strong>Créer automatiquement un devis</strong>
                                    </label>
                                </div>
                                <small class="text-muted">
                                    Un devis vierge sera créé et vous serez redirigé vers l'édition pour ajouter les produits
                                </small>
                            </div>

                            <div class="col-md-6" id="canal_devis_container">
                                <label class="form-label">Canal de vente du devis</label>
                                <select name="canal_vente_id" class="form-select">
                                    <option value="3" selected>Digital / En ligne</option>
                                    <option value="1">Showroom</option>
                                    <option value="2">Terrain</option>
                                </select>
                            </div>

                            <!-- Boutons -->
                            <div class="col-12">
                                <hr>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-check-circle"></i> Convertir en client
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
            <div class="card bg-light">
                <div class="card-header">
                    <i class="bi bi-lightbulb"></i> À propos de la conversion
                </div>
                <div class="card-body">
                    <h6>Que va-t-il se passer ?</h6>
                    <ol class="small">
                        <li>Un nouveau <strong>client</strong> sera créé dans la base</li>
                        <li>Le <strong>lead sera marqué comme converti</strong></li>
                        <li>Les informations du lead seront transférées</li>
                        <li>Si demandé, un <strong>devis vierge</strong> sera créé</li>
                        <li>Un <strong>enregistrement pipeline</strong> sera créé pour suivre la conversion</li>
                    </ol>
                    
                    <hr>
                    
                    <h6>Bonnes pratiques</h6>
                    <ul class="small">
                        <li>Vérifier et compléter les informations</li>
                        <li>Créer un devis si le besoin est clair</li>
                        <li>Planifier un suivi rapide après conversion</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Afficher/masquer le champ canal selon la case à cocher
document.getElementById('creer_devis').addEventListener('change', function() {
    document.getElementById('canal_devis_container').style.display = 
        this.checked ? 'block' : 'none';
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
