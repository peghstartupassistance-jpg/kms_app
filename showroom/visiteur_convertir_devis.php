<?php
// showroom/visiteur_convertir_devis.php - Conversion rapide visiteur → devis/vente
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('DEVIS_CREER');

global $pdo;

$visiteur_id = $_GET['visiteur_id'] ?? null;

if (!$visiteur_id) {
    $_SESSION['flash_error'] = "Visiteur introuvable";
    header('Location: ' . url_for('showroom/visiteurs_list.php'));
    exit;
}

// Charger le visiteur
$stmt = $pdo->prepare("SELECT * FROM visiteurs_showroom WHERE id = ?");
$stmt->execute([$visiteur_id]);
$visiteur = $stmt->fetch();

if (!$visiteur) {
    $_SESSION['flash_error'] = "Visiteur introuvable";
    header('Location: ' . url_for('showroom/visiteurs_list.php'));
    exit;
}

// Si déjà lié à un client, charger ce client
$client = null;
if ($visiteur['client_id']) {
    $stmtClient = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmtClient->execute([$visiteur['client_id']]);
    $client = $stmtClient->fetch();
}

// Liste des clients existants (pour sélection)
$stmtClients = $pdo->query("
    SELECT id, nom, telephone 
    FROM clients 
    WHERE type_client_id IN (SELECT id FROM types_client WHERE code = 'SHOWROOM')
    ORDER BY nom
    LIMIT 500
");
$clients = $stmtClients->fetchAll();

// Traitement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    verifierCsrf($csrf);
    
    $action = $_POST['action'] ?? 'client_existant';
    $convertType = $_POST['convert_type'] ?? 'devis'; // 'devis' ou 'vente'
    $client_id = null;
    
    try {
        if ($action === 'creer_client') {
            // Créer un nouveau client
            $nom = trim($_POST['nom'] ?? '');
            $prenom = trim($_POST['prenom'] ?? '');
            $telephone = trim($_POST['telephone'] ?? '');
            $adresse = trim($_POST['adresse'] ?? '');
            
            if (empty($nom) || empty($telephone)) {
                throw new Exception("Nom et téléphone obligatoires");
            }
            
            // Récupérer le type SHOWROOM
            $stmtType = $pdo->query("SELECT id FROM types_client WHERE code = 'SHOWROOM'");
            $typeShowroom = $stmtType->fetchColumn();
            
            // Le schéma clients n'a pas de colonne prenom : on concatène prénom + nom
            $nomComplet = trim($prenom !== '' ? ($prenom . ' ' . $nom) : $nom);

            $stmtClient = $pdo->prepare("
                INSERT INTO clients (nom, telephone, adresse, type_client_id, statut)
                VALUES (?, ?, ?, ?, 'PROSPECT')
            ");
            $stmtClient->execute([$nomComplet, $telephone, $adresse ?: null, $typeShowroom]);
            $client_id = $pdo->lastInsertId();
            
            // Mettre à jour le visiteur
            $stmtUpdate = $pdo->prepare("UPDATE visiteurs_showroom SET client_id = ? WHERE id = ?");
            $stmtUpdate->execute([$client_id, $visiteur_id]);
            
        } elseif ($action === 'client_existant') {
            $client_id = $_POST['client_id'] ?? null;
            if (!$client_id) {
                throw new Exception("Veuillez sélectionner un client");
            }
            
            // Mettre à jour le visiteur
            $stmtUpdate = $pdo->prepare("UPDATE visiteurs_showroom SET client_id = ? WHERE id = ?");
            $stmtUpdate->execute([$client_id, $visiteur_id]);
        }
        
        // Créer un devis ou une vente
        if ($client_id) {
            // Canal SHOWROOM
            $stmtCanal = $pdo->query("SELECT id FROM canaux_vente WHERE code = 'SHOWROOM'");
            $canalShowroom = $stmtCanal->fetchColumn();

            if ($convertType === 'vente') {
                // Vente directe
                $numeroVente = 'V-' . date('Ymd-His');
                $stmtVente = $pdo->prepare("
                    INSERT INTO ventes (numero, date_vente, client_id, canal_vente_id, devis_id,
                                         statut, montant_total_ht, montant_total_ttc, utilisateur_id, commentaires)
                    VALUES (?, CURDATE(), ?, ?, NULL,
                            'EN_ATTENTE_LIVRAISON', 0, 0, ?, ?)
                ");
                $stmtVente->execute([
                    $numeroVente,
                    $client_id,
                    $canalShowroom,
                    utilisateurConnecte()['id'],
                    "Généré depuis visite showroom du " . date('d/m/Y', strtotime($visiteur['date_visite'])) .
                    "\nProduit d'intérêt : " . ($visiteur['produit_interet'] ?? 'N/A')
                ]);

                $vente_id = $pdo->lastInsertId();

                // Marquer le visiteur comme converti en vente
                $stmtConvert = $pdo->prepare("
                    UPDATE visiteurs_showroom 
                    SET converti_en_vente = 1, date_conversion = CURDATE()
                    WHERE id = ?
                ");
                $stmtConvert->execute([$visiteur_id]);

                $_SESSION['flash_success'] = "Vente $numeroVente créée avec succès";
                header('Location: ' . url_for('ventes/edit.php?id=' . $vente_id));
                exit;

            } else {
                // Devis
                $annee = date('Y');
                $stmtCount = $pdo->query("SELECT COUNT(*) FROM devis WHERE YEAR(date_devis) = $annee");
                $count = $stmtCount->fetchColumn() + 1;
                $numero = "DEV-$annee-" . str_pad($count, 5, '0', STR_PAD_LEFT);

                $stmtDevis = $pdo->prepare("
                    INSERT INTO devis (numero, date_devis, client_id, canal_vente_id, 
                                       utilisateur_id, statut, notes_internes)
                    VALUES (?, CURDATE(), ?, ?, ?, 'BROUILLON', ?)
                ");
                $stmtDevis->execute([
                    $numero,
                    $client_id,
                    $canalShowroom,
                    utilisateurConnecte()['id'],
                    "Généré depuis visite showroom du " . date('d/m/Y', strtotime($visiteur['date_visite'])) . 
                    "\nProduit d'intérêt : " . ($visiteur['produit_interet'] ?? 'N/A')
                ]);

                $devis_id = $pdo->lastInsertId();

                // Marquer le visiteur comme converti en devis
                $stmtConvert = $pdo->prepare("
                    UPDATE visiteurs_showroom 
                    SET converti_en_devis = 1, date_conversion = CURDATE()
                    WHERE id = ?
                ");
                $stmtConvert->execute([$visiteur_id]);

                $_SESSION['flash_success'] = "Devis $numero créé avec succès";
                header('Location: ' . url_for('devis/edit.php?id=' . $devis_id));
                exit;
            }
        }
        
    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Erreur : " . $e->getMessage();
    }
}

include __DIR__ . '/../partials/header.php';
include __DIR__ . '/../partials/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="bi bi-arrow-right-circle text-primary"></i> Convertir visiteur
        </h1>
        <a href="<?= url_for('showroom/visiteurs_list.php') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
    </div>

    <!-- Info visiteur -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>Informations visiteur</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <p class="mb-2"><strong>Nom :</strong> <?= htmlspecialchars($visiteur['client_nom']) ?></p>
                </div>
                <div class="col-md-4">
                    <p class="mb-2"><strong>Contact :</strong> <?= htmlspecialchars($visiteur['contact']) ?></p>
                </div>
                <div class="col-md-4">
                    <p class="mb-2"><strong>Date visite :</strong> <?= date('d/m/Y', strtotime($visiteur['date_visite'])) ?></p>
                </div>
            </div>
            <?php if ($visiteur['produit_interet']): ?>
                <div class="mt-2">
                    <strong>Produit d'intérêt :</strong> <?= htmlspecialchars($visiteur['produit_interet']) ?>
                </div>
            <?php endif; ?>
            <?php if ($visiteur['orientation']): ?>
                <div class="mt-2">
                    <strong>Orientation :</strong> <?= nl2br(htmlspecialchars($visiteur['orientation'])) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Actions de conversion -->
    <div class="row g-4">
        <!-- Option 1: Client existant -->
        <div class="col-lg-6">
            <div class="card h-100 shadow border-primary">
                <div class="card-header bg-primary bg-gradient text-white">
                    <h5 class="mb-0"><i class="bi bi-person-check me-2"></i>OPTION 1 : Client existant</h5>
                </div>
                <div class="card-body p-4">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                        <input type="hidden" name="action" value="client_existant">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold fs-5">Sélectionner un client <span class="text-danger">*</span></label>
                            <select name="client_id" class="form-select form-select-lg" required>
                                <option value="">-- Choisir un client --</option>
                                <?php foreach ($clients as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($client && $c['id'] == $client['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nom']) ?> - <?= htmlspecialchars($c['telephone']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-3">
                            <button type="submit" name="convert_type" value="devis" class="btn btn-success btn-lg py-3">
                                <i class="bi bi-file-earmark-text me-2"></i>
                                <strong>CRÉER UN DEVIS</strong>
                            </button>
                            <button type="submit" name="convert_type" value="vente" class="btn btn-primary btn-lg py-3">
                                <i class="bi bi-cash-coin me-2"></i>
                                <strong>CRÉER UNE VENTE</strong>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Option 2: Nouveau client -->
        <div class="col-lg-6">
            <div class="card h-100 shadow border-success">
                <div class="card-header bg-success bg-gradient text-white">
                    <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>OPTION 2 : Nouveau client</h5>
                </div>
                <div class="card-body p-4">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                        <input type="hidden" name="action" value="creer_client">
                        
                        <div class="row mb-3">
                            <div class="col-8">
                                <label class="form-label fw-bold">Nom <span class="text-danger">*</span></label>
                                <input type="text" name="nom" class="form-control form-control-lg" 
                                       value="<?= htmlspecialchars($visiteur['client_nom']) ?>" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">Prénom</label>
                                <input type="text" name="prenom" class="form-control form-control-lg">
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-7">
                                <label class="form-label fw-bold">Téléphone <span class="text-danger">*</span></label>
                                <input type="text" name="telephone" class="form-control form-control-lg" 
                                       value="<?= htmlspecialchars($visiteur['contact']) ?>" required>
                            </div>
                            <div class="col-5">
                                <label class="form-label">Adresse</label>
                                <input type="text" name="adresse" class="form-control form-control-lg" placeholder="Optionnel">
                            </div>
                        </div>

                        <div class="d-grid gap-3">
                            <button type="submit" name="convert_type" value="devis" class="btn btn-success btn-lg py-3">
                                <i class="bi bi-file-earmark-text me-2"></i>
                                <strong>CRÉER CLIENT + DEVIS</strong>
                            </button>
                            <button type="submit" name="convert_type" value="vente" class="btn btn-primary btn-lg py-3">
                                <i class="bi bi-cash-coin me-2"></i>
                                <strong>CRÉER CLIENT + VENTE</strong>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../partials/footer.php'; ?>
