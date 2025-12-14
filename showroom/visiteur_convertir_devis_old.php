<?php
// showroom/visiteur_convertir_devis.php - Conversion rapide visiteur → devis
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

            $stmtClient = $pdo->prepare("\
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
                // Vente directe, statut brouillon pour compléter dans l'éditeur
                $numeroVente = 'V-' . date('Ymd-His');
                $stmtVente = $pdo->prepare("\
                    INSERT INTO ventes (numero, date_vente, client_id, canal_vente_id, devis_id,
                                         statut, montant_total_ht, montant_total_ttc, utilisateur_id, commentaires)
                    VALUES (?, CURDATE(), ?, ?, NULL,
                            'EN_ATTENTE_LIVRAISON', 0, 0, ?, ?)
                ");
                $stmtVente->execute([
                    $numeroVente,
                    $client_id,
                    $canalShowroom,
                    $_SESSION['user_id'],
                    "Généré depuis visite showroom du " . date('d/m/Y', strtotime($visiteur['date_visite'])) .
                    "\nProduit d'intérêt : " . ($visiteur['produit_interet'] ?? 'N/A')
                ]);

                $vente_id = $pdo->lastInsertId();

                // Marquer le visiteur comme converti en vente
                $stmtConvert = $pdo->prepare("\
                    UPDATE visiteurs_showroom 
                    SET converti_en_vente = 1, date_conversion = CURDATE()
                    WHERE id = ?
                ");
                $stmtConvert->execute([$visiteur_id]);

                $_SESSION['flash_success'] = "Vente $numeroVente créée avec succès (à compléter)";
                header('Location: ' . url_for('ventes/edit.php?id=' . $vente_id));
                exit;

            } else {
                // Devis
                $annee = date('Y');
                $stmtCount = $pdo->query("SELECT COUNT(*) FROM devis WHERE YEAR(date_devis) = $annee");
                $count = $stmtCount->fetchColumn() + 1;
                $numero = "DEV-$annee-" . str_pad($count, 5, '0', STR_PAD_LEFT);

                $stmtDevis = $pdo->prepare("\
                    INSERT INTO devis (numero, date_devis, client_id, canal_vente_id, 
                                       utilisateur_id, statut, notes_internes)
                    VALUES (?, CURDATE(), ?, ?, ?, 'BROUILLON', ?)
                ");
                $stmtDevis->execute([
                    $numero,
                    $client_id,
                    $canalShowroom,
                    $_SESSION['user_id'],
                    "Généré depuis visite showroom du " . date('d/m/Y', strtotime($visiteur['date_visite'])) . 
                    "\nProduit d'intérêt : " . ($visiteur['produit_interet'] ?? 'N/A')
                ]);

                $devis_id = $pdo->lastInsertId();

                // Marquer le visiteur comme converti en devis
                $stmtConvert = $pdo->prepare("\
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
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">
            <i class="bi bi-arrow-right-circle"></i> Convertir visiteur en devis ou vente
        </h1>
        
        <a href="<?= url_for('showroom/visiteurs_list.php') ?>" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
    </div>

    <!-- Info visiteur -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-person-circle"></i> Informations visiteur
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <strong>Nom :</strong> <?= htmlspecialchars($visiteur['client_nom']) ?>
                </div>
                <div class="col-md-4">
                    <strong>Contact :</strong> <?= htmlspecialchars($visiteur['contact']) ?>
                </div>
                <div class="col-md-4">
                    <strong>Date visite :</strong> <?= date('d/m/Y', strtotime($visiteur['date_visite'])) ?>
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

    <!-- Formulaire conversion -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-file-earmark-text"></i> Créer un devis ou une vente
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= genererCsrf() ?>">
                <input type="hidden" name="action" id="action-field" value="client_existant">
                
                <div class="row g-3">
                    <!-- Carte client existant -->
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <i class="bi bi-person-check"></i> Utiliser un client existant
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Sélectionner un client showroom <span class="text-danger">*</span></label>
                                    <select name="client_id" class="form-select">
                                        <option value="">-- Choisir un client --</option>
                                        <?php foreach ($clients as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= ($client && $c['id'] == $client['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($c['nom']) ?> - 
                                                <?= htmlspecialchars($c['telephone']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="convert_type" value="devis" class="btn btn-success">
                                        <i class="bi bi-check-circle"></i> Créer le devis
                                    </button>
                                    <button type="submit" name="convert_type" value="vente" class="btn btn-primary">
                                        <i class="bi bi-cash-coin"></i> Créer la vente
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Carte nouveau client -->
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <i class="bi bi-person-plus"></i> Créer un nouveau client
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nom <span class="text-danger">*</span></label>
                                        <input type="text" name="nom" class="form-control" 
                                               value="<?= htmlspecialchars($visiteur['client_nom']) ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Prénom</label>
                                        <input type="text" name="prenom" class="form-control">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                                        <input type="text" name="telephone" class="form-control" 
                                               value="<?= htmlspecialchars($visiteur['contact']) ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Adresse</label>
                                        <input type="text" name="adresse" class="form-control">
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="convert_type" value="devis" class="btn btn-success">
                                        <i class="bi bi-plus-circle"></i> Créer le client + devis
                                    </button>
                                    <button type="submit" name="convert_type" value="vente" class="btn btn-primary">
                                        <i class="bi bi-cash-coin"></i> Créer le client + vente
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Déterminer l'action avant soumission (client existant ou nouveau)
document.addEventListener('DOMContentLoaded', function() {
    const actionField = document.getElementById('action-field');
    const form = document.querySelector('form');

    form.querySelectorAll('button[name="convert_type"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const parentCard = btn.closest('.card-body');
            if (parentCard && parentCard.querySelector('select[name="client_id"]')) {
                actionField.value = 'client_existant';
            } else {
                actionField.value = 'creer_client';
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
