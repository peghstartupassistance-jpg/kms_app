<?php
// devis/convertir_en_vente.php
require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_CREER');

global $pdo;

// Base URLs dynamiques, quel que soit le répertoire du projet
$appBasePath    = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\'); // ex : /kms_app ou ''
$devisBasePath  = $appBasePath . '/devis';
$ventesBasePath = $appBasePath . '/ventes';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo "Devis invalide.";
    exit;
}

// Charger le devis
$stmt = $pdo->prepare("SELECT * FROM devis WHERE id = :id");
$stmt->execute(['id' => $id]);
$devis = $stmt->fetch();

if (!$devis) {
    http_response_code(404);
    echo "Devis introuvable.";
    exit;
}

// 1) Vérifier le statut : seuls les devis ACCEPTES sont convertibles
if ($devis['statut'] !== 'ACCEPTE') {
    $_SESSION['flash_success'] = "Seuls les devis acceptés peuvent être convertis en vente.";
    header('Location: ' . $devisBasePath . '/list.php');
    exit;
}

// 2) Vérifier si déjà marqué comme converti
if (!empty($devis['est_converti'])) {
    $_SESSION['flash_success'] = "Ce devis a déjà été converti en vente.";
    header('Location: ' . $devisBasePath . '/list.php');
    exit;
}

// 3) Sécurité supplémentaire : vérifier si une vente existe déjà pour ce devis
$stmt = $pdo->prepare("SELECT id, numero FROM ventes WHERE devis_id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$venteExistante = $stmt->fetch();

if ($venteExistante) {
    // On marque le devis comme converti pour les prochaines fois
    $stmtUpdate = $pdo->prepare("UPDATE devis SET est_converti = 1 WHERE id = :id");
    $stmtUpdate->execute(['id' => $id]);

    $_SESSION['flash_success'] = "Ce devis était déjà lié à la vente " . $venteExistante['numero'] . ".";
    header('Location: ' . $ventesBasePath . '/detail.php?id=' . (int)$venteExistante['id']);
    exit;
}

// 4) Charger les lignes du devis
$stmt = $pdo->prepare("SELECT * FROM devis_lignes WHERE devis_id = :id");
$stmt->execute(['id' => $id]);
$lignes = $stmt->fetchAll();

if (empty($lignes)) {
    $_SESSION['flash_success'] = "Ce devis ne contient aucune ligne.";
    header('Location: ' . $devisBasePath . '/list.php');
    exit;
}

// 5) Utilisateur courant
$utilisateur = utilisateurConnecte();
$userId      = (int)($utilisateur['id'] ?? 0);

// 6) Création de la vente
$numeroVente = 'V-' . date('Ymd-His');

$sqlVente = "
    INSERT INTO ventes (
        numero, date_vente, client_id, canal_vente_id,
        devis_id, statut, montant_total_ht, montant_total_ttc,
        utilisateur_id, commentaires
    ) VALUES (
        :numero, CURDATE(), :client_id, :canal_id,
        :devis_id, 'EN_ATTENTE_LIVRAISON', :mtht, :mtttc,
        :utilisateur_id, :commentaires
    )
";
$stmt = $pdo->prepare($sqlVente);
$stmt->execute([
    'numero'         => $numeroVente,
    'client_id'      => $devis['client_id'],
    'canal_id'       => $devis['canal_vente_id'],
    'devis_id'       => $devis['id'],
    'mtht'           => $devis['montant_total_ht'],
    'mtttc'          => $devis['montant_total_ttc'],
    'utilisateur_id' => $userId,
    'commentaires'   => 'Vente issue du devis ' . $devis['numero'],
]);

$venteId = (int)$pdo->lastInsertId();

// 7) Copier les lignes du devis vers la vente
$stmtLigne = $pdo->prepare("
    INSERT INTO ventes_lignes (
        vente_id, produit_id, quantite,
        prix_unitaire, remise, montant_ligne_ht
    ) VALUES (
        :vente_id, :produit_id, :quantite,
        :prix_unitaire, :remise, :montant_ligne_ht
    )
");
foreach ($lignes as $l) {
    $stmtLigne->execute([
        'vente_id'         => $venteId,
        'produit_id'       => $l['produit_id'],
        'quantite'         => $l['quantite'],
        'prix_unitaire'    => $l['prix_unitaire'],
        'remise'           => $l['remise'],
        'montant_ligne_ht' => $l['montant_ligne_ht'],
    ]);
}

// 8) Marquer le devis comme converti
$stmt = $pdo->prepare("UPDATE devis SET est_converti = 1 WHERE id = :id");
$stmt->execute(['id' => $id]);

$_SESSION['flash_success'] = "Le devis a été converti en vente ($numeroVente).";
header('Location: ' . $ventesBasePath . '/detail.php?id=' . $venteId);
exit;
